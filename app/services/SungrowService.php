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
                    return $p;
                }
            }
            return null;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // =====================================================================
    // Pendientes de mapear (misma firma que los otros proveedores).
    // Rellenar con el endpoint de iSolarCloud correspondiente cuando se use.
    // =====================================================================

    /**
     * Resumen de alarmas de una planta.
     * NOTA: el plan de API de esta cuenta solo autoriza los CONTADORES de alarmas
     * (vienen en getPowerStationList), no el listado detallado (endpoints E900).
     */
    public function getSiteAlarms($psId, $pageIndex = 1, $pageSize = 200)
    {
        try {
            $item = $this->getPlantRealtime($psId);
            if (!is_array($item)) {
                return ['error' => 'planta_no_encontrada', 'ps_id' => $psId];
            }
            return [
                'ps_id'           => $psId,
                'ps_name'         => $item['ps_name'] ?? null,
                'alarm_count'     => $item['alarm_count'] ?? 0,
                'fault_count'     => $item['fault_count'] ?? 0,
                'ps_fault_status' => $item['ps_fault_status'] ?? null,
                'ps_status'       => $item['ps_status'] ?? null,
                'nota'            => 'Solo resumen (contadores). El listado detallado requiere permisos de API adicionales en iSolarCloud.',
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
     * Serie temporal (grafica) de un punto del inversor de la planta.
     * Endpoint autorizado: getDevicePointMinuteDataList (por eso pasa por el inversor).
     *
     * @param mixed $psId  id de la planta
     * @param array $params [ 'point' => 'p24' (potencia activa W, por defecto),
     *                        'start' => 'YmdHis', 'end' => 'YmdHis',
     *                        'interval' => 5 (minutos) ]
     * @return array ['ps_id','ps_key','point','series' => [ ['time','value'], ... ]]
     */
    public function getGraficas($psId, $params = [])
    {
        try {
            $point    = $params['point'] ?? 'p24';           // p24 = potencia activa (W)
            $interval = (int) ($params['interval'] ?? 5);
            $end      = $params['end']   ?? date('YmdHis');
            $start    = $params['start'] ?? date('YmdHis', strtotime('today'));

            // 1) Localizar el inversor de la planta.
            // Hay dos tipos y la mayoria del parque es del segundo: 1 = "Inverter"
            // (solo fotovoltaica) y 14 = "Hybrid inverter" (fotovoltaica + bateria).
            // Buscar solo el 1 dejaba sin grafica a las plantas con bateria.
            $devs = $this->apiCall('/openapi/getDeviceList', [
                'ps_id' => $psId, 'curPage' => 1, 'size' => 50,
            ]);
            $psKey = null;
            foreach (($devs['result_data']['pageList'] ?? []) as $d) {
                if (in_array((int) ($d['device_type'] ?? 0), self::TIPOS_INVERSOR, true)) {
                    $psKey = $d['ps_key'];
                    break;
                }
            }
            if (!$psKey) {
                return ['error' => 'no_inversor', 'proveedor' => 'Sungrow', 'ps_id' => $psId];
            }

            // 2) La API limita la ventana; troceamos en bloques de 2h (max 24h)
            $series = [];
            $sTs = strtotime(self::parseTs($start));
            $eTs = strtotime(self::parseTs($end));
            if ($eTs <= $sTs) $eTs = $sTs + 3600;
            if ($eTs - $sTs > 24 * 3600) $sTs = $eTs - 24 * 3600; // cap 24h
            $bloque = 2 * 3600;

            for ($ini = $sTs; $ini < $eTs; $ini += $bloque) {
                $fin = min($ini + $bloque, $eTs);
                $resp = $this->apiCall('/openapi/getDevicePointMinuteDataList', [
                    'ps_key_list'      => [$psKey],
                    'points'           => $point,
                    'start_time_stamp' => date('YmdHis', $ini),
                    'end_time_stamp'   => date('YmdHis', $fin),
                    'minute_interval'  => $interval,
                ]);
                $raw = $resp['result_data'][$psKey] ?? [];
                foreach ($raw as $r) {
                    $series[] = [
                        'time'  => $r['time_stamp'] ?? null,
                        'value' => isset($r[$point]) ? (float) $r[$point] : null,
                    ];
                }
            }

            return ['ps_id' => $psId, 'ps_key' => $psKey, 'point' => $point, 'series' => $series];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
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
