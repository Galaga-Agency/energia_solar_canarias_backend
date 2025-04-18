<?php
// Mostrar errores en pantalla
//ini_set('display_errors', 1); // Activar la visualización de errores
//error_reporting(E_ALL);
require_once __DIR__ . '/../../config/configApi.php';
require_once __DIR__ . '/../middlewares/autenticacion.php';
require_once __DIR__ . '/../controllers/usuarios.php';
require_once __DIR__ . '/../controllers/login.php';
require_once __DIR__ . '/../controllers/token.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../DBObjects/usuariosDB.php';
require_once __DIR__ . '/../DBObjects/clasesDB.php';
require_once __DIR__ . '/../controllers/SolarEdgeController.php';
require_once __DIR__ . '/../controllers/GoodWeController.php';
require_once __DIR__ . '/../services/ApiControladorService.php';
require_once __DIR__ . '/../services/GoodWeService.php';
require_once __DIR__ . '/../services/SolarEdgeService.php';
require_once __DIR__ . '/../DBObjects/logsDB.php';
require_once __DIR__ . '/../enums/Logs.php';
require_once __DIR__ . '/../models/OpenMeteo.php';
require_once __DIR__ . '/../utils/imagenes.php';

require_once __DIR__ . '/../services/ZohoService.php';

require_once __DIR__ . '/../helpers/RequestHelper.php';

$respuesta = new Respuesta;
$authMiddleware = new Autenticacion;
$logsDB = new LogsDB;

// Definir el array de proveedores de manera global
$proveedores = [
    'GoodWe' => 'goodwe',
    'SolarEdge' => 'solaredge',
    'VictronEnergy' => 'victronenergy'
    // Añadir más proveedores según sea necesario
];

// Obtener la ruta solicitada
$request = $_SERVER['REQUEST_URI'];

// Obtener el método HTTP (GET, POST, PUT, DELETE, etc.)
$method = $_SERVER['REQUEST_METHOD'];

// Parsear la ruta para quitar parámetros o el prefijo del archivo
$request = trim(parse_url($request, PHP_URL_PATH), '/');

// Define la subcarpeta donde está el proyecto
$baseDir = 'esc-backend';

// Si la ruta comienza con el nombre de la subcarpeta, elimínala
if (strpos($request, $baseDir) === 0) {
    $request = substr($request, strlen($baseDir));
    $request = trim($request, '/'); // Elimina cualquier barra adicional al inicio o final
}
$conexion = Conexion::getInstance();
$conn = $conexion->getConexion();
if ($conn == null) {
    // Si la conexión falla, devuelve un JSON de error y detén la ejecución
    $respuesta = new Respuesta;
    $respuesta->_500();
    $respuesta->message = 'El servidor no se ha podido conectar exitosamente';
    http_response_code(500);
    echo json_encode($respuesta);
    exit;
}
$handled = false; // Bandera para indicar si la ruta fue manejada

