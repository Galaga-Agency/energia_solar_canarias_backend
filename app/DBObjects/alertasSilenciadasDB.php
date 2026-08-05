<?php

require_once __DIR__ . '/../models/conexion.php';

/**
 * Silenciado de alertas.
 *
 * Los proveedores exponen las alertas en modo lectura, asi que el silenciado es
 * nuestro y se aplica al presentar la lista. La alerta sigue existiendo y sigue
 * llegando del proveedor: silenciar solo dice "no me avises de esta hasta tal
 * dia".
 */
class AlertasSilenciadasDB
{
    /**
     * Silencia una alerta, o actualiza la fecha si ya lo estaba.
     *
     * @param string      $proveedor Slug del proveedor ("goodwe").
     * @param string      $plantaId  Id de planta del proveedor.
     * @param string      $alertaId  Id de alerta del proveedor.
     * @param int         $usuarioId Quien la silencia.
     * @param string|null $hasta     'Y-m-d H:i:s', o null para indefinido.
     * @param string|null $motivo
     * @return bool
     */
    public function silenciar($proveedor, $plantaId, $alertaId, $usuarioId, $hasta = null, $motivo = null)
    {
        try {
            $conn = Conexion::getInstance()->getConexion();

            // ON DUPLICATE KEY: volver a silenciar es mover la fecha, no crear
            // una segunda fila. La UNIQUE de (proveedor, planta, alerta) lo
            // garantiza incluso con dos peticiones a la vez.
            $query = "INSERT INTO alertas_silenciadas
                        (usuario_id, proveedor, planta_id, alerta_id, silenciada_hasta, motivo)
                      VALUES (?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                        usuario_id = VALUES(usuario_id),
                        silenciada_hasta = VALUES(silenciada_hasta),
                        motivo = VALUES(motivo),
                        creado_en = CURRENT_TIMESTAMP";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }
            $stmt->bind_param('isssss', $usuarioId, $proveedor, $plantaId, $alertaId, $hasta, $motivo);

            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
            }
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Error al silenciar alerta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reactiva una alerta silenciada.
     *
     * Borra la fila en lugar de marcarla: una alerta no silenciada es la
     * ausencia de fila, y guardar el historial de silenciados no aporta nada
     * que el log de seguridad no cubra ya.
     *
     * @return bool true aunque no hubiera fila — el estado final es el pedido.
     */
    public function reactivar($proveedor, $plantaId, $alertaId)
    {
        try {
            $conn = Conexion::getInstance()->getConexion();
            $query = "DELETE FROM alertas_silenciadas
                      WHERE proveedor = ? AND planta_id = ? AND alerta_id = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }
            $stmt->bind_param('sss', $proveedor, $plantaId, $alertaId);
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
            }
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Error al reactivar alerta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alertas silenciadas VIGENTES, opcionalmente de una planta.
     *
     * Una fila caducada (silenciada_hasta ya pasado) no se devuelve: la alerta
     * vuelve a avisar sola, sin que nadie tenga que reactivarla a mano. Las de
     * fecha NULL son indefinidas y siempre cuentan.
     *
     * @return array|false
     */
    public function listar($plantaId = null, $proveedor = null)
    {
        try {
            $conn = Conexion::getInstance()->getConexion();

            $query = "SELECT proveedor, planta_id, alerta_id, silenciada_hasta, motivo, usuario_id, creado_en
                      FROM alertas_silenciadas
                      WHERE (silenciada_hasta IS NULL OR silenciada_hasta > NOW())";
            $tipos = '';
            $valores = [];

            if ($plantaId !== null && $plantaId !== '') {
                $query .= " AND planta_id = ?";
                $tipos .= 's';
                $valores[] = $plantaId;
            }
            if ($proveedor !== null && $proveedor !== '') {
                $query .= " AND proveedor = ?";
                $tipos .= 's';
                $valores[] = $proveedor;
            }

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }
            if ($tipos !== '') {
                $stmt->bind_param($tipos, ...$valores);
            }
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $filas = [];
            while ($row = $result->fetch_assoc()) {
                $filas[] = $row;
            }
            $stmt->close();
            return $filas;
        } catch (Exception $e) {
            error_log("Error al listar alertas silenciadas: " . $e->getMessage());
            return false;
        }
    }
}
