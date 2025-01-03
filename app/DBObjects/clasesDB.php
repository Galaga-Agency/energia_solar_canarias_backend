<?php

require_once __DIR__ . '/../models/conexion.php';

class ClasesDB {
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
    public function getClases() {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
    
            $query = "SELECT * FROM clases";
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
            $clases = [];
            while ($row = $result->fetch_assoc()) {
                $clases[] = $row;
            }
    
            // Cierra la consulta y la conexión
            $stmt->close();
    
            // Devuelve el array de plantas asociadas
            return $clases;
        } catch (Exception $e) {
            error_log("Error al relacionar usuario y planta: " . $e->getMessage());
            return false;
        }
    }
}

?>