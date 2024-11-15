<?php

require_once './../models/conexion.php';

class LogsDB {
    private $conexion;

    public function __construct() {
        $this->conexion = new Conexion();
    }

/**
 * Obtener los logs con paginación y filtro
 * @param int $page Número de página
 * @param int $limit Número de registros por página
 * @param string $like Cadena para filtrar por mensaje
 * @return array|false Array con los logs o false en caso de error
 */
public function getLogs($page = 1, $limit = 200, $like = '') {
    try {
        $conexion = new Conexion();
        $conn = $conexion->getConexion();
        
        $offset = ($page - 1) * $limit; // Calcula el desplazamiento en base a la página actual

        // Construir la consulta con parámetros dinámicos para LIMIT y OFFSET
        $query = "SELECT * FROM logs 
                  WHERE message LIKE ? 
                  LIMIT $limit OFFSET $offset";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }

        // Preparar el patrón de búsqueda para LIKE
        $likePattern = "%$like%";
        $stmt->bind_param('s', $likePattern); // Enlazar el patrón del LIKE
        
        $stmt->execute();
        $result = $stmt->get_result();

        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }

        $stmt->close();
        $conn->close();
        return $logs;

    } catch (Exception $e) {
        error_log("Error al obtener logs: " . $e->getMessage());
        return false;
    }
}

/**
 * Insertar un log en la base de datos
 * @param int $userId ID del usuario relacionado con el log
 * @param Logs $level Nivel del log (INFO, WARNING, ERROR, etc.)
 * @param string $message Mensaje descriptivo del log
 * @return bool True si la inserción fue exitosa, false en caso de error
 */
public function postLogs(int $userId, Logs $level, string $message): bool {
    try {
        $conexion = new Conexion();
        $conn = $conexion->getConexion();

        // Consulta SQL para insertar el log
        $query = "INSERT INTO `logs` (`usuario_id`, `timestamp`, `level`, `message`) 
                  VALUES (?, NOW(), ?, ?)";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }

        // Enlazar los parámetros (userId, level, message)
        $levelValue = $level->value; // Convertir el enum a string
        $stmt->bind_param('iss', $userId, $levelValue, $message);

        // Ejecutar la consulta
        $result = $stmt->execute();

        // Verificar si la consulta fue exitosa
        if ($result && $stmt->affected_rows > 0) {
            $stmt->close();
            $conn->close();
            return true;
        }

        $stmt->close();
        $conn->close();
        return false;

    } catch (Exception $e) {
        error_log("Error al insertar log: " . $e->getMessage());
        return false;
    }
}


}

?>