// Rutas y endpoints
switch ($method) {
    case 'GET':
        switch (true) {
            case ($request === 'zoho/actualizarDatosPlantas'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        $zohoservice = new ZohoService();
                        $zohoRespuesta = $zohoservice->actualizarDatosPlantas();
                        $respuesta->success($zohoRespuesta);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            case ($request === 'zoho/verificarToken'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        $zohoservice = new ZohoService();
                        $zohoRespuesta = $zohoservice->getAccessToken();
                        $respuesta->success($zohoRespuesta);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            case ($request === 'usuario/imagen'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        if (isset($_GET['id'])) {
                            // Obtener los datos del cuerpo de la solicitud, aunque no los necesitamos para la imagen
                            // El archivo se recibe como parte de $_FILES, no de php://input
                            $imagenes = new Imagenes();
                            $imagenes->obtenerImagenUsuario($_GET['id']);
                        } else {
                            $imagenes = new Imagenes();
                            //recoge el id del usuario por el token
                            $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                            //borra la imagen del usuario
                            $imagenes->obtenerImagenUsuario($idUser);
                        }
                    } else {
                        $imagenes = new Imagenes();
                        //recoge el id del usuario por el token
                        $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                        //borra la imagen del usuario
                        $imagenes->obtenerImagenUsuario($idUser);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case (preg_match('/^plant\/alert/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if (isset($_GET['proveedor'])) {
                        $apiControladorService = new ApiControladorService;
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $pageIndex = isset($_GET['pageIndex']) ? $_GET['pageIndex'] : 1;
                                $pageSize = isset($_GET['pageSize']) ? $_GET['pageSize'] : 200;
                                $status = isset($_GET['status']) ? $_GET['status'] : 3;
                                $apiControladorService->GetPowerStationWariningInfoByMultiCondition($pageIndex, $pageSize, $status);
                                break;
                            case $proveedores['SolarEdge']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay Alertas en la planta de SolarEdge';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            case $proveedores['VictronEnergy']:
                                if (isset($_GET['siteId'])) {
                                    $siteId = $_GET['siteId'];
                                    $pageIndex = isset($_GET['pageIndex']) ? $_GET['pageIndex'] : 1;
                                    $pageSize = isset($_GET['pageSize']) ? $_GET['pageSize'] : 200;
                                    $apiControladorService->getSiteAlarms($siteId, $pageIndex, $pageSize);
                                } else {
                                    $respuesta->_404();
                                    $respuesta->message = 'No se ha encontrado el siteId';
                                    http_response_code($respuesta->code);
                                    echo json_encode($respuesta);
                                }
                                break;
                            default:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no es valido';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case (preg_match('/^plant\/inventario\/([\w-]+)$/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                $powerStationId = $matches[1];
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if (isset($_GET['proveedor'])) {
                        $apiControladorService = new ApiControladorService;
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $apiControladorService->GetInverterAllPoint($powerStationId);
                                break;
                            case $proveedores['SolarEdge']:
                                $apiControladorService->inventarioSolarEdge($powerStationId);
                                break;
                            case $proveedores['VictronEnergy']:
                                $apiControladorService->getSiteEquipoVictronEnergy($powerStationId);
                                break;
                            default:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no es valido';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case (preg_match('/^plant\/overview\/([\w-]+)$/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                $powerStationId = $matches[1];
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if (isset($_GET['proveedor'])) {
                        $apiControladorService = new ApiControladorService;
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay beneficios en la planta de GoodWe';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            case $proveedores['SolarEdge']:
                                $apiControladorService->overviewSolarEdge($powerStationId);
                                break;
                            case $proveedores['VictronEnergy']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay beneficios en la planta de VictronEnergy';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            default:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no es valido';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case (preg_match('/^plant\/benefits\/([\w-]+)$/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                $powerStationId = $matches[1];
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if (isset($_GET['proveedor'])) {
                        $apiControladorService = new ApiControladorService;
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay beneficios en la planta de GoodWe';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            case $proveedores['SolarEdge']:
                                $apiControladorService->getBenefitsSolarEdge($powerStationId);
                                break;
                            case $proveedores['VictronEnergy']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay beneficios en la planta de VictronEnergy';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            default:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no es valido';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            case (preg_match('/^usuario\/bearerToken/', $request, $matches) ? true : false):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                $headers = getallheaders();
                if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                    if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                        $authMiddleware->upsertApiAcceso();
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'El token no se puede authentificar con exito';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'Solo se puede solicitar un Token permanente mediante Bearer Token';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'logs'):
                $handled = true;
                try {
                    //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                    if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                        if ($authMiddleware->verificarAdmin()) {
                            $body = file_get_contents("php://input");
                            $data = json_decode($body, true); // Decodificar JSON a un array asociativo
                            $mensaje = isset($data['mensaje']) ? $data['mensaje'] : '';
                            $page = isset($_GET['page']) ? $_GET['page'] : 1;
                            $limit = isset($_GET['limit']) ? $_GET['limit'] : 200;
                            $logs = $logsDB->getLogs($page, $limit, $mensaje);
                            $respuesta->success($logs);
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                        } else {
                            $respuesta->_403();
                            $respuesta->message = 'No tienes permisos para hacer esta consulta';
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                        }
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'El token no se puede authentificar con exito';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } catch (Exception $e) {
                    $respuesta->_500($e->getMessage());
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'clases'):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $clasesDB = new ClasesDB;
                        $clases = $clasesDB->getClases();
                        $respuesta->success($clases);
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'proveedores'):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $arrayProveedores = [];
                        foreach ($proveedores as $key => $value) {
                            $arrayProveedores[] =  $value;
                        }
                        $respuesta->success($arrayProveedores);
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            // Nuevo caso para obtener los detalles de una planta por ID
            case (preg_match('/^plant\/power\/realtime\/([\w-]+)$/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                $powerStationId = $matches[1];
                $proveedor = $_GET['proveedor'];
                // Verificamos que el usuario esté autenticado y sea administrador
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    switch ($proveedor) {
                        case $proveedores['GoodWe']:
                            $goodWe = new ApiControladorService;
                            $goodWe->getPlantPowerRealtimeGoodwe($powerStationId);
                            break;
                        case $proveedores['SolarEdge']:
                            $solarEdge = new ApiControladorService;
                            $solarEdge->getPlantPowerRealtimeSolarEdge($powerStationId);
                            break;
                        case $proveedores['VictronEnergy']:
                            $victronEnergy = new ApiControladorService;
                            $victronEnergy->getPlantPowerRealtimeVictronEnergy($powerStationId);
                            break;
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            // Nuevo caso para obtener los detalles de una planta por ID
            case (preg_match('/^plants\/details\/([\w-]+)$/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                $powerStationId = $matches[1];
                $proveedor = $_GET['proveedor'];
                // Verificamos que el usuario esté autenticado y sea administrador
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        // Instanciar el controlador de plantas y obtener detalles
                        $apiControladorService = new ApiControladorService();
                        $apiControladorService->getSiteDetail($powerStationId, $proveedor);
                    } else {
                        // El usuario nos tiene que mandar obligatoriamente el proveedor para que verifiquemos si tiene acceso a ese id
                        $idUsuario = $authMiddleware->obtenerIdUsuarioActivo();
                        $proveedor = $_GET['proveedor'];
                        $apiControladorService = new ApiControladorService();
                        $apiControladorService->getSiteDetailCliente($idUsuario, $powerStationId, $proveedor);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'usuarios'):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $usuarios = new UsuariosController;
                        $usuarios->getAllUsers();
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'usuario'):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                    $usuarios = new UsuariosController;
                    $usuarios->getUser($idUser);
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            case (preg_match('/^usuarios\/(\d+)$/', $request, $matches)):
                $handled = true;
                $id = $matches[1];
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $usuarios = new UsuariosController;
                        $usuarios->getUser($id);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            //Devuelve una lista de todas las plantas (Admin)
            case ($request === 'plants'):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    $admin = $authMiddleware->verificarAdmin();
                    if (isset($_GET['proveedor']) && !isset($_GET['plantId'])) {
                        $apiControladorService = new ApiControladorService;
                        $page = isset($_GET['page']) ? $_GET['page'] : 1;
                        $pageSize = isset($_GET['pageSize']) ? $_GET['pageSize'] : 200;
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                if ($admin) {
                                    $apiControladorService->getAllPlantsGoodWe($page, $pageSize);
                                } else {
                                    $respuesta->_403();
                                    $respuesta->message = 'No tienes permisos para hacer esta consulta';
                                    http_response_code($respuesta->code);
                                    echo json_encode($respuesta);
                                }
                                break;
                            case $proveedores['SolarEdge']:
                                if ($admin) {
                                    $apiControladorService->getAllPlantsSolarEdge($page, $pageSize);
                                } else {
                                    $respuesta->_403();
                                    $respuesta->message = 'No tienes permisos para hacer esta consulta';
                                    http_response_code($respuesta->code);
                                    echo json_encode($respuesta);
                                }
                                break;
                            case $proveedores['VictronEnergy']:
                                if ($admin) {
                                    $apiControladorService->getAllPlantsVictronEnergy($page, $pageSize);
                                } else {
                                    $respuesta->_403();
                                    $respuesta->message = 'No tienes permisos para hacer esta consulta';
                                    http_response_code($respuesta->code);
                                    echo json_encode($respuesta);
                                }
                                break;
                            default:
                                $respuesta->success();
                                $respuesta->message = 'No se ha encontrado el proveedor';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    } else {
                        // Verificar si el usuario es administrador
                        if ($admin) {
                            $apiControladorService = new ApiControladorService();
                            if (isset($_GET['usuarioId'])) {
                                //Solicitamos todas las plantas de un cliente 
                                $usuarioId = $_GET['usuarioId'];
                                $apiControladorService->getAllPlantsCliente($usuarioId);
                            } elseif (isset($_GET['plantId']) && isset($_GET['proveedor'])) {
                                //Solicitamos todos los clientes de una planta
                                $plantId = $_GET['plantId'];
                                $nombreProveedor = $_GET['proveedor'];
                                $apiControladorService->getAllClientsPlanta($plantId, $nombreProveedor);
                            } else {
                                $apiControladorService->getAllPlants();
                            }
                        } else {
                            $idUsuario = $authMiddleware->obtenerIdUsuarioActivo();
                            $apiControladorService = new ApiControladorService();
                            $apiControladorService->getAllPlantsCliente($idUsuario);
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            default:
                $handled = true;
                $respuesta->_400();
                $respuesta->message = 'El End Point no existe en la API ' . $request;
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                break;
        }
        break;

    case 'POST':
        switch (true) {
            case ($request === 'zoho/historialprecios' && isset($_GET['plantId']) && isset($_GET['proveedor'])):
                $handled = true;
                $plantId = $_GET['plantId'];
                $proveedor = $_GET['proveedor'];
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        $zohoservice = new ZohoService();
                        $zohoRespuesta = $zohoservice->obtenerListadoDePrecios($plantId, $proveedor);
                        $respuesta->success($zohoRespuesta);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'zoho/crearCliente'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        $zohoservice = new ZohoService();
                        $zohoRespuesta = $zohoservice->crearCliente();
                        $respuesta->success($zohoRespuesta);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case (preg_match('/^plants\/energy\/([\w-]+(?:,[\w-]+)*)$/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                $powerStationIds = isset($matches[1]) ? $matches[1] : "";
                $body = file_get_contents("php://input");
                $data = json_decode($body, true); // Decodificar JSON a un array asociativo
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if (isset($_GET['proveedor'])) {
                        $apiControladorService = new ApiControladorService;
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no tiene esta llamada';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            case $proveedores['SolarEdge']:
                                if (isset($data['time']) && isset($data['startTime']) && isset($data['endTime'])) {
                                    $time = $data['time'];
                                    $startTime = $data['startTime'];
                                    $endTime = $data['endTime'];
                                    $apiControladorService->BulkApiFleetEnergy($time, $startTime, $endTime, $powerStationIds);
                                } else {
                                    $respuesta->_404();
                                    $respuesta->message = 'Parametros faltantes en el body';
                                    http_response_code($respuesta->code);
                                    echo json_encode($respuesta);
                                }
                                break;
                            case $proveedores['VictronEnergy']:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no tiene esta llamada';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            default:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no es valido';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case (preg_match('/^plant\/grafica\/bateria\/([\w-]+)$/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                $powerStationId = $matches[1];
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if (isset($_GET['proveedor'])) {
                        $apiControladorService = new ApiControladorService;
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay beneficios en la planta de GoodWe';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            case $proveedores['SolarEdge']:
                                $body = file_get_contents("php://input");
                                $data = json_decode($body, true); // Decodificar JSON a un array asociativo
                                if (isset($data['fechaInicio']) && isset($data['fechaFin'])) {
                                    $apiControladorService->cargaBateriaSolarEdge($powerStationId, $data['fechaInicio'], $data['fechaFin']);
                                } else {
                                    $respuesta->_404();
                                    $respuesta->message = 'Parametros faltantes en el body';
                                    http_response_code($respuesta->code);
                                    echo json_encode($respuesta);
                                    break;
                                }
                                break;
                            case $proveedores['VictronEnergy']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay beneficios en la planta de VictronEnergy';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            default:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no es valido';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case (preg_match('/^plant\/grafica\/comparacion\/([\w-]+)$/', $request, $matches) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                $powerStationId = $matches[1];
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if (isset($_GET['proveedor'])) {
                        $apiControladorService = new ApiControladorService;
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay beneficios en la planta de GoodWe';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            case $proveedores['SolarEdge']:
                                $body = file_get_contents("php://input");
                                $data = json_decode($body, true); // Decodificar JSON a un array asociativo
                                if (isset($data['timeUnit']) && isset($data['date'])) {
                                    $apiControladorService->getPlantComparative($powerStationId, $data['date'], $data['timeUnit']);
                                } else {
                                    $respuesta->_404();
                                    $respuesta->message = 'Parametros faltantes en el body';
                                    http_response_code($respuesta->code);
                                    echo json_encode($respuesta);
                                    break;
                                }
                                break;
                            case $proveedores['VictronEnergy']:
                                $respuesta->_404();
                                $respuesta->message = 'No hay beneficios en la planta de VictronEnergy';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                            default:
                                $respuesta->_404();
                                $respuesta->message = 'El proveedor no es valido';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'forgot/password'):
                $handled = true;
                //Se le pasara un email y un idiomaUsuario
                $postBody = file_get_contents("php://input");
                if ($postBody == null || $postBody == '') {
                    $respuesta->_400();
                    $respuesta->message = 'No se ha encontrado el body';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                    break;
                }
                //Decodificar el body
                $postBodyArray = json_decode($postBody, true);
                if ($postBodyArray['email'] == null || $postBodyArray['email'] == '') {
                    $respuesta->_400();
                    $respuesta->message = 'No se ha encontrado el email';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                    break;
                }
                $loginController = new LoginController($postBody);
                $loginController->userPasswordRecover();
                break;
            case ($request === 'usuario/imagen'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Obtener los datos del cuerpo de la solicitud, aunque no los necesitamos para la imagen
                    // El archivo se recibe como parte de $_FILES, no de php://input
                    $imagenes = new Imagenes();
                    $userId = $authMiddleware->obtenerIdUsuarioActivo();
                    $imagenes->subirImagen($userId);
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'change/password'):
                $handled = true;
                //Se le pasara un email y un idiomaUsuario
                $postBody = file_get_contents("php://input");
                $loginController = new LoginController($postBody);
                $postBodyArray = json_decode($postBody, true);
                $loginController->changePasswordUser($postBodyArray);
                break;
            case ($request === 'login'):
                $handled = true;
                $postBody = file_get_contents("php://input");
                $loginController = new LoginController($postBody);
                $loginController->userLogin();
                break;

            case ($request === 'token'):
                $handled = true;
                $postBody = file_get_contents("php://input");
                $tokenController = new TokenController($postBody);
                $tokenController->validarToken();
                break;
            case ($request === 'usuarios'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $usuarios = new UsuariosController;
                        $usuarios->crearUser();
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'clima'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Decodificar el cuerpo JSON
                    $input = json_decode(file_get_contents("php://input"), true);
                    // Verificar si se proporcionó el campo 'name'
                    if (isset($input['name'])) {
                        $name = $input['name'];
                        // Pasarle la ruta
                        $openMeteo = new OpenMeteo;
                        $resultado = $openMeteo->obtenerClima($name);
                    } elseif (isset($input['lat']) && isset($input['long'])) {
                        $lat = $input['lat'];
                        $long = $input['long'];
                        // Pasarle la ruta
                        $openMeteo = new OpenMeteo;
                        $resultado = $openMeteo->obtenerClimaCoordenadas($lat, $long);
                    } else {
                        $respuesta->_404();
                        $respuesta->message = 'No se a encontrado el campo name o lat y long en el json';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                        break;
                    }
                    //Enviar la respuesta en formato json
                    echo $resultado;
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            //Cada planta tiene su precio por lo que no necesitamos al usuario solo la planta  
            case ($request === 'totalrealprice' && isset($_GET['plantId']) && isset($_GET['proveedor']) ? true : false):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar que los campos existen
                    if (isset($_GET['plantId']) && isset($_GET['proveedor'])) {
                        $plantId = $_GET['plantId'];
                        $proveedor = $_GET['proveedor'];
                        $respuesta = new Respuesta;
                        //declaramos la variable a null para evitar problemas de declaración
                        $realPrice = null;
                        switch ($proveedor) {
                            case $proveedor == $proveedores['GoodWe']:
                                $goodweController = new GoodWeController;
                                $realPrice = json_decode($goodweController->getPlantRealPrice($plantId));
                                break;
                            case $proveedor == $proveedores['SolarEdge']:
                                $solarEdgeController = new SolarEdgeController;
                                $realPrice = json_decode($solarEdgeController->getPlantRealPrice($plantId));
                                break;
                            case $proveedor == $proveedores['VictronEnergy']:
                                $victronEnergyController = new VictronEnergyController;
                                $realPrice = json_decode($victronEnergyController->getPlantRealPrice($plantId));
                                break;
                            default:
                                $realPrice = null;
                                break;
                        }
                        if ($realPrice != null) {
                            $respuesta->success($realPrice);
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                        } else {
                            $respuesta->_404($realPrice);
                            $respuesta->message = 'No se han encontrado datos o la planta o el proveedor no existe';
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                            break;
                        }
                    } else {
                        $respuesta->_404();
                        $respuesta->message = 'No existe el identificador de la planta o el nombre del proveedor';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                        break;
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'usuarios/relacionar'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $idPlanta = RequestHelper::getParam('idplanta');
                        $idUsuario = RequestHelper::getParam('idusuario');
                        $idProveedor = RequestHelper::getParam('proveedor');
                        $usuarios = new UsuariosController;
                        $usuarios->relacionarUsers($idUsuario, $idPlanta, $idProveedor);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            // Nuevo caso para obtener las graficas de la planta
            case (preg_match('/^plants\/graficas$/', $request, $matches) && isset($_GET['proveedor'])):
                $handled = true;
                // Verificamos que el usuario esté autenticado y sea administrador
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        // Instanciar el controlador de plantas y obtener detalles
                        $apiController = new ApiControladorService();
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $apiController->getGraficasGoodWe();
                                break;
                            case $proveedores['SolarEdge']:
                                $apiController->getGraficasSolarEdge();
                                break;
                            case $proveedores['VictronEnergy']:
                                $apiController->getGraficasVictronEnergy();
                                break;
                            default:
                                $respuesta->_400();
                                $respuesta->message = 'Proveedor no encontrado';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    } else {
                        // El usuario nos tiene que mandar obligatoriamente el proveedor para que verifiquemos si tiene acceso a ese id
                        $apiController = new ApiControladorService();
                        $proveedor = $_GET['proveedor'];
                        switch ($proveedor) {
                            case $proveedores['GoodWe']:
                                $apiController->getGraficasGoodWe();
                                break;
                            case $proveedores['SolarEdge']:
                                $apiController->getGraficasSolarEdge();
                                break;
                            case $proveedores['VictronEnergy']:
                                $apiController->getGraficasVictronEnergy();
                                break;
                            default:
                                $respuesta->_400();
                                $respuesta->message = 'Proveedor no encontrado';
                                http_response_code($respuesta->code);
                                echo json_encode($respuesta);
                                break;
                        }
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'zoho/imprimirWebhook'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        // Obtener los parámetros de la URL
                        $queryParams = $_GET;

                        // Obtener las cabeceras de la solicitud
                        $headers = getallheaders();

                        // Obtener el cuerpo del webhook
                        $webhookData = file_get_contents('php://input');

                        // Decodificar el JSON del cuerpo
                        $decodedData = json_decode($webhookData, true);

                        // Imprimir todos los detalles en el log o como respuesta
                        error_log("Detalles del Webhook:");
                        error_log("URL: " . $_SERVER['REQUEST_URI']);
                        error_log("Parámetros de la URL: " . print_r($queryParams, true));
                        error_log("Cabeceras: " . print_r($headers, true));
                        error_log("Cuerpo del Webhook (JSON): " . $webhookData);
                        error_log("Datos Decodificados: " . print_r($decodedData, true));

                        // Define la ruta del archivo donde quieres guardar los datos
                        $file = 'webhook_data.txt';

                        // Abre el archivo para escribir (en modo de escritura, lo que crea o sobrescribe el archivo)
                        $handle = fopen($file, 'w');

                        if ($handle) {
                            // Guarda los datos en el archivo en formato JSON
                            fwrite($handle, json_encode([
                                'headers' => $headers,
                                'queryParams' => $queryParams,
                                'body' => $decodedData
                            ], JSON_PRETTY_PRINT));

                            fclose($handle); // Cierra el archivo después de escribir

                            echo 'Webhook recibido y guardado correctamente.';
                        } else {
                            echo 'Error al intentar guardar el archivo.';
                        }
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            default:
                $handled = true;
                $respuesta->_400();
                $respuesta->message = 'El End Point no existe en la API ' . $request;
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                break;
        }
        break;

    case 'PUT':
        switch (true) {
            case ($request === 'zoho/imprimirWebhook'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        // Obtener los parámetros de la URL
                        $queryParams = $_GET;

                        // Obtener las cabeceras de la solicitud
                        $headers = getallheaders();

                        // Obtener el cuerpo del webhook
                        $webhookData = file_get_contents('php://input');

                        // Decodificar el JSON del cuerpo
                        $decodedData = json_decode($webhookData, true);

                        // Imprimir todos los detalles en el log o como respuesta
                        error_log("Detalles del Webhook:");
                        error_log("URL: " . $_SERVER['REQUEST_URI']);
                        error_log("Parámetros de la URL: " . print_r($queryParams, true));
                        error_log("Cabeceras: " . print_r($headers, true));
                        error_log("Cuerpo del Webhook (JSON): " . $webhookData);
                        error_log("Datos Decodificados: " . print_r($decodedData, true));

                        // Define la ruta del archivo donde quieres guardar los datos
                        $file = 'webhook_data.txt';

                        // Abre el archivo para escribir (en modo de escritura, lo que crea o sobrescribe el archivo)
                        $handle = fopen($file, 'w');

                        if ($handle) {
                            // Guarda los datos en el archivo en formato JSON
                            fwrite($handle, json_encode([
                                'headers' => $headers,
                                'queryParams' => $queryParams,
                                'body' => $decodedData
                            ], JSON_PRETTY_PRINT));

                            fclose($handle); // Cierra el archivo después de escribir

                            echo 'Webhook recibido y guardado correctamente.';
                        } else {
                            echo 'Error al intentar guardar el archivo.';
                        }
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'zoho/actualizarCliente'):
                $handled = true;

                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        // Obtener los datos del body
                        $jsonInput = file_get_contents("php://input");
                        $data = json_decode($jsonInput, true);

                        if (!$data) {
                            $respuesta->_400();
                            $respuesta->message = 'Datos JSON inválidos o vacíos.';
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                            break;
                        }

                        // Ejecutar actualización en Zoho
                        $zohoService = new ZohoService();
                        $zohoRespuesta = $zohoService->actualizarCliente($data);

                        $respuesta->success($zohoRespuesta);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede autenticar con éxito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            case ($request === 'usuario'):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                    $usuarios = new UsuariosController;
                    $usuarios->actualizarUser($idUser);
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'usuarios'):
                $handled = true;
                $jsonInput = file_get_contents("php://input");
                $data = json_decode($jsonInput, true);

                if (!$data['idApp']) {
                    $respuesta->_400();
                    $respuesta->message = 'Datos JSON inválidos o vacíos.';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                    break;
                }

                // Extraer el ID del usuario desde la URL
                $id = $data['idApp'];
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo()) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $usuarios = new UsuariosController;
                        $usuarios->actualizarUser($id); // Pasar el ID al método de actualización
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            case (preg_match('/^usuarios\/(\d+)$/', $request, $matches) ? true : false):
                $handled = true;
                // Extraer el ID del usuario desde la URL
                $id = $matches[1];
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo()) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $usuarios = new UsuariosController;
                        $usuarios->actualizarUser($id); // Pasar el ID al método de actualización
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            default:
                $handled = true;
                $respuesta->_400();
                $respuesta->message = 'El End Point no existe en la API';
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                break;
        }
        break;

    case 'DELETE':
        switch (true) {
            case ($request === 'zoho/imprimirWebhook'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        // Obtener los parámetros de la URL
                        $queryParams = $_GET;

                        // Obtener las cabeceras de la solicitud
                        $headers = getallheaders();

                        // Obtener el cuerpo del webhook
                        $webhookData = file_get_contents('php://input');

                        // Decodificar el JSON del cuerpo
                        $decodedData = json_decode($webhookData, true);

                        // Imprimir todos los detalles en el log o como respuesta
                        error_log("Detalles del Webhook:");
                        error_log("URL: " . $_SERVER['REQUEST_URI']);
                        error_log("Parámetros de la URL: " . print_r($queryParams, true));
                        error_log("Cabeceras: " . print_r($headers, true));
                        error_log("Cuerpo del Webhook (JSON): " . $webhookData);
                        error_log("Datos Decodificados: " . print_r($decodedData, true));

                        // Define la ruta del archivo donde quieres guardar los datos
                        $file = 'webhook_data.txt';

                        // Abre el archivo para escribir (en modo de escritura, lo que crea o sobrescribe el archivo)
                        $handle = fopen($file, 'w');

                        if ($handle) {
                            // Guarda los datos en el archivo en formato JSON
                            fwrite($handle, json_encode([
                                'headers' => $headers,
                                'queryParams' => $queryParams,
                                'body' => $decodedData
                            ], JSON_PRETTY_PRINT));

                            fclose($handle); // Cierra el archivo después de escribir

                            echo 'Webhook recibido y guardado correctamente.';
                        } else {
                            echo 'Error al intentar guardar el archivo.';
                        }
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case (preg_match('/zoho\/eliminarCliente\/(\d+)/', $request, $matches) ? true : false):
                $handled = true;
                $clienteId = $matches[1] ?? null; // Extraer el ID del cliente de la URL

                if (!$clienteId) {
                    $respuesta->_400();
                    $respuesta->message = 'ID de cliente requerido';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                    break;
                }

                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        $zohoService = new ZohoService();
                        /*
                        $zohoRespuesta = $zohoService->eliminarCliente($clienteId);
                        */
                        $respuesta->success($zohoRespuesta);
                        echo json_encode($respuesta);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permiso para realizar esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede autenticar con éxito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'usuario/imagen'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        if (isset($_GET['imagen'])) {
                            // Obtener los datos del cuerpo de la solicitud, aunque no los necesitamos para la imagen
                            // El archivo se recibe como parte de $_FILES, no de php://input
                            $imagenes = new Imagenes();
                            $imagenes->borrarImagen($_GET['imagen']);
                        } else {
                            $imagenes = new Imagenes();
                            //recoge el id del usuario por el token
                            $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                            //borra la imagen del usuario
                            $imagenes->borrarImagenUsuario($idUser);
                        }
                    } else {
                        $imagenes = new Imagenes();
                        //recoge el id del usuario por el token
                        $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                        //borra la imagen del usuario
                        $imagenes->borrarImagenUsuario($idUser);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'usuarios/relacionar'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $idPlanta = RequestHelper::getParam('idplanta');
                        $idUsuario = RequestHelper::getParam('idusuario') ?? null;
                        $idProveedor = RequestHelper::getParam('proveedor');
                        $usuarios = new UsuariosController;
                        $usuarios->desrelacionarUsers($idUsuario, $idPlanta, $idProveedor);
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'usuario'):
                $handled = true;
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                    $usuarios = new UsuariosController;
                    $usuarios->eliminarUser($idUser);
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            case (preg_match('/^usuarios\/(\d+)$/', $request, $matches) ? true : false):
                $handled = true;

                // Extraer el ID del usuario desde la URL
                $id = $matches[1];

                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $usuarios = new UsuariosController;
                        $usuarios->eliminarUser($id); // Pasar el ID al método de actualización
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;
            case ($request === 'usuarios' && isset($_GET['idApp'])):
                $handled = true;
                // Verificamos que se haya enviado el JSON y que contenga el campo idApp
                if (isset($_GET['idApp'])) {
                    $id = $_GET['idApp'];  // Extraemos el idApp desde el JSON
                } else {
                    $respuesta->_400();
                    $respuesta->message = "Falta el campo 'idApp' en la solicitud.";
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                    return;
                }
                //Verificamos que existe el usuario CREADOR del token y sino manejamos el error dentro de la funcion
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    // Verificar si el usuario es administrador
                    if ($authMiddleware->verificarAdmin()) {
                        $usuarios = new UsuariosController;
                        $usuarios->eliminarUser($id); // Pasar el ID al método de actualización
                    } else {
                        $respuesta->_403();
                        $respuesta->message = 'No tienes permisos para hacer esta consulta';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            default:
                $handled = true;
                $respuesta->_400();
                $respuesta->message = 'El End Point no existe en la API';
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                break;
        }
        break;

    default:
        $handled = true;
        $respuesta->_405();
        $respuesta->message = 'Este método no está permitido en la API. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
        http_response_code($respuesta->code);
        echo json_encode($respuesta);
        break;
}

if (!$handled) {
    // Manejo global para rutas no definidas
    http_response_code(404);
    $respuesta->_404();
    $respuesta->message = 'La ruta solicitada no existe en esta API.';
    echo json_encode($respuesta);
}
