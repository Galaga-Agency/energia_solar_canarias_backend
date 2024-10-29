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
            $conn = $this->conexion->getConexion();
            
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
    public function postUser($data) {
        try {
            $conn = $this->conexion->getConexion();
            $query = "INSERT INTO usuarios (email, password_hash, clase_id, nombre, apellido, imagen, movil, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssisssii", $data['email'], $data['password_hash'], $data['clase_id'], $data['nombre'], $data['apellido'], $data['imagen'], $data['movil'], $data['activo']);
            $result = $stmt->execute();

            $stmt->close();
            $conn->close();
            return $result;

        } catch (Exception $e) {
            error_log("Error al insertar usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar un usuario existente
     * @param int $id ID del usuario a actualizar
     * @param array $data Datos del usuario a actualizar
     * @return bool True en caso de éxito, false en caso de error
     */
    public function putUser($id, $data) {
        try {
            $conn = $this->conexion->getConexion();
            $query = "UPDATE usuarios SET email = ?, password_hash = ?, clase_id = ?, nombre = ?, apellido = ?, imagen = ?, movil = ?, activo = ? WHERE usuario_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssisssiii", $data['email'], $data['password_hash'], $data['clase_id'], $data['nombre'], $data['apellido'], $data['imagen'], $data['movil'], $data['activo'], $id);
            $result = $stmt->execute();

            $stmt->close();
            $conn->close();
            return $result;

        } catch (Exception $e) {
            error_log("Error al actualizar usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un usuario
     * @param int $id ID del usuario a eliminar
     * @return bool True en caso de éxito, false en caso de error
     */
    public function deleteUser($id) {
        try {
            $conn = $this->conexion->getConexion();
            $query = "DELETE FROM usuarios WHERE usuario_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $result = $stmt->execute();

            $stmt->close();
            $conn->close();
            return $result;

        } catch (Exception $e) {
            error_log("Error al eliminar usuario: " . $e->getMessage());
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
            $conn = $this->conexion->getConexion();
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
}
?>
