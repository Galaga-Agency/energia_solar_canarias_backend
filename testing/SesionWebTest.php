<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/controllers/SesionWebController.php';

class SesionWebTest extends TestCase
{
    public function testSecretoAleatorioDe256Bits(): void
    {
        $a = SesionWebService::secreto();
        $this->assertTrue(SesionWebService::secretoValido($a));
        $this->assertNotSame($a, SesionWebService::secreto());
        $this->assertSame(64, strlen($a));
    }

    public function testSecretoMalformadoNoConsultaLaBase(): void
    {
        $db = $this->createMock(SesionWebDB::class);
        $db->expects($this->never())->method('rotar');
        $this->expectException(SesionWebInvalida::class);
        (new SesionWebService($db))->renovar('incorrecto');
    }

    public function testRenovarSoloEntregaHashesAlAlmacen(): void
    {
        $old = str_repeat('a', 64);
        $db = $this->createMock(SesionWebDB::class);
        $db->expects($this->once())->method('rotar')->with(
            hash('sha256', $old),
            $this->callback(fn($value) => preg_match('/^[a-f0-9]{64}$/D', $value) === 1 && $value !== $old),
            $this->greaterThan(0), SesionWebService::INACTIVIDAD_SEG,
        )->willReturn(['estado' => 'invalida']);
        $this->expectException(SesionWebInvalida::class);
        (new SesionWebService($db))->renovar($old);
    }

    public function testCarreraSeDistingueDeUnaSesionRevocada(): void
    {
        $db = $this->createMock(SesionWebDB::class);
        $db->method('rotar')->willReturn(['estado' => 'en_curso']);
        $this->expectException(SesionWebEnCurso::class);
        (new SesionWebService($db))->renovar(str_repeat('a', 64));
    }

    private array $entornoPrevio = [];

    protected function setUp(): void
    {
        // jwtAcceso() firma con Conexion::$secret_key/$algorithm, que normalmente
        // rellena el constructor al conectar con la BD. Aqui no hay BD.
        foreach (['secret_key' => str_repeat('k', 64), 'algorithm' => 'HS256'] as $prop => $valor) {
            $p = new ReflectionProperty(Conexion::class, $prop);
            $this->entornoPrevio[$prop] = $p->getValue();
            if ($this->entornoPrevio[$prop] === null) $p->setValue(null, $valor);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->entornoPrevio as $prop => $valor) {
            (new ReflectionProperty(Conexion::class, $prop))->setValue(null, $valor);
        }
    }

    /** Lo que hace rotar() de verdad: llamar al preparador antes del COMMIT. */
    private function almacenQueRota(): SesionWebDB
    {
        $db = $this->createMock(SesionWebDB::class);
        $db->method('rotar')->willReturnCallback(function (string $h, string $n, int $ahora, int $inact, ?callable $antes) {
            $r = ['estado' => 'ok', 'sesion_id' => str_repeat('b', 32), 'usuario_id' => 7, 'email' => 'x@example.invalid', 'expira_en' => $ahora + $inact];
            if ($antes) $antes($r);
            return $r;
        });
        return $db;
    }

    public function testElPreparadorRecibeElRefreshQueSeDevuelve(): void
    {
        $visto = null;
        $r = (new SesionWebService($this->almacenQueRota()))->renovar(str_repeat('a', 64), function (array $r) use (&$visto) { $visto = $r['refresh']; });
        $this->assertSame($r['refresh'], $visto);
    }

    public function testSiFallaPrepararPerfilOCookieNoSeDaPorRenovada(): void
    {
        // rotar() deshace la transaccion al ver la excepcion: el token presentado
        // sigue sin consumir y el navegador puede volver a intentarlo.
        $this->expectException(RuntimeException::class);
        (new SesionWebService($this->almacenQueRota()))->renovar(str_repeat('a', 64), function () { throw new RuntimeException('cabeceras ya enviadas'); });
    }

    public function testUnTokenYaUsadoSeRechazaSinMargen(): void
    {
        // Sin ventana de reintento: el almacen devuelve 'invalida' y no se entrega sesion.
        $db = $this->createMock(SesionWebDB::class);
        $db->expects($this->once())->method('rotar')->with(
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->isType('callable'),
        )->willReturn(['estado' => 'invalida']);
        $this->expectException(SesionWebInvalida::class);
        (new SesionWebService($db))->renovar(str_repeat('a', 64));
    }

    public function testCerrarNoGuardaNiBuscaElSecretoEnClaro(): void
    {
        $db = $this->createMock(SesionWebDB::class);
        $old = str_repeat('a', 64);
        $db->expects($this->once())->method('revocarToken')->with(hash('sha256', $old), $this->greaterThan(0));
        (new SesionWebService($db))->cerrar($old);
    }

    public static function peticiones(): array
    {
        return [
            ['application/json', 'https://app.example.com', 'same-site', true],
            ['application/json; charset=utf-8', 'https://app.example.com', 'same-site', true],
            ['text/plain', 'https://app.example.com', 'same-site', false],
            ['application/json', 'https://evil.example.com', 'same-site', false],
            ['application/json', 'https://app.example.com:444', 'same-site', false],
            ['application/json', 'https://app.example.com', 'cross-site', false],
            ['application/json', '', '', true],
        ];
    }

    #[DataProvider('peticiones')]
    public function testProteccionDeOrigenYFormato(string $tipo, string $origen, string $sitio, bool $esperado): void
    {
        $server = $_SERVER; $env = $_ENV;
        try {
            $_ENV['FRONTEND_URL'] = 'https://app.example.com'; $_ENV['BACKEND_URL'] = 'https://backend.example.com';
            $_SERVER['CONTENT_TYPE'] = $tipo; $_SERVER['HTTP_ORIGIN'] = $origen; $_SERVER['HTTP_SEC_FETCH_SITE'] = $sitio;
            $this->assertSame($esperado, SesionWebController::permitida());
        } finally { $_SERVER = $server; $_ENV = $env; }
    }

    public function testCookieSecureFueraDelLoopbackLocal(): void
    {
        $env = $_ENV;
        try {
            foreach (['https://backend.example.com', 'http://backend.example.com', 'https://localhost'] as $url) {
                $_ENV['BACKEND_URL'] = $url;
                $this->assertTrue(SesionWebController::cookieSegura());
                $this->assertSame('__Host-esc_refresh', SesionWebController::nombreCookie());
            }
            $_ENV['BACKEND_URL'] = 'http://localhost:8080';
            $this->assertFalse(SesionWebController::cookieSegura());
            $this->assertSame('esc_refresh_local', SesionWebController::nombreCookie());
        } finally { $_ENV = $env; }
    }
}
