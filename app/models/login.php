<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../DBObjects/usuariosDB.php';

class Login
{
    private $usuario;
    private $password;
    private $table = 'usuarios';
    private $datos;
    private $idiomaUsuario;
    private $conexion;
    private $dataUsuario;
    private $passwordEsperada;

    function __construct($datos, $idiomaUsuario = 'es')
    {
        $this->datos = $datos;
        $this->idiomaUsuario = $idiomaUsuario;
        $this->conexion = Conexion::getInstance();
    }

    public function userLogin()
    {
        $respuesta = new Respuesta;
        try {

            if (!isset($this->datos['email']) || !isset($this->datos['password'])) {
                $respuesta->_400();
                $respuesta->message = 'Error en el formato de los datos que has enviado - O no has especificado un dato obligatorio';
                return $respuesta;
            } else {
                $this->password = $this->datos['password'];
                $this->usuario = $this->datos['email'];
                // Creamos una nueva conexión (buena práctica para abrir y cerrar peticiones)
                $conexion = Conexion::getInstance();
                $conn = $conexion->getConexion();

                // Definimos la consulta con marcador de posición
                $query = "SELECT usuarios.usuario_id, usuarios.email, usuarios.password_hash, clases.nombre as clase, usuarios.movil,
                    usuarios.nombre, usuarios.apellido, usuarios.imagen, usuarios.activo, usuarios.eliminado
                    FROM usuarios 
                    inner join clases 
                    on clases.clase_id = usuarios.clase_id
                    WHERE email = ?";

                // Preparamos la consulta
                $stmt = $conn->prepare($query);

                // Ligar parámetros para el marcador (s es de String)
                $stmt->bind_param("s", $this->usuario);

                // Ejecutar la consulta
                $stmt->execute();

                // Obtener los resultados
                $result = $stmt->get_result();

                if ($result) {
                    if ($result->num_rows) {
                        $dataUsuario = [];
                        while ($row = mysqli_fetch_assoc($result)) {
                            $dataUsuario['id'] = $row['usuario_id'];
                            $dataUsuario['email'] = $row['email'];
                            $dataUsuario['password'] = $row['password_hash'];
                            $dataUsuario['clase'] = $row['clase'];
                            $dataUsuario['movil'] = $row['movil'];
                            $dataUsuario['nombre'] = $row['nombre'];
                            $dataUsuario['apellido'] = $row['apellido'];
                            $dataUsuario['imagen'] = $row['imagen'];
                            $dataUsuario['activo'] = $row['activo'];
                            $dataUsuario['eliminado'] = $row['eliminado'];
                            $dataUsuario['idiomaUsuario'] = $this->idiomaUsuario;
                        }
                        //mientras no se pasen los datos y se reemplacen
                        while ($this->dataUsuario != $dataUsuario) {
                            $this->dataUsuario = $dataUsuario;
                        }
                        if ($this->dataUsuario['activo']) {
                            if (!$dataUsuario['eliminado']) {
                                $this->passwordEsperada = $this->dataUsuario['password'];
                                if (password_verify($this->password, $this->passwordEsperada)) {
                                    $usuarioLogueado = new UsuariosDB;
                                    $usuarioLogueado->actualizarUltimoLogin($dataUsuario['id']);
                                    // Si la contraseña es correcta, devuelve una respuesta exitosa o genera un token
                                    $this->dataUsuario['password'] = "Información no disponible en esta consulta";
                                    $respuesta->success($this->dataUsuario);
                                    $respuesta->message = 'Login exitoso';

                                    return $respuesta;
                                } else {
                                    $respuesta->_401();
                                    $respuesta->message = 'No autorizado en la API, la contraseña es inválida';
                                    return $respuesta;
                                }
                            } else {
                                $respuesta->_401();
                                $respuesta->message = 'No autorizado en la API, el usuario está eliminado';
                                return $respuesta;
                            }
                        } else {
                            $respuesta->_401();
                            $respuesta->message = 'No autorizado en la API, el usuario está inactivo';
                            return $respuesta;
                        }
                    } else {
                        $respuesta->_401();
                        $respuesta->message = 'No autorizado en la API, el usuario no existe';
                        return $respuesta;
                    }
                } else {
                    $respuesta->_500();
                    $respuesta->message = 'Error en el modelo Login de la API, en la consulta SQL de las credenciales del usuario';
                    return $respuesta;
                }
            }
        } catch (\Throwable $th) {
            $conexion->close();
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
            $respuesta->message = 'Error en el modelo Login de la API';
            return $respuesta;
        }
    }
}

/*
//PRUEBAS
$postBody = '{"email": "soporte@galagaagency.com","password": "Galaga2024!","idiomaUsuario": "es"}';
$datos = json_decode($postBody, true);
$login = new Login($datos);
$responseLogin = $login->userLogin();
print_r($responseLogin);
*/