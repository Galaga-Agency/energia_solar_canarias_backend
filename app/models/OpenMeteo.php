<?php
require_once "Nominatim.php";
class OpenMeteo{
    private $nominatim;

    //definimos el constructor de la clase
    public function __construct()
    {
        $this->nominatim = new Nominatim;
    }

    public function obtenerClima($localizacion){
        $nominatimData = $this->nominatim->devolverCoordenadas($localizacion);
       // Extraer latitud y longitud
        $latitude = $nominatimData[0]['lat'];
        $longitude = $nominatimData[0]['lon'];

        // URL de Open-Meteo para obtener el clima
        $openMeteoUrl = "https://api.open-meteo.com/v1/forecast?latitude=$latitude&longitude=$longitude&daily=temperature_2m_max,temperature_2m_min&timezone=Europe/Madrid";

        // Realizar la solicitud a Open-Meteo
        $weatherResponse = file_get_contents($openMeteoUrl);

        if ($weatherResponse === FALSE) {
            die("Error al conectarse a Open-Meteo.");
        }
        return $weatherResponse;
    }
}
?>