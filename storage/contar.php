<?php
$prueba = file_get_contents("prueba.json");
$prueba = json_decode($prueba, true);
$plantas = count($prueba['data']);
echo $plantas;
?>