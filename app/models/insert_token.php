<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";

class InsertToken
{
    public $respuesta;
    public $error;
    private $dataUsuario;
    private $tokenLogin;
    private $timeTokenLogin;
    private $table = 'usuarios';
    private $usuarioId;
    private $conexion;

    function __construct($dataUsuario, $tokenLogin, $timeTokenLogin)
    {
        $this->respuesta = new Respuesta;
        $this->error = new Errores;
        $this->dataUsuario = $dataUsuario;
        $this->tokenLogin = $tokenLogin;
        $this->timeTokenLogin = $timeTokenLogin;
        $this->usuarioId = $dataUsuario['id'];
        $this->conexion = new Conexion;
    }

    public function execute()
    {
        try {
            $query = "UPDATE $this->table SET tokenLogin = '$this->tokenLogin', timeTokenLogin = '$this->timeTokenLogin' WHERE id = $this->usuarioId";
            $result = $this->conexion->datos($query);
            if ($result) {
                $this->respuesta->success();
                $this->respuesta->message = 'El token ha sido insertado exitosamente';
                $this->respuesta->pagination = null;
                return $this->respuesta;
            } else {
                $this->error->_500();
                $this->error->message = 'Error en el modelo insert_token en la consulta SQL de la API';
                return $this->error;
            }
        } catch (\Throwable $th) {
            $mensajeError = $th->getMessage();
            $archivoError = $th->getFile();
            $lineaError = $th->getLine();
            $trazaError = $th->getTraceAsString();
            $errores = [];
            $errores['mensajeError'] = $mensajeError;
            $errores['archivoError'] = $archivoError;
            $errores['lineaError'] = $lineaError;
            $errores['trazaError'] = $trazaError;
            $this->error->_500($errores);
            $this->error->message = 'Error en el modelo insert_token de la API';
            return $this->error;
        }
    }
}