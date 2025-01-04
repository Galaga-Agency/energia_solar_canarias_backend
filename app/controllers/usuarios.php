<?php

require_once __DIR__ . "/../models/usuarios.php";
require_once __DIR__ . "/../utils/respuesta.php";
require_once __DIR__ . "/../middlewares/autenticacion.php";
require_once __DIR__ . "/../controllers/LogsController.php";


class UsuariosController
{
    private $usuarios;

    function __construct()
    {
        $this->usuarios = new Usuarios;
    }
    //Relaciona un id de una planta de un proveedor al id de la planta del usuario
    public function relacionarUsers($idUsuario, $idPlanta, $idProveedor)
    {
        // Crear una instancia del controlador de logs
        $logsController = new LogsController();
        // Instanciar el objeto de acceso a la base de datos
        $usuariosDB = new UsuariosDB();
        $user = $usuariosDB->getAdmin($idUsuario);
        if ($user == true) {
            $logsController->registrarLog(Logs::WARNING, "no se a podido relacionar un usuario");
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "No puedes asociar una planta a un usuario admin el usuario admin tiene acceso a todas las plantas";
            http_response_code(400);
            echo json_encode($respuesta);
            return;
        }
        if (!$usuariosDB->verificarEstadoUsuario($idUsuario)) {
            $logsController->registrarLog(Logs::WARNING, "El usuario que se intenta relacionar no existe en la base de datos o a sido eliminado");
            $respuesta = new Respuesta();
            $respuesta->_404();
            $respuesta->message = "El usuario que se intenta relacionar no existe en la base de datos o a sido eliminado";
            http_response_code(404);
            echo json_encode($respuesta);
            return;
        }
        if ($usuariosDB->comprobarUsuarioAsociadoPlanta($idUsuario, $idPlanta, $idProveedor)) {
            $logsController->registrarLog(Logs::WARNING, "El usuario que se intenta relacionar ya esta relacionado con esa misma planta");
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "El usuario que se intenta relacionar ya esta relacionado con esa misma planta";
            http_response_code(400);
            echo json_encode($respuesta);
            return;
        }
        $usuario = $usuariosDB->relacionarUsers($idPlanta, $idUsuario, $idProveedor);

        if ($usuario != false) {
            $logsController->registrarLog(Logs::POST, "El usuario se a relacionado con la planta correctamente");
            $respuesta = new Respuesta();
            $respuesta->success($usuario);
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        } else {
            $logsController->registrarLog(Logs::WARNING, "Error al realizar la operación");
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "Error al realizar la operación";
            http_response_code(400);
            echo json_encode($respuesta);
        }
    }

    public function desrelacionarUsers($idUsuario, $idPlanta, $idProveedor)
    {
        // Crear una instancia del controlador de logs
        $logsController = new LogsController();
        // Instanciar el objeto de acceso a la base de datos
        $usuariosDB = new UsuariosDB();
        $user = $usuariosDB->getAdmin($idUsuario);
        if ($user == true) {
            $logsController->registrarLog(Logs::WARNING, "no se a podido desrelacionar un usuario");
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "No puedes desrelacionar una planta a un usuario admin el usuario admin tiene acceso a todas las plantas";
            http_response_code(400);
            echo json_encode($respuesta);
            return;
        }
        if (!$usuariosDB->verificarEstadoUsuario($idUsuario)) {
            $logsController->registrarLog(Logs::WARNING, "El usuario que se intenta desrelacionar no existe en la base de datos o a sido eliminado");
            $respuesta = new Respuesta();
            $respuesta->_404();
            $respuesta->message = "El usuario que se intenta desrelacionar no existe en la base de datos o a sido eliminado";
            http_response_code(404);
            echo json_encode($respuesta);
            return;
        }
        if ($usuariosDB->comprobarUsuarioAsociadoPlanta($idUsuario, $idPlanta, $idProveedor)) {

            $usuario = $usuariosDB->desrelacionarUsers($idPlanta, $idUsuario, $idProveedor);

            if ($usuario != false) {
                $logsController->registrarLog(Logs::DELETE, "El usuario se a desrelacionado con la planta correctamente");
                $respuesta = new Respuesta();
                $respuesta->success($usuario);
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                return;
            } else {
                $logsController->registrarLog(Logs::WARNING, "Error al realizar la operación");
                $respuesta = new Respuesta();
                $respuesta->_400();
                $respuesta->message = "Error al realizar la operación";
                http_response_code(400);
                echo json_encode($respuesta);
                return;
            }
        } else {
            $logsController->registrarLog(Logs::WARNING, "El usuario que se intenta desrelacionar no esta relacionado con esa misma planta");
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "El usuario que se intenta desrelacionar no esta relacionado con esa misma planta";
            http_response_code(400);
            echo json_encode($respuesta);
            return;
        }
    }

