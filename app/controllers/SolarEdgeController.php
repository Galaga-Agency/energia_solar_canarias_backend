<?php
require_once '../services/SolarEdgeService.php';
/**
 * @param $siteId = el id de la planta
 * @param $startDate = la fecha de inicio
 * @param $endDate = la fecha de fin
 * @return json_encode con los datos que saca desde el servicio
 */
class SolarEdgeController
{
    private $solarEdgeService;
    private $logsController;

    public function __construct()
    {
        $this->solarEdgeService = new SolarEdgeService();
        $this->logsController = new LogsController();
    }

    public function getPlantPowerRealtime($powerStationId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge power en tiempo real");
        $data = $this->solarEdgeService->getPlantPowerRealtime($powerStationId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    public function getPlantPowerBenefits($powerStationId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge power en tiempo real");
        $data = $this->solarEdgeService->getPlantPowerBenefits($powerStationId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    // Método para obtener los detalles de una planta con id $siteId
    public function getSiteDetails($siteId)
    {
        // Registrar en logs el acceso a la API
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de SolarEdge para obtener los detalles de una planta");

        // Obtener los datos de la planta desde el servicio de SolarEdge
        $result = $this->solarEdgeService->getSiteDetails($siteId);

        // Decodificar el JSON recibido en un array asociativo
        $decodedResult = json_decode($result, true);

        // Mapear el estado de "status" si existe en "details"
        if (isset($decodedResult['details']['status'])) {
            $decodedResult['details']['status'] = $this->mapSolarEdgeStatus($decodedResult['details']['status']);
        }

        // Añadir un nuevo campo "organizacion" al resultado
        $decodedResult['details']['organization'] = "solaredge";

        // Configurar el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        $decodedResult = json_encode($decodedResult);

        // Retornar el JSON modificado
        return json_encode($decodedResult);
    }

    //Método para obtener la grafica
    public function getPowerDashboardCustom($chartField, $foldUp, $timeUnit, $siteId, $billingCycle, $period, $periodDuration, $startTime, $endTime)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge graficas personalizadas");
        $data = $this->solarEdgeService->getPowerDashboardCustom($chartField, $foldUp, $timeUnit, $siteId, $billingCycle, $period, $periodDuration, $startTime, $endTime);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    //Método para obtener la grafica
    public function getPowerDashboard($siteId, $dia, $fechaFin, $fechaInicio)
{
    try {
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de SolarEdge para gráficas");

        // Obtenemos los datos de las dos fuentes
        $data1 = $this->solarEdgeService->getPowerDashboard($siteId, $dia, $fechaFin, $fechaInicio);
        $data2 = $this->solarEdgeService->getPowerConsumption($siteId, $dia, $fechaFin, $fechaInicio);
        $data3 = $this->solarEdgeService->getPowerBattery($siteId, $dia, $fechaFin, $fechaInicio);
        $data4 = $this->solarEdgeService->getPowerExport($siteId, $dia, $fechaFin, $fechaInicio);
        $data5 = $this->solarEdgeService->getPowerImport($siteId, $dia, $fechaFin, $fechaInicio);
        $data6 = $this->solarEdgeService->getPowerSelfConsumption($siteId, $dia, $fechaFin, $fechaInicio);
        $data7 = $this->solarEdgeService->getPlantGastosEnergeticos($siteId);

        // Convertimos los objetos stdClass a arrays
        $solarProduction = isset($data1) ? json_decode(json_encode($data1), true) : [];
        $consumption = isset($data2) ? json_decode(json_encode($data2), true) : [];
        $battery = isset($data3) ? json_decode(json_encode($data3), true) : [];
        $export = isset($data4) ? json_decode(json_encode($data4), true) : [];
        $import = isset($data5) ? json_decode(json_encode($data5), true) : [];
        $selfConsumption = isset($data6) ? json_decode(json_encode($data6), true) : [];
        $overview = isset($data7) ? json_decode(json_encode($data7), true) : [];

        // Validamos las claves específicas
        $solarProductionValues = $solarProduction['energy']['values'] ?? [];
        $consumptionMeters = $consumption['energyDetails']['meters'][0]['values'] ?? [];
        $batteryValues = $battery['storageData']['batteries'] ?? [];
        $exportValues = $export['energyDetails']['meters'][0]['values'] ?? [];
        $importValues = $import['energyDetails']['meters'][0]['values'] ?? [];
        $selfConsumptionValues = $selfConsumption['energyDetails']['meters'][0]['values'] ?? [];
        $overviewValues = $overview['overview'] ?? [];

        // Construimos la salida separando cada conjunto de datos
        $result = [
            'consumption' => $consumptionMeters,
            'solarProduction' => $solarProductionValues,
            'storagePower' => $batteryValues,
            'export' => $exportValues,
            'import' => $importValues,
            'selfConsumption' => $selfConsumptionValues,
            'overview' => $overviewValues
        ];

        // Enviamos los datos como JSON
        header('Content-Type: application/json');
        return json_encode($result, JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        $this->logsController->registrarLog(Logs::ERROR, "Error al obtener datos de SolarEdge: " . $e->getMessage());
        return json_encode(['error' => 'No se pudieron obtener los datos'], JSON_PRETTY_PRINT);
    }
}



    //Método para obtener los datos de todas las plantas
    public function getAllPlants($page = 1, $pageSize = 200)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge todas las plantas");
        $data = $this->solarEdgeService->getAllPlants($page, $pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }
    // Función para mapear el estado de SolarEdge a una descripción legible
    private function mapSolarEdgeStatus($status)
    {
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
