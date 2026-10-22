<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../app/services/CacheApiService.php';
require_once __DIR__ . '/../app/services/GoodWeCachePolitica.php';
require_once __DIR__ . '/../app/services/GoodWeService.php';

class GoodWeCacheMemoria extends CacheApiService
{
    public array $filas = [];
    public int $reloj = 1000000;
    public bool $turno = true;
    public bool $escritura = true;
    public int $liberaciones = 0;
    public ?Closure $alBloquear = null;
    public function __construct() { parent::__construct(null); }
    protected function ahoraMs(): int { return $this->reloj; }
    protected function leer($clave) { return $this->filas[$clave] ?? null; }
    protected function guardar($clave, $proveedor, $ruta, $resp, $ttlSeg, $ahora)
    {
        if (!$this->escritura) return false;
        $this->filas[$clave] = ['respuesta' => json_encode($resp), 'creado_en' => $ahora, 'ttl_seg' => $ttlSeg];
        return true;
    }
    protected function pedirTurno($nombre)
    {
        if ($this->alBloquear) ($this->alBloquear)($this);
        return $this->turno;
    }
    protected function soltarTurno($nombre) { ++$this->liberaciones; }
    protected function borrarClave(string $clave): void { unset($this->filas[$clave]); }
}

class GoodWeCacheSesionPrueba extends GoodWeSesionDB
{
    public function obtener(string $cuentaHash): ?array
    {
        return ['token' => 'token-ficticio', 'uid' => 'uid-ficticio', 'timestamp' => 1000, 'client' => 'ios', 'version' => 'v2', 'language' => 'es'];
    }
}

class GoodWeCacheHttpPrueba extends HttpClient
{
    public array $cuerpos = [];
    public function post($url, $headers = [], $data = '')
    {
        if (str_contains($url, 'CrossLogin')) throw new LogicException('No debe iniciar sesion');
        $this->cuerpos[] = json_decode($data, true);
        return json_encode(['code' => '00000', 'data' => ['dataList' => [
            ['item' => 'proSystemTotalStats', 'statisticsList' => [['date' => '2026-10-19', 'val' => 10]]],
            ['item' => 'proSelfConsumStats', 'statisticsList' => [['date' => '2026-10-19', 'val' => 5]]],
        ]]]);
    }
}

class GoodWeCacheTest extends TestCase
{
    private function recordar(GoodWeCacheMemoria $cache, callable $fetch): array
    {
        return $cache->recordarProtegido('GoodWe', 'peticion', 60, 900, $fetch, [GoodWeCachePolitica::class, 'esValida']);
    }

    public function testUnaConsultaDentroDelTtlNoVuelveAlProveedor(): void
    {
        $cache = new GoodWeCacheMemoria(); $llamadas = 0;
        $fetch = function () use (&$llamadas) { return ['code' => 0, 'data' => ['valor' => ++$llamadas]]; };
        $this->assertFalse($this->recordar($cache, $fetch)['_cache']['cacheado']);
        $cache->reloj += 59000;
        $r = $this->recordar($cache, $fetch);
        $this->assertTrue($r['_cache']['cacheado']);
        $this->assertSame(1, $r['_cache']['esperar_seg']);
        $this->assertSame(1, $llamadas);
        $cache->reloj += 1000;
        $this->assertSame(2, $this->recordar($cache, $fetch)['data']['valor']);
        $this->assertSame(2, $llamadas);
    }

    public function testElTtlEmpiezaAlTerminarUnaConsultaLenta(): void
    {
        $cache = new GoodWeCacheMemoria();
        $this->recordar($cache, function () use ($cache) { $cache->reloj += 30000; return ['code' => 0, 'data' => []]; });
        $cache->reloj += 59000;
        $r = $this->recordar($cache, fn() => throw new LogicException('No debe consultar'));
        $this->assertSame(59, $r['_cache']['edad_seg']);
        $this->assertTrue($r['_cache']['cacheado']);
    }

    public function testUnErrorImponeUnaPausaSinGuardarElErrorComoDato(): void
    {
        $cache = new GoodWeCacheMemoria(); $llamadas = 0;
        $fetch = function () use (&$llamadas) { ++$llamadas; return ['code' => 'C0602', 'data' => null]; };
        $primera = $this->recordar($cache, $fetch);
        $this->assertArrayHasKey('error', $primera);
        $this->assertSame(60, $primera['_cache']['esperar_seg']);
        $this->assertArrayNotHasKey('GoodWe:' . hash('sha256', 'peticion'), $cache->filas);
        $cache->reloj += 59000;
        $this->recordar($cache, $fetch);
        $this->assertSame(1, $llamadas);
        $cache->reloj += 1000;
        $this->recordar($cache, $fetch);
        $this->assertSame(2, $llamadas);
    }

