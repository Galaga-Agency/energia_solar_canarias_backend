<?php
//ENSAYO DE WEBHOOK
$direccion = $_SERVER['DOCUMENT_ROOT'] . '/conexion_esc-backend.json';
$jsondata = file_get_contents($direccion);

if ($jsondata !== false) {
    echo $jsondata;
} else {
    echo "Error al obtener el contenido del archivo JSON.";
}
?>