<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/GoodWe.php';
require_once __DIR__ . '/../controllers/ProveedoresController.php';
require_once __DIR__ . '/../DBObjects/proveedoresDB.php';
require_once __DIR__ . '/../services/PrecioService.php';


class GoodWeService
{
    private $goodWe;
    private $httpClient;
    private $proveedoresController;
    private $precioService;
    private $proveedoresDB;

    public function __construct()
    {
        $this->goodWe = new GoodWeTokenAuthentified();
        $this->httpClient = new HttpClient();
        $this->proveedoresController = new ProveedoresController();
        $this->proveedoresDB = new ProveedoresDB();
        $this->precioService = new PrecioService();
    }

    //Llamada en tiempo real a la energia que genera la planta, en postman corresponde con la llamada POST GetPowerFlow
    public function getPlantPowerRealtime($powerStationId)
    {
        $url = GoodWe::$url . "api/v2/PowerStation/GetPowerflow";

        $token = $this->proveedoresController->getTokenProveedor('GoodWe');

        // Token en formato JSON
        $tokenData = [
            'uid' => $this->goodWe->getUid(),
            'timestamp' => $this->goodWe->getTimestamp(),
            'token' => $token['tokenAuth'],
            'client' => $this->goodWe->getClient(),
            'version' => $this->goodWe->getVersion(),
            'language' => $this->goodWe->getLanguage()
        ];

        $headers = [
            'Content-Type: application/json',
            'Token: ' . json_encode($tokenData)
        ];

        $data = [
            'PowerStationId' => $powerStationId
        ];

        try {
            // Realiza la primera solicitud
            $response = $this->httpClient->post($url, $headers, json_encode($data));
            $decodedResponse = json_decode($response, true);



            // Verificar si la respuesta indica que la autorización ha caducado
            while ($decodedResponse['code'] == 100002) {
                // Verificar si la respuesta indica que la autorización ha caducado
                if (isset($decodedResponse['code']) && $decodedResponse['code'] === 100002) {
                    // Realizar login para obtener nuevos datos de autorización
                    $newTokenData = $this->crossLogin();

                    if (isset($newTokenData['uid'])) {
                        // Actualizar los datos del token
                        $this->goodWe->setUid($newTokenData['uid']);
                        $this->goodWe->setTimestamp($newTokenData['timestamp']);
                        $this->goodWe->setToken($newTokenData['token']);

                        // Reintentar la solicitud con los nuevos datos
                        $tokenData['uid'] = $newTokenData['uid'];
                        $tokenData['timestamp'] = $newTokenData['timestamp'];
                        $tokenData['token'] = $newTokenData['token'];
                        $headers[1] = 'Token: ' . json_encode($tokenData);

                        // Segunda solicitud con el nuevo token
                        $response = $this->httpClient->post($url, $headers, json_encode($data));
                        $decodedResponse = json_decode($response, true);
                    } else {
                        throw new Exception("No se pudo obtener el nuevo token de autorización.");
                    }
                }
            }

            return $decodedResponse;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Llamada que se hace para construir el grafico de la planta en postman corresponde con la llamada POST GetPlantPowerChart
    public function GetChartByPlant($data)
    {
        if (isset($data['full_script']) && $data['full_script']) {
            $url = GoodWe::$url . "api/v2/Charts/GetPlantPowerChart";
            if (isset($data['chartIndexId'])) {
                unset($data['chartIndexId']);
            }
        } else {
            $url = GoodWe::$url . "api/v2/Charts/GetChartByPlant";
        }

        $token = $this->proveedoresController->getTokenProveedor('GoodWe');

        // Token en formato JSON
        $tokenData = [
            'uid' => $this->goodWe->getUid(),
            'timestamp' => $this->goodWe->getTimestamp(),
            'token' => $token['tokenAuth'],
            'client' => $this->goodWe->getClient(),
            'version' => $this->goodWe->getVersion(),
            'language' => $this->goodWe->getLanguage()
        ];

        $headers = [
            'Content-Type: application/json',
            'Token: ' . json_encode($tokenData)
        ];

        try {
            // Realiza la primera solicitud
            $response = $this->httpClient->post($url, $headers, json_encode($data));
            $decodedResponse = json_decode($response, true);

            // Verificar si la respuesta indica que la autorización ha caducado
            while ($decodedResponse['code'] == 100002) {
                // Verificar si la respuesta indica que la autorización ha caducado
                if (isset($decodedResponse['code']) && $decodedResponse['code'] === 100002) {
                    // Realizar login para obtener nuevos datos de autorización
                    $newTokenData = $this->crossLogin();

                    if (isset($newTokenData['uid'])) {
                        // Actualizar los datos del token
                        $this->goodWe->setUid($newTokenData['uid']);
                        $this->goodWe->setTimestamp($newTokenData['timestamp']);
                        $this->goodWe->setToken($newTokenData['token']);


                        // Reintentar la solicitud con los nuevos datos
                        $tokenData['uid'] = $newTokenData['uid'];
                        $tokenData['timestamp'] = $newTokenData['timestamp'];
                        $tokenData['token'] = $newTokenData['token'];
                        $headers[1] = 'Token: ' . json_encode($tokenData);

                        // Segunda solicitud con el nuevo token
                        $response = $this->httpClient->post($url, $headers, json_encode($data));
                        $decodedResponse = json_decode($response, true);
                    } else {
                        throw new Exception("No se pudo obtener el nuevo token de autorización.");
                    }
                }
            }

            return $decodedResponse;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getRealIncomeStats($plantId)
    {
        $today = new DateTime();
        $fechaHoy = $today->format('Y-m-d');
        $fechaInicioMes = $today->format('Y-m-01');
        $fechaFinMes = $today->format('Y-m-t');

        // Obtener rangos de precios personalizados
        $rangos = $this->precioService->getPreciosPersonalizadosPorPlanta($plantId, "goodwe");

        if (empty($rangos)) {
            return [];
        }

        // Determinar la fecha de inicio más antigua
        $fechaInicioPlanta = null;
        foreach ($rangos as $rango) {
            $fechaInicio = $rango['fecha_inicio'];
            if ($fechaInicioPlanta === null || strtotime($fechaInicio) < strtotime($fechaInicioPlanta)) {
                $fechaInicioPlanta = $fechaInicio;
            }
        }

        // Recorrer hacia atrás en bloques de 30 días
        $fechaLimite = new DateTime($fechaHoy);
        $datosAcumulados = [];

        while (true) {
            $data = [
                'id' => $plantId,
                'date' => $fechaLimite->format('Y-m-d'),
                'range' => '2',
                'chartIndexId' => '3',
                'isDetailFull' => false
            ];

            $energiaData = $this->GetChartByPlant($data);
            $datosAcumulados[] = $energiaData;

            $fechaLimite->modify('-30 days');

            if ($fechaLimite < new DateTime($fechaInicioPlanta)) {
                break;
            }
        }

        // Inicializar totales
        $totalEnergia = $totalIngreso = $totalAhorro = 0;
        $mesEnergia = $mesIngreso = $mesAhorro = 0;
        $hoyEnergia = $hoyIngreso = $hoyAhorro = 0;

        // Procesar los datos acumulados
        $fechaInicioPlantaObj = new DateTime($fechaInicioPlanta);

        foreach ($datosAcumulados as $respuesta) {
            if (!isset($respuesta['data']['lines'])) continue;

            foreach ($respuesta['data']['lines'] as $linea) {
                if (!isset($linea['xy'])) continue;

                foreach ($linea['xy'] as $punto) {
                    $fechaPunto = $punto['x'];
                    $valor = $punto['y'];

                    if ($valor === null) continue;

                    $fechaPuntoObj = new DateTime($fechaPunto);
                    if ($fechaPuntoObj < $fechaInicioPlantaObj) continue;

                    $rangoPrecio = $this->obtenerPrecioPorFecha($fechaPunto, $rangos, $fechaInicioPlanta);

                    if ($linea['name'] === 'PVGeneration') {
                        $totalEnergia += $valor;
                        $totalIngreso += $valor * $rangoPrecio['precio'];
                        $totalAhorro += $valor * $rangoPrecio['precio_ahorro'];

                        if ($fechaPunto >= $fechaInicioMes && $fechaPunto <= $fechaFinMes) {
                            $mesEnergia += $valor;
                            $mesIngreso += $valor * $rangoPrecio['precio'];
                            $mesAhorro += $valor * $rangoPrecio['precio_ahorro'];
                        }

                        if ($fechaPunto === $fechaHoy) {
                            $hoyEnergia += $valor;
                            $hoyIngreso += $valor * $rangoPrecio['precio'];
                            $hoyAhorro += $valor * $rangoPrecio['precio_ahorro'];
                        }
                    }
                }
            }
        }


        // Devolver resultado final
        return [
            'planta_id' => (int) $plantId,
            'moneda' => 'EUR', // puedes cambiarlo si algún rango lo especifica
            'total' => [
                'fecha_inicio' => $fechaInicioPlanta,
                'fecha_final' => $fechaHoy,
                'energia_kwh' => round($totalEnergia, 2),
                'ingreso' => round($totalIngreso, 2),
                'ahorro' => round($totalAhorro, 2)
            ],
            'mes_actual' => [
                'fecha_inicio' => $fechaInicioMes,
                'fecha_final' => $fechaFinMes,
                'energia_kwh' => round($mesEnergia, 2),
                'ingreso' => round($mesIngreso, 2),
                'ahorro' => round($mesAhorro, 2)
            ],
            'hoy' => [
                'fecha_inicio' => $fechaHoy,
                'fecha_final' => $fechaHoy,
                'energia_kwh' => round($hoyEnergia, 2),
                'ingreso' => round($hoyIngreso, 2),
                'ahorro' => round($hoyAhorro, 2)
            ]
        ];
    }



    private function obtenerPrecioPorFecha($fechaPunto, $rangos, $fechaInicioPlanta)
    {
        if ($fechaPunto < $fechaInicioPlanta) return 0;

        foreach ($rangos as $rango) {
            $inicio = $rango['fecha_inicio'];
            $fin = $rango['fecha_final'] ?? date('Y-m-d');
            if ($fechaPunto >= $inicio && $fechaPunto <= $fin) {
                return $rango;
            }
        }

        return 0;
    }

    //LLamada a todas las plantas en postman corresponde a la llamada POST Todas plantas
    public function GetAllPlants($page = 1, $pageSize = 200)
    {
        $url = GoodWe::$url . "api/PowerStationMonitor/QueryPowerStationMonitor";

        $token = $this->proveedoresController->getTokenProveedor('GoodWe');

        // Token en formato JSON
        $tokenData = [
            'uid' => $this->goodWe->getUid(),
            'timestamp' => $this->goodWe->getTimestamp(),
            'token' => $token['tokenAuth'],
            'client' => $this->goodWe->getClient(),
            'version' => $this->goodWe->getVersion(),
            'language' => $this->goodWe->getLanguage()
        ];

        $headers = [
            'Content-Type: application/json',
            'Token: ' . json_encode($tokenData)
        ];

        $data = [
            "key" => "",
            "orderby" => "",
            "powerstation_type" => "",
            "powerstation_status" => "",
            "page_index" => $page,
            "page_size" => $pageSize,
            "adcode" => "",
            "org_id" => "",
            "condition" => ""
        ];

        try {
            // Realiza la primera solicitud
            $response = $this->httpClient->post($url, $headers, json_encode($data));
            $decodedResponse = json_decode($response, true);

            // Verificar si la respuesta indica que la autorización ha caducado
            while ($decodedResponse['code'] == 100002) {
                // Verificar si la respuesta indica que la autorización ha caducado
                if (isset($decodedResponse['code']) && $decodedResponse['code'] === 100002) {
                    // Realizar login para obtener nuevos datos de autorización
                    $newTokenData = $this->crossLogin();

                    if (isset($newTokenData['uid'])) {
                        // Actualizar los datos del token
                        $this->goodWe->setUid($newTokenData['uid']);
                        $this->goodWe->setTimestamp($newTokenData['timestamp']);
                        $this->goodWe->setToken($newTokenData['token']);


                        // Reintentar la solicitud con los nuevos datos
                        $tokenData['uid'] = $newTokenData['uid'];
                        $tokenData['timestamp'] = $newTokenData['timestamp'];
                        $tokenData['token'] = $newTokenData['token'];
                        $headers[1] = 'Token: ' . json_encode($tokenData);

                        // Segunda solicitud con el nuevo token
                        $response = $this->httpClient->post($url, $headers, json_encode($data));
                        $decodedResponse = json_decode($response, true);
                    } else {
                        throw new Exception("No se pudo obtener el nuevo token de autorización.");
                    }
                }
            }

            return $decodedResponse;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //LLamada a todas las plantas en postman corresponde a la llamada POST GetPowerStationWariningInfoByMultiCondition
    public function GetPowerStationWariningInfoByMultiCondition($pageIndex = 1, $pageSize = 200, $status = 3)
    {
        $url = GoodWe::$url . "api/SmartOperateMaintenance/GetPowerStationWariningInfoByMultiCondition";

        $token = $this->proveedoresController->getTokenProveedor('GoodWe');

        // Token en formato JSON
        $tokenData = [
            'uid' => $this->goodWe->getUid(),
            'timestamp' => $this->goodWe->getTimestamp(),
            'token' => $token['tokenAuth'],
            'client' => $this->goodWe->getClient(),
            'version' => $this->goodWe->getVersion(),
            'language' => $this->goodWe->getLanguage()
        ];

        $headers = [
            'Content-Type: application/json',
            'Token: ' . json_encode($tokenData)
        ];
        //Generar la fecha de inicio y fin por ejemplo si hoy es 24/11/2024 la fecha de inicio seria 11/24/2024 00:00:00 y la fecha de fin seria 12/23/2024 23:59:59
        $fechaFin = date('m/d/Y') . ' 23:59:59';
        $fechaInicio = date('m/d/Y', strtotime('-1 month +1 day')) . ' 00:00:00';

        $data = [
            "adcode" => "",
            "township" => "",
            "orgid" => "",
            "stationid" => "",
            "warninglevel" => 7,
            "status" => $status,
            "starttime" => $fechaInicio,
            "endtime" => $fechaFin,
            "page_size" => $pageSize,
            "page_index" => $pageIndex,
            "device_type" => [],
            "fault_classification" => [],
            "standard_faultLevel" => []
        ];

        try {
            // Realiza la primera solicitud
            $response = $this->httpClient->post($url, $headers, json_encode($data));
            $decodedResponse = json_decode($response, true);

            // Verificar si la respuesta indica que la autorización ha caducado
            while ($decodedResponse['code'] == 100002) {
                // Verificar si la respuesta indica que la autorización ha caducado
                if (isset($decodedResponse['code']) && $decodedResponse['code'] === 100002) {
                    // Realizar login para obtener nuevos datos de autorización
                    $newTokenData = $this->crossLogin();

                    if (isset($newTokenData['uid'])) {
                        // Actualizar los datos del token
                        $this->goodWe->setUid($newTokenData['uid']);
                        $this->goodWe->setTimestamp($newTokenData['timestamp']);
                        $this->goodWe->setToken($newTokenData['token']);


                        // Reintentar la solicitud con los nuevos datos
                        $tokenData['uid'] = $newTokenData['uid'];
                        $tokenData['timestamp'] = $newTokenData['timestamp'];
                        $tokenData['token'] = $newTokenData['token'];
                        $headers[1] = 'Token: ' . json_encode($tokenData);

                        // Segunda solicitud con el nuevo token
                        $response = $this->httpClient->post($url, $headers, json_encode($data));
                        $decodedResponse = json_decode($response, true);
                    } else {
                        throw new Exception("No se pudo obtener el nuevo token de autorización.");
                    }
                }
            }

            return $decodedResponse;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //LLamada a los detalles e la planta en postman corresponde con la llamada POST GetPlantDetailByPowerstation
    public function GetPlantDetailByPowerstationId($powerStationId)
    {
        $url = GoodWe::$url . "api/v3/PowerStation/GetPlantDetailByPowerstationId";

        $token = $this->proveedoresController->getTokenProveedor('GoodWe');

        // Token en formato JSON
        $tokenData = [
            'uid' => $this->goodWe->getUid(),
            'timestamp' => $this->goodWe->getTimestamp(),
            'token' => $token['tokenAuth'],
            'client' => $this->goodWe->getClient(),
            'version' => $this->goodWe->getVersion(),
            'language' => $this->goodWe->getLanguage()
        ];

        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Token: ' . json_encode($tokenData)
        ];

        $data = [
            'powerStationId' => $powerStationId
        ];

        try {
            // Realiza la primera solicitud
            $response = $this->httpClient->get($url, $headers, $data);
            $decodedResponse = json_decode($response, true);

            //Si todo esta correcto devolvemos los datos
            if ($decodedResponse['code'] != 100002) {
                return $response;
            }

            // Verificar si la respuesta indica que la autorización ha caducado
            while ($decodedResponse['code'] == 100002) {
                if (isset($decodedResponse['code']) && $decodedResponse['code'] === 100002) {
                    // Realizar login para obtener nuevos datos de autorización
                    $newTokenData = $this->crossLogin();

                    if (isset($newTokenData['uid'])) {
                        // Actualizar los datos del token
                        $this->goodWe->setUid($newTokenData['uid']);
                        $this->goodWe->setTimestamp($newTokenData['timestamp']);
                        $this->goodWe->setToken($newTokenData['token']);


                        // Reintentar la solicitud con los nuevos datos
                        $tokenData['uid'] = $newTokenData['uid'];
                        $tokenData['timestamp'] = $newTokenData['timestamp'];
                        $tokenData['token'] = $newTokenData['token'];
                        $headers[1] = 'Token: ' . json_encode($tokenData);

                        // Segunda solicitud con el nuevo token
                        $response = $this->httpClient->get($url, $headers, $data);
                        return $response;
                    } else {
                        throw new Exception("No se pudo obtener el nuevo token de autorización.");
                    }
                }
            }

            return $decodedResponse;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //LLamada a los detalles e la planta en postman corresponde con la llamada POST GetInverterAllPoint
    public function GetInverterAllPoint($powerStationId)
    {
        $url = GoodWe::$url . "api/v3/PowerStation/GetInverterAllPoint";
        $token = $this->proveedoresController->getTokenProveedor('GoodWe');

        // Token en formato JSON
        $tokenData = [
            'uid' => $this->goodWe->getUid(),
            'timestamp' => $this->goodWe->getTimestamp(),
            'token' => $token['tokenAuth'],
            'client' => $this->goodWe->getClient(),
            'version' => $this->goodWe->getVersion(),
            'language' => $this->goodWe->getLanguage()
        ];

        $headers = [
            'Content-Type: application/json',
            'Token: ' . json_encode($tokenData)
        ];

        $data = [
            'PowerStationId' => $powerStationId
        ];

        try {
            // Realiza la primera solicitud
            $response = $this->httpClient->post($url, $headers, json_encode($data));
            $decodedResponse = json_decode($response, true);



            // Verificar si la respuesta indica que la autorización ha caducado
            while ($decodedResponse['code'] == 100002) {
                // Verificar si la respuesta indica que la autorización ha caducado
                if (isset($decodedResponse['code']) && $decodedResponse['code'] === 100002) {
                    // Realizar login para obtener nuevos datos de autorización
                    $newTokenData = $this->crossLogin();

                    if (isset($newTokenData['uid'])) {
                        // Actualizar los datos del token
                        $this->goodWe->setUid($newTokenData['uid']);
                        $this->goodWe->setTimestamp($newTokenData['timestamp']);
                        $this->goodWe->setToken($newTokenData['token']);

                        // Reintentar la solicitud con los nuevos datos
                        $tokenData['uid'] = $newTokenData['uid'];
                        $tokenData['timestamp'] = $newTokenData['timestamp'];
                        $tokenData['token'] = $newTokenData['token'];
                        $headers[1] = 'Token: ' . json_encode($tokenData);

                        // Segunda solicitud con el nuevo token
                        $response = $this->httpClient->post($url, $headers, json_encode($data));
                        $decodedResponse = json_decode($response, true);
                    } else {
                        throw new Exception("No se pudo obtener el nuevo token de autorización.");
                    }
                }
            }

            return $decodedResponse;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //LoginUser en postman corresponde con la llamada POST LoginUser
    public function crossLogin()
    {
        $url = GoodWe::$url . "api/v1/Common/CrossLogin";

        $data = [
            'account' => GoodWe::$account,
            'pwd' => GoodWe::$pwd
        ];

        $headers = [
            'Content-Type: application/json',
            'Token: ' . json_encode([
                'version' => 'v2.1.0',
                'client' => 'ios',
                'language' => 'en'
            ])
        ];

        try {
            $response = $this->httpClient->post($url, $headers, json_encode($data));
            $responseData = json_decode($response, true);

            if (isset($responseData['hasError']) && $responseData['hasError'] === false && isset($responseData['data'])) {
                $token = $responseData['data']['token'] ?? '';
                $expires_at = $responseData['data']['timestamp'] ?? '';

                // Verificar si ya hay token en la DB
                $tokenId = $this->proveedoresDB->verificarTokenProveedor('GoodWe');

                $ok = false;
                if ($tokenId) {
                    $ok = $this->proveedoresDB->actualizarToken($tokenId, $token, '', $expires_at);
                } else {
                    $ok = $this->proveedoresDB->insertarTokenYAsociar('GoodWe', $token, '', $expires_at);
                }

                if (!$ok) {
                    error_log("Error al guardar el token en la base de datos.");
                }

                // ✅ ACTUALIZAR OBJETO goodWe
                $this->goodWe->setUid($responseData['data']['uid'] ?? '');
                $this->goodWe->setTimestamp($expires_at);
                $this->goodWe->setToken($token);

                // ✅ Devolver tokenData completo
                return [
                    'uid' => $responseData['data']['uid'] ?? '',
                    'timestamp' => $expires_at,
                    'token' => $token,
                    'token_renovation' => '',
                    'expires_at' => $expires_at,
                    'client' => $responseData['data']['client'] ?? 'ios',
                    'version' => $responseData['data']['version'] ?? 'v2.1.0',
                    'language' => $responseData['data']['language'] ?? 'en'
                ];
            } else {
                throw new Exception("Login fallido: " . ($responseData['msg'] ?? 'Error desconocido'));
            }
        } catch (Exception $e) {
            error_log("Error en crossLogin: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
