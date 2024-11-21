<?php
class Nominatim{
//Esta api es para sacar las coordenadas de la ubicación
//ejemplo https://nominatim.openstreetmap.org/search?q=Los+Parrales+5,+Arucas,+Canary+Islands,+Spain&format=json
public function devolverCoordenadas($localizacion){
    // URL de Nominatim para obtener coordenadas
    $nominatimUrl = "https://nominatim.openstreetmap.org/search?q=" . urlencode($localizacion) . "&format=json&limit=1";

    $options = [
        "http" => [
            "header" => "User-Agent: MyApp/1.0 (your_email@example.com)"
        ]
    ];

    $context = stream_context_create($options);

    // Realizar la solicitud a Nominatim
    $nominatimResponse = file_get_contents($nominatimUrl, false, $context);

    if ($nominatimResponse === FALSE) {
        die("Error al conectarse a Nominatim.");
    }
    
    // Decodificar la respuesta JSON de Nominatim
    $nominatimData = json_decode($nominatimResponse, true);
    
    if (!isset($nominatimData[0])) {
        die("No se encontraron coordenadas para la ubicación especificada.");
    }
    return $nominatimData;
}
}

?>