    public function testUnFalloSirveLaUltimaLecturaMarcadaYSuEdadTieneLimite(): void
    {
        $cache = new GoodWeCacheMemoria();
        $this->recordar($cache, fn() => ['code' => 0, 'data' => ['valor' => 12]]);
        $cache->reloj += 61000;
        $vieja = $this->recordar($cache, fn() => throw new RuntimeException('Timeout'));
        $this->assertSame(12, $vieja['data']['valor']);
        $this->assertTrue($vieja['_cache']['obsoleto']);
        $this->assertSame(61, $vieja['_cache']['edad_seg']);
        $cache->reloj += 840000;
        $this->assertArrayHasKey('error', $this->recordar($cache, fn() => ['code' => 999]));
        $this->assertSame(3, $cache->liberaciones);
    }

    public function testSinLockNoSeConsultaNiConCacheNiSinElla(): void
    {
        $cache = new GoodWeCacheMemoria(); $llamadas = 0;
        $fetch = function () use (&$llamadas) { ++$llamadas; return ['code' => 0, 'data' => []]; };
        $cache->turno = false;
        $this->assertArrayHasKey('error', $this->recordar($cache, $fetch));
        $this->assertSame(0, $llamadas);
        $cache->turno = true; $this->recordar($cache, $fetch);
        $cache->reloj += 61000; $cache->turno = false;
        $this->assertTrue($this->recordar($cache, $fetch)['_cache']['obsoleto']);
        $this->assertSame(1, $llamadas);
    }

    public function testReleeLaRespuestaQueGuardoOtroWorker(): void
    {
        $cache = new GoodWeCacheMemoria();
        $cache->alBloquear = static function ($cache) {
            $cache->filas['GoodWe:' . hash('sha256', 'peticion')] = [
                'respuesta' => json_encode(['code' => 0, 'data' => ['otro_worker' => true]]),
                'creado_en' => $cache->reloj, 'ttl_seg' => 60,
            ];
        };
        $r = $this->recordar($cache, fn() => throw new LogicException('No debe consultar'));
        $this->assertTrue($r['data']['otro_worker']);
        $this->assertSame(1, $cache->liberaciones);
    }

    public function testSinPoderGuardarLaPausaNoSeLlamaAlProveedor(): void
    {
        $cache = new GoodWeCacheMemoria(); $cache->escritura = false;
        $r = $this->recordar($cache, fn() => throw new LogicException('No debe consultar'));
        $this->assertArrayHasKey('error', $r);
        $this->assertSame(1, $cache->liberaciones);
    }

    #[DataProvider('tiempos')]
    public function testTiemposSegunElDato(string $ruta, array $params, int $ttl): void
    {
        $this->assertSame($ttl, GoodWeCachePolitica::para('https://goodwe.invalid/' . $ruta, $params, '2026-10-20')['ttl']);
    }

    public static function tiempos(): array
    {
        return [
            ['api/v2/PowerStation/GetPowerflow', [], 60],
            ['stations/flow?stationId=abc', [], 60],
            ['api/v3/PowerStation/GetPlantDetailByPowerstationId', [], 120],
            ['api/SmartOperateMaintenance/GetPowerStationWariningInfoByMultiCondition', [], 120],
            ['api/PowerStationMonitor/QueryPowerStationMonitor', [], 600],
            ['api/v3/PowerStation/GetInverterAllPoint', [], 1800],
            ['v1/hems/power/statisticsAndPreV2', ['endTime' => '2026-10-20 23:59:59'], 300],
            // Ayer sigue abierto: GoodWe consolida datos tardios durante el dia siguiente.
            ['v1/hems/power/statisticsAndPreV2', ['endTime' => '2026-10-19 23:59:59'], 300],
            ['v1/hems/power/statisticsAndPreV2', ['endTime' => '2026-10-18 23:59:59'], 86400],
            ['stations/statistics', ['endTime' => '2026-09-30 23:59:59'], 86400],
            ['stations/statistics', ['endTime' => '2026-12-31 23:59:59'], 300],
            ['stations/statistics', ['endTime' => '2026-02-31 23:59:59'], 300],
        ];
    }

