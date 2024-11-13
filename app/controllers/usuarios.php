<?php

require_once "../models/usuarios.php";
require_once "../utils/respuesta.php";

class UsuariosController
{
    private $usuarios;

    function __construct()
    {
        $this->usuarios = new Usuarios;
    }

    public function relacionarUsers($idUsuario, $idPlanta, $proveedor){
         // Instanciar el objeto de acceso a la base de datos
         $usuariosDB = new UsuariosDB();
         $user = $usuariosDB->getAdmin($idUsuario);
         if($user == true){
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "No puedes asociar una planta a un usuario admin el usuario admin tiene acceso a todas las plantas";
            http_response_code(400);
            echo json_encode($respuesta);
            return;
         }
         if (!$usuariosDB->verificarEstadoUsuario($idUsuario)) {
            $respuesta = new Respuesta();
            $respuesta->_404();
            $respuesta->message = "El usuario que se intenta relacionar no existe en la base de datos o a sido eliminado";
            http_response_code(404);
            echo json_encode($respuesta);
            return;
        }
        if ($usuariosDB->comprobarUsuarioAsociadoPlanta($idUsuario, $idPlanta, $proveedor)) {
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "El usuario que se intenta relacionar ya esta relacionado con esa misma planta";
            http_response_code(400);
            echo json_encode($respuesta);
            return;
        }
         $usuario = $usuariosDB->relacionarUsers($idPlanta, $idUsuario, $proveedor);
         if($usuario != false){
             $respuesta = new Respuesta();
             $respuesta->success($usuario);
             http_response_code($respuesta->code);
             echo json_encode($respuesta);
         }else{
             $respuesta = new Respuesta();
             $respuesta->_400();
             $respuesta->message = "Error al realizar la operación";
             http_response_code(400);
             echo json_encode($respuesta);
         }
    }

    public function getAllUsers()
    {
        // Definir los valores predeterminados de paginación
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;

        // Instanciar el objeto de acceso a la base de datos
        $usuariosDB = new UsuariosDB();

        // Obtener usuarios con paginación
        $usuarios = $usuariosDB->getUsers($page, $limit);

        // Verificar si se obtuvo un resultado
        if ($usuarios !== false) {
            $paginacion = new Paginacion();
            $paginacion->success($usuarios);
            $paginacion->page = $page;
            $paginacion->limit = $limit;
            echo json_encode($paginacion);
        } else {
            // Si hubo un error al obtener los usuarios
            $respuesta = new Respuesta();
            $respuesta->_500();
            $respuesta->message = "Error al obtener usuarios.";
            echo json_encode($respuesta);
        }
    }


    public function getUser($id)
    {
        // Instanciar el objeto de acceso a la base de datos
        $usuariosDB = new UsuariosDB();
        $usuario = $usuariosDB->getUser($id);
        if($usuario != false){
            //quitamos la contraseña hasheada para enviar los datos
            if (isset($usuario[0]['password_hash'])) {
                unset($usuario[0]['password_hash']);
            }
            $respuesta = new Respuesta();
            $respuesta->success($usuario);
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }else{
            $respuesta = new Respuesta();
            $respuesta->_404();
            $respuesta->message = "Error al obtener el usuario.";
            echo json_encode($respuesta);
        }
    }

