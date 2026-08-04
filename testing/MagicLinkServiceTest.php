<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/dobles/ConexionDoble.php';
require_once __DIR__ . '/../app/services/MagicLinkService.php';

/**
 * Tests del acceso sin contraseña (enlace magico).
 *
 * Lo que se protege aqui:
 *
 *  1. El token NUNCA se guarda en claro. Si esto se rompe, un volcado de la
 *     base de datos permite entrar como cualquier usuario, igual que si
 *     guardasemos las contraseñas sin cifrar.
 *
 *  2. El canje es UN SOLO UPDATE atomico con las condiciones dentro del SQL.
 *     La tentacion es hacer SELECT, comprobar en PHP y luego UPDATE: eso es una
 *     condicion de carrera. Dos peticiones simultaneas con el mismo token (el
 *     usuario pulsa dos veces, o el antivirus del correo hace prefetch) verian
 *     las dos el token como valido antes de que ninguna lo marcase, y el enlace
 *     de "un solo uso" valdria dos veces.
 *
 *  3. Un usuario eliminado o inactivo no recibe enlace. Sin esta comprobacion
 *     el enlace magico seria una puerta trasera para cuentas dadas de baja,
 *     saltandose el borrado logico.
 *
 * No hacen falta MySQL ni el stack: se usa un doble de conexion que apunta lo
 * que se le pide y devuelve resultados guionizados.
 *
 * @see app/services/MagicLinkService.php
 * @see db_migrations/2026_08_04_create_magic_link.sql
 */
class MagicLinkServiceTest extends TestCase
{
    /** Un token nuevo tiene 32 bytes de entropia en hexadecimal. */
    public function testElTokenTiene64HexYNoSeRepite()
    {
        $servicio = new MagicLinkService(ConexionDoble::conInsert('magic_links'));
        $token = $servicio->emitirMagicLink(42, '10.0.0.1');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);

        $otro = (new MagicLinkService(ConexionDoble::conInsert('magic_links')))
            ->emitirMagicLink(42, null);
        $this->assertNotSame($token, $otro, 'Dos tokens seguidos no pueden coincidir');
    }

    /** Lo mas importante del fichero: en la base de datos solo va el hash. */
    public function testElTokenEnClaroNuncaLlegaALaBaseDeDatos()
    {
        $conexion = ConexionDoble::conInsert('magic_links');
        $servicio = new MagicLinkService($conexion);
        $token = $servicio->emitirMagicLink(42, '10.0.0.1');

        $enviado = $conexion->parametrosEnviados();

        $this->assertStringNotContainsString(
            $token,
            $enviado,
            'El token en claro NO puede viajar a la base de datos'
        );
        $this->assertStringContainsString(
            hash('sha256', $token),
            $enviado,
            'Lo que se guarda es el sha256 del token'
        );
    }

    /**
     * El canje empieza por el UPDATE, con las condiciones dentro del SQL.
     * Si algun dia el primer statement pasa a ser un SELECT, hay carrera.
     */
    public function testElCanjeEsUnUpdateAtomicoConLasCondicionesEnElSql()
    {
        $conexion = ConexionDoble::conGuion([
            'UPDATE magic_links' => ['afectadas' => 1],
            'SELECT usuario_id FROM magic_links' => ['fila' => 7],
        ]);
        $servicio = new MagicLinkService($conexion);

        $this->assertSame(7, $servicio->canjearMagicLink(str_repeat('a', 64)));

        $primero = $conexion->primerStatement();
        $this->assertStringContainsStringIgnoringCase('UPDATE', $primero);
        $this->assertStringContainsStringIgnoringCase('consumido_en IS NULL', $primero);
        $this->assertStringContainsStringIgnoringCase('expira_en > NOW()', $primero);
    }

    /** affected_rows = 0 significa: no existe, ya se uso o caduco. Los tres, no. */
    public function testUnTokenYaUsadoNoVale()
    {
        $conexion = ConexionDoble::conGuion([
            'UPDATE magic_links' => ['afectadas' => 0],
            'SELECT usuario_id FROM magic_links' => ['fila' => 7],
        ]);
        $servicio = new MagicLinkService($conexion);

        $this->assertNull($servicio->canjearMagicLink(str_repeat('a', 64)));
    }

    public function testUnTokenVacioSeRechazaSinTocarLaBaseDeDatos()
    {
        $conexion = ConexionDoble::conGuion([]);
        $servicio = new MagicLinkService($conexion);

        $this->assertNull($servicio->canjearMagicLink(''));
        $this->assertSame(0, $conexion->numeroDeConsultas());
    }

    public function testElHandoffSeComportaIgualQueElEnlace()
    {
        $servicio = new MagicLinkService(ConexionDoble::conInsert('auth_handoffs'));
        $codigo = $servicio->emitirHandoff(42);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $codigo);

        $conexion = ConexionDoble::conGuion([
            'UPDATE auth_handoffs' => ['afectadas' => 0],
            'SELECT usuario_id FROM auth_handoffs' => ['fila' => 7],
        ]);
        $this->assertNull((new MagicLinkService($conexion))->canjearHandoff($codigo));
    }

    /** Un usuario dado de baja no puede entrar por la puerta de atras. */
    public function testUsuarioEliminadoNoRecibeEnlace()
    {
        $conexion = ConexionDoble::conUsuario(['eliminado' => 1, 'activo' => 1]);
        $servicio = new MagicLinkService($conexion);

        $this->assertNull($servicio->buscarUsuarioPorEmail('x@y.z'));
    }

    public function testUsuarioInactivoNoRecibeEnlace()
    {
        $conexion = ConexionDoble::conUsuario(['eliminado' => 0, 'activo' => 0]);
        $servicio = new MagicLinkService($conexion);

        $this->assertNull($servicio->buscarUsuarioPorEmail('x@y.z'));
    }

    public function testUsuarioActivoSiRecibeEnlace()
    {
        $conexion = ConexionDoble::conUsuario(['eliminado' => 0, 'activo' => 1, 'clase' => 'admin']);
        $servicio = new MagicLinkService($conexion);

        $usuario = $servicio->buscarUsuarioPorEmail('x@y.z');
        $this->assertIsArray($usuario);
        $this->assertSame(5, $usuario['id']);
        $this->assertSame('admin', $usuario['clase']);
    }

    /**
     * Las vidas son parte del contrato de seguridad, no un detalle.
     * Alargarlas sin pensarlo amplia la ventana de un correo interceptado.
     */
    public function testLasVidasSonLasAcordadas()
    {
        $this->assertSame(900, MagicLinkService::MAGIC_TTL_SEG, 'Enlace: 15 minutos');
        $this->assertSame(60, MagicLinkService::HANDOFF_TTL_SEG, 'Handoff: 60 segundos');
    }
}
