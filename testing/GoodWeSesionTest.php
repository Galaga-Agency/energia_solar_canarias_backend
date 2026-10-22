<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/GoodWeService.php';

/** Dobles sin conexion a GoodWe ni a MySQL. */
class GoodWeAlmacenPrueba extends GoodWeSesionDB
{
    public ?array $sesion = null;
    public int $bloqueos = 0;
    public int $liberaciones = 0;
    public int $escrituras = 0;
    public bool $fallarGuardado = false;
    public ?Closure $alBloquear = null;
    public ?int $edad = null;

    public function segundosDesdeGuardado(string $cuentaHash): ?int { return $this->edad; }

    public function obtener(string $cuentaHash): ?array { return $this->sesion; }
    public function bloquear(string $cuentaHash): void
    {
        ++$this->bloqueos;
        if ($this->alBloquear) { ($this->alBloquear)($this); }
    }
    public function liberar(string $cuentaHash): void { ++$this->liberaciones; }
    public function guardar(string $cuentaHash, array $cabecera): void
    {
        ++$this->escrituras;
        if ($this->fallarGuardado) { throw new RuntimeException('Fallo de persistencia simulado'); }
        $this->sesion = $cabecera;
    }
}

class GoodWeHttpPrueba extends HttpClient
{
    public array $respuestas = [];
    public array $peticiones = [];

    public function post($url, $headers = [], $data = '') { return $this->responder('POST', $url, $headers, $data); }
    public function get($url, $headers = [], $data = []) { return $this->responder('GET', $url, $headers, $data); }
    private function responder($metodo, $url, $headers, $data): string
    {
        $this->peticiones[] = compact('metodo', 'url', 'headers', 'data');
        if (!$this->respuestas) { throw new LogicException('Peticion inesperada: ' . $url); }
        $respuesta = array_shift($this->respuestas);
        if ($respuesta instanceof Throwable) { throw $respuesta; }
        return is_string($respuesta) ? $respuesta : json_encode($respuesta);
    }
}

class GoodWeSesionTest extends TestCase
{
    private GoodWeAlmacenPrueba $almacen;
    private GoodWeHttpPrueba $http;

    protected function setUp(): void
    {
        $this->almacen = new GoodWeAlmacenPrueba();
        $this->http = new GoodWeHttpPrueba();
    }

    private function cabecera(string $token = 'token-prueba'): array
    {
        return ['uid' => 'uid-prueba', 'timestamp' => 1792400000000, 'token' => $token,
            'client' => 'web', 'version' => 'version-del-login', 'language' => 'es'];
    }

    private function servicio(): GoodWeSesionService
    {
        return new GoodWeSesionService($this->almacen, $this->http, 'https://goodwe.invalid/', 'cuenta@example.invalid', 'password-prueba');
    }

    public function testReutilizaLaSesionPersistidaInclusoEnOtroProceso(): void
    {
        $this->http->respuestas = [['hasError' => false, 'data' => $this->cabecera()]];
        $primera = $this->servicio()->obtener();
        $segunda = $this->servicio()->obtener();
        $this->assertSame($this->cabecera(), $primera);
        $this->assertSame($primera, $segunda);
        $this->assertCount(1, $this->http->peticiones);
        $this->assertSame(1, $this->almacen->escrituras);
        $this->assertSame(['account' => 'cuenta@example.invalid', 'pwd' => 'password-prueba'], json_decode($this->http->peticiones[0]['data'], true));
    }

    public function testLaFechaDeEmisionNoSeInterpretaComoCaducidad(): void
    {
        $this->almacen->sesion = $this->cabecera();
        $this->almacen->sesion['timestamp'] = 1000;
        $this->assertSame(1000, $this->servicio()->obtener()['timestamp']);
        $this->assertCount(0, $this->http->peticiones);
    }

    public function testElTokenRechazadoSeSustituyeEnElAlmacen(): void
    {
        $this->almacen->sesion = $this->cabecera('viejo');
        $this->http->respuestas = [['hasError' => false, 'data' => $this->cabecera('nuevo')]];
        $this->assertSame('nuevo', $this->servicio()->renovar('viejo')['token']);
        $this->assertSame('nuevo', $this->almacen->sesion['token']);
        $this->assertSame(1, $this->almacen->liberaciones);
    }

