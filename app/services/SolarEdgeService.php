<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/SolarEdge.php';

class SolarEdgeService
{
    private $solarEdge;
    private $httpClient;

    public function __construct()
    {
        $this->solarEdge = new SolarEdge();
        $this->httpClient = new HttpClient();
    }

    public function BulkApiFleetEnergy($time, $startDate, $endDate, $arrayEnteros)
    {
        $url = $this->solarEdge->getUrl() . "sites/$arrayEnteros/energy?timeUnit=$time&startDate=$startDate&endDate=$endDate&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }


    //Método que devuelve la grafica del consumo de bateria
    public function cargaBateriaSolarEdge($siteId, $startTime, $endTime)
    {
        $startParam = urlencode("$startTime 00:00:00");
        $endParam = urlencode("$endTime 23:59:59");

        $url = $this->solarEdge->getUrl() . "site/$siteId/storageData?startTime={$startParam}&endTime={$endParam}&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }


    //Método que devuelve la potencia de las plantas que esten en funcionamiento
    public function inventarioSolarEdge($siteId)
    {
        $url = $this->solarEdge->getUrl() . "site/$siteId/inventory?api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Método que devuelve la potencia de las plantas que esten en funcionamiento
    public function getPlantComparative($siteId, $from, $to, $timeUnit)
    {
        $url = $this->solarEdge->getUrl() . "site/$siteId/energy?timeUnit=$timeUnit&startDate=$from&endDate=$to&api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Método que devuelve la potencia de las plantas que esten en funcionamiento
    public function getPlantPowerBenefits($siteId)
    {
        $url = $this->solarEdge->getUrl() . "site/$siteId/envBenefits?systemUnits=Metrics&api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Método que devuelve la potencia de las plantas que esten en funcionamiento
    public function getPlantGastosEnergeticos($siteId)
    {
        $url = $this->solarEdge->getUrl() . "site/$siteId/overview?api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }


    //Método que devuelve el estado de la bateria
    public function getCurrentPowerFlow($siteId)
    {
        $url = $this->solarEdge->getUrl() . "site/$siteId/currentPowerFlow?api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Método que devuelve la potencia de las plantas que esten en funcionamiento
    public function getPlantPowerRealtime($siteId)
    {
        $url = $this->solarEdge->getUrl() . "site/$siteId/currentPowerFlow?api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoge la grafica de la planta energy
    public function getPowerDashboard($siteId, $dia, $fechaFin = null, $fechaInicio = null)
    {
        // Formato de fecha
        $formato = 'Y-m-d';

        // Convertir fechas a DateTime si no son nulas
        $fechaSinFormatearFin = $fechaFin ? new DateTime($fechaFin) : new DateTime('today 23:59:59');
        $fechaSinFormatearInicio = $fechaInicio ? new DateTime($fechaInicio) : null;

        // Ajustar fechas según el valor de $dia
        switch ($dia) {
            case "QUARTER_OF_AN_HOUR":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('today');
                break;
            case "DAY":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('yesterday 23:59:59');
                break;

            case "WEEK":
                // Si no hay fecha de inicio, tomar el inicio de la semana actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('monday this week 00:00:00');
                break;

            case "MONTH":
                // Si no hay fecha de inicio, tomar el inicio del mes actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of this month 00:00:00');
                break;

            case "YEAR":
                // Si no hay fecha de inicio, tomar el inicio del año actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of January 00:00:00');
                break;

            default:
                return "Error: día incorrecto";
        }

        // Formatear las fechas
        $fechaInicioFormateada = $fechaSinFormatearInicio->format($formato);
        $fechaFinFormateada = $fechaSinFormatearFin->format($formato);

        // Construir la URL utilizando el formato definido
        $url = $this->solarEdge->getUrl() . "site/$siteId/energy?timeUnit=$dia&startDate=$fechaInicioFormateada&endDate=$fechaFinFormateada&api_key=" . $this->solarEdge->getApiKey();

        // Realizar la solicitud
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoge la grafica de la planta consumption
    public function getPowerConsumption($siteId, $dia, $fechaFin = null, $fechaInicio = null)
    {
        // Formato de fecha
        $formato = 'Y-m-d H:i:s';

        // Convertir fechas a DateTime si no son nulas
        $fechaSinFormatearFin = $fechaFin ? new DateTime($fechaFin) : new DateTime('today 23:59:59');
        $fechaSinFormatearInicio = $fechaInicio ? new DateTime($fechaInicio) : null;

        // Ajustar fechas según el valor de $dia
        switch ($dia) {
            case "QUARTER_OF_AN_HOUR":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('today');
                break;
            case "DAY":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('yesterday 23:59:59');
                break;

            case "WEEK":
                // Si no hay fecha de inicio, tomar el inicio de la semana actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('monday this week 00:00:00');
                break;

            case "MONTH":
                // Si no hay fecha de inicio, tomar el inicio del mes actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of this month 00:00:00');
                break;

            case "YEAR":
                // Si no hay fecha de inicio, tomar el inicio del año actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of January 00:00:00');
                break;

            default:
                return "Error: día incorrecto";
        }

        //pongo la fecha en formato
        $fechaSinFormatearInicio->setTime(0, 0, 0); // Establecer la hora como 00:00:00
        $fechaSinFormatearFin->setTime(23, 59, 59); // Establecer la hora como 23:59:59

        // Formatear las fechas
        $fechaInicioFormateada = $fechaSinFormatearInicio->format($formato);
        $fechaFinFormateada = $fechaSinFormatearFin->format($formato);

        // Construir la URL utilizando el formato definido
        $url = $this->solarEdge->getUrl() . "site/$siteId/energyDetails?meters=CONSUMPTION&timeUnit=$dia&startTime=" . urlencode($fechaInicioFormateada) . "&endTime=" . urlencode($fechaFinFormateada) . "&api_key=" . $this->solarEdge->getApiKey();

        // Realizar la solicitud
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    //Recoge la grafica de la planta export (FeedIn)
    public function getPowerExport($siteId, $dia, $fechaFin = null, $fechaInicio = null)
    {
        // Formato de fecha
        $formato = 'Y-m-d H:i:s';

        // Convertir fechas a DateTime si no son nulas
        $fechaSinFormatearFin = $fechaFin ? new DateTime($fechaFin) : new DateTime('today 23:59:59');
        $fechaSinFormatearInicio = $fechaInicio ? new DateTime($fechaInicio) : null;

        // Ajustar fechas según el valor de $dia
        switch ($dia) {
            case "QUARTER_OF_AN_HOUR":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('today');
                break;
            case "DAY":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('yesterday 23:59:59');
                break;

            case "WEEK":
                // Si no hay fecha de inicio, tomar el inicio de la semana actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('monday this week 00:00:00');
                break;

            case "MONTH":
                // Si no hay fecha de inicio, tomar el inicio del mes actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of this month 00:00:00');
                break;

            case "YEAR":
                // Si no hay fecha de inicio, tomar el inicio del año actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of January 00:00:00');
                break;

            default:
                return "Error: día incorrecto";
        }

        //pongo la fecha en formato
        $fechaSinFormatearInicio->setTime(0, 0, 0); // Establecer la hora como 00:00:00
        $fechaSinFormatearFin->setTime(23, 59, 59); // Establecer la hora como 23:59:59

        // Formatear las fechas
        $fechaInicioFormateada = $fechaSinFormatearInicio->format($formato);
        $fechaFinFormateada = $fechaSinFormatearFin->format($formato);

        // Construir la URL utilizando el formato definido
        $url = $this->solarEdge->getUrl() . "site/$siteId/energyDetails?meters=FEEDIN&timeUnit=$dia&startTime=" . urlencode($fechaInicioFormateada) . "&endTime=" . urlencode($fechaFinFormateada) . "&api_key=" . $this->solarEdge->getApiKey();

        // Realizar la solicitud
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    //Recoge la grafica de la planta import (Purchased)
    public function getPowerImport($siteId, $dia, $fechaFin = null, $fechaInicio = null)
    {
        // Formato de fecha
        $formato = 'Y-m-d H:i:s';

        // Convertir fechas a DateTime si no son nulas
        $fechaSinFormatearFin = $fechaFin ? new DateTime($fechaFin) : new DateTime('today 23:59:59');
        $fechaSinFormatearInicio = $fechaInicio ? new DateTime($fechaInicio) : null;

        // Ajustar fechas según el valor de $dia
        switch ($dia) {
            case "QUARTER_OF_AN_HOUR":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('today');
                break;
            case "DAY":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('yesterday 23:59:59');
                break;

            case "WEEK":
                // Si no hay fecha de inicio, tomar el inicio de la semana actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('monday this week 00:00:00');
                break;

            case "MONTH":
                // Si no hay fecha de inicio, tomar el inicio del mes actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of this month 00:00:00');
                break;

            case "YEAR":
                // Si no hay fecha de inicio, tomar el inicio del año actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of January 00:00:00');
                break;

            default:
                return "Error: día incorrecto";
        }

        //pongo la fecha en formato
        $fechaSinFormatearInicio->setTime(0, 0, 0); // Establecer la hora como 00:00:00
        $fechaSinFormatearFin->setTime(23, 59, 59); // Establecer la hora como 23:59:59

        // Formatear las fechas
        $fechaInicioFormateada = $fechaSinFormatearInicio->format($formato);
        $fechaFinFormateada = $fechaSinFormatearFin->format($formato);

        // Construir la URL utilizando el formato definido
        $url = $this->solarEdge->getUrl() . "site/$siteId/energyDetails?meters=PURCHASED&timeUnit=$dia&startTime=" . urlencode($fechaInicioFormateada) . "&endTime=" . urlencode($fechaFinFormateada) . "&api_key=" . $this->solarEdge->getApiKey();

        // Realizar la solicitud
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    //Recoge la grafica de la planta import (Purchased)
    public function getPowerSelfConsumption($siteId, $dia, $fechaFin = null, $fechaInicio = null)
    {
        // Formato de fecha
        $formato = 'Y-m-d H:i:s';

        // Convertir fechas a DateTime si no son nulas
        $fechaSinFormatearFin = $fechaFin ? new DateTime($fechaFin) : new DateTime('today 23:59:59');
        $fechaSinFormatearInicio = $fechaInicio ? new DateTime($fechaInicio) : null;

        // Ajustar fechas según el valor de $dia
        switch ($dia) {
            case "QUARTER_OF_AN_HOUR":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('today');
                break;
            case "DAY":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('yesterday 23:59:59');
                break;

            case "WEEK":
                // Si no hay fecha de inicio, tomar el inicio de la semana actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('monday this week 00:00:00');
                break;

            case "MONTH":
                // Si no hay fecha de inicio, tomar el inicio del mes actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of this month 00:00:00');
                break;

            case "YEAR":
                // Si no hay fecha de inicio, tomar el inicio del año actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of January 00:00:00');
                break;

            default:
                return "Error: día incorrecto";
        }

        //pongo la fecha en formato
        $fechaSinFormatearInicio->setTime(0, 0, 0); // Establecer la hora como 00:00:00
        $fechaSinFormatearFin->setTime(23, 59, 59); // Establecer la hora como 23:59:59

        // Formatear las fechas
        $fechaInicioFormateada = $fechaSinFormatearInicio->format($formato);
        $fechaFinFormateada = $fechaSinFormatearFin->format($formato);

        // Construir la URL utilizando el formato definido
        $url = $this->solarEdge->getUrl() . "site/$siteId/energyDetails?meters=SELFCONSUMPTION&timeUnit=$dia&startTime=" . urlencode($fechaInicioFormateada) . "&endTime=" . urlencode($fechaFinFormateada) . "&api_key=" . $this->solarEdge->getApiKey();

        // Realizar la solicitud
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    //Recoge la grafica de la planta power battery
    public function getPowerBattery($siteId, $dia, $fechaFin = null, $fechaInicio = null)
    {
        // Formato de fecha
        $formato = 'Y-m-d H:i:s';

        // Convertir fechas a DateTime si no son nulas
        $fechaSinFormatearFin = $fechaFin ? new DateTime($fechaFin) : new DateTime('today 23:59:59');
        $fechaSinFormatearInicio = $fechaInicio ? new DateTime($fechaInicio) : null;

        // Ajustar fechas según el valor de $dia
        switch ($dia) {
            case "QUARTER_OF_AN_HOUR":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('today');
                break;
            case "DAY":
                // Si no hay fecha de inicio, tomar ayer a las 23:59:59
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('yesterday 23:59:59');
                break;

            case "WEEK":
                // Si no hay fecha de inicio, tomar el inicio de la semana actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('monday this week 00:00:00');
                break;

            case "MONTH":
                // Si no hay fecha de inicio, tomar el inicio del mes actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of this month 00:00:00');
                break;

            case "YEAR":
                // Si no hay fecha de inicio, tomar el inicio del año actual
                $fechaSinFormatearInicio = $fechaSinFormatearInicio ?? new DateTime('first day of January 00:00:00');
                break;

            default:
                return "Error: día incorrecto";
        }

        //pongo la fecha en formato
        $fechaSinFormatearInicio->setTime(0, 0, 0); // Establecer la hora como 00:00:00
        $fechaSinFormatearFin->setTime(23, 59, 59); // Establecer la hora como 23:59:59

        // Formatear las fechas
        $fechaInicioFormateada = $fechaSinFormatearInicio->format($formato);
        $fechaFinFormateada = $fechaSinFormatearFin->format($formato);

        // Construir la URL utilizando el formato definido
        $url = $this->solarEdge->getUrl() . "site/$siteId/storageData?startTime=" . urlencode($fechaInicioFormateada) . "&endTime=" . urlencode($fechaFinFormateada) . "&api_key=" . $this->solarEdge->getApiKey();

        // Realizar la solicitud
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    //Recoge la grafica 'Custom' de la planta
    public function getPowerDashboardCustom($chartField, $foldUp, $timeUnit, $siteId, $billingCycle, $period, $periodDuration, $startTime, $endTime)
    {

        $url = $this->solarEdge->getUrl() . "solaredge-apigw/api/site/$siteId/customEnergyDashboardChart?chartField=$chartField&foldUp=$foldUp&timeUnit=$timeUnit&siteId=$siteId&billingCycle=$billingCycle&period=$period&periodDuration=$periodDuration&startTime=$startTime&endTime=$endTime";

        try {
            $response = $this->httpClient->get($url);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoge los detalles de la planta
    public function getSiteDetails($siteId)
    {
        $url = $this->solarEdge->getUrl() . "site/$siteId/details?api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Método que recoje todas las plantas.
     *
     * Dos limites de la API de SolarEdge que hay que respetar aqui:
     *  - `size` no admite mas de 100: con 101+ la peticion falla entera y devuelve
     *    cero plantas (no trunca).
     *  - `startIndex` es un OFFSET, no un numero de pagina, asi que hay que
     *    convertirlo o la pagina 1 se saltaria la primera planta.
     */
    public function getAllPlants($page = 1, $pageSize = 100)
    {
        $pageSize = max(1, min((int) $pageSize, 100));
        $startIndex = max(0, ((int) $page - 1) * $pageSize);
        $url = $this->solarEdge->getUrl() . "sites/list?size=$pageSize&startIndex=$startIndex&api_key=" . $this->solarEdge->getApiKey();
        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    // Método para obtener los datos de sensores con un rango de fechas extendido
    public function getSensorsDataExtended($siteId, $startDate, $endDate)
    {
        $url = $this->solarEdge->getUrl() . "site/$siteId/sensors";
        $url .= "?startDate=" . urlencode($startDate) . "&endDate=" . urlencode($endDate);
        $url .= "&api_key=" . $this->solarEdge->getApiKey();

        try {
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    private function minFecha(array $rangos): string
    {
        return min(array_column($rangos, 'fecha_inicio'));
    }

    /**
     * ===========================================================================================
     * Todas estas funciones son para sacar el cálculo de la energía real del cliente en SolarEdge
     * ===========================================================================================
     */

    //Esta función devuelve el precio y el ahorro de la planta que le corresponde
    public function getEstadisticasEnergiaSolarEdge($rangos, $plantaId)
    {
        //Verificamos que rangos sea valido
        if($rangos == null){
            return ["planta_id" => $plantaId,
                    "moneda" => "EUR",
                    "status" => "No hay historial de precio en esta planta",
                    "total" => array_merge([
                        "fecha_inicio" => "0000-00-00",
                        "fecha_final" => "0000-00-00",
                        "energia_kwh" => 0,
                        "ingreso" => 0,
                        "ahorro" => 0
                    ]),
                        "mes_actual" => array_merge([
                        "fecha_inicio" => "0000-00-00",
                        "fecha_final" => "0000-00-00",
                        "energia_kwh" => 0,
                        "ingreso" => 0,
                        "ahorro" => 0
                        ]),
                    "hoy" => array_merge([
                        "fecha_inicio" => "0000-00-00",
                        "fecha_final" => "0000-00-00",
                        "energia_kwh" => 0,
                        "ingreso" => 0,
                        "ahorro" => 0
                    ])];
                }
        $apiKey = $this->solarEdge->getApiKey();
        $hoy = date('Y-m-d');
        $inicioMes = date('Y-m-01');
        $agrupadoPorPlanta = [];

        // Agrupamos todos los rangos por planta
        foreach ($rangos as $rango) {
            $agrupadoPorPlanta[$plantaId]['moneda'] = $rango['moneda'];
            $agrupadoPorPlanta[$plantaId]['rangos'][] = $rango;
        }

        $resultados = [];

        foreach ($agrupadoPorPlanta as $plantaId => $datosPlanta) {
            $moneda = $datosPlanta['moneda'];
            $rangos = $datosPlanta['rangos'];

            $total = $this->calcularPeriodoPorRangos($plantaId, $rangos, $apiKey, $this->minFecha($rangos), $hoy);
            $mesActual = $this->calcularPeriodoPorRangos($plantaId, $rangos, $apiKey, $inicioMes, $hoy);
            $hoyDatos = $this->calcularPeriodoPorRangos($plantaId, $rangos, $apiKey, $hoy, $hoy);

            $resultados[] = [
                'planta_id' => $plantaId,
                'moneda' => $moneda,
                'total' => array_merge(['fecha_inicio' => $this->minFecha($rangos), 'fecha_final' => $hoy], $total),
                'mes_actual' => array_merge(['fecha_inicio' => $inicioMes, 'fecha_final' => $hoy], $mesActual),
                'hoy' => array_merge(['fecha_inicio' => $hoy, 'fecha_final' => $hoy], $hoyDatos),
            ];
        }

        return $resultados;
    }
    //Hace el cálculo de Rangos de precio real del cliente
    private function calcularPeriodoPorRangos($plantId, array $rangos, $apiKey, $fechaInicio, $fechaFinal)
    {
        $energiaTotal = 0;
        $ingresoTotal = 0;
        $ahorroTotal = 0;

        foreach ($rangos as $rango) {
            // Si no hay solapamiento, saltamos
            $inicio = max($fechaInicio, $rango['fecha_inicio']);
            $fin = min($fechaFinal, $rango['fecha_final'] ?: $fechaFinal);

            if ($inicio > $fin) continue;

            $energiaWh = $this->consultarEnergiaTotal($plantId, $inicio, $fin, $apiKey);
            $energiaKwh = $energiaWh / 1000;
            $energiaTotal += $energiaKwh;
            $ingresoTotal += $energiaKwh * $rango['precio'];
            $ahorroTotal += $energiaKwh * $rango['precio_ahorro'];
        }

        return [
            'energia_kwh' => round($energiaTotal, 2),
            'ingreso' => round($ingresoTotal, 2),
            'ahorro' => round($ahorroTotal, 2)
        ];
    }
    // 👇 Esta función ya está lista para dividir por años si hace falta
    private function consultarEnergiaTotal($plantId, $startDate, $endDate, $apiKey)
    {
        $totalWh = 0;
        $subrangos = $this->dividirPorAnios($startDate, $endDate);

        foreach ($subrangos as $rango) {
            $url = "https://monitoringapi.solaredge.com/site/$plantId/energy"
                . "?timeUnit=DAY"
                . "&startDate={$rango['start']}"
                . "&endDate={$rango['end']}"
                . "&api_key=$apiKey";

            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (isset($data['energy']['values'])) {
                foreach ($data['energy']['values'] as $punto) {
                    $totalWh += $punto['value'] ?? 0;
                }
            }
        }

        return $totalWh;
    }
    //Prepara la aplicación por si hay rangos de mas de 1 año
    private function dividirPorAnios(string $start, string $end): array
    {
        $startDate = new DateTime($start);
        $endDate = new DateTime($end);

        $rangos = [];

        while ($startDate <= $endDate) {
            $endOfYear = (clone $startDate)->modify('+1 year -1 day');
            if ($endOfYear > $endDate) {
                $endOfYear = $endDate;
            }

            $rangos[] = [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endOfYear->format('Y-m-d')
            ];

            $startDate = (clone $endOfYear)->modify('+1 day');
        }

        return $rangos;
    }
    
    /**
     * ===========================================================================================
     * //////////ACABAN LAS FUNCIONES DE CALCULO DE ENERGÍA EN TIEMPO REAL DEL CLIENTE////////////
     * ===========================================================================================
     */
}
