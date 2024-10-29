<?php

require_once "../../config/configApi.php";
require_once "../middlewares/autenticacion.php";
require_once "../controllers/usuarios.php";
require_once "../controllers/login.php";
require_once "../controllers/token.php";
require_once "../utils/respuesta.php";
require_once "../DBObjects/usuariosDB.php";

$respuesta = new Respuesta;
$error = new Errores;

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

// Rutas y endpoints
switch ($method) {
    case 'GET':
        if ($request === 'usuarios') {
            // Obtener el token del encabezado Authorization
            $headers = getallheaders();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
            if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $jwtToken = $matches[1]; // Extraer el token

                // Verificar el token
                $autenticar = Conexion::verifyJwt($jwtToken);
                
                if ($autenticar) {
                        // Si el token es válido, continuar con la lógica
                        $usuarios = new UsuariosController;
                        $dboUser = new UsuariosDB();
                    if($dboUser->getAdmin($autenticar['id'])){
                        $usuarios->getAllUsers();
                    }else{
                        // Si el token no es válido, enviar un error de autorización
                        $error->_400();
                        $error->message = 'Operación no autorizada. No eres administrador.';
                        http_response_code($error->code);
                        echo json_encode($error);
                        die();
                    }
                } else {
                    // Si el token no es válido, enviar un error de autorización
                    $error->_400();
                    $error->message = 'No autorizado. Token inválido o expirado.';
                    http_response_code("$error->code");
                    echo json_encode($error);
                    die();
                }
            } else {
               // Si no se proporciona el token o no es un Bearer Token
               $error->_400();
               $error->message = 'Encabezado de autorización faltante o incorrecto';
               http_response_code($error->code);
               echo json_encode($error);
               die();
            }
        } elseif (preg_match('/^usuarios\/(\d+)$/', $request, $matches)) {
            $id = $matches[1];
            $autenticar = new Autenticacion('getUser');
            $autenticar->execute();
            $usuarios = new UsuariosController;
            $usuarios->getUser($id);
        } elseif (preg_match('/^\/usuarios\/pages\/(\d+)$/', $request, $matches)) {
            $page = $matches[1]; // Capturamos el número de página de la URL
            require_once "../models/usuarios.php";
            $usuarios = new Usuarios;
            $response = $usuarios->getUser($id);
            http_response_code($response->code);
            echo json_encode($response);
        } else {
            $error->_400();
            $error->message = "El End Point no existe en la API {$request}";
            http_response_code("$error->code");
            echo json_encode($error);
        }
        break;

    case 'POST':
        if ($request === 'login') {
            $autenticar = new Autenticacion('userLogin');
            $autenticar->execute();
            $postBody = file_get_contents("php://input");
            $loginController = new LoginController($postBody);
            $loginController->userLogin();
        } elseif ($request === 'token') {
            $autenticar = new Autenticacion('validarToken');
            $autenticar->execute();
            $postBody = file_get_contents("php://input");
            $tokenController = new TokenController($postBody);
            $tokenController->validarToken();
        } else {
            $error->_400();
            $error->message = 'El End Point no existe en la API';
            http_response_code($error->code);
            echo json_encode($error);
        }
        break;

    case 'PUT':
        if (preg_match('/^products\/(\d+)$/', $request, $matches)) {
            $productId = $matches[1];
            // Lógica para actualizar un producto específico por ID
            echo json_encode(['message' => 'Producto actualizado con ID: ' . $productId]);
        } else {
            $error->_400();
            $error->message = 'El End Point no existe en la API';
            http_response_code($error->code);
            echo json_encode($error);
        }
        break;

    case 'DELETE':
        if (preg_match('/^products\/(\d+)$/', $request, $matches)) {
            $productId = $matches[1];
            // Lógica para eliminar un producto específico por ID
            echo json_encode(['message' => 'Producto eliminado con ID: ' . $productId]);
        } else {
            $error->_400();
            $error->message = 'El End Point no existe en la API';
            http_response_code($error->code);
            echo json_encode($error);
        }
        break;

    default:
        $this->error->_405();
        $this->error->message = 'Este método no está permitido en la API. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
        http_response_code($this->error->code);
        echo json_encode($this->error);
}