    public function testReleeBajoElBloqueoYReutilizaLaRenovacionConcurrente(): void
    {
        $this->almacen->sesion = $this->cabecera('viejo');
        $nuevo = $this->cabecera('nuevo-de-otra-peticion');
        $this->almacen->alBloquear = static function ($almacen) use ($nuevo) { $almacen->sesion = $nuevo; };
        $this->assertSame($nuevo, $this->servicio()->renovar('viejo'));
        $this->assertCount(0, $this->http->peticiones);
        $this->assertSame(0, $this->almacen->escrituras);
        $this->assertSame(1, $this->almacen->liberaciones);
    }

    public function testPrimerLoginConcurrenteTampocoSeDuplica(): void
    {
        $nuevo = $this->cabecera();
        $this->almacen->alBloquear = static function ($almacen) use ($nuevo) { $almacen->sesion = $nuevo; };
        $this->assertSame($nuevo, $this->servicio()->obtener());
        $this->assertCount(0, $this->http->peticiones);
    }

    #[DataProvider('loginsInvalidos')]
    public function testLoginInvalidoConservaLaSesionAnteriorYLiberaElBloqueo($respuesta): void
    {
        $vieja = $this->cabecera('viejo');
        $this->almacen->sesion = $vieja;
        $this->http->respuestas = [$respuesta];
        try {
            $this->servicio()->renovar('viejo');
            $this->fail('El login invalido no puede usarse');
        } catch (RuntimeException $e) {
            $this->assertSame($vieja, $this->almacen->sesion);
            $this->assertSame(0, $this->almacen->escrituras);
            $this->assertSame(1, $this->almacen->liberaciones);
        }
    }

    public static function loginsInvalidos(): array
    {
        return [
            'credenciales rechazadas' => [['hasError' => true, 'data' => []]],
            'token vacio' => [['hasError' => false, 'data' => ['uid' => 'uid', 'timestamp' => 1000, 'token' => '']]],
            'sin uid' => [['hasError' => false, 'data' => ['timestamp' => 1000, 'token' => 'token']]],
            'sin timestamp' => [['hasError' => false, 'data' => ['uid' => 'uid', 'token' => 'token']]],
            'no JSON' => ['<html>Error</html>'],
            'fallo de red' => [new RuntimeException('Fallo de transporte simulado')],
        ];
    }

    public function testNoDevuelveUnTokenQueNoSeHaPodidoGuardar(): void
    {
        $vieja = $this->cabecera('viejo');
        $this->almacen->sesion = $vieja;
        $this->almacen->fallarGuardado = true;
        $this->http->respuestas = [['hasError' => false, 'data' => $this->cabecera('nuevo')]];
        try {
            $this->servicio()->renovar('viejo');
            $this->fail('Debe fallar si la persistencia falla');
        } catch (RuntimeException $e) {
            $this->assertSame($vieja, $this->almacen->sesion);
            $this->assertSame(1, $this->almacen->liberaciones);
        }
    }

    private function api(): GoodWeService
    {
        // Evita crear los servicios de precios/Zoho: aqui se prueba el transporte GoodWe.
        $api = (new ReflectionClass(GoodWeService::class))->newInstanceWithoutConstructor();
        foreach (['httpClient' => $this->http, 'sesion' => $this->servicio()] as $nombre => $valor) {
            (new ReflectionProperty(GoodWeService::class, $nombre))->setValue($api, $valor);
        }
        return $api;
    }

    private function tokenDePeticion(int $indice): array
    {
        return json_decode(substr($this->http->peticiones[$indice]['headers'][1], strlen('Token: ')), true);
    }

