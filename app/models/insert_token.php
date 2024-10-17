<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";

class InsertToken extends Conexion
{
    public $respuesta;
    public $error;
    private $dataUsuario;
    private $tokenLogin;
    private $timeTokenLogin;
    private $table = 'usuarios';
    private $usuarioId;

    function __construct($dataUsuario, $tokenLogin, $timeTokenLogin)
    {
        $this->respuesta = new Respuesta;
        $this->error = new Errores;
        $this->dataUsuario = $dataUsuario;
        $this->tokenLogin = $tokenLogin;
        $this->timeTokenLogin = $timeTokenLogin;
        $this->usuarioId = $dataUsuario['id'];
    }

    public function execute()
    {
        try {
            $query = "UPDATE $this->table SET tokenLogin = '$this->tokenLogin', timeTokenLogin = '$this->timeTokenLogin' WHERE id = $this->usuarioId";
            $result = parent::datos($query);
            if ($result) {
                $this->respuesta->success();
                $this->respuesta->message = 'El token ha sido insertado exitosamente';
                return $this->respuesta;
            } else {
                $this->error->_500();
                $this->error->message = 'Error en el modelo insert_token en la consulta SQL de la API';
                return $this->error;
            }
        } catch (\Throwable $th) {
            $this->error->_500($th);
            $this->error->message = 'Error en el modelo insert_token de la API';
            return $this->error;
        }
    }
}
