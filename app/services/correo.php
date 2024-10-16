<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';


$mail = new PHPMailer(true);
try {
    // Configuración SMTP para Amazon WorkMail
    $mail->isSMTP();
    $mail->Host = 'email-smtp.eu-west-1.amazonaws.com'; // Servidor SMTP para WorkMail en Irlanda
    $mail->SMTPAuth = true;
    $mail->Username = 'escApp'; // Tu correo de WorkMail
    $mail->Password = 'escApp2024!'; // Contraseña de la cuenta de WorkMail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usa SSL
    $mail->Port = 465; // También puedes usar 587 para TLS

    // Configuración del correo
    $mail->setFrom('admin@app-energiasolarcanarias.com', 'Admin');
    $mail->addAddress('soporte@galagaagency.com'); // Dirección del destinatario
    $mail->Subject = 'Prueba de correo con Amazon WorkMail';
    $mail->Body = 'Este es un mensaje de prueba enviado desde Amazon WorkMail usando PHP.';

    // Enviar correo
    $mail->send();
    echo 'Correo enviado correctamente';
} catch (Exception $e) {
    echo "Error al enviar el correo: {$mail->ErrorInfo}";
}
