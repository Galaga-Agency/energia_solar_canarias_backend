<?php
require_once __DIR__ . '/../controllers/SolarEdgeController.php';
require_once __DIR__ . '/../controllers/GoodWeController.php';
require_once __DIR__ . '/../controllers/VictronEnergyController.php';
require_once __DIR__ . '/../controllers/usuarios.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../DBObjects/plantasAsociadasDB.php';


class ApiControladorService
{
    private $solarEdgeController;
    private $goodWeController;
    private $victronEnergyController;
    private $logsController;

    public function __construct()
    {
        $this->logsController = new LogsController();
        $this->solarEdgeController = new SolarEdgeController();
        $this->victronEnergyController = new VictronEnergyController;
        $this->goodWeController = new GoodWeController();
    }
    /**
     * 
     * Estas funcion son genericas para todos los proveedores
     * 
     */
    public function getAllPlants()
    {
        $respuesta = new Respuesta;
        try {
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getAllPlants();
            $goodWeData = json_decode($goodWeResponse, true);

            // Obtener datos de SolarEdge
            $solarEdgeResponse = $this->solarEdgeController->getAllPlants();
            $solarEdgeData = json_decode($solarEdgeResponse, true);

            // Obtener datos de SolarEdge
            $victronEnergyResponse = $this->victronEnergyController->getAllPlants();
            $victronEnergyData = json_decode($victronEnergyResponse, true);

            $plants = $this->processPlants($goodWeData, $solarEdgeData, $victronEnergyData);


            if ($plants != null) {
                $this->logsController->registrarLog(Logs::INFO, "Se han encontrado las plantas");
                $respuesta->success($plants);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado plantas");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error en el servidor de algun proveedor");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    /**
     * 
     * Estas funcion son genericas para genericas para SolarEdge
     * 
     */
    public function overviewSolarEdge($siteId)
    {
        $respuesta = new Respuesta;
        try {

            $solarEdgeResponse = $this->solarEdgeController->overviewSolarEdge($siteId);

            $solarEdgeData = json_decode($solarEdgeResponse);


            if ($solarEdgeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado los beneficios de SolarEdge");
                $respuesta->success($solarEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado los beneficios de SolarEdge");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "No se han encontrado Beneficios o la peticion es nula";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de SolarEdge: " . $e->getMessage());
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getPlantComparative($siteId, $startTime, $timeUnit)
    {
        $respuesta = new Respuesta;
        try {
            $solarEdgeResponse = $this->solarEdgeController->getPlantComparative($siteId, $startTime, $timeUnit);
            $solarEdgeData = json_decode($solarEdgeResponse);

            if ($solarEdgeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado la comparacion de años de SolarEdge");
                $respuesta->success($solarEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado la comparacion de años de SolarEdge");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "No se han encontrado la comparacion de años o la peticion es nula";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de SolarEdge: " . $e->getMessage());
            $respuesta->_500($e->getMessage());
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function cargaBateriaSolarEdge($powerStationId, $startTime, $endTime)
    {
        $respuesta = new Respuesta;
        try {
            $solarEdgeResponse = $this->solarEdgeController->cargaBateriaSolarEdge($powerStationId, $startTime, $endTime);
            $solarEdgeData = json_decode($solarEdgeResponse);

            if ($solarEdgeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se ha encontrado el inventario de SolarEdge");
                $respuesta->success($solarEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se ha encontrado el inventario de SolarEdge");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "no se ha encontrado el inventario de SolarEdge";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de SolarEdge: " . $e->getMessage());
            $respuesta->_500($e->getMessage());
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    /**
     * 
     * Estas funcion proporcionan informacion sobre las alertas
     * 
     */

    //VictronEnergy
    public function getSiteAlarms($siteId, $pageIndex = 1, $pageSize = 200)
    {
        $respuesta = new Respuesta;
        try {
            // Obtener datos de GoodWe
            $victronEnergyResponse = $this->victronEnergyController->getSiteAlarms($siteId, $pageIndex, $pageSize);
            $victronEnergyData = json_decode($victronEnergyResponse, true);

            if ($victronEnergyData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en VictronEnergy");
                $respuesta->success($victronEnergyData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en VictronEnergy");
                $respuesta->_400($victronEnergyData);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de VictronEnergy");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }

    public function GetPowerStationWariningInfoByMultiCondition($pageIndex = 1, $pageSize = 200, $status = 3)
    {
        $respuesta = new Respuesta;
        try {
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->GetPowerStationWariningInfoByMultiCondition($pageIndex, $pageSize, $status);
            $goodWeData = json_decode($goodWeResponse, true);

            if ($goodWeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en GoodWe");
                $respuesta->success($goodWeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en GoodWe");
                $respuesta->_400($goodWeData);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de GoodWe");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }

    /**
     * 
     * Estas funcion proporcionan informacion sobre el equipo
     * 
     */
    //VictronEnergy
    public function getSiteEquipoVictronEnergy($siteId)
    {
        $respuesta = new Respuesta;
        try {
            $victronEnergyResponse = $this->victronEnergyController->getSiteEquipo($siteId);
            $victronEnergyData = json_decode($victronEnergyResponse);

            if ($victronEnergyData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se ha encontrado el inventario de VictronEnergy");
                $respuesta->success($victronEnergyData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se ha encontrado el inventario de VictronEnergy");
                $respuesta->_400($victronEnergyData);
                $respuesta->message = "no se ha encontrado el inventario de VictronEnergy";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de VictronEnergy: " . $e->getMessage());
            $respuesta->_500($e->getMessage());
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    //SolarEdge
    public function inventarioSolarEdge($siteId)
    {
        $respuesta = new Respuesta;
        try {
            $solarEdgeResponse = $this->solarEdgeController->inventarioSolarEdge($siteId);
            $solarEdgeData = json_decode($solarEdgeResponse);

            if ($solarEdgeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se ha encontrado el inventario de SolarEdge");
                $respuesta->success($solarEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se ha encontrado el inventario de SolarEdge");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "no se ha encontrado el inventario de SolarEdge";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de SolarEdge: " . $e->getMessage());
            $respuesta->_500($e->getMessage());
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    //GoodWe
    public function GetInverterAllPoint($powerStationId)
    {
        $respuesta = new Respuesta;
        try {
            $goodWeResponse = $this->goodWeController->GetInverterAllPoint($powerStationId);
            $goodWeEdgeData = json_decode($goodWeResponse);

            if ($goodWeEdgeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se ha encontrado el inventario de GoodWe");
                $respuesta->success($goodWeEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se ha encontrado el inventario de GoodWe");
                $respuesta->_400($goodWeEdgeData);
                $respuesta->message = "no se ha encontrado el inventario de GoodWe";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de GoodWe: " . $e->getMessage());
            $respuesta->_500($e->getMessage());
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }

    /**
     * 
     * Estas funciones se utilizan para obtener los beneficios de las plantas de todos los proveedores que lo permitan
     * 
     */
    public function getBenefitsSolarEdge($powerStationId)
    {
        $respuesta = new Respuesta;
        try {

            $solarEdgeResponse = $this->solarEdgeController->getPlantPowerBenefits($powerStationId);

            $solarEdgeData = json_decode($solarEdgeResponse);


            if ($solarEdgeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado los beneficios de SolarEdge");
                $respuesta->success($solarEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado los beneficios de SolarEdge");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "No se han encontrado Beneficios o la peticion es nula";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de SolarEdge: " . $e->getMessage());
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    /**
     * 
     * Estas funciones se utilizan para obtener los datos de las gráficas de todos los proveedores
     * 
     */
    public function getGraficasSolarEdge()
    {
        $respuesta = new Respuesta;
        try {

            // Obtener datos de SolarEdge
            $data = $this->getEnergyDashBoardCuerpo();
            if ($data != null) {
                $solarEdgeResponse = $this->solarEdgeController->getPowerDashboard($data['siteId'], $data['timeUnit'], $data['endTime'], $data['startTime']);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se a realizado correctamente la peticion a la api faltan parametros o son de distinto nombre");
                $respuesta->_400();
                $respuesta->message = "No se a realizado correctamente la peticion a la api faltan parametros o son de distinto nombre";
                http_response_code(400);
                echo json_encode($respuesta);
                return;
            }
            $solarEdgeData = json_decode($solarEdgeResponse);


            if ($solarEdgeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las gráficas de SolarEdge");
                $respuesta->success($solarEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las gráficas de SolarEdge");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "No se han encontrado graficas de SolarEdge";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de SolarEdge: " . $e->getMessage());
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getGraficasGoodWe()
    {
        $respuesta = new Respuesta;
        try {
            $data = $this->getChartByPlantCuerpo();

            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getChartByPlants($data);
            $goodWeData = json_decode($goodWeResponse, true);

            if ($goodWeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en GoodWe");
                $respuesta->success($goodWeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en GoodWe");
                $respuesta->_400($goodWeData);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de GoodWe");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getGraficasVictronEnergy()
    {
        $respuesta = new Respuesta;
        try {
            $data = $this->getCuerpoGraficaVictronEnergy();

            // Obtener datos de GoodWe
            if ($data != null) {
                $victronEnergyResponse = $this->victronEnergyController->getGraficoDetails($data);
                $victronEnergyData = json_decode($victronEnergyResponse, true);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han pasado los parametros correctos en VictronEnergy");
                $respuesta->_400();
                $respuesta->message = "revisa los parametros";
                http_response_code(400);
                echo json_encode($respuesta);
                return;
            }

            if ($victronEnergyData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en VictronEnergy");
                $respuesta->success($victronEnergyData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en VictronEnergy");
                $respuesta->_400($victronEnergyData);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de VictronEnergy");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    /**
     * 
     * Estas funciones se utilizan para obtener los datos en tiempo real de todos los proveedores
     * 
     */
    public function getPlantPowerRealtimeSolarEdge($powerStationId)
    {
        $respuesta = new Respuesta;
        try {

            $solarEdgeResponse = $this->solarEdgeController->getPlantPowerRealtime($powerStationId);

            $solarEdgeData = json_decode($solarEdgeResponse);


            if ($solarEdgeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las gráficas de SolarEdge");
                $respuesta->success($solarEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las gráficas de SolarEdge");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "No se han encontrado graficas de SolarEdge";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error del proveedor de SolarEdge: " . $e->getMessage());
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getPlantPowerRealtimeGoodwe($powerStationId)
    {
        $respuesta = new Respuesta;
        try {
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getPlantPowerRealtime($powerStationId);
            $goodWeData = json_decode($goodWeResponse, true);

            if ($goodWeData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en GoodWe");
                $respuesta->success($goodWeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en GoodWe");
                $respuesta->_400($goodWeData);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de GoodWe");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getPlantPowerRealtimeVictronEnergy($powerStationId)
    {
        $respuesta = new Respuesta;
        try {
            // Obtener datos de GoodWe
            $victronEnergyResponse = $this->victronEnergyController->getSiteRealtime($powerStationId);
            $victronEnergyData = json_decode($victronEnergyResponse, true);

            if ($victronEnergyData != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en GoodWe");
                $respuesta->success($victronEnergyData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en GoodWe");
                $respuesta->_400($victronEnergyData);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de GoodWe");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    /**
     * 
     * Estas funciones se utilizan para obtener los datos de Todas las plantas de cada proveedor
     * 
     */
    public function getAllPlantsGoodWe($page = 1, $pageSize = 200)
    {
        $respuesta = new Paginacion();
        try {
            // Obtener datos de GoodWe
            $goodWeResponse = $this->goodWeController->getAllPlants($page, $pageSize);
            $goodWeData = json_decode($goodWeResponse, true);

            $plants = $this->processPlants($goodWeData, [], []);

            if ($plants != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en GoodWe");
                $respuesta->success($plants);
                $respuesta->page = $page;
                $respuesta->limit = $pageSize;
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas en GoodWe");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de GoodWe");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getAllPlantsSolarEdge($page = 1, $pageSize = 200)
    {
        $respuesta = new Paginacion();
        try {
            // Obtener datos de SolarEdge
            $solarEdgeResponse = $this->solarEdgeController->getAllPlants($page, $pageSize);
            $solarEdgeData = json_decode($solarEdgeResponse, true);

            $plants = $this->processPlants([], $solarEdgeData, []);

            if ($plants != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en SolarEdge");
                $respuesta->success($plants);
                $respuesta->page = $page;
                $respuesta->limit = $pageSize;
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las plantas en SolarEdge");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de SolarEdge");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getAllPlantsVictronEnergy($page = 1, $pageSize = 200)
    {
        $respuesta = new Paginacion();
        try {
            // Obtener datos de SolarEdge
            $victronEnergyResponse = $this->victronEnergyController->getAllPlants($page, $pageSize);
            $victronEnergyData = json_decode($victronEnergyResponse, true);

            $plants = $this->processPlants([], [], $victronEnergyData);

            if ($plants != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en SolarEdge");
                $respuesta->success($plants);
                $respuesta->page = $page;
                $respuesta->limit = $pageSize;
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las plantas en SolarEdge");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de SolarEdge");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getAllPlantsCliente($idUsuario)
    {
        $respuesta = new Respuesta;
        try {
            $plantasAsociadasDB = new PlantasAsociadasDB;
            $plantasAsociadas = $plantasAsociadasDB->getPlantasAsociadasAlUsuario($idUsuario);

            if ($plantasAsociadas == false) {
                $this->logsController->registrarLog(Logs::INFO, "No se encuentran plantas del cliente");
                $respuesta->_404();
                $respuesta->message = 'No se han encontrado plantas para este usuario';
                http_response_code(404);
                echo json_encode($respuesta);
                return;
            }

            $goodWeArray = [];
            $solarEdgeArray = [];
            $victronEnergyArray = [];

            foreach ($plantasAsociadas as $planta) {
                if ($planta['nombre_proveedor'] === 'GoodWe') {
                    // Obtener y decodificar datos de GoodWe
                    $goodWeResponse = $this->goodWeController->getPlantDetails($planta['planta_id']);
                    $goodWeData = $this->decodeJsonResponse($goodWeResponse);

                    if (is_array($goodWeData) && isset($goodWeData['data']['info']['powerstation_id'])) {
                        // Usar el ID como clave para evitar duplicados
                        $goodWeArray[$goodWeData['data']['info']['powerstation_id']] = $goodWeData;
                    }
                }
                if ($planta['nombre_proveedor'] === 'SolarEdge') {
                    // Obtener y decodificar datos de SolarEdge
                    $solarEdgeResponse = $this->solarEdgeController->getSiteDetails($planta['planta_id']);
                    $solarEdgeData = $this->decodeJsonResponse($solarEdgeResponse);

                    if (is_array($solarEdgeData) && isset($solarEdgeData['details']['id'])) {
                        // Usar el ID como clave para evitar duplicados
                        $solarEdgeArray[$solarEdgeData['details']['id']] = $solarEdgeData;
                    }
                }
                if ($planta['nombre_proveedor'] === 'VictronEnergy') {
                    // Obtener y decodificar datos de SolarEdge
                    $victronEnergyResponse = $this->victronEnergyController->getSiteDetails($planta['planta_id']);
                    $victronEnergyData = $this->decodeJsonResponse($victronEnergyResponse);

                    if (is_array($victronEnergyData) && isset($victronEnergyData['records'][0]['idSite'])) {
                        // Usar el ID como clave para evitar duplicados
                        $victronEnergyArray[$victronEnergyData['records'][0]['idSite']] = $victronEnergyData;
                    }
                }
            }

            // Convertir los arrays asociativos en arrays simples para procesarlos
            $goodWeArray = array_values($goodWeArray);
            $solarEdgeArray = array_values($solarEdgeArray);
            $victronEnergyArray = array_values($victronEnergyArray);


            $processedPlants = $this->processPlantsCliente($goodWeArray, $solarEdgeArray, $victronEnergyArray);
            $respuesta->success($processedPlants);
            $this->logsController->registrarLog(Logs::INFO, "El usuario accede a sus plantas");
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error cogiendo las plantas del usuario");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algún proveedor " . $e->getMessage();
            http_response_code(500);
        }

        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    /**
     * 
     * Estas funciones recogen los detalles de la planta
     * 
     */
    public function getSiteDetail($id, $proveedor)
    {
        $respuesta = new Respuesta;
        try {
            global $proveedores; // Acceder al array global dentro de la función


            // Validar proveedor y asignar datos correspondientes
            if ($proveedor === $proveedores['GoodWe']) {
                // Obtener datos de GoodWe
                $goodWeResponse = $this->goodWeController->getPlantDetails($id);
                $goodWeData = json_decode($goodWeResponse, true);
                $plants = $goodWeData;
            } elseif ($proveedor === $proveedores['SolarEdge']) {
                // Obtener datos de SolarEdge
                $solarEdgeResponse = $this->solarEdgeController->getSiteDetails($id);
                $solarEdgeData = json_decode($solarEdgeResponse, true);
                $plants = $solarEdgeData;
            } elseif ($proveedor === $proveedores['VictronEnergy']) {
                // Obtener datos de VictronEnergy
                $victronEnergyResponse = $this->victronEnergyController->getSiteDetails($id);
                $victronEnergyData = json_decode($victronEnergyResponse, true);
                $plants = $victronEnergyData;
            } else {
                // Proveedor inválido
                $this->logsController->registrarLog(Logs::ERROR, "Proveedor no válido: $proveedor");
                $respuesta->_400();
                $respuesta->message = "Proveedor no válido.";
                http_response_code(400);
                echo json_encode($respuesta);
                return;
            }

            // Validar que los datos no sean null o vacíos
            if (!empty($plants) && $plants !== null) {
                $respuesta->success(json_decode($plants));
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas para el proveedor $proveedor con ID $id");
                $respuesta->_400();
                $respuesta->message = "No se han encontrado plantas.";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "Error en el servidor de la API: " . $e->getMessage());
            $respuesta->_500();
            $respuesta->message = "Error interno del servidor: " . $e->getMessage();
            http_response_code(500);
        }

        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getSiteDetailCliente($usuarioId, $idPlanta, $proveedor)
    {
        $respuesta = new Respuesta;
        try {
            global $proveedores; // Acceder al array global dentro de la función
            // Verificar si el proveedor está en el array global de proveedores
            $plantasAsociadas = new PlantasAsociadasDB;
            if ($plantasAsociadas->isPlantasAsociadasAlUsuario($usuarioId, $idPlanta, $proveedor)) {
                if ($proveedor == $proveedores['GoodWe']) {
                    // Obtener datos de GoodWe
                    $goodWeResponse = $this->goodWeController->getPlantDetails($idPlanta);
                    $goodWeData = json_decode($goodWeResponse, true);
                } else {
                    $goodWeData = "";
                }

                if ($proveedor == $proveedores['SolarEdge']) {
                    // Obtener datos de SolarEdge
                    $solarEdgeResponse = $this->solarEdgeController->getSiteDetails($idPlanta);
                    $solarEdgeData = json_decode($solarEdgeResponse, true);
                } else {
                    $solarEdgeData = "";
                }

                if ($proveedor == $proveedores['GoodWe']) {
                    $plants = $goodWeData;
                } else if ($proveedor == $proveedores['SolarEdge']) {
                    $plants = $solarEdgeData;
                }


                if ($plants != null) {
                    $this->logsController->registrarLog(Logs::INFO, "Se han solicitado las plantas del cliente");
                    // Verificar si los datos son un array o un string
                    if (is_array($plants)) {
                        $respuesta->success($plants);
                    } else {
                        $respuesta->success(json_decode($plants));
                    }
                } else {
                    $this->logsController->registrarLog(Logs::INFO, "No se han encontrado plantas");
                    $respuesta->_400($plants);
                    $respuesta->message = "No se han encontrado plantas";
                    http_response_code(400);
                }
            } else {
                $this->logsController->registrarLog(Logs::INFO, "El id del usuario y id de la planta no coincide o no esta disponible para ese usuario");
                $respuesta->_404();
                $respuesta->message = "El id del usuario y id de la planta no coincide o no esta disponible para ese usuario";
                http_response_code(404);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, "error en el servidor de la API" . $e->getMessage());
            $respuesta->_500();
            $respuesta->message = $e->getMessage();;
            http_response_code(500);
        }
        // Devolver el resultado como JSON
        header('Content-Type: application/json');
        echo json_encode($respuesta, true);
    }
    /**
     * 
     * Estas funciones se utilizan para procesar las plantas todas con el mismo formato de salida 'mapear el array'
     * 
     */
    //Aquí va la lógica de las apis conversiones etc.. (Lista plantas Admin)
    public function processPlants(array $goodWeData, array $solarEdgeData, array $victronEnergyData): array
    {
        $plants = [];

        // Procesar datos de GoodWe
        if (isset($goodWeData['data']['list']) && is_array($goodWeData['data']['list'])) {
            foreach ($goodWeData['data']['list'] as $plant) {
                $status = "";
                // Mapear el código de estado a una descripción legible
                $status = $this->mapGoodWeStatus($plant['status']);
                $plants[] = [
                    'id' => $plant['powerstation_id'] ?? '',
                    'name' => $plant['stationname'] ?? '',
                    'address' => $plant['location'] ?? '',
                    'capacity' => $plant['capacity'] ?? 0,
                    'status' => $status,
                    'type' => $plant['powerstation_type'] ?? '',
                    'latitude' => $plant['latitude'] ?? '',
                    'longitude' => $plant['longitude'] ?? '',
                    'organization' => 'goodwe',
                    'batteryVoltage' => null, //No disponible en GoodWe
                    'batterySoc' => null, //No disponible en GoodWe
                    'current_power' => $plant['pac'] ?? 0, // Potencia actual en W
                    'total_energy' => $plant['etotal'] ?? 0, // Energía total generada en kWh
                    'daily_energy' => $plant['eday'] ?? 0, // Energía generada hoy en kWh
                    'monthly_energy' => $plant['emonth'] ?? 0, // Energía generada este mes en kWh
                    'installation_date' => null, // No disponible en GoodWe
                    'pto_date' => null, // No disponible en GoodWe
                    'notes' => null, // No disponible en GoodWe
                    'alert_quantity' => null, // No disponible en GoodWe
                    'highest_impact' => null, // No disponible en GoodWe
                    'primary_module' => null, // No disponible en GoodWe
                    'public_settings' => null // No disponible en GoodWe
                ];
            }
        }

        // Procesar datos de SolarEdge
        if (isset($solarEdgeData['sites']['site']) && is_array($solarEdgeData['sites']['site'])) {
            foreach ($solarEdgeData['sites']['site'] as $site) {
                $addressParts = [
                    $site['location']['address'] ?? '',
                    $site['location']['city'] ?? '',
                    $site['location']['country'] ?? ''
                ];
                $address = implode(', ', array_filter($addressParts));

                $status = "";
                // Mapear el código de estado a una descripción legible
                $status = $this->mapSolarEdgeStatus($site['status']);

                $plants[] = [
                    'id' => $site['id'] ?? '',
                    'name' => $site['name'] ?? '',
                    'address' => $address,
                    'capacity' => $site['peakPower'] ?? 0,
                    'status' => $status,
                    'type' => $site['type'] ?? '',
                    'latitude' => $site['location']['latitude'] ?? '',
                    'longitude' => $site['location']['longitude'] ?? '',
                    'organization' => 'SolarEdge',
                    'batteryVoltage' => null, //No disponible en SolarEdge
                    'batterySoc' => null, //No disponible en SolarEdge
                    'current_power' => null, // No disponible en SolarEdge
                    'total_energy' => null, // No disponible en SolarEdge
                    'daily_energy' => null, // No disponible en SolarEdge
                    'monthly_energy' => null, // No disponible en SolarEdge
                    'installation_date' => $site['installationDate'] ?? null,
                    'pto_date' => $site['ptoDate'] ?? null,
                    'notes' => $site['notes'] ?? null,
                    'alert_quantity' => $site['alertQuantity'] ?? null,
                    'highest_impact' => $site['highestImpact'] ?? null,
                    'primary_module' => $site['primaryModule'] ?? null,
                    'public_settings' => $site['publicSettings'] ?? null
                ];
            }
        }

        // Verificar que 'records' es un array
        if (isset($victronEnergyData['records']) && is_array($victronEnergyData['records'])) {
            foreach ($victronEnergyData['records'] as $plant) {
                // Inicializar latitud y longitud como null
                $latitud = null;
                $longitud = null;

                $address = $plant['geofence']; //Le paso el array que luego parseamos para recoger la latitud y longitud

                // Buscar los valores de 'lat' y 'lng'
                if (preg_match('/"lat":([0-9\.-]+)/', $address, $latMatch)) {
                    $latitud = $latMatch[1];
                }
                if (preg_match('/"lng":([0-9\.-]+)/', $address, $lngMatch)) {
                    $longitud = $lngMatch[1];
                }

                // Convertir syscreated a fecha
                $installation_date = isset($plant['syscreated']) ? date("Y-m-d", $plant['syscreated']) : null;

                // Verificar si 'extended' existe y es un array
                if (isset($plant['extended']) && is_array($plant['extended'])) {
                    foreach ($plant['extended'] as $item) {
                        if (isset($item['idDataAttribute'], $item['rawValue'])) {
                            if ($item['idDataAttribute'] == 144) {
                                $batterySoc = $item['rawValue'];
                            } elseif ($item['idDataAttribute'] == 143) {
                                $batteryVoltage = $item['rawValue'];
                            } elseif ($item['idDataAttribute'] == 215) {
                                $status = $item['formattedValue'];
                            }
                        }
                    }
                }

                // Construir el array de datos
                $plants[] = [
                    'id' => $plant['idSite'] ?? '',
                    'name' => $plant['name'] ?? '',
                    'address' => $plant['geofence'] ?? null,
                    'capacity' => $plant['pvMax'] ?? 0,
                    'status' => $status ?? null, // Valor predeterminado
                    'type' => $plant['device_icon'] ?? '',
                    'latitude' => $latitud, // Procesado previamente
                    'longitude' => $longitud, // Procesado previamente
                    'organization' => 'victronenergy', // Valor fijo
                    'batteryVoltage' => $batteryVoltage ?? null,
                    'batterySoc' => $batterySoc ?? null,
                    'current_power' => null,
                    'total_energy' => null,
                    'daily_energy' => null,
                    'monthly_energy' => null,
                    'installation_date' => $installation_date,
                    'pto_date' => null,
                    'notes' => $plant['notes'] ?? null,
                    'alert_quantity' => $plant['alarmMonitoring'] ?? null,
                    'highest_impact' => null,
                    'primary_module' => null,
                    'public_settings' => null
                ];
            }
        } else {
            error_log('Error: "records" no es un array válido o está vacío.');
        }

        return $plants;
    }
    //Aquí va la lógica de las apis conversiones etc.. (Lista plantas Cliente)
    public function processPlantsCliente(array $goodWeData, array $solarEdgeData, array $victronEnergyData): array
    {
        $plants = [];

        // Procesar datos de GoodWe
        foreach ($goodWeData as $goodWePlant) {
            $status = $this->mapGoodWeStatus($goodWePlant['data']['info']['status'] ?? '');
            echo json_encode($goodWePlant);
            $plant = [
                'id' => $goodWePlant['data']['info']['powerstation_id'] ?? null,
                'name' => $goodWePlant['data']['info']['stationname'] ?? null,
                'address' => $goodWePlant['data']['info']['address'] ?? null,
                'capacity' => $goodWePlant['data']['info']['capacity'] ?? null,
                'status' => $status,
                'type' => $goodWePlant['data']['info']['powerstation_type'] ?? null,
                'latitude' => $goodWePlant['latitude'] ?? null,
                'longitude' => $goodWePlant['longitude'] ?? null,
                'organization' => 'goodwe',
                'current_power' => $goodWePlant['data']['kpi']['pac'] ?? null, // Potencia actual en W
                'total_energy' => $goodWePlant['data']['kpi']['total_power'] ?? null, // Energía total generada en kWh
                'daily_energy' => $goodWePlant['data']['kpi']['power'] ?? null, // Energía generada hoy en kWh
                'monthly_energy' => $goodWePlant['data']['kpi']['month_generation'] ?? null, // Energía generada este mes en kWh
                'installation_date' => null, // No disponible en GoodWe
                'pto_date' => null, // No disponible en GoodWe
                'notes' => null, // No disponible en GoodWe
                'alert_quantity' => null, // No disponible en GoodWe
                'highest_impact' => null, // No disponible en GoodWe
                'primary_module' => null, // No disponible en GoodWe
                'public_settings' => null // No disponible en GoodWe
            ];

            $plants[] = $plant; // Agregar el planta de GoodWe al array $plants
        }

        // Procesar datos de SolarEdge
        foreach ($solarEdgeData as $solarEdgePlant) {
            $addressParts = [
                $solarEdgePlant['details']['location']['address'] ?? '',
                $solarEdgePlant['details']['location']['city'] ?? '',
                $solarEdgePlant['details']['location']['country'] ?? ''
            ];
            $address = implode(', ', array_filter($addressParts));

            $status = $this->mapSolarEdgeStatus($solarEdgePlant['details']['status'] ?? '');

            $plant = [
                'id' => $solarEdgePlant['details']['id'] ?? null,
                'name' => $solarEdgePlant['details']['name'] ?? null,
                'address' => $address,
                'capacity' => $solarEdgePlant['details']['peakPower'] ?? null,
                'status' => $status,
                'type' => $solarEdgePlant['details']['type'] ?? null,
                'latitude' => $solarEdgePlant['details']['location']['latitude'] ?? null,
                'longitude' => $solarEdgePlant['details']['location']['longitude'] ?? null,
                'organization' => 'SolarEdge',
                'current_power' => null, // No disponible en SolarEdge
                'total_energy' => null, // No disponible en SolarEdge
                'daily_energy' => null, // No disponible en SolarEdge
                'monthly_energy' => null, // No disponible en SolarEdge
                'installation_date' => $solarEdgePlant['details']['installationDate'] ?? null,
                'pto_date' => $solarEdgePlant['details']['ptoDate'] ?? null,
                'notes' => $solarEdgePlant['details']['notes'] ?? null,
                'alert_quantity' => $solarEdgePlant['details']['alertQuantity'] ?? null,
                'highest_impact' => $solarEdgePlant['details']['highestImpact'] ?? null,
                'primary_module' => $solarEdgePlant['details']['primaryModule'] ?? null,
                'public_settings' => $solarEdgePlant['details']['publicSettings'] ?? null
            ];

            $plants[] = $plant; // Agregar la planta de SolarEdge al array $plants
        }
        // Procesar datos de victronEnergyData
        if (isset($victronEnergyData[0]['records']) && is_array($victronEnergyData[0]['records'])) {
            foreach ($victronEnergyData[0]['records'] as $plant) {
                // Inicializar latitud y longitud como null
                $latitud = null;
                $longitud = null;

                $address = $plant['geofence']; //Le paso el array que luego parseamos para recoger la latitud y longitud

                // Buscar los valores de 'lat' y 'lng'
                if (preg_match('/"lat":([0-9\.-]+)/', $address, $latMatch)) {
                    $latitud = $latMatch[1];
                }
                if (preg_match('/"lng":([0-9\.-]+)/', $address, $lngMatch)) {
                    $longitud = $lngMatch[1];
                }

                // Convertir syscreated a fecha
                $installation_date = isset($plant['syscreated']) ? date("Y-m-d", $plant['syscreated']) : null;

                // Verificar si 'extended' existe y es un array
                if (isset($plant['extended']) && is_array($plant['extended'])) {
                    foreach ($plant['extended'] as $item) {
                        if (isset($item['idDataAttribute'], $item['rawValue'])) {
                            if ($item['idDataAttribute'] == 144) {
                                $batterySoc = $item['rawValue'];
                            } elseif ($item['idDataAttribute'] == 143) {
                                $batteryVoltage = $item['rawValue'];
                            } elseif ($item['idDataAttribute'] == 215) {
                                $status = $item['formattedValue'];
                            }
                        }
                    }
                }

                // Construir el array de datos
                $plants[] = [
                    'id' => $plant['idSite'] ?? '',
                    'name' => $plant['name'] ?? '',
                    'address' => $plant['geofence'] ?? null,
                    'capacity' => $plant['pvMax'] ?? 0,
                    'status' => $status ?? null, // Valor predeterminado
                    'type' => $plant['device_icon'] ?? '',
                    'latitude' => $latitud, // Procesado previamente
                    'longitude' => $longitud, // Procesado previamente
                    'organization' => 'victronenergy', // Valor fijo
                    'batteryVoltage' => $batteryVoltage ?? null,
                    'batterySoc' => $batterySoc ?? null,
                    'current_power' => null,
                    'total_energy' => null,
                    'daily_energy' => null,
                    'monthly_energy' => null,
                    'installation_date' => $installation_date,
                    'pto_date' => null,
                    'notes' => $plant['notes'] ?? null,
                    'alert_quantity' => $plant['alarmMonitoring'] ?? null,
                    'highest_impact' => null,
                    'primary_module' => null,
                    'public_settings' => null
                ];
            }
        }

        return $plants;
    }
    //===================== Estas funciones se utilizan para recoger datos de clientes en una planta ====================
    public function getAllClientsPlanta($idPlanta,$nombreProveedor)
    {
        $respuesta = new Respuesta;
        try {
            $usuariosController = new UsuariosController;
            $usuariosAsociados = $usuariosController->getUsuariosAsociadosAPlantas($idPlanta,$nombreProveedor);  
            $respuesta->success($usuariosAsociados);
            $this->logsController->registrarLog(Logs::INFO, "El administrador accede a las plantas del usuario");
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error cogiendo las plantas del usuario");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algún proveedor " . $e->getMessage();
            http_response_code(500);
        }
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    /**
     * Estas funciones se utilizan para mapear el codigo de status
     */
    // Función para mapear el estado de GoodWe a una descripción legible
    private function mapGoodWeStatus($statusCode)
    {
        switch ($statusCode) {
            case 2:
                return 'error';
            case 1:
                return 'working';
            case 0:
                return 'waiting';
            case -1:
                return 'disconnected';
            default:
                return 'unknown';
        }
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
     * Estas funciones se utilizan para mapear las gráficas
     */
    //acceso graficas de Victron Energy 
    public function getCuerpoGraficaVictronEnergy()
    {
        // Obtén los datos JSON del cuerpo de la solicitud POST
        $json = file_get_contents('php://input');

        // Decodifica el JSON en un array o un objeto PHP
        $data = json_decode($json, true); // El segundo parámetro true convierte el JSON a un array asociativo

        // Verifica si los datos fueron decodificados correctamente
        if ($data === null) {
            return null;
        }

        // Verifica si las claves existen en el array
        $id = isset($data['id']) ? $data['id'] : null;
        $tipo = isset($data['type']) ? $data['type'] : null;
        $interval = isset($data['interval']) ? $data['interval'] : null;
        $fechaInicio = isset($data['fechaInicio']) ? $data['fechaInicio'] : $data['fechaInicio'];
        $fechaFin = isset($data['fechaFin']) ? $data['fechaFin'] : $data['fechaFin'];

        // Si alguna de las claves no existe, retorna null
        if ($id === null || $tipo === null || $fechaInicio === null || $fechaFin === null || $interval === null) {
            return null;
        }
        // Si todo está presente, puedes proceder con el uso de las variables
        return [
            'id' => $id,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'interval' => $interval,
            'type' => $tipo
        ];
    }
    //acceso graficas de GoodWe 
    public function getChartByPlantCuerpo()
    {
        // Obtén los datos JSON del cuerpo de la solicitud POST
        $json = file_get_contents('php://input');

        // Decodifica el JSON en un array o un objeto PHP
        $data = json_decode($json, true); // El segundo parámetro true convierte el JSON a un array asociativo

        // Verifica si los datos fueron decodificados correctamente
        if ($data === null) {
            return null;
        }

        // Verifica si las claves existen en el array
        $id = isset($data['id']) ? $data['id'] : null;
        $date = isset($data['date']) ? $data['date'] : null;
        $range = isset($data['range']) ? $data['range'] : null;
        $chartIndexId = isset($data['chartIndexId']) ? $data['chartIndexId'] : null;
        $full_script = isset($data['full_script']) ? $data['full_script'] : null;

        // Si alguna de las claves no existe, retorna null
        if ($id === null || $date === null || $range === null && $chartIndexId != "potencia" || $chartIndexId === null) {
            return null;
        }

        switch ($chartIndexId) {
            case "generacion de energia y ingresos":
                switch ($range) {
                    case "dia":
                        // Código para el rango "dia"
                        $chartIndexId = "3";
                        $range = 2;
                        break;
                    case "mes":
                        // Código para el rango "mes"
                        $chartIndexId = "3";
                        $range = "3";
                        break;
                    case "año":
                        // Código para el rango "año"
                        $chartIndexId = "3";
                        $range = "4";
                        break;
                    default:
                        // Código para el caso por defecto
                        $chartIndexId = "3";
                        $range = 2;
                        break;
                }
                break;

            case "proporcion para uso personal":
                switch ($range) {
                    case "dia":
                        // Código para el rango "dia"
                        $chartIndexId = "5";
                        $range = 2;
                        break;
                    case "mes":
                        // Código para el rango "mes"
                        $chartIndexId = "5";
                        $range = "3";
                        break;
                    case "año":
                        // Código para el rango "año"
                        $chartIndexId = "5";
                        $range = "4";
                        break;
                    default:
                        // Código para el caso por defecto
                        $chartIndexId = "5";
                        $range = 2;
                        break;
                }
                break;

            case "indice de contribucion":
                switch ($range) {
                    case "dia":
                        // Código para el rango "dia"
                        $range = 2;
                        $chartIndexId = "8";
                        break;
                    case "mes":
                        // Código para el rango "mes"
                        $range = "3";
                        $chartIndexId = "8";
                        break;
                    case "año":
                        // Código para el rango "año"
                        $range = "4";
                        $chartIndexId = "8";
                        break;
                    default:
                        // Código para el caso por defecto
                        $chartIndexId = "8";
                        $range = 2;
                        break;
                }
                break;

            case "estadisticas sobre energia":
                switch ($range) {
                    case "dia":
                        // Código para el rango "dia"
                        $range = 2;
                        $chartIndexId = "7";
                        break;
                    case "mes":
                        // Código para el rango "mes"
                        $range = "3";
                        $chartIndexId = "7";
                        break;
                    case "año":
                        // Código para el rango "año"
                        $range = "4";
                        $chartIndexId = "7";
                        break;
                    default:
                        // Código para el caso por defecto
                        $chartIndexId = "7";
                        $range = 2;
                        break;
                }
                break;
        }
        if ($full_script != null) {
            return [
                'id' => $id,
                'date' => $date,
                'full_script' => $full_script
            ];
        }

        // Si todo está presente, puedes proceder con el uso de las variables
        return [
            'id' => $id,
            'date' => $date,
            'range' => $range,
            'chartIndexId' => $chartIndexId,
            'isDetailFull' => "",
        ];
    }
    //acceso graficas custom de SolarEdge
    public function getEnergyDashBoardCuerpo()
    {
        // Obtén los datos JSON del cuerpo de la solicitud POST
        $json = file_get_contents('php://input');

        // Decodifica el JSON en un array o un objeto PHP
        $data = json_decode($json, true); // El segundo parámetro true convierte el JSON a un array asociativo

        // Verifica si los datos fueron decodificados correctamente
        if ($data === null) {
            return null;
        }

        // Verifica si las claves existen en el array
        $timeUnit = isset($data['dia']) ? $data['dia'] : null;
        $fieldId = isset($data['id']) ? $data['id'] : null;
        $startTime = isset($data['fechaInicio']) ? $data['fechaInicio'] : null;
        $endTime = isset($data['fechaFin']) ? $data['fechaFin'] : null;
        // Si alguna de las claves no existe, retorna null
        if ($fieldId === null || $timeUnit === null) {
            return null;
        }
        // Si todo está presente, puedes proceder con el uso de las variables
        return [
            'timeUnit' => $timeUnit,
            'siteId' => $fieldId,
            'endTime' => isset($endTime) ? $endTime : null,
            'startTime' => isset($startTime) ? $startTime : null
        ];
    }
    /**
     * Función privada para decodificar respuestas JSON con posible doble codificación
     */
    private function decodeJsonResponse($response)
    {
        $decodedData = json_decode($response, true);

        if (is_string($decodedData)) {
            $decodedData = json_decode($decodedData, true);
        }

        return $decodedData;
    }
}
