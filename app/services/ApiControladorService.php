<?php
require_once './../controllers/SolarEdgeController.php';
require_once './../controllers/GoodWeController.php';
require_once './../utils/respuesta.php';
require_once './../DBObjects/plantasAsociadasDB.php';

class ApiControladorService {
    private $solarEdgeController;
    private $goodWeController;
    private $logsController;

    public function __construct() {
        $this->logsController = new LogsController();
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
                $this->logsController->registrarLog(Logs::INFO, "Se han encontrado las plantas");
                $respuesta->success($plants);
            }else{
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado plantas");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        }catch(Throwable $e){
            $this->logsController->registrarLog(Logs::ERROR, "Error en el servidor de algun proveedor");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getGraficasSolarEdge() {
        $respuesta = new Respuesta;
        try{

            // Obtener datos de SolarEdge
            $data = $this->getEnergyDashBoardCuerpo();
            if($data != null){
                $solarEdgeResponse = $this->solarEdgeController->getPowerDashboard($data['siteId'],$data['timeUnit'],$data['endTime'],$data['startTime']);
            }else{
                $this->logsController->registrarLog(Logs::INFO, "No se a realizado correctamente la peticion a la api faltan parametros o son de distinto nombre");
                $respuesta->_400();
                $respuesta->message = "No se a realizado correctamente la peticion a la api faltan parametros o son de distinto nombre";
                http_response_code(400);
                echo json_encode($respuesta);
                return;
            }
            $solarEdgeData = json_decode($solarEdgeResponse);
            
            
            if($solarEdgeData != null){
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las gráficas de SolarEdge");
                $respuesta->success($solarEdgeData);
            }else{
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las gráficas de SolarEdge");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "No se han encontrado graficas de SolarEdge";
                http_response_code(400);
            }
        }catch(Throwable $e){
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de SolarEdge: "+$e->getMessage());
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    
    public function getGraficasGoodWe() {
        $respuesta = new Respuesta;
        try{
            $data = $this->getChartByPlantCuerpo();

            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getChartByPlants($data);
            $goodWeData = json_decode($goodWeResponse, true);
            
            if($goodWeData != null){
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en GoodWe");
                $respuesta->success($goodWeData);
            }else{
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en GoodWe");
                $respuesta->_400($goodWeData);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        }catch(Throwable $e){
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de GoodWe");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getAllPlantsGoodWe() {
        $respuesta = new Respuesta;
        try{
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getAllPlants();
            $goodWeData = json_decode($goodWeResponse, true);

            $plants = $this->processPlants($goodWeData, []);
            
            if($plants != null){
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en GoodWe");
                $respuesta->success($plants);
            }else{
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en GoodWe");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        }catch(Throwable $e){
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de GoodWe");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getAllPlantsSolarEdge() {
        $respuesta = new Respuesta;
        try{
            // Obtener datos de SolarEdge
            $solarEdgeResponse = $this->solarEdgeController->getAllPlants();
            $solarEdgeData = json_decode($solarEdgeResponse, true);

            $plants = $this->processPlants([], $solarEdgeData);
            
            if($plants != null){
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en SolarEdge");
                $respuesta->success($plants);
            }else{
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las plantas en SolarEdge");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        }catch(Throwable $e){
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de SolarEdge");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getAllPlantsCliente($idUsuario) {
        $respuesta = new Respuesta;
        try {
            $plantasAsociadasDB = new PlantasAsociadasDB;
            $plantasAsociadas = $plantasAsociadasDB->getPlantasAsociadasAlUsuario($idUsuario);
    
            if ($plantasAsociadas == false) {
                $this->logsController->registrarLog(Logs::INFO, "No se encuentran plantas del cliente");
                $respuesta->_404();
                $respuesta->message = 'No se han encontrado plantas para este usuario';
                http_response_code(404);
                echo json_encode($respuesta);
                return;
            }
    
            $goodWeArray = [];
            $solarEdgeArray = [];
    
            foreach ($plantasAsociadas as $planta) {
                if ($planta['nombre_proveedor'] === 'GoodWe') {
                    // Obtener y decodificar datos de GoodWe
                    $goodWeResponse = $this->goodWeController->getPlantDetails($planta['planta_id']);
                    $goodWeData = $this->decodeJsonResponse($goodWeResponse);
                    
                    if (is_array($goodWeData) && isset($goodWeData['data']['info']['powerstation_id'])) {
                        // Usar el ID como clave para evitar duplicados
                        $goodWeArray[$goodWeData['data']['info']['powerstation_id']] = $goodWeData;
                    }
                    
                } elseif ($planta['nombre_proveedor'] === 'SolarEdge') {
                    // Obtener y decodificar datos de SolarEdge
                    $solarEdgeResponse = $this->solarEdgeController->getSiteDetails($planta['planta_id']);
                    $solarEdgeData = $this->decodeJsonResponse($solarEdgeResponse);
                    
                    if (is_array($solarEdgeData) && isset($solarEdgeData['details']['id'])) {
                        // Usar el ID como clave para evitar duplicados
                        $solarEdgeArray[$solarEdgeData['details']['id']] = $solarEdgeData;
                    }
                }
            }
    
            // Convertir los arrays asociativos en arrays simples para procesarlos
            $goodWeArray = array_values($goodWeArray);
            $solarEdgeArray = array_values($solarEdgeArray);
    
            $processedPlants = $this->processPlantsCliente($goodWeArray, $solarEdgeArray);
            $respuesta->success($processedPlants);
            $this->logsController->registrarLog(Logs::INFO, "El usuario accede a sus plantas");
    
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error cogiendo las plantas del usuario");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algún proveedor";
            http_response_code(500);
        }
    
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    
    
    /**
     * Función privada para decodificar respuestas JSON con posible doble codificación
     */
    private function decodeJsonResponse($response) {
        $decodedData = json_decode($response, true);
    
        if (is_string($decodedData)) {
            $decodedData = json_decode($decodedData, true);
        }
    
        return $decodedData;
    }
    
    
    public function getSiteDetail($id, $proveedor) {
        $respuesta = new Respuesta;
        try {
            global $proveedores; // Acceder al array global dentro de la función
    
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getPlantDetails($id);
            $goodWeData = json_decode($goodWeResponse, true);
    
            // Obtener datos de SolarEdge
            $solarEdgeResponse = $this->solarEdgeController->getSiteDetails($id);
            $solarEdgeData = json_decode($solarEdgeResponse, true);
    
            // Validar proveedor y asignar datos correspondientes
            if ($proveedor === $proveedores['GoodWe']) {
                $plants = $goodWeData;
            } elseif ($proveedor === $proveedores['SolarEdge']) {
                $plants = $solarEdgeData;
            } else {
                // Proveedor inválido
                $this->logsController->registrarLog(Logs::ERROR, "Proveedor no válido: $proveedor");
                $respuesta->_400();
                $respuesta->message = "Proveedor no válido.";
                http_response_code(400);
                echo json_encode($respuesta);
                return;
            }
    
            // Validar que los datos no sean null o vacíos
            if (!empty($plants) && $plants !== null) {
                $respuesta->success(json_decode($plants));
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas para el proveedor $proveedor con ID $id");
                $respuesta->_400();
                $respuesta->message = "No se han encontrado plantas.";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error en el servidor de la API: " . $e->getMessage());
            $respuesta->_500();
            $respuesta->message = "Error interno del servidor: " . $e->getMessage();
            http_response_code(500);
        }
    
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    
    public function getSiteDetailCliente($usuarioId, $idPlanta, $proveedor) {
        $respuesta = new Respuesta;
        try{
            global $proveedores; // Acceder al array global dentro de la función
             // Verificar si el proveedor está en el array global de proveedores
            $plantasAsociadas = new PlantasAsociadasDB;
            if($plantasAsociadas->isPlantasAsociadasAlUsuario($usuarioId, $idPlanta, $proveedor)){
                if($proveedor == $proveedores['GoodWe']){
                // Obtener datos de GoodWe
                $goodWeResponse = $this->goodWeController->getPlantDetails($idPlanta);
                $goodWeData = json_decode($goodWeResponse, true);
                }else{
                    $goodWeData = "";
                }
                
                if($proveedor == $proveedores['SolarEdge']){
                // Obtener datos de SolarEdge
                $solarEdgeResponse = $this->solarEdgeController->getSiteDetails($idPlanta);
                $solarEdgeData = json_decode($solarEdgeResponse, true);
                }else{
                    $solarEdgeData = "";
                }

                if($proveedor == $proveedores['GoodWe']){
                    $plants = $goodWeData;
                }else if($proveedor == $proveedores['SolarEdge']){
                    $plants = $solarEdgeData;
                }

            
                if($plants != null){
                    $this->logsController->registrarLog(Logs::INFO, "Se han solicitado las plantas del cliente");
                    $respuesta->success($plants);
                }else{
                    $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas");
                    $respuesta->_400($plants);
                    $respuesta->message = "No se han encontrado plantas";
                    http_response_code(400);
                }
            }else{
                $this->logsController->registrarLog(Logs::INFO, "El id del usuario y id de la planta no coincide o no esta disponible para ese usuario");
                $respuesta->_404();
                $respuesta->message = "El id del usuario y id de la planta no coincide o no esta disponible para ese usuario";
                http_response_code(404);
            }
        }catch(Throwable $e){
            $this->logsController->registrarLog(Logs::ERROR, "error en el servidor de la API");
            $respuesta->_500();
            $respuesta->message = $e->getMessage();;
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta, true);
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
    //Aquí va la lógica de las apis conversiones etc.. (Lista plantas Admin)
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
    //Aquí va la lógica de las apis conversiones etc.. (Lista plantas Cliente)
    public function processPlantsCliente(array $goodWeData, array $solarEdgeData): array {
        $plants = [];
    
        // Procesar datos de GoodWe
        foreach ($goodWeData as $goodWePlant) {
            $status = $this->mapGoodWeStatus($goodWePlant['data']['info']['status'] ?? '');
            $plant = [
                'id' => $goodWePlant['data']['info']['powerstation_id'] ?? '',
                'name' => $goodWePlant['data']['info']['stationname'] ?? '',
                'address' => $goodWePlant['data']['info']['address'] ?? '',
                'capacity' => $goodWePlant['data']['info']['capacity'] ?? 0,
                'status' => $status,
                'type' => $goodWePlant['data']['info']['powerstation_type'] ?? '',
                'latitude' => $goodWePlant['latitude'] ?? '',
                'longitude' => $goodWePlant['longitude'] ?? '',
                'organization' => $goodWePlant['data']['info']['org_name'] ?? 'GoodWe',
                'current_power' => $goodWePlant['data']['kpi']['pac'] ?? 0, // Potencia actual en W
                'total_energy' => $goodWePlant['data']['kpi']['total_power'] ?? 0, // Energía total generada en kWh
                'daily_energy' => $goodWePlant['data']['kpi']['power'] ?? 0, // Energía generada hoy en kWh
                'monthly_energy' => $goodWePlant['data']['kpi']['month_generation'] ?? 0, // Energía generada este mes en kWh
                'installation_date' => null, // No disponible en GoodWe
                'pto_date' => null, // No disponible en GoodWe
                'notes' => null, // No disponible en GoodWe
                'alert_quantity' => null, // No disponible en GoodWe
                'highest_impact' => null, // No disponible en GoodWe
                'primary_module' => null, // No disponible en GoodWe
                'public_settings' => null // No disponible en GoodWe
            ];
    
            $plants[] = $plant; // Agregar el planta de GoodWe al array $plants
        }
    
        // Procesar datos de SolarEdge
        foreach ($solarEdgeData as $solarEdgePlant) {
            $addressParts = [
                $solarEdgePlant['details']['location']['address'] ?? '',
                $solarEdgePlant['details']['location']['city'] ?? '',
                $solarEdgePlant['details']['location']['country'] ?? ''
            ];
            $address = implode(', ', array_filter($addressParts));
    
            $status = $this->mapSolarEdgeStatus($solarEdgePlant['details']['status'] ?? '');
    
            $plant = [
                'id' => $solarEdgePlant['details']['id'] ?? '',
                'name' => $solarEdgePlant['details']['name'] ?? '',
                'address' => $address,
                'capacity' => $solarEdgePlant['details']['peakPower'] ?? 0,
                'status' => $status,
                'type' => $solarEdgePlant['details']['type'] ?? '',
                'latitude' => $solarEdgePlant['details']['location']['latitude'] ?? '',
                'longitude' => $solarEdgePlant['details']['location']['longitude'] ?? '',
                'organization' => 'SolarEdge',
                'current_power' => null, // No disponible en SolarEdge
                'total_energy' => null, // No disponible en SolarEdge
                'daily_energy' => null, // No disponible en SolarEdge
                'monthly_energy' => null, // No disponible en SolarEdge
                'installation_date' => $solarEdgePlant['details']['installationDate'] ?? null,
                'pto_date' => $solarEdgePlant['details']['ptoDate'] ?? null,
                'notes' => $solarEdgePlant['details']['notes'] ?? null,
                'alert_quantity' => $solarEdgePlant['details']['alertQuantity'] ?? null,
                'highest_impact' => $solarEdgePlant['details']['highestImpact'] ?? null,
                'primary_module' => $solarEdgePlant['details']['primaryModule'] ?? null,
                'public_settings' => $solarEdgePlant['details']['publicSettings'] ?? null
            ];
    
            $plants[] = $plant; // Agregar la planta de SolarEdge al array $plants
        }
    
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
//acceso graficas de GoodWe 
public function getChartByPlantCuerpo(){
    // Obtén los datos JSON del cuerpo de la solicitud POST
    $json = file_get_contents('php://input');

    // Decodifica el JSON en un array o un objeto PHP
    $data = json_decode($json, true); // El segundo parámetro true convierte el JSON a un array asociativo

    // Verifica si los datos fueron decodificados correctamente
    if ($data === null) {
        return null;
    } 

    // Verifica si las claves existen en el array
    $id = isset($data['id']) ? $data['id'] : null;
    $date = isset($data['date']) ? $data['date'] : null;
    $range = isset($data['range']) ? $data['range'] : null;
    $chartIndexId = isset($data['chartIndexId']) ? $data['chartIndexId'] : null;

    // Si alguna de las claves no existe, retorna null
    if ($id === null || $date === null || $range === null || $chartIndexId === null) {
        return null;
    }

    switch ($chartIndexId) {
        case "generacion de energia y ingresos":
            switch ($range) {
                case "dia":
                    // Código para el rango "dia"
                    $chartIndexId = "3";
                    $range = 2;
                    break;
                case "mes":
                    // Código para el rango "mes"
                    $chartIndexId = "3";
                    $range = "3";
                    break;
                case "año":
                    // Código para el rango "año"
                    $chartIndexId = "3";
                    $range = "4";
                    break;
                default:
                    // Código para el caso por defecto
                    $chartIndexId = "3";
                    $range = 2;
                    break;
            }
            break;
    
        case "proporcion para uso personal":
            switch ($range) {
                case "dia":
                    // Código para el rango "dia"
                    $chartIndexId = "5";
                    $range = 2;
                    break;
                case "mes":
                    // Código para el rango "mes"
                    $chartIndexId = "5";
                    $range = "3";
                    break;
                case "año":
                    // Código para el rango "año"
                    $chartIndexId = "5";
                    $range = "4";
                    break;
                default:
                    // Código para el caso por defecto
                    $chartIndexId = "5";
                    $range = 2;
                    break;
            }
            break;
    
        case "indice de contribucion":
            switch ($range) {
                case "dia":
                    // Código para el rango "dia"
                    $range = 2;
                    $chartIndexId = "8";
                    break;
                case "mes":
                    // Código para el rango "mes"
                    $range = "3";
                    $chartIndexId = "8";
                    break;
                case "año":
                    // Código para el rango "año"
                    $range = "4";
                    $chartIndexId = "8";
                    break;
                default:
                    // Código para el caso por defecto
                    $chartIndexId = "8";
                    $range = 2;
                    break;
            }
            break;

        case "estadisticas sobre energia":
            switch ($range) {
                case "dia":
                    // Código para el rango "dia"
                    $range = 2;
                    $chartIndexId = "7";
                    break;
                case "mes":
                    // Código para el rango "mes"
                    $range = "3";
                    $chartIndexId = "7";
                    break;
                case "año":
                    // Código para el rango "año"
                    $range = "4";
                    $chartIndexId = "7";
                    break;
                default:
                    // Código para el caso por defecto
                    $chartIndexId = "7";
                    $range = 2;
                    break;
                }
                break;
    
        default:
            // Código para el caso por defecto si $chartIndexId no coincide con ninguno de los anteriores
            $chartIndexId = "3";
            $range = 2;
            break;
    }

    // Si todo está presente, puedes proceder con el uso de las variables
    return [
        'id' => $id,
        'date' => $date,
        'range' => $range,
        'chartIndexId' => $chartIndexId,
        'isDetailFull' => "",
    ];
}
//acceso graficas custom de SolarEdge
public function getEnergyDashBoardCuerpo(){
    // Obtén los datos JSON del cuerpo de la solicitud POST
    $json = file_get_contents('php://input');

    // Decodifica el JSON en un array o un objeto PHP
    $data = json_decode($json, true); // El segundo parámetro true convierte el JSON a un array asociativo

    // Verifica si los datos fueron decodificados correctamente
    if ($data === null) {
        return null;
    }

    // Verifica si las claves existen en el array
    $timeUnit = isset($data['dia']) ? $data['dia'] : null;
    $fieldId = isset($data['id']) ? $data['id'] : null;
    $startTime = isset($data['fechaInicio']) ? $data['fechaInicio'] : null;
    $endTime = isset($data['fechaFin']) ? $data['fechaFin'] : null;
    // Si alguna de las claves no existe, retorna null
    if ($fieldId === null ||$timeUnit === null) {
        return null;
    }
    // Si todo está presente, puedes proceder con el uso de las variables
    return [
        'timeUnit' => $timeUnit,
        'siteId' => $fieldId,
        'endTime' => isset($endTime) ? $endTime : null,
        'startTime' => isset($startTime) ? $startTime : null
    ];
}
}