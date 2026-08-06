<?php

require_once __DIR__ . '/../models/conexion.php';

class ApiAccesosDB {
    private $conexion;

    public function __construct() {
        $this->conexion = Conexion::getInstance();
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
    /**
     * Claves de un usuario, sin exponer la clave en si.
     *
     * La clave solo se ve una vez, al crearla: guardarla y volver a mostrarla
     * convertiria esta pantalla en un sitio donde robar credenciales. Lo que se
     * lista es el nombre, cuando se creo y cuando se uso por ultima vez.
     */
    public function listarPorUsuario($usuarioId) {
        try {
            $conn = $this->conexion->getConexion();
            $query = "SELECT api_accesos_id AS id, nombre, api_scope, creado_en,
                             ultimo_uso, revocado_en
                        FROM api_accesos
                       WHERE usuario_id = ?
                    ORDER BY creado_en DESC";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('i', $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            $filas = [];
            while ($fila = $result->fetch_assoc()) {
                $filas[] = $fila;
            }
            $stmt->close();
            return $filas;
        } catch (Exception $e) {
            error_log("Error en listarPorUsuario: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Revoca una clave. Se marca en vez de borrarse: el registro de cuando
     * existio y cuando se uso es justamente lo que hace falta despues de una
     * filtracion.
     */
    public function revocar($usuarioId, $id) {
        try {
            $conn = $this->conexion->getConexion();
            // usuario_id en el WHERE: sin eso, cualquiera con una sesion podria
            // revocar la clave de otro pasando su id.
            $query = "UPDATE api_accesos
                         SET revocado_en = NOW()
                       WHERE api_accesos_id = ? AND usuario_id = ?
                         AND revocado_en IS NULL";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ii', $id, $usuarioId);
            $stmt->execute();
            $afectadas = $stmt->affected_rows;
            $stmt->close();
            return $afectadas > 0;
        } catch (Exception $e) {
            error_log("Error en revocar: " . $e->getMessage());
            return false;
        }
    }

    public function verificarAccesoApiKey($api_key) {
        try {
            $conn = $this->conexion->getConexion();
            if (!$conn) {
                throw new Exception("Conexión a la base de datos fallida.");
            }
            //Metemos el Token para la validacion
            $api_key = 'Token ' . $api_key;
    
            // revocado_en IS NULL: una clave revocada tiene que dejar de
            // funcionar de inmediato. Antes seguia siendo valida porque la
            // consulta solo miraba la clave.
            $query = "SELECT api_accesos_id, api_scope, usuario_id as userId
                        FROM api_accesos
                       WHERE api_key = ? AND revocado_en IS NULL
                       LIMIT 1";
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

            // Marca de uso, para que el usuario pueda ver si una clave sigue
            // viva antes de revocarla. Sin fallar la peticion si no se puede.
            if ($scope) {
                $touch = $conn->prepare(
                    "UPDATE api_accesos SET ultimo_uso = NOW() WHERE api_accesos_id = ?"
                );
                if ($touch) {
                    $touch->bind_param('i', $scope['api_accesos_id']);
                    $touch->execute();
                    $touch->close();
                }
            }
    
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

            // En la tabla la clave se guarda CON el prefijo ("Token <uuid>"), pero aqui
            // llega pelada, porque getAuthApiScope() ya lo quita al leer la cabecera.
            // Sin volver a ponerlo no casa NUNCA y devuelve false: el usuario de la api
            // key salia como desconocido y no veia ninguna de sus plantas.
            // Es lo mismo que hace verificarAccesoApiKey().
            $api_key = 'Token ' . $api_key;

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

            return $afectados > 0;
        } catch (Exception $e) {
            error_log("Error en invalidarToken: " . $e->getMessage());
            return false;
        }
    }
    public function getApiAccesoPorToken($token) {
    try {
        $conn = $this->conexion->getConexion();
        
        // Preparar la consulta
        $query = "SELECT usuario_id, api_scope FROM api_accesos WHERE api_key = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta: " . $conn->error);
        }

        // Vincular parámetro y ejecutar
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $registro = $result->fetch_assoc();
        $stmt->close();

        if ($registro) {
            return $registro; // Retorna el array con usuario_id, api_scope
        } else {
            return false; // No se encontró el token
        }

    } catch (Exception $e) {
        error_log("Error en getApiAccesoPorToken: " . $e->getMessage());
        return false;
    }
}

}

?>
