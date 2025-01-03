<?php

require_once __DIR__ . "/../../vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Conexion
{
    //ESTA ES LA API KEY DEL SERVIDOR
    private static $secret_key = 'CWdefsJNq0KMJddeMZ!gaaWs3IuxgWdJAXIdl5bBzygLRE3-3FyhqGuwrseppjr9ldmJo4Y?4WVwcb6Lvv4MQ3nO!exF9Ch!XinpigxBq2WT-wSyKdgRUrNbrAorbvipvFx4-M';
    //ESTA ES EL ALGORITMO DE CIFRADO
    private static $algorithm = 'HS256';
    private $server;
    private $user;
    private $password;
    private $database;
    private $port;

    // Instancia única
    private static $instance = null;

    public $conexion;
    public $errno;
    public $error;

    // Constructor privado
    private function __construct()
    {
        $this->iniciarConexion();
    }
    // Método para iniciar o reiniciar la conexión
    private function iniciarConexion()
    {
        // Cargar configuración desde el archivo
        $listadatos = $this->datosConexion();
        foreach ($listadatos as $key => $value) {
            $this->server = $value['server'];
            $this->user = $value['user'];
            $this->password = $value['password'];
            $this->database = $value['database'];
            $this->port = $value['port'];
        }

        // Crear la conexión a MySQL
        $this->conexion = new mysqli($this->server, $this->user, $this->password, $this->database, $this->port);

        // Manejar errores en la conexión
        if ($this->conexion->connect_errno) {
            throw new Exception("Error al conectar a la base de datos: " . $this->conexion->connect_error);
        }
    }

    // Método estático para obtener la instancia única
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Conexion();
        }
        return self::$instance;
    }

    private function datosConexion()
    {
        $direccion = dirname(__FILE__);
        $jsondata = file_get_contents("../../config/conexion.json");
        return json_decode($jsondata, true);
    }

    public function datos($query)
    {
        if ($this->conexion->errno) {
            $this->errno = $this->conexion->errno;
            return 0;
        }
        $result = $this->conexion->query($query);
        if ($this->conexion->error) {
            $this->error = $this->conexion->error;
            return 0;
        }
        return $result;
    }

    public function datosPost($query)
    {
        if ($this->conexion->errno) {
            $this->errno = $this->conexion->errno;
            return 0;
        }
        $result = $this->conexion->query($query);
        if ($this->conexion->error) {
            $this->error = $this->conexion->error;
            return 0;
        }
        return $this->conexion->insert_id;
    }

    public function utf8($array)
    {
        array_walk_recursive($array, function ($item, $key) {
            if (!mb_detect_encoding($item, 'utf-8', true)) {
                $item = utf8_encode($item);
            }
        });
        return $array;
    }

    public function sanitizar($datos, $conexion)
    {
        // Sanitizar primero los datos y luego usar mysqli_real_escape_string
        $datos = trim(strip_tags($datos ?? "")); // Eliminar espacios en blanco y etiquetas HTML
        $datos = htmlspecialchars($datos, ENT_QUOTES, 'UTF-8'); // Escapar caracteres especiales de HTML
        // Asegurarse de que la conexión esté activa antes de usar mysqli_real_escape_string
        if ($this->conexion) {
            return mysqli_real_escape_string($conexion, $datos);
        } else {
            return false;
        }
    }
    // Método para cerrar la conexión
    public function close()
    {
        if ($this->conexion) {
            $this->conexion->close();
            self::$instance = null; // Reinicia la instancia para la próxima solicitud
        }
    }
    // Método para obtener la conexión activa
    public function getConexion()
{
    // Verificar si existe o no la conexión
    if (!($this->conexion instanceof mysqli)) {
        // Intentar iniciar la conexión
        $this->iniciarConexion();
    } else {
        // Ya tenemos un objeto mysqli, verificar su validez
        if (!$this->conexion->ping()) {
            // Si ping falla, intentar reconectar una vez
            $this->iniciarConexion();

            // Comprobar inmediatamente si ahora sí funciona
            if (!$this->conexion instanceof mysqli || !$this->conexion->ping()) {
                // Si seguimos sin poder hacer ping, lanzar excepción
                throw new Exception("No se pudo restablecer la conexión a la base de datos.");
            }
        }
    }

    return $this->conexion;
}

    // Método para reemplazar y obtener la conexión actual
    public function setConexion($conexion)
    {
        $this->conexion = $conexion;
    }

    //crear jwt 180 dias
    static public function jwt($id, $email)
    {
        $time = time(); // Devuelve la fecha Unix actual
        $token = array(
            "iat" => $time, // Tiempo en que inicia el token
            "exp" => $time + (60 * 60 * 24 * 180), // Tiempo en el que expira el token (180 días)
            "data" => [
                "id" => $id,
                "email" => $email
            ]
        );

        $jwt = JWT::encode($token, self::$secret_key, self::$algorithm);

        return $jwt;
        //echo '<pre>'; print_r($jwt); echo '</pre>'; // Sirve para saber que nos devuelve el token
    }

    static public function jwtPermanente($id, $email)
    {
        $time = time(); // Devuelve la fecha Unix actual

        // Crear un "JWT ID" único para identificar este token
        $jti = uniqid(); // Genera un ID único para el JWT

        // Crear el payload del JWT
        $token = array(
            "iat" => $time,  // Tiempo de emisión
            "exp" => null,    // Expiración en null para hacerlo "permanente"
            "jti" => $jti,    // El ID único para este token se utilizará para validar el token si lo dan / damos de baja en la base de datos
            "data" => [
                "id" => $id,
                "email" => $email
            ]
        );

        $jwt = JWT::encode($token, self::$secret_key, self::$algorithm);

        return $jwt;
        //echo '<pre>'; print_r($jwt); echo '</pre>'; // Sirve para saber que nos devuelve el token
    }
    /**
     * Verificar JWT
     * @param string $jwt Token JWT recibido
     * @return array|false Devuelve los datos del token si es válido, o false si no lo es
     */
    public static function verifyJwt($jwt)
    {
        try {
            $decoded = JWT::decode($jwt, new Key(self::$secret_key, self::$algorithm));
            return (array) $decoded->data; // Devuelve los datos si el token es válido
        } catch (Exception $e) {
            error_log("Error al verificar JWT: " . $e->getMessage());
            return false; // Token inválido o expirado
        }
    }
}
