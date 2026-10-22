<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/SungrowService.php';

/**
 * Plantas Sungrow con VARIOS inversores, y la ventana del dia intradia.
 *
 * Caso real (2026-10-21): 6165627 tiene dos SG33CX y ningun contador.
 * getDeviceList devuelve primero 6165627_1_2_3, que no produce nunca, y despues
 * 6165627_1_1_3, que si. El backend se quedaba con el primero, asi que:
 *   - /plant/power/realtime pisaba curr_power con su 0 W ("Sin produccion"),
 *   - la grafica Diario salia plana (0,00 kWh).
 * Y la vista Diario acababa a las 12:00 porque el fin `yyyy-mm-dd` se leia como
 * 00:00 y se le sumaban 12 h de holgura.
 *
 * Sin red ni BD: los helpers son estaticos y puros, y el flujo completo se prueba
 * con un servicio montado sin constructor y dobles de token/HTTP que graban cada
 * peticion y responden con datos de la forma real de iSolarCloud.
 */
class SungrowMultiInversorTest extends TestCase
{
    private const PARADO     = '6165627_1_2_3';
    private const PRODUCIENDO = '6165627_1_1_3';

    // ------------------------------------------------------------ sumarPotencia

    public function testSumarPotenciaSumaTodosLosInversores()
    {
        $lista = [
            ['device_point' => ['ps_key' => self::PARADO, 'p24' => '0', 'device_time' => '20261021114000']],
            ['device_point' => ['ps_key' => self::PRODUCIENDO, 'p24' => '2740', 'device_time' => '20261021114500']],
        ];
        $r = SungrowService::sumarPotencia($lista);
        $this->assertSame(2740.0, $r['w']);
        // La lectura mas reciente, no la primera.
        $this->assertSame('20261021114500', $r['device_time']);
    }

    /** Un inversor sin dato no cuenta; ninguno con dato -> null (no un 0 inventado). */
    public function testSumarPotenciaIgnoraVaciosYNuncaFuerzaCero()
    {
        $lista = [
            ['device_point' => ['p24' => '--']],
            ['device_point' => ['p24' => '1500.5']],
            ['device_point' => ['p24' => null]],
        ];
        $this->assertSame(1500.5, SungrowService::sumarPotencia($lista)['w']);

        $this->assertNull(SungrowService::sumarPotencia([]));
        $this->assertNull(SungrowService::sumarPotencia([['device_point' => ['p24' => '']], 'basura']));
    }

    public function testHoraPlantaIsoUsaLaHoraLocalDeLaLectura()
    {
        // Octubre (antes del cambio de hora) en Canarias = UTC+1.
        $this->assertSame('2026-10-21T11:45:00+01:00', SungrowService::horaPlantaIso('20261021114500'));
        $this->assertNull(SungrowService::horaPlantaIso(null));
        $this->assertNull(SungrowService::horaPlantaIso('ayer'));
    }

    // ------------------------------------------------------------ ventanaDelDia

    private function ahora(string $local): DateTimeImmutable
    {
        return new DateTimeImmutable($local, new DateTimeZone('Atlantic/Canary'));
    }

    /** Un dia pasado es el dia ENTERO: antes acababa a las 12:00. */
    public function testVentanaDiaPasadoCubreElDiaEntero()
    {
        $v = SungrowService::ventanaDelDia(
            ['start' => '2026-10-20', 'end' => '2026-10-20'],
            $this->ahora('2026-10-21 11:47:00')
        );
        $this->assertSame(['20261020000000', '20261020235959'], $v);
    }

    /** Hoy termina AHORA (hora de la planta, aunque PHP corra en UTC). */
    public function testVentanaHoyTerminaAhora()
    {
        $ahora = $this->ahora('2026-10-21 11:47:05');
        $this->assertSame(
            ['20261021000000', '20261021114705'],
            SungrowService::ventanaDelDia(['start' => '2026-10-21', 'end' => '2026-10-21'], $ahora)
        );
        // Sin fechas: hoy desde las 00:00 hasta ahora.
        $this->assertSame(['20261021000000', '20261021114705'], SungrowService::ventanaDelDia([], $ahora));
        // Un instante en UTC se pasa a la hora de la planta (10:47Z = 11:47 en Canarias).
        $utc = new DateTimeImmutable('2026-10-21 10:47:05', new DateTimeZone('UTC'));
        $this->assertSame('20261021114705', SungrowService::ventanaDelDia([], $utc)[1]);
    }

