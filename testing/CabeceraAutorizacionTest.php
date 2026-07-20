<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../app/middlewares/autenticacion.php';

/**
 * La cabecera Authorization se lee sin distinguir mayusculas de minusculas.
 *
 * Los nombres de cabecera son case-insensitive (RFC 7230 §3.2), pero el codigo hacia
 * $headers['Authorization'] literal. Con curl y con el navegador por HTTP/1.1 llega
 * con esa grafia exacta y colaba, asi que el fallo no se veia.
 *
 * Se descubrio al poner el frontend contra el backend local: el proxy de Next va por
 * Node, que normaliza los nombres a minusculas, y TODAS las llamadas autenticadas
 * devolvian 403. Parecia un problema de credenciales y era de mayusculas.
 *
 * Lo que lo hace algo mas que una anecdota de desarrollo: HTTP/2 OBLIGA a que los
 * nombres de cabecera vayan en minusculas. Cualquier proxy o CDN por delante que
 * hable HTTP/2 con el backend y no reescriba la grafia dejaria a todo el mundo fuera.
 *
 * Se prueban los metodos estaticos, que no llaman al constructor (abre la BD).
 */
class CabeceraAutorizacionTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public static function grafias(): array
    {
        return [
            'como lo manda curl'         => ['Authorization'],
            'como lo manda Node/HTTP2'   => ['authorization'],
            'a gritos'                   => ['AUTHORIZATION'],
            'mezclada'                   => ['AuThOrIzAtIoN'],
        ];
    }

    /**
     * En CLI no existe getallheaders(), asi que se reconstruye desde $_SERVER. Ahi los
     * nombres ya llegan normalizados, y por eso este test no puede probar la grafia
     * de la cabecera cruda: lo que prueba es que la BUSQUEDA no distingue mayusculas.
     */
    #[DataProvider('grafias')]
    public function testLaCabeceraSeEncuentraSeaComoSeaQueVengaEscrita(string $grafia)
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJhbGciOiJIUzI1NiJ9.test';
        $this->assertSame(
            'Bearer eyJhbGciOiJIUzI1NiJ9.test',
            Autenticacion::cabecera($grafia),
            "no se encontro la cabecera pidiendola como '$grafia'"
        );
    }

    public function testSiNoVieneLaCabeceraDevuelveNulo()
    {
        $this->assertNull(Autenticacion::cabecera('Authorization'));
    }

    /** Un Bearer no debe colarse como API key, ni al reves: son permisos distintos. */
    public function testNoSeConfundeBearerConToken()
    {
        $auth = new ReflectionMethod(Autenticacion::class, 'tokenDeAutorizacion');
        if (PHP_VERSION_ID < 80500) $auth->setAccessible(true);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer jwt-de-usuario';
        $this->assertSame('jwt-de-usuario', $auth->invoke(null, 'Bearer'));
        $this->assertFalse($auth->invoke(null, 'Token'), 'un Bearer no es una API key');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Token api-key-de-cliente';
        $this->assertSame('api-key-de-cliente', $auth->invoke(null, 'Token'));
        $this->assertFalse($auth->invoke(null, 'Bearer'), 'una API key no es un JWT');
    }
}
