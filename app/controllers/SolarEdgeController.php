<?php
require_once '../services/SolarEdgeService.php';
/**
 * @param $siteId = el id de la planta
 * @param $startDate = la fecha de inicio
 * @param $endDate = la fecha de fin
 * @return json_encode con los datos que saca desde el servicio
 */
class SolarEdgeController {
    private $solarEdgeService;
    private $logsController;

    public function __construct() {
        $this->solarEdgeService = new SolarEdgeService();
        $this->logsController = new LogsController();
    }

    // Método para obtener los detalles de una planta con id $siteId
    public function getSiteDetails($siteId) {
        // Registrar en logs el acceso a la API
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de SolarEdge para obtener los detalles de una planta");

        // Obtener los datos de la planta desde el servicio de SolarEdge
        $result = $this->solarEdgeService->getSiteDetails($siteId);

        // Decodificar el JSON recibido en un array asociativo
        $decodedResult = json_decode($result, true);

        // Mapear el estado de "status" si existe en "details"
        if (isset($decodedResult['details']['status'])) {
            $decodedResult['details']['status'] = $this->mapSolarEdgeStatus($decodedResult['details']['status']);
        }

        // Añadir un nuevo campo "organizacion" al resultado
        $decodedResult['details']['organizacion'] = "solaredge";

        // Configurar el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        $decodedResult = json_encode($decodedResult);

        // Retornar el JSON modificado
        return json_encode($decodedResult);
    }

    //Método para obtener la grafica
    public function getPowerDashboardCustom($chartField, $foldUp, $timeUnit, $siteId, $billingCycle, $period, $periodDuration, $startTime, $endTime) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge graficas personalizadas");
        $data = $this->solarEdgeService->getPowerDashboardCustom($chartField, $foldUp, $timeUnit,$siteId, $billingCycle, $period, $periodDuration, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    //Método para obtener la grafica
    public function getPowerDashboard($siteId, $dia, $fechaFin,$fechaInicio) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge graficas");
        $data = $this->solarEdgeService->getPowerDashboard($siteId,$dia,$fechaFin,$fechaInicio);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    //Método para obtener los datos de todas las plantas
    public function getAllPlants($page = 1, $pageSize=200) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge todas las plantas");
        $data = $this->solarEdgeService->getAllPlants($page, $pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía del sitio
    public function getSiteEnergy($siteId, $startDate, $endDate) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getSiteEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía en intervalos de cuarto de hora
    public function getQuarterHourlyEnergy($siteId, $startDate, $endDate) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getQuarterHourlyEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía diaria
    public function getDailyEnergy($siteId, $startDate, $endDate) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getDailyEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía diaria para un mes completo
    public function getMonthlyDailyEnergy($siteId, $startDate, $endDate) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getMonthlyDailyEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía anual
    public function getYearlyEnergy($siteId, $startDate, $endDate) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getYearlyEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los beneficios ambientales
    public function getEnvironmentalBenefits($siteId, $systemUnits) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getEnvironmentalBenefits($siteId, $systemUnits);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los beneficios ambientales en unidades métricas
    public function getEnvironmentalBenefitsMetrics($siteId) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getEnvironmentalBenefitsMetrics($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los detalles de energía del consumo mensual
    public function getEnergyDetailsConsumption($siteId, $startTime, $endTime) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getEnergyDetailsConsumption($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
     // Método para obtener los detalles de energía del consumo anual
     public function getEnergyDetailsConsumptionYear($siteId, $startTime, $endTime) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getEnergyDetailsConsumptionYear($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
     // Método para obtener los detalles de energía del consumo diario
     public function getEnergyDetailsConsumptionDay($siteId, $startTime, $endTime) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getEnergyDetailsConsumptionDay($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los detalles de potencia del consumo
    public function getPowerDetailsConsumption($siteId, $startTime, $endTime) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getPowerDetailsConsumption($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de almacenamiento
    public function getStorageData($siteId, $startTime, $endTime) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getStorageData($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener el flujo de energía actual
    public function getCurrentPowerFlow($siteId) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getCurrentPowerFlow($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de sensores
    public function getSensorsData($siteId, $startDate, $endDate) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getSensorsData($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de sensores con un rango de fechas extendido
    public function getSensorsDataExtended($siteId, $startDate, $endDate) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getSensorsDataExtended($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de sensores con un rango de fechas desde el 1 hasta el 30 de octubre
    public function getSensorsDataForMonth($siteId, $startDate, $endDate) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge");
        $data = $this->solarEdgeService->getSensorsDataForMonth($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Función para mapear el estado de SolarEdge a una descripción legible
private function mapSolarEdgeStatus($status) {
    switch ($status) {
        case 'PendingCommunication':
            return 'waiting';
        case 'Active':
            return 'working';
        default:
            return 'unknown';
    }
} 
}
?>
