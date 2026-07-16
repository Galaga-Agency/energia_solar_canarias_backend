<?php
use Dotenv\Dotenv;
use Firebase\JWT\Key;
use Firebase\JWT\JWT;

require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../controllers/usuarios.php';
require_once __DIR__ . '/../DBObjects/usuariosDB.php';
require_once __DIR__ . '/../controllers/ApiAccesosController.php';


class Autenticacion
{
    private static $secret_key;
    private static $algorithm;
    private $conexion;
    private $apiScope;

    function __construct()
    {
        try{
            // Cargar el archivo .env desde la carpeta config
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../config');
            $dotenv->load();

            // Asignar los valores del .env a las propiedades estáticas
            self::$secret_key = $_ENV['SECRET_KEY'];
            self::$algorithm = $_ENV['ALGORITHM'];
        }catch(Exception $e){
            echo "Error al cargar el archivo .env";
        }
        $this->conexion = Conexion::getInstance();
        $this->apiScope = [
            "admin" => "scope1",
            "usuario" => "scope2"
        ];
    }
    /**
     * Lee una cabecera sin distinguir mayusculas de minusculas.
     *
     * Los nombres de cabecera son case-insensitive (RFC 7230 §3.2), pero aqui se
     * leian con $headers['Authorization'] literal. Con curl y con el navegador por
     * HTTP/1.1 llega asi y colaba; en cuanto delante hay algo que normaliza a
     * minusculas, el token deja de verse y la respuesta es un 403 que parece de
     * credenciales pero es de mayusculas. Pasa con cualquier proxy en Node (por
     * ejemplo el rewrite de Next para desarrollo) y, sobre todo, HTTP/2 OBLIGA a
     * que los nombres vayan en minusculas.
     *
     * @return string|null El valor, o null si la cabecera no viene.
     */
    public static function cabecera($nombre)
    {
        foreach (self::cabeceras() as $clave => $valor) {
            if (strcasecmp($clave, $nombre) === 0) {
                return $valor;
            }
        }
        return null;
    }

