<?php

require_once "configApi.php";
require_once "clases/respuesta.php";

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

//echo $request;

// Verificar si la cabecera 'usuario' y 'apiKey' están presentes en $_SERVER
$usuario = isset($_SERVER['HTTP_USUARIO']) ? $_SERVER['HTTP_USUARIO'] : null;
$apiKey = isset($_SERVER['HTTP_APIKEY']) ? $_SERVER['HTTP_APIKEY'] : null;

// Verificar que ambas cabeceras están presentes
if ($usuario && $apiKey) {
    // Aquí podrías comparar con los valores esperados o buscarlos en una base de datos
    $usuarioEsperado = 'anfego1';
    $apiKeyEsperada = '***REMOVED***';

    if ($usuario === $usuarioEsperado && $apiKey === $apiKeyEsperada) {
        // Rutas y endpoints
        switch ($method) {
            case 'GET':
                if ($request === 'usuarios') {
                    require_once "clases/usuarios.php";
                    $usuarios = new Usuarios;
                    $response = $usuarios->getAllUsers();
                    http_response_code($response->code);
                    echo json_encode($response);
                } elseif (preg_match('/^usuarios\/(\d+)$/', $request, $matches)) {
                    $id = $matches[1];
                    require_once "clases/usuarios.php";
                    $usuarios = new Usuarios;
                    $response = $usuarios->getUser($id);
                    http_response_code($response->code);
                    echo json_encode($response);
                } elseif (preg_match('/^\/usuarios\/pages\/(\d+)$/', $request, $matches)) {
                    $page = $matches[1]; // Capturamos el número de página de la URL
                    require_once "clases/usuarios.php";
                    $usuarios = new Usuarios;
                    $response = $usuarios->getUser($id);
                    http_response_code($response->code);
                    echo json_encode($response);
                } else {
                    // Endpoint no encontrado
                    http_response_code(404);
                    echo json_encode(['error' => 'Endpoint no encontrado']);
                }
                break;

            case 'POST':
                if ($request === 'products') {
                    // Lógica para crear un nuevo producto
                    echo json_encode(['message' => 'Producto creado']);
                }
                break;

            case 'PUT':
                if (preg_match('/^products\/(\d+)$/', $request, $matches)) {
                    $productId = $matches[1];
                    // Lógica para actualizar un producto específico por ID
                    echo json_encode(['message' => 'Producto actualizado con ID: ' . $productId]);
                }
                break;

            case 'DELETE':
                if (preg_match('/^products\/(\d+)$/', $request, $matches)) {
                    $productId = $matches[1];
                    // Lógica para eliminar un producto específico por ID
                    echo json_encode(['message' => 'Producto eliminado con ID: ' . $productId]);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(['error' => 'Método no permitido']);
                break;
        }
    } else {
        $error->_401();
        $error->message = 'Acceso denegado, el usuario y la apiKey no son válidos';
        http_response_code($error->code);
        echo json_encode($error);
    }
} else {
    $error->_400();
    $error->message = 'Acceso denegado, las cabeceras \'usuario\' y/o \'apiKey\' no están presentes en la solicitud.';
    http_response_code($error->code);
    echo json_encode($error);
}
