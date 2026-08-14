<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/Sigenergy.php';
require_once __DIR__ . '/../utils/SigenergyErrores.php';
require_once __DIR__ . '/CacheApiService.php';

/**
 * Servicio Sigenergy (Sigen Openapi OFICIAL).
 *
 * Usa la API de desarrollador documentada (openapi-eu.sigencloud.com), NO el
 * cliente web que se imitaba antes (api-eu). Autenticacion OAuth por cuenta:
 * el token Bearer lo gestiona ProveedorTokenService (login oficial + cache 12h).
 *
 * Endpoints usados (todos GET, systemId en la ruta):
 *   openapi/system                          -> lista de sistemas (Inventory > System List)
 *   openapi/systems/{id}/summary            -> KPIs (Realtime > System Realtime Data)
 *   openapi/systems/{id}/energyFlow         -> flujo (Realtime > System Energy Flow)
 *   openapi/systems/{id}/history            -> historico (Historical > System Historical Data)
 *   openapi/system/{id}/devices             -> inventario (Inventory > Device List)
 *
 * LIMITES DE FRECUENCIA (de la doc, importantes):
 *   - 10 peticiones/min por cuenta en total.
 *   - summary, energyFlow, history y device list: 1 por ESTACION cada 5 min.
 *   Por eso NO se debe llamar summary/energyFlow por cada planta en la vista lista;
 *   solo en el detalle de una planta.
 */
class SigenergyService
{
    /**
     * Segundos que guardamos cada respuesta.
     *
     * Su limite es de 300 s (1 acceso por estacion cada 5 min) y ponemos 315 A
     * PROPOSITO: si usaramos 300 justos, al caducar nuestra copia su ventana podria
     * no haberse abierto todavia (los relojes no arrancan a la vez) y nos comeriamos
     * un 1201 "Access restriction". Con el margen, cuando volvemos a preguntar su
     * ventana esta abierta seguro.
     */
    private const CACHE_TTL_SEG = 315;

    /**
     * TTL del FLUJO en vivo.
     *
     * El limite 1201 de Sigenergy es por estacion, y con 315 s el diagrama
     * mostraba el mismo numero durante cinco minutos: por rapido que
     * preguntase el navegador, la cache respondia siempre lo mismo y la vista
     * "en tiempo real" no se movia. Diez segundos es lo que se pide aqui.
     *
     * Solo se aplica a energyFlow y summary, que son los que alimentan el
     * diagrama. Los historicos siguen con el TTL largo, porque lo que paso
     * ayer no cambia y ahi el cupo si importa.
     */
    private const CACHE_TTL_VIVO_SEG = 0;

    /**
     * TTL para historicos de fechas YA CERRADAS (24 h).
     *
     * Lo que paso ayer no va a cambiar, asi que volver a preguntarlo cada 315 s es
     * gastar cupo a cambio de nada. Y el cupo importa: el 1201 es por estacion, y cada
     * `level` que mira el usuario (Dia/Semana/Mes/Año) es otra llamada a la MISMA
     * estacion. Con esto, navegar por el historico solo cuesta la primera vez.
     * El dia en curso sigue con el TTL corto porque aun se esta llenando.
     */
    private const CACHE_TTL_HISTORICO_SEG = 86400;

    private $sigenergy;
    private $httpClient;
    private $cache;

    public function __construct()
    {
        $this->sigenergy = new Sigenergy();
        $this->httpClient = new HttpClient();
        $this->cache = new CacheApiService($this->sigenergy->getConexion());
    }

    public function getSigenergy() { return $this->sigenergy; }
    public function getHttpClient() { return $this->httpClient; }
    public function getCache() { return $this->cache; }

