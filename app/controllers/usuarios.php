<?php

require_once __DIR__ . "/../models/usuarios.php";
require_once __DIR__ . "/../utils/respuesta.php";
require_once __DIR__ . "/../middlewares/autenticacion.php";
require_once __DIR__ . "/../controllers/LogsController.php";
require_once __DIR__ . "/../services/ZohoService.php";


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
            $usuarios = $this->pasarUrl($usuarios);
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
            $usuario = $this->pasarUrl($usuario);
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
        // Crear una instancia del servicio de Zoho
        $zohoService = new ZohoService();

        // Crear una instancia del controlador de logs
        $logsController = new LogsController();

        // Obtener el JSON desde el cuerpo de la solicitud
        $postBody = file_get_contents("php://input");

        $data = json_decode($postBody, true); // Decodificar el JSON en un array asociativo

        // Validar que los datos requeridos existan en el JSON
        if (!isset($data['email'], $data['password'], $data['clase'])) {
            $logsController->registrarLog(Logs::WARNING, "Datos incompletos en el JSON de la solicitud.");
            $respuesta = new Respuesta();
            $respuesta->_400();
            $respuesta->message = "Datos incompletos en la solicitud, Se requiere un email, clase y una contraseña.";
            echo json_encode($respuesta);
            return;
        }

        if (!isset($data['origen'])) {
            $data['origen'] = 'app';
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

        //recogemos el id del usuario nuevo
        $IdusuarioCreado = $usuariosDB->getIdUserPorEmail($data['email']);

        // Añadir el usuario_id al array de datos
        $data['usuario_id'] = $IdusuarioCreado['usuario_id'];

        // Responder según el resultado
        if ($result) {
            // Prevenir bucles infinitos si el usuario fue creado desde Zoho
            if (isset($data['origen']) && $data['origen'] === 'crm') {
                if (empty($data['idApp'])) {
                    // Si idApp está vacío, actualizamos el identificador en Zoho
                    $clienteId = isset($data['id']) ? $data['id'] : null;
                    $idApp = $IdusuarioCreado['usuario_id'];
                    $resultCRM = $zohoService->actualizarId($clienteId, $idApp);

                    $logsController->registrarLog(Logs::INFO, "Usuario {$data['email']} creado desde CRM sin idApp. Se ha actualizado el identificador.");

                    $respuesta = new Respuesta();
                    $respuesta->success($data);
                    $respuesta->code = 201;
                    $respuesta->message = "Usuario creado localmente desde Zoho y vinculado correctamente (idApp actualizado).";
                    echo json_encode($respuesta);
                    return;
                }

                // Si ya tiene un idApp, no se reenvía a Zoho para evitar bucles
                $logsController->registrarLog(Logs::INFO, "Usuario {$data['email']} creado desde CRM. No se reenvía a Zoho para evitar bucles.");
                $respuesta = new Respuesta();
                $respuesta->success($data);
                $respuesta->code = 201;
                $respuesta->message = "Usuario creado localmente desde Zoho (sin sincronización hacia CRM).";
                echo json_encode($respuesta);
                return;
            }

            // Crear el usuario en Zoho si no fue creado desde CRM
            $resultCRM = $zohoService->crearCliente($data);
            if (isset($resultCRM['error']) && $resultCRM['error'] === true) {
                $logsController->registrarLog(Logs::ERROR, "Error al crear el usuario en Zoho: " . $resultCRM['message'] . " por el administrador {$idUser}");

                // Respuesta de error si la creación en Zoho falla
                $respuesta = new Respuesta();
                $respuesta->_500($resultCRM);  // Asumiendo que _500() maneja errores internos
                $respuesta->message = "Error al crear el usuario en Zoho.";
                echo json_encode($respuesta);
                return;
            }

            // Si la creación es exitosa
            $logsController->registrarLog(Logs::POST, "Usuario creado o restaurado exitosamente: {$data['email']} por el administrador {$idUser}");

            $respuesta = new Respuesta();
            $respuesta->success($data);
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
        // Crear una instancia del servicio de Zoho
        $zohoService = new ZohoService();
        // Crear una instancia del controlador de logs
        $logsController = new LogsController();
        // Obtener el JSON desde el cuerpo de la solicitud
        $postBody = file_get_contents("php://input");
        $data = json_decode($postBody, true); // Decodificar el JSON en un array asociativo
        $usuariosDB = new UsuariosDB();

        // Validar que los datos requeridos existan en el JSON
        if (isset($data)) {
            // Instancia de la base de datos
            if (isset($data['clase'])) {
                if (!$usuariosDB->comprobarClaseExiste($data['clase'])) {
                    $logsController->registrarLog(Logs::WARNING, "Error al realizar la operación actualizar los usuarios el nombre de la clase no existe");
                    $respuesta = new Respuesta();
                    $respuesta->_400();
                    $respuesta->message = "El nombre de la clase no existe";
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                    return;
                }
            }

            if ($usuariosDB->verificarEstadoUsuario($id)) {
                if (isset($data['origen']) && $data['origen'] === 'crm') {
                    // Compara los datos con CRM (Zoho)
                    $resultCRM = $zohoService->obtenerCliente($id); // Método para obtener los datos del cliente en Zoho CRM
                    if (is_array($resultCRM) && isset($resultCRM['error']) && $resultCRM['error'] == true) {
                        $logsController->registrarLog(Logs::ERROR, "Error al obtener el usuario de Zoho: " . $resultCRM['message']);
                        $respuesta = new Respuesta();
                        $respuesta->_500($resultCRM);
                        $respuesta->message = "Error al obtener el usuario de Zoho.";
                        echo json_encode($respuesta);
                        return;
                    }      
                    
                    // Comparar los datos del CRM con los datos en Zoho y la base de datos
                    if ($this->compararDatosCRMConBaseDatos($resultCRM['data'], $data)) {
                        $logsController->registrarLog(Logs::INFO, "No hay cambios en Zoho, los datos ya están actualizados.");
                        $respuesta = new Respuesta();
                        $respuesta->success(true);
                        $respuesta->message = "No hay cambios en Zoho.";
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                        return;
                    } else {
                        // Si hay cambios, actualizar Zoho
                        $data['usuario_id'] = $id;  // Asegurar que el id del usuario se pasa a Zoho
                        $resultUpdateCRM = $zohoService->actualizarCliente($data);
                        if (isset($resultUpdateCRM['error']) && $resultUpdateCRM['error'] == true) {
                            $logsController->registrarLog(Logs::ERROR, "Error al actualizar el usuario en Zoho: " . $resultUpdateCRM['message']);
                            $respuesta = new Respuesta();
                            $respuesta->_500($resultUpdateCRM);
                            $respuesta->message = "Error al actualizar el usuario en Zoho.";
                            echo json_encode($respuesta);
                            return;
                        }

                        // Si se actualiza Zoho, entonces actualizar también la base de datos
                        $result = $usuariosDB->updateUser($id, $data);
                        if ($result) {
                            $logsController->registrarLog(Logs::PUT, "Se actualizó el usuario en Zoho y en la base de datos " . $id);
                            $respuesta = new Respuesta();
                            $respuesta->success(true);
                            $respuesta->message = "Usuario actualizado correctamente en Zoho y en la base de datos.";
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                        } else {
                            $logsController->registrarLog(Logs::ERROR, "Error al actualizar el usuario en la base de datos.");
                            $respuesta = new Respuesta();
                            $respuesta->_409();
                            $respuesta->message = "Error al actualizar el usuario en la base de datos.";
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                        }
                    }
                } else {
                    // Si el origen es "app" o no está especificado
                    $resultDB = $usuariosDB->getUser($id); // Obtener los datos actuales del usuario en la base de datos

                    if ($this->compararDatosBaseConCRM($resultDB, $data)) {
                        // Si los datos en la base de datos son iguales a los de Zoho, no hacer nada
                        $logsController->registrarLog(Logs::INFO, "No hay cambios en la base de datos, los datos ya están actualizados.");
                        $respuesta = new Respuesta();
                        $respuesta->success(true);
                        $respuesta->message = "No hay cambios en la base de datos.";
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                        return;
                    } else {
                        // Si los datos en la base de datos no coinciden con Zoho, actualizar Zoho
                        $data['usuario_id'] = $id;  // Asegurar que el id del usuario se pasa a Zoho
                        $resultUpdateCRM = $zohoService->actualizarCliente($data);
                        if (isset($resultUpdateCRM['error']) && $resultUpdateCRM['error'] == true) {
                            $logsController->registrarLog(Logs::ERROR, "Error al actualizar el usuario en Zoho: " . $resultUpdateCRM['message']);
                            $respuesta = new Respuesta();
                            $respuesta->_500($resultUpdateCRM);
                            $respuesta->message = "Error al actualizar el usuario en Zoho.";
                            echo json_encode($respuesta);
                            return;
                        }

                        // Ahora actualizar la base de datos
                        $result = $usuariosDB->updateUser($id, $data);
                        if ($result) {
                            $logsController->registrarLog(Logs::PUT, "Se actualizó el usuario en la base de datos y en Zoho " . $id);
                            $respuesta = new Respuesta();
                            $respuesta->success(true);
                            $respuesta->message = "Usuario actualizado correctamente en la base de datos y Zoho.";
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                        } else {
                            $logsController->registrarLog(Logs::ERROR, "Error al actualizar el usuario en la base de datos.");
                            $respuesta = new Respuesta();
                            $respuesta->_409();
                            $respuesta->message = "Error al actualizar el usuario en la base de datos.";
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                        }
                    }
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
            $logsController->registrarLog(Logs::WARNING, "El usuario no ha mandado cuerpo en la peticion");
            $respuesta = new Respuesta();
            $respuesta->_404(); // Error de solicitud
            $respuesta->message = "No se han mandado datos en el body";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }
    }

    // Método para comparar datos entre CRM y la base de datos
    private function compararDatosCRMConBaseDatos($crmData, $dbData)
    {
        // Compara los datos del CRM con los datos de la base de datos
        return $crmData == $dbData; // Lógica de comparación según el formato de los datos
    }

    // Método para comparar los datos de la base de datos con los del CRM
    private function compararDatosBaseConCRM($dbData, $crmData)
    {
        // Compara los datos de la base de datos con los del CRM
        return $dbData == $crmData; // Lógica de comparación según el formato de los datos
    }


    public function eliminarUser($id)
    {
        // Crear una instancia del servicio de Zoho
        $zohoService = new ZohoService();
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
                $resultCRM = $zohoService->eliminarCliente($id);
                if (isset($resultCRM['error']) && $resultCRM['error'] == true) {
                    $logsController->registrarLog(Logs::ERROR, "Error al eliminar el usuario en Zoho: " . $resultCRM['message']);
                    $respuesta = new Respuesta();
                    $respuesta->_500($resultCRM);
                    $respuesta->message = "Error al eliminar el usuario en Zoho.";
                    echo json_encode($respuesta);
                    return;
                }
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
    //llama a todos los usuarios relacionados con una planta
    public function getUsuariosAsociadosAPlantas($idPlanta, $nombreProveedor)
    {
        try {
            // Crear una instancia del controlador de logs
            $logsController = new LogsController();
            // Instancia de la base de datos
            $usuariosDB = new UsuariosDB();

            $result = $usuariosDB->getUsuariosAsociadosAPlantas($idPlanta, $nombreProveedor);

            if ($result != false) {
                //Verificamos que el administrador necesita las imagenes y se las pasamos codificadas
                $result = $this->pasarUrl($result);

                return $result;
            } else {
                $logsController->registrarLog(Logs::WARNING, "No se encontraron usuarios asociados a esta planta o a habido un error");
                return false;
            }
        } catch (Exception $e) {
            $logsController->registrarLog(Logs::ERROR, "Error al obtener usuarios asociados a esta planta " . $e->getMessage());
            return false;
        }
    }
    //================ Esta funcion crea una url temporal que deja visualizar las imagenes del backend ==================//
    public function pasarUrl($data)
    {
        // Verificamos si $data es un solo usuario o una lista de usuarios
        $usuarios = is_array($data) && isset($data[0]) ? $data : [$data];

        foreach ($usuarios as &$usuario) {
            if (!empty($usuario['imagen'])) {
                // Aquí recogemos solo el nombre de la imagen
                $usuario['imagen'] = explode('/', $usuario['imagen']);
                $usuario['imagen'] = end($usuario['imagen']);

                // Verificamos si la imagen existe en el sistema
                $path = __DIR__ . '/../utils/img/' . $usuario['imagen'];

                if (file_exists($path)) {
                    // Aquí usamos el método generarUrlProtegida para generar la URL de acceso
                    $imagenesController = new Imagenes();
                    // Suponemos que el ID del usuario está almacenado en $usuario['usuario_id']
                    $usuario['imagen'] = $imagenesController->generarUrlProtegida($usuario['usuario_id']);
                } else {
                    // Si la imagen no existe, podemos asignar un valor por defecto o mensaje de error
                    $usuario['imagen'] = null;
                }
            } else {
                // Si el usuario no tiene imagen, podemos asignar un valor por defecto o null
                $usuario['imagen'] = null;
            }
        }

        // Si $data era un solo usuario, devolvemos el primer elemento del array
        return is_array($data) && isset($data[0]) ? $usuarios : $usuarios[0];
    }
}

/*
//PRUEBAS
$users = new UsuariosController;
$users->getAllUsers();
*/
