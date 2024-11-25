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
    public function getPlantPowerRealtime($powerStationId) {
        // Registrar el acceso en los logs
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de GoodWe Realtime");
    
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->getPlantPowerRealtime($powerStationId);
    
        // Configurar el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);;
    }

    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getPlantDetails($powerStationId) {
        // Registrar el acceso en los logs
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de GoodWe");
    
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetPlantDetailByPowerstationId($powerStationId);
    
        // Asegurar que $result sea un arreglo o JSON válido
        $decodedResult = json_decode($result, true);
    
        // Verificar si existe el nodo "info" con el campo "status"
        if (isset($decodedResult['data']['info']['status'])) {
            $decodedResult['data']['info']['status'] = $this->mapGoodWeStatus($decodedResult['data']['info']['status']);
        }

        // Añadir el campo "organizacion" al resultado
        $decodedResult['data']['organization'] = "goodwe";
    
        // Configurar el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        $decodedResult = json_encode($decodedResult);
        // Retornar el objeto modificado como JSON
        return json_encode($decodedResult);
    }
    
    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getAllPlants($page = 1, $pageSize = 200) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de GoodWe");
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetAllPlants($page,$pageSize);
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
    // Función para mapear el estado de GoodWe a una descripción legible
private function mapGoodWeStatus($statusCode) {
    switch ($statusCode) {
        case 2:
            return 'error';
        case 1:
            return 'working';
        case 0:
            return 'waiting';
        case -1:
            return 'disconnected';
        default:
            return 'unknown';
    }
}
//powercontrol_status
}
