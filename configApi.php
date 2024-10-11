<?php

date_default_timezone_set('Atlantic/Canary');

ini_set('curl.cainfo', '/dev/null');
set_time_limit(0);
ini_set('default_socket_timeout', 7200);

ini_set("display_errors", 0);
ini_set("display_startup_errors", 0);
mysqli_report(MYSQLI_REPORT_OFF);

header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Content-Type: application/json; charset=utf-8');
$allowed_origins = [
    'https://app-energiasolarcanarias.com',
    'http://app-energiasolarcanarias.com',
    'https://localhost:3000',
    'http://localhost/esc-backend/',
    // Agrega más dominios permitidos aquí
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true'); // Opcional si necesitas enviar cookies o autenticación
}

