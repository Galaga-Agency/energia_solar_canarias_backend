<?php
require_once '../services/GoodWeService.php';

class GoodWeController {
    private $goodWeService;

    public function __construct() {
        $this->goodWeService = new GoodWeService();
    }

    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getPlantDetails($powerStationId) {
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetPlantDetailByPowerstationId($powerStationId);
        // Configura el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }

    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getAllPlants() {
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetAllPlants();
        // Configura el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }
}
