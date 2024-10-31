<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";

class InsertToken
{
    private $dataUsuario;
    private $tokenLogin;
    private $timeTokenLogin;
    private $table = 'token';
    private $usuarioId;
    private $conexion;

    function __construct($dataUsuario, $tokenLogin, $timeTokenLogin)
    {
        $this->dataUsuario = $dataUsuario;
        $this->tokenLogin = $tokenLogin;
        $this->timeTokenLogin = $timeTokenLogin;
        $this->usuarioId = $dataUsuario['id'];
        $this->conexion = new Conexion;
    }

    public function execute()
    {
        $respuesta = new Respuesta;
        try {
            // Crear instancia de la conexion
            $conexion = new Conexion();

            // Obtener la conexion
            $conn = $conexion->getConexion();
            $query = "INSERT INTO `token`( `usuario_id`, `token_login`, `time_token_login`) 
            VALUES (?,?,?)";

            // Preparar la consulta
            $stmt = $conn->prepare($query);

            // Ligar parámetros para los marcadores (s es de String, i de Int)
            $stmt->bind_param("iss", $this->usuarioId, $this->tokenLogin, $this->timeTokenLogin);

            // Ejecutar la consulta
            if ($stmt->execute()) {
                $respuesta->success();
                $respuesta->message = 'El token ha sido insertado exitosamente';
                return $respuesta;
            } else {
                $respuesta->_500();
                $respuesta->message = 'Error en el modelo insert_token en la consulta SQL de la API';
                return $respuesta;
            }
            // Cerrar el statement
            $stmt->close();
            // Cerrar la conexion
            $conexion->close();
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
            $respuesta->_500($errores);
            $respuesta->message = 'Error en el modelo insert_token de la API';
            return $respuesta;
        }
    }
}
