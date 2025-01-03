<?php
require_once "../app/models/OpenMeteo.php";
$openMeteo = new OpenMeteo;
$openMeteo->obtenerClima("Calle Canadá 8, San Bartolomé de Tirajana, Spain ");
?>