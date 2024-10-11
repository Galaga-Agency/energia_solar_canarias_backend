<?php

// Manejo de solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 200 OK');
    exit();
}

$allowed_origins = [
    'http://localhost',  // Durante desarrollo desde localhost
    'http://localhost:3000',  // Durante desarrollo desde localhost
    'https://app-energiasolarcanarias.com', // Producción
    'http://app-energiasolarcanarias.com', // Desarrollo desde el servidor de producción
    // Agrega más dominios permitidos aquí si es necesario
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, usuario, apiKey');
    header('Access-Control-Allow-Credentials: true'); // Solo si necesitas enviar cookies o encabezados de autenticación
    header('Content-Type: application/json; charset=utf-8');
}

date_default_timezone_set('Atlantic/Canary');

ini_set('curl.cainfo', '/dev/null');
set_time_limit(0);
ini_set('default_socket_timeout', 7200);

ini_set("display_errors", 0);
ini_set("display_startup_errors", 0);
mysqli_report(MYSQLI_REPORT_OFF);
