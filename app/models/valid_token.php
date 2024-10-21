<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";
require_once "../utils/token.php";

class ValidToken
{
    public $respuesta;
    public $error;
    private $dataUsuario;
    private $tokenLogin;
    private $timeTokenLogin;
    private $table = 'usuarios';
    private $id;
    private $conexion;
    private $token;
    private $tokenEsperado;
    private $valido = false;

    function __construct()
    {
        $this->respuesta = new Respuesta;
        $this->error = new Errores;
        $this->conexion = new Conexion;
        $this->token = new Token;
    }

    public function execute($id, $token)
    {
        try {
            $this->id = $id;
            $query = "SELECT * FROM $this->table WHERE id = $this->id";
            $result = $this->conexion->datos($query);
            if ($result) {
                if ($result->num_rows) {
                    $dataUsuario = [];
                    while ($row = mysqli_fetch_assoc($result)) {
                        $dataUsuario['id'] = $row['id'];
                        $dataUsuario['email'] = $row['email'];
                        $dataUsuario['cod'] = $row['cod'];
                        $dataUsuario['clase'] = $row['clase'];
                        $dataUsuario['movil'] = $row['movil'];
                        $dataUsuario['nombre'] = $row['nombre'];
                        $dataUsuario['apellido'] = $row['apellido'];
                        $dataUsuario['imagen'] = $row['imagen'];
                        $dataUsuario['tokenLogin'] = $row['tokenLogin'];
                        $dataUsuario['timeTokenLogin'] = $row['timeTokenLogin'];
                    }
                    $this->dataUsuario = $dataUsuario;
                    $this->tokenLogin = $this->dataUsuario['tokenLogin'];
                    $this->tokenEsperado = $token;
                    if ($this->tokenEsperado == $this->tokenLogin) {
                        $this->timeTokenLogin = $this->dataUsuario['timeTokenLogin'];
                        $this->valido = $this->token->isTokenValid($this->dataUsuario['timeTokenLogin']);
                        if ($this->valido) {
                            $this->dataUsuario['tokenLogin'] = 'Información no disponible en esta consulta';
                            $this->dataUsuario['timeTokenLogin'] = 'Información no disponible en esta consulta';
                            $this->respuesta->success($this->dataUsuario);
                            $this->respuesta->message = 'El token aún es válido para el usuario enviado';
                            $this->respuesta->pagination = null;
                            return $this->respuesta;
                        } else {
                            $this->error->_401();
                            $this->error->message = 'No autorizado, el token NO es válido para el usuario enviado';
                            return $this->error;
                        }
                    } else {
                        $this->error->_401();
                        $this->error->message = 'No autorizado, el token enviado NO coincide con el token que tiene asignado el usuario';
                        return $this->error;
                    }
                } else {
                    $this->error->_400();
                    $this->error->message = 'El usuario enviado no existe en la base de datos';
                    return $this->error;
                }
            } else {
                $this->error->_500();
                $this->error->message = 'Error en el modelo insert_token en la consulta SQL de la API, en la función validez, al intentar obtener los datos del usuario para validar el token';
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
