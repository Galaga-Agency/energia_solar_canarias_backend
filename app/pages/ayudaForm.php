<?php
require_once __DIR__ . '/../services/correo.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar los datos enviados desde el formulario
    $nombre = htmlspecialchars(trim($_POST['name']));
    $correo = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $mensaje = htmlspecialchars(trim($_POST['message']));
    $captcha = trim($_POST['captcha']);

    // Validar el CAPTCHA
    if (!isset($_SESSION['captcha']) || strtolower($captcha) !== strtolower($_SESSION['captcha'])) {
        // Redirigir con mensaje de error si el CAPTCHA es incorrecto
        header('Location: http://localhost/esc-backend/index.php?page=ayuda&status=error&message=Captcha incorrecto. Inténtalo nuevamente.');
        unset($_SESSION['captcha']); // Eliminar el CAPTCHA para evitar reusos
        exit;
    }

    // Eliminar el CAPTCHA una vez validado
    unset($_SESSION['captcha']);

    // Procesar el formulario
    $correoService = new Correo();
    $dataUsuario = [
        'nombre' => $nombre,
        'email' => $correo,
        'mensaje' => $mensaje,
    ];

    // Asume que `$language` es accesible desde la sesión o configuración
    $language = $_SESSION['lang'] ?? 'es';

    $respuesta = $correoService->enviarMensajeContacto($dataUsuario, $language);

    // Codifica la respuesta para redirigir con un mensaje adecuado
    $status = $respuesta->status ? 'success' : 'error';
    $message = urlencode($respuesta->message);

    // Redirige de vuelta con el resultado del procesamiento
    header("Location: http://localhost/esc-backend/index.php?page=ayuda&status=$status&message=$message");
    exit;
}
?>
