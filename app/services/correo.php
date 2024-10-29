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
        $jsondata = file_get_contents("../../config/smtp.json");
        $dataSmtp =  json_decode($jsondata, true);
        foreach ($dataSmtp as $key => $value) {
            $this->host = $value['host'];
            $this->username = $value['username'];
            $this->password = $value['password'];
            $this->port = $value['port'];
        }
    }

    public function login($dataUsuario, $idiomaUsuario = 'es', $token)
    {
        try {
            if (isset($dataUsuario['email']) && isset($dataUsuario['nombre']) && isset($dataUsuario['tokenLogin'])) {
                $emailUsuario = $dataUsuario['email'];
                $nombreUsuario = $dataUsuario['nombre'];

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
                $validezEs = htmlentities($validezEs);
                $validezEn = "The token is only valid for 5 minutes";
                $validezEn = htmlentities($validezEn);

                if ($idiomaUsuario == 'es') {
                    $this->mail->Subject = 'Token';
                    $this->message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEspanolHtml . '</p><p style="font-size: 20px; color: black; text-align: center;"><b>' . $token . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEs . '</p><div style="display: flex; width: 100%; justify-content: center; align-items: center;"><img src="https://app-energiasolarcanarias-backend.com/public/assets/img/logo.png" style="width: 260px;"></div>';
                } else {
                    $this->mail->Subject = 'Token';
                    $this->message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEnglishlHtml . '</p><p style="font-size: 20px; color: black; text-align: center;"><b>' . $token . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEn . '</p><div style="display: flex; width: 100%; justify-content: center; align-items: center;"><img src="https://app-energiasolarcanarias-backend.com/public/assets/img/logo.png" style="width: 260px;"></div>';
                }

                $this->mail->Body = $this->message;
                // Enviar correo
                $this->mail->send();
                //el unset borra los parametros que le digamos
                unset($dataUsuario['tokenLogin']);
                unset($dataUsuario['timeTokenLogin']);
                //Retornar respuesta
                $this->respuesta->success($dataUsuario);
                if ($idiomaUsuario == 'es') {
                    $this->respuesta->message = 'Login exitoso, el token para continuar ha sido enviado a tu email con una validez de 5 minutos';
                } else {
                    $this->respuesta->message = 'Successful login, the token to continue has been sent to your email with a validity of 5 minutes';
                }
                $this->respuesta->pagination = null;
                return $this->respuesta;
            } else {
                $this->error->_500();
                $this->error->message = 'Error en el servicio correo: No se ha recibido en los datos del usuario ($dataUsuario) los datos necesarios para intentar enviar el correo electrónico con el token al usuario';
                return $this->error;
            }
        } catch (Exception $e) {
            $this->error->_500($e);
            $this->error->message = 'Error de SMTP o de la dependencia PHP-MAILER en el servicio correo al enviar el token de login al usuario' . $this->mail->ErrorInfo;
            return $this->error;
        }
    }
}
