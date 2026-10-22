<?php

require_once __DIR__ . '/../services/SesionWebService.php';
require_once __DIR__ . '/../utils/respuesta.php';

class SesionWebController
{
    /** JSON obliga a preflight fuera del origen. Origin tambien se comprueba. */
    public static function permitida(): bool
    {
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') return false;
        if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') return false;
        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origen === '') return true; // CLI/app nativa; no es una peticion de formulario.
        $permitidos = [];
        foreach ([$_ENV['FRONTEND_URL'] ?? 'https://app.energiasolarcanarias.com', $_ENV['BACKEND_URL'] ?? 'https://app-backend.energiasolarcanarias.com'] as $url) {
            $p = parse_url($url);
            if (!isset($p['scheme'], $p['host'])) continue;
            $permitidos[] = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        }
        return in_array($origen, $permitidos, true);
    }

    public static function cookieSegura(): bool
    {
        $p = parse_url($_ENV['BACKEND_URL'] ?? 'https://app-backend.energiasolarcanarias.com');
        return !(($p['scheme'] ?? '') === 'http' && in_array($p['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true));
    }

    public static function nombreCookie(): string { return self::cookieSegura() ? '__Host-esc_refresh' : 'esc_refresh_local'; }

    private static function cookie(?string $secreto, int $expira = 0): void
    {
        header('Cache-Control: no-store, private'); header('Pragma: no-cache');
        if (!setcookie(self::nombreCookie(), $secreto ?? '', [
            'expires' => $secreto === null ? time() - 3600 : $expira,
            'path' => '/', 'secure' => self::cookieSegura(), 'httponly' => true, 'samesite' => 'Strict',
        ])) throw new RuntimeException('No se pudo establecer la cookie de sesion');
    }

    public static function emitir(int $id): array
    {
        $servicio = new SesionWebService();
        $r = $servicio->crear($id);
        // Un nuevo login en el mismo navegador sustituye su sesion anterior.
        $servicio->cerrar($_COOKIE[self::nombreCookie()] ?? null);
        self::cookie($r['refresh'], $r['refresh_expira']);
        return $r['publico'];
    }

    public function renovar(): void
    {
        if (!self::permitida()) { $this->responder(403, 'Origen o formato de renovacion no permitido'); return; }
        try {
            require_once __DIR__ . '/AccesoController.php';
            $perfil = null;
            // Perfil y cookie se preparan ANTES de confirmar la rotacion: si algo falla
            // aqui, la transaccion se deshace y la cookie actual sigue valiendo.
            $r = (new SesionWebService())->renovar($_COOKIE[self::nombreCookie()] ?? null, function (array $r) use (&$perfil) {
                $perfil = AccesoController::perfil((int) $r['publico']['usuario_id']);
                if (!$perfil) throw new SesionWebInvalida('La cuenta no esta disponible');
                self::cookie($r['refresh'], $r['refresh_expira']);
            });
            $this->responder(200, 'Sesion renovada', array_merge($perfil, $r['publico']));
        } catch (SesionWebEnCurso $e) {
            header('Retry-After: 1'); $this->responder(409, 'Renovacion en curso');
        } catch (SesionWebInvalida $e) {
            header_remove('Set-Cookie'); self::cookie(null); $this->responder(401, 'Sesion caducada o revocada');
        } catch (Throwable $e) {
            // La rotacion se ha deshecho: que no salga la cookie nueva que ya no vale.
            header_remove('Set-Cookie');
            $this->responder(500, 'No se pudo renovar la sesion');
        }
    }

    public function cerrar(): void
    {
        if (!self::permitida()) { $this->responder(403, 'Origen o formato de cierre no permitido'); return; }
        try {
            (new SesionWebService())->cerrar($_COOKIE[self::nombreCookie()] ?? null);
            self::cookie(null);
            $this->responder(200, 'Sesion cerrada');
        } catch (Throwable $e) { $this->responder(500, 'No se pudo cerrar la sesion'); }
    }

    private function responder(int $codigo, string $mensaje, array $datos = []): void
    {
        header('Content-Type: application/json'); header('Cache-Control: no-store, private'); header('Pragma: no-cache');
        http_response_code($codigo);
        echo json_encode(['status' => $codigo === 200, 'code' => $codigo, 'message' => $mensaje, 'data' => $datos]);
    }
}
