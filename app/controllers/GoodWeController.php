<?php
require_once __DIR__ . '/../services/GoodWeService.php';
require_once __DIR__ . '/../services/PrecioService.php';
require_once __DIR__ . '/../controllers/LogsController.php';


class GoodWeController
{
    private $goodWeService;
    private $logsController;
    private $precioService;

    public function __construct()
    {
        $this->goodWeService = new GoodWeService();
        $this->logsController = new LogsController();
        $this->precioService = new PrecioService();
    }

    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function GetInverterAllPoint($powerStationId)
    {
        // Registrar el acceso en los logs
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de GoodWe Realtime");

        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->getInverterAllPoint($powerStationId);

        // Configurar el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }

    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getPlantPowerRealtime($powerStationId)
    {
        // Registrar el acceso en los logs
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de GoodWe Realtime");

        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->getPlantPowerRealtime($powerStationId);

        // Configurar el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }

    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getPlantDetails($powerStationId)
    {
        // Registrar el acceso en los logs
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de GoodWe");

        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetPlantDetailByPowerstationId($powerStationId);

        // Asegurar que $result sea un arreglo o JSON válido
        $decodedResult = json_decode($result, true);

        // Verificar si existe el nodo "info" con el campo "status"
        if (isset($decodedResult['data']['powercontrol_status'])) {
            $decodedResult['data']['powercontrol_status'] = $this->mapGoodWeStatus($decodedResult['data']['powercontrol_status']);
        }
        if (isset($decodedResult['data']['info']['status'])) {
            $decodedResult['data']['info']['status'] = $this->mapGoodWeStatus($decodedResult['data']['info']['status']);
        }

        // Añadir el campo "organizacion" al resultado
        $decodedResult['data']['organization'] = "goodwe";

        // Configurar el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        $decodedResult = json_encode($decodedResult);
        // Retornar el objeto modificado como JSON
        return json_encode($decodedResult);
    }

    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getAllPlants($page = 1, $pageSize = 200)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de GoodWe");
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetAllPlants($page, $pageSize);
        // Configura el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }
    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function GetPowerStationWariningInfoByMultiCondition($pageIndex = 1, $pageSize = 200, $status = 3)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de GoodWe");
        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetPowerStationWariningInfoByMultiCondition($pageIndex, $pageSize, $status);
        // Configura el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }
    /**
     * Controlador para obtener los detalles de la planta por ID
     *
     * @param string $powerStationId ID de la planta de energía
     * @return string
     */
    public function getChartByPlants($data)
    {
        $this->logsController->registrarLog(Logs::INFO, "Accede a la API de GoodWe");

        // Llama al servicio para obtener los detalles de la planta
        $result = $this->goodWeService->GetChartByPlant($data);

        // Suponemos que este array lo recuperas desde Zoho o base de datos
        $fechasPrecioReal = $this->precioService->getPreciosPorFechas();

        // Aquí almacenamos el array y le convertimos el precio real del cliente
        $this->precioRealDelCliente($result, $fechasPrecioReal);

        // Configura el tipo de contenido de la respuesta como JSON
        header('Content-Type: application/json');
        return json_encode($result);
    }
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
    //powercontrol_status

    //Hacemos el cambio del precio real del cliente
    private function precioRealDelCliente(&$data, $fechasPrecioReal)
    {
        if (!isset($data['data']['lines'])) {
            $this->logsController->registrarLog(Logs::ERROR, "No hay líneas en el array de datos");
            return;
        }

        $lines = &$data['data']['lines'];

        // Eliminar línea previa de RealIncome si ya existe
        foreach ($lines as $index => $line) {
            if (isset($line['name']) && $line['name'] === 'RealIncome') {
                unset($lines[$index]);
            }
        }

        // Obtener ID de la planta
        $plantaId = null;
        if (isset($data['components']['para'])) {
            $para = json_decode($data['components']['para'], true);
            $plantaId = $para['model']['Id'] ?? null;
            $this->logsController->registrarLog(Logs::INFO, "Planta ID: $plantaId");
        }

        if (!$plantaId) {
            $this->logsController->registrarLog(Logs::ERROR, "No se encontró el ID de planta");
            return;
        }

        // Buscar línea de generación
        $generacion = null;
        foreach ($lines as $line) {
            if (isset($line['name']) && $line['name'] === 'PVGeneration') {
                $generacion = $line;
                break;
            }
        }

        if (!$generacion) {
            $this->logsController->registrarLog(Logs::ERROR, "No se encontró línea PVGeneration");
            return;
        }

        // Obtener la fecha mínima válida
        $fechaMinima = null;
        foreach ($fechasPrecioReal as $rango) {
            if ($rango['planta_id'] === $plantaId) {
                if (!$fechaMinima || $rango['fecha_inicio'] < $fechaMinima) {
                    $fechaMinima = $rango['fecha_inicio'];
                }
            }
        }

        $realIncome = [
            'label' => 'Real Income',
            'name' => 'RealIncome',
            'unit' => 'EUR',
            'isActive' => true,
            'axis' => 1,
            'sort' => 99,
            'type' => '2',
            'frontColor' => '#ff9900',
            'xy' => []
        ];

        foreach ($generacion['xy'] as $punto) {
            $fecha = $punto['x'];
            $kwh = $punto['y'];
            $precio = 0;

            if ($fechaMinima && $fecha < $fechaMinima) {
                $this->logsController->registrarLog(Logs::INFO, "Fecha $fecha es anterior a $fechaMinima → precio 0");
            } else {
                foreach ($fechasPrecioReal as $rango) {
                    if ($rango['planta_id'] !== $plantaId) {
                        continue;
                    }

                    $inicio = $rango['fecha_inicio'];
                    $fin = $rango['fecha_final'] ?: '9999-12-31'; // Si está vacío o null, es hasta el infinito

                    if ($fecha >= $inicio && $fecha <= $fin) {
                        $precio = $rango['precio'];
                        break;
                    }
                }
            }

            $realIncome['xy'][] = [
                'x' => $fecha,
                'y' => round($kwh * $precio, 2),
                'z' => null
            ];
        }

        $data['data']['lines'] = array_values($lines);
        $data['data']['lines'][] = $realIncome;
    }

    //Método para obtener los precios reales de la planta a partir de los datos de zoho
    public function getPlantRealPrice($siteId)
    {
        $this->logsController->registrarLog(Logs::INFO, " accede a la api de solarEdge precio real de planta de solarEdge");
        //Obtenemos los datos de la planta desde el servicio de SolarEdge y personalizamos los precios
        $data = $this->goodWeService->getRealIncomeStats($siteId);
        header('Content-Type: application/json');
        return json_encode($data);
    }
}
