<?php
require_once __DIR__ . '/../services/SigenergyService.php';

/**
 * Controlador Sigenergy (SigenCloud).
 * Registra logs y delega en SigenergyService, devolviendo JSON (mismo patron que
 * los demas controladores de proveedor).
 */
class SigenergyController
{
    private $sigenergyService;
    private $logsController;

    public function __construct()
    {
        $this->sigenergyService = new SigenergyService();
        $this->logsController = new LogsController();
    }

    /** Todas las plantas de Sigenergy. */
    public function getAllPlants($page = 1, $pageSize = 2000)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sigenergy todas las plantas");
        $data = $this->sigenergyService->getAllPlants($page, $pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Detalle de una planta. */
    public function getPlantDetails($stationId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sigenergy detalle de la planta " . $stationId);
        $data = $this->sigenergyService->getPlantDetails($stationId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Datos en tiempo real de una planta (energyflow). */
    public function getPlantPowerRealtime($stationId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sigenergy tiempo real de la planta " . $stationId);
        $data = $this->sigenergyService->getPlantRealtime($stationId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Serie temporal (grafica) de una planta. */
    public function getGraficas($data)
    {
        $stationId = $data['id'] ?? null;
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sigenergy grafica de la planta " . $stationId);
        $result = $this->sigenergyService->getGraficas($stationId, $data);
        header('Content-Type: application/json');
        return json_encode($result);
    }

    /** Inventario/equipos de una planta. */
    public function getInventario($stationId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sigenergy inventario de la planta " . $stationId);
        $data = $this->sigenergyService->getInventario($stationId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Incidencias de una planta (deducidas del estado de los equipos; ver el servicio). */
    public function getSiteAlarms($stationId, $pageIndex = 1, $pageSize = 200)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sigenergy alertas de la planta " . $stationId);
        $data = $this->sigenergyService->getSiteAlarms($stationId, $pageIndex, $pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }
}
