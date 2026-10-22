<?php
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
require_once __DIR__ . "/../models/conexion.php";
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/../DBObjects/usuariosDB.php";
require_once __DIR__ . "/../controllers/LogsController.php";

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;


class Imagenes
{
    private $respuesta;
    private $logsController;
    private $clave_secreta;
    private $algorithm;
    private $carpetaDestino = __DIR__ . '/img/';

    private function urlBase(): string
    {
        $configuredUrl = $_ENV['BACKEND_URL'] ?? getenv('BACKEND_URL');
        $backendUrl = is_string($configuredUrl) ? trim($configuredUrl) : '';
        if ($backendUrl !== '') {
            return rtrim($backendUrl, '/');
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $proxyScheme = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $proxyScheme === 'https';
        return ($https ? 'https://' : 'http://') . $host;
    }

    /** Devuelve una ruta bajo img/ y rechaza enlaces simbólicos que salgan de esa carpeta. */
    private function rutaImagenSegura(string $imagen): ?string
    {
        $urlPath = parse_url($imagen, PHP_URL_PATH);
        $nombre = basename(is_string($urlPath) ? $urlPath : $imagen);
        if ($nombre === '' || $nombre === '.' || $nombre === '..') {
            return null;
        }

        $candidata = $this->carpetaDestino . $nombre;
        $base = realpath($this->carpetaDestino);
        $real = realpath($candidata);
        if ($real === false) {
            return $candidata;
        }
        if ($base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }

        return $real;
    }

    public function __construct()
    {
        try {
            // Cargar el archivo .env
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../config');
            $dotenv->load();

            $this->clave_secreta = $_ENV['SECRET_KEY'];
            $this->algorithm = $_ENV['ALGORITHM'];
        } catch (Exception $e) {
            echo "Error al cargar el archivo .env";
        }

        $this->respuesta = new Respuesta;
        $this->logsController = new LogsController;
    }

    // Método para subir la imagen
    public function subirImagen($userId)
    {
        // Verificamos si el archivo fue enviado correctamente
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {

            // Verificar si la carpeta de destino existe, si no la creamos
            if (!is_dir($this->carpetaDestino)) {
                if (!mkdir($this->carpetaDestino, 0755, true) && !is_dir($this->carpetaDestino)) {
                    $this->respuesta->_500();
                    $this->respuesta->message = 'No se pudo preparar la carpeta de imágenes';
                    http_response_code($this->respuesta->code);
                    echo json_encode($this->respuesta);
                    return;
                }
            }

            // Obtener la información del archivo
            $rutaTemporal = $_FILES['imagen']['tmp_name'];

            // El MIME declarado por el navegador no es fiable; se detecta desde el contenido.
            $tipoArchivo = (new finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);
            $extensionesPorMime = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            if (!is_string($tipoArchivo) || !isset($extensionesPorMime[$tipoArchivo]) || @getimagesize($rutaTemporal) === false) {
                // Si el tipo no es permitido, devolvemos un error
                $this->respuesta->_400();
                $this->respuesta->message = 'Tipo de archivo no permitido';
                http_response_code($this->respuesta->code);
                echo json_encode($this->respuesta);
                return;
            }

            // Generamos un nombre único para el archivo para evitar sobrescribir archivos
            $nombreArchivoFinal = bin2hex(random_bytes(16)) . '.' . $extensionesPorMime[$tipoArchivo];
            $rutaDestino = $this->carpetaDestino . $nombreArchivoFinal;

            // Movemos el archivo desde la ubicación temporal a la carpeta de destino
            if (move_uploaded_file($rutaTemporal, $rutaDestino)) {
                $this->logsController->registrarLog(Logs::INFO, "El usuario subió una imagen: $nombreArchivoFinal");
                // El archivo se ha subido correctamente
                $rutaCompleta = $this->urlBase() . '/img/' . $nombreArchivoFinal;

                //relacionar la imagen con el usuario
                $usuariosDB = new UsuariosDB;

                // Obtener la imagen anterior del usuario
                $imagenAnterior = $usuariosDB->getUserImage($userId);
                if ($imagenAnterior) {
                    // Extraer el nombre de la imagen anterior
                    $rutaImagenAnterior = $this->rutaImagenSegura((string) $imagenAnterior);

                    // Borrar la imagen anterior si existe
                    if ($rutaImagenAnterior !== null && is_file($rutaImagenAnterior)) {
                        unlink($rutaImagenAnterior);
                    }
                }

                $imagenLinkeada = $usuariosDB->putUserImage($userId, $rutaCompleta);
                if ($imagenLinkeada == false) {
                    //Eliminar la imagen creada en el servidor
                    unlink($rutaDestino);
                    // Si no se puede borrar el archivo
                    $this->respuesta->_500();
                    $this->respuesta->message = 'Hubo un error al guardar la imagen';
                    http_response_code($this->respuesta->code);
                    echo json_encode($this->respuesta);
                    return;
                } else {
                    $this->respuesta->success();
                    $this->respuesta->message = 'Imagen subida exitosamente';
                    $this->respuesta->data = [
                        'path' => $rutaCompleta,
                        'nombrePath' => $nombreArchivoFinal
                    ]; // Ruta completa del archivo
                    echo json_encode($this->respuesta);
                }
            } else {
                // Ocurrió un error al mover el archivo
                $this->respuesta->_500();
                $this->respuesta->message = 'Hubo un error al guardar la imagen';
                http_response_code($this->respuesta->code);
                echo json_encode($this->respuesta);
            }
        } else {
            // Si no se encuentra el archivo o ocurre otro error
            $this->respuesta->_404();
            $this->respuesta->message = 'No se ha encontrado la imagen o hubo un error con la carga';
            try{
                //Esto falla al ejecutarse en el servidor de producción algo del apache o del php.ini que no deja meter imagenes de mas de 2MB
                //(var_dump($_FILES['imagen']);
                }catch(Throwable $e){
                    echo $e->getMessage();
                }
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
        }
    }
    // Método para borrar la imagen de la carpeta img
    public function borrarImagen($imagenNombre)
    {
        // Verificar que el archivo está dentro de la carpeta 'img/' (evitar ataques de manipulación de ruta)
        $rutaImagen = $this->rutaImagenSegura((string) $imagenNombre);
        if ($rutaImagen === null) {
            // Si la ruta no está dentro de la carpeta de imágenes, prevenimos el borrado
            $this->respuesta->_400();
            $this->respuesta->message = 'Intento de acceso no autorizado a otro archivo o el archivo no existe';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
            return;
        }

        // Verificamos si el archivo existe
        if (is_file($rutaImagen)) {
            // Intentamos borrar el archivo
            if (unlink($rutaImagen)) {
                //Eliminar la imagen de todos los usuarios que tengan la imagen
                $usuariosDB = new UsuariosDB;
                $rutaCompleta = $this->urlBase() . '/img/' . basename($rutaImagen);
                $usuariosDesrelacionador = $usuariosDB->deleteUserImageByPath($rutaCompleta);
                if ($usuariosDesrelacionador == false) {
                    // Si no se puede borrar el archivo
                    $this->respuesta->_500();
                    $this->respuesta->message = 'Hubo un error al desrelacionar la imagen de los usuarios';
                    http_response_code($this->respuesta->code);
                    echo json_encode($this->respuesta);
                } else {
                    $this->logsController->registrarLog(Logs::INFO, "El usuario elimino una imagen: $imagenNombre");
                    $this->respuesta->success();
                    $this->respuesta->message = 'Imagen eliminada exitosamente';
                    echo json_encode($this->respuesta);
                }
            } else {
                // Si no se puede borrar el archivo
                $this->respuesta->_500();
                $this->respuesta->message = 'Hubo un error al eliminar la imagen';
                http_response_code($this->respuesta->code);
                echo json_encode($this->respuesta);
            }
        } else {
            // Si el archivo no existe
            $this->respuesta->_404();
            $this->respuesta->message = 'La imagen no existe en el servidor';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
        }
    }

    // Método para borrar la imagen de la carpeta img
    public function borrarImagenUsuario($idUser)
    {
        $usuariosDB = new UsuariosDB;

        $imagen = $usuariosDB->getUserImage($idUser);

        if ($imagen == false) {
            $this->respuesta->_404();
            $this->respuesta->message = 'El usuario no tiene ninguna foto de perfil';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
            return;
        }

        //dejar solo la imagen
        $imagenNombre = basename((string) (parse_url((string) $imagen, PHP_URL_PATH) ?: $imagen));

        // Verificar que el archivo está dentro de la carpeta 'img/' (evitar ataques de manipulación de ruta)
        $rutaImagen = $this->rutaImagenSegura($imagenNombre);
        if ($rutaImagen === null) {
            // Si la ruta no está dentro de la carpeta de imágenes, prevenimos el borrado
            $this->respuesta->_400();
            $this->respuesta->message = 'Intento de acceso no autorizado a otro archivo';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
            return;
        }

        // Verificamos si el archivo existe
        if (is_file($rutaImagen)) {
            // Intentamos borrar el archivo
            if (unlink($rutaImagen)) {
                $usuariosDB->deleteUserImage($idUser);
                $this->logsController->registrarLog(Logs::INFO, "El usuario elimino una imagen: $imagenNombre");
                $this->respuesta->success();
                $this->respuesta->message = 'Imagen eliminada exitosamente';
                echo json_encode($this->respuesta);
            } else {
                // Si no se puede borrar el archivo
                $this->respuesta->_500();
                $this->respuesta->message = 'Hubo un error al eliminar la imagen';
                http_response_code($this->respuesta->code);
                echo json_encode($this->respuesta);
            }
        } else {
            // Si el archivo no existe
            $this->respuesta->_404();
            $this->respuesta->message = 'La imagen no existe en el servidor';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
        }
    }

    // Función para generar una URL protegida para la imagen
    public function generarUrlProtegida($usuario_id)
    {
        $clave_secreta_url = $this->clave_secreta; // Otra clave secreta para firmar la URL
        $expiracion = time() + 3600; // La URL será válida por una hora

        $datos = [
            'usuario_id' => $usuario_id,
            'expiracion' => $expiracion
        ];
        // Generar el token de la URL
        $token_url = JWT::encode($datos, $this->clave_secreta, $this->algorithm);

        // Obtener el host actual
        $host = $_SERVER['HTTP_HOST'];
        // Añadir 'esc-backend' solo si estamos en localhost
        if (strpos($host, 'localhost') !== false) {
            $host .= "/esc-backend";
        }

        // El esquema se toma de la peticion en vez de forzar https: en local el
        // stack habla http, y una url https://localhost el navegador no puede
        // resolver, asi que ningun avatar cargaba en desarrollo.
        $esquema = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ) ? 'https' : 'http';

        // Retornar la URL protegida
        return "$esquema://$host/app/utils/obtener_imagen.php?token=$token_url";
    }

