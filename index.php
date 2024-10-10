<?php

require_once "configApi.php";

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
            require_once "usuarios.php";
        } elseif (preg_match('/^usuarios\/(\d+)$/', $request, $matches)) {
            $productId = $matches[1];
            // Lógica para devolver un producto específico por ID
            echo json_encode(['message' => 'Producto con ID: ' . $productId]);
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