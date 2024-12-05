<?php
require_once __DIR__ . '/../services/correo.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = htmlspecialchars(trim($_POST['name']));
    $correo = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $mensaje = htmlspecialchars(trim($_POST['message']));

    $correoService = new Correo();
    $dataUsuario = [
        'nombre' => $nombre,
        'email' => $correo,
        'mensaje' => $mensaje,
    ];

    $respuesta = $correoService->enviarMensajeContacto($dataUsuario, $language);

    // Codifica la respuesta para agregarla a la URL
    $status = $respuesta->status;
    $message = urlencode($respuesta->message);

    // Redirige de vuelta con los datos en la URL
    header("Location: http://localhost/esc-backend/index.php?page=ayuda&status=$status&message=$message");
    exit;
}
