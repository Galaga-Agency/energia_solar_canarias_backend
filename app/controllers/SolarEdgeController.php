<?php
require_once __DIR__ . '/../services/SolarEdgeService.php';
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
    private $precioService;

    public function __construct()
    {
        $this->solarEdgeService = new SolarEdgeService();
        $this->logsController = new LogsController();
        $this->precioService = new PrecioService();
    }

    public function BulkApiFleetEnergy($time, $startDate, $endDate, $arrayEnteros)
    {
        // Registrar log
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API SolarEdge para la carga de la bulk api fleet energy");

        // Llamada a la API para obtener datos de energía
        $data = $this->solarEdgeService->BulkApiFleetEnergy($time, $startDate, $endDate, $arrayEnteros);

        // Verificar si la API devolvió una respuesta válida
        if (!is_array($data) || !isset($data['sitesEnergy']) || !isset($data['sitesEnergy']['siteEnergyList'])) {
            return null;
            exit;
        }

        // Procesar los datos de energía
        $result = $this->processEnergyData($data);

        // Retornar la respuesta en JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }


    public function cargaBateriaSolarEdge($powerStationId, $startTime, $endTime)
    {
        // Registrar log
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API SolarEdge para la carga de batería");
        //Recoger el inventario con una llamada a la api
        $inventario = $this->solarEdgeService->cargaBateriaSolarEdge($powerStationId, $startTime, $endTime);
        // Retornar la respuesta en JSON
        header('Content-Type: application/json');
        return json_encode($inventario);
    }

    public function getPlantComparative($powerStationId, $startDate, $timeUnit)
    {
        // Registrar log
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API SolarEdge para comparación de años de plantas");

        // Convertir la fecha de inicio a un objeto DateTime (asumiendo que $startDate llega en formato 'YYYY-MM-DD')
        $startDateObj = new DateTime($startDate);
        $currentDate = new DateTime('today');

        // Obtener años de inicio y fin
        $startYear = (int)$startDateObj->format('Y');
        $endYear = (int)$currentDate->format('Y');

        $allData = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            // Determinar las fechas "from" y "to" para cada solicitud
            if ($year === $startYear && $year === $endYear) {
                // Caso especial: la planta se implementó este mismo año
                $from = $startDateObj;
                $to = $currentDate;
            } elseif ($year === $startYear) {
                // Primer año desde la fecha de implementación hasta 31 de diciembre de ese año
                $from = $startDateObj;
                $to = new DateTime("$year-12-31");
            } elseif ($year < $endYear) {
                // Años intermedios desde el 1 de enero hasta 31 de diciembre
                $from = new DateTime("$year-01-01");
                $to = new DateTime("$year-12-31");
            } else {
                // Último año desde el 1 de enero hasta la fecha actual
                $from = new DateTime("$year-01-01");
                $to = $currentDate;
            }

            $formattedDateInicio = $from->format('Y-m-d');
            $formattedDateFin = $to->format('Y-m-d');

            // Llamar a la API por el rango determinado
            // Aquí asumiré que el método solarEdgeService->getPlantComparative($powerStationId, $from, $to)
            // devuelve los datos correspondientes a ese rango. Es un método hipotético.
            $yearData = $this->solarEdgeService->getPlantComparative($powerStationId, $formattedDateInicio, $formattedDateFin, $timeUnit);

            // Extraer solo el array de values
            $values = isset($yearData->energy->values) ? $yearData->energy->values : [];

            // Ir concatenándolos al array principal
            $allData = array_merge($allData, $values);
        }

        // Retornar la respuesta en JSON
        header('Content-Type: application/json');
        return json_encode($allData);
    }
    public function inventarioSolarEdge($powerStationId)
    {
        // Registrar log
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API SolarEdge para el inventario de las plantas");
        //Recoger el inventario con una llamada a la api
        $inventario = $this->solarEdgeService->inventarioSolarEdge($powerStationId);
        // Retornar la respuesta en JSON
        header('Content-Type: application/json');
        return json_encode($inventario);
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
                    if (isset($value['value'])) {
                        $totalSolarProduction += $value['value'];
                    }
                }
            }

            // Sumar valores de consumo
            if (!empty($consumptionMeters)) {
                foreach ($consumptionMeters as $value) {
                    if (isset($value['value'])) {
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
                    if (isset($value['value'])) {
                        $totalExport += $value['value'];
                    }
                }
            }

            // Sumar valores de importación
            if (!empty($importValues)) {
                foreach ($importValues as $value) {
                    if (isset($value['value'])) {
                        $totalImport += $value['value'];
                    }
                }
            }

            // Sumar valores de autoconsumo
            if (!empty($selfConsumptionValues)) {
                foreach ($selfConsumptionValues as $value) {
                    if (isset($value['value'])) {
                        $totalSelfConsumption += $value['value'];
                    }
                }
            }
            // Cálculo del porcentaje de Importación
            $denominatorImport = $totalSelfConsumption + $totalImport;
            $porcentajeImport = ($denominatorImport != 0) ? ($totalImport / $denominatorImport) * 100 : 0;

            // Cálculo del porcentaje de Exportación
            $denominatorExport = $totalSelfConsumption + $totalExport;
            $porcentajeExport = ($denominatorExport != 0) ? ($totalExport / $denominatorExport) * 100 : 0;

            // Cálculo del porcentaje de Autoconsumo en Importación
            $denominatorSelfConsumptionImport = $totalSelfConsumption + $totalImport;
            $porcentajeSelfConsumptionImport = ($denominatorSelfConsumptionImport != 0) ? ($totalSelfConsumption / $denominatorSelfConsumptionImport) * 100 : 0;

            // Cálculo del porcentaje de Autoconsumo en Exportación
            $denominatorSelfConsumptionExport = $totalSelfConsumption + $totalExport;
            $porcentajeSelfConsumptionExport = ($denominatorSelfConsumptionExport != 0) ? ($totalSelfConsumption / $denominatorSelfConsumptionExport) * 100 : 0;


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
                'porcentajeSelfConsumptionImport' => $porcentajeSelfConsumptionImport,
                'porcentajeSelfConsumptionExport' => $porcentajeSelfConsumptionExport,
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

    //Método para obtener los precios reales de la planta a partir de los datos de zoho
    public function getPlantRealPrice($siteId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge precio real de planta de solarEdge");
        // Obtener los precios personalizados de la planta desde el historial de precios de zoho
        $rangos = $this->precioService->getPreciosPersonalizadosPorPlanta($siteId, "solaredge");
        //Obtenemos los datos de la planta desde el servicio de SolarEdge y personalizamos los precios
        $data = $this->solarEdgeService->getEstadisticasEnergiaSolarEdge($rangos, $siteId);
        header('Content-Type: application/json');
        return json_encode($data);
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

    /**
     * Procesa los datos de energía y genera el objeto de salida esperado.
     */
    private function processEnergyData($data)
    {
        $chartEnergy = [];
        $totalEnergy = 0;
        $numSites = 0;

        // Se accede directamente a "sitesEnergy"
        $siteEnergyList = $data['sitesEnergy']['siteEnergyList'];

        // Contar sitios con datos válidos
        foreach ($siteEnergyList as $site) {
            if (!empty($site['energyValues']['values'])) {
                $numSites++;
            }
        }

        // Sumar los valores de energía por fecha
        foreach ($siteEnergyList as $site) {
            foreach ($site['energyValues']['values'] as $entry) {
                $date = date("Y-m-d\TH:i:s\Z", strtotime($entry['date'])); // Formato UTC
                $value = isset($entry['value']) ? floatval($entry['value']) : 0; // Null se convierte en 0

                if (!isset($chartEnergy[$date])) {
                    $chartEnergy[$date] = 0;
                }
                $chartEnergy[$date] += $value;
                $totalEnergy += $value;
            }
        }

        // Calcular rendimiento promedio
        $averageYield = $numSites > 0 ? $totalEnergy / $numSites : 0;

        return [
            'energy' => round($totalEnergy / 1000, 3), // Convertir a kWh
            'chartEnergy' => array_map(fn($v) => round($v / 1000, 3), $chartEnergy), // Convertir a kWh cada valor
            'averageYield' => round($averageYield / 1000, 6), // Convertir a kWh
            'prData' => [
                'averagePr' => 0.0,  // No proporcionado en los datos de origen
                'sitesWithPr' => 0,   // No proporcionado en los datos de origen
                'isAllSitesHavePr' => false
            ]
        ];
    }
}