    /** Un YmdHis completo (Custom) se respeta tal cual; dos dias = de 00:00 a 23:59:59. */
    public function testVentanaRespetaHorasExplicitasYRangosDeDosDias()
    {
        $ahora = $this->ahora('2026-10-21 11:00:00');
        $this->assertSame(
            ['20261019083000', '20261019170000'],
            SungrowService::ventanaDelDia(['start' => '20261019083000', 'end' => '20261019170000'], $ahora)
        );
        $this->assertSame(
            ['20261018000000', '20261019235959'],
            SungrowService::ventanaDelDia(['start' => '2026-10-18', 'end' => '2026-10-19'], $ahora)
        );
    }

    // -------------------------------------------------------- sumarPorTimestamp

    public function testSumarPorTimestampSumaPorInstanteYOrdena()
    {
        $porClave = [
            self::PARADO      => ['p24' => ['20261020120500' => 0.0, '20261020120000' => 0.0]],
            self::PRODUCIENDO => ['p24' => ['20261020120000' => 2700.0, '20261020120500' => 2800.0, '20261020121000' => 2900.0]],
        ];
        $s = SungrowService::sumarPorTimestamp($porClave, ['p24', 'p14']);

        $this->assertSame([
            ['time' => '20261020120000', 'value' => 2700.0],
            ['time' => '20261020120500', 'value' => 2800.0],
            ['time' => '20261020121000', 'value' => 2900.0],
        ], $s['p24']);
        // Un punto que nadie trae sigue presente y vacio (forma de respuesta intacta).
        $this->assertSame([], $s['p14']);
    }

    /** Null solo si NINGUN inversor trae valor en ese instante. */
    public function testSumarPorTimestampNullSoloSiNadieTraeValor()
    {
        $s = SungrowService::sumarPorTimestamp([
            'a' => ['p24' => ['20261020000000' => null, '20261020000500' => null]],
            'b' => ['p24' => ['20261020000000' => null, '20261020000500' => 10.0]],
        ], ['p24']);
        $this->assertNull($s['p24'][0]['value']);
        $this->assertSame(10.0, $s['p24'][1]['value']);
    }

    /** Tensiones y SOC no se suman: media de los que reportan, sin el 0 del parado. */
    public function testPuntosNoAditivosSePromedian()
    {
        $s = SungrowService::sumarPorTimestamp([
            'a' => ['p18' => ['t1' => 0.0, 't2' => 0.0], 'p13141' => ['t1' => 0.6]],
            'b' => ['p18' => ['t1' => 230.0, 't2' => 0.0], 'p13141' => ['t1' => 0.8]],
            'c' => ['p18' => ['t1' => 234.0]],
        ], ['p18', 'p13141']);
        $this->assertSame(232.0, $s['p18'][0]['value']);
        $this->assertSame(0.0, $s['p18'][1]['value']);
        $this->assertEqualsWithDelta(0.7, $s['p13141'][0]['value'], 1e-9);
    }

    /** Con un solo dispositivo la serie es la suya (lo de siempre en plantas de un inversor). */
    public function testUnSoloDispositivoNoCambia()
    {
        $s = SungrowService::sumarPorTimestamp(
            ['x' => ['p24' => ['20261020100000' => 1.5, '20261020100500' => null]]],
            ['p24']
        );
        $this->assertSame([
            ['time' => '20261020100000', 'value' => 1.5],
            ['time' => '20261020100500', 'value' => null],
        ], $s['p24']);
    }

    public function testClavesDeDaTodosLosInversoresYSoloElContador()
    {
        $devs = ['inversor' => self::PARADO, 'inversores' => [self::PARADO, self::PRODUCIENDO], 'contador' => 'm_7_1_1'];
        $this->assertSame([self::PARADO, self::PRODUCIENDO], SungrowService::clavesDe(self::PARADO, $devs));
        $this->assertSame(['m_7_1_1'], SungrowService::clavesDe('m_7_1_1', $devs));
        // Sin la lista (cache antigua) se comporta como antes.
        $this->assertSame(['k'], SungrowService::clavesDe('k', ['inversor' => 'k', 'contador' => null]));
    }

    // ------------------------------------------------- flujo completo con dobles

