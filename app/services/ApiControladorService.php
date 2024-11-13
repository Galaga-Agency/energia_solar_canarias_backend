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