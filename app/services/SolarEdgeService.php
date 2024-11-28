<?php
require_once '../utils/HttpClient.php';
require_once '../models/SolarEdge.php';

class SolarEdgeService {
    private $solarEdge;
    private $httpClient;

    public function __construct() {
        $this->solarEdge = new SolarEdge();
        $this->httpClient = new HttpClient();
    }
    
    //Método que devuelve la potencia de las plantas que esten en funcionamiento
    public function getPlantPowerRealtime($siteId){
        $url = $this->solarEdge->getUrl() . "site/$siteId/currentPowerFlow?api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoge la grafica de la planta
    public function getPowerDashboard($siteId, $dia, $fechaFin = null, $fechaInicio = null) {
        // Formato de fecha
        $formato = 'Y-m-d';
    
        // Convertir fechas a DateTime si no son nulas
        $fechaSinFormatearFin = $fechaFin ? new DateTime($fechaFin) : new DateTime('today 23:59:59');
        $fechaSinFormatearInicio = $fechaInicio ? new DateTime($fechaInicio) : null;
    
        // Ajustar fechas según el valor de $dia
        switch ($dia) {
            case "QUARTER_OF_AN_HOUR":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('today');
                break;
            case "DAY":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('yesterday 23:59:59');
                break;
    
            case "WEEK":
                // Si no hay fecha de inicio, tomar el inicio de la semana actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('monday this week 00:00:00');
                break;
    
            case "MONTH":
                // Si no hay fecha de inicio, tomar el inicio del mes actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of this month 00:00:00');
                break;
    
            case "YEAR":
                // Si no hay fecha de inicio, tomar el inicio del año actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of January 00:00:00');
                break;
    
            default:
                return "Error: día incorrecto";
        }
    
        // Formatear las fechas
        $fechaInicioFormateada = $fechaSinFormatearInicio->format($formato);
        $fechaFinFormateada = $fechaSinFormatearFin->format($formato);
    
        // Construir la URL utilizando el formato definido
        $url = $this->solarEdge->getUrl() . "site/$siteId/energy?timeUnit=$dia&startDate=$fechaInicioFormateada&endDate=$fechaFinFormateada&api_key=" . $this->solarEdge->getApiKey();
    
        // Realizar la solicitud
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

        //Recoge la grafica de la planta
        public function getPowerConsumption($siteId, $dia, $fechaInicioFormateada = null, $fechaFinFormateada = null) {
            // Construir la URL utilizando el formato definido
            $url = $this->solarEdge->getUrl() . "site/$siteId/currentPowerFlow?timeUnit=$dia&startDate=$fechaInicioFormateada&endDate=$fechaFinFormateada&api_key=" . $this->solarEdge->getApiKey();
        
            // Realizar la solicitud
            try {
                $response = $this->httpClient->get($url);
                return json_decode($response);
            } catch (Exception $e) {
                return ['error' => $e->getMessage()];
            }
        }
    
    //Recoge la grafica 'Custom' de la planta
    public function getPowerDashboardCustom($chartField, $foldUp, $timeUnit, $siteId, $billingCycle, $period, $periodDuration, $startTime, $endTime) {
        
        $url = $this->solarEdge->getUrl() . "solaredge-apigw/api/site/$siteId/customEnergyDashboardChart?chartField=$chartField&foldUp=$foldUp&timeUnit=$timeUnit&siteId=$siteId&billingCycle=$billingCycle&period=$period&periodDuration=$periodDuration&startTime=$startTime&endTime=$endTime";

        try {
            $response = $this->httpClient->get($url);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoge los detalles de la planta
    public function getSiteDetails($siteId) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/details?api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Método que recoje todas las plantas
    public function getAllPlants($page = 1, $pageSize=200) {
        $url = $this->solarEdge->getUrl() . "sites/list?size=$pageSize&startIndex=$page&api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los datos de sensores con un rango de fechas extendido
    public function getSensorsDataExtended($siteId, $startDate, $endDate) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/sensors";
        $url .= "?startDate=" . urlencode($startDate) . "&endDate=" . urlencode($endDate);
        $url .= "&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>
