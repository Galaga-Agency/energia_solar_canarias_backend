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

        var_dump($body);

        return $this->enviarDatosZoho($body, 'POST');
    }

    /**
     * DELETE
     */

    public function deleteCliente($data = "")
    {
        if ($data == "") {
            $data = $this->obtenerDatosRequest();
            if (!$data) {
                return json_encode(["error" => "Datos incompletos o inválidos"]);
            }
            $body = $this->construirBodyZoho($data);
        } else {
            $body = $this->construirBodyZohoCreadoApp($data);
        }

        return $this->enviarDatosZoho($body);
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

    public function eliminarCliente($clienteId)
    {
        if (!$clienteId) {
            return json_encode(["error" => "ID de cliente requerido"]);
        }

        // Llamar a la función genérica de envío con el ID en la URL
        return $this->enviarDatosZoho([], 'DELETE', 'Clientes', $clienteId);
    }

    //Estructura del body para enviar a Zoho (cliente) desde la app
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
    private function enviarDatosZoho(array $body = [], string $method = 'POST', string $endpoint = 'Clientes', string $extraPath = '')
    {
        try {
            $zohoService = new ZohoService();
            $accessTokenData = $zohoService->getAccessToken();

            if (!isset($accessTokenData['access_token'], $accessTokenData['api_domain'])) {
                throw new Exception("Error obteniendo el token de acceso de Zoho.");
            }

            $accessToken = $accessTokenData['access_token'];
            $apiDomain = $accessTokenData['api_domain'];

            // Construcción de la URL con el ID si es DELETE
            $url = $apiDomain . $this->routes[$endpoint];
            if ($method === 'DELETE' && !empty($extraPath)) {
                $url .= '/' . $extraPath;
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

            // Enviar cuerpo solo si es necesario
            if (!empty($body) && $method === 'POST') {
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
