<?php
require_once './../controllers/SolarEdgeController.php';

class ApiControladorService {
    private $solarEdgeController;

    public function __construct() {
        $this->solarEdgeController = new SolarEdgeController();
    }
    public function getAllPlants(){
        $plantsSolarEdge = $this->solarEdgeController->getAllPlants();
        echo $plantsSolarEdge;
    }
    public function getSiteDetail($id){
        $plantsSolarEdge = $this->solarEdgeController->getSiteDetails($id);
        echo $plantsSolarEdge;
    }
    public function getSiteEnergy($siteId, $startDate, $endDate){
        $plantsSolarEdge = $this->solarEdgeController->getSiteEnergy($siteId, $startDate, $endDate);
        echo $plantsSolarEdge;
    }
    public function getQuarterHourlyEnergy($siteId, $startDate, $endDate){
        $plantsSolarEdge = $this->solarEdgeController->getQuarterHourlyEnergy($siteId, $startDate, $endDate);
        echo $plantsSolarEdge;
    }
    public function getYearlyEnergy($siteId, $startDate, $endDate){
        $plantsSolarEdge = $this->solarEdgeController->getYearlyEnergy($siteId,$startDate,$endDate);
        echo $plantsSolarEdge;

    }
}