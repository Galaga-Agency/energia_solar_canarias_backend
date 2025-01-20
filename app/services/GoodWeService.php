<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/GoodWe.php';


class GoodWeService
{
    private $goodWe;
    private $httpClient;
    private $proveedoresController;

    public function __construct()
    {
        $this->goodWe = new GoodWeTokenAuthentified();
        $this->httpClient = new HttpClient();
        $this->proveedoresController = new ProveedoresController();
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
        if ($data['full_script']) {
            $url = GoodWe::$url . "api/v2/Charts/GetPlantPowerChart";
            if(isset($data['chartIndexId'])){
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
    public function GetPowerStationWariningInfoByMultiCondition($pageIndex = 1, $pageSize = 200, $status)
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

        $data =[
            "adcode" => "",
            "township" => "",
            "orgid" => "",
            "stationid" => "",
            "warninglevel" => 7,
            "status" => '"'.$status.'"',
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

        // Datos de la solicitud en el cuerpo
        $data = [
            'account' => GoodWe::$account,
            'pwd' => GoodWe::$pwd
        ];
        // Headers
        $headers = [
            'Content-Type: application/json',
            'Token: ' . json_encode([
                'version' => 'v2.1.0',
                'client' => 'ios',
                'language' => 'en'
            ])
        ];

        try {
            // Realiza la solicitud POST con los datos y encabezados
            $response = $this->httpClient->post($url, $headers, json_encode($data));

            // Decodifica la respuesta JSON
            $responseData = json_decode($response, true);

            // Verifica si la respuesta es exitosa y contiene los datos necesarios
            if (isset($responseData['hasError']) && $responseData['hasError'] === false && isset($responseData['data'])) {
                $token = isset($responseData['data']['token']) ? $responseData['data']['token'] : '';
                $timestamp = isset($responseData['data']['timestamp']) ? $responseData['data']['timestamp'] : '';

                $this->proveedoresController->setTokenProveedor('GoodWe', $token, '', $timestamp);
                $proveedor = $this->proveedoresController->getTokenProveedor('GoodWe');
                return [
                    'uid' => isset($responseData['data']['uid']) ? $responseData['data']['uid'] : '',
                    'timestamp' => isset($proveedor['expires_at']) ? $proveedor['expires_at'] : '',
                    'token' => isset($proveedor['tokenAuth']) ? $proveedor['tokenAuth'] : '',
                    'client' => isset($responseData['data']['client']) ? $responseData['data']['client'] : '',
                    'version' => isset($responseData['data']['version']) ? $responseData['data']['version'] : '',
                    'language' => isset($responseData['data']['language']) ? $responseData['data']['language'] : ''
                ];
            } else {
                throw new Exception("Login fallido: " . ($responseData['msg'] ?? 'Error desconocido'));
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
