<?php
require_once './../controllers/SolarEdgeController.php';
require_once './../controllers/GoodWeController.php';
require_once './../utils/respuesta.php';
require_once './../DBObjects/plantasAsociadasDB.php';

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
    public function getAllPlantsGoodWe() {
        $respuesta = new Respuesta;
        try{
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getAllPlants();
            $goodWeData = json_decode($goodWeResponse, true);

            $plants = $this->processPlants($goodWeData, []);
            
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
    public function getAllPlantsSolarEdge() {
        $respuesta = new Respuesta;
        try{
            // Obtener datos de SolarEdge
            $solarEdgeResponse = $this->solarEdgeController->getAllPlants();
            $solarEdgeData = json_decode($solarEdgeResponse, true);

            $plants = $this->processPlants([], $solarEdgeData);
            
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
    public function getAllPlantsCliente($idUsuario) {
        $respuesta = new Respuesta;
        try {
            $plantasAsociadasDB = new PlantasAsociadasDB;
            $plantasAsociadas = $plantasAsociadasDB->getPlantasAsociadasAlUsuario($idUsuario);
    
            if ($plantasAsociadas == false) {
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
    
        } catch (Throwable $e) {
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
    
    
    public function getSiteDetail($id) {
        $respuesta = new Respuesta;
        try{
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getPlantDetails($id);
            $goodWeData = json_decode($goodWeResponse, true);
    
            // Obtener datos de SolarEdge
            $solarEdgeResponse = $this->solarEdgeController->getSiteDetails($id);
            $solarEdgeData = json_decode($solarEdgeResponse, true);

            $plants = $this->unifyPlantData($goodWeData,$solarEdgeData);
            
            if($plants != null){
            $respuesta->success($plants);
            }else{
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        }catch(Throwable $e){
            $respuesta->_500();
            $respuesta->message = $e->getMessage();;
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta, true);
    }
    public function getSiteDetailCliente($usuarioId, $idPlanta, $proveedor) {
        $respuesta = new Respuesta;
        try{
            global $proveedores; // Acceder al array global dentro de la función
             // Verificar si el proveedor está en el array global de proveedores
            $plantasAsociadas = new PlantasAsociadasDB;
            if($plantasAsociadas->isPlantasAsociadasAlUsuario($usuarioId, $idPlanta, $proveedor)){
                if($proveedor == "GoodWe"){
                // Obtener datos de GoodWe
                $goodWeResponse = $this->goodWeController->getPlantDetails($idPlanta);
                $goodWeData = json_decode($goodWeResponse, true);
                }else{
                    $goodWeData = "";
                }
                
                if($proveedor == "SolarEdge"){
                // Obtener datos de SolarEdge
                $solarEdgeResponse = $this->solarEdgeController->getSiteDetails($idPlanta);
                $solarEdgeData = json_decode($solarEdgeResponse, true);
                }else{
                    $solarEdgeData = "";
                }

                $plants = $this->unifyPlantData($goodWeData,$solarEdgeData);
            
                if($plants != null){
                    $respuesta->success($plants);
                }else{
                    $respuesta->_400($plants);
                    $respuesta->message = "No se han encontrado plantas";
                    http_response_code(400);
                }
            }else{
                $respuesta->_404();
                $respuesta->message = "El id del usuario y id de la planta no coincide o no esta disponible para ese usuario";
                http_response_code(404);
            }
        }catch(Throwable $e){
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
    
    

    //Aquí va la lógica de las apis conversiones etc.. (Datos precisos de la planta)
    function unifyPlantData($goodWeData, $solarEdgeData): array {
        // Decodifica los datos JSON como arrays
        $solarEdgeData = json_decode($solarEdgeData, true);
        $goodWeData = json_decode($goodWeData, true);
    
        // Comprobamos que la llamada realizada es a solarEdge
        if (isset($solarEdgeData['details'])) {
        $data = $solarEdgeData['details'];
        $addressParts = [
            $data['location']['address'] ?? '',
            $data['location']['city'] ?? '',
            $data['location']['country'] ?? ''
        ];
        $address = implode(', ', array_filter($addressParts));

        $status = $this->mapSolarEdgeStatus($data['status']);
    
        // Construcción del array planta
        $planta = [
            'organization' => 'SolarEdge',
            'id' => $data['id'] ?? '',
            'name' => $data['name'] ?? '',
            'accountId' => $data['accountId'] ?? '',
            'status' => $status ?? '',
            'peakPower' => $data['peakPower'] ?? '',
            'lastUpdateTime' => $data['lastUpdateTime'] ?? '',
            'installationDate' => $data['installationDate'] ?? '',
            'ptoDate' => $data['ptoDate'] ?? '',
            'notes' => $data['notes'] ?? '',
            'type' => $data['type'] ?? '',
            'location' => $address,
            'batteryCapacity' => null,//SolarEdge no tiene este dato
            'orgCode' => null,//SolarEdge no tiene este dato
            "kpi" => [
                'monthGeneration' => null,//SolarEdge no tiene este dato
                'pac' => null,//SolarEdge no tiene este dato
                'power' => null,//SolarEdge no tiene este dato
                'totalPower' => null,//SolarEdge no tiene este dato
                'dayIncome' => null,//SolarEdge no tiene este dato
                'yieldRate' => null,//SolarEdge no tiene este dato
                'currency' => null,//SolarEdge no tiene este dato
            ],
            'alertQuantity' => $data['alertQuantity'] ?? '',
            'highestImpact' => $data['highestImpact'] ?? '',
            "primaryModule" => [
                "manufacturerName" => $data['primaryModule']['manufacturerName'] ?? '',
                "modelName" => $data['primaryModule']['modelName'] ?? '',
                "maximumPower" => $data['primaryModule']['maximumPower'] ?? '',
                "temperatureCoef" => $data['primaryModule']['temperatureCoef'] ?? ''
            ],
            "isEvcharge" => null,//SolarEdge no tiene este dato
            "isTigo" => null,//SolarEdge no tiene este dato
            "isPowerflow" => null,//SolarEdge no tiene este dato
            "isSec" => null,//SolarEdge no tiene este dato
            "isGenset" => null,//SolarEdge no tiene este dato
            "isMicroInverter" => null,//SolarEdge no tiene este dato
            "hasLayout" => null,//SolarEdge no tiene este dato
            "layout_id" => null,//SolarEdge no tiene este dato
            "isMeter" => null,//SolarEdge no tiene este dato
            "isEnvironmental" => null,//SolarEdge no tiene este dato
            "powercontrol_status" => null,//SolarEdge no tiene este dato
            "chartsTypesByPlant" => null //SolarEdge no tiene este dato
        ];
        // Estructura final
        $array = [
            $planta
        ];
        return $array;
    //Comprobamos que exista un mensaje ezxitoso en la api de GoodWe
    }else if (isset($goodWeData['msg']) && $goodWeData['msg'] == 'success') {
        // Datos de GoodWe
        $info = $goodWeData['data']['info'];
        $kpi = $goodWeData['data']['kpi'];
        $chartsTypesByPlant = $goodWeData['data']['chartsTypesByPlant'];
        $data = $goodWeData['data'];

        $status = $this->mapGoodWeStatus($info['status']);
    
        // Construcción del array planta
        $planta = [
            'organization' => 'GoodWe',
            'id' => $info['powerstation_id'] ?? '',
            'name' => $info['stationname'] ?? '',
            'accountId' => null, // La API de GoodWe no tiene
            'status' => $status ?? '',
            'peakPower' => $info['capacity'] ?? '',
            'lastUpdateTime' => $info['local_date'] ?? '',
            'installationDate' => $info['create_time'] ?? '',
            'ptoDate' => null, // La API de GoodWe no tiene
            'notes' => null, // La API de GoodWe no tiene
            'type' => $info['powerstation_type'] ?? '',
            'location' => $info['address'] ?? '',
            'batteryCapacity' => $info['battery_capacity'] ?? '',
            'orgCode' => $info['org_code'] ?? '',
            "kpi" => [
                'monthGeneration' => $kpi['month_generation'] ?? '',
                'pac' => $kpi['pac'] ?? '',
                'power' => $kpi['power'] ?? '',
                'totalPower' => $kpi['total_power'] ?? '',
                'dayIncome' => $kpi['day_income'] ?? '',
                'yieldRate' => $kpi['yield_rate'] ?? '',
                'currency' => $kpi['currency'] ?? '',
            ],
            'alertQuantity' => null, // La API de GoodWe no tiene
            'highestImpact' => null, // La API de GoodWe no tiene
            "primaryModule" => [
                "manufacturerName" => null, // La API de GoodWe no tiene
                "modelName" => null, // La API de GoodWe no tiene
                "maximumPower" => null, // La API de GoodWe no tiene
                "temperatureCoef" => null // La API de GoodWe no tiene
            ],
            "isEvcharge" => $data['isEvcharge'] ?? false,
            "isTigo" => $data['isTigo'] ?? false,
            "isPowerflow" => $data['isPowerflow'] ?? false,
            "isSec" => $data['isSec'] ?? false,
            "isGenset" => $data['isGenset'] ?? false,
            "isMicroInverter" => $data['isMicroInverter'] ?? false,
            "hasLayout" => $data['hasLayout'] ?? false,
            "layout_id" => $data['layout_id'] ?? '',
            "isMeter" => $data['isMeter'] ?? false,
            "isEnvironmental" => $data['isEnvironmental'] ?? false,
            "powercontrol_status" => $data['powercontrol_status'] ?? 0,
        ];
    
        // Construir la sección "chartsTypesByPlant" usando foreach para cada nivel de datos
        $chartsArray = [];
        foreach ($chartsTypesByPlant as $chart) {
            $chartData = [
                "date" => $chart['date'] ?? '',
                "typeName" => $chart['typeName'] ?? '',
                "chartIndices" => []
            ];
    
            foreach ($chart['chartIndices'] as $index) {
                $indexData = [
                    "indexName" => $index['indexName'] ?? '',
                    "indexLabel" => $index['indexLabel'] ?? '',
                    "chartIndexId" => $index['chartIndexId'] ?? '',
                    "dateRange" => []
                ];
    
                foreach ($index['dateRange'] as $range) {
                    $indexData['dateRange'][] = [
                        "text" => $range['text'] ?? '',
                        "value" => $range['value'] ?? '',
                        "type" => $range['type'] ?? '',
                        "now" => $range['now'] ?? '',
                        "dateFormater" => $range['dateFormater'] ?? null
                    ];
                }
    
                $chartData['chartIndices'][] = $indexData;
            }
    
            $chartsArray[] = $chartData;
        }
    
        $planta['chartsTypesByPlant'] = $chartsArray;
    
        // Estructura final
        $array = [
            $planta
        ];
    
        return $array;
    }else{
        // Estructura final
        $array = [];
    }
        return $array;
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