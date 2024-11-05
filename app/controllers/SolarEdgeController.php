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

    public function __construct() {
        $this->solarEdgeService = new SolarEdgeService();
    }

    //Método para obtener los detalles de una planta con id $siteId
    public function getSiteDetails($siteId) {
        $data = $this->solarEdgeService->getSiteDetails($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    //Método para obtener los datos de todas las plantas
    public function getAllPlants() {
        $data = $this->solarEdgeService->getAllPlants();
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía del sitio
    public function getSiteEnergy($siteId, $startDate, $endDate) {
        $data = $this->solarEdgeService->getSiteEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía en intervalos de cuarto de hora
    public function getQuarterHourlyEnergy($siteId, $startDate, $endDate) {
        $data = $this->solarEdgeService->getQuarterHourlyEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía diaria
    public function getDailyEnergy($siteId, $startDate, $endDate) {
        $data = $this->solarEdgeService->getDailyEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía diaria para un mes completo
    public function getMonthlyDailyEnergy($siteId, $startDate, $endDate) {
        $data = $this->solarEdgeService->getMonthlyDailyEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de energía anual
    public function getYearlyEnergy($siteId, $startDate, $endDate) {
        $data = $this->solarEdgeService->getYearlyEnergy($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los beneficios ambientales
    public function getEnvironmentalBenefits($siteId, $systemUnits) {
        $data = $this->solarEdgeService->getEnvironmentalBenefits($siteId, $systemUnits);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los beneficios ambientales en unidades métricas
    public function getEnvironmentalBenefitsMetrics($siteId) {
        $data = $this->solarEdgeService->getEnvironmentalBenefitsMetrics($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los detalles de energía del consumo mensual
    public function getEnergyDetailsConsumption($siteId, $startTime, $endTime) {
        $data = $this->solarEdgeService->getEnergyDetailsConsumption($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
     // Método para obtener los detalles de energía del consumo anual
     public function getEnergyDetailsConsumptionYear($siteId, $startTime, $endTime) {
        $data = $this->solarEdgeService->getEnergyDetailsConsumptionYear($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
     // Método para obtener los detalles de energía del consumo diario
     public function getEnergyDetailsConsumptionDay($siteId, $startTime, $endTime) {
        $data = $this->solarEdgeService->getEnergyDetailsConsumptionDay($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los detalles de potencia del consumo
    public function getPowerDetailsConsumption($siteId, $startTime, $endTime) {
        $data = $this->solarEdgeService->getPowerDetailsConsumption($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de almacenamiento
    public function getStorageData($siteId, $startTime, $endTime) {
        $data = $this->solarEdgeService->getStorageData($siteId, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener el flujo de energía actual
    public function getCurrentPowerFlow($siteId) {
        $data = $this->solarEdgeService->getCurrentPowerFlow($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de sensores
    public function getSensorsData($siteId, $startDate, $endDate) {
        $data = $this->solarEdgeService->getSensorsData($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de sensores con un rango de fechas extendido
    public function getSensorsDataExtended($siteId, $startDate, $endDate) {
        $data = $this->solarEdgeService->getSensorsDataExtended($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Método para obtener los datos de sensores con un rango de fechas desde el 1 hasta el 30 de octubre
    public function getSensorsDataForMonth($siteId, $startDate, $endDate) {
        $data = $this->solarEdgeService->getSensorsDataForMonth($siteId, $startDate, $endDate);
        header('Content-Type: application/json');
        return json_encode($data);
    }
}
?>
