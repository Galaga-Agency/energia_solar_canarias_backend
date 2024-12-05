<?php

use Firebase\JWT\Key;
use Firebase\JWT\JWT;

require_once "../models/conexion.php";
require_once "../utils/respuesta.php";
require_once "../controllers/usuarios.php";
require_once "../DBObjects/usuariosDB.php";
require_once "../controllers/ApiAccesosController.php";

class Autenticacion
{
    private static $secret_key = 'CWdefsJNq0KMJddeMZ!gaaWs3IuxgWdJAXIdl5bBzygLRE3-3FyhqGuwrseppjr9ldmJo4Y?4WVwcb6Lvv4MQ3nO!exF9Ch!XinpigxBq2WT-wSyKdgRUrNbrAorbvipvFx4-M';
    private static $algorithm = 'HS256';
    private $conexion;
    private $apiScope;

    function __construct()
    {
        $this->conexion = Conexion::getInstance();
        $this->apiScope = [
            "admin" => "scope1",
            "usuario" => "scope2"
        ];
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
        $headers = getallheaders();
        if (isset($headers['Authorization']) && preg_match('/Token\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Obtener y validar el token de autorización en formato API key
     * @return string|false El token si está presente y es válido, o false si no lo está
     */
    public function getAuthToken()
    {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1]; // Devuelve el token extraído
        }

        return false; // Si el encabezado no es válido o no existe
    }

    /**
     * Obtener y validar el token de autorización en formato Bearer
     * @return string|false El token si está presente y es válido, o false si no lo está
     */
    public function getBearerToken()
    {
        $headers = getallheaders();
        if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
        return false;
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
    
    public function verificarTokenUsuarioActivo()
    {
        $headers = getallheaders();
        if(isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)){
        $jwtToken = $this->getBearerToken();
        }else if(isset($headers['Authorization']) && preg_match('/Token\s(\S+)/', $headers['Authorization'], $matches)){
        $authToken = $this->getAuthApiScope();
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