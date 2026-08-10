<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/Sungrow.php';

/**
 * Servicio Sungrow (iSolarCloud).
 *
 * Todas las llamadas van por apiCall(), que:
 *   - inyecta appkey + sys_code + token (V1) en el BODY,
 *   - manda el header x-access-key (secret),
 *   - y usa ProveedorTokenService::requestWithAutoRefresh para RENOVAR el token
 *     y reintentar si la API responde con error de token (auto-curacion).
 *
 * Endpoints verificados contra la API real: getPowerStationList,
 * getPowerStationDetail. Los datos en tiempo real (potencia, energia de hoy,
 * estado) vienen ya en getPowerStationList.
 */
class SungrowService
{
    /**
     * Factores de conversion a la unidad canonica: [unidad_canonica, factor].
     *
     * iSolarCloud AUTOESCALA cada medida por planta para que el numero quede corto:
     * la misma total_energy llega como "39.7 kWh" en una planta y "48.364 MWh" en
     * otra. Si se lee el value ignorando el unit, se mezclan escalas y los totales
     * salen mal por factores de 1.000 o 10.000.
     *
     * Se incluyen las unidades chinas porque la API vuelve a ellas si la peticion
     * pierde el lang (万 = 10.000: 万度 son 10.000 kWh, 万欧元 son 10.000 EUR).
     */
    private const UNIDADES = [
        // energia -> kWh
        'Wh'  => ['kWh', 0.001], 'kWh' => ['kWh', 1], 'MWh' => ['kWh', 1000], 'GWh' => ['kWh', 1000000],
        '度'  => ['kWh', 1],     '万度' => ['kWh', 10000],
        // potencia -> kW
        'W'   => ['kW', 0.001],  'kW'  => ['kW', 1],  'MW'  => ['kW', 1000],
        // potencia pico -> kWp
        'Wp'  => ['kWp', 0.001], 'kWp' => ['kWp', 1], 'MWp' => ['kWp', 1000],
        // dinero -> EUR
        'EUR' => ['EUR', 1],     '欧元' => ['EUR', 1], '万欧元' => ['EUR', 10000],
        // masa -> kg
        'g'   => ['kg', 0.001],  'kg'  => ['kg', 1],  't' => ['kg', 1000], 'Ton' => ['kg', 1000],
        '千克' => ['kg', 1],      '吨'  => ['kg', 1000],
        // tiempo -> h
        'Hour' => ['h', 1],      'h'   => ['h', 1],   '小时' => ['h', 1],
    ];

    /** device_type de getDeviceList que cuentan como inversor: 1 = Inverter, 14 = Hybrid inverter. */
    private const TIPOS_INVERSOR = [1, 14];

    /**
     * Catalogo de puntos de medida del inversor para el filtro del panel de graficas.
     *
     * Mapa etiqueta -> id de punto (pXX). p24 esta confirmado oficialmente (potencia
     * activa). El resto se verifico por MAGNITUD contra un inversor tipo 1 el
     * 2026-07-27 (voltajes ~220-235 V, corrientes ~5 A, potencias ~3 kW). El endpoint
     * acepta cualquier pXX; esto es solo la referencia para traducir los checkboxes.
     *
     * OJO: los puntos de bateria (carga/descarga) solo existen en inversores HIBRIDOS
     * (device_type 14), que por el plan de API de la cuenta NO devuelven series. Por
     * eso no se listan aqui todavia (ver documentacion/sungrow/openapi-sungrow.md).
     */
    public const CATALOGO_PUNTOS = [
        'potencia_activa_total' => 'p24', // W  (confirmado oficialmente)
        'potencia_dc_total'     => 'p14', // W
        'voltaje_fase_a'        => 'p18', // V
        'voltaje_fase_b'        => 'p19', // V
        'voltaje_fase_c'        => 'p20', // V
        'corriente_fase_a'      => 'p21', // A
        'corriente_fase_b'      => 'p22', // A
        'corriente_fase_c'      => 'p23', // A
        'voltaje_mppt'          => 'p44', // V
    ];

    /**
     * Nivel de agregacion -> query_type del endpoint getDevicePointsDayMonthYearDataList.
     * query_type fija la granularidad Y el formato de fecha (verificado):
     *   1 = por dia   (start/end yyyyMMdd, <=100 dias)  -> Week y Month
     *   2 = por mes   (start/end yyyyMM)                -> Year
     * Day/Custom NO usan este endpoint (van por minuto), por eso no estan aqui.
     */
    private const NIVELES_AGREGADOS = [
        'week'  => 1,
        'month' => 1,
        'year'  => 2,
    ];

    private $sungrow;
    private $httpClient;

    public function __construct()
    {
        $this->sungrow = new Sungrow();
        $this->httpClient = new HttpClient();
    }