    #[DataProvider('endpoints')]
    public function testTodosLosEndpointsRenuevanYReintentanUnaVez(string $metodo, array $args, bool $plus): void
    {
        $anteriorUrl = GoodWe::$url;
        GoodWe::$url = 'https://goodwe.invalid/';
        try {
            $this->almacen->sesion = $this->cabecera('viejo');
            $this->http->respuestas = [
                ['code' => $plus ? 'C0602' : '100002'],
                ['hasError' => false, 'data' => $this->cabecera('nuevo')],
                ['code' => $plus ? '00000' : 0, 'data' => ['powerflow' => ['pv' => '1W'], 'record' => 1, 'list' => [['id' => 'planta']], 'dataList' => []]],
            ];
            $resultado = $this->api()->$metodo(...$args);
            $this->assertNotEmpty($resultado);
            $this->assertArrayNotHasKey('error', is_array($resultado) ? $resultado : json_decode($resultado, true));
            $this->assertCount(3, $this->http->peticiones);
            $this->assertSame($this->cabecera('viejo'), $this->tokenDePeticion(0));
            $this->assertSame($this->cabecera('nuevo'), $this->tokenDePeticion(2));
            $this->assertSame('nuevo', $this->almacen->sesion['token']);
        } finally { GoodWe::$url = $anteriorUrl; }
    }

    public static function endpoints(): array
    {
        return [
            'flujo SEMS' => ['getPlantPowerRealtime', ['planta'], false],
            'listado' => ['GetAllPlants', [], false],
            'alarmas' => ['GetPowerStationWariningInfoByMultiCondition', [], false],
            'detalle' => ['GetPlantDetailByPowerstationId', ['planta'], false],
            'equipos' => ['GetInverterAllPoint', ['planta'], false],
            'curva SEMS+' => ['GetChartByPlant', [['id' => 'planta', 'full_script' => true]], true],
            'energia SEMS+' => ['GetChartByPlant', [['id' => 'planta', 'range' => 2]], true],
        ];
    }

    public function testElSegundoRechazoNoEntraEnUnBucle(): void
    {
        $this->almacen->sesion = $this->cabecera('viejo');
        $this->http->respuestas = [['code' => 'C0602'], ['hasError' => false, 'data' => $this->cabecera('nuevo')], ['code' => 'C0602']];
        $respuesta = $this->api()->GetChartByPlant(['id' => 'planta', 'full_script' => true]);
        $this->assertArrayHasKey('error', $respuesta);
        $this->assertCount(3, $this->http->peticiones);
        $this->assertSame(1, $this->almacen->escrituras);
    }

    public function testUnErrorAjenoALaSesionNoProvocaOtroLogin(): void
    {
        $this->almacen->sesion = $this->cabecera();
        $this->http->respuestas = [['code' => 'ERROR_PROVEEDOR']];
        $this->assertArrayHasKey('error', $this->api()->GetChartByPlant(['id' => 'planta']));
        $this->assertSame(0, $this->almacen->bloqueos);
        $this->assertCount(1, $this->http->peticiones);
    }

    public function testUnTokenRecienGuardadoYRechazadoNoProvocaOtroLogin(): void
    {
        // Otra instancia con la misma cuenta lo acaba de invalidar: entrar otra vez
        // la echaria a ella y empezaria un ping-pong de logins.
        $this->almacen->sesion = ['uid' => 'u', 'timestamp' => 1, 'token' => 'viejo', 'client' => 'ios', 'version' => 'v2.1.0', 'language' => 'en'];
        $this->almacen->edad = 10;
        try {
            $this->servicio()->renovar('viejo');
            $this->fail('Deberia haber fallado sin hacer login');
        } catch (GoodWeSesionEnConflicto $e) {
            $this->assertSame([], $this->http->peticiones);
            $this->assertSame(1, $this->almacen->liberaciones, 'el bloqueo se libera igual');
        }
    }

    public function testPasadoElEnfriamientoSiSeVuelveAEntrar(): void
    {
        $this->almacen->sesion = ['uid' => 'u', 'timestamp' => 1, 'token' => 'viejo', 'client' => 'ios', 'version' => 'v2.1.0', 'language' => 'en'];
        $this->almacen->edad = GoodWeSesionService::ENFRIAMIENTO_SEG;
        $this->http->respuestas[] = ['hasError' => false, 'data' => ['uid' => 'u', 'timestamp' => 2, 'token' => 'nuevo']];
        $this->assertSame('nuevo', $this->servicio()->renovar('viejo')['token']);
    }
}