    /**
     * GET generico a la Openapi, con CACHE y auto-refresco de token.
     *
     * La cache va aqui, en la unica puerta de salida, asi que cubre todos los
     * endpoints (lista, summary, energyFlow, history, devices) sin repetir codigo:
     * la ruta completa (con query) identifica la peticion, asi que dos plantas o dos
     * fechas distintas se cachean por separado.
     *
     * Devuelve la respuesta decodificada { code, msg, data, _cache }. Algunos
     * endpoints (p.ej. el login) mandan `data` como STRING JSON; lo desanidamos.
     *
     * @return array
     */
    private function apiCall($path, $ttlSeg = null)
    {
        $ttlSeg = $ttlSeg ?? self::CACHE_TTL_SEG;
        $tokenService = $this->sigenergy->getTokenService();
        $url = $this->sigenergy->getUrl() . ltrim($path, '/');

        // La Openapi indica exito con code=0. Un token invalido/caducado llega como
        // HTTP 401, o como code de la familia 11xxx (11002 bloqueo, 11003 auth fallo).
        $isAuthError = function ($code, $body) {
            if ($code == 401) return true;
            $c = is_array($body) ? (int) ($body['code'] ?? 0) : 0;
            return in_array($c, [11002, 11003], true);
        };

        return $this->cache->recordar(
            Sigenergy::NOMBRE,
            $path,
            $ttlSeg,
            function () use ($tokenService, $url, $isAuthError) {
                [, $body] = $tokenService->requestWithAutoRefresh(
                    Sigenergy::NOMBRE,
                    function ($token) use ($url) {
                        $ch = curl_init($url);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_HTTPHEADER => [
                                'Accept: */*',
                                'Content-Type: application/json',
                                'Authorization: Bearer ' . $token,
                                // Sin User-Agent de navegador, CloudFront devuelve 403
                                // antes de llegar al origen (aunque sea la API oficial).
                                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:152.0) Gecko/20100101 Firefox/152.0',
                            ],
                        ]);
                        $r = curl_exec($ch);
                        $hc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        return [$hc, json_decode($r, true)];
                    },
                    $isAuthError
                );

