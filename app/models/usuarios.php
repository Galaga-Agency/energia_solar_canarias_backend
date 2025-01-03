<?php

require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/../utils/respuesta.php";
require_once __DIR__ . "/../DBObjects/usuariosDB.php";


class Usuarios
{
    public $error;
    private $conexion;

    function __construct()
    {
        $this->conexion = Conexion::getInstance();
    }

    // Getter y setter para 'error'
    public function getError()
    {
        return $this->error;
    }

    public function setError($error)
    {
        $this->error = $error;
    }

    // Getter y setter para 'conexion'
    public function getConexion()
    {
        return $this->conexion;
    }

    public function setConexion($conexion)
    {
        $this->conexion = $conexion;
    }
}
