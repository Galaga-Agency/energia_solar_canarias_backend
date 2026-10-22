<?php

require_once __DIR__ . '/../DBObjects/SesionWebDB.php';

class SesionWebInvalida extends RuntimeException {}
class SesionWebEnCurso extends RuntimeException {}

class SesionWebService
{
    public const ACCESO_SEG = 900;
    public const INACTIVIDAD_SEG = 90 * 86400;
    public const ABSOLUTO_SEG = 180 * 86400;

    public function __construct(private ?SesionWebDB $almacen = null)
    {
        $this->almacen = $almacen ?? new SesionWebDB();
    }

    public static function secreto(): string { return bin2hex(random_bytes(32)); }
    public static function secretoValido($token): bool { return is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1; }

    public function crear(int $id): array
    {
        $u = $this->almacen->usuario($id);
        if (!$u) throw new SesionWebInvalida('La cuenta no esta disponible');
        $sid = bin2hex(random_bytes(16)); $secreto = self::secreto(); $ahora = time();
        $expira = $ahora + self::INACTIVIDAD_SEG;
        $this->almacen->crear($id, $sid, SesionWebDB::huella($u), hash('sha256', $secreto), $ahora, $ahora + self::ABSOLUTO_SEG, $expira);
        return $this->resultado($id, $u['email'], $sid, $secreto, $expira);
    }

    /**
     * $preparar recibe el resultado (con el refresh nuevo) DENTRO de la transaccion y
     * antes del COMMIT: ahi se lee el perfil y se pone la cookie. Si lanza, no se
     * consume nada.
     */
    public function renovar($token, ?callable $preparar = null): array
    {
        if (!self::secretoValido($token)) throw new SesionWebInvalida('Sesion no valida');
        $nuevo = self::secreto();
        $resultado = null;
        $r = $this->almacen->rotar(hash('sha256', $token), hash('sha256', $nuevo), time(), self::INACTIVIDAD_SEG,
            function (array $r) use ($nuevo, $preparar, &$resultado) {
                $resultado = $this->resultado($r['usuario_id'], $r['email'], $r['sesion_id'], $nuevo, $r['expira_en']);
                if ($preparar !== null) $preparar($resultado);
            });
        if ($r['estado'] === 'en_curso') throw new SesionWebEnCurso('Renovacion en curso');
        if ($r['estado'] !== 'ok' || $resultado === null) throw new SesionWebInvalida('Sesion no valida');
        return $resultado;
    }

    private function resultado(int $id, string $email, string $sid, string $secreto, int $expira): array
    {
        return ['publico' => [
            'usuario_id' => $id, 'tokenIdentificador' => Conexion::jwtAcceso($id, $email, $sid),
            'expiresIn' => self::ACCESO_SEG, 'expiresAt' => time() + self::ACCESO_SEG,
            'refreshEnabled' => true, 'sessionId' => $sid,
        ], 'refresh' => $secreto, 'refresh_expira' => $expira];
    }

    public function cerrar($token): void
    {
        if (self::secretoValido($token)) $this->almacen->revocarToken(hash('sha256', $token), time());
    }
}
