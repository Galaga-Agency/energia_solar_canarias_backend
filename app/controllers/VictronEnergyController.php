<?php
require_once __DIR__ . '/../services/VictronEnergyService.php';
require_once __DIR__ . '/../services/PrecioService.php';

/**
 * @param $siteId = el id de la planta
 * @param $startDate = la fecha de inicio
 * @param $endDate = la fecha de fin
 * @return json_encode con los datos que saca desde el servicio
 */
class VictronEnergyController
{
    private $victronEnergyService;
    private $logsController;
    private $precioService;

    public function __construct()
    {
        $this->victronEnergyService = new VictronEnergyService();
        $this->logsController = new LogsController();
        $this->precioService = new PrecioService();
    }

    //Método para obtener las alertas de la planta
    public function getSiteAlarms($siteId,$pageIndex = 1,$pageSize = 200)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy todas las plantas");
        $data = $this->victronEnergyService->getSiteAlarms($siteId,$pageIndex,$pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    //Método para obtener el inventario de la planta
    public function getSiteEquipo($siteId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy todas las plantas");
        $data = $this->victronEnergyService->getSiteEquipo($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    //Método para obtener los datos de un gráfico en concreto dependiendo del gráfico solicitado
    public function getGraficoDetails($data)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy a un gráfico de tipo " . $data['type']);
        $data = $this->victronEnergyService->getGraficoDetails($data['id'], $data['fechaInicio'], $data['fechaFin'], $data['type'],$data['interval']);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    //Método para obtener los datos de un gráfico en concreto dependiendo del gráfico solicitado
    public function getGraficoDetailsOverallstats($data)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy a un gráfico de tipo " . $data['type']);
        $data = $this->victronEnergyService->getGraficoDetailsOverallstats($data['id'], $data['type']);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    //Método para obtener los datos de todas las plantas
    public function getSiteDetails($siteId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy todas las plantas");
        $data = $this->victronEnergyService->getSiteDetails($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /**
     * El detalle de la planta MAS su total historico, para la ficha.
     *
     * Mismo formato que getSiteDetails (la cadena de VRM, codificada) con
     * `records[0].lifetime_energy` anadido: {kwh, Pb, Pc, Pg, desde, hasta}, o
     * null si VRM no lo da. Va aparte de getSiteDetails porque el listado de
     * plantas del cliente tambien lo usa, y ahi seria una llamada mas a VRM
     * por cada planta.
     */
    public function getSiteDetailsConTotales($siteId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy detalle con totales " . $siteId);
        $crudo = $this->victronEnergyService->getSiteDetails($siteId);
        $detalle = is_string($crudo) ? json_decode($crudo, true) : null;

        if (is_array($detalle) && isset($detalle['records'][0]) && is_array($detalle['records'][0])) {
            $planta = $detalle['records'][0];
            try {
                $detalle['records'][0]['lifetime_energy'] =
                    $this->victronEnergyService->getTotalHistorico($siteId, $planta['syscreated'] ?? null);
            } catch (Throwable $e) {
                // El total es un extra: si falla, la ficha sale igual sin el.
                $this->logsController->registrarLog(Logs::ERROR, "VictronEnergy total historico $siteId: " . $e->getMessage());
                $detalle['records'][0]['lifetime_energy'] = null;
            }
            $crudo = json_encode($detalle);
        }

        header('Content-Type: application/json');
        return json_encode($crudo);
    }

    //Método para obtener los datos de todas las plantas
    public function getSiteRealtime($siteId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy todas las plantas");
        $data = $this->victronEnergyService->getSiteRealtime($siteId);
        header('Content-Type: application/json');
        return $data;
    }

    //Método para obtener los datos de todas las plantas
    public function getAllPlants($page = 1, $pageSize = 200)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy todas las plantas");
        $data = $this->victronEnergyService->getAllPlants($page, $pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    //Método para obtener los datos de todas las plantas
    public function getPlantRealPrice($plantId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy precio de la planta real " . $plantId);
        $rangos = $this->precioService->getPreciosPersonalizadosPorPlanta($plantId, 'victronenergy');
        $data = $this->victronEnergyService->getEstadisticasEnergiaVictron($rangos, $plantId);
        header('Content-Type: application/json');
        return json_encode($data);
    }
}
