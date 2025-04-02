<?php
require_once __DIR__ . "/../services/ZohoService.php";
require_once __DIR__ . "/LogsController.php";

use PhpParser\Node\Expr\Cast\String_;

class ZohoController
{
    private $logsController;
    private $routes;

    public function __construct()
    {
        $logsController = new LogsController();
        // Constructor
        //array con todas las rutas de zoho
        $this->routes = [
            "Clientes" => "/crm/v2/Accounts",
            'Plantas'  => '/crm/v2/Plantas',
            'Plantas/search' => '/crm/v2/Plantas/search',
            'Plantas/acciones/insertar' => '/crm/v2/Plantas/actions/insert',
            'bulk/api' => '/crm/bulk/v2/write'
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
            $this->logsController->registrarLog(Logs::ERROR, "Se requiere idApp para buscar el cliente.");
            return ["error" => "Se requiere idApp para buscar el cliente."];
        }

        // Construir los parámetros de búsqueda
        $queryParams = ['criteria' => '(idApp:equals:' . $idApp . ')'];
        $resultado = $this->enviarDatosZoho([], 'GET', 'Clientes', '', $queryParams);

        // Verificar si se encontró un cliente
        if (!is_array($resultado) || !isset($resultado['data'][0]['id'])) {
            $this->logsController->registrarLog(Logs::ERROR, "No se encontró ningún cliente en Zoho con el idApp: " . $idApp);
            return ["error" => "No se encontró ningún cliente en Zoho con el idApp: " . $idApp];
        }

        $this->logsController->registrarLog(Logs::INFO, "Se a encontrado el cliente en zoho con id " . $idApp . " id de zoho: " . $resultado['data'][0]['id']);
        // Retornar el id del cliente encontrado
        return $resultado['data'][0]['id'];
    }

    // Función para obtener el cliente desde Zoho CRM por idApp
    public function obtenerCliente($idApp)
    {
        if (empty($idApp)) {
            $this->logsController->registrarLog(Logs::ERROR, "Se requiere idApp para obtener los datos del cliente.");
            return json_encode(["error" => "Se requiere idApp para obtener los datos del cliente."]);
        }

        // Construir los parámetros de búsqueda
        $queryParams = ['criteria' => '(idApp:equals:' . $idApp . ')'];
        $resultado = $this->enviarDatosZoho([], 'GET', 'Clientes', '', $queryParams);

        // Verificar si se encontró un cliente
        if (!is_array($resultado) || !isset($resultado['data'][0]['id'])) {
            $this->logsController->registrarLog(Logs::ERROR, "No se encontró ningún cliente en Zoho con el idApp: " . $idApp);
            return json_encode(["error" => "No se encontró ningún cliente en Zoho con el idApp: " . $idApp]);
        }

        $this->logsController->registrarLog(Logs::INFO, "Cliente encontrado exitosamente." . $idApp);
        // Retornar los datos del cliente encontrado
        return [
            "success" => true,
            "message" => "Cliente encontrado exitosamente.",
            "data" => $resultado['data'][0]
        ];
    }

    //Funcion que obtiene las plantas que no existen del array de plantas y nos las devuelve
    public function comprobarIdPlantasExistentes(array $plantasZoho): array
    {
        $plantasFiltradas = [];

        foreach ($plantasZoho as $planta) {
            $queryParams = [
                'criteria' => '(idPlanta:equals:' . $planta['idPlanta'] . ')'
            ];

            $respuesta = $this->enviarDatosZoho([], 'GET', 'Plantas/search', '', $queryParams);

            // Si no se encuentra ninguna planta, la añadimos para crear
            if (!isset($respuesta['data'][0]['id'])) {
                $plantasFiltradas[] = $planta;
            }
        }

        return $plantasFiltradas;
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

    public function crearTodasLasPlantasEnZoho(array $plantasAInsertar): array
    {
        $creadas = [];
        $batchSize = 100;
        $chunks = array_chunk($plantasAInsertar, $batchSize);

        $csvDir = __DIR__ . '/plantasCSV';
        if (!is_dir($csvDir)) {
            mkdir($csvDir, 0777, true);
        }

        foreach ($chunks as $index => $grupo) {
            $csvPath = "$csvDir/plantas_batch_$index.csv";
            $zipPath = "$csvDir/plantas_batch_$index.zip";

            $this->guardarCSV($grupo, $csvPath);

            // Crear ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                $zip->addFile($csvPath, basename($csvPath));
                $zip->close();
            } else {
                $creadas[] = [
                    'grupo' => $grupo,
                    'error' => 'No se pudo crear el archivo ZIP.'
                ];
                continue;
            }

            // Subir ZIP
            $fileId = $this->subirArchivoZipZoho($zipPath);

            if (!$fileId) {
                $creadas[] = [
                    'grupo' => $grupo,
                    'error' => 'Error al subir el archivo ZIP a Zoho.'
                ];
                continue;
            }

            $body = [
                "operation" => "insert",
                "resource" => [
                    [
                        "type" => "data",
                        "module" => "Plantas",
                        "file_id" => $fileId
                    ]
                ]
            ];

            $respuesta = $this->enviarBulkInsertZoho($body);

            if (isset($respuesta['status']) && $respuesta['status'] === 'success') {
                $creadas[] = [
                    "grupo" => $grupo,
                    "job_id" => $respuesta['details']['id'],
                    "estado" => "pendiente"
                ];
            } else {
                $creadas[] = [
                    "grupo" => $grupo,
                    "error" => $respuesta['message'] ?? 'Error desconocido'
                ];
            }

            unlink($csvPath);
            unlink($zipPath);
        }

        return $creadas;
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

        // Verificar si la búsqueda devuelve exactamente un solo cliente
        if (count($resultado['data']) == 1) {
            $zohoId = $resultado['data'][0]['id']; // Extraer el Zoho_ID del cliente
            $deleteResponse = $this->enviarDatosZoho([], 'DELETE', 'Clientes', $zohoId);

            if (isset($deleteResponse['error']) && $deleteResponse['error'] == true) {
                return json_encode(["error" => "Error al eliminar el cliente en Zoho: " . $deleteResponse['message']]);
            }

            return ["success" => true, "message" => "Cliente eliminado correctamente en Zoho.", "zohoId" => $zohoId];
        } else {
            // Si más de un cliente se encuentra con el mismo idApp, logueamos el problema
            return json_encode(["error" => "Se encontraron múltiples clientes con el mismo idApp: " . $idApp]);
        }
    }


