<?php
require_once __DIR__ . '/../controllers/SolarEdgeController.php';
require_once __DIR__ . '/../controllers/GoodWeController.php';
require_once __DIR__ . '/../controllers/VictronEnergyController.php';
require_once __DIR__ . '/../controllers/SungrowController.php';
require_once __DIR__ . '/../controllers/SigenergyController.php';
require_once __DIR__ . '/../controllers/usuarios.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../utils/SigenergyErrores.php';
require_once __DIR__ . '/../DBObjects/plantasAsociadasDB.php';
require_once __DIR__ . '/GeocodificadorService.php';


class ApiControladorService
{
    private $solarEdgeController;
    private $goodWeController;
    private $victronEnergyController;
    private $sungrowController;
    private $sigenergyController;
    private $logsController;

    public function __construct()
    {
        $this->logsController = new LogsController();
        $this->solarEdgeController = new SolarEdgeController();
        $this->victronEnergyController = new VictronEnergyController;
        $this->goodWeController = new GoodWeController();
        $this->sungrowController = new SungrowController();
        $this->sigenergyController = new SigenergyController();
    }
    /**
     * 
     * Estas funcion son genericas para todos los proveedores
     * 
     */
    public function getAllPlants($devolver = false)
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

            // Obtener datos de Sungrow
            $sungrowResponse = $this->sungrowController->getAllPlants();
            $sungrowData = json_decode($sungrowResponse, true);

            // Obtener datos de Sigenergy
            $sigenergyResponse = $this->sigenergyController->getAllPlants();
            $sigenergyData = json_decode($sigenergyResponse, true);

            $plants = $this->processPlants($goodWeData, $solarEdgeData, $victronEnergyData, $sungrowData, $sigenergyData);

            //si devolver es true entonces devolvemos todas las plantas para que podamos hacer nuestros calculos o otras cosas
            if ($devolver) {
                return $plants;
            }

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

