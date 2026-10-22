<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/MetodosAcceso.php';

/**
 * Reglas de los metodos de acceso (enlace por correo, email + contraseña).
 *
 * La regla que mas importa es la de no dejar a nadie fuera: una cuenta sin
 * ningun metodo activo no tiene forma de volver a entrar, ni siquiera para
 * arreglarlo, porque Ajustes esta detras del login.
 */
class MetodosAccesoTest extends TestCase
{
    public function testSinNadaGuardadoSeEntraSoloPorEnlace(): void
    {
        $this->assertSame(['enlace' => true, 'password' => false], MetodosAcceso::combinar([]));
    }

    public function testLoGuardadoMandaSobreElDefecto(): void
    {
        $this->assertSame(['enlace' => false, 'password' => true], MetodosAcceso::combinar(['enlace' => 0, 'password' => '1']));
    }

    public function testUnMetodoGuardadoQueYaNoExisteSeIgnora(): void
    {
        $this->assertSame(['enlace' => true, 'password' => false], MetodosAcceso::combinar(['fax' => true]));
    }

    public function testNoSePuedeApagarElUltimoMetodo(): void
    {
        $estado = ['enlace' => true, 'password' => false];

        $this->assertNotNull(MetodosAcceso::motivoRechazo($estado, 'enlace', false, null));
    }

    public function testSiQuedaOtroSePuedeApagar(): void
    {
        $estado = ['enlace' => true, 'password' => true];

        $this->assertNull(MetodosAcceso::motivoRechazo($estado, 'enlace', false, null));
    }

    public function testEncenderLaContrasenaExigeUna(): void
    {
        $estado = MetodosAcceso::combinar([]);

        $this->assertNotNull(MetodosAcceso::motivoRechazo($estado, 'password', true, null));
        $this->assertNotNull(MetodosAcceso::motivoRechazo($estado, 'password', true, ''));
        $this->assertNotNull(MetodosAcceso::motivoRechazo($estado, 'password', true, 'corta'));
        $this->assertNull(MetodosAcceso::motivoRechazo($estado, 'password', true, 'una-contraseña-larga'));
    }

    public function testEncenderElEnlaceNoPideContrasena(): void
    {
        $this->assertNull(MetodosAcceso::motivoRechazo(['enlace' => false, 'password' => true], 'enlace', true, null));
    }

    public function testMetodoDesconocido(): void
    {
        $this->assertNotNull(MetodosAcceso::motivoRechazo(MetodosAcceso::combinar([]), 'fax', true, null));
    }

    public function testLaContrasenaNoPuedePasarDe72Bytes(): void
    {
        // bcrypt ignora lo que pase de 72 bytes: dos contraseñas distintas con el
        // mismo principio darian el mismo hash.
        $this->assertNotNull(MetodosAcceso::motivoPasswordInvalida(str_repeat('a', 73)));
        $this->assertNull(MetodosAcceso::motivoPasswordInvalida(str_repeat('a', 72)));
    }

    public function testLaLongitudMinimaCuentaCaracteresNoBytes(): void
    {
        // 10 caracteres con tildes son mas de 10 bytes; tiene que valer igual.
        $this->assertNull(MetodosAcceso::motivoPasswordInvalida('ñáéíóúñáéí'));
    }

    public function testCambiarLasFormasDeEntrarExigeUnLoginReciente(): void
    {
        $ahora = 1_800_000_000;
        $this->assertTrue(MetodosAcceso::loginReciente($ahora - 60, $ahora));
        $this->assertTrue(MetodosAcceso::loginReciente($ahora - MetodosAcceso::REAUTENTICACION_SEG, $ahora));
        $this->assertFalse(MetodosAcceso::loginReciente($ahora - MetodosAcceso::REAUTENTICACION_SEG - 1, $ahora));
        // Sin hora de login (clave de API, token ilegible) no hay pase.
        $this->assertFalse(MetodosAcceso::loginReciente(null, $ahora));
        // Un iat en el futuro no es un login reciente: es un reloj o un token raro.
        $this->assertFalse(MetodosAcceso::loginReciente($ahora + 60, $ahora));
    }
}