    public function testLoginYEndpointsDesconocidosNoSeCachean(): void
    {
        $this->assertNull(GoodWeCachePolitica::para('https://goodwe.invalid/api/v1/Common/CrossLogin', []));
        $this->assertNull(GoodWeCachePolitica::para('https://goodwe.invalid/nuevo', []));
    }

    public function testLaIdentidadSeparaCuentaPlantaFechaYFiltrosPeroNoElOrdenDelMapa(): void
    {
        $key = fn($cuenta, $url, $datos) => GoodWeCachePolitica::identidad($cuenta, 'POST', $url, $datos);
        $original = $key('a', 'https://eu.invalid/stats', ['id' => 'planta-A', 'fecha' => '2026-10-20', 'status' => 0]);
        $this->assertSame($original, $key('a', 'https://eu.invalid/stats', ['status' => 0, 'fecha' => '2026-10-20', 'id' => 'planta-A']));
        $this->assertNotSame($original, $key('b', 'https://eu.invalid/stats', ['id' => 'planta-A', 'fecha' => '2026-10-20', 'status' => 0]));
        $this->assertNotSame($original, $key('a', 'https://other.invalid/stats', ['id' => 'planta-A', 'fecha' => '2026-10-20', 'status' => 0]));
        foreach ([['id' => 'planta-B'], ['fecha' => '2026-10-19'], ['status' => 1]] as $cambio) {
            $this->assertNotSame($original, $key('a', 'https://eu.invalid/stats', $cambio + ['id' => 'planta-A', 'fecha' => '2026-10-20', 'status' => 0]));
        }
    }

    public function testSoloLasRespuestasBuenasSonCacheables(): void
    {
        foreach ([['code' => '100002', 'data' => []], ['code' => 'C0602', 'data' => []], ['code' => 0, 'hasError' => true, 'data' => []], ['code' => 0, 'error' => 'fallo', 'data' => []]] as $error) {
            $this->assertFalse(GoodWeCachePolitica::esValida($error));
        }
        $this->assertTrue(GoodWeCachePolitica::esValida(['code' => '00000', 'data' => []]));
    }

    public function testLaTraduccionDeSemsPlusConservaLaMetadata(): void
    {
        $meta = ['cacheado' => true, 'edad_seg' => 35];
        $raw = ['data' => [], '_cache' => $meta];
        $this->assertSame($meta, GoodWeSemsPlus::respuestaCurva('p', $raw)['_cache']);
        $this->assertSame($meta, GoodWeSemsPlus::respuestaEstadisticas('p', '3', $raw)['_cache']);
        $this->assertSame($meta, GoodWeSemsPlus::respuestaEstadisticas('p', '7', $raw)['_cache']);
    }

    public function testVariasGraficasCompartenLaConsultaBrutaPeroSeparanPlantaYFecha(): void
    {
        $http = new GoodWeCacheHttpPrueba();
        $api = (new ReflectionClass(GoodWeService::class))->newInstanceWithoutConstructor();
        $sesion = new GoodWeSesionService(new GoodWeCacheSesionPrueba(), $http, 'https://goodwe.invalid/', 'cuenta-ficticia', 'password-ficticio');
        foreach (['httpClient' => $http, 'sesion' => $sesion, 'cache' => new GoodWeCacheMemoria()] as $nombre => $valor) {
            (new ReflectionProperty(GoodWeService::class, $nombre))->setValue($api, $valor);
        }
        $base = ['id' => 'planta-a', 'date' => '2026-10-19', 'range' => 2];
        $produccion = $api->GetChartByPlant($base + ['chartIndexId' => '3']);
        $uso = $api->GetChartByPlant($base + ['chartIndexId' => '5']);
        $this->assertCount(1, $http->cuerpos);
        $this->assertSame('PVGeneration', $produccion['data']['lines'][0]['name']);
        $this->assertSame('SelfUse', $uso['data']['lines'][0]['name']);
        $this->assertTrue($uso['_cache']['cacheado']);
        $api->GetChartByPlant(['id' => 'planta-b'] + $base);
        $api->GetChartByPlant(['date' => '2026-10-18'] + $base);
        $this->assertCount(3, $http->cuerpos);
        $this->assertSame('planta-b', $http->cuerpos[1]['stationId']);
        $this->assertNotSame($http->cuerpos[0]['endTime'], $http->cuerpos[2]['endTime']);
    }
}
