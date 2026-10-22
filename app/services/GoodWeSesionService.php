<?php

require_once __DIR__ . '/../DBObjects/GoodWeSesionDB.php';
require_once __DIR__ . '/../utils/HttpClient.php';

/** Otra instancia con la misma cuenta acaba de invalidar el token: no se pelea por el. */
class GoodWeSesionEnConflicto extends RuntimeException {}

/** Sesion del portal GoodWe reutilizada por SEMS y SEMS+. */
class GoodWeSesionService
{
    /**
     * Si GoodWe rechaza un token guardado hace menos de esto, es que otra instancia
     * (otro entorno, Bruno, la app de GoodWe) acaba de hacer login con la misma cuenta.
     * Volver a entrar la echaria a ella, y ella a nosotros: un bucle de logins. Se
     * falla y la cache sirve la ultima lectura buena; pasado el plazo se reintenta.
     */
    public const ENFRIAMIENTO_SEG = 60;

    private string $cuentaHash;

    public function __construct(
        private GoodWeSesionDB $almacen,
        private HttpClient $http,
        private string $url,
        private string $cuenta,
        private string $password
    ) {
        $this->cuentaHash = hash('sha256', rtrim($url, '/') . '|' . strtolower(trim($cuenta)));
    }

    public function obtener(): array
    {
        $guardada = $this->almacen->obtener($this->cuentaHash);
        return self::completa($guardada) ? $guardada : $this->renovar();
    }

    /**
     * Con token rechazado: renovar solo si otra peticion no lo ha hecho ya.
     * Sin token: crear una sesion si aun no hay una persistida.
     */
    public function renovar(?string $rechazado = null): array
    {
        $this->almacen->bloquear($this->cuentaHash);
        try {
            // Releer DESPUES del bloqueo: la peticion que esperaba reutiliza
            // el token recien guardado por la anterior sin expulsarla con otro login.
            $actual = $this->almacen->obtener($this->cuentaHash);
            if (self::completa($actual) && ($rechazado === null || $actual['token'] !== $rechazado)) {
                return $actual;
            }
            if ($rechazado !== null && self::completa($actual) && $actual['token'] === $rechazado) {
                $edad = $this->almacen->segundosDesdeGuardado($this->cuentaHash);
                if ($edad !== null && $edad < self::ENFRIAMIENTO_SEG) {
                    throw new GoodWeSesionEnConflicto('GoodWe rechazo una sesion de hace ' . $edad . ' s: otra instancia usa la misma cuenta');
                }
            }
            if (trim($this->cuenta) === '' || $this->password === '') {
                throw new RuntimeException('Faltan las credenciales de GoodWe');
            }

            $crudo = $this->http->post(
                rtrim($this->url, '/') . '/api/v1/Common/CrossLogin',
                ['Content-Type: application/json', 'Token: ' . json_encode(['client' => 'ios', 'version' => 'v2.1.0', 'language' => 'en'])],
                json_encode(['account' => $this->cuenta, 'pwd' => $this->password], JSON_THROW_ON_ERROR)
            );
            $respuesta = json_decode((string) $crudo, true);
            $datos = $respuesta['data'] ?? null;
            if (($respuesta['hasError'] ?? true) !== false || !is_array($datos)) {
                throw new RuntimeException('GoodWe rechazo el inicio de sesion');
            }
            $cabecera = [
                'uid' => $datos['uid'] ?? null,
                'timestamp' => $datos['timestamp'] ?? null,
                'token' => $datos['token'] ?? null,
                'client' => $datos['client'] ?? 'ios',
                'version' => $datos['version'] ?? 'v2.1.0',
                'language' => $datos['language'] ?? 'en',
            ];
            if (!self::completa($cabecera)) {
                throw new RuntimeException('GoodWe devolvio una sesion incompleta');
            }
            // Solo se devuelve el nuevo token si su persistencia ha terminado bien.
            $this->almacen->guardar($this->cuentaHash, $cabecera);
            return $cabecera;
        } finally {
            $this->almacen->liberar($this->cuentaHash);
        }
    }

    private static function completa(?array $sesion): bool
    {
        if (!$sesion || !is_numeric($sesion['timestamp'] ?? null) || (float) $sesion['timestamp'] <= 0) {
            return false;
        }
        foreach (['token', 'uid', 'client', 'version', 'language'] as $clave) {
            if (!is_string($sesion[$clave] ?? null) || trim($sesion[$clave]) === '') {
                return false;
            }
        }
        return true;
    }
}
