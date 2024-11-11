<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";
require_once "./../DBObjects/usuariosDB.php";

class Usuarios
{
    public $error;
    private $conexion;

    function __construct()
    {
        $this->conexion = new Conexion;
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
