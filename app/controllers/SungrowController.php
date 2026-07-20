<?php
require_once __DIR__ . '/../services/SungrowService.php';

/**
 * Controlador Sungrow (iSolarCloud).
 * Registra logs y delega en SungrowService, devolviendo JSON (mismo patron que
 * los demas controladores de proveedor).
 */
class SungrowController
{
    private $sungrowService;
    private $logsController;

    public function __construct()
    {
        $this->sungrowService = new SungrowService();
        $this->logsController = new LogsController();
    }

    /** Todas las plantas de Sungrow. */
    public function getAllPlants($page = 1, $pageSize = 2000)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sungrow todas las plantas");
        $data = $this->sungrowService->getAllPlants($page, $pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Detalle de una planta. */
    public function getPlantDetails($psId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sungrow detalle de la planta " . $psId);
        $data = $this->sungrowService->getPlantDetails($psId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Datos en tiempo real de una planta. */
    public function getPlantPowerRealtime($psId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sungrow tiempo real de la planta " . $psId);
        $data = $this->sungrowService->getPlantRealtime($psId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Serie temporal (grafica) de una planta. */
    public function getGraficas($data)
    {
        $psId = $data['id'] ?? null;
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sungrow grafica de la planta " . $psId);
        $result = $this->sungrowService->getGraficas($psId, $data);
        header('Content-Type: application/json');
        return json_encode($result);
    }

    /** Resumen de alarmas de una planta. */
    public function getSiteAlarms($psId, $pageIndex = 1, $pageSize = 200)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sungrow alertas de la planta " . $psId);
        $data = $this->sungrowService->getSiteAlarms($psId, $pageIndex, $pageSize);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Beneficios/ingresos de una planta. */
    public function getPlantPowerBenefits($psId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sungrow beneficios de la planta " . $psId);
        $data = $this->sungrowService->getBenefits($psId);
        header('Content-Type: application/json');
        return json_encode($data);
    }

    /** Inventario/equipos de una planta. */
    public function getInventario($psId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de Sungrow inventario de la planta " . $psId);
        $data = $this->sungrowService->getInventario($psId);
        header('Content-Type: application/json');
        return json_encode($data);
    }
}
