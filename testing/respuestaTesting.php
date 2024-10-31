<?php
require_once "./../app/utils/respuesta.php";
$respuesta = new Paginacion();
$respuesta->error_400();
echo json_encode($respuesta);
