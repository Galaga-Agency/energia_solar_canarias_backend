<?php
require_once './../models/conexion.php';

class UsuariosDB {
    private $conexion;

    public function __construct() {
        $this->conexion = new Conexion();
    }

    /**
     * Obtener todos los usuarios
     * @return array|false Array con los usuarios o false en caso de error
     */
    public function getUsers($page = 1, $limit = 200) {
        try {
            $conexion = new Conexion();
            $conn = $conexion->getConexion();
            
            $offset = ($page - 1) * $limit; // Calcula el desplazamiento en base a la página actual
    
            $query = "SELECT * FROM usuarios LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $limit, $offset); // Bind de los parámetros para LIMIT y OFFSET
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $usuarios = [];
            while ($row = $result->fetch_assoc()) {
                $usuarios[] = $row;
            }
    
            $stmt->close();
            $conn->close();
            return $usuarios;
    
        } catch (Exception $e) {
            error_log("Error al obtener usuarios: " . $e->getMessage());
            return false;
        }
    }    

    /**
     * Agregar un nuevo usuario
     * @param array $data Datos del usuario a insertar
     * @return bool True en caso de éxito, false en caso de error
     */
    public function insertUser($data) {
        try {
            $conexion = new Conexion();
            $conn = $conexion->getConexion();
    
            // Consulta de inserción
            $query = "INSERT INTO usuarios (email, password_hash, clase_id, nombre, apellido, imagen, movil, activo, eliminado)
                      VALUES (?, ?, (SELECT clase_id FROM clases WHERE nombre = ?), ?, ?, ?, ?, ?, ?)";
    
            $stmt = $conn->prepare($query);
    
            // Generar el hash de la contraseña
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
    
            // Convertir booleanos a enteros
            $activo = $data['activo'] ? 1 : 0;
            $eliminado = $data['eliminado'] ? 1 : 0;
    
            // Ligar parámetros a la consulta
            $stmt->bind_param(
                'sssssssii',
                $data['email'],
                $passwordHash,
                $data['clase'],
                $data['nombre'],
                $data['apellido'],
                $data['imagen'],
                $data['movil'],
                $activo,
                $eliminado
            );
    
            // Ejecutar la consulta
            $result = $stmt->execute();
    
            // Cerrar el statement y la conexión
            $stmt->close();
            $conn->close();
            return $result;
    
        } catch (Exception $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            return false;
        }
    }       

    /**
     * Actualizar un usuario existente
     * @param int $id ID del usuario a actualizar
     * @param array $data Datos del usuario a actualizar
     * @return bool True en caso de éxito, false en caso de error
     */
    public function updateUser($id, $data) {
        try {
            // Obtener la conexión de nuevo para asegurar que esté abierta
            $conexion = new Conexion();
            $conn = $conexion->getConexion();
            
            if (!$conn) {
                throw new Exception("Conexión no disponible.");
            }
    
            // Consulta de actualización
            $query = "UPDATE usuarios SET email = ?, password_hash = ?, clase_id = (SELECT clase_id FROM clases WHERE nombre = ?), 
                      nombre = ?, apellido = ?, imagen = ?, movil = ?, activo = ?, eliminado = ? WHERE usuario_id = ?";
    
            $stmt = $conn->prepare($query);
    
            // Generar el hash de la contraseña
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
    
            // Convertir booleanos a enteros
            $activo = $data['activo'] ? 1 : 0;
            $eliminado = $data['eliminado'] ? 1 : 0;
    
            // Ligar parámetros a la consulta
            $stmt->bind_param(
                'sssssssiii',
                $data['email'],
                $passwordHash,
                $data['clase'],
                $data['nombre'],
                $data['apellido'],
                $data['imagen'],
                $data['movil'],
                $activo,
                $eliminado,
                $id
            );
    
            // Ejecutar la consulta
            $result = $stmt->execute();
    
            // Cerrar el statement y la conexión
            $stmt->close();
            $conn->close();
    
            return $result;
    
        } catch (Exception $e) {
            error_log("Error al actualizar usuario: " . $e->getMessage());
            return false;
        }
    }
    
    

