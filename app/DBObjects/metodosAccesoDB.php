<?php
require_once __DIR__ . "/../models/conexion.php";
require_once __DIR__ . "/../services/MetodosAcceso.php";

/**
 * Metodos de acceso por usuario (tabla usuarios_metodos_acceso).
 *
 * Sin fila = valor por defecto del metodo. Las reglas (que metodos existen, que
 * no se quede nadie sin forma de entrar, la contraseña minima) estan en
 * MetodosAcceso; aqui solo se lee y se escribe.
 */
class MetodosAccesoDB
{
    /** @return array<string, bool> */
    public function obtener(int $usuarioId): array
    {
        $conn = Conexion::getInstance()->getConexion();
        $stmt = $conn->prepare("SELECT metodo, activo FROM usuarios_metodos_acceso WHERE usuario_id = ?");
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $guardado = [];
        while ($fila = $resultado->fetch_assoc()) {
            $guardado[$fila['metodo']] = (bool) $fila['activo'];
        }
        $stmt->close();

        return MetodosAcceso::combinar($guardado);
    }

    public function permitido(int $usuarioId, string $metodo): bool
    {
        return $this->obtener($usuarioId)[$metodo] ?? false;
    }

    /**
     * Cambia un metodo. Si viene contraseña, se guarda en la MISMA transaccion:
     * o quedan las dos cosas o ninguna (no puede quedar el metodo encendido con
     * la contraseña aleatoria de antes).
     */
    public function guardar(int $usuarioId, string $metodo, bool $activo, ?string $password = null): bool
    {
        $conn = Conexion::getInstance()->getConexion();
        $conn->begin_transaction();
        try {
            if ($password !== null) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE usuarios SET password_hash = ? WHERE usuario_id = ?");
                $stmt->bind_param('si', $hash, $usuarioId);
                $stmt->execute();
                $stmt->close();
            }

            $valor = $activo ? 1 : 0;
            $stmt = $conn->prepare(
                "INSERT INTO usuarios_metodos_acceso (usuario_id, metodo, activo) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE activo = VALUES(activo)"
            );
            $stmt->bind_param('isi', $usuarioId, $metodo, $valor);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('MetodosAccesoDB::guardar ' . $usuarioId . ' ' . $metodo . ': ' . $e->getMessage());
            return false;
        }
    }
}