    public function getAllUsers()
    {
        // Crear una instancia del controlador de logs
        $logsController = new LogsController();
        // Definir los valores predeterminados de paginación
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;

        // Instanciar el objeto de acceso a la base de datos
        $usuariosDB = new UsuariosDB();

        // Obtener usuarios con paginación
        $usuarios = $usuariosDB->getUsers($page, $limit);

        // Verificar si se obtuvo un resultado
        if ($usuarios !== false) {
            $logsController->registrarLog(Logs::GET, "Se solicitan todos los usuarios");
            $paginacion = new Paginacion();
            $paginacion->success($usuarios);
            $paginacion->page = $page;
            $paginacion->limit = $limit;
            echo json_encode($paginacion);
        } else {
            $logsController->registrarLog(Logs::ERROR, "Error al obtener los usuarios");
            // Si hubo un error al obtener los usuarios
            $respuesta = new Respuesta();
            $respuesta->_500();
            $respuesta->message = "Error al obtener usuarios.";
            echo json_encode($respuesta);
        }
    }


    public function getUser($id)
    {
        // Crear una instancia del controlador de logs
        $logsController = new LogsController();
        // Instanciar el objeto de acceso a la base de datos
        $usuariosDB = new UsuariosDB();
        $usuario = $usuariosDB->getUser($id);
        if ($usuario != false) {
            $logsController->registrarLog(Logs::GET, "Se solicita su mismo usuario");
            //quitamos la contraseña hasheada para enviar los datos
            if (isset($usuario[0]['password_hash'])) {
                unset($usuario[0]['password_hash']);
            }
            $respuesta = new Respuesta();
            $respuesta->success($usuario);
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        } else {
            $logsController->registrarLog(Logs::WARNING, "Error al realizar la operación obtener su usuario");
            $respuesta = new Respuesta();
            $respuesta->_404();
            $respuesta->message = "Error al obtener el usuario.";
            echo json_encode($respuesta);
        }
    }

    public function crearUser()
    {
        // Crear una instancia del controlador de logs
        $logsController = new LogsController();

        // Obtener el JSON desde el cuerpo de la solicitud
        $postBody = file_get_contents("php://input");

        $data = json_decode($postBody, true); // Decodificar el JSON en un array asociativo

        // Validar que los datos requeridos existan en el JSON
        if (!isset($data['email'], $data['password'], $data['clase'], $data['nombre'], $data['apellido'], $data['imagen'], $data['movil'], $data['activo'], $data['eliminado'])) {
            $logsController->registrarLog(Logs::WARNING, "Datos incompletos en el JSON de la solicitud.");
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "Datos incompletos en la solicitud.";
            echo json_encode($respuesta);
            return;
        }

        // Instancia de la base de datos
        $usuariosDB = new UsuariosDB();

        // Verificar si la clase existe
        if (!$usuariosDB->comprobarClaseExiste($data['clase'])) {
            $logsController->registrarLog(Logs::WARNING, "Clase inválida: El administrador intentó registrar un usuario con una clase inexistente.");
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "El nombre de la clase no existe.";
            echo json_encode($respuesta);
            return;
        }

        // Verificar si el email ya está registrado
        if ($usuariosDB->comprobarUsuario($data['email'])) {
            $logsController->registrarLog(Logs::WARNING, "Intento de creación con email existente: {$data['email']}");
            $respuesta = new Respuesta();
            $respuesta->_409();
            $respuesta->message = "El email ya está registrado.";
            echo json_encode($respuesta);
            return;
        }

        // Función para obtener el ID del usuario activo
        $authMiddleware = new Autenticacion();
        $idUser = $authMiddleware->obtenerIdUsuarioActivo();

        // Obtener el ID del usuario por email si ya existe en estado eliminado
        $idUsuarioPorEmail = $usuariosDB->getIdUserPorEmail($data['email']);
        if ($idUsuarioPorEmail && $usuariosDB->usuarioEliminado($idUsuarioPorEmail)) {
            // Restaurar usuario eliminado
            $result = $usuariosDB->updateUser($idUsuarioPorEmail['usuario_id'], $data);
        } else {
            // Crear un nuevo usuario
            $result = $usuariosDB->insertUser($data);
        }

        // Responder según el resultado
        if ($result) {
            $logsController->registrarLog(Logs::POST, "Usuario creado o restaurado exitosamente: {$data['email']} por el administrador {$idUser}");
            $respuesta = new Respuesta();
            $respuesta->success($result);
            $respuesta->code = 201; // Código de creación exitosa
            $respuesta->message = "Usuario creado exitosamente.";
            echo json_encode($respuesta);
        } else {
            $logsController->registrarLog(Logs::ERROR, "Error al crear el usuario: {$data['email']} por el administrador {$idUser}");
            $respuesta = new Respuesta();
            $respuesta->_500();
            $respuesta->message = "Error al crear el usuario.";
            echo json_encode($respuesta);
        }
    }


