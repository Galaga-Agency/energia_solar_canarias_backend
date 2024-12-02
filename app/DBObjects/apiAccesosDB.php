<?php

require_once './../models/conexion.php';

class ApiAccesosDB {
    private $conexion;

    public function __construct() {
        $this->conexion = new Conexion();
    }

    /**
     * Crear o actualizar el acceso a la API para un usuario
     * 
     * @param int $usuarioId
     * @param string $api_key
     * @param string $api_scope
     * @return array|false Devuelve el registro completo creado/actualizado o false en caso de error
     */
    public function upsertApiAcceso($usuarioId, $api_key, $api_scope) {
        try {
            //metemos el validador de tipo de token
            $api_key = 'Token ' . $api_key;
            $conn = $this->conexion->getConexion();

            // Verificar si el usuario existe
            $queryCheckUser = "SELECT COUNT(*) as existe FROM usuarios WHERE usuario_id = ?";
            $stmtCheckUser = $conn->prepare($queryCheckUser);
            if (!$stmtCheckUser) {
                throw new Exception("Error al preparar consulta para verificar usuario: " . $conn->error);
            }

            $stmtCheckUser->bind_param('i', $usuarioId);
            $stmtCheckUser->execute();
            $resultCheckUser = $stmtCheckUser->get_result();
            $row = $resultCheckUser->fetch_assoc();

            if ($row['existe'] == 0) {
                throw new Exception("El usuario con ID $usuarioId no existe.");
            }

            $stmtCheckUser->close();

            // Verificar si el acceso ya existe
            $querySelect = "SELECT usuario_id FROM api_accesos WHERE usuario_id = ?";
            $stmtSelect = $conn->prepare($querySelect);
            if (!$stmtSelect) {
                throw new Exception("Error al preparar consulta para verificar token: " . $conn->error);
            }

            $stmtSelect->bind_param('i', $usuarioId);
            $stmtSelect->execute();
            $result = $stmtSelect->get_result();
            $registroExistente = $result->fetch_assoc();

            $stmtSelect->close();

            if ($registroExistente) {
                // Actualizar el registro existente
                $queryUpdate = "UPDATE api_accesos SET api_key = ?, api_scope = ? WHERE usuario_id = ?";
                $stmtUpdate = $conn->prepare($queryUpdate);
                if (!$stmtUpdate) {
                    throw new Exception("Error al preparar consulta para actualizar token: " . $conn->error);
                }

                $stmtUpdate->bind_param('ssi', $api_key, $api_scope, $usuarioId);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                // Obtener el registro actualizado
                $queryGetRecord = "SELECT * FROM `api_accesos` WHERE usuario_id = ?";
                $stmtGetRecord = $conn->prepare($queryGetRecord);
                if (!$stmtGetRecord) {
                    throw new Exception("Error al preparar consulta para obtener registro actualizado: " . $conn->error);
                }

                $stmtGetRecord->bind_param('i', $usuarioId);
                $stmtGetRecord->execute();
                $result = $stmtGetRecord->get_result();
                $registroActualizado = $result->fetch_assoc();

                $stmtGetRecord->close();
                $conn->close();

                return $registroActualizado; // Devuelve el registro actualizado
            } else {
                // Crear un nuevo registro
                $queryInsert = "INSERT INTO api_accesos (usuario_id, api_key, api_scope) VALUES (?, ?, ?)";
                $stmtInsert = $conn->prepare($queryInsert);
                if (!$stmtInsert) {
                    throw new Exception("Error al preparar consulta para crear token: " . $conn->error);
                }

                $stmtInsert->bind_param('iss', $usuarioId, $api_key, $api_scope);
                $stmtInsert->execute();
                $insertId = $stmtInsert->insert_id;
                $stmtInsert->close();

                // Obtener el registro creado
                $queryGetRecord = "SELECT * FROM `api_accesos` WHERE usuario_id = ?";
                $stmtGetRecord = $conn->prepare($queryGetRecord);
                if (!$stmtGetRecord) {
                    throw new Exception("Error al preparar consulta para obtener registro creado: " . $conn->error);
                }

                $stmtGetRecord->bind_param('i', $insertId);
                $stmtGetRecord->execute();
                $result = $stmtGetRecord->get_result();
                $registroCreado = $result->fetch_assoc();

                $stmtGetRecord->close();
                $conn->close();

                return $registroCreado; // Devuelve el registro creado
            }
        } catch (Exception $e) {
            error_log("Error en upsertApiAcceso: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica el acceso de un token y devuelve su scope
     */
    public function verificarAccesoApiKey($api_key) {
        try {
            $conn = $this->conexion->getConexion();
            if (!$conn) {
                throw new Exception("Conexión a la base de datos fallida.");
            }
            //Metemos el Token para la validacion
            $api_key = 'Token ' . $api_key;
    
            $query = "SELECT api_scope, usuario_id as userId FROM api_accesos WHERE api_key = ? LIMIT 1";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error al preparar consulta: " . $conn->error);
            }
    
            $stmt->bind_param('s', $api_key);
            $stmt->execute();
            $result = $stmt->get_result();
            if (!$result) {
                throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
            }
    
            $scope = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
    
            return $scope;
        } catch (Exception $e) {
            error_log("Error en verificarAccesoApiKey: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica el acceso de un token y devuelve su id
     */
    public function devolverIdPorAccesoApiKey($api_key) {
        try {
            $conn = $this->conexion->getConexion();

            $query = "SELECT usuario_id FROM api_accesos WHERE api_key = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error al preparar consulta para verificar token: " . $conn->error);
            }

            $stmt->bind_param('s', $api_key);
            $stmt->execute();
            $result = $stmt->get_result();
            $usuario = $result->fetch_assoc();

            $stmt->close();
            $conn->close();

            return $usuario ? $usuario['usuario_id'] : false;
        } catch (Exception $e) {
            error_log("Error en verificarAccesoApiKey: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalida un token si existe
     */
    public function invalidarToken($usuarioId, $api_key) {
        try {
            $conn = $this->conexion->getConexion();

            $query = "DELETE FROM api_accesos WHERE usuario_id = ? AND api_key = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error al preparar consulta para invalidar token: " . $conn->error);
            }

            $stmt->bind_param('is', $usuarioId, $api_key);
            $stmt->execute();
            $afectados = $stmt->affected_rows;

            $stmt->close();
            $conn->close();

            return $afectados > 0;
        } catch (Exception $e) {
            error_log("Error en invalidarToken: " . $e->getMessage());
            return false;
        }
    }
}

?>
