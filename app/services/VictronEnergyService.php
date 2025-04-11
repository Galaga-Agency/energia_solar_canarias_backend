<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/VictronEnergy.php';

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
        $url = $this->victronEnergy->getUrl() . "installations/$siteId/stats?end=$timeEnd&interval=hours&start=$timeStart&type=$type&interval=$interval";
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

            if (isset($data->records->Pb) && is_array($data->records->Pb)) {
                $energiaWh = array_reduce($data->records->Pb, function ($carry, $item) {
                    return $carry + ($item[1] ?? 0);
                }, 0);

                $energiaKwh = $energiaWh; // ¡Ya viene en kWh!
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
