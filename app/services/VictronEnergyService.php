<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/VictronEnergy.php';
require_once __DIR__ . '/VictronLogica.php';

class VictronEnergyService
{
    private $victronEnergy;
    private $httpClient;
    private $header;

    public function __construct()
    {
        $this->victronEnergy = new VictronEnergy();
        $this->httpClient = new HttpClient();
        $this->header = [
            'x-authorization: ' . $this->victronEnergy->getApiKey()
        ];
    }
    public function getHttpClient()
    {
        return $this->httpClient;
    }
    public function getVictronEnergy()
    {
        return $this->victronEnergy;
    }
    public function setHttpClient($httpClient)
    {
        $this->httpClient = $httpClient;
    }
    public function setVictronEnergy($victronEnergy)
    {
        $this->victronEnergy = $victronEnergy;
    }

    //Recoger datos detallados de la planta
    public function getSiteEquipo($siteId)
    {
        $url = $this->victronEnergy->getUrl() . "installations/$siteId/system-overview";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoger datos detallados de la planta
    public function getSiteAlarms($siteId, $pageIndex = 1, $pageSize = 200)
    {
        $url = $this->victronEnergy->getUrl() . "installations/$siteId/alarm-log?page=$pageIndex&count=$pageSize";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //recoger el grafico de las plantas / parece que tambien funciona para recoger en tiempo real los valores
    public function getGraficoDetails($siteId, $timeStart, $timeEnd, $type, $interval)
    {
        // `interval` UNA vez: antes iba fijo a "hours" y repetido con el pedido,
        // y quien leyera el primero servia horas para cualquier rango.
        $url = $this->victronEnergy->getUrl() . "installations/$siteId/stats?end=$timeEnd&interval=$interval&start=$timeStart&type=$type";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //recoger el grafico de las plantas de Overallstats
    public function getGraficoDetailsOverallstats($siteId, $type)
    {
        $url = $this->victronEnergy->getUrl() . "installations/$siteId/overallstats?type=$type";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoger datos detallados de la planta
    public function getSiteDetails($siteId)
    {
        $url = $this->victronEnergy->getUrl() . "users/" . $this->victronEnergy->getIdInstallation() . "/installations?idSite=$siteId&extended=1";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoger datos detallados de la planta
    public function getSiteRealtime($siteId)
    {
        $url = $this->victronEnergy->getUrl() . "installations/" . $siteId . "/diagnostics";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Método que recoje todas las plantas
    public function getAllPlants($page = 1, $pageSize = 200)
    {
        $url = $this->victronEnergy->getUrl() . "users/" . $this->victronEnergy->getIdInstallation() . "/installations?extended=1";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Energia solar acumulada desde que se dio de alta la instalacion.
     *
     * `overallstats` no sirve (solo trae year/month/week/today y vacios en una
     * planta parada). /stats con type=kwh, interval=years y start=syscreated si
     * trae `totals`, que es lo que se suma: Pb + Pc + Pg. Por ventanas de 4
     * anos, porque VRM no contesta rangos de mas de 5.
     *
     * @return array{kwh: float, Pb: ?float, Pc: ?float, Pg: ?float, desde: int, hasta: int}|null
     */
    public function getTotalHistorico($siteId, $syscreated, ?int $ahora = null): ?array
    {
        if (!is_numeric($syscreated) || (int) $syscreated <= 0) {
            return null;
        }
        $ahora = $ahora ?? time();

        // VRM no contesta rangos de mas de 5 anos: se pide por ventanas y se suma.
        $totales = [];
        foreach (VictronLogica::ventanasHistorico((int) $syscreated, $ahora) as [$inicio, $fin]) {
            $datos = $this->getGraficoDetails($siteId, $inicio, $fin, 'kwh', 'years');
            if (!is_object($datos) || empty($datos->success)) {
                return null; // un trozo que falla daria un total falso por bajo
            }
            $totales[] = $datos->totals ?? null;
        }
        $total = VictronLogica::totalHistorico(VictronLogica::sumarTotales($totales));
        if ($total === null) {
            return null;
        }
        return $total + ['desde' => (int) $syscreated, 'hasta' => $ahora];
    }

    //Método que formatea las gráficas por rango
    public function getEstadisticasEnergiaVictron($rangos, $plantaId)
    {
        if($rangos == null || $rangos == ""){
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
        $hoy = date('Y-m-d');
        $inicioMes = date('Y-m-01');
        $agrupado = [];

        // Agrupar rangos por planta
        foreach ($rangos as $rango) {
            $agrupado[$plantaId]['moneda'] = $rango['moneda'] ?? 'EUR';
            $agrupado[$plantaId]['rangos'][] = $rango;
        }

        $resultados = [];

        foreach ($agrupado as $plantaId => $datos) {
            $moneda = $datos['moneda'];
            $rangos = $datos['rangos'];

            $total = $this->calcularPeriodoVictron($plantaId, $rangos, $this->minFecha($rangos), $hoy);
            $mesActual = $this->calcularPeriodoVictron($plantaId, $rangos, $inicioMes, $hoy);
            $hoyDatos = $this->calcularPeriodoVictron($plantaId, $rangos, $hoy, $hoy);

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
    private function calcularPeriodoVictron($plantId, array $rangos, string $fechaInicio, string $fechaFinal)
    {
        $energiaTotal = 0;
        $ingresoTotal = 0;
        $ahorroTotal = 0;

        foreach ($rangos as $rango) {
            $inicio = max($fechaInicio, $rango['fecha_inicio']);
            $fin = min($fechaFinal, $rango['fecha_final'] ?: $fechaFinal);

            if ($inicio > $fin) continue;

            $startTimestamp = strtotime($inicio);
            $endTimestamp = strtotime($fin . ' 23:59:59');

            // Consulta la energía solar de Victron para ese subrango
            $data = $this->getGraficoDetails($plantId, $startTimestamp, $endTimestamp, 'solar_yield', 'hours');

            // Solar = a bateria (Pb) + a consumo (Pc) + a red (Pg). Solo se
            // sumaba Pb, asi que lo autoconsumido no contaba para ingresos.
            if (is_object($data) && isset($data->records)) {
                $energiaKwh = VictronLogica::energiaSolarKwh($data->records); // ya viene en kWh
                $energiaTotal += $energiaKwh;
                $ingresoTotal += $energiaKwh * $rango['precio'];
                $ahorroTotal += $energiaKwh * $rango['precio_ahorro'];
            }
        }

        return [
            'energia_kwh' => round($energiaTotal, 2),
            'ingreso' => round($ingresoTotal, 2),
            'ahorro' => round($ahorroTotal, 2),
        ];
    }
    private function minFecha(array $rangos): string
    {
        return min(array_column($rangos, 'fecha_inicio'));
    }
}