                if (is_array($body) && isset($body['data'])) {
                    $body['data'] = self::desanidar($body['data']);
                }
                return is_array($body) ? $body : ['code' => -1, 'msg' => 'respuesta no valida', 'data' => null];
            },
            // Que se puede cachear lo decide el catalogo de errores, no la cache: asi
            // el significado de cada codigo vive en un unico sitio (SigenergyErrores) y
            // no hay dos listas que se puedan desincronizar.
            function ($resp) {
                if (!is_array($resp) || !array_key_exists('code', $resp)) return false;
                return !SigenergyErrores::esTransitorio($resp['code']);
            }
        );
    }

    /**
     * Deshace el JSON dentro de JSON que devuelve Sigenergy.
     *
     * Su API no es consistente: unos endpoints mandan `data` como objeto normal, otros
     * como un STRING con JSON dentro, y `openapi/system/{id}/devices` manda una LISTA
     * DE STRINGS (y encima cada `attrMap` de dentro es otro string con JSON). Sin esto
     * el frontend recibiria texto donde espera objetos.
     *
     * Solo decodifica strings que de verdad contienen un objeto/lista JSON: asi un
     * campo de texto normal (un nombre, una direccion) se queda como esta.
     */
    private static function desanidar($valor)
    {
        if (is_string($valor)) {
            $t = ltrim($valor);
            if ($t === '' || ($t[0] !== '{' && $t[0] !== '[')) return $valor;
            $d = json_decode($valor, true);
            return $d === null ? $valor : self::desanidar($d);
        }
        if (is_array($valor)) {
            foreach ($valor as $k => $v) $valor[$k] = self::desanidar($v);
        }
        return $valor;
    }

    /**
     * Lista de sistemas de la cuenta (openapi/system).
     *
     * `data` es un array plano de sistemas (no viene paginado por la API), asi que
     * recortamos aqui para que page/pageSize funcionen igual que en los demas
     * proveedores. Cada sistema trae: systemId, systemName, addr, status,
     * onOffGridStatus, gridConnectTime, pvCapacity, batteryCapacity, timeZone.
     */
    public function getAllPlants($page = 1, $pageSize = 2000)
    {
        try {
            $resp = $this->apiCall('openapi/system');
            if (isset($resp['data']) && is_array($resp['data'])) {
                $offset = max(0, ((int) $page - 1) * (int) $pageSize);
                $resp['data'] = array_slice($resp['data'], $offset, max(1, (int) $pageSize));
            }
            return $resp;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Estado en tiempo real de una planta: fusiona summary (KPIs de energia) +
     * energyFlow (diagrama de potencia), igual que el panel de Sigenergy.
     *
     *   summary   -> dailyPowerGeneration, monthlyPowerGeneration,
     *                annualPowerGeneration, lifetimePowerGeneration, lifetimeCo2...
     *   energyFlow-> pvPower, gridPower, loadPower, batteryPower, batterySoc,
     *                evPower, heatPumpPower
     *
     * Cada uno cuenta como 1 acceso/estacion/5min, asi que en la practica el tiempo
     * real de Sigenergy se refresca como mucho cada 5 min (limite de su API).
     * Si una de las dos llamadas falla, se devuelve la otra en vez de perder todo.
     *
     * @param string $systemId systemId oficial (p.ej. VSSKC1768221900)
     */
    /**
     * Solo el `summary`, pensado para el LISTADO de plantas.
     *
     * Distinto de `getPlantRealtime`, que pide summary Y energyFlow: dos
     * llamadas por planta. Eso vale en el detalle de UNA planta, pero en la
     * lista se multiplica por todas y revienta el limite de la cuenta — que es
     * de 10 peticiones por minuto para TODO, no por estacion. Al agotarlo
     * empiezan a fallar tambien las demas llamadas a Sigenergy.
     *
     * Aqui una sola peticion por planta, y con el TTL normal de 315 s en vez
     * del "vivo" de 0: el limite por estacion es de 1 cada 5 minutos, asi que
     * recargar la lista veinte veces seguidas no cuesta ni una peticion mas.
     * Los datos pueden tener hasta 5 minutos, que es justo lo que el proveedor
     * permite.
     *
     * @param string $systemId systemId oficial (p.ej. VSSKC1768221900)
     */
    public function getPlantSummary($systemId)
    {
        try {
            $id = rawurlencode($systemId);

            // Las DOS llamadas, porque cada una trae la mitad del dato:
            //   summary    -> energia (dailyPowerGeneration, kWh)
            //   energyFlow -> potencia instantanea (pvPower, kW)
            // El summary NO incluye pvPower — comprobado en vivo, sus claves
            // son solo totales de energia — asi que pedir solo una dejaba la
            // columna "Potencia actual" vacia en toda la flota Sigenergy.
            //
            // Con el TTL normal de 315 s (su limite es 1 por estacion cada 5
            // min) recargar la lista no cuesta ninguna peticion nueva: la
            // primera vez son dos por planta y a partir de ahi salen de cache.
            $summary = $this->apiCall("openapi/systems/$id/summary", self::CACHE_TTL_SEG);
            $flow    = $this->apiCall("openapi/systems/$id/energyFlow", self::CACHE_TTL_SEG);

            $datosSum  = (isset($summary['code']) && $summary['code'] == 0 && is_array($summary['data'] ?? null))
                ? $summary['data'] : [];
            $datosFlow = (isset($flow['code']) && $flow['code'] == 0 && is_array($flow['data'] ?? null))
                ? $flow['data'] : [];

            if (!$datosSum && !$datosFlow) {
                return $summary;
            }

            $fusion = $datosSum;
            foreach ($datosFlow as $k => $v) {
                if ($v !== null || !array_key_exists($k, $fusion)) { $fusion[$k] = $v; }
            }

            return [
                'code' => 0,
                'msg' => 'success',
                'data' => $fusion,
                '_cache' => $this->cacheMasRestrictiva([$summary, $flow]),
            ];
        } catch (Exception $e) {
            return ['code' => -1, 'msg' => $e->getMessage(), 'data' => null];
        }
    }

    public function getPlantRealtime($systemId)
    {
        try {
            $id = rawurlencode($systemId);
            $flow    = $this->apiCall("openapi/systems/$id/energyFlow", self::CACHE_TTL_VIVO_SEG);
            $summary = $this->apiCall("openapi/systems/$id/summary", self::CACHE_TTL_VIVO_SEG);

            $datosFlow = (isset($flow['code']) && $flow['code'] == 0 && is_array($flow['data'] ?? null))
                ? $flow['data'] : [];
            $datosSum  = (isset($summary['code']) && $summary['code'] == 0 && is_array($summary['data'] ?? null))
                ? $summary['data'] : [];

            if (!$datosFlow && !$datosSum) {
                return $flow; // ninguna respondio: devolvemos el error tal cual
            }

            // Base = KPIs de energia; encima el flujo instantaneo (solo valores no nulos).
            $fusion = $datosSum;
            foreach ($datosFlow as $k => $v) {
                if ($v !== null || !array_key_exists($k, $fusion)) $fusion[$k] = $v;
            }

            // Cada llamada tiene su propia cache; el dato conjunto no se puede refrescar
            // hasta que caduque la MAS lenta de las dos, asi que informamos de esa.
            $meta = $this->cacheMasRestrictiva([$flow, $summary]);

            return ['code' => 0, 'msg' => 'success', 'data' => $fusion, '_cache' => $meta];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * De varias respuestas cacheadas, devuelve el bloque _cache con mas espera
     * pendiente (el que manda para poder refrescar el conjunto).
     */
    private function cacheMasRestrictiva(array $respuestas)
    {
        $peor = null;
        foreach ($respuestas as $r) {
            $m = $r['_cache'] ?? null;
            if (!is_array($m)) continue;
            if ($peor === null || ($m['esperar_seg'] ?? 0) > ($peor['esperar_seg'] ?? 0)) {
                $peor = $m;
            }
        }
        return $peor ?? [
            'cacheado' => false, 'obsoleto' => false, 'edad_seg' => 0,
            'esperar_seg' => 0, 'esperar' => '00:00', 'proxima_actualizacion' => date('c'),
        ];
    }

    /** Detalle de una planta: se toma de la lista de sistemas por systemId. */
    public function getPlantDetails($systemId)
    {
        try {
            $lista = $this->getAllPlants(1, 2000);
            foreach (($lista['data'] ?? []) as $s) {
                if ((string) ($s['systemId'] ?? '') === (string) $systemId) {
                    return $s;
                }
            }
            return null;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Estados (de planta o de equipo) que cuentan como incidencia.
     * Normal / Standby / Idle / Init son operativos: no son incidencia.
     *
     * CUIDADO CON LA DOC: su enum dice "Fault" y "Offline", pero la API devuelve de
     * verdad "Faulty" y "Disconnection" (comprobado contra las plantas reales). Se
     * aceptan las dos grafias por si algun dia lo alinean con lo que documentan.
     * La comparacion se hace en minusculas, asi que aqui van en minusculas.
     */
    private const ESTADOS_INCIDENCIA = [
        'faulty', 'fault',            // averiado
        'disconnection', 'offline',   // sin comunicacion
        'shutdown', 'emergencystopped', 'reset',
    ];

    /**
     * Inventario/equipos de una planta (openapi/system/{id}/devices).
     *
     * Devuelve cada equipo con: serialNumber, deviceType (Inverter, Battery, EVAC...),
     * status, pn, firmwareVersion y attrMap (ya como objeto, no como string) con las
     * caracteristicas segun el tipo: los inversores traen ratedActivePower/ratedVoltage,
     * las baterias ratedEnergy/ratedChargePower...
     */
    public function getInventario($systemId)
    {
        try {
            return $this->apiCall('openapi/system/' . rawurlencode($systemId) . '/devices');
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Serie temporal / historico de una planta (openapi/systems/{id}/history).
     *
     * Parametros:
     *   level (obligatorio): Day | Week | Month | Year | Lifetime.
     *     - Day    -> puntos cada 5 min del dia.
     *     - Month  -> un punto por dia del mes.
     *     - Year   -> un punto por mes.
     *     - Lifetime -> un punto por año (no lleva date).
     *   date (yyyy-MM-dd): dia/mes/año de referencia. Obligatorio salvo Lifetime.
     *
     * La respuesta trae los totales del periodo (powerGeneration, powerToGrid,
     * powerSelfConsumption, powerUse, esCharging, esDischarging...) y un `itemList`
     * con cada punto: dataTime, pvTotalPower, loadPower, toGridPower, fromGridPower,
     * esChargePower, esDischargePower, batSoc, etc.
     *
     * @param string $systemId
     * @param array  $params ['level' => 'Day', 'date' => 'YYYY-MM-DD']
     */
    public function getGraficas($systemId, $params = [])
    {
        try {
            $level = $params['level'] ?? 'Day';
            $date  = $params['date'] ?? date('Y-m-d');
            $q = 'level=' . rawurlencode($level);
            if (strtolower($level) !== 'lifetime') {
                $q .= '&date=' . rawurlencode($date);
            }
            return $this->apiCall(
                'openapi/systems/' . rawurlencode($systemId) . "/history?$q",
                $this->ttlHistorico($level, $date)
            );
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * TTL de un historico segun si el periodo ya esta cerrado.
     *
     * Un periodo cerrado (ayer, el mes pasado, 2025...) es inmutable: se puede guardar
     * 24 h sin riesgo. Si el periodo INCLUYE hoy todavia se esta llenando, asi que se
     * queda con el TTL corto. Lifetime siempre incluye hoy.
     *
     * Se compara contra el FINAL del periodo, no contra la fecha pedida: pedir el
     * "Month" del dia 3 de este mes devuelve el mes en curso, que no esta cerrado.
     */
    private function ttlHistorico($level, $date)
    {
        $ts = strtotime($date);
        if ($ts === false) return self::CACHE_TTL_SEG;

        switch (strtolower($level)) {
            case 'day':   $fin = strtotime('tomorrow', $ts); break;
            case 'week':  $fin = strtotime('monday next week', $ts); break;
            case 'month': $fin = strtotime('first day of next month 00:00', $ts); break;
            case 'year':  $fin = strtotime('first day of january next year 00:00', $ts); break;
            default:      return self::CACHE_TTL_SEG; // lifetime y cualquier otro
        }

        return ($fin !== false && $fin <= strtotime('today'))
            ? self::CACHE_TTL_HISTORICO_SEG
            : self::CACHE_TTL_SEG;
    }

    /**
     * Incidencias de una planta, DEDUCIDAS del estado de sus equipos.
     *
     * IMPORTANTE, para no confundir a quien lea esto: la Openapi oficial NO tiene
     * ningun endpoint REST de alarmas. Las alarmas de verdad (con alarmCode, hora
     * exacta y generation/recovery) solo se entregan por push MQTT, y para eso hace
     * falta suscribirse (seccion Subscription de la doc) y montar un receptor MQTT.
     *
     * Mientras tanto, lo unico consultable de forma sincrona es el `status` de cada
     * equipo del inventario. Eso SI dice si algo va mal (Fault, Offline...), asi que
     * lo exponemos como incidencias. Lo que NO da es el codigo de alarma ni desde
     * cuando lleva fallando: para eso hace falta el MQTT.
     *
     * Por eso cada item lleva `origen: estado_equipo` y la respuesta lleva
     * `alarmas_en_tiempo_real: false`: el frontend debe poder distinguir esto de una
     * alarma real y no dar a entender una precision que no tenemos.
     */
    public function getSiteAlarms($systemId, $pageIndex = 1, $pageSize = 200)
    {
        try {
            $inv = $this->getInventario($systemId);
            if (!SigenergyErrores::esExito($inv)) {
                return $inv; // el error se traduce aguas arriba
            }

            $incidencias = [];

            // 1) Estado de la PLANTA. Hace falta mirarlo aparte: una planta caida
            //    ("Disconnection") tiene sus equipos en "Normal" — el ultimo estado que
            //    se conocio antes de perder la comunicacion. Sin esto, una planta
            //    incomunicada saldria con 0 incidencias, que es justo lo contrario de
            //    la verdad. Sale de la lista, que ya esta cacheada: no cuesta llamada.
            $planta = $this->getPlantDetails($systemId);
            $estadoPlanta = is_array($planta) ? (string) ($planta['status'] ?? '') : '';
            if (in_array(strtolower($estadoPlanta), self::ESTADOS_INCIDENCIA, true)) {
                $incidencias[] = [
                    'systemId'     => $systemId,
                    'serialNumber' => null,
                    'deviceType'   => 'Planta',
                    'status'       => $estadoPlanta,
                    'descripcion'  => self::describirEstado($estadoPlanta, 'Planta'),
                    'origen'       => 'estado_planta',
                ];
            }

            // 2) Estado de cada EQUIPO.
            foreach (($inv['data'] ?? []) as $d) {
                if (!is_array($d)) continue;
                $estado = (string) ($d['status'] ?? '');
                if (!in_array(strtolower($estado), self::ESTADOS_INCIDENCIA, true)) continue;
                $incidencias[] = [
                    'systemId'     => $d['systemId'] ?? $systemId,
                    'serialNumber' => $d['serialNumber'] ?? null,
                    'deviceType'   => $d['deviceType'] ?? null,
                    'status'       => $estado,
                    'descripcion'  => self::describirEstado($estado, $d['deviceType'] ?? 'Equipo'),
                    'origen'       => 'estado_equipo',
                ];
            }

            $total = count($incidencias);
            $offset = max(0, ((int) $pageIndex - 1) * (int) $pageSize);

            return [
                'code' => 0,
                'msg'  => 'success',
                'data' => [
                    'systemId' => $systemId,
                    'total'    => $total,
                    'items'    => array_slice($incidencias, $offset, max(1, (int) $pageSize)),
                    // Deja claro que esto NO son las alarmas push de Sigenergy.
                    'alarmas_en_tiempo_real' => false,
                    'nota' => 'Incidencias deducidas del estado de los equipos. Sigenergy no ofrece '
                        . 'listado de alarmas por REST: las alarmas reales (alarmCode, hora, '
                        . 'generation/recovery) solo llegan por push MQTT previa suscripcion.',
                ],
                '_cache' => $inv['_cache'] ?? null,
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** Texto legible para un estado de planta o de equipo. */
    private static function describirEstado($estado, $tipo)
    {
        $textos = [
            'faulty'           => 'averiado',
            'fault'            => 'averiado',
            'disconnection'    => 'sin comunicacion con la nube',
            'offline'          => 'sin comunicacion con la nube',
            'shutdown'         => 'apagado',
            'emergencystopped' => 'en parada de emergencia',
            'reset'            => 'reiniciandose',
        ];
        $k = strtolower((string) $estado);
        return $tipo . ' ' . ($textos[$k] ?? ('en estado ' . $estado));
    }
}
