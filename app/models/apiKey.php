<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";

class ApiKey
{
    private $respuesta;
    private $error;
    private $email;
    private $password;
    private $token;
    private $table = 'usuarios';
    private $conexion;

    /**
     * =========================================================================
     * ORGANIZACION DEL OBJETO COMO CONSTRUCTOR -> PARAMETROS -> FUNCIONES
     * =========================================================================
     */

    function __construct()
    {
        $this->respuesta = new Respuesta;
        $this->error = new Errores;
        $this->token = new Token;
        $this->conexion = new Conexion;
    }
    // Getter y setter para 'email'
    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    // Getter y setter para 'password'
    public function getPassword() {
        return $this->password;
    }

    public function setPassword($password) {
        $this->password = $password;
    }

    // Getter y setter para 'token'
    public function getToken() {
        return $this->token;
    }

    public function setToken($token) {
        $this->token = $token;
    }

    // Getter para 'table' (ya que es una propiedad privada y constante, no es necesario un setter)
    public function getTable() {
        return $this->table;
    }

    // Getter y setter para 'conexion'
    public function getConexion() {
        return $this->conexion;
    }

    public function setConexion($conexion) {
        $this->conexion = $conexion;
    }

    // Getter y setter para 'respuesta'
    public function getRespuesta() {
        return $this->respuesta;
    }

    public function setRespuesta($respuesta) {
        $this->respuesta = $respuesta;
    }

    // Getter y setter para 'error'
    public function getError() {
        return $this->error;
    }

    public function setError($error) {
        $this->error = $error;
    }

    //Funcion de logueo
    /**
     * acceso
     *  HEADER:     KEY         VALUE
     *        - 1: usuario      String
     *        - 2: apiKey       String
     * 
     *  JSON:
     *      {
     *          "email": "string",
     *          "password": "string",
     *          "idiomaUsuario": "string"
     *      }
     */
    public function login($datos)
    {
        try {
            echo $datos;
            if (!isset($datos['user']) || !isset($datos['password'])) {
                $this->error->_500();
                $this->error->message = 'Error en el formato de los datos que has enviado - O no has especificado un dato obligatorio';
                return $this->error;
            } else {

                //recogemos el email y la contraseña
                $usuario = $datos['email'];
                $password = $datos['password'];

                //Sanitizacion y validacion de los datos de entrada
                $usuarioSanitizado = $this->conexion->sanitizar($usuario, $this->conexion->conexion);
                $passwordSanitizada = $this->conexion->sanitizar($password, $this->conexion->conexion);

                //Consulta preparada para evitar inyecciones SQL
                $stmt = $this->conexion->prepare("SELECT * FROM {$this->table} WHERE email = :email AND password = :password");
                $stmt->bind_param(":email", $usuarioSanitizado, PDO::PARAM_STR);
                $stmt->bind_param(":password", $passwordSanitizada, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->get_result();

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
                            $dataUsuario['eliminado'] = $row['eliminado'];
                            $this->respuesta->success($dataUsuario);
                            $this->respuesta->message = 'Login exitoso';
                            $this->respuesta->pagination = null;
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
            $this->conexion->close();
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
