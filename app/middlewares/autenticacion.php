<?php

require_once "../models/conexion.php";
require_once "../utils/respuesta.php";

class Autenticacion extends Conexion
{

    private $usuario;
    private $apiKey;
    public $error;
    public $respuesta;
    public $request;
    private $scope;
    private $table = 'usuarios';

    function __construct($request)
    {
        $this->error = new Errores;
        $this->respuesta = new Respuesta;
        $this->request = $request;
    }

    public function autenticar()
    {
        // Verificar si la cabecera 'usuario' y 'apiKey' están presentes en $_SERVER
        $this->usuario = isset($_SERVER['HTTP_USUARIO']) ? $_SERVER['HTTP_USUARIO'] : null;
        $this->apiKey = isset($_SERVER['HTTP_APIKEY']) ? $_SERVER['HTTP_APIKEY'] : null;

        // Verificar que ambas cabeceras están presentes
        if ($this->usuario && $this->apiKey) {
            // Aquí podrías comparar con los valores esperados o buscarlos en una base de datos

            $query = "SELECT * FROM $this->table WHERE email = '$this->usuario' AND apiKey = '$this->apiKey'";
            $result = parent::datos($query);
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
                        $dataUsuario['tokenLogin'] = $row['activo'];
                        $dataUsuario['timeTokenLogin'] = $row['activo'];
                        $dataUsuario['apiKey'] = $row['activo'];
                        $dataUsuario['apiScope'] = $row['activo'];
                        $dataUsuario['eliminado'] = $row['eliminado'];
                    }
                    if (!$dataUsuario['activo'] || $dataUsuario['eliminado']) {
                        $this->error->_401();
                        $this->error->message = 'No autorizad@, el usuario ha sido inactivado o eliminado. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                        return $this->error;
                        http_response_code($this->error->code);
                        echo json_encode($this->error);
                        die();
                    }
                    if ($dataUsuario['apiScope'] == 2) {
                        $direccion = dirname(__FILE__);
                        $jsondata = file_get_contents("../" . $direccion . "config" . "/" . "scope_1.json");
                        $scope1 = json_decode($jsondata, true);
                        foreach ($scope1 as $value) {
                            if ($this->request == '$value') {
                                $this->error->_401();
                                $this->error->message = 'No autorizad@, la APIKEY no tiene el alcance suficiente para esta consulta. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                                return $this->error;
                                http_response_code($this->error->code);
                                echo json_encode($this->error);
                                die();
                            }
                        }
                    }
                    return;
                } else {
                    $this->error->_401();
                    $this->error->message = 'No autorizad@, las credenciales no son válidas. No se reconoce la APIKEY. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                    return $this->error;
                    http_response_code($this->error->code);
                    echo json_encode($this->error);
                    die();
                }
            } else {
                $this->error->_500();
                $this->error->message = 'Error en el middleware autenticacion de la API, en la consulta SQL de las credenciales del usuario y la apiKey';
                return $this->error;
            }
        } else {
            $this->error->_401();
            $this->error->message = 'No autorizad@, las credenciales no son válidas. No se reconoce la APIKEY. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
            http_response_code($this->error->code);
            echo json_encode($this->error);
            die();
        }
    }
}
