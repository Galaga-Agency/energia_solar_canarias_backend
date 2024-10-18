<?php

class Conexion
{
    private $server;
    private $user;
    private $password;
    private $database;
    private $port;
    public $conexion;
    public $errno;
    public $error;

    function __construct()
    {
        $listadatos = $this->datosConexion();
        foreach ($listadatos as $key => $value) {
            $this->server = $value['server'];
            $this->user = $value['user'];
            $this->password = $value['password'];
            $this->database = $value['database'];
            $this->port = $value['port'];
        }
        $this->conexion = new mysqli($this->server, $this->user, $this->password, $this->database, $this->port);
        if ($this->conexion->connect_errno) {
            $this->errno = $this->conexion->connect_errno;
            $this->error = $this->conexion->connect_error;
        }
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
}
