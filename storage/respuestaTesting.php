<?php
require_once "./../app/utils/respuesta.php";
$respuesta = new Paginacion();
$respuesta->_400();
echo json_encode($respuesta);
