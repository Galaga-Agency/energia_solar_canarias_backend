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

    public function getPowerDashboard($siteId, $dia, $fechaFin = null, $fechaInicio = null) {
        // Formato de fecha
        $formato = 'Y-m-d';
    
        // Convertir fechas a DateTime si no son nulas
        $fechaSinFormatearFin = $fechaFin ? new DateTime($fechaFin) : new DateTime('today 23:59:59');
        $fechaSinFormatearInicio = $fechaInicio ? new DateTime($fechaInicio) : null;
    
        // Ajustar fechas según el valor de $dia
        switch ($dia) {
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
    
    public function getPowerDashboardCustom($chartField, $foldUp, $timeUnit, $siteId, $billingCycle, $period, $periodDuration, $startTime, $endTime) {
        
        $url = $this->solarEdge->getUrl() . "solaredge-apigw/api/site/1851069/customEnergyDashboardChart?chartField=$chartField&foldUp=$foldUp&timeUnit=$timeUnit&siteId=$siteId&billingCycle=$billingCycle&period=$period&periodDuration=$periodDuration&startTime=$startTime&endTime=$endTime";

        try {
            $response = $this->httpClient->get($url);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }


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
     // Método para obtener los datos de energía del sitio en un rango de fechas
     public function getSiteEnergy($siteId, $startDate, $endDate) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energy";
        $url .= "?timeUnit=DAY&startDate=$startDate&endDate=$endDate&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

     // Método para obtener los datos de energía en intervalos de cuarto de hora
     public function getQuarterHourlyEnergy($siteId, $startDate, $endDate) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energy";
        $url .= "?timeUnit=QUARTER_OF_AN_HOUR&startDate=$startDate&endDate=$endDate&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los datos de energía diaria
    public function getDailyEnergy($siteId, $startDate, $endDate) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energy";
        $url .= "?timeUnit=DAY&startDate=$startDate&endDate=$endDate&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los datos de energía diaria para un mes completo
    public function getMonthlyDailyEnergy($siteId, $startDate, $endDate) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energy";
        $url .= "?timeUnit=DAY&startDate=$startDate&endDate=$endDate&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los datos de energía anual
    public function getYearlyEnergy($siteId, $startDate, $endDate) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energy";
        $url .= "?timeUnit=YEAR&startDate=$startDate&endDate=$endDate&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
     // Método para obtener los beneficios ambientales
     public function getEnvironmentalBenefits($siteId, $systemUnits) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/envBenefits";
        $url .= "?systemUnits=$systemUnits&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
     // Método para obtener los beneficios ambientales en unidades métricas
     public function getEnvironmentalBenefitsMetrics($siteId) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/envBenefits";
        $url .= "?systemUnits=Metrics&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
     // Método para obtener los detalles de energía del consumo mensual
     public function getEnergyDetailsConsumption($siteId, $startTime, $endTime) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energyDetails";
        $url .= "?meters=CONSUMPTION&timeUnit=MONTH&startTime=" . urlencode($startTime) . "&endTime=" . urlencode($endTime);
        $url .= "&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los detalles de energía del consumo anual
    public function getEnergyDetailsConsumptionYear($siteId, $startTime, $endTime) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energyDetails";
        $url .= "?meters=CONSUMPTION&timeUnit=YEAR&startTime=" . urlencode($startTime) . "&endTime=" . urlencode($endTime);
        $url .= "&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los detalles de energía del consumo diario
    public function getEnergyDetailsConsumptionDay($siteId, $startTime, $endTime) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energyDetails";
        $url .= "?meters=CONSUMPTION&timeUnit=DAY&startTime=" . urlencode($startTime) . "&endTime=" . urlencode($endTime);
        $url .= "&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los detalles de potencia del consumo
    public function getPowerDetailsConsumption($siteId, $startTime, $endTime) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/powerDetails";
        $url .= "?meters=CONSUMPTION&startTime=" . urlencode($startTime) . "&endTime=" . urlencode($endTime);
        $url .= "&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
     // Método para obtener los datos de almacenamiento
     public function getStorageData($siteId, $startTime, $endTime) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/storageData";
        $url .= "?startTime=" . urlencode($startTime) . "&endTime=" . urlencode($endTime);
        $url .= "&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener el flujo de energía actual
    public function getCurrentPowerFlow($siteId) {
        $url = $this->solarEdge->getUrl() . "site/$siteId/currentPowerFlow";
        $url .= "?api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los datos de sensores
    public function getSensorsData($siteId, $startDate, $endDate) {
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
     // Método para obtener los datos de sensores con un rango de fechas desde el 1 hasta el 30 de octubre
     public function getSensorsDataForMonth($siteId, $startDate, $endDate) {
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