    public function BulkApiFleetEnergy($time,$startTime,$endTime,$arrayEnteros)
    {
        $respuesta = new Respuesta;
        try {

            $solarEdgeResponse = $this->solarEdgeController->BulkApiFleetEnergy($time,$startTime,$endTime,$arrayEnteros);

            $solarEdgeData = json_decode($solarEdgeResponse);


            if ($solarEdgeData != null) {
                $respuesta->success($solarEdgeData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado Datos en la petición o la peticion es nula");
                $respuesta->_400($solarEdgeData);
                $respuesta->message = "No se han encontrado Datos en la petición o la peticion es nula";
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

    public function GetPowerStationWariningInfoByMultiCondition($pageIndex = 1, $pageSize = 2000, $status = 3)
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
     * Las alarmas de GoodWe de UN cliente.
     *
     * El endpoint de GoodWe no acepta una planta: devuelve las alarmas de todo
     * el parque. Para un cliente eso son, casi todas, instalaciones ajenas, asi
     * que se piden todas y se descartan las que no son suyas antes de
     * responder — el filtro vive aqui, en el servidor, no en el frontend.
     *
     * @param array $idsPropias Mapa planta_id => true de sus instalaciones.
     */
    public function alarmasGoodWeDelUsuario($pageIndex, $pageSize, $status, array $idsPropias)
    {
        $respuesta = new Respuesta;
        try {
            $goodWeResponse = $this->goodWeController->GetPowerStationWariningInfoByMultiCondition($pageIndex, $pageSize, $status);
            $goodWeData = json_decode($goodWeResponse, true);

            if ($goodWeData === null) {
                $this->logsController->registrarLog(Logs::INFO, "No se han encontrado alarmas en GoodWe");
                $respuesta->_400($goodWeData);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode($respuesta);
                return;
            }

            // La lista viaja en data.data.list; si GoodWe cambia la forma, se
            // devuelve vacio en lugar de arriesgarse a enseñar de mas.
            $lista = $goodWeData['data']['list'] ?? null;
            if (!is_array($lista)) {
                $lista = [];
            }

            $suyas = [];
            foreach ($lista as $alarma) {
                $planta = (string) ($alarma['stationId'] ?? '');
                if ($planta !== '' && isset($idsPropias[$planta])) {
                    $suyas[] = $alarma;
                }
            }

            $goodWeData['data']['list'] = $suyas;
            $respuesta->success($goodWeData);
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de GoodWe");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }

    /**
     *
     * Estas funcion proporcionan informacion sobre el equipo
     *
     */
    //VictronEnergy
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
    //Sungrow
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
                if(isset($data['overallstats']) && $data['overallstats'] == true){
                    $victronEnergyResponse = $this->victronEnergyController->getGraficoDetailsOverallstats($data);
                }else{
                    $victronEnergyResponse = $this->victronEnergyController->getGraficoDetails($data);
                }
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
    public function getGraficasSungrow()
    {
        $respuesta = new Respuesta;
        try {
            $data = $this->getCuerpoGraficaSungrow();
            if ($data === null) {
                $this->logsController->registrarLog(Logs::INFO, "Faltan parametros para la grafica de Sungrow");
                $respuesta->_400();
                $respuesta->message = "revisa los parametros (id obligatorio)";
                http_response_code(400);
                echo json_encode($respuesta);
                return;
            }
            $sungrowResponse = $this->sungrowController->getGraficas($data);
            $sungrowData = json_decode($sungrowResponse, true);

            if ($sungrowData != null && !isset($sungrowData['error'])) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las graficas de Sungrow");
                $respuesta->success($sungrowData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las graficas de Sungrow");
                $respuesta->_400($sungrowData);
                $respuesta->message = "No se han encontrado graficas de Sungrow";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de Sungrow");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getGraficasSigenergy()
    {
        $respuesta = new Respuesta;
        try {
            $data = $this->getCuerpoGraficaSigenergy();
            if ($data === null) {
                $this->logsController->registrarLog(Logs::INFO, "Faltan parametros para la grafica de Sigenergy");
                $respuesta->_400();
                $respuesta->message = "revisa los parametros (id obligatorio)";
                http_response_code(400);
                echo json_encode($respuesta);
                return;
            }
            $sigenergyResponse = $this->sigenergyController->getGraficas($data);
            $sigenergyData = json_decode($sigenergyResponse, true);

            if ($this->fallaSigenergy($respuesta, $sigenergyData)) return;

            if ($sigenergyData != null && !isset($sigenergyData['error'])) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las graficas de Sigenergy");
                $respuesta->success($sigenergyData);
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las graficas de Sigenergy");
                $respuesta->_400($sigenergyData);
                $respuesta->message = "No se han encontrado graficas de Sigenergy";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de Sigenergy");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    /**
     * Traduce un error de Sigenergy a nuestra respuesta y lo emite. Devuelve true si
     * habia error (y por tanto ya no hay que seguir), false si la respuesta era buena.
     *
     * Existe porque Sigenergy contesta SIEMPRE HTTP 200 y mete el fallo real en `code`.
     * Sin esto, un "Station not permitted" salia como 200 status=true con data vacio y
     * el frontend no podia distinguir "no hay datos" de "esta planta no es tuya".
     */
    private function fallaSigenergy($respuesta, $datos)
    {
        // Sin envoltorio `code` no hay nada que traducir (p.ej. el detalle de planta,
        // que devuelve el objeto pelado).
        if (!is_array($datos) || !array_key_exists('code', $datos)) return false;
        if (SigenergyErrores::esExito($datos)) return false;

        $e = SigenergyErrores::deRespuesta($datos);
        $respuesta->status = false;
        $respuesta->code = $e['http'];
        $respuesta->message = $e['mensaje'];
        $respuesta->data = [
            'proveedor' => 'Sigenergy',
            'codigo_sigenergy' => $e['codigo'],
            'msg_sigenergy' => $datos['msg'] ?? null,
            'causa' => $e['causa'],
            'reintentable' => $e['transitorio'],
            'documentado' => $e['documentado'],
        ];
        // El bloque de cache se mantiene: en un 1201 es justo lo que dice cuanto esperar.
        if (isset($datos['_cache'])) $respuesta->data['_cache'] = $datos['_cache'];

        $this->logsController->registrarLog(
            $e['transitorio'] ? Logs::ERROR : Logs::INFO,
            "Sigenergy code={$e['codigo']} ({$datos['msg']}): {$e['causa']}"
        );
        http_response_code($e['http']);
        header('Content-Type: application/json');
        echo json_encode($respuesta);
        return true;
    }



    /**
     *
     * Estas funciones se utilizan para obtener los datos de Todas las plantas de cada proveedor
     *
     */
    public function getAllPlantsGoodWe($page = 1, $pageSize = 2000)
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
    public function getAllPlantsSolarEdge($page = 1, $pageSize = 2000)
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
    public function getAllPlantsVictronEnergy($page = 1, $pageSize = 2000)
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
    public function getAllPlantsSungrow($page = 1, $pageSize = 2000)
    {
        $respuesta = new Paginacion();
        try {
            $sungrowResponse = $this->sungrowController->getAllPlants($page, $pageSize);
            $sungrowData = json_decode($sungrowResponse, true);

            $plants = $this->processPlants([], [], [], $sungrowData);

            if ($plants != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en Sungrow");
                $respuesta->success($plants);
                $respuesta->page = $page;
                $respuesta->limit = $pageSize;
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las plantas en Sungrow");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de Sungrow");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
    public function getAllPlantsSigenergy($page = 1, $pageSize = 2000)
    {
        $respuesta = new Paginacion();
        try {
            $sigenergyResponse = $this->sigenergyController->getAllPlants($page, $pageSize);
            $sigenergyData = json_decode($sigenergyResponse, true);

            if ($this->fallaSigenergy($respuesta, $sigenergyData)) return;

            $plants = $this->processPlants([], [], [], [], $sigenergyData);

            if ($plants != null) {
                $this->logsController->registrarLog(Logs::INFO, "se han encontrado las plantas en Sigenergy");
                $respuesta->success($plants);
                $respuesta->page = $page;
                $respuesta->limit = $pageSize;
                // Info de cache/rate-limit: la lista de Sigenergy solo se puede refrescar
                // 1 vez cada 5 min POR CUENTA, asi que el frontend necesita saber cuanto
                // falta para que tenga sentido volver a pedirla.
                if (isset($sigenergyData['_cache'])) {
                    $respuesta->cache = $sigenergyData['_cache'];
                }
            } else {
                $this->logsController->registrarLog(Logs::INFO, "no se han encontrado las plantas en Sigenergy");
                $respuesta->_400($plants);
                $respuesta->message = "No se han encontrado plantas";
                http_response_code(400);
            }
        } catch (Throwable $e) {
            $this->logsController->registrarLog(Logs::ERROR, $e->getMessage() . "Error en el servidor de Sigenergy");
            $respuesta->_500();
            $respuesta->message = "Error en el servidor de algun proveedor";
            http_response_code(500);
        }
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
                $respuesta->success();
                $respuesta->message = 'No se han encontrado plantas para este usuario';
                http_response_code(200);
                echo json_encode($respuesta);
                return;
            }

            $goodWeArray = [];
            $solarEdgeArray = [];
            $victronEnergyArray = [];
            $sungrowArray = [];
            $sigenergyArray = [];

            foreach ($plantasAsociadas as $planta) {
                if ($planta['nombre_proveedor'] === 'GoodWe') {
                    // Obtener y decodificar datos de GoodWe
                    $goodWeResponse = $this->goodWeController->getPlantDetails($planta['planta_id']);
                    $goodWeData = $this->decodeJsonResponse($goodWeResponse);

                    if (is_array($goodWeData) && isset($goodWeData['data']['info']['powerstation_id'])) {
                        // Las COORDENADAS solo vienen en el listado, no en el
                        // detalle: `getPlantDetails` no trae latitude/longitude
                        // ni en la raiz ni dentro de data.info (comprobado en
                        // produccion, ambas null). Como esta rama es la del
                        // cliente y solo pide detalles, el mapa se quedaba sin
                        // una sola ubicacion mientras el del admin — que lee el
                        // listado — las pintaba todas.
                        $coords = $this->coordenadasGoodWe($planta['planta_id']);
                        if ($coords) {
                            $goodWeData['latitude']  = $coords['lat'];
                            $goodWeData['longitude'] = $coords['lng'];
                        }

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
                if ($planta['nombre_proveedor'] === 'Sungrow') {
                    // Tiempo real (item de la lista con potencia/energia) por ps_id
                    $sungrowResponse = $this->sungrowController->getPlantPowerRealtime($planta['planta_id']);
                    $sungrowData = $this->decodeJsonResponse($sungrowResponse);

                    if (is_array($sungrowData) && isset($sungrowData['ps_id'])) {
                        $sungrowArray[$sungrowData['ps_id']] = $sungrowData;
                    }
                }
                if ($planta['nombre_proveedor'] === 'Sigenergy') {
                    // Detalle (registro de la lista oficial) por systemId
                    $sigenergyResponse = $this->sigenergyController->getPlantDetails($planta['planta_id']);
                    $sigenergyData = $this->decodeJsonResponse($sigenergyResponse);

                    if (is_array($sigenergyData) && isset($sigenergyData['systemId'])) {
                        $sigenergyArray[$sigenergyData['systemId']] = $sigenergyData;
                    }
                }
            }

            // Convertir los arrays asociativos en arrays simples para procesarlos
            $goodWeArray = array_values($goodWeArray);
            $solarEdgeArray = array_values($solarEdgeArray);
            $victronEnergyArray = array_values($victronEnergyArray);
            $sungrowArray = array_values($sungrowArray);
            $sigenergyArray = array_values($sigenergyArray);


            $processedPlants = $this->processPlantsCliente($goodWeArray, $solarEdgeArray, $victronEnergyArray, $sungrowArray, $sigenergyArray);
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
            } elseif ($proveedor === $proveedores['Sungrow']) {
                // Obtener datos de Sungrow
                $sungrowResponse = $this->sungrowController->getPlantDetails($id);
                $plants = $sungrowResponse;
            } elseif ($proveedor === $proveedores['Sigenergy']) {
                // Obtener datos de Sigenergy
                $sigenergyResponse = $this->sigenergyController->getPlantDetails($id);
                $plants = $sigenergyResponse;
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

                if($proveedor == $proveedores['VictronEnergy']){
                    // Obtener datos de VictronEnergy
                    $victronEnergyResponse = $this->victronEnergyController->getSiteDetails($idPlanta);
                    $victronEnergyData = json_decode($victronEnergyResponse, true);
                }else{
                    $victronEnergyData = "";
                }

                if ($proveedor == $proveedores['Sungrow']) {
                    // Obtener datos de Sungrow
                    $sungrowResponse = $this->sungrowController->getPlantDetails($idPlanta);
                    $sungrowData = json_decode($sungrowResponse, true);
                } else {
                    $sungrowData = "";
                }

                if ($proveedor == $proveedores['Sigenergy']) {
                    // Obtener datos de Sigenergy
                    $sigenergyResponse = $this->sigenergyController->getPlantDetails($idPlanta);
                    $sigenergyData = json_decode($sigenergyResponse, true);
                } else {
                    $sigenergyData = "";
                }

                $plants = null;

                if ($proveedor == $proveedores['GoodWe']) {
                    $plants = $goodWeData;
                } else if ($proveedor == $proveedores['SolarEdge']) {
                    $plants = $solarEdgeData;
                } else if ($proveedor == $proveedores['VictronEnergy']){
                    $plants = $victronEnergyData;
                } else if ($proveedor == $proveedores['Sungrow']){
                    $plants = $sungrowData;
                } else if ($proveedor == $proveedores['Sigenergy']){
                    $plants = $sigenergyData;
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
    public function processPlants(array $goodWeData, array $solarEdgeData, array $victronEnergyData, array $sungrowData = [], array $sigenergyData = []): array
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
                    // SolarEdge's LIST reports peakPower as 0.0 for every
                    // site — verified against its own API, where the same
                    // plant's detail says 4.05 — so the real nameplate is
                    // read from the detail. Without it the shared plant table
                    // showed "0 kW" on all 17 SolarEdge rows while the other
                    // providers filled the column.
                    'capacity' => self::capacidadSolarEdge($site, $this->solarEdgeController),
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

        // Procesar datos de Sungrow (getPowerStationList)
        if (isset($sungrowData['result_data']['pageList']) && is_array($sungrowData['result_data']['pageList'])) {
            foreach ($sungrowData['result_data']['pageList'] as $ps) {
                $currPowerKw = isset($ps['curr_power']['value']) ? (float) $ps['curr_power']['value'] : null;
                $plants[] = [
                    'id' => $ps['ps_id'] ?? '',
                    'name' => $ps['ps_name'] ?? '',
                    'address' => $ps['ps_location'] ?? null,
                    'capacity' => isset($ps['total_capcity']['value']) ? (float) $ps['total_capcity']['value'] : 0,
                    'status' => $this->mapSungrowStatus($ps['ps_status'] ?? null),
                    'type' => $ps['ps_type'] ?? '',
                    'latitude' => $ps['latitude'] ?? '',
                    'longitude' => $ps['longitude'] ?? '',
                    'organization' => 'sungrow',
                    'batteryVoltage' => null,
                    'batterySoc' => null,
                    'current_power' => $currPowerKw !== null ? $currPowerKw * 1000 : null, // kW -> W (coherente con GoodWe)
                    'total_energy' => isset($ps['total_energy']['value']) ? (float) $ps['total_energy']['value'] : null,
                    'daily_energy' => isset($ps['today_energy']['value']) ? (float) $ps['today_energy']['value'] : null,
                    'monthly_energy' => null,
                    'installation_date' => $ps['install_date'] ?? null,
                    'pto_date' => null,
                    'notes' => $ps['description'] ?? null,
                    'alert_quantity' => $ps['alarm_count'] ?? null,
                    'highest_impact' => null,
                    'primary_module' => null,
                    'public_settings' => null
                ];
            }
        }

        // Procesar datos de Sigenergy (Openapi oficial: openapi/system -> data[] plano).
        // La lista oficial es mas escueta que el apaño: NO trae lat/lon ni energia
        // diaria por planta (eso solo esta en summary/energyFlow, limitados a 1 acceso
        // por estacion cada 5 min, asi que solo se piden en el detalle de la planta).
        if (isset($sigenergyData['data']) && is_array($sigenergyData['data'])) {
            foreach ($sigenergyData['data'] as $st) {
                if (!is_array($st) || !isset($st['systemId'])) continue;
                $plants[] = [
                    'id' => $st['systemId'] ?? '',
                    'name' => $st['systemName'] ?? '',
                    'address' => $st['addr'] ?? null,
                    'capacity' => $st['pvCapacity'] ?? 0,
                    'status' => $this->mapSigenergyStatus($st['status'] ?? null),
                    'type' => $st['onOffGridStatus'] ?? '',
                    'latitude' => null,  // la Openapi oficial no expone coordenadas
                    'longitude' => null,
                    'organization' => 'sigenergy',
                    'batteryVoltage' => null,
                    'batterySoc' => null,
                    'current_power' => null, // usar /plant/power/realtime (energyFlow) en el detalle
                    'total_energy' => null,
                    'daily_energy' => null,  // usar summary en el detalle (rate-limit)
                    'monthly_energy' => null,
                    // La API real devuelve gridConnectedTime en MILISEGUNDOS (el doc dice
                    // "gridConnectTime" en segundos, pero no es asi: ojo con ese desfase).
                    'installation_date' => isset($st['gridConnectedTime']) ? date('Y-m-d', (int) ($st['gridConnectedTime'] / 1000)) : null,
                    'pto_date' => null,
                    'notes' => null,
                    'alert_quantity' => null, // las alarmas de Sigenergy van por push MQTT
                    'highest_impact' => null,
                    'primary_module' => null,
                    'public_settings' => null
                ];
            }
        }

        return $this->rellenarCoordenadasPorDireccion($plants);
    }
    /**
     * Ultimo recurso para el mapa: si una planta trae DIRECCION pero no
     * coordenadas, se geocodifica la direccion.
     *
     * Los cinco proveedores dan direccion, pero no todos dan latitud y
     * longitud: Sigenergy no las expone en su Openapi y el detalle de GoodWe
     * tampoco las trae. Sin esto, esas plantas no salian en el mapa aunque en
     * la ficha se viera perfectamente su calle.
     *
     * Se aplica a las DOS ramas, admin y cliente, porque el hueco no es de una
     * sola: una planta Sigenergy no tiene coordenadas para nadie.
     *
     * El resultado se cachea en base de datos (ver GeocodificadorService), asi
     * que esto solo cuesta una llamada la primera vez que se ve una direccion.
     */
    private function rellenarCoordenadasPorDireccion(array $plants): array
    {
        $geocodificador = null;

        foreach ($plants as $i => $planta) {
            // Las que ya traen coordenadas del proveedor se dejan intactas: son
            // la posicion real del sitio, no una aproximacion de la calle.
            $tieneLat = isset($planta['latitude']) && $planta['latitude'] !== '' && $planta['latitude'] !== null;
            $tieneLng = isset($planta['longitude']) && $planta['longitude'] !== '' && $planta['longitude'] !== null;
            if ($tieneLat && $tieneLng) { continue; }

            $direccion = $planta['address'] ?? null;
            if (!is_string($direccion) || trim($direccion) === '') { continue; }

            // Se instancia solo si de verdad hace falta: abre conexion a la BD.
            if ($geocodificador === null) {
                $geocodificador = new GeocodificadorService();
            }

            $coordenadas = $geocodificador->coordenadas($direccion);
            if ($coordenadas === null) { continue; }

            $plants[$i]['latitude']  = $coordenadas['lat'];
            $plants[$i]['longitude'] = $coordenadas['lng'];
            // Marcado como aproximado: viene de la calle, no del inversor. El
            // front puede distinguirlo si algun dia quiere dibujarlo distinto.
            $plants[$i]['coordinates_approximate'] = true;
        }

        return $plants;
    }

    /**
     * Coordenadas de una planta GoodWe, sacadas del LISTADO.
     *
     * El detalle no las trae — ni en la raiz ni en data.info — asi que es el
     * unico sitio de donde se pueden leer. Se pide el listado UNA vez por
     * peticion y se guarda en memoria: un cliente con seis plantas haria si no
     * seis llamadas identicas al mismo endpoint.
     */
    private ?array $coordenadasGoodWeCache = null;

    private function coordenadasGoodWe(string $plantaId): ?array
    {
        if ($this->coordenadasGoodWeCache === null) {
            $this->coordenadasGoodWeCache = [];
            try {
                $listado = $this->decodeJsonResponse($this->goodWeController->getAllPlants(1, 200));
                $filas = $listado['data']['list'] ?? $listado['data'] ?? [];

                foreach ((is_array($filas) ? $filas : []) as $fila) {
                    if (!is_array($fila)) { continue; }
                    $id = (string) ($fila['powerstation_id'] ?? '');
                    if ($id === '') { continue; }
                    // GoodWe las manda como CADENA ("28.100486"); se guardan tal
                    // cual y el front ya las normaliza a numero.
                    $this->coordenadasGoodWeCache[$id] = [
                        'lat' => $fila['latitude'] ?? null,
                        'lng' => $fila['longitude'] ?? null,
                    ];
                }
            } catch (Throwable $e) {
                // Sin listado no hay mapa, pero la lista de plantas del cliente
                // tiene que seguir saliendo: un mapa vacio es mucho menos grave
                // que una pantalla sin instalaciones.
                $this->logsController->registrarLog(
                    Logs::WARNING,
                    'No se pudieron leer las coordenadas del listado GoodWe: ' . $e->getMessage()
                );
            }
        }

        return $this->coordenadasGoodWeCache[$plantaId] ?? null;
    }

    //Aquí va la lógica de las apis conversiones etc.. (Lista plantas Cliente)
    public function processPlantsCliente(array $goodWeData, array $solarEdgeData, array $victronEnergyData, array $sungrowData = [], array $sigenergyData = []): array
    {
        $plants = [];

        // Procesar datos de GoodWe
        foreach ($goodWeData as $goodWePlant) {
            $status = $goodWePlant['data']['info']['status'] ?? 'unknown';
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

            $status = $solarEdgePlant['details']['status'] ?? 'unknown';
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

        // Procesar datos de Sungrow (item de getPowerStationList por planta)
        foreach ($sungrowData as $ps) {
            if (!is_array($ps) || !isset($ps['ps_id'])) continue;
            $currPowerKw = isset($ps['curr_power']['value']) ? (float) $ps['curr_power']['value'] : null;
            $plants[] = [
                'id' => $ps['ps_id'] ?? null,
                'name' => $ps['ps_name'] ?? null,
                'address' => $ps['ps_location'] ?? null,
                'capacity' => isset($ps['total_capcity']['value']) ? (float) $ps['total_capcity']['value'] : null,
                'status' => $this->mapSungrowStatus($ps['ps_status'] ?? null),
                'type' => $ps['ps_type'] ?? null,
                'latitude' => $ps['latitude'] ?? null,
                'longitude' => $ps['longitude'] ?? null,
                'organization' => 'sungrow',
                'current_power' => $currPowerKw !== null ? $currPowerKw * 1000 : null, // kW -> W
                'total_energy' => isset($ps['total_energy']['value']) ? (float) $ps['total_energy']['value'] : null,
                'daily_energy' => isset($ps['today_energy']['value']) ? (float) $ps['today_energy']['value'] : null,
                'monthly_energy' => null,
                'installation_date' => $ps['install_date'] ?? null,
                'pto_date' => null,
                'notes' => $ps['description'] ?? null,
                'alert_quantity' => $ps['alarm_count'] ?? null,
                'highest_impact' => null,
                'primary_module' => null,
                'public_settings' => null
            ];
        }

        // Procesar datos de Sigenergy (registro de la lista oficial openapi/system por planta)
        foreach ($sigenergyData as $st) {
            if (!is_array($st) || !isset($st['systemId'])) continue;
            $plants[] = [
                'id' => $st['systemId'] ?? null,
                'name' => $st['systemName'] ?? null,
                'address' => $st['addr'] ?? null,
                'capacity' => $st['pvCapacity'] ?? null,
                'status' => $this->mapSigenergyStatus($st['status'] ?? null),
                'type' => $st['onOffGridStatus'] ?? null,
                'latitude' => null,  // la Openapi oficial no expone coordenadas
                'longitude' => null,
                'organization' => 'sigenergy',
                'current_power' => null,
                'total_energy' => null,
                'daily_energy' => null,  // usar summary en el detalle (rate-limit)
                'monthly_energy' => null,
                'installation_date' => isset($st['gridConnectedTime']) ? date('Y-m-d', (int) ($st['gridConnectedTime'] / 1000)) : null,
                'pto_date' => null,
                'notes' => null,
                'alert_quantity' => null, // alarmas via push MQTT
                'highest_impact' => null,
                'primary_module' => null,
                'public_settings' => null
            ];
        }

        return $this->rellenarCoordenadasPorDireccion($plants);
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
    /**
     * Nameplate capacity for a SolarEdge site, in kWp.
     *
     * Prefers the value the list carries; falls back to the site detail when
     * it is missing or zero. The detail costs one request per site, so it is
     * only paid when the list genuinely has nothing usable.
     */
    private static function capacidadSolarEdge(array $site, $controlador)
    {
        $delListado = (float) ($site['peakPower'] ?? 0);
        if ($delListado > 0) {
            return $delListado;
        }

        $id = $site['id'] ?? null;
        if (!$id || !$controlador || !method_exists($controlador, 'getSiteDetails')) {
            return 0;
        }

        try {
            $detalle = $controlador->getSiteDetails($id);

            // Llega codificado DOS veces: json_decode devuelve otra cadena
            // JSON, no un array, asi que buscar 'details' dentro daba NULL
            // siempre. Se decodifica hasta que deje de ser texto.
            $datos = $detalle;
            for ($i = 0; $i < 3 && is_string($datos); $i++) {
                $datos = json_decode($datos, true);
            }
            if (!is_array($datos)) {
                return 0;
            }

            // El detalle llega anidado de formas distintas segun por donde se
            // pida (`details`, `data.details`, o plano), asi que se buscan
            // todas en vez de asumir una.
            $pico = $datos['details']['peakPower']
                ?? $datos['data']['details']['peakPower']
                ?? $datos['peakPower']
                ?? null;

            return $pico !== null ? (float) $pico : 0;
        } catch (Throwable $e) {
            // Una capacidad ausente no justifica romper el listado entero.
            return 0;
        }
    }

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

    // Función para mapear el estado (ps_status) de Sungrow a una descripción legible
    private function mapSungrowStatus($status)
    {
        switch ((int) $status) {
            case 1:
                return 'working';
            case 0:
                return 'disconnected';
            default:
                return 'unknown';
        }
    }

    // Función para mapear el estado de Sigenergy a una descripción legible.
    // NOTA: códigos tentativos (confirmar con la doc de Sigen). Observado en datos
    // reales: 4 = en producción/normal, 2 = con alarmas/fallo.
    /**
     * Estado de una planta Sigenergy al vocabulario unificado.
     *
     * La API oficial devuelve `status` como STRING (p.ej. "Normal", "Disconnection",
     * "Faulty"), a diferencia del apaño anterior que daba un entero. Mapeamos por
     * texto y dejamos 'unknown' para lo no previsto.
     */
    private function mapSigenergyStatus($status)
    {
        $s = strtolower(trim((string) $status));
        switch ($s) {
            case 'normal':
            case 'ongrid':
            case 'on_grid':
            case 'running':
                return 'working';
            case 'faulty':
            case 'fault':
            case 'error':
            case 'alarm':
                return 'error';
            case 'waiting':
            case 'standby':
                return 'waiting';
            case 'disconnection':
            case 'disconnected':
            case 'offline':
                return 'disconnected';
            default:
                return $s === '' ? 'unknown' : 'unknown';
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
        $fechaInicio = isset($data['fechaInicio']) ? $data['fechaInicio'] : null;
        $fechaFin = isset($data['fechaFin']) ? $data['fechaFin'] : null;
        $overallstats = isset($data['overallstats']) ? $data['overallstats'] : false;
        
        //si overallstats es true y tipo y id no son null se devuelve un array con los datos de tipo overallstats
        if($overallstats === true && $tipo !== null && $id !== null){
            return [
                'id' => $id,
                'overallstats' => $overallstats,
                'type' => $tipo
            ];
        }
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
    //acceso graficas de Sungrow
    public function getCuerpoGraficaSungrow()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if ($data === null) {
            return null;
        }
        $id = $data['id'] ?? null;
        if ($id === null) {
            return null;
        }
        // Panel de graficas:
        //   level    Day|Custom -> intradia por minuto; Week|Month|Year -> agregado.
        //   points   varias medidas (array o csv). Compat: 'point' suelto sigue valiendo.
        //   interval 5|15|30|60 (solo intradia).
        //   date     fecha de referencia para Week/Month/Year (yyyy-mm-dd; hoy por defecto).
        //   fechaInicio/fechaFin (YmdHis) para Custom intradia.
        $cuerpo = [
            'id'       => $id,
            'interval' => $data['interval'] ?? 5,
            'start'    => $data['fechaInicio'] ?? $data['start'] ?? null,
            'end'      => $data['fechaFin'] ?? $data['end'] ?? null,
        ];
        if (isset($data['level']))  $cuerpo['level']  = $data['level'];
        if (isset($data['date']))   $cuerpo['date']   = $data['date'];
        // Solo se pasa 'points' si viene explicito (activa el modo multipunto); si no,
        // se mantiene 'point' suelto para no cambiar la forma de respuesta antigua.
        if (isset($data['points'])) {
            $cuerpo['points'] = $data['points'];
        } else {
            $cuerpo['point'] = $data['point'] ?? 'p24'; // p24 = potencia activa (W)
        }
        return $cuerpo;
    }

    /**
     * Cuerpo de la grafica de Sigenergy (Openapi oficial: history por level+date).
     *
     * Body esperado:
     *   id    obligatorio -> systemId (p.ej. VSSKC1768221900)
     *   level opcional     -> Day | Week | Month | Year | Lifetime (por defecto Day)
     *   date  opcional     -> yyyy-MM-dd (por defecto hoy; se ignora en Lifetime)
     *
     * Acepta tambien 'fecha' como alias de 'date' por comodidad del frontend.
     */
    public function getCuerpoGraficaSigenergy()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if ($data === null) {
            return null;
        }
        $id = $data['id'] ?? null;
        if ($id === null) {
            return null;
        }
        return [
            'id'    => $id,
            'level' => $data['level'] ?? 'Day',
            'date'  => $data['date'] ?? $data['fecha'] ?? date('Y-m-d'),
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
