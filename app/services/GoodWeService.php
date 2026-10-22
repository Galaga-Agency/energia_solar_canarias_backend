<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/GoodWe.php';
require_once __DIR__ . '/../services/PrecioService.php';
require_once __DIR__ . '/GoodWeSemsPlus.php';
require_once __DIR__ . '/GoodWeSesionService.php';
require_once __DIR__ . '/CacheApiService.php';
require_once __DIR__ . '/GoodWeCachePolitica.php';


class GoodWeService
{
    private $httpClient;
    private $precioService;
    private GoodWeSesionService $sesion;
    private ?CacheApiService $cache = null;

    public function __construct()
    {
        new GoodWe();
        $this->httpClient = new HttpClient();
        $this->sesion = new GoodWeSesionService(
            new GoodWeSesionDB(), $this->httpClient,
            (string) GoodWe::$url, (string) GoodWe::$account, (string) GoodWe::$pwd
        );
        $this->precioService = new PrecioService();
        $this->cache = new CacheApiService(Conexion::getInstance()->getConexion());
    }

    /** Una sola politica de sesion y reintento para todos los endpoints del portal. */
    private function peticionConSesion(string $metodo, string $url, array $datos = [], bool $formulario = false): string
    {
        $politica = GoodWeCachePolitica::para($url, $datos);
        if ($this->cache === null || $politica === null) {
            return $this->peticionSinCache($metodo, $url, $datos, $formulario);
        }
        // Solo se cachea el JSON del proveedor, antes de calcular precios o filtrar
        // plantas/alertas del usuario. El router comprueba los permisos CADA vez.
        $respuesta = $this->cache->recordarProtegido(
            'GoodWe', GoodWeCachePolitica::identidad((string) GoodWe::$account, $metodo, $url, $datos),
            $politica['ttl'], $politica['max_edad'],
            fn() => json_decode($this->peticionSinCache($metodo, $url, $datos, $formulario), true),
            [GoodWeCachePolitica::class, 'esValida']
        );
        return json_encode($respuesta, JSON_THROW_ON_ERROR);
    }

    private function peticionSinCache(string $metodo, string $url, array $datos, bool $formulario): string
    {
        $sesion = $this->sesion->obtener();
        for ($intento = 0; $intento < 2; $intento++) {
            $headers = [
                'Content-Type: ' . ($formulario ? 'application/x-www-form-urlencoded; charset=UTF-8' : 'application/json'),
                'Token: ' . json_encode($sesion, JSON_THROW_ON_ERROR),
            ];
            $crudo = $metodo === 'GET'
                ? $this->httpClient->get($url, $headers, $datos)
                : $this->httpClient->post($url, $headers, json_encode($datos, JSON_THROW_ON_ERROR));
            $respuesta = json_decode((string) $crudo, true);
            if (!is_array($respuesta)) {
                throw new RuntimeException('GoodWe no devolvio una respuesta JSON valida');
            }
            $sinSesion = in_array((string) ($respuesta['code'] ?? ''), ['100002', GoodWeSemsPlus::SIN_SESION], true);
            if (!$sinSesion) {
                return (string) $crudo;
            }
            if ($intento === 0) {
                $sesion = $this->sesion->renovar($sesion['token']);
            }
        }
        throw new RuntimeException('GoodWe rechazo la sesion renovada');
    }

