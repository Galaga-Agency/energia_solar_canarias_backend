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

        //obtener los datos de la batería
        $data = $this->solarEdgeService->getCurrentPowerFlow($siteId);

        $battery = isset($data) ? json_decode(json_encode($data), true) : [];

        $batteryValues = $battery['siteCurrentPowerFlow'] ?? [];

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

        // Añadir un nuevo campo "battery" al resultado
        $decodedResult['details']['siteCurrentPowerFlow'] = $batteryValues;

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

            // Inicializar las sumas
            $totalSolarProduction = 0;
            $totalConsumption = 0;
            $totalBatteryValues = 0;
            $totalExport = 0;
            $totalImport = 0;
            $totalSelfConsumption = 0;

            // Sumar valores de producción solar
            if (!empty($solarProductionValues)) {
                foreach ($solarProductionValues as $value) {
                    if(isset($value['value'])){
                    $totalSolarProduction += $value['value'];
                    }
                }
            }

            // Sumar valores de consumo
            if (!empty($consumptionMeters)) {
                foreach ($consumptionMeters as $value) {
                    if(isset($value['value'])){
                    $totalConsumption += $value['value'];
                    }
                }
            }

            // Sumar valores de batería
            if (!empty($batteryValues)) {
                foreach ($batteryValues as $battery) {
                    if (!empty($battery['telemetries'])) {
                        foreach ($battery['telemetries'] as $telemetry) {
                            $totalBatteryValues += $telemetry['power'];
                        }
                    }
                }
            }

            // Sumar valores de exportación
            if (!empty($exportValues)) {
                foreach ($exportValues as $value) {
                    if(isset($value['value'])){
                    $totalExport += $value['value'];
                    }
                }
            }

            // Sumar valores de importación
            if (!empty($importValues)) {
                foreach ($importValues as $value) {
                    if(isset($value['value'])){
                    $totalImport += $value['value'];
                    }
                }
            }

            // Sumar valores de autoconsumo
            if (!empty($selfConsumptionValues)) {
                foreach ($selfConsumptionValues as $value) {
                    if(isset($value['value'])){
                    $totalSelfConsumption += $value['value'];
                    }
                }
            }

            $porcentajeImport= $totalImport / ($totalSelfConsumption + $totalImport) * 100;
            $porcentajeExport= $totalExport / ($totalSelfConsumption + $totalExport) * 100;
            $porcentajeSelfConsumptionImport = $totalSelfConsumption / ($totalSelfConsumption + $totalImport) * 100;
            $porcentajeSelfConsumptionExport = $totalSelfConsumption / ($totalSelfConsumption + $totalExport) * 100;


            // Construimos la salida separando cada conjunto de datos
            $result = [
                'consumption' => $consumptionMeters,
                'totalConsumption' => $totalConsumption,
                'solarProduction' => $solarProductionValues,
                'totalProduction' => $totalSolarProduction,
                'storagePower' => $batteryValues,
                'storagePowerTotal' => $totalBatteryValues,
                'export' => $exportValues,
                'totalExport' => $totalExport,
                'porcentajeExport' => $porcentajeExport,
                'import' => $importValues,
                'totalImport' => $totalImport,
                'porcentajeImport' => $porcentajeImport,
                'selfConsumption' => $selfConsumptionValues,
                'totalSelfConsumption' => $totalSelfConsumption,
                'porcentajeSelfConsumptionImport' =>$porcentajeSelfConsumptionImport,
                'porcentajeSelfConsumptionExport' =>$porcentajeSelfConsumptionExport,
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

    public function overviewSolarEdge($siteId)
    {
        try {
            $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge overview");
            $data = $this->solarEdgeService->getPlantGastosEnergeticos($siteId);
            header('Content-Type: application/json');
            return json_encode($data);
        } catch (\Throwable $e) {
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
