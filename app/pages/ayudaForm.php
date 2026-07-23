<?php

require_once __DIR__ . '/../services/correo.php';

/**
 * Procesa el formulario del centro de ayuda (envia el mensaje por correo).
 *
 * Este endpoint NO toca la base de datos: solo manda un email con PHPMailer, asi que
 * no hay superficie de inyeccion SQL. El riesgo real aqui es XSS reflejado y la
 * inyeccion de cabeceras de correo; por eso:
 *   - Todo lo que se muestra al usuario se sanea (htmlspecialchars con ENT_QUOTES).
 *   - El email pasa por FILTER_VALIDATE_EMAIL y se rechaza si no es valido (asi no
 *     llega un valor sucio a las cabeceras Reply-To del correo).
 *   - La redireccion NO usa $_SERVER['HTTP_HOST'] (manipulable por el cliente): se
 *     construye una URL relativa y se emite dentro de <script> con json_encode, que
 *     produce una cadena JS segura y no se puede escapar del literal.
 */

/** Redirige de vuelta al centro de ayuda con un aviso, sin exponer datos sin sanear. */
function redirigirAyuda(string $status, string $mensaje): void
{
    // URL relativa: no depende de HTTP_HOST. rawurlencode evita que el mensaje meta
    // '&', '#' o caracteres que rompan la query. json_encode (con banderas anti-HTML)
    // evita romper el literal JS o cerrar el <script>.
    $destino = 'index.php?page=ayuda&status=' . rawurlencode($status)
        . '&message=' . rawurlencode($mensaje);
    $destinoJs = json_encode($destino, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<script>window.location.href = {$destinoJs};</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigirAyuda('error', 'Metodo no permitido.');
}

// Limites de longitud: cortan abusos (mensajes gigantes) y encajan con el maxlength
// del formulario. Se recorta en servidor porque el maxlength del HTML no es fiable.
$nombre  = mb_substr(htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8'), 0, 100);
$mensaje = mb_substr(htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8'), 0, 2000);
$captcha = trim($_POST['captcha'] ?? '');

// El email se valida de verdad: si no es un correo, ni se intenta enviar. filter_var
// devuelve false ante algo invalido, y ese false no puede llegar a las cabeceras.
$correo = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

// Validar el CAPTCHA (frena el envio automatizado y hace de proteccion anti-CSRF:
// un tercero no puede conocer el texto de la imagen).
if (!isset($_SESSION['captcha']) || strtolower($captcha) !== strtolower($_SESSION['captcha'])) {
    unset($_SESSION['captcha']); // un solo uso: se invalida aunque falle
    redirigirAyuda('error', 'Captcha incorrecto. Intentalo nuevamente.');
}

// El CAPTCHA es de un solo uso: se consume en cuanto se valida bien.
unset($_SESSION['captcha']);

// Campos obligatorios (incluye el email ya validado: si era invalido, $correo es false).
if ($nombre === '' || $mensaje === '' || $correo === false) {
    redirigirAyuda('error', 'Revisa los campos: nombre, un correo valido y mensaje son obligatorios.');
}

$correoService = new Correo();
$dataUsuario = [
    'nombre'  => $nombre,
    'email'   => $correo,
    'mensaje' => $mensaje,
];

$language = $_SESSION['lang'] ?? 'es';

$respuesta = $correoService->enviarMensajeContacto($dataUsuario, $language);

$status = $respuesta->status ? 'success' : 'error';
redirigirAyuda($status, $respuesta->message);
