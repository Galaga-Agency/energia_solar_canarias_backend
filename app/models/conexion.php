<?php

require_once __DIR__ . "/../../vendor/autoload.php";

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Conexion
{
    //ESTA ES LA API KEY DEL SERVIDOR
    private static $secret_key;
    //ESTA ES EL ALGORITMO DE CIFRADO
    private static $algorithm;
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
        try {
            // Cargar el archivo .env desde la carpeta config
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../config');
            $dotenv->load();

            // Asignar los valores del .env a las propiedades estáticas
            self::$secret_key = $_ENV['SECRET_KEY'];
            self::$algorithm = $_ENV['ALGORITHM'];
        } catch (Exception $e) {
            echo "Error al cargar el archivo .env";
        }

        $this->iniciarConexion();
    }
    // Método para iniciar o reiniciar la conexión
    private function iniciarConexion()
    {
        // Cargar configuración desde el archivo
        $listadatos = $this->datosConexion();
        foreach ($listadatos as $key => $value) {
            $this->server = $value['server'] ?? '127.0.0.1';
            $this->user = $value['user'] ?? 'test_db';
            $this->password = $value['password'] ?? 'test_user';
            $this->database = $value['database'] ?? 'test_password';
            $this->port = $value['port'] ?? '3306';
        }

        // Crear la conexión a MySQL
        $this->conexion = new mysqli($this->server, $this->user, $this->password, $this->database, $this->port);

        // Manejar errores en la conexión
        if ($this->conexion->connect_errno) {
            throw new Exception("Error al conectar a la base de datos: " . $this->conexion->connect_error);
        }

        /**
         * UTF-8 en la conexión, no solo en las tablas.
         *
         * Sin esto mysqli habla latin1 aunque la tabla sea utf8mb4, y todo lo
         * que lleve tilde o eñe se guarda roto: en `geocodificacion_cache` se
         * veían "Mogán" como "Mog?n", "Santa Brígida" como "Santa Br?gida" y
         * "España" como "Espa?a".
         *
         * No es solo cosmético. Esas direcciones se mandaban así a Google, que
         * no reconoce "Br?gida" como ningún sitio, de modo que la planta se
         * quedaba sin coordenadas y fuera del mapa para siempre — porque un
         * fallo cacheado no se reintenta.
         *
         * Va AQUÍ y no en cada servicio: `Conexion` es un singleton, así que
         * quien lo arregle en su propio sitio llega tarde si otro lo creó
         * antes. Este es el único punto donde se abre la conexión.
         */
        $this->conexion->set_charset('utf8mb4');
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
        $jsondata = file_get_contents(__DIR__ . "/../../config/conexion.json");
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

    //crear jwt 1 hora
    static public function jwtVolatil($id, $email)
    {
        $claveSecretaJWTVolatil = $_ENV['SECRET_KEY_VOLATIL']; //Esta clave es diferente a la clave de JWT normal
        $algorithmJWTVolatil = $_ENV['ALGORITHM_VOLATIL']; //Este algoritmo es diferente al algoritmo de JWT normal
        $time = time(); // Devuelve la fecha Unix actual
        $token = array(
            "iat" => $time, // Tiempo en que inicia el token
            "exp" => $time + 600, // Tiempo en el que expira el token (600 segundos = 10 minutos)
            "volatility" => true, // Marcar el token como volátil
            "data" => [
                "id" => $id,
                "email" => $email
            ]
        );

        $jwt = JWT::encode($token, $claveSecretaJWTVolatil, $algorithmJWTVolatil);

        return $jwt;
        //echo '<pre>'; print_r($jwt); echo '</pre>'; // Sirve para saber que nos devuelve el token
    }

    // Verificar JWT Volatil y si es volatil dejarlo inutilizado al primer uso del token
    public static function verifyJwtVolatil($jwt)
    {
        try {
            $claveSecretaJWTVolatil = $_ENV['SECRET_KEY_VOLATIL']; // Esta clave es diferente a la clave de JWT normal
            $algorithmJWTVolatil = $_ENV['ALGORITHM_VOLATIL']; // Este algoritmo es diferente al algoritmo de JWT normal
            $decoded = JWT::decode($jwt, new Key($claveSecretaJWTVolatil, $algorithmJWTVolatil));

            // Verificamos si la propiedad 'volatility' existe y su valor
            if (isset($decoded->volatility) && $decoded->volatility === true) {
                return $decoded; // Si la propiedad 'volatility' es true, devolvemos true
            } else {
                return false; // Si no existe la propiedad o es false, devolvemos false
            }
        } catch (Exception $e) {
            error_log("Error al verificar JWT Volatil: " . $e->getMessage());
            return false; // Token inválido o expirado
        }
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

    /**
     * JWT de sesion para el acceso por enlace magico: 30 dias.
     *
     * Mas corto que los 180 dias del login con contraseña a proposito. Sin
     * contraseña, quien controle el correo puede pedir un enlace nuevo cuando
     * quiera, asi que alargar la sesion no aporta comodidad: solo alarga la
     * ventana de un token robado.
     *
     * Misma clave y mismo algoritmo que jwt(), asi que el middleware de
     * autenticacion lo valida sin cambios.
     */
    static public function jwtSesion($id, $email)
    {
        $time = time();
        $token = array(
            "iat" => $time,
            "exp" => $time + (60 * 60 * 24 * 30), // 30 dias
            "data" => [
                "id" => $id,
                "email" => $email
            ]
        );

        return JWT::encode($token, self::$secret_key, self::$algorithm);
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
