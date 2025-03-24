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
     * GET
     */
    // Función para buscar el cliente por idApp en Zoho
    private function buscarClientePorIdApp($idApp)
    {
        if (empty($idApp)) {
            return ["error" => "Se requiere idApp para buscar el cliente."];
        }

        // Construir los parámetros de búsqueda
        $queryParams = ['criteria' => '(idApp:equals:' . $idApp . ')'];
        $resultado = $this->enviarDatosZoho([], 'GET', 'Clientes', '', $queryParams);

        // Verificar si se encontró un cliente
        if (!is_array($resultado) || !isset($resultado['data'][0]['id'])) {
            return ["error" => "No se encontró ningún cliente en Zoho con el idApp: " . $idApp];
        }

        // Retornar el id del cliente encontrado
        return $resultado['data'][0]['id'];
    }

    // Función para obtener el cliente desde Zoho CRM por idApp
    public function obtenerCliente($idApp)
    {
        if (empty($idApp)) {
            return json_encode(["error" => "Se requiere idApp para obtener los datos del cliente."]);
        }

        // Construir los parámetros de búsqueda
        $queryParams = ['criteria' => '(idApp:equals:' . $idApp . ')'];
        $resultado = $this->enviarDatosZoho([], 'GET', 'Clientes', '', $queryParams);

        // Verificar si se encontró un cliente
        if (!is_array($resultado) || !isset($resultado['data'][0]['id'])) {
            return json_encode(["error" => "No se encontró ningún cliente en Zoho con el idApp: " . $idApp]);
        }

        // Retornar los datos del cliente encontrado
        return [
            "success" => true,
            "message" => "Cliente encontrado exitosamente.",
            "data" => $resultado['data'][0]
        ];
    }


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

    // Función para actualizar el cliente en Zoho
    private function actualizarClienteEnZoho($data, $zohoId)
    {
        if (empty($zohoId)) {
            return ["error" => "No se ha encontrado un id de cliente válido para actualizar."];
        }

        // Construir los campos del cliente con los datos proporcionados
        $campos = $this->construirBodyZohoCreadoApp($data);
        $campos["id"] = $zohoId;  // Agregar el ID al cuerpo de la solicitud

        // Establecer el cuerpo de la solicitud en el formato adecuado para Zoho
        $body = ["data" => $campos];

        // Enviar solicitud PUT para actualizar el cliente
        $respuestaPut = $this->enviarDatosZoho($body['data'], 'PUT', 'Clientes', $zohoId);

        // Verificar respuesta de Zoho
        if (!is_array($respuestaPut)) {
            return ["error" => "Error inesperado al comunicarse con Zoho."];
        }

        // Verificar si hubo algún error en la respuesta de Zoho
        if (isset($respuestaPut['status']) && $respuestaPut['status'] == "error") {
            return ["error" => "Error al actualizar el cliente en Zoho: " . $respuestaPut['message']];
        }

        // Retornar el éxito de la operación
        return [
            "success" => true,
            "message" => "Cliente actualizado correctamente en Zoho.",
            "zohoId" => $zohoId,
            "data" => $respuestaPut['data'][0]
        ];
    }

    // Función principal que usa las funciones anteriores para actualizar un cliente
    public function actualizarCliente($data)
    {
        // Validar si se han pasado los datos necesarios
        if ($data === null || !is_array($data) || !isset($data['usuario_id'])) {
            return json_encode(["error" => "Datos incompletos. Se requiere idApp para actualizar el cliente."]);
        }

        // Buscar el cliente por idApp
        $zohoId = $this->buscarClientePorIdApp($data['usuario_id']);
        if (isset($zohoId['error'])) {
            return json_encode($zohoId);  // Si hay un error, lo devolvemos
        }

        // Actualizar el cliente en Zoho
        $resultado = $this->actualizarClienteEnZoho($data, $zohoId);
        return json_encode($resultado);
    }

    public function actualizarId($clienteId, $idApp)
    {
        if (!$clienteId || !$idApp) {
            return json_encode(["error" => "Faltan parámetros obligatorios: clienteId o idApp"]);
        }

        $body = [
            "data" => [
                [
                    "id" => $clienteId,
                    "idApp" => (string)$idApp
                ]
            ]
        ];

        // Enviar PUT con los nuevos datos
        $respuestaPut = $this->enviarDatosZoho($body, 'PUT', 'Clientes', $clienteId);

        if (!is_array($respuestaPut)) {
            return json_encode(["error" => "Error inesperado al comunicarse con Zoho."]);
        }

        if (isset($respuestaPut['data'][0]['status']) && $respuestaPut['data'][0]['status'] === "error") {
            return [
                "error" => "Error al actualizar el cliente en Zoho: " . ($respuestaPut['data'][0]['message'] ?? 'Desconocido')
            ];
        }

        return [
            "success" => true,
            "message" => "idApp actualizado correctamente en Zoho.",
            "zohoId" => $clienteId,
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

    //Estructura del body para enviar a Zoho (cliente) desde la app (POST PUT y DELETE)
    private function construirBodyZohoCreadoApp($data)
    {
        $accountName = $data['nombre'] . " " . $data['apellido'];
        return [
            "data" => [
                [
                    "Correo_electr_nico_1" => $data['email'],
                    "origen" => $data['origen'] ?? "app",
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



    //Estructura del body para enviar a Zoho (cliente) (SOLO PARA PRUEBAS POST desde POSTMAN)
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
