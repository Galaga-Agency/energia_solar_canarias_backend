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
                $nombreUsuario = isset($dataUsuario['nombre']) ? $dataUsuario['nombre'] : '';

                // Configuración SMTP para Amazon WorkMail
                $this->mail->isSMTP();
                $this->mail->Host =  $this->host; // Servidor SMTP para WorkMail en Irlanda
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $this->username; // Tu correo de WorkMail
                $this->mail->Password = $this->password; // Contraseña de la cuenta de WorkMail
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Usa SSL
                $this->mail->Port = $this->port; // También puedes usar 587 para TLS
                $this->mail->CharSet = 'UTF-8'; // Configuración para UTF-8

                // Configuración del correo
                $this->mail->setFrom('admin@app-energiasolarcanarias.com', 'Admin');
                $this->mail->addAddress($emailUsuario, $nombreUsuario); // Dirección del destinatario

                $this->mail->isHTML(true);

                $textoEspanol = 'Saludos ' . $nombreUsuario . '. ' . 'El token para iniciar sesión en app.energiasolarcanarias.com es: ';
                $textoEspanolHtml = htmlentities($textoEspanol);
                $textoEnglish = 'Greetings ' . $nombreUsuario . '. ' . 'The token to complete the login on app.energiasolarcanarias.com is: ';
                $textoEnglishlHtml = htmlentities($textoEnglish);
                $validezEs = "El token sólo tiene una validez de 5 minutos";
                $validezEs = htmlentities($validezEs);
                $validezEn = "The token is only valid for 5 minutes";
                $validezEn = htmlentities($validezEn);

                // URL de la PWA/frontend (configurable por .env). Un enlace HTTPS normal:
                // el sistema abre la PWA instalada si la tiene, o el navegador si no.
                $appUrl = rtrim($_ENV['FRONTEND_URL'] ?? 'https://app.energiasolarcanarias.com', '/');
                $btnEs = '<div style="text-align: center; margin: 24px 0;"><a href="' . htmlspecialchars($appUrl) . '" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; font-size: 18px; font-weight: bold; padding: 12px 28px; border-radius: 8px;">Abrir la aplicación</a></div>';
                $btnEn = '<div style="text-align: center; margin: 24px 0;"><a href="' . htmlspecialchars($appUrl) . '" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; font-size: 18px; font-weight: bold; padding: 12px 28px; border-radius: 8px;">Open the app</a></div>';
                $logoHtml = '<div style="display: flex; width: 100%; justify-content: center; align-items: center;"><img src="https://app-backend.energiasolarcanarias.com/public/assets/img/logo.png" style="width: 260px;"></div>';

                if ($idiomaUsuario == 'es') {
                    $this->mail->Subject = 'Token';
                    $this->message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEspanolHtml . '</p><p style="font-size: 20px; color: black; text-align: center;"><b>' . $token . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEs . '</p>' . $btnEs . $logoHtml;
                } else {
                    $this->mail->Subject = 'Token';
                    $this->message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEnglishlHtml . '</p><p style="font-size: 20px; color: black; text-align: center;"><b>' . $token . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEn . '</p>' . $btnEn . $logoHtml;
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
                //echo var_dump($dataUsuario);
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
                $nombreUsuario = isset($dataUsuario['nombre']) ? $dataUsuario['nombre'] : '';
                $mensajeUsuario = trim($dataUsuario['mensaje']);

                // Buzon que recibe los mensajes del formulario. Configurable por .env
                // (SOPORTE_EMAIL) para cambiarlo sin tocar codigo ni redesplegar; si no
                // esta definido, se mantiene el destino de siempre.
                $emailSoporte = $_ENV['SOPORTE_EMAIL'] ?? 'soporte@app-energiasolarcanarias.com';

                // Configuración SMTP
                $this->mail->isSMTP();
                $this->mail->Host = $this->host; // Servidor SMTP
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $this->username; // Usuario SMTP
                $this->mail->Password = $this->password; // Contraseña SMTP
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // SSL
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
                $this->mail->addAddress($emailSoporte, 'Soporte'); // Dirección de soporte (SOPORTE_EMAIL)
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
    /**
     * URL publica del backend, de donde cuelgan las imagenes del correo.
     * Configurable por si cambia el dominio; el valor por defecto es el actual.
     */
    private function backendUrl()
    {
        return rtrim(
            $_ENV['BACKEND_URL'] ?? 'https://app-backend.energiasolarcanarias.com',
            '/'
        );
    }

    /** Donde vive la app, para los enlaces que deben abrirla y no la API. */
    private function frontendUrl()
    {
        return rtrim(
            $_ENV['FRONTEND_URL'] ?? 'https://app.energiasolarcanarias.com',
            '/'
        );
    }

    /**
     * Correo de acceso sin contraseña: un solo boton con el enlace magico.
     *
     * A diferencia del login antiguo, aqui NO se manda ningun codigo para
     * copiar: el enlace es la credencial. Por eso el correo no debe reenviarse
     * — quien lo tenga entra.
     *
     * El enlace se pinta tambien como texto debajo del boton porque algunos
     * clientes de correo corporativos bloquean los botones HTML; sin esa copia
     * el usuario se queda sin forma de entrar.
     *
     * @param array  $dataUsuario datos del usuario (email, nombre)
     * @param string $enlace      URL completa al backend, con el token dentro
     */
    public function enlaceMagico($dataUsuario, $enlace, $idiomaUsuario = 'es')
    {
        try {
            if (!isset($dataUsuario['email'])) {
                $respuesta = new Respuesta;
                $respuesta->_500();
                $respuesta->message = 'Error en el servicio correo: falta el email del usuario.';
                return $respuesta;
            }

            $emailUsuario = $dataUsuario['email'];
            $nombreUsuario = isset($dataUsuario['nombre']) ? $dataUsuario['nombre'] : '';

            $this->mail->isSMTP();
            $this->mail->Host = $this->host;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->username;
            $this->mail->Password = $this->password;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = $this->port;
            $this->mail->CharSet = 'UTF-8';

            $this->mail->setFrom('admin@app-energiasolarcanarias.com', 'Energía Solar Canarias');
            $this->mail->addAddress($emailUsuario, $nombreUsuario);
            $this->mail->isHTML(true);

            // htmlspecialchars en el href y htmlentities en el texto: el nombre
            // viene de la base de datos y el enlace lleva un token, asi que
            // ninguno se concatena en crudo dentro del HTML.
            $enlaceSeguro = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
            $nombreSeguro = htmlentities($nombreUsuario);

            if ($idiomaUsuario == 'es') {
                $this->mail->Subject = 'Tu acceso a Energía Solar Canarias';
                $saludo = 'Hola ' . $nombreSeguro . ',';
                $intro = 'Pulsa el botón para entrar en la aplicación. No necesitas contraseña.';
                $textoBoton = 'Entrar en la aplicación';
                $validez = 'Este enlace caduca en 15 minutos y solo se puede usar una vez.';
                $aviso = 'Si no has pedido este acceso, puedes ignorar este correo.';
                $alternativa = 'Si el botón no funciona, copia esta dirección en tu navegador:';
            } else {
                $this->mail->Subject = 'Your access to Energía Solar Canarias';
                $saludo = 'Hi ' . $nombreSeguro . ',';
                $intro = 'Tap the button to sign in. No password needed.';
                $textoBoton = 'Open the app';
                $validez = 'This link expires in 15 minutes and can only be used once.';
                $aviso = 'If you did not request this, you can safely ignore this email.';
                $alternativa = 'If the button does not work, copy this address into your browser:';
            }

            // Colores de la identidad de 2026 (Rubro Studio): naranja #e4572c
            // sobre crema #f4f1ea, verde tinta #21332a para el texto.
            $this->message =
                '<div style="background:#f4f1ea;padding:32px 16px;font-family:Helvetica,Arial,sans-serif;">'
                . '<div style="max-width:520px;margin:0 auto;background:#ffffff;padding:32px;">'
                . '<p style="font-size:18px;color:#21332a;margin:0 0 16px;">' . $saludo . '</p>'
                . '<p style="font-size:16px;color:#21332a;line-height:1.5;margin:0 0 28px;">' . $intro . '</p>'
                . '<div style="text-align:center;margin:0 0 28px;">'
                . '<a href="' . $enlaceSeguro . '" style="display:inline-block;background:#e4572c;color:#f4f1ea;'
                . 'text-decoration:none;font-size:17px;font-weight:bold;padding:14px 32px;">' . $textoBoton . '</a>'
                . '</div>'
                . '<p style="font-size:14px;color:#5b5551;line-height:1.5;margin:0 0 8px;">' . $alternativa . '</p>'
                . '<p style="font-size:13px;color:#5b5551;word-break:break-all;margin:0 0 24px;">' . $enlaceSeguro . '</p>'
                . '<p style="font-size:14px;color:#5b5551;margin:0 0 8px;">' . $validez . '</p>'
                . '<p style="font-size:14px;color:#5b5551;margin:0;">' . $aviso . '</p>'
                . '</div>'
                // Logo de la identidad de 2026. NO usar logo.png: ese es el
                // logotipo anterior (circulo azul con sol amarillo) y desentona
                // por completo con el resto del correo.
                . '<div style="text-align:center;margin-top:24px;">'
                . '<img src="' . $this->backendUrl() . '/public/assets/img/logo-esc-2026.png"'
                . ' width="200" style="width:200px;height:auto;" alt="Energía Solar Canarias">'
                . '</div>'
                . '</div>';

            $this->mail->Body = $this->message;
            $this->mail->send();

            $respuesta = new Respuesta;
            $respuesta->success();
            $respuesta->message = $idiomaUsuario == 'es'
                ? 'Enlace de acceso enviado.'
                : 'Access link sent.';
            return $respuesta;
        } catch (Exception $e) {
            $respuesta = new Respuesta;
            $respuesta->_500($e);
            $respuesta->message = 'Error al enviar el enlace de acceso: ' . $this->mail->ErrorInfo;
            return $respuesta;
        }
    }

    public function recuperarContrasena($dataUsuario, $tokenRecuperacion, $idiomaUsuario = 'es')
    {
        try {
            if (isset($dataUsuario['email'])) {
                $emailUsuario = $dataUsuario['email'];
                $nombreUsuario = isset($dataUsuario['nombre']) ? $dataUsuario['nombre'] : '';

                // Configuración SMTP para Amazon WorkMail (puedes cambiar el proveedor SMTP si lo necesitas)
                $this->mail->isSMTP();
                $this->mail->Host =  $this->host; // Servidor SMTP
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $this->username; // Tu correo de WorkMail
                $this->mail->Password = $this->password; // Contraseña de la cuenta de WorkMail
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Usa SSL
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
                $urlRecuperacion = 'https://app.energiasolarcanarias.com/reset-password?token=' . $tokenRecuperacion;

                if ($idiomaUsuario == 'es') {
                    $this->mail->Subject = 'Recuperación de Contraseña';
                    $this->message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEspanolHtml . '</p><p style="font-size: 20px; color: black; text-align: center;"><b>' . $urlRecuperacion . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEs . '</p><div style="display: flex; width: 100%; justify-content: center; align-items: center;"><img src="https://app-backend.energiasolarcanarias.com/public/assets/img/logo.png" style="width: 260px;"></div>';
                } else {
                    $this->mail->Subject = 'Password Recovery';
                    $this->message = '<p style="font-size: 20px; color: black; text-align: center;">' . $textoEnglishlHtml . '</p><p style="font-size: 20px; color: black; text-align: center;"><b>' . $urlRecuperacion . '</b></p><p style="font-size: 20px; color: black; text-align: center;">' . $validezEn . '</p><div style="display: flex; width: 100%; justify-content: center; align-items: center;"><img src="https://app-backend.energiasolarcanarias.com/public/assets/img/logo.png" style="width: 260px;"></div>';
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

    /**
     * Aviso de alertas en las instalaciones del usuario.
     *
     * Un solo correo con todas las alertas nuevas, no uno por alerta: una
     * tormenta que tumba doce plantas a la vez no puede convertirse en doce
     * correos, o el usuario silencia el remitente y deja de ver los avisos que
     * si importan.
     *
     * @param array $dataUsuario  email y nombre.
     * @param array $alertas      cada una: planta, mensaje, severidad.
     */
    public function avisoAlertas($dataUsuario, $alertas, $idiomaUsuario = 'es')
    {
        try {
            if (!isset($dataUsuario['email']) || empty($alertas)) {
                return false;
            }

            $this->mail->clearAddresses();
            $this->mail->isSMTP();
            $this->mail->Host = $this->host;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->username;
            $this->mail->Password = $this->password;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = $this->port;
            $this->mail->CharSet = 'UTF-8';

            $this->mail->setFrom('admin@app-energiasolarcanarias.com', 'Energía Solar Canarias');
            $this->mail->addAddress($dataUsuario['email'], $dataUsuario['nombre'] ?? '');
            $this->mail->isHTML(true);

            $total = count($alertas);
            $nombreSeguro = htmlentities($dataUsuario['nombre'] ?? '');

            if ($idiomaUsuario == 'es') {
                $this->mail->Subject = $total === 1
                    ? 'Una incidencia en tus instalaciones'
                    : $total . ' incidencias en tus instalaciones';
                $saludo = 'Hola ' . $nombreSeguro . ',';
                $intro = $total === 1
                    ? 'Hemos detectado una incidencia:'
                    : 'Hemos detectado ' . $total . ' incidencias:';
                $textoBoton = 'Ver en la aplicación';
                $pie = 'Puedes cambiar qué avisos recibes en Ajustes.';
            } else {
                $this->mail->Subject = $total === 1
                    ? 'An issue at your installations'
                    : $total . ' issues at your installations';
                $saludo = 'Hi ' . $nombreSeguro . ',';
                $intro = $total === 1
                    ? 'We detected an issue:'
                    : 'We detected ' . $total . ' issues:';
                $textoBoton = 'Open the app';
                $pie = 'You can change which alerts you receive in Settings.';
            }

            // Cada alerta como una fila con una barra de color a la izquierda.
            // El color NO es el unico portador del significado: la severidad va
            // escrita al lado (WCAG 1.4.1, y aqui ademas hay clientes de correo
            // que descartan los estilos por completo).
            $filas = '';
            foreach ($alertas as $alerta) {
                $color = ($alerta['severidad'] ?? '') === 'critical' ? '#a32219' : '#83624b';
                $etiqueta = ($alerta['severidad'] ?? '') === 'critical'
                    ? ($idiomaUsuario == 'es' ? 'Avería' : 'Fault')
                    : ($idiomaUsuario == 'es' ? 'Aviso' : 'Warning');

                $filas .=
                    '<div style="border-left:3px solid ' . $color . ';padding:10px 0 10px 14px;margin:0 0 14px;">'
                    . '<p style="font-size:12px;color:' . $color . ';margin:0 0 4px;'
                    . 'text-transform:uppercase;letter-spacing:0.12em;">' . $etiqueta . '</p>'
                    . '<p style="font-size:16px;color:#21332a;margin:0 0 4px;">'
                    . htmlentities($alerta['planta'] ?? '') . '</p>'
                    . '<p style="font-size:14px;color:#5b5551;margin:0;line-height:1.5;">'
                    . htmlentities($alerta['mensaje'] ?? '') . '</p>'
                    . '</div>';
            }

            $enlaceApp = htmlspecialchars($this->frontendUrl() . '/alertas', ENT_QUOTES, 'UTF-8');

            $this->message =
                '<div style="background:#f4f1ea;padding:32px 16px;font-family:Helvetica,Arial,sans-serif;">'
                . '<div style="max-width:520px;margin:0 auto;background:#ffffff;padding:32px;">'
                . '<p style="font-size:18px;color:#21332a;margin:0 0 16px;">' . $saludo . '</p>'
                . '<p style="font-size:16px;color:#21332a;line-height:1.5;margin:0 0 24px;">' . $intro . '</p>'
                . $filas
                . '<div style="text-align:center;margin:28px 0 0;">'
                . '<a href="' . $enlaceApp . '" style="display:inline-block;background:#e4572c;color:#f4f1ea;'
                . 'text-decoration:none;font-size:17px;font-weight:bold;padding:14px 32px;">' . $textoBoton . '</a>'
                . '</div>'
                . '<p style="font-size:13px;color:#5b5551;margin:24px 0 0;">' . $pie . '</p>'
                . '</div>'
                . '<div style="text-align:center;margin-top:24px;">'
                . '<img src="' . $this->backendUrl() . '/public/assets/img/logo-esc-2026.png"'
                . ' width="200" style="width:200px;height:auto;" alt="Energía Solar Canarias">'
                . '</div>'
                . '</div>';

            $this->mail->Body = $this->message;
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Error en avisoAlertas: " . $e->getMessage());
            return false;
        }
    }

}
