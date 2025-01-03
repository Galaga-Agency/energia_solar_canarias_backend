<?php

require_once __DIR__ . '/../models/conexion.php';

class ProveedoresDB {
    private $conexion;

    public function __construct() {
        $this->conexion = Conexion::getInstance();
    }

    /**
    * Relacionar un usuario con una planta
    * 
    * @return array en caso de éxito o false en caso de error
    */
    public function getTodosProveedores() {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
    
            $query = "SELECT * FROM proveedores;";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            // Ejecuta la consulta
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
                return false;
            }
    
            // Recoge los resultados de la consulta
            $result = $stmt->get_result();
            $proveedores = [];
            while ($row = $result->fetch_assoc()) {
                $proveedores[] = $row;
            }
    
            // Cierra la consulta y la conexión
            $stmt->close();
    
            // Devuelve el array de plantas asociadas
            return $proveedores;
        } catch (Exception $e) {
            error_log("Error al relacionar usuario y planta: " . $e->getMessage());
            return false;
        }
    }
    /**
    * Relacionar un usuario con una planta
    * 
    * @return token
    */
    public function getTokenProveedor($nombreProveedor) {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
    
            $query = "SELECT tokenAuth, tokenRenovation, expires_at from proveedores inner join bearertoken on proveedores.token_id = bearertoken.id where proveedores.nombre = ?;";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $nombreProveedor);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            // Ejecuta la consulta
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
                return false;
            }
    
            // Recoge los resultados de la consulta
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                return false; // No se encontró el proveedor
            }

            $data = $result->fetch_assoc(); // Transformar el resultado en un array asociativo
    
            // Cierra la consulta y la conexión
            $stmt->close();
    
            // Devuelve el array de plantas asociadas
            return $data;
        } catch (Exception $e) {
            error_log("Error al relacionar usuario y planta: " . $e->getMessage());
            return false;
        }
    }
    /**
   * verifica si el token del proveedor existe
    * 
    * @return token
    */
    public function verificarTokenProveedor($nombreProveedor) {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
    
            // Verificar si el proveedor existe
            $query = "SELECT id, token_id FROM proveedores WHERE nombre = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }
    
            $stmt->bind_param("s", $nombreProveedor);
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
            }
    
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                return false; // El proveedor no existe
            }
    
            $row = $result->fetch_assoc();
            $stmt->close();
    
            // Retornar el `token_id` si el proveedor existe, o null si no tiene token asociado
            return $row['token_id'] ?? null;
        } catch (Exception $e) {
            error_log("Error en verificarTokenProveedor: " . $e->getMessage());
            return false;
        }
    }    
    /**
     * Actualiza el token si existe
     */
    public function actualizarToken($tokenId, $token, $tokenRenovation = '', $expires_at = null) {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
    
            $query = "UPDATE bearertoken 
                      SET tokenAuth = ?, tokenRenovation = ?, expires_at = ?, updated_at = NOW() 
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta de actualización: " . $conn->error);
            }
    
            $stmt->bind_param("sssi", $token, $tokenRenovation, $expires_at, $tokenId);
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta de actualización: " . $stmt->error);
            }
    
            $stmt->close();
    
            return true; // Actualización exitosa
        } catch (Exception $e) {
            error_log("Error en actualizarToken: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Inserta el token y se asocia
     */
    public function insertarTokenYAsociar($nombreProveedor, $token, $tokenRenovation = '', $expires_at = null) {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
    
            // Insertar un nuevo token
            $queryInsertToken = "INSERT INTO bearertoken (tokenAuth, tokenRenovation, expires_at, created_at, updated_at) 
                                 VALUES (?, ?, ?, NOW(), NOW())";
            $stmtInsertToken = $conn->prepare($queryInsertToken);
            if (!$stmtInsertToken) {
                throw new Exception("Error en la preparación de la consulta de inserción: " . $conn->error);
            }
    
            $stmtInsertToken->bind_param("ssi", $token, $tokenRenovation, $expires_at);
            if (!$stmtInsertToken->execute()) {
                throw new Exception("Error en la ejecución de la consulta de inserción: " . $stmtInsertToken->error);
            }
    
            // Obtener el ID del token recién insertado
            $newTokenId = $conn->insert_id;
            $stmtInsertToken->close();
    
            // Asociar el nuevo token al proveedor
            $queryUpdateProveedor = "UPDATE proveedores SET token_id = ? WHERE nombre = ?";
            $stmtUpdateProveedor = $conn->prepare($queryUpdateProveedor);
            if (!$stmtUpdateProveedor) {
                throw new Exception("Error en la preparación de la consulta de actualización del proveedor: " . $conn->error);
            }
    
            $stmtUpdateProveedor->bind_param("is", $newTokenId, $nombreProveedor);
            if (!$stmtUpdateProveedor->execute()) {
                throw new Exception("Error en la ejecución de la consulta de actualización del proveedor: " . $stmtUpdateProveedor->error);
            }
    
            $stmtUpdateProveedor->close();
    
            return true; // Inserción y asociación exitosas
        } catch (Exception $e) {
            echo $e->getMessage();
            error_log("Error en insertarTokenYAsociar: " . $e->getMessage());
            return false;
        }
    }  
}
?>