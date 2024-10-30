<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";
require_once "../utils/token.php";

class ValidToken
{
    private $respuesta;
    private $error;
    private $dataUsuario;
    private $tokenLogin;
    private $timeTokenLogin;
    private $table = 'usuarios';
    private $id;
    private $token;
    private $tokenEsperado;
    private $valido = false;

    /**
     * =========================================================================
     * ORGANIZACION DEL OBJETO COMO CONSTRUCTOR -> PARAMETROS -> FUNCIONES
     * =========================================================================
     */

    //Constructor que se inicia cada vez que se le llama a la clase
    function __construct()
    {
        $this->respuesta = new Respuesta;
        $this->error = new Errores;
        $this->token = new Token;
    }

    //GETTERS Y SETTERS

    // Getter y Setter para $respuesta
    public function getRespuesta()
    {
        return $this->respuesta;
    }

    public function setRespuesta($respuesta)
    {
        $this->respuesta = $respuesta;
    }

    // Getter y Setter para $error
    public function getError()
    {
        return $this->error;
    }

    public function setError($error)
    {
        $this->error = $error;
    }

    // Getter y Setter para $dataUsuario
    public function getDataUsuario()
    {
        return $this->dataUsuario;
    }

    public function setDataUsuario($dataUsuario)
    {
        $this->dataUsuario = $dataUsuario;
    }

    // Getter y Setter para $tokenLogin
    public function getTokenLogin()
    {
        return $this->tokenLogin;
    }

    public function setTokenLogin($tokenLogin)
    {
        $this->tokenLogin = $tokenLogin;
    }

    // Getter y Setter para $timeTokenLogin
    public function getTimeTokenLogin()
    {
        return $this->timeTokenLogin;
    }

    public function setTimeTokenLogin($timeTokenLogin)
    {
        $this->timeTokenLogin = $timeTokenLogin;
    }

    // Getter y Setter para $table
    public function getTable()
    {
        return $this->table;
    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    // Getter y Setter para $id
    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    // Getter y Setter para $token
    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    // Getter y Setter para $tokenEsperado
    public function getTokenEsperado()
    {
        return $this->tokenEsperado;
    }

    public function setTokenEsperado($tokenEsperado)
    {
        $this->tokenEsperado = $tokenEsperado;
    }

    // Getter y Setter para $valido
    public function isValido()
    {
        return $this->valido;
    }

    public function setValido($valido)
    {
        $this->valido = $valido;
    }

    //FUNCION DE VALIDACION DE TOKEN
    public function execute($id, $token)
    {
        try {
            $this->id = $id;
            // Creamos una nueva conexión (buena práctica para abrir y cerrar peticiones)
            $conexion = new Conexion();
            $conn = $conexion->getConexion();

            
            //Preparar la consulta
            $query = "SELECT usuarios.usuario_id, usuarios.email, clases.nombre as clase, usuarios.movil, usuarios.nombre, usuarios.apellido, usuarios.imagen, token.token_login as tokenLogin, token.time_token_login as timeTokenLogin FROM usuarios 
            inner join clases
            on clases.clase_id = usuarios.clase_id
            inner join token
            on token.usuario_id = usuarios.usuario_id
            WHERE usuarios.usuario_id = ? ORDER BY token.time_token_login DESC LIMIT 1;";

            // Preparamos la consulta
            $stmt = $conn->prepare($query);

            // Ligar parámetros para el marcador (s es de String)
            $stmt->bind_param("s", $id);

            // Ejecutar la consulta
            $stmt->execute();

            // Obtener los resultados
            $result = $stmt->get_result();

            //var_dump(mysqli_fetch_assoc($result), $id);
            if ($result) {
                if ($result->num_rows) {
                    $dataUsuario = [];
                    while ($row = mysqli_fetch_assoc($result)) {
                        $dataUsuario['id'] = $row['usuario_id'];
                        $dataUsuario['email'] = $row['email'];
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
                            //Eliminamos los parametros de la base de datos
                            unset($this->dataUsuario['tokenLogin']);
                            unset($this->dataUsuario['timeTokenLogin']);
                            //Creamos un token que dura 180 dias en la base de datos
                            $this->dataUsuario['tokenIdentificador'] = $token = Conexion::jwt($this->dataUsuario['id'],$this->dataUsuario['email']);
                            $this->respuesta->success($this->dataUsuario);
                            $this->respuesta->message = 'El token aún es válido para el usuario enviado';
                            $this->respuesta->pagination = null;
                            //cuando este el token validado borramos todos los tokens que tiene el usuario
                            $token = new Token();
                            $token->deleteAllTokensUser($this->dataUsuario['id']);
                            return $this->respuesta;
                        } else {
                            //Eliminamos todos los tokens del usuario que ya no sean validos
                            $token = new Token();
                            $token->deleteTokenUser($this->dataUsuario['id']);
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
