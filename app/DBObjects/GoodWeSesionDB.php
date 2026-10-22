<?php

require_once __DIR__ . '/../models/conexion.php';

/** Guarda la sesion completa y serializa los logins entre procesos/contenedores. */
class GoodWeSesionDB
{
    private ?mysqli $db = null;

    private function conexion(): mysqli
    {
        // La misma conexion debe mantener el bloqueo y toda la transaccion.
        return $this->db ??= Conexion::getInstance()->getConexion();
    }

    /** El router desactiva mysqli_report: comprobar cada resultado explicitamente. */
    private function ejecutar(string $sql, string $tipos = '', array $valores = []): mysqli_stmt
    {
        $stmt = $this->conexion()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la persistencia de GoodWe');
        }
        if (($tipos !== '' && !$stmt->bind_param($tipos, ...$valores)) || !$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('No se pudo ejecutar la persistencia de GoodWe');
        }
        return $stmt;
    }

    private function fila(mysqli_stmt $stmt): ?array
    {
        try {
            $resultado = $stmt->get_result();
            if (!$resultado) {
                throw new RuntimeException('No se pudo leer la persistencia de GoodWe');
            }
            return $resultado->fetch_assoc() ?: null;
        } finally {
            $stmt->close();
        }
    }

    public function obtener(string $cuentaHash): ?array
    {
        $fila = $this->fila($this->ejecutar('SELECT cabecera FROM goodwe_sesiones WHERE cuenta_hash = ?', 's', [$cuentaHash]));
        $datos = $fila ? json_decode($fila['cabecera'], true) : null;
        return is_array($datos) ? $datos : null;
    }

    /** Segundos desde que se guardo la sesion actual, o null si no hay. */
    public function segundosDesdeGuardado(string $cuentaHash): ?int
    {
        $fila = $this->fila($this->ejecutar('SELECT TIMESTAMPDIFF(SECOND, actualizado_en, NOW()) AS seg FROM goodwe_sesiones WHERE cuenta_hash = ?', 's', [$cuentaHash]));
        return $fila ? (int) $fila['seg'] : null;
    }

    public function bloquear(string $cuentaHash): void
    {
        // El nombre de GET_LOCK admite como maximo 64 caracteres.
        $nombre = substr('esc_goodwe_' . $cuentaHash, 0, 64);
        $fila = $this->fila($this->ejecutar('SELECT GET_LOCK(?, 40) AS adquirido', 's', [$nombre]));
        if ((int) ($fila['adquirido'] ?? 0) !== 1) {
            throw new RuntimeException('No se pudo bloquear la renovacion de GoodWe');
        }
    }

    public function liberar(string $cuentaHash): void
    {
        $nombre = substr('esc_goodwe_' . $cuentaHash, 0, 64);
        $this->ejecutar('SELECT RELEASE_LOCK(?)', 's', [$nombre])->close();
    }

    public function guardar(string $cuentaHash, array $cabecera): void
    {
        $conn = $this->conexion();
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('No se pudo iniciar el guardado de GoodWe');
        }
        try {
            // Mantener tambien bearertoken para los consumidores anteriores.
            $proveedor = $this->fila($this->ejecutar("SELECT id, token_id FROM proveedores WHERE nombre = 'GoodWe' LIMIT 1 FOR UPDATE"));
            if (!$proveedor) {
                throw new RuntimeException('GoodWe no esta configurado en proveedores');
            }

            $token = $cabecera['token'];
            $timestamp = (string) $cabecera['timestamp'];
            if ($proveedor['token_id']) {
                $this->ejecutar("UPDATE bearertoken SET tokenAuth = ?, tokenRenovation = '', expires_at = ?, updated_at = NOW() WHERE id = ?",
                    'ssi', [$token, $timestamp, $proveedor['token_id']])->close();
            } else {
                $this->ejecutar("INSERT INTO bearertoken (tokenAuth, tokenRenovation, expires_at) VALUES (?, '', ?)",
                    'ss', [$token, $timestamp])->close();
                $id = $conn->insert_id;
                $this->ejecutar('UPDATE proveedores SET token_id = ? WHERE id = ?', 'ii', [$id, $proveedor['id']])->close();
            }

            $json = json_encode($cabecera, JSON_THROW_ON_ERROR);
            $this->ejecutar('INSERT INTO goodwe_sesiones (cuenta_hash, cabecera) VALUES (?, ?) ON DUPLICATE KEY UPDATE cabecera = VALUES(cabecera), actualizado_en = NOW()',
                'ss', [$cuentaHash, $json])->close();
            if (!$conn->commit()) {
                throw new RuntimeException('No se pudo confirmar el guardado de GoodWe');
            }
        } catch (Throwable $e) {
            $conn->rollback();
            throw new RuntimeException('No se pudo guardar la sesion de GoodWe', 0, $e);
        }
    }
}
