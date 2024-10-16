<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once "../utils/respuesta.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*
class Correo
{

    public $mail;
    public $respuesta;
    public $error;
    public $host;
    public $username;
    public $password;
    public $port;
    private $tokken;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $direccion = dirname(__FILE__);
        $jsondata = file_get_contents("../" . $direccion . "config" . "/" . "smtp.json");
        $dataSmtp =  json_decode($jsondata, true);
        foreach ($dataSmtp as $key => $value) {
            $this->host = $value['host'];
            $this->username = $value['username'];
            $this->password = $value['password'];
            $this->port = $value['port'];
        }
    }

    public function enlaceLogin($email, $nombre, $idioma)
    {
        try {
            // Configuración SMTP para Amazon WorkMail
            $this->mail->isSMTP();
            $this->mail->Host =  $this->host; // Servidor SMTP para WorkMail en Irlanda
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->username; // Tu correo de WorkMail
            $this->mail->Password = $this->password; // Contraseña de la cuenta de WorkMail
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usa SSL
            $this->mail->Port = $this->port; // También puedes usar 587 para TLS

            // Configuración del correo
            $this->mail->setFrom('admin@app-energiasolarcanarias.com', 'Admin');
            $this->mail->addAddress($email, $nombre); // Dirección del destinatario

            $this->mail->isHTML(true);
            $textoEspanol = 'Saludos ' . $nombre . '. ' . 'El enlace para iniciar sesión en app-energiasolarcanarias.com es: ';
            $textoEspanolHtml = htmlentities($textoEspanol);
            $textoEnglish = 'Greetings ' . $nombre . '. ' . 'The link to complete the login on app-energiasolarcanarias.com is: ';
            $textoEnglishlHtml = htmlentities($textoEnglish);
            $clave = '1987082120200804';
            $link = 'http://localhost/esc-backend/restablecer_contrasena.php?id=' .  $id . '&usuario=' . $usuario . '&cod=' . $cod . '&clave=' . $clave;
            if ($idioma == 'es') {
                $this->mail->Subject = 'Energía Solar Canarias - Enlace para inciar sesión';
                $message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEspanolHtml . '</p><br/><p style="font-size: 20px; color: black; text-align: center;"><b>' . $usuario . '</b></p><p style="font-size: 20px; color: blue; text-decoration: underline; text-align: center;"><a href="' . $link . '">RESTABLECER TU CONTRASE&Ntilde;A</a></p><br/><div style="display: flex; width: 100%; justify-content: center; align-items: center; text-align: center;"><a href="http://localhost/dosxdos_app_private" style="display: flex; width: 100%; justify-content: center; align-items: center; text-align: center;"><img src="http://localhost/dosxdos_app_private/img/logo2930_original.png" style="width: 260px;"></a></div>';
            } else {
                $this->mail->Subject = 'Solar Energy Canary Islands - Login link';
                $message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEnglishlHtml . '</p><br/><p style="font-size: 20px; color: black; text-align: center;"><b>' . $usuario . '</b></p><p style="font-size: 20px; color: blue; text-decoration: underline; text-align: center;"><a href="' . $link . '">RESET YOUR PASSWORD</a></p><br/><div style="display: flex; width: 100%; justify-content: center; align-items: center; text-align: center;"><a href="http://localhost/dosxdos_app_private" style="display: flex; width: 100%; justify-content: center; align-items: center; text-align: center;"><img src="http://localhost/dosxdos_app_private/img/logo2930_original.png" style="width: 260px;"></a></div>';
            }
            $this->mail->Body = $message;

            // Enviar correo
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            echo "Error al enviar el correo: {$this->mail->ErrorInfo}";
        }
    }
}
*/