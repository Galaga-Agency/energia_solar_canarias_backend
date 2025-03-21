<?php

use PhpParser\Node\Expr\Cast\String_;

class ZohoController
{

    private $routes;

    public function __construct()
    {
        // Constructor
        //array con todas las rutas de zoho
        $this->routes = [
            "Clientes" => "/crm/v2/Accounts"
        ];
    }

    /**
     * Estas Funciones son para crear campos en los modulos de Zoho
     */

    /**
     * POST
     */
    public function crearCliente($data = null)
    {
        if ($data === null) {
            $data = $this->obtenerDatosRequest();
            if (!$data) {
                return json_encode(["error" => "Datos incompletos o inválidos"]);
            }
            $body = $this->construirBodyZoho($data);
        } else {
            $body = $this->construirBodyZohoCreadoApp($data);
        }

        return $this->enviarDatosZoho($body, 'POST');
    }

    /**
     * PUT
     */

    public function actualizarCliente($data)
    {
        if ($data === null || !is_array($data) || !isset($data['usuario_id'])) {
            return json_encode(["error" => "Datos incompletos. Se requiere idApp para actualizar el cliente."]);
        }

        // Buscar cliente por idApp
        $queryParams = ['criteria' => '(idApp:equals:' . $data['usuario_id'] . ')'];
        $resultado = $this->enviarDatosZoho([], 'GET', 'Clientes', '', $queryParams);

        if (!is_array($resultado) || !isset($resultado['data'][0]['id'])) {
            return json_encode(["error" => "No se encontró ningún cliente en Zoho con el idApp: " . $data['idApp']]);
        }

        $zohoId = $resultado['data'][0]['id'];

        // Construir campos del cliente y agregar el ID
        $campos = $this->construirBodyZohoCreadoApp($data);
        $campos["id"] = $zohoId;

        $body = $this->construirBodyZohoCreadoApp($data);
        $body["data"] = $body; // Establece el cuerpo dentro de la clave "data" correcta


        // Enviar PUT con los nuevos datos
        $respuestaPut = $this->enviarDatosZoho($body['data'], 'PUT', 'Clientes', $zohoId);

        if (!is_array($respuestaPut)) {
            return json_encode(["error" => "Error inesperado al comunicarse con Zoho."]);
        }

        if (isset($respuestaPut['status']) && $respuestaPut['status'] == "error") {
            return ["error" => "Error al actualizar el cliente en Zoho: " . $respuestaPut['message']];
        }

        return [
            "success" => true,
            "message" => "Cliente actualizado correctamente en Zoho.",
            "zohoId" => $zohoId,
            "data" => $respuestaPut['data'][0]
        ];
    }



    /**
     * DELETE
     */
    public function eliminarCliente($idApp)
    {
        if (!$idApp) {
            return json_encode(["error" => "ID de cliente (idApp) requerido."]);
        }

        // Buscar el cliente en Zoho por idApp
        $queryParams = ['criteria' => '(idApp:equals:' . $idApp . ')'];
        $resultado = $this->enviarDatosZoho([], 'GET', 'Clientes', '', $queryParams);

        // Validar si el cliente fue encontrado en Zoho
        if (!isset($resultado['data'][0]['id'])) {
            return json_encode(["error" => "No se encontró un cliente en Zoho con el idApp: " . $idApp]);
        }

        $zohoId = $resultado['data'][0]['id']; // Extraer el Zoho_ID del cliente

        // Eliminar el cliente en Zoho usando su Zoho_ID
        $deleteResponse = $this->enviarDatosZoho([], 'DELETE', 'Clientes', $zohoId);

        // Verificar la respuesta de Zoho después de eliminar
        if (isset($deleteResponse['error']) && $deleteResponse['error'] == true) {
            return json_encode(["error" => "Error al eliminar el cliente en Zoho: " . $deleteResponse['message']]);
        }

        return ["success" => true, "message" => "Cliente eliminado correctamente en Zoho.", "zohoId" => $zohoId];
    }


    /**
     * Estas Funciones son reutilizables para toda clase de peticiones a Zoho
     */

    // Funcion que recoge el objeto JSON de las peticiones POST
    private function obtenerDatosRequest()
    {
        $jsonInput = file_get_contents('php://input');
        $data = json_decode($jsonInput, true);

        if (!$data || !isset($data['Correo_electr_nico_1'])) {
            return null;
        }

        return $data;
    }

    //Estructura del body para enviar a Zoho (cliente) desde la app (POST y DELETE)
    private function construirBodyZohoCreadoApp($data)
    {
        $accountName = $data['nombre'] . " " . $data['apellido'];
        return [
            "data" => [
                [
                    "Correo_electr_nico_1" => $data['email'],
                    "Account_Name" => $accountName ?? "",
                    "M_vil" => $data['movil'] ?? "",
                    "Empresa" => $data['empresa'],
                    "Poblaci_n" => $data['ciudad'] ?? "",
                    "NIF" => $data['cif_nif'] ?? "",
                    "Record_Image" => $data['imagen'] ?? "",
                    "idApp" => $data['usuario_id'] . "" // Convertir a string
                ]
            ]
        ];
    }


    //Estructura del body para enviar a Zoho (cliente)
    private function construirBodyZoho($data)
    {
        return [
            "data" => [
                [
                    "Correo_electr_nico_1" => $data['Correo_electr_nico_1'],
                    "Account_Name" => $data['Account_Name'] ?? "",
                    "M_vil" => $data['M_vil'] ?? "",
                    "Empresa" => $data['Empresa'],
                    "Poblaci_n" => $data['Poblaci_n'] ?? "",
                    "NIF" => $data['NIF'] ?? "",
                    "Record_Image" => $data['Record_Image'] ?? ""
                ]
            ]
        ];
    }

    //Funcion para enviar los datos a Zoho
    private function enviarDatosZoho(array $body = [], string $method = 'POST', string $endpoint = 'Clientes', string $extraPath = '', array $queryParams = [])
    {
        try {
            $zohoService = new ZohoService();
            $accessTokenData = $zohoService->getAccessToken();

            if (!isset($accessTokenData['access_token'], $accessTokenData['api_domain'])) {
                throw new Exception("Error obteniendo el token de acceso de Zoho.");
            }

            $accessToken = $accessTokenData['access_token'];
            $apiDomain = $accessTokenData['api_domain'];

            // Construcción de la URL con el endpoint
            $url = $apiDomain . $this->routes[$endpoint];

            // Agregar extraPath solo si es DELETE (o algún método que lo requiera explícitamente)
            if (!empty($extraPath)) {
                $url .= '/' . $extraPath;
            }


            // Si es una petición GET y hay queryParams, los agregamos a la URL
            if ($method === 'GET' && !empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $headers = [
                "Authorization: Zoho-oauthtoken $accessToken",
                "Content-Type: application/json"
            ];

            // Inicializar cURL
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $headers
            ]);

            // Enviar cuerpo solo si es POST o DELETE con datos
            if (!empty($body) && in_array($method, ['POST', 'DELETE', 'PUT'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
            }

            // Ejecutar la solicitud
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Manejo de errores de cURL
            if ($response === false) {
                throw new Exception("Error en cURL: " . curl_error($ch));
            }

            curl_close($ch);

            // Decodificar respuesta
            $decodedResponse = json_decode($response, true);

            // Validar si Zoho respondió con un error
            if (isset($decodedResponse['data'][0]['status']) && $decodedResponse['data'][0]['status'] === "error") {
                throw new Exception("Error en la API de Zoho: " . ($decodedResponse['data'][0]['message'] ?? 'Mensaje no disponible'));
            }

            return $decodedResponse;
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
