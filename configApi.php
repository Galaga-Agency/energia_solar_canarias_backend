<?php

require_once 'clases/respuesta.php';
require_once "clases/conexion.php";

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
header('Access-Control-Allow-Origin: http://localhost/esc-backend, https://app-energiasolarcanarias.com');

$conexion = new Conexion;
$respuesta = new Respuesta;
$error = new Errores;

