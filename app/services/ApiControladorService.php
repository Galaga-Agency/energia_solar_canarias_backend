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
}