    // Método para obtener la imagen del usuario
    public function obtenerImagenUsuario($idUser)
    {
        $usuariosDB = new UsuariosDB;
        $imagen = $usuariosDB->getUserImage($idUser);

        if ($imagen == false) {
            $this->respuesta->_404();
            $this->respuesta->message = 'El usuario no tiene ninguna foto de perfil';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
            return;
        }

        // Extraer el nombre de la imagen
        $imagenNombre = basename((string) (parse_url((string) $imagen, PHP_URL_PATH) ?: $imagen));

        $this->obtenerImagen($imagenNombre);
    }

    // Método para obtener y devolver la imagen en formato adecuado
    public function obtenerImagen($imagenNombre)
    {
        // Verificar la ruta del archivo
        $rutaImagen = $this->rutaImagenSegura($imagenNombre);
        if ($rutaImagen === null) {
            $this->respuesta->_400();
            $this->respuesta->message = 'Intento de acceso no autorizado a otro archivo';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
            return;
        }

        // Verificar que el archivo exista
        if (is_file($rutaImagen)) {
            $tipoArchivo = mime_content_type($rutaImagen);
            header('Content-Type: ' . $tipoArchivo);
            readfile($rutaImagen);
        } else {
            $this->respuesta->_404();
            $this->respuesta->message = 'La imagen no existe en el servidor';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
        }
    }

    // Función para verificar el JWT
    public function verificarJWT($jwt)
    {
        try {
            // Intentamos decodificar el JWT
            $decoded = JWT::decode($jwt, new Key($this->clave_secreta, $this->algorithm));

            // Si se decodifica correctamente, retornar los datos
            return $decoded;
        } catch (ExpiredException $e) {
            // Si el token está expirado
            return 'Token expirado: ' . $e->getMessage();
        } catch (SignatureInvalidException $e) {
            // Si la firma es inválida
            return 'Firma inválida: ' . $e->getMessage();
        } catch (BeforeValidException $e) {
            // Si el token aún no es válido
            return 'Token no válido aún: ' . $e->getMessage();
        } catch (Exception $e) {
            // Para cualquier otro error
            return 'Error al verificar el JWT: ' . $e->getMessage();
        }
    }
}
