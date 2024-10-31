<?php
ini_set('curl.cainfo', '/dev/null');
set_time_limit(0);
ini_set('default_socket_timeout', 28800);
date_default_timezone_set('Atlantic/Canary');

function obtenerDatosSolarEdge($i) {
    // URL de la API de SolarEdge
    $url = "https://monitoringapi.solaredge.com/site/1851069/currentPowerFlow?api_key=***REMOVED***";

    // Hacer la solicitud a la API
    $respuesta = file_get_contents($url);

    // Verificar si la solicitud fue exitosa
    if ($respuesta === FALSE) {
        http_response_code(500); // Error del servidor
        echo json_encode(["success" => false, "message" => "Error al obtener datos de la API de SolarEdge."]);
        return;
    }

    // Decodificar la respuesta JSON
    $datos = json_decode($respuesta, true);

    // Verificar si la decodificación fue exitosa
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500); // Error del servidor
        echo json_encode(["success" => false, "message" => "Error al decodificar la respuesta JSON."]);
        return;
    }
    echo("<br><br><br><br><br>");
    echo("{$i}<br>");
    // Retornar los datos ordenados en formato JSON
    http_response_code(200); // Solicitud exitosa
    echo json_encode(["success" => true, "data" => $datos]);
    @ob_flush();
    flush();
}
$contador = 0;
while(true){
    $time = date('Y-m-d H:i:s');
echo "{$time} <br><br>"; // Ejemplo de salida: 2024-10-25 14:30:00
@ob_flush();
flush();
try {
    // Llamar a la función para obtener y ordenar los datos
    obtenerDatosSolarEdge($contador);
    $contador++;
} catch (\Throwable $th) {
    obtenerDatosSolarEdge($contador);
    $contador++;
}
// Agregar un retraso de 5 segundos
$rnd = random_int(0, 10);
sleep(5);
}


?>