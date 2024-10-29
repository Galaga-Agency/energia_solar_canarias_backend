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
                    // Crear instancia de la conexión
                    $conexion = new Conexion();
                     // Obtener la conexión
                    $conn = $conexion->getConexion();

                    //Consulta que verifica si el usuario tiene activo algun token de verificacion

                    $queryVerificarToken = "SELECT COUNT(token.token_id) as total_tokens from token 
                    INNER JOIN usuarios on usuarios.usuario_id = token.usuario_id
                    WHERE usuarios.email = ?;";

                    $stmt = $conn->prepare($queryVerificarToken);

                    // Ligar parámetros para los marcadores (s es de String, i de Int)
                    $stmt->bind_param("s", $this->usuario);

                    //Ejecutar la consulta con los parametros
                    $stmt->execute();

                    $result = $stmt->get_result();

                    //Si el token existe ejecuta esto
                    if ($result) {
                        $row = $result->fetch_assoc();
                        $totalTokens = $row['total_tokens'];
                    
                        // Verificamos si el usuario tiene muchos tokens activos para bloquear el spam o intento de injeccion
                        if ($totalTokens > 5) {
                            $token = new Token();
                            $token->deleteTokenUserPorEmail($this->usuario);
                            $this->error->_401();
                            $this->error->message = 'No autorizad@, has superado el numero de intentos por favor espere 5 minutos para probar mas intentos por favor contactar por favor con soporte@galagaagency.com';
                            http_response_code($this->error->code);
                            echo json_encode($this->error);
                            die();
                        }
                    //Si no hay token retornamos un error de no hay tokens activos
                    }else{
                        $this->error->_401();
                            $this->error->message = 'No autorizad@, no tiene tokens activos en su usuario vuelve a loguearse si tiene dudas por favor contactar por favor con soporte@galagaagency.com';
                            http_response_code($this->error->code);
                            echo json_encode($this->error);
                            die();
                    }

                    // Cerrar el statement y la conexión
                    $stmt->close();

                    //Abrimos la conexion para realizar la segunda consulta
                    $conexion->getConexion();


                    $query = "
                    SELECT usuarios.usuario_id, usuarios.email, usuarios.password_hash, 
                    clases.nombre as clase, usuarios.movil, usuarios.nombre, usuarios.apellido, usuarios.imagen, usuarios.activo, usuarios.eliminado, 
                    api_accesos.api_key as apiKey, api_accesos.api_scope as apiScope
                    FROM {$this->table} 
                    INNER JOIN clases 
                    ON usuarios.clase_id = clases.clase_id 
                    INNER JOIN api_accesos
                    ON api_accesos.usuario_id = usuarios.usuario_id
                    WHERE email = ?;";

                    // Preparar la consulta
                    $stmt = $conn->prepare($query);

                    // Ligar parámetros para los marcadores (s es de String, i de Int)
                    $stmt->bind_param("s", $this->usuario);

                    // Ejecutar la consulta
                    $stmt->execute();

                    // Obtener el resultado
                    $result = $stmt->get_result();

                    if ($result) {
                        if ($result->num_rows) {
                            $dataUsuario = [];
                            while ($row = mysqli_fetch_assoc($result)) {
                                $dataUsuario['usuario_id'] = $row['usuario_id'];
                                $dataUsuario['email'] = $row['email'];
                                $dataUsuario['password_hash'] = $row['password_hash'];
                                $dataUsuario['clase'] = $row['clase'];
                                $dataUsuario['movil'] = $row['movil'];
                                $dataUsuario['nombre'] = $row['nombre'];
                                $dataUsuario['apellido'] = $row['apellido'];
                                $dataUsuario['imagen'] = $row['imagen'];
                                $dataUsuario['activo'] = $row['activo'];
                                $dataUsuario['apiKey'] = $row['apiKey'];
                                $dataUsuario['apiScope'] = $row['apiScope'];
                                $dataUsuario['eliminado'] = $row['eliminado'];
                            }
                            //mientras que los datos no sean iguales
                            while($this->dataUsuario != $dataUsuario){
                            $this->dataUsuario = $dataUsuario;
                            }
                            if (!$this->dataUsuario['activo'] || $this->dataUsuario['eliminado']) {
                                $this->error->_401();
                                $this->error->message = 'No autorizad@, el usuario al que pertenece la apiKey ha sido inactivado o eliminado. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                                http_response_code($this->error->code);
                                echo json_encode($this->error);
                                die();
                            }
                            if ($this->dataUsuario['apiScope'] == "2") {
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