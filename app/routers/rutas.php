<?php ob_start(); ?>
<?php ob_start(); ?>
<?php
// Mostrar errores en pantalla
//ini_set('display_errors', 1); // Activar la visualización de errores
//error_reporting(E_ALL);
require_once __DIR__ . '/../utils/cabeceras_seguridad.php';
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
require_once __DIR__ . '/../controllers/SungrowController.php';
require_once __DIR__ . '/../controllers/SigenergyController.php';
require_once __DIR__ . '/../services/ApiControladorService.php';
require_once __DIR__ . '/../services/ProveedorApiService.php';
require_once __DIR__ . '/../services/GoodWeService.php';
require_once __DIR__ . '/../services/SolarEdgeService.php';
require_once __DIR__ . '/../services/SungrowService.php';
require_once __DIR__ . '/../services/SigenergyService.php';
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
    'VictronEnergy' => 'victronenergy',
    'Sungrow' => 'sungrow',
    'Sigenergy' => 'sigenergy'
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

// Si la petición es a la raíz con ?page=, redirigir a index.php (documentación)
if (empty($request) && isset($_GET['page'])) {
    chdir(__DIR__ . '/../../');
    require_once __DIR__ . '/../../index.php';
    exit;
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
            // Paso 2 del acceso sin contraseña: el usuario pulsa el enlace del
            // correo. Valida, consume el token y REDIRIGE al frontend con un
            // codigo de traspaso de un solo uso. Nunca devuelve JSON: aqui ha
            // llegado un navegador siguiendo un enlace, no una llamada de la
            // API. Ver MagicLinkController.
            //
            // Va lo PRIMERO del switch a proposito: es la unica ruta publica
            // del bloque GET y no debe pasar por ninguna comprobacion de token.
            case ($request === 'auth/magic'):
                $handled = true;
                require_once __DIR__ . '/../controllers/MagicLinkController.php';
                $magicController = new MagicLinkController();
                $magicController->canjearEnlace();
                break;

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
            // Alertas silenciadas vigentes. Las caducadas no se devuelven: la
            // alerta vuelve a avisar sola, sin reactivarla a mano.
            case (preg_match('/^plant\/alert\/silenciadas$/', $request, $matches) ? true : false):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    require_once __DIR__ . '/../DBObjects/alertasSilenciadasDB.php';

                    $plantaId  = $_GET['plantaId'] ?? null;
                    $proveedor = $_GET['proveedor'] ?? null;

                    $db = new AlertasSilenciadasDB;
                    $filas = $db->listar($plantaId, $proveedor);

                    if ($filas === false) {
                        $respuesta->_500();
                        $respuesta->message = 'No se ha podido consultar las alertas silenciadas';
                    } else {
                        $respuesta->success($filas);
                        $respuesta->message = '200 - Solicitud exitosa';
                    }
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
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
                        $proveedor = $_GET['proveedor'];
                        // Admin -> cualquier planta. Usuario normal -> solo las suyas.
                        // Aqui la planta va en `siteId`; la rama de GoodWe no lleva id
                        // (devuelve las alarmas de TODO el parque) y se controla aparte.
                        if (isset($_GET['siteId']) && !$authMiddleware->puedeVerPlanta($_GET['siteId'], $proveedor)) break;
                        // GoodWe es el unico cuyo endpoint de alarmas NO acepta una
                        // planta: devuelve las de TODO el parque filtradas por estado.
                        // Por eso no esta en el contrato (ver GoodWeAdaptador) y se
                        // atiende aparte, y solo para admin: darselas a un cliente seria
                        // ensenarle alarmas de instalaciones ajenas.
                        if ($proveedor === $proveedores['GoodWe']) {
                            $pageIndex = isset($_GET['pageIndex']) ? $_GET['pageIndex'] : 1;
                            $pageSize = isset($_GET['pageSize']) ? $_GET['pageSize'] : 200;
                            $status = isset($_GET['status']) ? $_GET['status'] : 3;

                            // Un cliente TAMBIEN ve sus alarmas, filtradas.
                            //
                            // Antes esto respondia 403 a quien no fuera admin, asi que un
                            // cliente abriendo una planta GoodWe no veia ninguna alerta y
                            // la peticion fallaba una y otra vez. Y devolverle la lista
                            // entera tampoco vale: son las alarmas de TODO el parque, o
                            // sea las instalaciones de otros clientes.
                            //
                            // Cada alarma trae `stationId`, asi que se piden todas y se
                            // deja solo las de sus plantas. El filtro se hace AQUI, no en
                            // el frontend: lo que no debe ver, no sale del servidor.
                            if (!$authMiddleware->verificarAdmin()) {
                                require_once __DIR__ . '/../DBObjects/plantasAsociadasDB.php';
                                $idUsuario = $authMiddleware->obtenerIdUsuarioActivo();
                                $propias = (new PlantasAsociadasDB)->getPlantasAsociadasAlUsuario($idUsuario);
                                $idsPropias = [];
                                foreach (($propias ?: []) as $fila) {
                                    $idsPropias[(string) $fila['planta_id']] = true;
                                }
                                (new ApiControladorService)->alarmasGoodWeDelUsuario(
                                    $pageIndex,
                                    $pageSize,
                                    $status,
                                    $idsPropias
                                );
                                break;
                            }

                            (new ApiControladorService)->GetPowerStationWariningInfoByMultiCondition($pageIndex, $pageSize, $status);
                            break;
                        }

                        // El resto SI son por planta, asi que el siteId es obligatorio.
                        if (!isset($_GET['siteId'])) {
                            $respuesta->_404();
                            $respuesta->message = 'No se ha encontrado el siteId';
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                            break;
                        }
                        $pageIndex = isset($_GET['pageIndex']) ? $_GET['pageIndex'] : 1;
                        $pageSize = isset($_GET['pageSize']) ? $_GET['pageSize'] : 200;
                        // Quien no tenga alertas (SolarEdge) lo dice el mismo -> 404.
                        (new ProveedorApiService)->alertas($proveedor, $_GET['siteId'], (int) $pageIndex, (int) $pageSize);
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
                        $proveedor = $_GET['proveedor'];
                        // Admin -> cualquier planta. Usuario normal -> solo las suyas.
                        if (!$authMiddleware->puedeVerPlanta($powerStationId, $proveedor)) break;
                        // Sin switch: el registro resuelve el proveedor y el contrato
                        // dice como se pide el inventario. Ver ProveedorApiService.
                        (new ProveedorApiService)->inventario($proveedor, $powerStationId);
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
                        $proveedor = $_GET['proveedor'];
                        // Admin -> cualquier planta. Usuario normal -> solo las suyas.
                        if (!$authMiddleware->puedeVerPlanta($powerStationId, $proveedor)) break;
                        // Sin switch: quien no ofrezca resumen lo dice el mismo y sale
                        // un 404. Ver ProveedorApiService y ProveedorBase.
                        (new ProveedorApiService)->resumen($proveedor, $powerStationId);
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
                        $proveedor = $_GET['proveedor'];
                        // Admin -> cualquier planta. Usuario normal -> solo las suyas.
                        if (!$authMiddleware->puedeVerPlanta($powerStationId, $proveedor)) break;
                        // Sin switch: quien no ofrezca beneficios lo dice el mismo.
                        (new ProveedorApiService)->beneficios($proveedor, $powerStationId);
                    }
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            // Usuarios con acceso a una planta. Es la consulta inversa de
            // usuarios/{id}/plantas, que el frontend necesita para la ficha de
            // la planta: sin ella habria que pedir todos los usuarios y sus
            // plantas para pintar un solo panel.
            //
            // Solo administradores: saber quien tiene acceso a una instalacion
            // es informacion de gestion, no de monitorizacion.
            case (preg_match('/^planta\/usuarios\/([\w-]+)$/', $request, $matches) ? true : false):
                $handled = true;
                $idPlanta = $matches[1];
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    if ($authMiddleware->verificarAdmin()) {
                        // Explicito: hoy llega via otros require_once, pero
                        // depender de eso rompe en cuanto cambie ese orden.
                        require_once __DIR__ . '/../DBObjects/plantasAsociadasDB.php';
                        // Se acepta el slug que usa el resto de la API
                        // ("victronenergy") y se traduce al nombre que guarda
                        // la tabla proveedores ("VictronEnergy"). Pedir el
                        // nombre exacto obligaria al cliente a conocer una
                        // segunda forma de escribir cada proveedor, y los dos
                        // se parecen lo bastante como para fallar en silencio.
                        $proveedorParam = $_GET['proveedor'] ?? null;
                        $proveedor = null;
                        if ($proveedorParam !== null && $proveedorParam !== '') {
                            $slug = strtolower($proveedorParam);
                            foreach ($proveedores as $nombre => $clave) {
                                if ($clave === $slug || strtolower($nombre) === $slug) {
                                    $proveedor = $nombre;
                                    break;
                                }
                            }
                            // Proveedor desconocido: mejor no filtrar por un
                            // nombre inventado, que devolveria vacio siempre.
                            if ($proveedor === null) $proveedor = $proveedorParam;
                        }

                        $plantasAsociadasDB = new PlantasAsociadasDB;
                        $usuarios = $plantasAsociadasDB->getUsuariosDeLaPlanta($idPlanta, $proveedor);

                        if ($usuarios === false) {
                            $respuesta->_500();
                            $respuesta->message = 'No se ha podido consultar los usuarios de la planta';
                        } else {
                            // Una planta sin usuarios asociados es un resultado
                            // valido (lista vacia), no un 404.
                            $respuesta->success($usuarios);
                            $respuesta->message = '200 - Solicitud exitosa';
                        }
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

            // Preferencias de notificacion del propio usuario. Sin fila en la
            // tabla se devuelven los valores por defecto, asi que esta ruta
            // nunca esta vacia.
            case ($request === 'usuario/notificaciones'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    require_once __DIR__ . '/../DBObjects/preferenciasNotificacionesDB.php';
                    $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                    $prefsDB = new PreferenciasNotificacionesDB();
                    $respuesta->success(true);
                    $respuesta->data = $prefsDB->obtener($idUser);
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            // Actividad reciente de la cuenta. Solo la del propio usuario: el
            // usuario_id sale del token, nunca de la peticion.
            case ($request === 'usuario/actividad'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                    $conn = Conexion::getInstance()->getConexion();
                    $stmt = $conn->prepare(
                        "SELECT id, timestamp, level, message
                           FROM logs
                          WHERE usuario_id = ?
                       ORDER BY timestamp DESC
                          LIMIT 30"
                    );
                    $filas = [];
                    if ($stmt) {
                        $stmt->bind_param('i', $idUser);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        while ($fila = $res->fetch_assoc()) {
                            $filas[] = $fila;
                        }
                        $stmt->close();
                    }
                    $respuesta->success(true);
                    $respuesta->data = $filas;
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            // Claves de API del propio usuario. Sin la clave en si: solo se ve
            // una vez, al crearla, y volver a mostrarla convertiria esta ruta
            // en un sitio del que robar credenciales.
            case ($request === 'usuario/api-keys'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                    require_once __DIR__ . '/../DBObjects/apiAccesosDB.php';
                    $apiAccesosDB = new ApiAccesosDB();
                    $respuesta->success(true);
                    $respuesta->data = $apiAccesosDB->listarPorUsuario($idUser);
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
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
                if ($authMiddleware->getBearerToken()) {
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
                    // Admin -> cualquier planta. Usuario normal -> solo las suyas.
                    if (!$authMiddleware->puedeVerPlanta($powerStationId, $proveedor)) break;
                    // Sin switch: lo resuelve el registro. Ver ProveedorApiService.
                    (new ProveedorApiService)->tiempoReal($proveedor, $powerStationId);
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
                            case $proveedores['Sungrow']:
                                if ($admin) {
                                    $apiControladorService->getAllPlantsSungrow($page, $pageSize);
                                } else {
                                    $respuesta->_403();
                                    $respuesta->message = 'No tienes permisos para hacer esta consulta';
                                    http_response_code($respuesta->code);
                                    echo json_encode($respuesta);
                                }
                                break;
                            case $proveedores['Sigenergy']:
                                if ($admin) {
                                    $apiControladorService->getAllPlantsSigenergy($page, $pageSize);
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
            // Silenciar / reactivar una alerta, y listar las silenciadas.
            //
            // El silenciado es NUESTRO, no del proveedor: los cinco exponen las
            // alertas en modo lectura, asi que se guarda aparte y se aplica al
            // presentar la lista. La alerta sigue existiendo y sigue llegando.
            //
            // Cualquier usuario con acceso a la planta puede silenciar: quien
            // vigila una instalacion es quien sabe que un aviso es ruido.
            case (preg_match('/^plant\/alert\/silenciar$/', $request, $matches) ? true : false):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    require_once __DIR__ . '/../DBObjects/alertasSilenciadasDB.php';

                    $body = json_decode(file_get_contents("php://input"), true) ?: [];
                    $proveedor = $body['proveedor'] ?? ($_GET['proveedor'] ?? null);
                    $plantaId  = $body['plantaId'] ?? null;
                    $alertaId  = $body['alertaId'] ?? null;
                    $hasta     = $body['hasta'] ?? null;   // 'Y-m-d H:i:s' o null
                    $motivo    = $body['motivo'] ?? null;

                    if (!$proveedor || !$plantaId || !$alertaId) {
                        $respuesta->_400();
                        $respuesta->message = 'Faltan parametros: proveedor, plantaId y alertaId son obligatorios';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                        break;
                    }

                    // Mismo control que para ver la planta: si no puede verla,
                    // no puede silenciar sus alertas.
                    if (!$authMiddleware->puedeVerPlanta($plantaId, $proveedor)) break;

                    $usuarioId = $authMiddleware->obtenerIdUsuarioActivo();
                    $db = new AlertasSilenciadasDB;

                    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                        $ok = $db->reactivar($proveedor, $plantaId, $alertaId);
                        $accion = 'alerta_reactivada';
                    } else {
                        $ok = $db->silenciar($proveedor, $plantaId, $alertaId, $usuarioId, $hasta, $motivo);
                        $accion = 'alerta_silenciada';
                    }

                    if ($ok) {
                        registrarEventoSeguridad($accion, "usuario=$usuarioId | proveedor=$proveedor | planta=$plantaId | alerta=$alertaId");
                        $respuesta->success([]);
                        $respuesta->message = '200 - Solicitud exitosa';
                    } else {
                        $respuesta->_500();
                        $respuesta->message = 'No se ha podido guardar el silenciado';
                    }
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

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
                        // Admin -> cualquier planta. Usuario normal -> solo las suyas.
                        // Aqui llegan VARIOS ids separados por comas: hay que comprobarlos
                        // todos, o colando una planta suya se leerian las demas.
                        $permitido = true;
                        foreach (explode(',', $powerStationIds) as $unId) {
                            if (!$authMiddleware->puedeVerPlanta(trim($unId), $proveedor)) {
                                $permitido = false;
                                break;
                            }
                        }
                        if (!$permitido) break;
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
                        // Admin -> cualquier planta. Usuario normal -> solo las suyas.
                        if (!$authMiddleware->puedeVerPlanta($powerStationId, $proveedor)) break;
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
                        // Admin -> cualquier planta. Usuario normal -> solo las suyas.
                        if (!$authMiddleware->puedeVerPlanta($powerStationId, $proveedor)) break;
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

            // ── Acceso sin contraseña (enlace magico) ──────────────────────
            // Convive con /login y /token, que siguen funcionando mientras
            // dure la migracion del frontend. Ver MagicLinkController.

            // Paso 1: pedir el enlace. Responde igual exista el email o no.
            case ($request === 'auth/magic-link'):
                $handled = true;
                require_once __DIR__ . '/../controllers/MagicLinkController.php';
                $postBody = file_get_contents("php://input");
                $magicController = new MagicLinkController();
                $magicController->solicitarEnlace($postBody);
                break;

            // Paso 3: canjear el codigo de traspaso por el JWT de sesion.
            case ($request === 'auth/handoff'):
                $handled = true;
                require_once __DIR__ . '/../controllers/MagicLinkController.php';
                $postBody = file_get_contents("php://input");
                $magicController = new MagicLinkController();
                $magicController->canjearHandoff($postBody);
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
                            case $proveedores['Sungrow']:
                                $apiController->getGraficasSungrow();
                                break;
                            case $proveedores['Sigenergy']:
                                $apiController->getGraficasSigenergy();
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

                        // Usuario normal -> solo sus plantas. Aqui la planta viaja en el
                        // CUERPO, no en la ruta: `id` en casi todos y `siteId` en
                        // SolarEdge (ver getEnergyDashBoardCuerpo).
                        $cuerpoGrafica = json_decode(file_get_contents('php://input'), true);
                        $idGrafica = $cuerpoGrafica['id'] ?? $cuerpoGrafica['siteId'] ?? null;
                        if ($idGrafica === null) {
                            $respuesta->_400();
                            $respuesta->message = 'Falta el id de la planta en el cuerpo';
                            http_response_code($respuesta->code);
                            echo json_encode($respuesta);
                            break;
                        }
                        if (!$authMiddleware->puedeVerPlanta($idGrafica, $proveedor)) break;

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
                            case $proveedores['Sungrow']:
                                $apiController->getGraficasSungrow();
                                break;
                            case $proveedores['Sigenergy']:
                                $apiController->getGraficasSigenergy();
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
            // Guardar las preferencias de notificacion del propio usuario.
            //
            // Esta ruta estaba declarada DOS veces dentro del bloque GET: la de
            // lectura primero y esta justo despues, asi que el switch entraba
            // siempre en la primera y el guardado no era alcanzable por ningun
            // metodo. El frontend hacia PUT y recibia "El End Point no existe
            // en la API", de modo que los interruptores de ajustes no
            // guardaban nada. El cuerpo del handler no cambia; solo pasa al
            // bloque que le corresponde.
            case ($request === 'usuario/notificaciones'):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    require_once __DIR__ . '/../DBObjects/preferenciasNotificacionesDB.php';
                    $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                    $datos = json_decode(file_get_contents("php://input"), true) ?: [];
                    $prefsDB = new PreferenciasNotificacionesDB();

                    if ($prefsDB->guardar($idUser, $datos)) {
                        $respuesta->success(true);
                        // Se devuelve lo GUARDADO, no lo enviado: la severidad y
                        // la frecuencia pasan por lista blanca, y el frontend
                        // tiene que ver el valor que ha quedado de verdad.
                        $respuesta->data = $prefsDB->obtener($idUser);
                    } else {
                        $respuesta->_500();
                        $respuesta->message = 'No se pudieron guardar las preferencias';
                    }
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
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
            // Revocar una clave de API propia. Se marca revocada en vez de
            // borrarse: despues de una filtracion, saber cuando existio y
            // cuando se uso por ultima vez es justo lo que hace falta.
            case (preg_match('/^usuario\/api-keys\/(\d+)$/', $request, $matches) ? true : false):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    require_once __DIR__ . '/../DBObjects/apiAccesosDB.php';
                    $idUser = $authMiddleware->obtenerIdUsuarioActivo();
                    $apiAccesosDB = new ApiAccesosDB();
                    if ($apiAccesosDB->revocar($idUser, (int) $matches[1])) {
                        $respuesta->success(true);
                        $respuesta->message = 'Clave revocada';
                    } else {
                        $respuesta->_404();
                        $respuesta->message = 'Clave no encontrada o ya revocada';
                    }
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                } else {
                    $respuesta->_403();
                    $respuesta->message = 'El token no se puede authentificar con exito';
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
                break;

            // Silenciar / reactivar una alerta, y listar las silenciadas.
            //
            // El silenciado es NUESTRO, no del proveedor: los cinco exponen las
            // alertas en modo lectura, asi que se guarda aparte y se aplica al
            // presentar la lista. La alerta sigue existiendo y sigue llegando.
            //
            // Cualquier usuario con acceso a la planta puede silenciar: quien
            // vigila una instalacion es quien sabe que un aviso es ruido.
            case (preg_match('/^plant\/alert\/silenciar$/', $request, $matches) ? true : false):
                $handled = true;
                if ($authMiddleware->verificarTokenUsuarioActivo() != false) {
                    require_once __DIR__ . '/../DBObjects/alertasSilenciadasDB.php';

                    $body = json_decode(file_get_contents("php://input"), true) ?: [];
                    $proveedor = $body['proveedor'] ?? ($_GET['proveedor'] ?? null);
                    $plantaId  = $body['plantaId'] ?? null;
                    $alertaId  = $body['alertaId'] ?? null;
                    $hasta     = $body['hasta'] ?? null;   // 'Y-m-d H:i:s' o null
                    $motivo    = $body['motivo'] ?? null;

                    if (!$proveedor || !$plantaId || !$alertaId) {
                        $respuesta->_400();
                        $respuesta->message = 'Faltan parametros: proveedor, plantaId y alertaId son obligatorios';
                        http_response_code($respuesta->code);
                        echo json_encode($respuesta);
                        break;
                    }

                    // Mismo control que para ver la planta: si no puede verla,
                    // no puede silenciar sus alertas.
                    if (!$authMiddleware->puedeVerPlanta($plantaId, $proveedor)) break;

                    $usuarioId = $authMiddleware->obtenerIdUsuarioActivo();
                    $db = new AlertasSilenciadasDB;

                    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                        $ok = $db->reactivar($proveedor, $plantaId, $alertaId);
                        $accion = 'alerta_reactivada';
                    } else {
                        $ok = $db->silenciar($proveedor, $plantaId, $alertaId, $usuarioId, $hasta, $motivo);
                        $accion = 'alerta_silenciada';
                    }

                    if ($ok) {
                        registrarEventoSeguridad($accion, "usuario=$usuarioId | proveedor=$proveedor | planta=$plantaId | alerta=$alertaId");
                        $respuesta->success([]);
                        $respuesta->message = '200 - Solicitud exitosa';
                    } else {
                        $respuesta->_500();
                        $respuesta->message = 'No se ha podido guardar el silenciado';
                    }
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
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

// --- production config ---
ini_set("display_errors", 0);
error_reporting(0);
// -------------------------
