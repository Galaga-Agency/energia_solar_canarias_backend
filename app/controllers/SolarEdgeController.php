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
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de SolarEdge para gráficas");

        // Obtenemos los datos de las dos fuentes
        $data1 = $this->solarEdgeService->getPowerDashboard($siteId, $dia, $fechaFin, $fechaInicio);
        $data2 = $this->solarEdgeService->getPowerConsumption($siteId, $dia, $fechaFin, $fechaInicio);

        // Convertimos los objetos stdClass a arrays
        $data1 = json_decode(json_encode($data1), true);
        $data2 = json_decode(json_encode($data2), true);

        // Validamos las claves en ambos datasets
        $values1 = isset($data1['energy']['values']) ? $data1['energy']['values'] : [];
        $meters = isset($data2['energyDetails']['meters']) ? $data2['energyDetails']['meters'] : [];

        // Extraemos los valores de consumo del segundo dataset
        $values2 = [];
        foreach ($meters as $meter) {
            if ($meter['type'] === 'Consumption') {
                $values2 = $meter['values'];
                break;
            }
        }

        // Fusionamos los datos por fecha
        $mergedData = [];

        foreach ($values1 as $value1) {
            // Fecha del primer dataset
            $date = $value1['date'];

            // Buscamos valores correspondientes en el segundo dataset
            $consumption = array_filter($values2, function ($item) use ($date) {
                return $item['date'] === $date;
            });

            // Tomamos el primer valor encontrado o asignamos null
            $consumption = reset($consumption);

            // Fusionamos los datos
            $mergedData[] = [
                'date' => $date,
                'generated' => $value1['value'] ?? 0,
                'consumed' => $consumption['value'] ?? 0,
                'net' => ($value1['value'] ?? 0) - ($consumption['value'] ?? 0),
            ];
        }

        // Enviamos los datos fusionados como JSON
        header('Content-Type: application/json');
        return json_encode(['energy' => $mergedData], JSON_PRETTY_PRINT);
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