    /**
     * Esta funcion "elimina" al user de manera logica es decir cambia el estado de la eliminacion del usuario
     * @param int $id ID del usuario a eliminar
     * @return bool True en caso de éxito, false en caso de error
     */
    public function borrarUser($id) {
        try {
            $conn = $this->conexion->getConexion();
    
            // Consulta para actualizar el estado eliminado a 1
            $query = "UPDATE usuarios SET eliminado = 1 WHERE usuario_id = ?";
    
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id); // Liga el ID del usuario como parámetro
            $result = $stmt->execute();
    
            // Cerrar el statement y la conexión
            $stmt->close();
            $conn->close();
    
            return $result;
    
        } catch (Exception $e) {
            error_log("Error al realizar el borrado lógico del usuario: " . $e->getMessage());
            return false;
        }
    }    
 /**
     * Consultar un usuario
     * @param int $id ID del usuario a consultar
     * @return bool True en caso de que sea admin, false en caso de que no sea admin
     */
    public function getAdmin($id) {
        try {
            $conexion = new Conexion();
            $conn = $conexion->getConexion();
            $query = "SELECT clases.nombre as clase FROM usuarios
                      INNER JOIN clases ON clases.clase_id = usuarios.clase_id
                      WHERE usuarios.usuario_id = ?;";
    
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
    
            if ($result && $row = $result->fetch_assoc()) {
                $clase = $row['clase']; // Obtener el nombre de la clase
                $stmt->close();
                $conn->close();
                
                // Verificar si el usuario es admin
                if (strtolower($clase) === 'admin') {
                    return true;
                }
            }
    
            // Cerrar el statement y la conexión en caso de que no se encuentre la clase o no sea admin
            $stmt->close();
            $conn->close();
            return false;
    
        } catch (Exception $e) {
            error_log("Error al obtener clase del usuario: " . $e->getMessage());
            return false;
        }
    }  
    /**
     * Comprueba que el email no exista en la base de datos
     *  @param string $email Email a verificar
     *  @return bool True en caso de que tenga un usuario con el mismo email, false en caso de que no tenga ese email en la base de datos
     */ 
    public function comprobarUsuario($email) {
        try {
            $conexion = new Conexion();
            $conn = $conexion->getConexion();
            $query = "SELECT COUNT(usuario_id) as usuarios FROM usuarios WHERE email = ?;";
    
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
    
            if ($result && $row = $result->fetch_assoc()) {
                // Si el conteo es mayor que 0, el usuario existe
                if ($row['usuarios'] > 0) {
                    return true;
                }
            }
    
            // Cerrar el statement y la conexión
            $stmt->close();
            $conn->close();
            return false;
    
        } catch (Exception $e) {
            error_log("Error al comprobar usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Comprueba que el email es el mismo que el del usuario que tiene el id
     *  @param string $email Email a verificar
     *  @return bool True en caso de que tenga un usuario con el mismo email, false en caso de que no tenga ese email en la base de datos
     */ 
    public function esMismoUsuario($id, $email) {
        try {
            $conexion = new Conexion();
            $conn = $conexion->getConexion();
    
            // Consulta para verificar si el email pertenece al usuario con el ID proporcionado
            $query = "SELECT usuario_id FROM usuarios WHERE email = ? AND usuario_id = ?";
    
            $stmt = $conn->prepare($query);
            $stmt->bind_param('si', $email, $id);
            $stmt->execute();
            $result = $stmt->get_result();
    
            // Si se encuentra un registro, el email pertenece al usuario con ese ID
            if ($result->num_rows > 0) {
                $stmt->close();
                $conn->close();
                return true;
            }
    
            // Cerrar el statement y la conexión si no se encuentra coincidencia
            $stmt->close();
            $conn->close();
            return false;
    
        } catch (Exception $e) {
            error_log("Error al verificar si el email pertenece al mismo usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Comprueba que el email es el mismo que el del usuario que tiene el id
     *  @param string $email Email a verificar
     *  @return bool True en caso de que tenga un usuario con el mismo email, false en caso de que no tenga ese email en la base de datos
     */ 
    public function verificarEstadoUsuario($id) {
        try {
            $conexion = new Conexion();
            $conn = $conexion->getConexion();
            
            // Consulta para verificar el estado del usuario
            $query = "SELECT activo, eliminado FROM usuarios WHERE usuario_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
    
            // Verificar si se encontró un registro
            if ($result && $row = $result->fetch_assoc()) {
                $stmt->close();
                $conn->close();
    
                // Retornar el estado en función de los campos 'activo' y 'eliminado'
                if ($row['eliminado'] == 1 || $row['activo'] == 0) {
                    return false;
                }else{
                    return true;
                } 
            }
    
            // Cerrar el statement y la conexión si no se encontró el usuario
            $stmt->close();
            $conn->close();
            return null; // O retorna un valor que indique que el usuario no existe
    
        } catch (Exception $e) {
            error_log("Error al verificar el estado del usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Comprueba que la clase por ejemplo admin, usuario etc... existe en la base de datos
     *  @param string $clase verifica que la clase existe
     *  @return bool True en caso de que la clase exista, false en caso de que la clase no exista
     */ 
    public function comprobarClaseExiste($clase) {
        try {
            $conexion = new Conexion();
            $conn = $conexion->getConexion();
    
            // Consulta para verificar si la clase existe
            $query = "SELECT * FROM clases WHERE nombre = ?";
    
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $clase); // Cambiamos a 's' para string
            $stmt->execute();
            $result = $stmt->get_result();
    
            // Verificar si se encontró un registro
            if ($result && $result->num_rows > 0) {
                $stmt->close();
                $conn->close();
                return true; // La clase existe
            }
    
            // Cerrar el statement y la conexión si no se encontró la clase
            $stmt->close();
            $conn->close();
            return false; // La clase no existe
    
        } catch (Exception $e) {
            error_log("Error al verificar si la clase existe: " . $e->getMessage());
            return false;
        }
    }
}
?>