    public function actualizarUser($id)
    {
        // Crear una instancia del controlador de logs
        $logsController = new LogsController();
        // Obtener el JSON desde el cuerpo de la solicitud
        $postBody = file_get_contents("php://input");
        $data = json_decode($postBody, true); // Decodificar el JSON en un array asociativo

        // Validar que los datos requeridos existan en el JSON
        if (isset($data['email'], $data['password'], $data['clase'], $data['nombre'], $data['apellido'], $data['imagen'], $data['movil'], $data['activo'], $data['eliminado'])) {
            // Instancia de la base de datos
            $usuariosDB = new UsuariosDB();
            if (!$usuariosDB->comprobarClaseExiste($data['clase'])) {
                $logsController->registrarLog(Logs::WARNING, "Error al realizar la operación actualizar los usuarios el nombre de la clase no existe");
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
                        $logsController->registrarLog(Logs::PUT, "a modificado al usuario " . $id);
                        $respuesta = new Respuesta();
                        $respuesta->success($result);
                        $respuesta->message = "Usuario actualizado exitosamente.";
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    } else {
                        $logsController->registrarLog(Logs::ERROR, "Error al actualizar el usuario. El correo electrónico ya está registrado en otro usuario. El correo es único y solo se puede cambiar a los usuarios que no tengan ese correo elctrónico. Si queremos cambiar el correo de un usuario a otro la manera de hacerlo sería dar de alta al usuario del correo y cambiarle el correo por ejemplo prueba_su_correo y luego cambiar el correo del otro usuario, así mantenemos registrado los datos del otro usuario para futuros cambios");
                        $respuesta = new Respuesta();
                        $respuesta->_409($result);
                        $respuesta->message = "Error al actualizar el usuario. Este correo está registrado en otro usuario dado de baja para solucionar el error da de alta al usuario con ese correo y modificalo.";
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $logsController->registrarLog(Logs::WARNING, "El email ya está registrado en otro usuario.");
                    $respuesta = new Respuesta();
                    $respuesta->_409();
                    $respuesta->message = "El email ya está registrado en otro usuario.";
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
            } else {
                $logsController->registrarLog(Logs::WARNING, "Datos incompletos en la solicitud en actualizar user.");
                // Respuesta si faltan datos en el JSON
                $respuesta = new Respuesta();
                $respuesta->_400(); // Error de solicitud
                $respuesta->message = "Datos incompletos en la solicitud.";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            }
        } else {
            $logsController->registrarLog(Logs::WARNING, "El usuario no se a encontrado en actualizar user.");
            $respuesta = new Respuesta();
            $respuesta->_404(); // Error de solicitud
            $respuesta->message = "El usuario no se a encontrado";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }
    }

    public function eliminarUser($id)
    {
        // Crear una instancia del controlador de logs
        $logsController = new LogsController();
        // Instancia de la base de datos
        $usuariosDB = new UsuariosDB();
        if ($usuariosDB->verificarEstadoUsuario($id)) {

            // Llamar a la función para realizar el borrado lógico
            $result = $usuariosDB->borrarUser($id);
        } else {
            $logsController->registrarLog(Logs::WARNING, "El usuario no se a encontrado en borrar user.");
            $respuesta = new Respuesta();
            $respuesta->_404(); // Error de solicitud
            $respuesta->message = "El usuario no se a encontrado";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }
        if (isset($result)) {
            if ($result) {
                $logsController->registrarLog(Logs::DELETE, "a eliminado al usuario" . $id);
                $respuesta = new Respuesta();
                $respuesta->success($result);
                $respuesta->message = "Usuario eliminado.";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            } else {
                $logsController->registrarLog(Logs::ERROR, "Error al eliminar el usuario." . $id);
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
