<?php
require_once '../services/VictronEnergyService.php';
/**
 * @param $siteId = el id de la planta
 * @param $startDate = la fecha de inicio
 * @param $endDate = la fecha de fin
 * @return json_encode con los datos que saca desde el servicio
 */
class VictronEnergyController {
    private $victronEnergyService;
    private $logsController;

    public function __construct() {
        $this->victronEnergyService = new VictronEnergyService();
        $this->logsController = new LogsController();
    }

    //Método para obtener los datos de todas las plantas
    public function getSiteDetails($siteId) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy todas las plantas");
        $data = $this->victronEnergyService->getSiteDetails($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    //Método para obtener los datos de todas las plantas
    public function getAllPlants($page = 1, $pageSize=200) {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de VictronEnergy todas las plantas");
        $data = $this->victronEnergyService->getAllPlants($page, $pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }
}
?>
