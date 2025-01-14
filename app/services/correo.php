<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../utils/respuesta.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Correo
{

    public $mail;
    public $host;
    public $username;
    public $password;
    public $port;
    public $message;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $direccion = dirname(__FILE__);
        $jsondata = file_get_contents(dirname(__FILE__) . '/../../config/smtp.json');
        $dataSmtp =  json_decode($jsondata, true);
        foreach ($dataSmtp as $key => $value) {
            $this->host = $value['host'];
            $this->username = $value['username'];
            $this->password = $value['password'];
            $this->port = $value['port'];
        }
    }

    public function login($dataUsuario, $token, $idiomaUsuario = 'es')
    {
        try {
            if (isset($dataUsuario['email']) && isset($dataUsuario['tokenLogin'])) {
                $emailUsuario = $dataUsuario['email'];
                $nombreUsuario = isset($dataUsuario['nombre'])? $dataUsuario['nombre'] : '';

                // Configuración SMTP para Amazon WorkMail
                $this->mail->isSMTP();
                $this->mail->Host =  $this->host; // Servidor SMTP para WorkMail en Irlanda
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $this->username; // Tu correo de WorkMail
                $this->mail->Password = $this->password; // Contraseña de la cuenta de WorkMail
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usa SSL
                $this->mail->Port = $this->port; // También puedes usar 587 para TLS
                $this->mail->CharSet = 'UTF-8'; // Configuración para UTF-8

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
                $respuesta = new Respuesta;
                $respuesta->success($dataUsuario);
                if ($idiomaUsuario == 'es') {
                    $respuesta->message = 'Login exitoso, el token para continuar ha sido enviado a tu email con una validez de 5 minutos';
                } else {
                    $respuesta->message = 'Successful login, the token to continue has been sent to your email with a validity of 5 minutes';
                }
                return $respuesta;
            } else {
                $respuesta = new Respuesta;
                $respuesta->_500();
                $respuesta->message = 'Error en el servicio correo: No se ha recibido en los datos del usuario ($dataUsuario) los datos necesarios para intentar enviar el correo electrónico con el token al usuario';
                echo var_dump($dataUsuario);
                return $respuesta;
            }
        } catch (Exception $e) {
            $respuesta = new Respuesta;
            $respuesta->_500($e);
            $respuesta->message = 'Error de SMTP o de la dependencia PHP-MAILER en el servicio correo al enviar el token de login al usuario' . $this->mail->ErrorInfo;
            return $respuesta;
        }
    }
    /**
     * Enviar un mensaje desde el formulario de contacto
     * @param array $dataUsuario Datos del usuario (nombre, correo, mensaje)
     * @param string $lang Idioma activo del usuario
     * @return Respuesta
     */
    public function enviarMensajeContacto($dataUsuario, $lang)
    {
        try {
            // Validar que los datos necesarios estén presentes
            if (isset($dataUsuario['email']) && isset($dataUsuario['mensaje'])) {
                // Sanitizar los datos sin convertir caracteres UTF-8
                $emailUsuario = filter_var($dataUsuario['email'], FILTER_SANITIZE_EMAIL);
                $nombreUsuario = isset($dataUsuario['nombre'])? $dataUsuario['nombre'] : '';
                $mensajeUsuario = trim($dataUsuario['mensaje']);

                // Configuración SMTP
                $this->mail->isSMTP();
                $this->mail->Host = $this->host; // Servidor SMTP
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $this->username; // Usuario SMTP
                $this->mail->Password = $this->password; // Contraseña SMTP
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
                $this->mail->Port = $this->port; // Puerto
                $this->mail->CharSet = 'UTF-8'; // Configuración para UTF-8

                // Mensajes dependiendo del idioma
                $asuntoSoporte = $lang === 'es' ? 'Nuevo mensaje desde el formulario de contacto' : 'New message from the contact form';
                $asuntoConfirmacion = $lang === 'es' ? 'Confirmación de envío de mensaje' : 'Message submission confirmation';
                $mensajeSoporte = $lang === 'es'
                    ? "
                <h3>Mensaje de contacto</h3>
                <p><strong>Nombre:</strong> $nombreUsuario</p>
                <p><strong>Email:</strong> $emailUsuario</p>
                <p><strong>Mensaje:</strong><br>$mensajeUsuario</p>
            "
                    : "
                <h3>Contact Message</h3>
                <p><strong>Name:</strong> $nombreUsuario</p>
                <p><strong>Email:</strong> $emailUsuario</p>
                <p><strong>Message:</strong><br>$mensajeUsuario</p>
            ";
                $mensajeCliente = $lang === 'es'
                    ? "
                <h3>Gracias por contactarnos</h3>
                <p>Hola <strong>$nombreUsuario</strong>,</p>
                <p>Hemos recibido tu mensaje y nuestro equipo de soporte se pondrá en contacto contigo lo antes posible.</p>
                <p><strong>Tu mensaje:</strong></p>
                <blockquote>$mensajeUsuario</blockquote>
                <p>Gracias,<br>El equipo de Energía Solar Canarias.</p>
            "
                    : "
                <h3>Thank you for contacting us</h3>
                <p>Hello <strong>$nombreUsuario</strong>,</p>
                <p>We have received your message, and our support team will get in touch with you as soon as possible.</p>
                <p><strong>Your message:</strong></p>
                <blockquote>$mensajeUsuario</blockquote>
                <p>Thank you,<br>The Energía Solar Canarias Team.</p>
            ";

                // **Correo para soporte**
                $this->mail->setFrom('admin@app-energiasolarcanarias.com', 'Formulario de Contacto');
                $this->mail->addAddress('soporte@app-energiasolarcanarias.com', 'Soporte'); // Dirección de soporte
                $this->mail->addReplyTo($emailUsuario, $nombreUsuario); // Permitir respuesta al remitente

                $this->mail->isHTML(true);
                $this->mail->Subject = $asuntoSoporte;
                $this->mail->Body = $mensajeSoporte;
                $this->mail->AltBody = strip_tags($mensajeSoporte);

                // Enviar el correo al soporte
                $this->mail->send();

                // **Correo de confirmación para el cliente**
                $this->mail->clearAddresses(); // Limpiar destinatarios previos
                $this->mail->addAddress($emailUsuario, $nombreUsuario); // Correo del cliente
                $this->mail->Subject = $asuntoConfirmacion;
                $this->mail->Body = $mensajeCliente;
                $this->mail->AltBody = strip_tags($mensajeCliente);

                // Enviar el correo al cliente
                $this->mail->send();

                // Retornar respuesta de éxito
                $respuesta = new Respuesta;
                $respuesta->success();
                $respuesta->message = $lang === 'es'
                    ? 'El mensaje ha sido enviado correctamente.'
                    : 'Your message has been sent successfully.';
                return $respuesta;
            } else {
                // Retornar error si faltan datos
                $respuesta = new Respuesta;
                $respuesta->_400();
                $respuesta->message = $lang === 'es'
                    ? 'Todos los campos son obligatorios.'
                    : 'All fields are required.';
                return $respuesta;
            }
        } catch (Exception $e) {
            // Capturar errores de SMTP y PHPMailer
            $respuesta = new Respuesta;
            $respuesta->_500();
            $respuesta->message = $lang === 'es'
                ? 'Error al enviar el correo: ' . $this->mail->ErrorInfo
                : 'Error sending the email: ' . $this->mail->ErrorInfo;
            return $respuesta;
        }
    }
    public function recuperarContrasena($dataUsuario, $tokenRecuperacion, $idiomaUsuario = 'es')
    {
        try {
            if (isset($dataUsuario['email'])) {
                $emailUsuario = $dataUsuario['email'];
                $nombreUsuario = isset($dataUsuario['nombre'])? $dataUsuario['nombre'] : '';

                // Configuración SMTP para Amazon WorkMail (puedes cambiar el proveedor SMTP si lo necesitas)
                $this->mail->isSMTP();
                $this->mail->Host =  $this->host; // Servidor SMTP
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $this->username; // Tu correo de WorkMail
                $this->mail->Password = $this->password; // Contraseña de la cuenta de WorkMail
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usa SSL
                $this->mail->Port = $this->port; // También puedes usar 587 para TLS
                $this->mail->CharSet = 'UTF-8'; // Configuración para UTF-8

                // Configuración del correo
                $this->mail->setFrom('admin@app-energiasolarcanarias.com', 'Admin');
                $this->mail->addAddress($emailUsuario, $nombreUsuario); // Dirección del destinatario

                $this->mail->isHTML(true);

                // Mensaje en español
                $textoEspanol = 'Saludos ' . $nombreUsuario . '. ' . 'Hemos recibido una solicitud para restablecer tu contraseña. El enlace para restablecer tu contraseña es: ';
                $textoEspanolHtml = htmlentities($textoEspanol);
                $validezEs = "Este enlace tiene una validez de 10 minutos.";
                $validezEs = htmlentities($validezEs);

                // Mensaje en inglés
                $textoEnglish = 'Greetings ' . $nombreUsuario . '. ' . 'We have received a request to reset your password. The link to reset your password is: ';
                $textoEnglishlHtml = htmlentities($textoEnglish);
                $validezEn = "This link is valid for 10 minutes.";
                $validezEn = htmlentities($validezEn);

                // Enlace de recuperación (esto dependerá de tu sistema de backend, por ejemplo: tu URL de recuperación)
                $urlRecuperacion = 'https://app-energiasolarcanarias.com/reset-password?token=' . $tokenRecuperacion;

                if ($idiomaUsuario == 'es') {
                    $this->mail->Subject = 'Recuperación de Contraseña';
                    $this->message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEspanolHtml . '</p><p style="font-size: 20px; color: black; text-align: center;"><b>' . $urlRecuperacion . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEs . '</p><div style="display: flex; width: 100%; justify-content: center; align-items: center;"><img src="https://app-energiasolarcanarias-backend.com/public/assets/img/logo.png" style="width: 260px;"></div>';
                } else {
                    $this->mail->Subject = 'Password Recovery';
                    $this->message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEnglishlHtml . '</p><p style="font-size: 20px; color: black; text-align: center;"><b>' . $urlRecuperacion . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEn . '</p><div style="display: flex; width: 100%; justify-content: center; align-items: center;"><img src="https://app-energiasolarcanarias-backend.com/public/assets/img/logo.png" style="width: 260px;"></div>';
                }

                $this->mail->Body = $this->message;

                // Enviar correo
                $this->mail->send();

                // Retornar respuesta
                $respuesta = new Respuesta;
                $respuesta->success();
                if ($idiomaUsuario == 'es') {
                    $respuesta->message = 'Se ha enviado un enlace para recuperar tu contraseña a tu email, con una validez de 10 minutos.';
                } else {
                    $respuesta->message = 'A recovery link has been sent to your email with a validity of 10 minutes.';
                }
                return $respuesta;
            } else {
                // Si no se reciben los datos necesarios
                $respuesta = new Respuesta;
                $respuesta->_500();
                $respuesta->message = 'Error en el servicio correo: No se han recibido los datos necesarios del usuario.';
                return $respuesta;
            }
        } catch (Exception $e) {
            // Si ocurre un error al enviar el correo
            $respuesta = new Respuesta;
            $respuesta->_500($e);
            $respuesta->message = 'Error al enviar el correo de recuperación de contraseña al usuario: ' . $this->mail->ErrorInfo;
            return $respuesta;
        }
    }
}
