<?php

use Firebase\JWT\Key;
use Firebase\JWT\JWT;

require_once "../models/conexion.php";
require_once "../utils/respuesta.php";
require_once "../controllers/usuarios.php";
require_once "../DBObjects/usuariosDB.php";

class Autenticacion
{
    //ESTA ES LA API KEY DEL SERVIDOR
    private static $secret_key = '***REMOVED***';
    //ESTA ES EL ALGORITMO DE CIFRADO
    private static $algorithm = 'HS256';
    private $usuario;
    private $apiKey;
    private $usuarioEsperado;
    private $apiKeyEsperada;
    public $request;
    private $scope;
    private $table = 'usuarios';
    private $conexion;
    private $dataUsuario;

    function __construct()
    {
        $this->conexion = new Conexion;
    }

    /**
     * Obtener y validar el token de autorización en formato Bearer
     * @return string|false El token si está presente y es válido, o false si no lo está
     */
    public function getBearerToken()
    {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1]; // Devuelve el token extraído
        }

        return false; // Si el encabezado no es válido o no existe
    }

    /**
     * Verificar si el usuario es administrador y tiene un token válido
     * @return bool Devuelve true si el usuario es administrador y el token es válido, de lo contrario, responde con un error y termina la ejecución
     */
    public function verificarAdmin()
    {
        //lo defino arriba y ya no me toca definirlo en cada if
        $respuesta = new Respuesta;
        // Obtener el token usando la función getBearerToken
        $jwtToken = $this->getBearerToken();

        if ($jwtToken) {
            // Verificar el token
            $autenticar = Conexion::verifyJwt($jwtToken);

            if ($autenticar) {
                // Crear instancia de UsuariosDB
                $dboUser = new UsuariosDB();

                // Verificar si el usuario es administrador
                if ($dboUser->getAdmin($autenticar['id'])) {
                    return true;
                } else {
                    return false;
                    die();
                }
            } else {
                // Token inválido o expirado
                $respuesta->_401();
                $respuesta->message = 'No autorizado. Token inválido o expirado.';
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                die();
            }
        } else {
            // Encabezado de autorización incorrecto o ausente
            $respuesta->_400();
            $respuesta->message = 'Encabezado de autorización faltante o incorrecto';
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
            die();
        }
    }
    public function verificarTokenUsuarioActivo()
    {
        //lo defino arriba y no me toca definirlo en cada if
        $respuesta = new Respuesta;
        // Obtener el token del encabezado
        $auth = new Autenticacion();
        $jwtToken = $auth->getBearerToken();

        if (!$jwtToken) {
            $respuesta->_401();
            $respuesta->message = "Token de autorización no proporcionado.";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
            return false;
        }

        // Decodificar el token para obtener id y email
        try {
            $decoded = JWT::decode($jwtToken, new Key(self::$secret_key, self::$algorithm));
            $userId = $decoded->data->id;

            // Instancia de la clase UsuariosDB para verificar el estado
            $usuariosDB = new UsuariosDB();
            $estadoUsuario = $usuariosDB->verificarEstadoUsuario($userId);

            if ($estadoUsuario) {
                return true; // Usuario activo, se permite el acceso
            } else {
                $respuesta->_404();
                $respuesta->message = "Usuario del token no encontrado";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            }
        } catch (Exception $e) {
            // Token inválido o expirado
            $respuesta->_401();
            $respuesta->message = "Token inválido o expirado.";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }

        return false; // En caso de error o usuario inactivo/eliminado
    }
        public function obtenerIdUsuarioActivo()
    {
        // Definir la respuesta para manejar errores
        $respuesta = new Respuesta;
    
        // Obtener el token del encabezado
        $auth = new Autenticacion();
        $jwtToken = $auth->getBearerToken();

        if (!$jwtToken) {
            $respuesta->_401();
            $respuesta->message = "Token de autorización no proporcionado.";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
            return false;
        }

        // Decodificar el token para obtener id y email
        try {
            $decoded = JWT::decode($jwtToken, new Key(self::$secret_key, self::$algorithm));
            $userId = $decoded->data->id;

            // Instancia de la clase UsuariosDB para verificar el estado
            $usuariosDB = new UsuariosDB();
            $estadoUsuario = $usuariosDB->verificarEstadoUsuario($userId);

            if ($estadoUsuario) {
                return $userId; // Retornar el ID del usuario activo
            } else {
                $respuesta->_404();
                $respuesta->message = "Usuario del token no encontrado.";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            }
        } catch (Exception $e) {
            // Token inválido o expirado
            $respuesta->_401();
            $respuesta->message = "Token inválido o expirado.";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }

    return false; // En caso de error o usuario inactivo/eliminado
}

}

/*
//PRUEBAS
$autencicacion = new Autenticacion("userLogin");
$autencicacion->execute();
*/