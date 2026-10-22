<?php

require_once __DIR__ . '/../models/conexion.php';

/** Sesiones revocables y refresh tokens de un solo uso; nunca guarda el secreto. */
class SesionWebDB
{
    private ?mysqli $db = null;
    private function conexion(): mysqli { return $this->db ??= Conexion::getInstance()->getConexion(); }

    private function ejecutar(string $sql, string $tipos = '', array $valores = []): mysqli_stmt
    {
        $st = $this->conexion()->prepare($sql);
        if (!$st) throw new RuntimeException('No se pudo preparar la operacion de sesion');
        if (($tipos !== '' && !$st->bind_param($tipos, ...$valores)) || !$st->execute()) {
            $st->close(); throw new RuntimeException('No se pudo guardar la operacion de sesion');
        }
        return $st;
    }

    private function fila(mysqli_stmt $st): ?array
    {
        try {
            $r = $st->get_result();
            if (!$r) throw new RuntimeException('No se pudo leer la sesion');
            return $r->fetch_assoc() ?: null;
        } finally { $st->close(); }
    }

    public static function huella(array $usuario): string
    {
        return hash('sha256', $usuario['password_hash'] . "\0" . $usuario['email']);
    }

    public function usuario(int $id, bool $actual = false): ?array
    {
        return $this->fila($this->ejecutar('SELECT usuario_id, email, password_hash FROM usuarios WHERE usuario_id=? AND activo=1 AND eliminado=0' . ($actual ? ' LOCK IN SHARE MODE' : ''), 'i', [$id]));
    }

    public function crear(int $id, string $sid, string $huella, string $hash, int $ahora, int $absoluto, int $expira): void
    {
        $db = $this->conexion();
        if (!$db->begin_transaction()) throw new RuntimeException('No se pudo iniciar la sesion');
        try {
            $this->ejecutar('INSERT INTO auth_sesiones (id,usuario_id,credencial_hash,creado_en,ultimo_uso,limite_absoluto) VALUES (?,?,?,?,?,?)',
                'sisiii', [$sid, $id, $huella, $ahora, $ahora, $absoluto])->close();
            $this->insertarToken($hash, $sid, $ahora, $expira);
            if (!$db->commit()) throw new RuntimeException('No se pudo confirmar la sesion');
        } catch (Throwable $e) { $db->rollback(); throw $e; }
    }

    private function insertarToken(string $hash, string $sid, int $ahora, int $expira): void
    {
        $this->ejecutar('INSERT INTO auth_refresh_tokens (token_hash,sesion_id,creado_en,expira_en) VALUES (?,?,?,?)',
            'ssii', [$hash, $sid, $ahora, $expira])->close();
    }

    private function leerSesion(string $sid, bool $bloquear = false): ?array
    {
        return $this->fila($this->ejecutar('SELECT * FROM auth_sesiones WHERE id=?' . ($bloquear ? ' FOR UPDATE' : ''), 's', [$sid]));
    }

