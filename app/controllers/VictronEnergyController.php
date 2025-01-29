<?php
require_once __DIR__ . '/../services/VictronEnergyService.php';
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

    public function __construct()
    {
        $this->victronEnergyService = new VictronEnergyService();
        $this->logsController = new LogsController();
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
}
