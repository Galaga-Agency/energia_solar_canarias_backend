<?php

require_once "../models/conexion.php";
require_once "../utils/respuesta.php";

class Autenticacion
{

    private $usuario;
    private $apiKey;
    private $usuarioEsperado;
    private $apiKeyEsperada;
    public $error;
    public $respuesta;
    public $request;
    private $scope;
    private $table = 'usuarios';
    private $conexion;
    private $dataUsuario;

    function __construct($request)
    {
        $this->error = new Errores;
        $this->respuesta = new Respuesta;
        $this->request = $request;
        $this->conexion = new Conexion;
    }

    public function execute()
    {
        try {
            // Verificar si la cabecera 'usuario' y 'apiKey' están presentes en $_SERVER
            $this->usuario = isset($_SERVER['HTTP_USUARIO']) ? $_SERVER['HTTP_USUARIO'] : null;
            $this->apiKey = isset($_SERVER['HTTP_APIKEY']) ? $_SERVER['HTTP_APIKEY'] : null;

            // Verificar que ambas cabeceras están presentes
            if ($this->usuario && $this->apiKey) {
                $usuarioSanitizado = $this->conexion->sanitizar($this->usuario, $this->conexion->conexion);
                $apiKeySanitizada = $this->conexion->sanitizar($this->apiKey, $this->conexion->conexion);
                if ($usuarioSanitizado && $apiKeySanitizada) {
                    $this->usuario = $usuarioSanitizado;
                    $this->apiKey = $apiKeySanitizada;
                    // Comparar los valores con la base de datos
                    $query = "SELECT * FROM $this->table WHERE email = '$this->usuario'";
                    $result = $this->conexion->datos($query);
                    if ($result) {
                        if ($result->num_rows) {
                            $dataUsuario = [];
                            while ($row = mysqli_fetch_assoc($result)) {
                                $dataUsuario['id'] = $row['id'];
                                $dataUsuario['email'] = $row['email'];
                                $dataUsuario['password'] = $row['password'];
                                $dataUsuario['cod'] = $row['cod'];
                                $dataUsuario['clase'] = $row['clase'];
                                $dataUsuario['movil'] = $row['movil'];
                                $dataUsuario['nombre'] = $row['nombre'];
                                $dataUsuario['apellido'] = $row['apellido'];
                                $dataUsuario['imagen'] = $row['imagen'];
                                $dataUsuario['activo'] = $row['activo'];
                                $dataUsuario['tokenLogin'] = $row['tokenLogin'];
                                $dataUsuario['timeTokenLogin'] = $row['timeTokenLogin'];
                                $dataUsuario['apiKey'] = $row['apiKey'];
                                $dataUsuario['apiScope'] = $row['apiScope'];
                                $dataUsuario['eliminado'] = $row['eliminado'];
                            }
                            $this->dataUsuario = $dataUsuario;
                            if (!$this->dataUsuario['activo'] || $this->dataUsuario['eliminado']) {
                                $this->error->_401();
                                $this->error->message = 'No autorizad@, el usuario al que pertenece la apiKey ha sido inactivado o eliminado. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                                http_response_code($this->error->code);
                                echo json_encode($this->error);
                                die();
                            }
                            if ($this->dataUsuario['apiScope'] == 2) {
                                $scope1 = require_once "../../config/scope_1.php";;
                                foreach ($scope1 as $value) {
                                    if ($this->request == $value) {
                                        $this->error->_401();
                                        $this->error->message = 'No autorizad@, la APIKEY no tiene el alcance suficiente para esta consulta. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                                        http_response_code($this->error->code);
                                        echo json_encode($this->error);
                                        die();
                                    }
                                }
                            }
                            $this->usuarioEsperado = $this->dataUsuario['email'];
                            $this->apiKeyEsperada = $this->dataUsuario['apiKey'];
                            if ($this->usuario === $this->usuarioEsperado && $this->apiKey === $this->apiKeyEsperada) {
                                return;
                            } else {
                                $this->error->_401();
                                $this->error->message = 'No autorizad@, las credenciales de las cabeceras no son válidas. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                                http_response_code($this->error->code);
                                echo json_encode($this->error);
                                die();
                            }
                        } else {
                            $this->error->_401();
                            $this->error->message = 'No autorizad@, las credenciales no son válidas. El usuario de las cabeceras no existe. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                            http_response_code($this->error->code);
                            echo json_encode($this->error);
                            die();
                        }
                    } else {
                        $this->error->_500();
                        $this->error->message = 'Error en el middleware autenticacion de la API, en la consulta SQL de los datos del usuario';
                        http_response_code($this->error->code);
                        echo json_encode($this->error);
                        die();
                    }
                } else {
                    $this->error->_500();
                    $this->error->message = 'Error en la función sanitizar del middlewares autenticación al intentar sanitizar el email y la apiKey';
                    http_response_code($this->error->code);
                    echo json_encode($this->error);
                    die();
                }
            } else {
                $this->error->_401();
                $this->error->message = 'No autorizad@, las credenciales no son válidas. No se reconoce la APIKEY. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                http_response_code($this->error->code);
                echo json_encode($this->error);
                die();
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
            $this->error->message = 'Error en el modelo Login de la API';
            http_response_code($this->error->code);
            echo json_encode($this->error);
            die();
        }
    }
}

/*
//PRUEBAS
$autencicacion = new Autenticacion("userLogin");
$autencicacion->execute();
*/