    /**
     * Monta el servicio sin constructor (que abriria MySQL) y le inyecta un
     * token falso y un HTTP que graba las peticiones y responde con $responder.
     *
     * @param callable(string $path, array $body): array $responder
     */
    private function servicio(callable $responder, array &$peticiones): SungrowService
    {
        $svc = (new ReflectionClass(SungrowService::class))->newInstanceWithoutConstructor();

        $token = new class {
            public function requestWithAutoRefresh($nombre, callable $fn, ?callable $esAuth = null)
            {
                return $fn('token-de-prueba');
            }
        };
        $sungrow = new class($token) {
            private $t;
            public function __construct($t) { $this->t = $t; }
            public function getTokenService() { return $this->t; }
            public function getUrl() { return 'https://sungrow.invalid'; }
            public function getAppkey() { return 'appkey'; }
            public function getSecretKey() { return 'secret'; }
        };
        $http = new class($responder, $peticiones) {
            private $r;
            public $log;
            public function __construct(callable $r, array &$log) { $this->r = $r; $this->log = &$log; }
            public function post($url, $headers = [], $data = '')
            {
                $path = (string) parse_url($url, PHP_URL_PATH);
                $body = json_decode($data, true);
                $this->log[] = ['path' => $path, 'body' => $body];
                return json_encode(($this->r)($path, $body));
            }
        };

        foreach (['sungrow' => $sungrow, 'httpClient' => $http] as $prop => $valor) {
            $p = new ReflectionProperty(SungrowService::class, $prop);
            if (PHP_VERSION_ID < 80100) {
                $p->setAccessible(true);
            }
            $p->setValue($svc, $valor);
        }
        return $svc;
    }

    private static function listaDispositivos(string $psId): array
    {
        return ['result_code' => '1', 'result_data' => ['pageList' => [
            ['ps_key' => $psId . '_1_2_3', 'device_type' => 1, 'device_name' => 'Inverter2'],
            ['ps_key' => $psId . '_1_1_3', 'device_type' => 1, 'device_name' => 'Inverter1'],
            ['ps_key' => $psId . '_9_1_1', 'device_type' => 9, 'device_name' => 'Logger'],
        ]]];
    }

    private static function listaPlantas(string $psId): array
    {
        return ['result_code' => '1', 'result_data' => ['pageList' => [[
            'ps_id' => (int) $psId,
            'curr_power' => ['value' => '2.74', 'unit' => 'kW'],
            'curr_power_update_time' => '2026-10-21T11:40:00+08:00',
        ]]]];
    }

    /** curr_power = suma de los dos inversores, con la hora de la lectura y en UNA llamada. */
    public function testRealtimeSumaLosInversoresEnUnaLlamada()
    {
        $psId = '9100001';
        $peticiones = [];
        $svc = $this->servicio(function ($path, $body) use ($psId) {
            switch ($path) {
                case '/openapi/getPowerStationList': return self::listaPlantas($psId);
                case '/openapi/getDeviceList':       return self::listaDispositivos($psId);
                case '/openapi/getDeviceRealTimeData':
                    return ['result_code' => '1', 'result_data' => ['device_point_list' => [
                        ['device_point' => ['ps_key' => $psId . '_1_2_3', 'p24' => '0', 'device_time' => '20261021114500']],
                        ['device_point' => ['ps_key' => $psId . '_1_1_3', 'p24' => '3010', 'device_time' => '20261021114500']],
                    ]]];
            }
            return [];
        }, $peticiones);

        $r = $svc->getPlantRealtime($psId);

        $this->assertSame(['value' => 3.01, 'unit' => 'kW'], $r['curr_power']);
        $this->assertSame('2026-10-21T11:45:00+01:00', $r['curr_power_update_time']);

        $rt = array_values(array_filter($peticiones, fn($p) => $p['path'] === '/openapi/getDeviceRealTimeData'));
        $this->assertCount(1, $rt, 'una sola llamada para todos los inversores');
        $this->assertSame([$psId . '_1_2_3', $psId . '_1_1_3'], $rt[0]['body']['ps_key_list']);
    }

    /** Si el tiempo real falla, se queda el curr_power de la lista: nunca un 0 forzado. */
    public function testRealtimeSinDatoConservaLaPotenciaDeLaLista()
    {
        $psId = '9100002';
        $peticiones = [];
        $svc = $this->servicio(function ($path) use ($psId) {
            switch ($path) {
                case '/openapi/getPowerStationList': return self::listaPlantas($psId);
                case '/openapi/getDeviceList':       return self::listaDispositivos($psId);
            }
            return ['result_code' => 'E900', 'result_msg' => 'Unauthorized access'];
        }, $peticiones);

        $r = $svc->getPlantRealtime($psId);

        $this->assertSame(['value' => 2.74, 'unit' => 'kW'], $r['curr_power']);
        $this->assertSame('2026-10-21T11:40:00+08:00', $r['curr_power_update_time']);
    }

