<?php
require_once './../controllers/SolarEdgeController.php';
require_once './../controllers/GoodWeController.php';
require_once './../utils/respuesta.php';

class ApiControladorService {
    private $solarEdgeController;
    private $goodWeController;

    public function __construct() {
        $this->solarEdgeController = new SolarEdgeController();
        $this->goodWeController = new GoodWeController();
    }
    public function getAllPlants() {
        $respuesta = new Respuesta;
        try{
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getAllPlants();
            $goodWeData = json_decode($goodWeResponse, true);
    
            // Obtener datos de SolarEdge
            $solarEdgeResponse = $this->solarEdgeController->getAllPlants();
            $solarEdgeData = json_decode($solarEdgeResponse, true);

            $plants = $this->processPlants($goodWeData, $solarEdgeData);
            
            if($plants != null){
            $respuesta->success($plants);
            }else{
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        }catch(Throwable $e){
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getSiteDetail($id) {
        // Obtener los detalles de SolarEdge y GoodWe
        $plantsSolarEdge = $this->solarEdgeController->getSiteDetails($id);
        $solarEdgeData = json_decode($plantsSolarEdge, true);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Manejar el error de decodificación JSON
            error_log('Error al decodificar JSON de SolarEdge: ' . json_last_error_msg());
            $solarEdgeData = []; // O maneja el error según tu lógica
        }
    
        $goodWeController = $this->goodWeController->getPlantDetails($id);
        $goodWeData = json_decode($goodWeController, true);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Manejar el error de decodificación JSON
            error_log('Error al decodificar JSON de GoodWe: ' . json_last_error_msg());
            $goodWeData = []; // O maneja el error según tu lógica
        }
    
        // Asegúrate de que ambos datos sean arrays antes de pasarlos a unifyPlantData
        if (is_array($goodWeData) && is_array($solarEdgeData)) {
            $data = $this->unifyPlantData($goodWeData, $solarEdgeData);
        } else {
            // Manejar el caso donde los datos no son arrays
            error_log('Los datos de GoodWe o SolarEdge no son arrays.');
            $data = []; // O maneja el error según tu lógica
        }
    
        $response = new Respuesta;
        $response->success($data);
    
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($response);
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
    //Aquí va la lógica de las apis conversiones etc.. (Lista plantas)
    public function processPlants(array $goodWeData, array $solarEdgeData): array {
        $plants = [];

        // Procesar datos de GoodWe
        if (isset($goodWeData['data']['list']) && is_array($goodWeData['data']['list'])) {
            foreach ($goodWeData['data']['list'] as $plant) {
                $status = "";
                // Mapear el código de estado a una descripción legible
                $status = $this->mapGoodWeStatus($plant['status']);
                $plants[] = [
                    'id' => $plant['powerstation_id'] ?? '',
                    'name' => $plant['stationname'] ?? '',
                    'address' => $plant['location'] ?? '',
                    'capacity' => $plant['capacity'] ?? 0,
                    'status' => $status,
                    'type' => $plant['powerstation_type'] ?? '',
                    'latitude' => $plant['latitude'] ?? '',
                    'longitude' => $plant['longitude'] ?? '',
                    'organization' => $plant['org_name'] ?? 'GoodWe',
                    'current_power' => $plant['pac'] ?? 0, // Potencia actual en W
                    'total_energy' => $plant['etotal'] ?? 0, // Energía total generada en kWh
                    'daily_energy' => $plant['eday'] ?? 0, // Energía generada hoy en kWh
                    'monthly_energy' => $plant['emonth'] ?? 0, // Energía generada este mes en kWh
                    'installation_date' => null, // No disponible en GoodWe
                    'pto_date' => null, // No disponible en GoodWe
                    'notes' => null, // No disponible en GoodWe
                    'alert_quantity' => null, // No disponible en GoodWe
                    'highest_impact' => null, // No disponible en GoodWe
                    'primary_module' => null, // No disponible en GoodWe
                    'public_settings' => null // No disponible en GoodWe
                ];
            }
        }

        // Procesar datos de SolarEdge
        if (isset($solarEdgeData['sites']['site']) && is_array($solarEdgeData['sites']['site'])) {
            foreach ($solarEdgeData['sites']['site'] as $site) {
                $addressParts = [
                    $site['location']['address'] ?? '',
                    $site['location']['city'] ?? '',
                    $site['location']['country'] ?? ''
                ];
                $address = implode(', ', array_filter($addressParts));

                $status = "";
                // Mapear el código de estado a una descripción legible
                $status = $this->mapSolarEdgeStatus($site['status']);

                $plants[] = [
                    'id' => $site['id'] ?? '',
                    'name' => $site['name'] ?? '',
                    'address' => $address,
                    'capacity' => $site['peakPower'] ?? 0,
                    'status' => $status,
                    'type' => $site['type'] ?? '',
                    'latitude' => $site['location']['latitude'] ?? '',
                    'longitude' => $site['location']['longitude'] ?? '',
                    'organization' => 'SolarEdge',
                    'current_power' => null, // No disponible en SolarEdge
                    'total_energy' => null, // No disponible en SolarEdge
                    'daily_energy' => null, // No disponible en SolarEdge
                    'monthly_energy' => null, // No disponible en SolarEdge
                    'installation_date' => $site['installationDate'] ?? null,
                    'pto_date' => $site['ptoDate'] ?? null,
                    'notes' => $site['notes'] ?? null,
                    'alert_quantity' => $site['alertQuantity'] ?? null,
                    'highest_impact' => $site['highestImpact'] ?? null,
                    'primary_module' => $site['primaryModule'] ?? null,
                    'public_settings' => $site['publicSettings'] ?? null
                ];
            }
        }

        return $plants;
    }
    //Aquí va la lógica de las apis conversiones etc.. (Datos precisos de la planta)
    function unifyPlantData(array $goodWeData, array $solarEdgeData): array {
        $plants = ["hola"];
        return $plants;
    }
  // Función para mapear el estado de GoodWe a una descripción legible
private function mapGoodWeStatus($statusCode) {
    switch ($statusCode) {
        case 2:
            return 'error';
        case 1:
            return 'disconnected';
        case 0:
            return 'waiting';
        case -1:
            return 'working';
        default:
            return 'unknown';
    }
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