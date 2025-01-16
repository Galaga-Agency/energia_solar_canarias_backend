<?php
require_once __DIR__ . "/../models/conexion.php";
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/imagenes.php";
require_once __DIR__ . "/../controllers/LogsController.php";
require_once __DIR__ . "/../DBObjects/usuariosDB.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$imagenesController = new Imagenes();
$respuesta = new Respuesta();
$logsController = new LogsController();

// Verificar si el parámetro 'token' está presente en la URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Verificar si el token es válido
    $decoded = $imagenesController->verificarJWT($token);

    if ($decoded) {
        // El token es válido, proceder a obtener la imagen del usuario
        $usuario_id = $decoded->usuario_id;
        $usuariosDB = new UsuariosDB();
        
        echo $usuario_id;
        // Obtener la ruta de la imagen del usuario
        $imagen = $usuariosDB->getUserImage($usuario_id);

        if ($imagen) {
            // Extraer solo el nombre de la imagen desde la ruta
            $imagenNombreArray = explode("/", $imagen);
            $imagenNombre = end($imagenNombreArray);

            // Verificar si la imagen existe en el servidor
            $carpetaDestino = __DIR__ . '/img/';
            $rutaImagen = $carpetaDestino . $imagenNombre;

            if (file_exists($rutaImagen)) {
                // Si la imagen existe, enviar la imagen con el tipo de contenido adecuado
                $tipoArchivo = mime_content_type($rutaImagen);
                header('Content-Type: ' . $tipoArchivo);
                readfile($rutaImagen);
                exit;
            } else {
                // Si la imagen no existe en el servidor
                $respuesta->_404();
                $respuesta->message = 'La imagen no existe en el servidor';
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            }
        } else {
            // Si el usuario no tiene una imagen
            $respuesta->_404();
            $respuesta->message = 'El usuario no tiene una foto de perfil';
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }
    } else {
        // Si el token no es válido o ha expirado
        $respuesta->_400();
        $respuesta->message = 'Token no válido o expirado';
        http_response_code($respuesta->code);
        echo json_encode($respuesta);
    }
} else {
    // Si el parámetro 'token' no está presente en la URL
    $respuesta->_400();
    $respuesta->message = 'Token de acceso no proporcionado';
    http_response_code($respuesta->code);
    echo json_encode($respuesta);
}
