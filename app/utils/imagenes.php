<?php
require_once __DIR__ . "/../models/conexion.php";
require_once __DIR__ . "/respuesta.php";
require_once __DIR__ . "/../DBObjects/usuariosDB.php";
require_once __DIR__ . "/../controllers/LogsController.php";

class Imagenes
{
    private $respuesta;
    private $logsController;
    private $carpetaDestino = __DIR__ . '/img/'; // Definir la carpeta de destino

    public function __construct()
    {
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
                mkdir($this->carpetaDestino, 0777, true); // Crear la carpeta si no existe
            }

            // Obtener la información del archivo
            $nombreArchivo = $_FILES['imagen']['name'];
            $tipoArchivo = $_FILES['imagen']['type'];
            $rutaTemporal = $_FILES['imagen']['tmp_name'];

            // Validar el tipo de archivo (opcional)
            $extensionesPermitidas = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
            if (!in_array($tipoArchivo, $extensionesPermitidas)) {
                // Si el tipo no es permitido, devolvemos un error
                $this->respuesta->_400();
                $this->respuesta->message = 'Tipo de archivo no permitido';
                http_response_code($this->respuesta->code);
                echo json_encode($this->respuesta);
                return;
            }

            // Generamos un nombre único para el archivo para evitar sobrescribir archivos
            $nombreArchivoFinal = uniqid('imagen_') . '.' . pathinfo($nombreArchivo, PATHINFO_EXTENSION);
            $rutaDestino = $this->carpetaDestino . $nombreArchivoFinal;

            // Movemos el archivo desde la ubicación temporal a la carpeta de destino
            if (move_uploaded_file($rutaTemporal, $rutaDestino)) {
                $this->logsController->registrarLog(Logs::INFO, "El usuario subió una imagen: $nombreArchivoFinal");
                // El archivo se ha subido correctamente
                $rutaCompleta = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/img/' . $nombreArchivoFinal;

                //relacionar la imagen con el usuario
                $usuariosDB = new UsuariosDB;
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
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
        }
    }

    // Método para borrar la imagen de la carpeta img
    public function borrarImagen($imagenNombre)
    {
        // Verificar que el archivo está dentro de la carpeta 'img/' (evitar ataques de manipulación de ruta)
        $rutaImagen = $this->carpetaDestino . $imagenNombre;

        // Aseguramos que la ruta esté dentro de la carpeta de imágenes
        if (strpos(realpath($rutaImagen), realpath($this->carpetaDestino)) !== 0) {
            // Si la ruta no está dentro de la carpeta de imágenes, prevenimos el borrado
            $this->respuesta->_400();
            $this->respuesta->message = 'Intento de acceso no autorizado a otro archivo o el archivo no existe';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
            return;
        }

        // Verificamos si el archivo existe
        if (file_exists($rutaImagen)) {
            // Intentamos borrar el archivo
            if (unlink($rutaImagen)) {
                //Eliminar la imagen de todos los usuarios que tengan la imagen
                $usuariosDB = new UsuariosDB;
                $usuariosDesrelacionador = $usuariosDB->deleteUserImageByPath($rutaImagen);
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
        $imagenNombreArray = explode("/", $imagen);

        $imagenNombre = $imagenNombreArray[count($imagenNombreArray) - 1];

        // Verificar que el archivo está dentro de la carpeta 'img/' (evitar ataques de manipulación de ruta)
        $rutaImagen = $this->carpetaDestino . $imagenNombre;

        // Aseguramos que la ruta esté dentro de la carpeta de imágenes
        if (strpos(realpath($rutaImagen), realpath($this->carpetaDestino)) !== 0) {
            // Si la ruta no está dentro de la carpeta de imágenes, prevenimos el borrado
            $this->respuesta->_400();
            $this->respuesta->message = 'Intento de acceso no autorizado a otro archivo';
            http_response_code($this->respuesta->code);
            echo json_encode($this->respuesta);
            return;
        }

        // Verificamos si el archivo existe
        if (file_exists($rutaImagen)) {
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
}
