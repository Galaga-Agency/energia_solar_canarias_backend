<?php

require_once './../models/conexion.php';

class PlantasAsociadasDB {
    private $conexion;

    public function __construct() {
        $this->conexion = Conexion::getInstance();
    }

 /**
   * Relacionar un usuario con una planta
    * 
    * @param int $idPlanta El ID de la planta
    * @param int $idUsuario El ID del usuario
    * @param string $proveedor El nombre del proveedor
    * @return array en caso de éxito o false en caso de error
    */
    public function getPlantasAsociadasAlUsuario($idUsuario) {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
    
            $query = "SELECT * FROM plantas_asociadas WHERE usuario_id = ?;";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }
    
            // Vincula el parámetro 'i' para enteros
            $stmt->bind_param('i', $idUsuario);
    
            // Ejecuta la consulta
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
                return false;
            }
    
            // Recoge los resultados de la consulta
            $result = $stmt->get_result();
            $plantas = [];
            while ($row = $result->fetch_assoc()) {
                $plantas[] = $row;
            }
    
            // Cierra la consulta y la conexión
            $stmt->close();
    
            // Devuelve el array de plantas asociadas
            return $plantas;
        } catch (Exception $e) {
            error_log("Error al relacionar usuario y planta: " . $e->getMessage());
            return false;
        }
    }
    /**
 * Verificar si una planta está asociada a un usuario.
 * 
 * @param int $usuarioId El ID del usuario
 * @param int $idPlanta El ID de la planta
 * @param string $proveedor El nombre del proveedor
 * @return bool true en caso de éxito o false en caso de error
 */
public function isPlantasAsociadasAlUsuario($usuarioId, $idPlanta, $proveedor) {
    try {
        $conexion = Conexion::getInstance();
        $conn = $conexion->getConexion();

        $query = "SELECT * FROM plantas_asociadas WHERE usuario_id = ? AND planta_id = ? AND proveedor_id = (SELECT proveedores.id from proveedores WHERE proveedores.nombre = ?);";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta: " . $conn->error);
        }

        // Vincula los parámetros
        $stmt->bind_param('iss', $usuarioId, $idPlanta, $proveedor);

        // Ejecuta la consulta
        if (!$stmt->execute()) {
            throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
        }

        // Recoge el resultado
        $result = $stmt->get_result();

        // Devuelve true si se encontró una fila, false en caso contrario
        $existeAsociacion = $result->num_rows > 0;

        // Cierra la consulta y la conexión
        $stmt->close();

        return $existeAsociacion;

    } catch (Exception $e) {
        error_log("Error al verificar la asociación entre usuario y planta: " . $e->getMessage());
        return false;
    }
} 
}

?>