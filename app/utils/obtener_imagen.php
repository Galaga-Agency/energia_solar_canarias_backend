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
        
        var_dump($usuario_id);
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
                // Si la imagen existe, preparar la respuesta en formato form-data

                // Establecer el tipo de archivo de la imagen
                $tipoArchivo = mime_content_type($rutaImagen);

                // Crear el encabezado para la respuesta multipart/form-data
                header('Content-Type: multipart/form-data; boundary=--boundary');

                // Enviar la imagen
                echo "--boundary\r\n";
                echo "Content-Disposition: form-data; name=\"image\"; filename=\"" . $imagenNombre . "\"\r\n";
                echo "Content-Type: " . $tipoArchivo . "\r\n\r\n";
                readfile($rutaImagen); // Imprimir el contenido del archivo

                // Enviar un campo adicional con el mensaje
                echo "\r\n--boundary\r\n";
                echo "Content-Disposition: form-data; name=\"message\"\r\n\r\n";
                echo "Imagen enviada correctamente\r\n";
                echo "--boundary--"; // Fin del formulario

                exit;
            } else {
                // Si la imagen no existe en el servidor
                header('Content-Type: multipart/form-data; boundary=--boundary');
                echo "--boundary\r\n";
                echo "Content-Disposition: form-data; name=\"message\"\r\n\r\n";
                echo "La imagen no existe en el servidor\r\n";
                echo "--boundary--";
                exit;
            }
        } else {
            // Si el usuario no tiene una imagen
            header('Content-Type: multipart/form-data; boundary=--boundary');
            echo "--boundary\r\n";
            echo "Content-Disposition: form-data; name=\"message\"\r\n\r\n";
            echo "El usuario no tiene una foto de perfil\r\n";
            echo "--boundary--";
            exit;
        }
    } else {
        // Si el token no es válido o ha expirado
        header('Content-Type: multipart/form-data; boundary=--boundary');
        echo "--boundary\r\n";
        echo "Content-Disposition: form-data; name=\"message\"\r\n\r\n";
        echo "Token no válido o expirado\r\n";
        echo "--boundary--";
        exit;
    }
} else {
    // Si el parámetro 'token' no está presente en la URL
    header('Content-Type: multipart/form-data; boundary=--boundary');
    echo "--boundary\r\n";
    echo "Content-Disposition: form-data; name=\"message\"\r\n\r\n";
    echo "Token de acceso no proporcionado\r\n";
    echo "--boundary--";
    exit;
}
?>