    public function crearUser()
    {
        // Obtener el JSON desde el cuerpo de la solicitud
        $postBody = file_get_contents("php://input");
        $data = json_decode($postBody, true); // Decodificar el JSON en un array asociativo

        // Validar que los datos requeridos existan en el JSON
        if (isset($data['email'], $data['password'], $data['clase'], $data['nombre'], $data['apellido'], $data['imagen'], $data['movil'], $data['activo'], $data['eliminado'])) {
            // Instancia de la base de datos
            $usuariosDB = new UsuariosDB();
            if (!$usuariosDB->comprobarClaseExiste($data['clase'])) {
                $respuesta = new Respuesta();
                $respuesta->_400();
                $respuesta->message = "El nombre de la clase no existe";
                echo json_encode($respuesta);
                return;
            }
            //Llamara a la funcion para verificar si el email del usuario ya existe en la base de datos ! se pone para que si no esta registrado pase el filtro
            if (!$usuariosDB->comprobarUsuario($data['email'])) {
                // Llamar a la función para crear el usuario en la base de datos
                $result = $usuariosDB->insertUser($data);
            } else {
                $respuesta = new Respuesta();
                $respuesta->_409();
                $respuesta->message = "El email ya está registrado.";
                echo json_encode($respuesta);
                return;
            }
            if ($result) {
                $respuesta = new Respuesta();
                $respuesta->success($result);
                $respuesta->code = 201; // Este codigo es el que se suele usar para creacion exitosa
                $respuesta->message = "Usuario creado exitosamente.";
                echo json_encode($respuesta);
            } else {
                $respuesta = new Respuesta();
                $respuesta->_500();
                $respuesta->message = "Error al crear el usuario.";
                echo json_encode($respuesta);
            }
        } else {
            // Respuesta si faltan datos en el JSON
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "Datos incompletos en la solicitud.";
            echo json_encode($respuesta);
        }
    }

    public function actualizarUser($id)
    {
        // Obtener el JSON desde el cuerpo de la solicitud
        $postBody = file_get_contents("php://input");
        $data = json_decode($postBody, true); // Decodificar el JSON en un array asociativo

        // Validar que los datos requeridos existan en el JSON
        if (isset($data['email'], $data['password'], $data['clase'], $data['nombre'], $data['apellido'], $data['imagen'], $data['movil'], $data['activo'], $data['eliminado'])) {
            // Instancia de la base de datos
            $usuariosDB = new UsuariosDB();
            if (!$usuariosDB->comprobarClaseExiste($data['clase'])) {
                $respuesta = new Respuesta();
                $respuesta->_400();
                $respuesta->message = "El nombre de la clase no existe";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                return;
            }
            if ($usuariosDB->verificarEstadoUsuario($id)) {
                // Verificar si el email pertenece a otro usuario
                if (!$usuariosDB->comprobarUsuario($data['email']) || $usuariosDB->esMismoUsuario($id, $data['email'])) {
                    // Llamar a la función para actualizar el usuario en la base de datos
                    $result = $usuariosDB->updateUser($id, $data);

                    if ($result) {
                        $respuesta = new Respuesta();
                        $respuesta->success($result);
                        $respuesta->message = "Usuario actualizado exitosamente";
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta = new Respuesta();
                        $respuesta->_500();
                        $respuesta->message = "Error al actualizar el usuario.";
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta = new Respuesta();
                    $respuesta->_409();
                    $respuesta->message = "El email ya está registrado en otro usuario.";
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
            } else {
                // Respuesta si faltan datos en el JSON
                $respuesta = new Respuesta();
                $respuesta->_400(); // Error de solicitud
                $respuesta->message = "Datos incompletos en la solicitud.";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            }
        } else {
            $respuesta = new Respuesta();
            $respuesta->_404(); // Error de solicitud
            $respuesta->message = "El usuario no se a encontrado";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }
    }

    public function eliminarUser($id)
    {
        // Instancia de la base de datos
        $usuariosDB = new UsuariosDB();
        if ($usuariosDB->verificarEstadoUsuario($id)) {

            // Llamar a la función para realizar el borrado lógico
            $result = $usuariosDB->borrarUser($id);
        } else {
            $respuesta = new Respuesta();
            $respuesta->_404(); // Error de solicitud
            $respuesta->message = "El usuario no se a encontrado";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }
        if (isset($result)) {
            if ($result) {
                $respuesta = new Respuesta();
                $respuesta->success($result);
                $respuesta->message = "Usuario eliminado.";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            } else {
                $respuesta = new Respuesta();
                $respuesta->_500();
                $respuesta->message = "Error al eliminar el usuario.";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            }
        }
    }
}

/*
//PRUEBAS
$users = new UsuariosController;
$users->getAllUsers();
*/
