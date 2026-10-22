<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/correo.php';

/**
 * El cifrado SMTP solo se puede desactivar pidiendolo de forma explicita.
 *
 * "secure": "none" existe para el buzon local (Mailpit, docker/smtp-local.json).
 * Lo que importa proteger es lo contrario: que un smtp.json de produccion, que no
 * trae la clave, siga yendo por STARTTLS.
 *
 * Se construye sin el constructor porque este lee config/smtp.json, que no esta
 * en el repo ni en CI.
 */
class CorreoCifradoTest extends TestCase
{
    private function correoCon($secure): Correo
    {
        $correo = (new ReflectionClass(Correo::class))->newInstanceWithoutConstructor();
        $correo->mail = new PHPMailer(true);
        $correo->secure = $secure;

        $aplicar = new ReflectionMethod(Correo::class, 'aplicarCifrado');
        $aplicar->invoke($correo);

        return $correo;
    }

    public function testPorDefectoUsaStarttls(): void
    {
        $correo = $this->correoCon('tls');

        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $correo->mail->SMTPSecure);
        $this->assertTrue($correo->mail->SMTPAutoTLS);
    }

    public function testUnValorDesconocidoNoDesactivaElCifrado(): void
    {
        // Una errata en smtp.json no puede dejar el correo en claro.
        $correo = $this->correoCon('NONE ');

        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $correo->mail->SMTPSecure);
    }

    public function testNoneDesactivaTlsParaElBuzonLocal(): void
    {
        $correo = $this->correoCon('none');

        $this->assertSame('', $correo->mail->SMTPSecure);
        $this->assertFalse($correo->mail->SMTPAutoTLS);
    }
}
