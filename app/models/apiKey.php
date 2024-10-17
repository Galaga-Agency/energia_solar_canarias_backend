<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";

class ApiKey extends Conexion
{
    public $respuesta;
    public $error;
    private $email;
    private $password;
    private $token;
    private $table = 'usuarios';

    function __construct()
    {
        $this->respuesta = new Respuesta;
        $this->error = new Errores;
        $this->token = new Token;
    }

    public function login($datos)
    {
        try {

            if (!isset($datos['user']) || !isset($datos['password'])) {
                $this->error->_500();
                $this->error->message = 'Error en el formato de los datos que has enviado - O no has especificado un dato obligatorio';
                return $this->error;
            } else {
                $usuario = parent::sanitizar($datos['email']);
                $contrasena = parent::sanitizar($datos['contrasena']);
                $query = "SELECT * FROM $this->table WHERE usuario = '$usuario' AND contrasena = '$contrasena'";
                $result = parent::datos($query);
                if ($result) {
                    if ($result->num_rows) {
                        $dataUsuario = [];
                        while ($row = mysqli_fetch_assoc($result)) {
                            $dataUsuario['id'] = $row['id'];
                            $dataUsuario['email'] = $row['email'];
                            $dataUsuario['contrasena'] = $row['contrasena'];
                            $dataUsuario['cod'] = $row['cod'];
                            $dataUsuario['clase'] = $row['clase'];
                            $dataUsuario['movil'] = $row['movil'];
                            $dataUsuario['nombre'] = $row['nombre'];
                            $dataUsuario['apellido'] = $row['apellido'];
                            $dataUsuario['imagen'] = $row['imagen'];
                            $dataUsuario['activo'] = $row['activo'];
                            $dataUsuario['eliminado'] = $row['eliminado'];
                            $this->respuesta->success($dataUsuario);
                            $this->respuesta->message = 'Login exitoso';
                            return $this->respuesta;
                        }
                    } else {
                        $this->error->_401();
                        $this->error->message = 'No autorizado en la API, las credenciales no son correctas';
                        return $this->error;
                    }
                } else {
                    $this->error->_500();
                    $this->error->message = 'Error en el modelo Login de la API, en la consulta SQL de las credenciales del usuario';
                    return $this->error;
                }
            }
        } catch (\Throwable $th) {
            $this->error->_500($th);
            $this->error->message = 'Error en el modelo Login de la API';
            return $this->error;
        }
    }
}