    //Llamada en tiempo real a la energia que genera la planta, en postman corresponde con la llamada POST GetPowerFlow
    public function getPlantPowerRealtime($powerStationId)
    {
        try {
            $decodedResponse = json_decode($this->peticionConSesion(
                'POST', GoodWe::$url . 'api/v2/PowerStation/GetPowerflow',
                ['PowerStationId' => $powerStationId]
            ), true);
            // SEMS+ sirve de respaldo en plantas sin flujo en SEMS antiguo.
            if (empty($decodedResponse['data']['powerflow'])) {
                try {
                    $plus = $this->peticionSemsPlus('GET', GoodWeSemsPlus::RUTA_FLUJO . '?stationId=' . rawurlencode($powerStationId));
                    $flujo = GoodWeSemsPlus::flujoComoPowerflow($plus);
                    if ($flujo !== null) {
                        $decodedResponse['data']['powerflow'] = $flujo;
                        $decodedResponse['data']['hasPowerflow'] = true;
                        if (isset($plus['_cache'])) $decodedResponse['_cache'] = $plus['_cache'];
                    }
                } catch (Exception $e) {
                    error_log('GoodWe: flujo de SEMS+ no disponible para ' . $powerStationId . ': ' . $e->getMessage());
                }
            }
            return $decodedResponse;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Graficas de la planta, servidas desde SEMS+.
     *
     * El portal antiguo (GetPlantPowerChart / GetChartByPlant) contesta con
     * `lines: null` desde que GoodWe lo dio de baja. Se piden los mismos datos
     * a SEMS+ y GoodWeSemsPlus los devuelve con la forma de siempre.
     *
     * `$data` llega de getChartByPlantCuerpo(): con `full_script` es la curva de
     * potencia del dia; si no, `range` (2/3/4) y `chartIndexId` (3/5/7/8).
     */
    public function GetChartByPlant($data)
    {
        try {
            $planta = (string) ($data['id'] ?? '');
            $fecha = (string) ($data['date'] ?? date('Y-m-d'));

            if (isset($data['full_script'])) {
                $plus = $this->peticionSemsPlus('POST', GoodWeSemsPlus::RUTA_CURVA, GoodWeSemsPlus::cuerpoCurva($planta, $fecha));
                return GoodWeSemsPlus::respuestaCurva($planta, $plus);
            }

            $cuerpo = GoodWeSemsPlus::cuerpoEstadisticas($planta, $data['range'] ?? 2, $fecha);
            $plus = $this->peticionSemsPlus('POST', GoodWeSemsPlus::RUTA_ESTADISTICAS, $cuerpo);
            return GoodWeSemsPlus::respuestaEstadisticas($planta, (string) ($data['chartIndexId'] ?? '3'), $plus);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Peticion al gateway de SEMS+ con el token de CrossLogin.
     *
     * SEMS+ contesta C0602 si el token no vale (caducado, o porque otro login
     * con la misma cuenta lo ha invalidado: GoodWe solo admite una sesion por
     * cuenta). Se hace login y se reintenta UNA vez.
     */
    private function peticionSemsPlus(string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        $base = rtrim($_ENV['GOODWE_PLUS_URL'] ?? 'https://eu-gateway.semsportal.com/web/sems/sems-plant/api', '/') . '/';
        $respuesta = json_decode($this->peticionConSesion($metodo, $base . $ruta, $cuerpo ?? []), true);
        if (($respuesta['code'] ?? null) !== GoodWeSemsPlus::OK) {
            throw new RuntimeException('SEMS+ no pudo completar la consulta');
        }
        return $respuesta;
    }

    public function getRealIncomeStats($plantId)
    {
        $today = new DateTime();
        $fechaHoy = $today->format('Y-m-d');
        $fechaInicioMes = $today->format('Y-m-01');
        $fechaFinMes = $today->format('Y-m-t');

        // Obtener rangos de precios personalizados
        $rangos = $this->precioService->getPreciosPersonalizadosPorPlanta($plantId, "goodwe");

        if (empty($rangos)) {
            return ["planta_id" => $plantId,
            "moneda" => "EUR",
            "status" => "No hay historial de precio en esta planta",
            "total" => array_merge([
                "fecha_inicio" => "0000-00-00",
                "fecha_final" => "0000-00-00",
                "energia_kwh" => 0,
                "ingreso" => 0,
                "ahorro" => 0
            ]),
                "mes_actual" => array_merge([
                "fecha_inicio" => "0000-00-00",
                "fecha_final" => "0000-00-00",
                "energia_kwh" => 0,
                "ingreso" => 0,
                "ahorro" => 0
                ]),
            "hoy" => array_merge([
                "fecha_inicio" => "0000-00-00",
                "fecha_final" => "0000-00-00",
                "energia_kwh" => 0,
                "ingreso" => 0,
                "ahorro" => 0
            ])];
        }

        // Determinar la fecha de inicio más antigua
        $fechaInicioPlanta = null;
        foreach ($rangos as $rango) {
            $fechaInicio = $rango['fecha_inicio'];
            if ($fechaInicioPlanta === null || strtotime($fechaInicio) < strtotime($fechaInicioPlanta)) {
                $fechaInicioPlanta = $fechaInicio;
            }
        }

        // Recorrer hacia atrás en bloques de 30 días
        $fechaLimite = new DateTime($fechaHoy);
        $datosAcumulados = [];

        while (true) {
            $data = [
                'id' => $plantId,
                'date' => $fechaLimite->format('Y-m-d'),
                'range' => '2',
                'chartIndexId' => '3',
                'isDetailFull' => false
            ];

            $energiaData = $this->GetChartByPlant($data);
            $datosAcumulados[] = $energiaData;

            $fechaLimite->modify('-30 days');

            if ($fechaLimite < new DateTime($fechaInicioPlanta)) {
                break;
            }
        }

        // Inicializar totales
        $totalEnergia = $totalIngreso = $totalAhorro = 0;
        $mesEnergia = $mesIngreso = $mesAhorro = 0;
        $hoyEnergia = $hoyIngreso = $hoyAhorro = 0;

        // Procesar los datos acumulados
        $fechaInicioPlantaObj = new DateTime($fechaInicioPlanta);

        foreach ($datosAcumulados as $respuesta) {
            if (!isset($respuesta['data']['lines'])) continue;

            foreach ($respuesta['data']['lines'] as $linea) {
                if (!isset($linea['xy'])) continue;

                foreach ($linea['xy'] as $punto) {
                    $fechaPunto = $punto['x'];
                    $valor = $punto['y'];

                    if ($valor === null) continue;

                    $fechaPuntoObj = new DateTime($fechaPunto);
                    if ($fechaPuntoObj < $fechaInicioPlantaObj) continue;

                    $rangoPrecio = $this->obtenerPrecioPorFecha($fechaPunto, $rangos, $fechaInicioPlanta);

                    if ($linea['name'] === 'PVGeneration') {
                        $totalEnergia += $valor;
                        $totalIngreso += $valor * $rangoPrecio['precio'];
                        $totalAhorro += $valor * $rangoPrecio['precio_ahorro'];

                        if ($fechaPunto >= $fechaInicioMes && $fechaPunto <= $fechaFinMes) {
                            $mesEnergia += $valor;
                            $mesIngreso += $valor * $rangoPrecio['precio'];
                            $mesAhorro += $valor * $rangoPrecio['precio_ahorro'];
                        }

                        if ($fechaPunto === $fechaHoy) {
                            $hoyEnergia += $valor;
                            $hoyIngreso += $valor * $rangoPrecio['precio'];
                            $hoyAhorro += $valor * $rangoPrecio['precio_ahorro'];
                        }
                    }
                }
            }
        }


        // Devolver resultado final
        return [
            'planta_id' => (int) $plantId,
            'moneda' => 'EUR', // puedes cambiarlo si algún rango lo especifica
            'total' => [
                'fecha_inicio' => $fechaInicioPlanta,
                'fecha_final' => $fechaHoy,
                'energia_kwh' => round($totalEnergia, 2),
                'ingreso' => round($totalIngreso, 2),
                'ahorro' => round($totalAhorro, 2)
            ],
            'mes_actual' => [
                'fecha_inicio' => $fechaInicioMes,
                'fecha_final' => $fechaFinMes,
                'energia_kwh' => round($mesEnergia, 2),
                'ingreso' => round($mesIngreso, 2),
                'ahorro' => round($mesAhorro, 2)
            ],
            'hoy' => [
                'fecha_inicio' => $fechaHoy,
                'fecha_final' => $fechaHoy,
                'energia_kwh' => round($hoyEnergia, 2),
                'ingreso' => round($hoyIngreso, 2),
                'ahorro' => round($hoyAhorro, 2)
            ]
        ];
    }



    private function obtenerPrecioPorFecha($fechaPunto, $rangos, $fechaInicioPlanta)
    {
        if ($fechaPunto < $fechaInicioPlanta) return 0;

        foreach ($rangos as $rango) {
            $inicio = $rango['fecha_inicio'];
            $fin = $rango['fecha_final'] ?? date('Y-m-d');
            if ($fechaPunto >= $inicio && $fechaPunto <= $fin) {
                return $rango;
            }
        }

        return 0;
    }

    /**
     * Todas las plantas de la cuenta.
     *
     * GoodWe devuelve como mucho 50 por pagina aunque se le pidan 200, y aqui
     * solo se pedia la primera: con 91 plantas en la cuenta, 41 no salian en
     * la app. Se van pidiendo paginas hasta tener `record` (o hasta $pageSize).
     */
    public function GetAllPlants($page = 1, $pageSize = 200)
    {
        $primera = $this->paginaPlantas($page, $pageSize);
        $total = (int) ($primera['data']['record'] ?? 0);
        if (!isset($primera['data']['list']) || $page != 1) {
            return $primera;
        }

        $lista = $primera['data']['list'];
        $tope = min($total, (int) $pageSize);
        $porPagina = max(count($lista), 1);
        for ($pagina = 2; count($lista) < $tope && $pagina <= 100; $pagina++) {
            $siguiente = $this->paginaPlantas($pagina, $porPagina);
            $mas = $siguiente['data']['list'] ?? [];
            if (!$mas) {
                break;
            }
            $lista = array_merge($lista, $mas);
        }
        $primera['data']['list'] = array_slice($lista, 0, $pageSize);
        if (count($lista) < $tope) {
            // Alguna pagina fallo: que no parezca un listado completo y fresco.
            error_log('GoodWe: listado incompleto, ' . count($lista) . ' de ' . $tope . ' plantas');
            $primera['_cache'] = array_merge(is_array($primera['_cache'] ?? null) ? $primera['_cache'] : [], ['obsoleto' => true, 'incompleto' => true]);
        }
        return $primera;
    }

    //LLamada a una pagina de plantas en postman corresponde a la llamada POST Todas plantas
    private function paginaPlantas($page, $pageSize)
    {
        $data = [
            "key" => "",
            "orderby" => "",
            "powerstation_type" => "",
            "powerstation_status" => "",
            "page_index" => $page,
            "page_size" => $pageSize,
            "adcode" => "",
            "org_id" => "",
            "condition" => ""
        ];

        try {
            return json_decode($this->peticionConSesion(
                'POST', GoodWe::$url . 'api/PowerStationMonitor/QueryPowerStationMonitor', $data
            ), true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //LLamada a todas las plantas en postman corresponde a la llamada POST GetPowerStationWariningInfoByMultiCondition
    public function GetPowerStationWariningInfoByMultiCondition($pageIndex = 1, $pageSize = 200, $status = 3)
    {
        $fechaFin = date('m/d/Y') . ' 23:59:59';
        $fechaInicio = date('m/d/Y', strtotime('-1 month +1 day')) . ' 00:00:00';

        $data = [
            "adcode" => "",
            "township" => "",
            "orgid" => "",
            "stationid" => "",
            "warninglevel" => 7,
            "status" => $status,
            "starttime" => $fechaInicio,
            "endtime" => $fechaFin,
            "page_size" => $pageSize,
            "page_index" => $pageIndex,
            "device_type" => [],
            "fault_classification" => [],
            "standard_faultLevel" => []
        ];

        try {
            return json_decode($this->peticionConSesion(
                'POST', GoodWe::$url . 'api/SmartOperateMaintenance/GetPowerStationWariningInfoByMultiCondition', $data
            ), true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //LLamada a los detalles e la planta en postman corresponde con la llamada POST GetPlantDetailByPowerstation
    public function GetPlantDetailByPowerstationId($powerStationId)
    {
        try {
            // El controlador anterior espera el JSON crudo en este endpoint.
            return $this->peticionConSesion(
                'GET', GoodWe::$url . 'api/v3/PowerStation/GetPlantDetailByPowerstationId',
                ['powerStationId' => $powerStationId], true
            );
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //LLamada a los detalles e la planta en postman corresponde con la llamada POST GetInverterAllPoint
    public function GetInverterAllPoint($powerStationId)
    {
        try {
            return json_decode($this->peticionConSesion(
                'POST', GoodWe::$url . 'api/v3/PowerStation/GetInverterAllPoint',
                ['PowerStationId' => $powerStationId]
            ), true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //LoginUser en postman corresponde con la llamada POST LoginUser
    public function crossLogin()
    {
        try {
            $datos = $this->sesion->obtener();
            return $datos + ['token_renovation' => '', 'expires_at' => $datos['timestamp']];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