    public function vigente(string $sid, int $id, int $ahora, int $inactividad): bool
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $sid)) return false;
        $s = $this->leerSesion($sid);
        $u = $this->usuario($id);
        return $s && $u && (int) $s['usuario_id'] === $id && $s['revocada_en'] === null
            && (int) $s['limite_absoluto'] > $ahora && (int) $s['ultimo_uso'] + $inactividad > $ahora
            && hash_equals($s['credencial_hash'], self::huella($u));
    }

    /**
     * Serializa la familia antes de consumir un token: exactamente un ganador.
     *
     * $antesDeConfirmar se ejecuta DENTRO de la transaccion, con el resultado, antes
     * del COMMIT: ahi el llamante prepara el perfil y la cookie. Si lanza, se deshace
     * todo y el token presentado sigue sin consumir. Antes se confirmaba primero, y un
     * fallo despues (perfil, cabeceras) dejaba al navegador con un token ya gastado:
     * el siguiente intento parecia una reutilizacion y se revocaba la sesion.
     *
     * Reutilizar un token consumido durante los primeros 5 segundos se trata como
     * una carrera legítima y responde 409 sin emitir otro secreto. Pasada esa ventana,
     * se revoca la familia como protección ante la reutilización de un token robado.
     */
    public function rotar(string $hash, string $nuevoHash, int $ahora, int $inactividad, ?callable $antesDeConfirmar = null): array
    {
        $db = $this->conexion();
        if (!$db->begin_transaction()) throw new RuntimeException('No se pudo iniciar la renovacion');
        try {
            $token = $this->fila($this->ejecutar('SELECT sesion_id FROM auth_refresh_tokens WHERE token_hash=?', 's', [$hash]));
            if (!$token) { $db->rollback(); return ['estado' => 'invalida']; }
            $s = $this->leerSesion($token['sesion_id'], true);
            $r = $this->fila($this->ejecutar('SELECT * FROM auth_refresh_tokens WHERE token_hash=? FOR UPDATE', 's', [$hash]));
            $u = $s ? $this->usuario((int) $s['usuario_id'], true) : null;
            $valida = $s && $r && $u && $s['revocada_en'] === null
                && (int) $s['limite_absoluto'] > $ahora && (int) $s['ultimo_uso'] + $inactividad > $ahora
                && (int) $r['expira_en'] > $ahora && hash_equals($s['credencial_hash'], self::huella($u));

            if ($valida && $r['consumido_en'] !== null) {
                // Mismo criterio que antes: en una carrera legitima (dos pestañas a la vez)
                // no se entrega nada; fuera de esa ventana, un token ya usado es robo.
                if ($ahora - (int) $r['consumido_en'] <= 5) {
                    $db->rollback(); return ['estado' => 'en_curso'];
                }
                $this->ejecutar('UPDATE auth_sesiones SET revocada_en=? WHERE id=?', 'is', [$ahora, $s['id']])->close();
                if (!$db->commit()) throw new RuntimeException('No se pudo revocar la sesion');
                return ['estado' => 'invalida'];
            }
            if (!$valida) {
                if ($s) $this->ejecutar('UPDATE auth_sesiones SET revocada_en=? WHERE id=?', 'is', [$ahora, $s['id']])->close();
                if (!$db->commit()) throw new RuntimeException('No se pudo revocar la sesion');
                return ['estado' => 'invalida'];
            }

            $this->ejecutar('UPDATE auth_refresh_tokens SET consumido_en=? WHERE token_hash=?', 'is', [$ahora, $hash])->close();
            $expira = min($ahora + $inactividad, (int) $s['limite_absoluto']);
            $this->insertarToken($nuevoHash, $s['id'], $ahora, $expira);
            $this->ejecutar('UPDATE auth_sesiones SET ultimo_uso=? WHERE id=?', 'is', [$ahora, $s['id']])->close();
            $resultado = ['estado' => 'ok', 'sesion_id' => $s['id'], 'usuario_id' => (int) $s['usuario_id'], 'email' => $u['email'], 'expira_en' => $expira];
            if ($antesDeConfirmar !== null) $antesDeConfirmar($resultado);
            if (!$db->commit()) throw new RuntimeException('No se pudo confirmar la renovacion');
            return $resultado;
        } catch (Throwable $e) { $db->rollback(); throw $e; }
    }

    /** Conserva tokens consumidos mientras la familia pueda vivir para detectar replay. */
    public function limpiarCaducadas(int $ahora): void
    {
        $this->ejecutar('DELETE FROM auth_sesiones WHERE limite_absoluto<? OR ultimo_uso<? OR revocada_en<?',
            'iii', [$ahora - 86400, $ahora - 90 * 86400 - 86400, $ahora - 86400])->close();
    }

    public function revocarToken(string $hash, int $ahora): void
    {
        // Primero se lee la sesion y luego se bloquea solo esa fila: el UPDATE con JOIN
        // tomaba los bloqueos en orden inverso a rotar() y podia acabar en deadlock.
        $t = $this->fila($this->ejecutar('SELECT sesion_id FROM auth_refresh_tokens WHERE token_hash=?', 's', [$hash]));
        if ($t) $this->ejecutar('UPDATE auth_sesiones SET revocada_en=? WHERE id=?', 'is', [$ahora, $t['sesion_id']])->close();
    }
}