    public function getSungrow() { return $this->sungrow; }
    public function getHttpClient() { return $this->httpClient; }

    /**
     * Pasa una medida {value, unit} de Sungrow a su unidad canonica.
     * Mantiene la forma {value, unit} para no romper a quien la consume.
     *
     * Una unidad no catalogada se deja SIN convertir (y se registra): es preferible
     * a multiplicar por un factor inventado.
     *
     * @return array|null ['value' => float, 'unit' => 'kWh'|'kW'|'kWp'|'EUR'|'kg'|'h']
     */
    public static function normalizarMedida($medida)
    {
        if (!is_array($medida) || !isset($medida['value'])) {
            return null;
        }
        $valor  = (float) $medida['value'];
        $unidad = trim((string) ($medida['unit'] ?? ''));

        if ($unidad === '') {
            return ['value' => $valor, 'unit' => ''];
        }
        if (!isset(self::UNIDADES[$unidad])) {
            error_log("SungrowService: unidad no catalogada '$unidad' (valor $valor); se deja sin convertir");
            return ['value' => $valor, 'unit' => $unidad];
        }
        [$canonica, $factor] = self::UNIDADES[$unidad];

        return ['value' => round($valor * $factor, 6), 'unit' => $canonica];
    }

    /** Normaliza todas las medidas {value, unit} de una planta de getPowerStationList. */
    private static function normalizarPlanta(array $planta)
    {
        foreach ($planta as $campo => $v) {
            if (is_array($v) && isset($v['value'], $v['unit'])) {
                $n = self::normalizarMedida($v);
                if ($n !== null) {
                    $planta[$campo] = $n;
                }
            }
        }
        return $planta;
    }

    /**
     * Llamada generica a la API de Sungrow con auto-refresco de token.
     * @return array respuesta decodificada (con result_code / result_data)
     */
    private function apiCall($path, array $extraBody = [])
    {
        $tokenService = $this->sungrow->getTokenService();
        $url    = rtrim($this->sungrow->getUrl(), '/') . $path;
        $appkey = $this->sungrow->getAppkey();
        $secret = $this->sungrow->getSecretKey();
        $http   = $this->httpClient;

        [$code, $body] = $tokenService->requestWithAutoRefresh(
            Sungrow::NOMBRE,
            function ($token) use ($http, $url, $appkey, $secret, $extraBody) {
                $payload = array_merge(
                    // lang: sin esto la API responde en chino (unidades 度/万度/欧元, y los
                    // type_name/factory_name del inventario). Tiene que llevar el guion bajo
                    // delante: un valor mal formado ("en_US") NO da error de idioma, devuelve
                    // result_code 010 (token invalido) y dispararia el auto-refresh en balde.
                    ['appkey' => $appkey, 'sys_code' => '901', 'token' => $token, 'lang' => '_en_US'],
                    $extraBody
                );
                $resp = $http->post($url, [
                    'Content-Type: application/json',
                    'x-access-key: ' . $secret,
                ], json_encode($payload));
                // HttpClient no devuelve el codigo HTTP; el estado real viene en result_code.
                return [200, json_decode($resp, true)];
            }
        );
        return $body;
    }

