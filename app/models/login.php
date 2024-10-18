<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";

class Login
{
    public $respuesta;
    public $error;
    private $email;
    private $password;
    private $token;
    private $table = 'usuarios';
    private $datos;
    private $idiomaUsuario;
    private $conexion;

    function __construct($datos, $idiomaUsuario = 'es')
    {
        $this->respuesta = new Respuesta;
        $this->error = new Errores;
        $this->datos = $datos;
        $this->idiomaUsuario = $idiomaUsuario;
        $this->conexion = new Conexion;
    }

    public function userLogin()
    {
        try {

            if (!isset($this->datos['email']) || !isset($this->datos['password'])) {
                $this->error->_400();
                $this->error->message = 'Error en el formato de los datos que has enviado - O no has especificado un dato obligatorio';
                return $this->error;
            } else {
                $usuario = $this->datos['email'];
                $password = $this->datos['password'];
                $usuarioSanitizado = $this->conexion->sanitizar($this->datos['email'], $this->conexion->conexion);
                $passwordSanitizada = $this->conexion->sanitizar($this->datos['password'], $this->conexion->conexion);
                if ($usuarioSanitizado && $passwordSanitizada) {
                    $query = "SELECT * FROM $this->table WHERE email = '$usuarioSanitizado' AND password = '$passwordSanitizada'";
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
                                $dataUsuario['activo'] = $row['activo'];
                                $dataUsuario['tokenLogin'] = $row['tokenLogin'];
                                $dataUsuario['eliminado'] = $row['eliminado'];
                                $dataUsuario['idiomaUsuario'] = $this->idiomaUsuario;
                            }
                            if ($dataUsuario['activo']) {
                                if (!$dataUsuario['eliminado']) {
                                    $this->respuesta->success($dataUsuario);
                                    $this->respuesta->message = 'Login exitoso, el token ha sido enviado al email del usuario con una validez de 5 minutos';
                                    $this->respuesta->pagination = null;
                                    return $this->respuesta;
                                } else {
                                    $this->error->_401();
                                    $this->error->message = 'No autorizado en la API, el usuario está eliminado';
                                    return $this->error;
                                }
                            } else {
                                $this->error->_401();
                                $this->error->message = 'No autorizado en la API, el usuario está inactivo';
                                return $this->error;
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
                } else {
                    $this->error->_500();
                    $this->error->message = 'Error en el modelo Login de la API, al tratar de sanitizar los datos para la consulta SQL de las credenciales del usuario' . '___usuario: ' . $usuario . '___password: ' . $password. '___usuarioSanitizado: ' . $usuarioSanitizado . '___passwordSanitizada: ' . $passwordSanitizada;
                    return $this->error;
                }
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
            return $this->error;
        }
    }
}

/*
//PRUEBAS
$postBody = '{"email": "soporte@galagaagency.com","password": "***REMOVED***","idiomaUsuario": "es"}';
$datos = json_decode($postBody, true);
$login = new Login($datos);
$responseLogin = $login->userLogin();
print_r($responseLogin);
*/