    /**
     * getallheaders() no existe fuera de Apache (php-fpm, CLI), asi que se
     * reconstruye desde $_SERVER. Estaba copiado dentro de dos metodos y definia la
     * funcion global sobre la marcha; aqui esta una sola vez.
     */
    private static function cabeceras()
    {
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (is_array($h)) return $h;
        }
        $headers = [];
        foreach ($_SERVER as $nombre => $valor) {
            if (substr($nombre, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($nombre, 5)))))] = $valor;
            }
        }
        return $headers;
    }

    /**
     * Saca el token de la cabecera Authorization segun su esquema.
     * @param string $esquema 'Bearer' (JWT de usuario) o 'Token' (API key).
     * @return string|false
     */
    private static function tokenDeAutorizacion($esquema)
    {
        $auth = self::cabecera('Authorization');
        if ($auth && preg_match('/' . $esquema . '\s(\S+)/', $auth, $m)) {
            return $m[1];
        }
        return false;
    }

    //Getter y setter de apiScope
    public function getApiScope()
    {
        return $this->apiScope;
    }

    public function setApiScope($apiScope)
    {
        $this->apiScope = $apiScope;
    }

    public function upsertApiAcceso()
    {
        $apiAcceso = new ApiAccesosController;
        $respuesta = new Respuesta;
        $api_key = $this->generarUUID();
        //El usuario que puede crear un token definitivo solo puede ser el usuario con Bearer Token
        if ($this->verificarTokenUsuarioActivo()) {
            $usuarioId = $this->obtenerIdUsuarioActivo();
            if ($this->verificarAdmin()) {
                $api_scope = $this->getApiScope()["admin"];
                $resultado = $apiAcceso->upsertApiAcceso($usuarioId, $api_key, $api_scope);
                $respuesta->success($resultado);
            } else {
                $api_scope = $this->getApiScope()["usuario"];
                $resultado = $apiAcceso->upsertApiAcceso($usuarioId, $api_key, $api_scope);
                $respuesta->success($resultado);
            }
        } else {
            $respuesta->_404();
            $respuesta->message = 'el usuario no existe';
        }
        if ($resultado == false) {
            $respuesta->_404();
            $respuesta->message = 'no se pudo crear el token';
        }
        http_response_code($respuesta->code);
        echo json_encode($respuesta);
    }

    public function verificarAuthApiScope()
    {
        $apiAccesosDB = new ApiAccesosController();
        $token = $this->getAuthApiScope();
        if ($token != null) {
            return $apiAccesosDB->verificarApiAcceso($token);
        }
        return false;
    }

    public function getAuthApiScope()
    {
        return self::tokenDeAutorizacion('Token');
    }

    /**
     * Obtener y validar el token de autorización en formato API key
     * @return string|false El token si está presente y es válido, o false si no lo está
     */
    public function getAuthToken()
    {
        return self::tokenDeAutorizacion('Bearer');
    }

    /**
     * Obtener y validar el token de autorización en formato Bearer
     * @return string|false El token si está presente y es válido, o false si no lo está
     */
    public function getBearerToken()
    {
        return self::tokenDeAutorizacion('Bearer');
    }
    /**
     * Verificar si el usuario es administrador y tiene un token válido
     * @return bool Devuelve true si el usuario es administrador y el token es válido, de lo contrario, responde con un error y termina la ejecución
     */
    public function verificarAdmin() {
        $authScope = $this->verificarAuthApiScope();
        
        if (isset($authScope['api_scope']) && $authScope['api_scope'] === $this->apiScope['admin']) {
            return true;
        }
    
        $jwtToken = $this->getBearerToken();
        if ($jwtToken) {
            $autenticar = $this->conexion::verifyJwt($jwtToken);
            if ($autenticar) {
                $usuariosDB = new UsuariosDB();
                return $usuariosDB->getAdmin($autenticar['id']);
            }
        }
    
        return false;
    }
    
    /**
     * Comprueba si el usuario del token puede consultar ESTA planta.
     *
     * La regla: el admin ve todas; el usuario normal solo las que tiene asignadas
     * en plantas_asociadas.
     *
     * Hace falta porque /plants y /plants/details ya filtraban por usuario, pero los
     * endpoints de datos (tiempo real, inventario, alertas, graficas...) iban directos
     * al proveedor sin mirar de quien es la planta: bastaba con saber el id para leer
     * datos de cualquier instalacion, y en SolarEdge o Sungrow los ids son numericos
     * y correlativos.
     *
     * Si devuelve false YA ha respondido con 403, asi que quien llama solo tiene que
     * cortar (return/break) sin escribir nada mas.
     *
     * @param string $idPlanta  id de la planta tal cual lo da el proveedor
     * @param string $proveedor nombre del proveedor (vale 'sigenergy' o 'Sigenergy':
     *                          la comparacion en MySQL no distingue mayusculas)
     * @return bool true si puede seguir
     */
    public function puedeVerPlanta($idPlanta, $proveedor)
    {
        if ($this->verificarAdmin()) {
            return true;
        }

        $idUsuario = $this->obtenerIdUsuarioActivo();
        if ($idUsuario) {
            require_once __DIR__ . '/../DBObjects/plantasAsociadasDB.php';
            $plantasAsociadas = new PlantasAsociadasDB;
            if ($plantasAsociadas->isPlantasAsociadasAlUsuario($idUsuario, $idPlanta, $proveedor)) {
                return true;
            }
        }

        // Mismo mensaje tanto si la planta no existe como si es de otro: si dijeramos
        // "no es tuya" estariamos confirmando que existe, y con ids correlativos eso
        // permite mapear el parque ajeno planta por planta.
        $respuesta = new Respuesta;
        $respuesta->_403();
        $respuesta->message = 'No tienes acceso a esta planta';
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode($respuesta);
        return false;
    }

    public function verificarTokenUsuarioActivo()
    {
        $jwtToken = $this->getBearerToken() ?: null;
        if (!$jwtToken) {
            $authToken = $this->getAuthApiScope() ?: null;
        }

        if (isset($jwtToken)) {
            try {
                $decoded = JWT::decode($jwtToken, new Key(self::$secret_key, self::$algorithm));
                $userId = $decoded->data->id;

                $usuariosDB = new UsuariosDB();
                
                return $usuariosDB->verificarEstadoUsuario($userId);
            } catch (Exception $e) {
                $respuesta = new Respuesta;
                $respuesta->_403();
                $respuesta->message = 'El token no se a podido authentificar';
                http_response_code(403);
                json_encode($respuesta);
                return false;
            }
        }

        if (isset($authToken)) {
            $usuariosDB = new UsuariosDB();
            $scopeValida = $this->verificarAuthApiScope();
            if(isset($scopeValida['userId'])){
            return $usuariosDB->verificarEstadoUsuario($scopeValida['userId']);
            }else{
                return false;

            }
        }

        return false;
    }
    public function obtenerIdUsuarioActivo()
    {
        $jwtToken = $this->getBearerToken();
        $authToken = $this->getAuthApiScope();

        if (!$jwtToken && !$authToken) {
            return false;
        }

        if ($authToken) {
            $apiAccesosController = new ApiAccesosController();
            return $apiAccesosController->devolverIdPorAccesoApiKey($authToken);
        }

        if ($jwtToken) {
            try {
                $decoded = JWT::decode($jwtToken, new Key(self::$secret_key, self::$algorithm));
                return $decoded->data->id;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }
    function generarUUID()
    {
        // Crea un UUID versión 4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), // 32 bits para la primera parte
            mt_rand(0, 0xffff), // 16 bits para la segunda parte
            mt_rand(0, 0x0fff) | 0x4000, // 16 bits para la tercera parte (UUIDv4)
            mt_rand(0, 0x3fff) | 0x8000, // 16 bits para la cuarta parte
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff) // 48 bits para la quinta parte
        );
    }
}

/*
//PRUEBAS
$autencicacion = new Autenticacion("userLogin");
$autencicacion->execute();
*/