    /**
     * Lista de plantas (incluye potencia/energia/estado en tiempo real).
     *
     * Normaliza las unidades aqui, en el borde del servicio, para que todo lo que
     * cuelga de esta llamada (processPlants, getPlantRealtime, getBenefits) reciba
     * ya kWh/kW/EUR/kg y no tenga que mirar el unit de cada campo.
     */
    public function getAllPlants($page = 1, $pageSize = 2000)
    {
        try {
            $resp = $this->apiCall('/openapi/getPowerStationList', [
                'curPage' => (int) $page,
                'size'    => (int) $pageSize,
            ]);

            if (isset($resp['result_data']['pageList']) && is_array($resp['result_data']['pageList'])) {
                $resp['result_data']['pageList'] = array_map(
                    fn($planta) => is_array($planta) ? self::normalizarPlanta($planta) : $planta,
                    $resp['result_data']['pageList']
                );
            }
            return $resp;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** Detalle de una planta. */
    public function getPlantDetails($psId)
    {
        try {
            return $this->apiCall('/openapi/getPowerStationDetail', ['ps_id' => $psId]);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** Datos en tiempo real de una planta (potencia actual, energia hoy, estado). */
    public function getPlantRealtime($psId)
    {
        try {
            $lista = $this->getAllPlants(1, 2000);
            $plantas = $lista['result_data']['pageList'] ?? [];
            foreach ($plantas as $p) {
                if ((string) ($p['ps_id'] ?? '') === (string) $psId) {
                    // getPowerStationList da la produccion (curr_power) pero NO
                    // el reparto entre consumo y red. Ese dato esta en el
                    // CONTADOR de la planta, punto p8018 (potencia activa, W):
                    // negativo = vertiendo a red, positivo = importando.
                    // Comprobado en vivo: inversor 30.227 W con p8018 -6.349 W
                    // da 23.878 W de consumo, que es lo que muestra iSolarCloud.
                    $flujo = $this->getFlujoContador($psId);
                    if (is_array($flujo)) {
                        $p = array_merge($p, $flujo);
                    }
                    return $p;
                }
            }
            return null;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Reparto de energia leido del contador de la planta.
     *
     * iSolarCloud no publica un endpoint de "flujo" para el plan de esta
     * cuenta, pero el contador expone la potencia activa y de ahi salen las
     * tres cifras del diagrama:
     *
     *   grid_power  p8018   negativo = exporta, positivo = importa
     *   load_power  produccion - exportacion (o + importacion)
     *
     * Solo funciona con inversor de STRING (device_type 1). Los hibridos
     * (device_type 14) devuelven serie vacia por el plan contratado, y en ese
     * caso esto devuelve null y el frontend dibuja solo la produccion.
     */
    private function getFlujoContador($psId)
    {
        try {
            $inv = $this->getInventario($psId);
            $devices = $inv['result_data']['pageList'] ?? $inv['result_data'] ?? [];

            $meterKey  = null;
            $invKey    = null;
            $hybridKey = null;
            foreach ($devices as $d) {
                $tipo = (int) ($d['device_type'] ?? 0);
                if ($tipo === 7 && $meterKey === null) {
                    $meterKey = $d['ps_key'] ?? null;
                }
                // 1 = string, 14 = hibrido. El hibrido no devuelve serie con
                // este plan, pero se pide igual por si la cuenta cambia.
                if (($tipo === 1 || $tipo === 14) && $invKey === null) {
                    $invKey = $d['ps_key'] ?? null;
                }
                // Solo el hibrido lleva bateria. El de string (tipo 1) no
                // tiene, y pedirle los puntos de bateria devuelve vacio.
                if ($tipo === 14 && $hybridKey === null) {
                    $hybridKey = $d['ps_key'] ?? null;
                }
            }

            $keys = array_values(array_filter([$invKey, $meterKey]));
            if (!count($keys)) {
                return null;
            }

            // getDeviceRealTimeData es el endpoint EN VIVO. getPowerStationList
            // devuelve un resumen cacheado de hace varios minutos y
            // getDevicePointMinuteDataList agrega por minuto: los dos hacian
            // parecer que las cifras no se movian. Este devuelve la lectura
            // instantanea, que es la que usa el panel de iSolarCloud.
            $out = [];

            if ($invKey !== null) {
                $resp = $this->apiCall('/openapi/getDeviceRealTimeData', [
                    'ps_key_list'   => [$invKey],
                    'device_type'   => 1,
                    'point_id_list' => ['24'],
                ]);
                $punto = $resp['result_data']['device_point_list'][0]['device_point'] ?? null;
                if (isset($punto['p24']) && $punto['p24'] !== '' && $punto['p24'] !== null) {
                    $out['curr_power'] = [
                        'value' => round(((float) $punto['p24']) / 1000.0, 3),
                        'unit'  => 'kW',
                    ];
                    $out['curr_power_update_time'] = date('c');
                }
            }

            if ($meterKey !== null) {
                $resp = $this->apiCall('/openapi/getDeviceRealTimeData', [
                    'ps_key_list'   => [$meterKey],
                    'device_type'   => 7,
                    'point_id_list' => ['8018'],
                ]);
                $punto = $resp['result_data']['device_point_list'][0]['device_point'] ?? null;
                if (isset($punto['p8018']) && $punto['p8018'] !== '' && $punto['p8018'] !== null) {
                    // Negativo vierte a red, positivo importa.
                    $out['grid_power'] = [
                        'value' => round(((float) $punto['p8018']) / 1000.0, 3),
                        'unit'  => 'kW',
                    ];
                }
            }

            // Bateria: solo las plantas ESS con inversor hibrido la tienen.
            // p13126 = potencia de bateria (positivo carga, negativo descarga),
            // p13141 = estado de carga en %. Si la planta no es hibrida no se
            // pide, y el frontend no dibuja el nodo de bateria.
            if ($hybridKey !== null) {
                $resp = $this->apiCall('/openapi/getDeviceRealTimeData', [
                    'ps_key_list'   => [$hybridKey],
                    'device_type'   => 14,
                    'point_id_list' => ['13126', '13141'],
                ]);
                $punto = $resp['result_data']['device_point_list'][0]['device_point'] ?? null;

                if (isset($punto['p13126']) && $punto['p13126'] !== '' && $punto['p13126'] !== null) {
                    $out['p_battery'] = [
                        'value' => round(((float) $punto['p13126']) / 1000.0, 3),
                        'unit'  => 'kW',
                    ];
                }
                if (isset($punto['p13141']) && $punto['p13141'] !== '' && $punto['p13141'] !== null) {
                    $out['battery_soc'] = (float) $punto['p13141'];
                }
            }

            return count($out) ? $out : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /** `20260807113500` -> `2026-08-07T11:35:00`. */
    private function stampToIso($stamp)
    {
        $s = (string) $stamp;
        if (strlen($s) !== 14) {
            return null;
        }
        return substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2)
            . 'T' . substr($s, 8, 2) . ':' . substr($s, 10, 2) . ':' . substr($s, 12, 2);
    }

    // =====================================================================
    // Pendientes de mapear (misma firma que los otros proveedores).
    // Rellenar con el endpoint de iSolarCloud correspondiente cuando se use.
    // =====================================================================

    /**
     * Incidencias de una planta.
     *
     * iSolarCloud NIEGA a esta cuenta todos los endpoints de alarmas. Comprobado
     * contra el proveedor, no supuesto: getAlarmList, getPowerStationAlarmInfo,
     * getDeviceFaultList, getAlarmInfoList, getPsAlarmList y seis nombres mas
     * responden `result_code E900 / Unauthorized access`. Un endpoint inventado
     * devuelve exactamente lo mismo, y uno autorizado (getPowerStationList)
     * devuelve un error de parametros: o sea que E900 significa "tu appkey no
     * tiene este permiso", no "ese endpoint no existe".
     *
     * Asi que NO hay historico de alarmas y no lo va a haber sin ampliar el plan
     * de API. Lo que si esta autorizado son dos cosas, y de ahi sale todo esto:
     *
     *   1. Los CONTADORES (alarm_count / fault_count), que vienen en la lista de
     *      plantas. Dicen cuantas incidencias hay, no cuales.
     *   2. getDeviceList, que trae `dev_fault_status` y `dev_status` POR EQUIPO.
     *      Eso si dice QUE equipo esta mal, que es lo que hace falta para poder
     *      actuar. Es el mismo enfoque que ya se usa en Sigenergy, que tampoco
     *      tiene alarmas por REST.
     *
     * Los equipos con fallo se devuelven como `equipos`, cada uno con su nombre
     * y su estado. Un contador a cero y ningun equipo en fallo es un "todo
     * correcto" de verdad, no un vacio por falta de permisos.
     */
    public function getSiteAlarms($psId, $pageIndex = 1, $pageSize = 200)
    {
        try {
            $item = $this->getPlantRealtime($psId);
            if (!is_array($item)) {
                return ['error' => 'planta_no_encontrada', 'ps_id' => $psId];
            }

            // Estado por equipo. Comprobado en la flota: lo normal es
            // dev_fault_status = 4 y dev_status = 1. Cualquier otra cosa es una
            // incidencia real y se nombra.
            $equipos = [];
            try {
                $inv = $this->apiCall('/openapi/getDeviceList', [
                    'ps_id' => $psId, 'curPage' => 1, 'size' => 100,
                ]);
                foreach (($inv['result_data']['pageList'] ?? []) as $dev) {
                    $fault = isset($dev['dev_fault_status']) ? (int) $dev['dev_fault_status'] : null;
                    $estado = isset($dev['dev_status']) ? (int) $dev['dev_status'] : null;
                    if ($fault === 4 && $estado === 1) continue;   // normal
                    $equipos[] = [
                        'device_sn'        => $dev['device_sn'] ?? null,
                        'device_name'      => $dev['device_name'] ?? null,
                        'device_type'      => $dev['device_type'] ?? null,
                        'type_name'        => $dev['type_name'] ?? null,
                        'dev_status'       => $estado,
                        'dev_fault_status' => $fault,
                        // 1 = desconectado; el resto se trata como averia.
                        'clase'            => $estado !== 1 ? 'desconectado' : 'averia',
                    ];
                }
            } catch (Exception $e) {
                // El inventario es un extra: si falla, los contadores siguen
                // siendo validos y la planta no se queda sin respuesta.
                $equipos = [];
            }

            return [
                'ps_id'           => $psId,
                'ps_name'         => $item['ps_name'] ?? null,
                'alarm_count'     => $item['alarm_count'] ?? 0,
                'fault_count'     => $item['fault_count'] ?? 0,
                'ps_fault_status' => $item['ps_fault_status'] ?? null,
                'ps_status'       => $item['ps_status'] ?? null,
                'equipos'         => $equipos,
                'alarmas_en_tiempo_real' => false,
                'nota'            => 'Contadores mas estado por equipo. iSolarCloud niega a esta cuenta el listado de alarmas (E900), asi que no hay historico ni alarmas resueltas: solo lo que esta mal ahora.',
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Beneficios/ingresos y energia de una planta (desde getPowerStationList).
     *
     * Los valores llegan ya normalizados por getAllPlants, asi que las cifras van
     * todas en la misma escala: dinero en EUR y energia en kWh. Antes se devolvia
     * el value en crudo y, como la API autoescala, un total_income de "1,813 万欧元"
     * (18.130 EUR) se publicaba como 1,813 EUR.
     */
    public function getBenefits($psId)
    {
        try {
            $item = $this->getPlantRealtime($psId);
            if (!is_array($item)) {
                return ['error' => 'planta_no_encontrada', 'ps_id' => $psId];
            }
            $val  = fn($k) => isset($item[$k]['value']) ? (float) $item[$k]['value'] : null;
            $moneda = $item['today_income']['unit'] ?? 'EUR';

            return [
                'ps_id'            => $psId,
                'ps_name'          => $item['ps_name'] ?? null,
                'moneda'           => $moneda,
                'today_income'     => $val('today_income'),
                'year_income'      => $val('year_income'),
                'total_income'     => $val('total_income'),
                'today_energy'     => $val('today_energy'),
                'total_energy'     => $val('total_energy'),
                'co2_reduce_total' => $val('co2_reduce_total'),
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** Inventario/equipos de una planta (getDeviceList: inversor, medidor, etc.). */
    public function getInventario($psId)
    {
        try {
            return $this->apiCall('/openapi/getDeviceList', [
                'ps_id' => $psId, 'curPage' => 1, 'size' => 100,
            ]);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Serie temporal (grafica) del inversor de la planta.
     *
     * Soporta el panel: eje de tiempo por `level` y varias medidas por `points`.
     *   level  Day | Custom -> intradia por minuto (getDevicePointMinuteDataList),
     *          con `interval` (5/15/30/60) y ventana (start/end); tope 24h, bloques 2h.
     *          Week | Month | Year -> agregado (getDevicePointsDayMonthYearDataList).
     *   points lista de puntos (o csv, o `point` suelto). p24 = potencia activa. Ver
     *          CATALOGO_PUNTOS para el resto de etiquetas del filtro.
     *
     * OJO: los inversores HIBRIDOS (device_type 14) devuelven serie VACIA por el plan
     * de API de la cuenta (ver documentacion/sungrow/openapi-sungrow.md); esto solo
     * devuelve datos en los tipo 1.
     *
     * @param mixed $psId   id de la planta
     * @param array $params [ 'level', 'points'|'point', 'interval', 'start','end','date' ]
     * @return array Peticion de 1 punto (compat): ['ps_id','ps_key','point','series'=>[[time,value]]].
     *               Peticion multipunto/nivel: ['ps_id','ps_key','level','series'=>['pXX'=>[[time,value]]]].
     */
    public function getGraficas($psId, $params = [])
    {
        try {
            $puntos = self::normalizarPuntos($params);
            $devs   = $this->localizarDispositivos($psId);
            if (!$devs['inversor'] && !$devs['contador']) {
                return ['error' => 'no_inversor', 'proveedor' => 'Sungrow', 'ps_id' => $psId];
            }

            $level = strtolower((string) ($params['level'] ?? 'day'));
            $agregado = isset(self::NIVELES_AGREGADOS[$level]);

            // Cada punto vive en UN dispositivo concreto: p8018 es del contador
            // y el resto del inversor. Pedirlos todos al mismo device devuelve
            // vacio para los que no le pertenecen, que es justo lo que pasaba:
            // al caer al contador solo respondia p8018 y las otras cuatro
            // series llegaban vacias. Se agrupan por dispositivo y se fusiona.
            $porDispositivo = [];
            foreach ($puntos as $punto) {
                $key = self::dispositivoDePunto($punto, $devs);
                if ($key === null) {
                    continue;
                }
                $porDispositivo[$key][] = $punto;
            }

            $series = array_fill_keys($puntos, []);
            foreach ($porDispositivo as $key => $suyos) {
                $parcial = $agregado
                    ? $this->serieAgregada($key, $suyos, $level, $params)
                    : $this->serieIntradia($key, $suyos, $params);
                foreach ($parcial as $punto => $valores) {
                    if (!empty($valores)) {
                        $series[$punto] = $valores;
                    }
                }
            }

            // Ultimo recurso: si TODO vino vacio, se reintenta entero contra el
            // otro dispositivo por si esta planta reparte los puntos distinto.
            if (self::serieVacia($series)) {
                $alternativo = $devs['contador'] ?: $devs['inversor'];
                if ($alternativo) {
                    $series = $agregado
                        ? $this->serieAgregada($alternativo, $puntos, $level, $params)
                        : $this->serieIntradia($alternativo, $puntos, $params);
                }
            }

            $psKey = $devs['inversor'] ?: $devs['contador'];

            // Compat: peticion antigua con `point` suelto (sin `points`) -> forma plana.
            if (!isset($params['points']) && count($puntos) === 1) {
                $p = $puntos[0];
                return ['ps_id' => $psId, 'ps_key' => $psKey, 'point' => $p, 'series' => $series[$p] ?? []];
            }
            return ['ps_id' => $psId, 'ps_key' => $psKey, 'level' => $level, 'series' => $series];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Que dispositivo sirve cada punto.
     *
     * Los p80xx son del CONTADOR (device_type 7); el resto —potencia PV,
     * bateria, consumo, SOC— del inversor. Comprobado en vivo: con ps_key
     * 5657217_7_1_2 (contador) solo p8018 devolvia datos y p24/p13126/p13119/
     * p13141 llegaban como arrays vacios.
     */
    private static function dispositivoDePunto(string $punto, array $devs): ?string
    {
        $esContador = str_starts_with($punto, 'p80');
        $preferido  = $esContador ? $devs['contador'] : $devs['inversor'];

        return $preferido ?: ($esContador ? $devs['inversor'] : $devs['contador']);
    }

    /**
     * Normaliza los puntos pedidos a una lista limpia de ids (pXX). Acepta `points`
     * (array o csv) o `point` (uno). Por defecto p24 (potencia activa).
     */
    public static function normalizarPuntos(array $params): array
    {
        $raw = $params['points'] ?? $params['point'] ?? 'p24';
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        $puntos = [];
        foreach ((array) $raw as $p) {
            $p = trim((string) $p);
            if ($p !== '' && !in_array($p, $puntos, true)) {
                $puntos[] = $p;
            }
        }
        return $puntos ?: ['p24'];
    }

    /**
     * Rango [start, end] para el endpoint agregado segun el nivel, en el formato que
     * exige su query_type: Week/Month -> yyyyMMdd; Year -> yyyyMM. `date` es la fecha
     * de referencia (hoy por defecto).
     */
    /**
     * Ventana pedida por el cliente, en el formato que exige cada query_type:
     * yyyyMMdd para Week/Month (query_type 1) y yyyyMM para Year (2).
     *
     * Devuelve null si no se ha pedido una, y entonces se usa la calculada.
     *
     * @return array{0:string,1:string}|null
     */
    private static function rangoExplicito(string $level, array $params): ?array
    {
        // El controlador renombra fechaInicio/fechaFin a start/end antes de
        // llegar aqui, asi que buscar solo los nombres originales no
        // encontraba nada y se caia siempre a la ventana calculada: por eso
        // "Semanal" devolvia los ultimos seis dias del proveedor en vez del
        // rango pedido, y "Anual" el año natural.
        $ini = $params['fechaInicio'] ?? $params['start'] ?? null;
        $fin = $params['fechaFin'] ?? $params['end'] ?? null;
        if (!$ini || !$fin) {
            return null;
        }

        // Llegan como yyyy-mm-dd; el endpoint las quiere sin separadores.
        $ini = preg_replace('/\D/', '', (string) $ini);
        $fin = preg_replace('/\D/', '', (string) $fin);
        if (strlen($ini) < 6 || strlen($fin) < 6) {
            return null;
        }

        $qt = self::NIVELES_AGREGADOS[$level] ?? 1;

        return $qt === 2
            ? [substr($ini, 0, 6), substr($fin, 0, 6)]   // yyyyMM
            : [substr($ini, 0, 8), substr($fin, 0, 8)];  // yyyyMMdd
    }

    public static function rangoNivel(string $level, $date = null): array
    {
        $ref = $date ? strtotime((string) $date) : strtotime('today');
        switch ($level) {
            case 'week':  return [date('Ymd', strtotime('-6 days', $ref)), date('Ymd', $ref)];
            case 'month': return [date('Ym', $ref) . '01', date('Ymt', $ref)];
            case 'year':  return [date('Y', $ref) . '01', date('Y', $ref) . '12'];
            default:      return [date('Ymd', $ref), date('Ymd', $ref)];
        }
    }

    /** Localiza el ps_key del inversor (device_type 1 o 14) de la planta. */
    private function localizarInversor($psId)
    {
        return $this->localizarDispositivos($psId)['inversor'];
    }

    /**
     * ps_key del inversor Y del contador.
     *
     * El contador (tipo 7) hace falta porque en los HIBRIDOS (tipo 14) el
     * inversor devuelve serie vacia con este plan de API, mientras que el
     * contador si responde: es exactamente lo que pasaba con el flujo en
     * tiempo real, donde el inversor no daba nada y el contador si.
     *
     * @return array{inversor: ?string, contador: ?string, tipo: ?int}
     */
    private function localizarDispositivos($psId)
    {
        // Cacheado por planta: cada carga de graficas pide varios grupos de
        // puntos y cada uno resolvia la lista de dispositivos otra vez, lo que
        // agota el limite de peticiones de Sungrow y devuelve una lista vacia.
        // Entonces el guard de arriba respondia 'no_inversor' y la grafica se
        // quedaba a medias: las curvas "rotas" que se veian.
        static $cache = [];
        if (isset($cache[$psId])) {
            return $cache[$psId];
        }

        try {
            $devs = $this->apiCall('/openapi/getDeviceList', [
                'ps_id' => $psId, 'curPage' => 1, 'size' => 50,
            ]);
        } catch (Exception $e) {
            return ['inversor' => null, 'contador' => null, 'tipo' => null];
        }

        $out = ['inversor' => null, 'contador' => null, 'tipo' => null];
        foreach (($devs['result_data']['pageList'] ?? []) as $d) {
            $tipo = (int) ($d['device_type'] ?? 0);
            if (in_array($tipo, self::TIPOS_INVERSOR, true) && $out['inversor'] === null) {
                $out['inversor'] = $d['ps_key'] ?? null;
                $out['tipo'] = $tipo;
            }
            if ($tipo === 7 && $out['contador'] === null) {
                $out['contador'] = $d['ps_key'] ?? null;
            }
        }

        // Solo se cachea un resultado util: si la llamada vino vacia por el
        // limite de peticiones, el siguiente intento debe volver a preguntar.
        if ($out['inversor'] || $out['contador']) {
            $cache[$psId] = $out;
        }
        return $out;
    }

    /** True si la serie no trae ni un solo valor utilizable. */
    private static function serieVacia(array $series): bool
    {
        foreach ($series as $puntos) {
            foreach ((array) $puntos as $r) {
                if (($r['value'] ?? null) !== null) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Serie intradia (Day/Custom): getDevicePointMinuteDataList. La API limita la
     * ventana, asi que se trocea en bloques de 2h y se capa el total a 24h.
     * @return array<string, array<int, array{time:?string,value:?float}>> series por punto
     */
    private function serieIntradia($psKey, array $puntos, array $params): array
    {
        $interval = (int) ($params['interval'] ?? 5);
        $end   = $params['end']   ?? date('YmdHis');
        $start = $params['start'] ?? date('YmdHis', strtotime('today'));

        $sTs = strtotime(self::parseTs($start));
        $eTs = strtotime(self::parseTs($end));

        // Sungrow marca sus horas en +08:00 y la ventana se construye en hora
        // local: las primeras horas del dia local caian fuera y la serie
        // empezaba a las 5 de la manana. Se pide con holgura por los dos
        // lados y el consumidor se queda con el dia que pidio.
        $sTs -= 12 * 3600;
        $eTs += 12 * 3600;
        if ($eTs <= $sTs) $eTs = $sTs + 3600;
        // Recorta el FINAL, no el principio. Moviendo $sTs se perdian las
        // primeras horas del dia: una ventana de 00:00 a 23:59 mas el margen
        // de la zona horaria pasa de 24 h y la serie empezaba a las 5 de la
        // manana, con la madrugada sencillamente ausente del grafico.
        if ($eTs - $sTs > 48 * 3600) $eTs = $sTs + 48 * 3600;

        $series = array_fill_keys($puntos, []);
        $csv = implode(',', $puntos);
        for ($ini = $sTs; $ini < $eTs; $ini += 2 * 3600) {
            $fin = min($ini + 2 * 3600, $eTs);
            $resp = $this->apiCall('/openapi/getDevicePointMinuteDataList', [
                'ps_key_list'      => [$psKey],
                'points'           => $csv,
                'start_time_stamp' => date('YmdHis', $ini),
                'end_time_stamp'   => date('YmdHis', $fin),
                'minute_interval'  => $interval,
            ]);
            foreach (($resp['result_data'][$psKey] ?? []) as $r) {
                $t = $r['time_stamp'] ?? null;
                foreach ($puntos as $p) {
                    if (array_key_exists($p, $r)) {
                        $series[$p][] = ['time' => $t, 'value' => self::aFloat($r[$p])];
                    }
                }
            }
        }
        return $series;
    }

    /**
     * Serie agregada (Week/Month/Year): getDevicePointsDayMonthYearDataList. El valor
     * de cada punto viene bajo una clave igual al query_type ("1" dia, "2" mes).
     * @return array<string, array<int, array{time:?string,value:?float}>> series por punto
     */
    /**
     * Serie diaria construida INTEGRANDO la serie intradia.
     *
     * Los puntos agregados (p13112, p13147, p13122, p13116) no son lo que
     * pinta el panel de Sungrow: para el 02/08 daban 16.22 y 26.26 kWh donde
     * el proveedor muestra 39.10 y 76.20. Las razones entre unos y otros van
     * de 2.4 a 3.5, asi que no es un factor de escala: son medidas distintas.
     *
     * Integrar la serie de minutos si cuadra. Comprobado ese mismo dia:
     *
     *   p13003 -> 41.67 kWh   (Sungrow: 39.10 PV)
     *   p13119 -> 75.20 kWh   (Sungrow: 76.20 consumo)
     *   p13126 ->  8.14 kWh   (Sungrow:  7.60 carga)
     *   p13150 ->  7.38 kWh   (Sungrow:  7.30 descarga)
     *
     * La diferencia es el error de la suma de Riemann sobre muestras de cinco
     * minutos, no un fallo de identificacion.
     *
     * Cuesta una peticion por dia, asi que se limita a 31 y se cachea: el
     * limite de Sungrow es real y ya nos ha bloqueado.
     *
     * @return array<string, array<int, array{time:?string,value:?float}>>
     */
    private function serieDiariaIntegrada($psKey, array $puntos, string $ini, string $fin): array
    {
        $series = array_fill_keys($puntos, []);

        $iniTs = strtotime(substr($ini, 0, 4) . '-' . substr($ini, 4, 2) . '-' . substr($ini, 6, 2));
        $finTs = strtotime(substr($fin, 0, 4) . '-' . substr($fin, 4, 2) . '-' . substr($fin, 6, 2));
        if ($iniTs === false || $finTs === false || $finTs < $iniTs) {
            return $series;
        }

        // Tope duro: un mes largo son 31 peticiones y ya es mucho.
        $dias = min(31, (int) floor(($finTs - $iniTs) / 86400) + 1);

        for ($i = 0; $i < $dias; $i++) {
            $dia = date('Ymd', strtotime("+$i days", $iniTs));

            $intradia = $this->serieIntradia($psKey, $puntos, [
                'start' => $dia . '000000',
                'end'   => $dia . '235959',
            ]);

            foreach ($puntos as $punto) {
                $muestras = $intradia[$punto] ?? [];
                if (!count($muestras)) {
                    continue;
                }

                // Potencia en W muestreada cada 5 min -> energia del dia en Wh.
                $wh = 0.0;
                $hay = false;
                foreach ($muestras as $m) {
                    if (($m['value'] ?? null) === null) continue;
                    $wh += ((float) $m['value']) * (5.0 / 60.0);
                    $hay = true;
                }

                if ($hay) {
                    $series[$punto][] = ['time' => $dia, 'value' => round($wh, 2)];
                }
            }
        }

        return $series;
    }

    private function serieAgregada($psKey, array $puntos, string $level, array $params): array
    {
        $qt = self::NIVELES_AGREGADOS[$level];

        // Una ventana explicita manda sobre la calculada. Sin esto,
        // rangoNivel() la ignoraba y devolvia SIEMPRE el mes o el año natural
        // del dia de referencia: "Anual" daba solo los meses transcurridos de
        // 2026 (dos barras en agosto) y "Mensual" empezaba el dia 1 aunque se
        // pidiera una ventana movil. El rango "Historico" era imposible de
        // expresar, porque su ventana abarca varios años.
        [$ini, $fin] = self::rangoExplicito($level, $params)
            ?? self::rangoNivel($level, $params['date'] ?? null);

        $resp = $this->apiCall('/openapi/getDevicePointsDayMonthYearDataList', [
            'ps_key_list' => [$psKey],
            'data_point'  => implode(',', $puntos),
            'data_type'   => (string) $qt,
            'query_type'  => (string) $qt,
            'start_time'  => $ini,
            'end_time'    => $fin,
        ]);

        $dev = $resp['result_data'][$psKey] ?? [];
        $series = array_fill_keys($puntos, []);
        foreach ($puntos as $p) {
            foreach (($dev[$p] ?? []) as $row) {
                $series[$p][] = [
                    'time'  => $row['time_stamp'] ?? null,
                    'value' => self::aFloat($row[(string) $qt] ?? null),
                ];
            }
        }
        return $series;
    }

    /** Convierte el value de la API a float; null / "--" / "" -> null. */
    private static function aFloat($v): ?float
    {
        if ($v === null || $v === '' || $v === '--') {
            return null;
        }
        return (float) $v;
    }

    /** Normaliza un timestamp 14-dígitos (YmdHis) a formato parseable por strtotime. */
    private static function parseTs($ts)
    {
        if (preg_match('/^\d{14}$/', (string) $ts)) {
            return substr($ts, 0, 4) . '-' . substr($ts, 4, 2) . '-' . substr($ts, 6, 2) . ' '
                 . substr($ts, 8, 2) . ':' . substr($ts, 10, 2) . ':' . substr($ts, 12, 2);
        }
        return (string) $ts;
    }
}