    /**
     * Diario de un dia pasado: 12 bloques de 2 h de 00:00 a 23:59:59 (antes
     * acababa a las 12:00), todos con los dos inversores, y la serie es la suma.
     */
    public function testGraficaDiaPasadoPideElDiaEnteroATodosLosInversores()
    {
        $psId = '9100003';
        $peticiones = [];
        $svc = $this->servicio(function ($path, $body) use ($psId) {
            if ($path === '/openapi/getDeviceList') {
                return self::listaDispositivos($psId);
            }
            if ($path === '/openapi/getDevicePointMinuteDataList') {
                $ini = $body['start_time_stamp'];
                // Una fila al principio de cada bloque; la frontera se repite
                // en el bloque siguiente y no debe contar doble.
                return ['result_code' => '1', 'result_data' => [
                    $psId . '_1_2_3' => [['time_stamp' => $ini, 'p24' => '0.0']],
                    $psId . '_1_1_3' => [['time_stamp' => $ini, 'p24' => '1000'], ['time_stamp' => $body['end_time_stamp'], 'p24' => '1000']],
                ]];
            }
            return [];
        }, $peticiones);

        $r = $svc->getGraficas($psId, [
            'id' => $psId, 'level' => 'Day', 'date' => '2026-10-20',
            'start' => '2026-10-20', 'end' => '2026-10-20', 'points' => 'p24',
        ]);

        $min = array_values(array_filter($peticiones, fn($p) => $p['path'] === '/openapi/getDevicePointMinuteDataList'));
        $this->assertCount(12, $min);
        $this->assertSame('20261020000000', $min[0]['body']['start_time_stamp']);
        $this->assertSame('20261020235959', $min[11]['body']['end_time_stamp']);
        foreach ($min as $m) {
            $this->assertSame([$psId . '_1_2_3', $psId . '_1_1_3'], $m['body']['ps_key_list']);
        }

        $serie = $r['series']['p24'];
        $this->assertSame('20261020000000', $serie[0]['time']);
        $this->assertSame('20261020235959', end($serie)['time']);
        $tarde = array_values(array_filter($serie, fn($x) => $x['time'] >= '20261020120000'));
        $this->assertNotEmpty($tarde, 'la serie pasa de las 12:00');
        foreach ($serie as $x) {
            $this->assertSame(1000.0, $x['value'], 'suma de los dos inversores, sin duplicar fronteras');
        }
    }

    /** Agregado (Week): la energia diaria es la suma de los inversores. */
    public function testGraficaAgregadaSumaLosInversores()
    {
        $psId = '9100004';
        $peticiones = [];
        $svc = $this->servicio(function ($path) use ($psId) {
            if ($path === '/openapi/getDeviceList') {
                return self::listaDispositivos($psId);
            }
            if ($path === '/openapi/getDevicePointsDayMonthYearDataList') {
                return ['result_code' => '1', 'result_data' => [
                    $psId . '_1_2_3' => ['p1' => [['time_stamp' => '20261019', '1' => '0'], ['time_stamp' => '20261020', '1' => '0']]],
                    $psId . '_1_1_3' => ['p1' => [['time_stamp' => '20261019', '1' => '12000'], ['time_stamp' => '20261020', '1' => '15000']]],
                ]];
            }
            return [];
        }, $peticiones);

        $r = $svc->getGraficas($psId, [
            'id' => $psId, 'level' => 'Week', 'start' => '2026-10-19', 'end' => '2026-10-20', 'points' => 'p1',
        ]);

        $this->assertSame([
            ['time' => '20261019', 'value' => 12000.0],
            ['time' => '20261020', 'value' => 15000.0],
        ], $r['series']['p1']);
        $agr = array_values(array_filter($peticiones, fn($p) => $p['path'] === '/openapi/getDevicePointsDayMonthYearDataList'));
        $this->assertCount(1, $agr);
        $this->assertSame([$psId . '_1_2_3', $psId . '_1_1_3'], $agr[0]['body']['ps_key_list']);
    }
}
