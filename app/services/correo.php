<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once "../utils/respuesta.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Correo
{

    public $mail;
    public $respuesta;
    public $error;
    public $host;
    public $username;
    public $password;
    public $port;
    public $message;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->respuesta = new Respuesta;
        $this->error = new Errores;
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

    public function login($dataUsuario, $idiomaUsuario = 'es')
    {
        try {
            $emailUsuario = $dataUsuario['email'];
            $nombreUsuario = $dataUsuario['nombre'];
            $token = $dataUsuario['tokenLogin'];

            if ($dataUsuario['login']) {
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
                $this->mail->addAddress($emailUsuario, $nombreUsuario); // Dirección del destinatario

                $this->mail->isHTML(true);
                $textoEspanol = 'Saludos ' . $nombreUsuario . '. ' . 'El token para iniciar sesión en app-energiasolarcanarias.com es: ';
                $textoEspanolHtml = htmlentities($textoEspanol);
                $textoEnglish = 'Greetings ' . $nombreUsuario . '. ' . 'The token to complete the login on app-energiasolarcanarias.com is: ';
                $textoEnglishlHtml = htmlentities($textoEnglish);
                $validezEs = "El token sólo tiene una validez de 5 minutos";
                $validezEs = htmlentities($textoEnglish);
                $validezEn = "The token is only valid for 5 minutes";
                $validezEn = htmlentities($textoEnglish);

                if ($idiomaUsuario == 'es') {
                    $this->mail->Subject = 'Energía Solar Canarias - Token para inciar sesión';

                    $message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEspanolHtml . '</p><br/><p style="font-size: 20px; color: black; text-align: center;"><b>' . $token . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEs . '</p><br/><div style="display: flex; width: 100%; justify-content: center; align-items: center; text-align: center;"><img src="https://app-energiasolarcanarias-backend.com/public/assets/img/logo.webp" style="width: 260px;"></div>';
                } else {
                    $this->mail->Subject = 'Solar Energy Canary Islands - Login link';
                    $message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEnglishlHtml . '</p><br/><p style="font-size: 20px; color: black; text-align: center;"><b>' . $token . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEn . '</p><br/><div style="display: flex; width: 100%; justify-content: center; align-items: center; text-align: center;"><img src="https://app-energiasolarcanarias-backend.com/public/assets/img/logo.webp" style="width: 260px;"></div>';
                }
                $this->mail->Body = $message;
                // Enviar correo
                $this->mail->send();
                //Retornar respuesta
                $this->respuesta->success($dataUsuario);
                if ($idiomaUsuario == 'es') {
                    $this->respuesta->message = 'Login exitoso, el token para continuar ha sido enviado a tu email con una validez de 5 minutos';
                } else {
                    $this->respuesta->message = 'Successful login, the token to continue has been sent to your email with a validity of 5 minutes';
                }
                return $this->respuesta;
            } else {
                $this->error->_401();
                $this->error->message = 'Error en el servicio correo: No se ha recibido en los datos del usuario ($dataUsuario) la validación del login al intentar enviar el correo electrónico con el token al usuario';
                return $this->error;
            }
        } catch (Exception $e) {
            $this->error->_500($e);
            $this->error->message = 'Error de SMTP o de la dependencia PHP-MAILER en el servicio correo al enviar el token de login al usuario' . $this->mail->ErrorInfo;
            return $this->error;
        }
    }
}
