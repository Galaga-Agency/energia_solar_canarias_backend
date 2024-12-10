<?php
require_once __DIR__ . '/../services/correo.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar los datos enviados desde el formulario
    $nombre = htmlspecialchars(trim($_POST['name']));
    $correo = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $mensaje = htmlspecialchars(trim($_POST['message']));
    $captcha = trim($_POST['captcha']);

    // URL base de la aplicación
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/index.php';

    // Validar el CAPTCHA
    if (!isset($_SESSION['captcha']) || strtolower($captcha) !== strtolower($_SESSION['captcha'])) {
        unset($_SESSION['captcha']); // Eliminar el CAPTCHA para evitar reusos
        echo "<script>
                const urlBase = '$baseUrl';
                const redirectionUrl = `$urlBase?page=ayuda&status=error&message=Captcha incorrecto. Inténtalo nuevamente.`;
                window.location.href = redirectionUrl;
              </script>";
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
    echo "<script>
            const urlBase = '$baseUrl';
            const redirectionUrl = `$urlBase?page=ayuda&status=$status&message=$message`;
            window.location.href = redirectionUrl;
          </script>";
    exit;
}
?>