    /**
     * Estas Funciones son reutilizables para toda clase de peticiones a Zoho
     */

    /**
     * CONSTRUIR CUERPO DE PETICIONES
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
        if (isset($data['nombre']) && isset($data['apellido'])) {
            $accountName = $data['nombre'] . " " . $data['apellido'];
        } elseif (isset($data['nombre'])) {
            $accountName = $data['nombre'];
        } elseif (isset($data['apellido'])) {
            $accountName = $data['apellido'];
        }
        return [
            "data" => [
                [
                    "Correo_electr_nico_1" => $data['email'],
                    "origen" => $data['origen'] ?? "app",
                    "Account_Name" => $accountName ?? "",
                    "Phone" => $data['movil'] ?? "",
                    "Empresa" => $data['empresa'] ?? "",
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
                    "Correo_electr_nico_1" => $data['Correo_electr_nico_1'] ?? "",
                    "Account_Name" => $data['Account_Name'] ?? "",
                    "M_vil" => $data['M_vil'] ?? "",
                    "Empresa" => $data['Empresa'],
                    "Poblaci_n" => $data['Poblaci_n'] ?? "",
                    "clase" => $data['clase'] ?? "",
                    "NIF" => $data['NIF'] ?? "",
                    "Record_Image" => $data['Record_Image'] ?? ""
                ]
            ]
        ];
    }

    //Estructura del body para enviar a zoho
    public function convertirPlantasFormatoZoho(array $plantas): array
    {
        $resultado = [];

        foreach ($plantas as $planta) {
            $resultado[] = [
                "Name" => $planta["name"] ?? "",
                "direccion" => $planta["address"] ?? "",
                "capacidad" => $planta["capacity"] ?? "",
                "estado" => $planta["status"] ?? "",
                "tipo" => $planta["type"] ?? "",
                "latitud" => $planta["latitude"] ?? "",
                "longitud" => $planta["longitude"] ?? "",
                "Organizaci_n" => $planta["organization"] ?? "",
                "idPlanta" => $planta["id"], // Tu identificador interno para evitar duplicados
                "clase" => $planta["clase"],
                "moneda" => "Euro" //Por defecto
            ];
        }

        return $resultado;
    }

    /**
     * Funciones para manejar CSV
     */

    private function guardarCSV(array $datos, string $rutaArchivo): void
    {
        $f = fopen($rutaArchivo, 'w');

        // Escribir cabeceras
        if (!empty($datos)) {
            fputcsv($f, array_keys($datos[0]));
        }

        // Escribir contenido
        foreach ($datos as $fila) {
            fputcsv($f, $fila);
        }

        fclose($f);
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

    private function subirArchivoZipZoho(string $zipPath)
    {
        try {
            $zohoService = new ZohoService();
            $accessTokenData = $zohoService->getAccessToken();

            if (!isset($accessTokenData['access_token'])) {
                throw new Exception("Error obteniendo el token de acceso de Zoho.");
            }

            $accessToken = $accessTokenData['access_token'];
            $zgid = '20103897680'; // Reemplaza si cambia tu zgid
            $url = 'https://content.zohoapis.eu/crm/v2/upload';

            $file = new CURLFile($zipPath, 'application/zip', basename($zipPath));

            $postFields = [
                'file' => $file
            ];

            $headers = [
                "Authorization: Zoho-oauthtoken $accessToken",
                "X-CRM-ORG: $zgid",
                "feature: bulk-write"
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $postFields
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $respuesta = json_decode($response, true);

            if (isset($respuesta['details']['file_id'])) {
                return $respuesta['details']['file_id'];
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    //Funcion para enviar datos a la bulk api
    private function enviarBulkInsertZoho(array $body): array
    {
        try {
            $zohoService = new ZohoService();
            $accessTokenData = $zohoService->getAccessToken();

            if (!isset($accessTokenData['access_token'])) {
                throw new Exception("Error obteniendo el token de acceso de Zoho.");
            }

            $accessToken = $accessTokenData['access_token'];
            $url = 'https://www.zohoapis.eu/crm/bulk/v2/write';

            $headers = [
                "Authorization: Zoho-oauthtoken $accessToken",
                "Content-Type: application/json"
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => json_encode($body, JSON_THROW_ON_ERROR)
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
