<?php
require_once '../services/GoodWeService.php';

class GoodWeController {
    private $goodWeService;
    private $logsController;

    public function __construct() {
        $this->goodWeService = new GoodWeService();
        $this->logsController = new LogsController();
    }

    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getPlantDetails($powerStationId) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de GoodWe");
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
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de GoodWe");
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetAllPlants();
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
    public function getChartByPlants($data) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de GoodWe");
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetChartByPlant($data);
        // Configura el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }
}
