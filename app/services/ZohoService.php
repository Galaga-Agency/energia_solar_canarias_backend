<?php
require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../utils/HttpClient.php";
require_once __DIR__ . "/../models/conexion.php";
require_once __DIR__ . "/../controllers/ZohoController.php";
require_once __DIR__ . "/../services/ApiControladorService.php";

use Dotenv\Dotenv;

class ZohoService
{
    private static $url;
    private static $redirect_uri;
    private static $client_id;
    private static $client_secret;
    private static $refresh_token;

    private $access_token;
    private $scope;
    private $api_domain;
    private $token_type;
    private $expires_in;

    private $httpClient;
    private $conexion;
    private $zohoController;

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->zohoController = new ZohoController();
        try {
            $this->conexion = Conexion::getInstance();
            // Cargar el archivo .env desde la carpeta config
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../config');
            $dotenv->load();

            // Asignar los valores del .env a las propiedades estáticas
            self::$url = $_ENV['ZOHO_URL'];
            self::$redirect_uri = $_ENV['ZOHO_REDIRECT_URI'];
            self::$client_id = $_ENV['ZOHO_CLIENT_ID'];
            self::$client_secret = $_ENV['ZOHO_CLIENT_SECRET'];
            self::$refresh_token = $_ENV['ZOHO_REFRESH_TOKEN'];
        } catch (Exception $e) {
            echo "Error al cargar el archivo .env";
        }
    }

    // Método para obtener el token desde la base de datos
    private function getTokenFromDatabase()
    {
        try {
            $conn = $this->conexion->getConexion();

            // Consulta SQL para obtener el token actual
            $query = "SELECT `access_token`, `expires_in`, `token_type`, `scope`, `api_domain` FROM `zoho_tokens` WHERE `refresh_token` = ? LIMIT 1";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            // Vincular el parámetro
            $stmt->bind_param('s', self::$refresh_token);
            $stmt->execute();

            // Variables locales para almacenar el resultado de la consulta
            $access_token = null;
            $expires_in = null;
            $token_type = null;
            $scope = null;
            $api_domain = null;
            
            $stmt->bind_result($access_token, $expires_in, $token_type, $scope, $api_domain);
            if ($stmt->fetch()) {
                // Guardar los datos del token
                $this->access_token = $access_token;
                $this->expires_in = $expires_in;
                $this->token_type = $token_type;
                $this->scope = $scope;
                $this->api_domain = $api_domain;
                return true;
            }

            // Si no se encuentra el token en la base de datos, retornar false
            return false;
        } catch (Exception $e) {
            error_log("Error al obtener el token desde la base de datos: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insertar el token en la base de datos la primera vez
     */
    public function insertZohoAccessToken()
    {
        try {
            $conn = $this->conexion->getConexion();

            $query = "INSERT INTO `zoho_tokens`(`access_token`, `expires_in`, `token_type`, `scope`, `api_domain`, `refresh_token`) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }
            // Vincular los parámetros
            $stmt->bind_param('sissss', $this->access_token, $this->expires_in, $this->token_type, $this->scope, $this->api_domain, self::$refresh_token);
            if (!$stmt->execute()) {
                throw new Exception("Error al insertar el token: " . $stmt->error);
            }

            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Error al insertar el token de Zoho: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar en la base de datos el token
     */
    public function updateZohoAccessToken()
    {
        try {
            $conn = $this->conexion->getConexion();

            // Actualizar el token en la base de datos por `refresh_token`
            $query = "UPDATE `zoho_tokens` 
                      SET `access_token` = ?, `expires_in` = ?, `token_type` = ?, `scope` = ?, `api_domain` = ? 
                      WHERE `refresh_token` = ?";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            // Vincular los parámetros
            $stmt->bind_param('sissss', $this->access_token, $this->expires_in, $this->token_type, $this->scope, $this->api_domain, self::$refresh_token);
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar el token: " . $stmt->error);
            }

            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Error al actualizar el token de Zoho: " . $e->getMessage());
            return false;
        }
    }

    // Método para verificar si el token es válido
    private function isTokenValid()
    {
        // Comprobar si el token no ha expirado
        return $this->expires_in > time();
    }

    /**
     * REFRESCAR TOKEN CUANDO ESTE EXPIRE Y METERLO EN LA BASE DE DATOS
    */
    public function getAccessToken()
    {
        // Definir la estructura de respuesta por defecto
        $defaultResponse = [
            "access_token" => "",
            "scope" => "",
            "api_domain" => "",
            "token_type" => "",
            "expires_in" => 0
        ];

        try {
            // Primero, obtenemos el token desde la base de datos
            $tokenExists = $this->getTokenFromDatabase();

            if ($tokenExists && $this->isTokenValid()) {
                // Si el token existe y es válido, devolvemos el token
                return [
                    "access_token" => $this->access_token,
                    "scope" => $this->scope,
                    "api_domain" => $this->api_domain,
                    "token_type" => $this->token_type,
                    "expires_in" => $this->expires_in
                ];
            }

            // Si el token no existe o ha expirado, lo renovamos
            $url = self::$url . "/oauth/v2/token" . "?client_id=" . self::$client_id . "&client_secret=" . self::$client_secret . "&redirect_uri=" . self::$redirect_uri . "&grant_type=refresh_token&refresh_token=" . self::$refresh_token;

            $response = $this->httpClient->post($url, [], []);

            if ($response) {
                $response = json_decode($response);

                // Guardamos los datos del nuevo token
                $this->access_token = $response->access_token;
                $this->scope = $response->scope;
                $this->api_domain = $response->api_domain;
                $this->token_type = $response->token_type;
                $this->expires_in = $response->expires_in + time();

                // Si ya existe un token, lo actualizamos, si no, lo insertamos
                if ($tokenExists) {
                    $this->updateZohoAccessToken();
                } else {
                    $this->insertZohoAccessToken();
                }

                return [
                    "access_token" => $this->access_token,
                    "scope" => $this->scope,
                    "api_domain" => $this->api_domain,
                    "token_type" => $this->token_type,
                    "expires_in" => $this->expires_in
                ];
            }

            return $defaultResponse;
        } catch (Exception $e) {
            // En caso de error, retornar una respuesta vacía o con datos predeterminados
            error_log("Error al obtener el token de Zoho: " . $e->getMessage());
            return $defaultResponse;
        }
    }

    //CRUD de clientes de App a Zoho
    /**
     * Crear un cliente desde la App a Zoho
     * Esta función se encarga de crear un cliente en Zoho CRM a partir de los datos
     * que se envíen desde la App.
    */
    public function crearCliente($data = null)
    {
        return $ClienteZoho = $this->zohoController->crearCliente($data);
    }

    /**
     * Actualizar un cliente desde la App a Zoho
     * Esta función se encarga de crear un cliente en Zoho CRM a partir de los datos
     * que se envíen desde la App.
    */
    public function actualizarCliente($data = null)
    {
        return $ClienteZoho = $this->zohoController->actualizarCliente($data);
    }
    /**
     * Eliminar un cliente desde la App a Zoho
     * Esta función se encarga de crear un cliente en Zoho CRM a partir de los datos
     * que se envíen desde la App.
    */
    public function eliminarCliente($clienteId = "")
    {
        return $ClienteZoho = $this->zohoController->eliminarCliente($clienteId);
    }

    /**
     * Desactiva la propiedad de zoho App Crear Cliente
     * Esta función se encarga de eliminar el App Crear Cliente por lo que se da a entender que no existe mas en la app
    */
    public function appCrearClienteFalse($clienteId = "")
    {
        return $ClienteZoho = $this->zohoController->appCrearClienteFalse($clienteId);
    }

    /**
     * Actualizar el identificador de un cliente en Zoho
     * Esta función se encarga de actualizar el idApp de un cliente en Zoho CRM a partir de los datos
     * que se envíen desde la App.
    */
    public function actualizarId($clienteId = null, $idApp = null)
    {
        return $ClienteZoho = $this->zohoController->actualizarId($clienteId, $idApp);
    }

    /**
     * Obtener un cliente desde la App a Zoho
     * Esta función se encarga de obtener un cliente en Zoho CRM a partir de los datos
     * que se envíen desde la App.
    */
    public function obtenerCliente($clienteId = null)
    {
        return $ClienteZoho = $this->zohoController->obtenerCliente($clienteId);
    }

    /**
     * 1. Obtener una planta de la aplicación de zoho Plantas sin los Clientes 
     * y si existe el identificador de la planta entonces no crearla
    */
    public function actualizarDatosPlantas()
    {
        $apiControladorService = new  ApiControladorService;
        //Recogemos todas las plantas que hay actualemente de los proveedores
        $todasLasPlantas = $apiControladorService->getAllPlants(true);
        //Recogemos todas las plantas que y las parseamos en datos que zoho reconozca validos
        $plantasZoho = $this->zohoController->convertirPlantasFormatoZoho($todasLasPlantas);
        //comprobamos que las plantas con idPlanta no esten ya creadas en zoho para evitar duplicados de plantas
        $plantasComprobadas = $this->zohoController->comprobarIdPlantasExistentes($plantasZoho);
        //actualizamos las plantas en zoho y devolvemos las plantas creadas
        $plantasCreadas = $this->zohoController->crearTodasLasPlantasEnZoho($plantasComprobadas);
        return $plantasCreadas;
        //var_dump($todasLasPlantas);
        //$plantasArray = $this->zohoController->sacarPlantasArray($clienteId);
    }

    /**
     * 1. Obtener una planta de la aplicación de zoho Plantas
     * 2. El cliente que se obtiene se le hace un filtro para sacar los ids en la tabla intermedia  Plantas_X_Clientes
     * 3. se hace un filtro con el cliente para saber cual es el cliente con la idApp y se saca el id de zoho de ese cliente Accounts
     * 4. se relaciona el cliente con la planta
    */
    public function obtenerListadoDePrecios($plantId,$proveedor){
        $listadoDePrecios = $this->zohoController->obtenerListadoDePrecios($plantId,$proveedor);
        $paresarPrecios = $this->parsearListaDePrecios($listadoDePrecios);
        return $paresarPrecios;
    }

    public function parsearListaDePrecios($listadoDePrecios){
        if($listadoDePrecios == null || $listadoDePrecios == ""){
            return null;
        }
        // Array donde se almacenarán los nuevos precios procesados
        $nuevoListadoDePrecios = [];
        if(!isset($listadoDePrecios['data'])){
            return null;
        }
        //echo json_encode($listadoDePrecios);
        // Iterar sobre el listado de precios recibido
        foreach($listadoDePrecios['data'] as $key => $value){ 
            // Agregar la estructura con los datos requeridos para cada precio
            $nuevoListadoDePrecios[] = [
                'precio' => $value['precio'],
                'precio_ahorro' => $value['precio_ahorro'],
                'fecha_inicio' => $value['fecha_inicio'],
                'fecha_final' => $value['fecha_final'],
                'planta_id' => $value['planta_id'],
                'proveedor' => $value['proveedor'],
                'moneda' => $value['moneda']
            ];
        }
        
        // Devolver el nuevo listado de precios
        return $nuevoListadoDePrecios;
    }    
}
