<?php

//ENSAYO ACCESO A ARCHIVOS PROHIBIDOS DESDE EL PROYECTO
$direccion = dirname(__FILE__);
$jsondata = file_get_contents($direccion . "/clases/" . "conexion.json");

if ($jsondata !== false) {
    echo $jsondata;
} else {
    echo "Error al obtener el contenido del archivo JSON.";
}

?>