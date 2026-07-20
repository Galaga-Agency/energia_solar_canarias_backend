<?php
/**
 * ProveedorTokenService
 * ---------------------------------------------------------------------------
 * Gestion centralizada de tokens OAuth de proveedores (Sungrow, Sigenergy).
 *
 *  - getValidToken($nombre): devuelve un access_token valido. Si esta caducado
 *    o proximo a caducar, lo refresca ANTES de devolverlo (refresco perezoso).
 *  - refresh($nombre): fuerza la renovacion y la guarda en BD. Llamalo tambien
 *    cuando el proveedor devuelva 401 (token invalidado antes de tiempo).
 *
 * Estrategia por proveedor:
 *  - Sungrow:   refresh_token rotatorio (guarda el par nuevo en cada refresco).
 *  - Sigenergy: re-autentica con usuario+contraseña (no caducan) -> a prueba de
 *               caidas largas: siempre se puede regenerar sin intervencion.
 */
class ProveedorTokenService
{
    /** @var mysqli */
    private $db;
    /** Margen: si caduca en menos de esto, se refresca de forma preventiva (ms) */
    private $margenMs;

    public function __construct($db, $margenMinutos = 30)
    {
        $this->db = $db;
        $this->margenMs = $margenMinutos * 60 * 1000;
    }

    /** Devuelve un access_token valido para el proveedor, refrescando si hace falta. */
    public function getValidToken($nombre)
    {
        $p = $this->getProveedor($nombre);
        if (!$p) throw new Exception("Proveedor '$nombre' no existe.");

        $ahora = (int) (microtime(true) * 1000);
        $caducaPronto = !$p['expires_at'] || ($p['expires_at'] - $ahora) < $this->margenMs;

        if (empty($p['tokenAuth']) || $caducaPronto) {
            return $this->refresh($nombre);
        }
        return $p['tokenAuth'];
    }

    /**
     * Ejecuta una llamada a la API del proveedor con auto-refresco.
     * $doRequest recibe el access_token y debe devolver [httpCode, mixed $body].
     * Si la respuesta es un error de autenticacion, refresca el token y REINTENTA una vez.
     *
     * OJO: no todos los proveedores usan HTTP 401. Sungrow (iSolarCloud) devuelve
     * HTTP 200 con result_code de token invalido (010 / er_token_login_invalid).
     * Por eso ademas del 401 se comprueba isAuthError() por defecto, o el
     * callback $isAuthError($code, $body) si se pasa uno a medida.
     *
     * Ej:
     *   [$code, $data] = $svc->requestWithAutoRefresh('Sungrow', function($token) use ($url) {
     *       $ch = curl_init($url);
     *       curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$token, ...], CURLOPT_RETURNTRANSFER => true]);
     *       $r = curl_exec($ch);
     *       return [curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode($r, true)];
     *   });
     */
    public function requestWithAutoRefresh($nombre, callable $doRequest, ?callable $isAuthError = null)
    {
        $isAuthError = $isAuthError ?? [$this, 'isAuthError'];

        $token = $this->getValidToken($nombre);
        [$code, $body] = $doRequest($token);

        if ($isAuthError($code, $body)) {
            // Token rechazado pese a parecer valido: forzar refresco y reintentar 1 vez.
            $token = $this->refresh($nombre);
            [$code, $body] = $doRequest($token);
        }
        return [$code, $body];
    }

    /** Heuristica por defecto para detectar un error de token invalido/expirado. */
    public function isAuthError($code, $body)
    {
        if ($code == 401) return true;
        // Codigos de token invalido de iSolarCloud (Sungrow): vienen con HTTP 200.
        // (result_code "1" = exito, NO es error.)
        $rc = is_array($body) ? ($body['result_code'] ?? null) : null;
        if (in_array((string) $rc, ['010', 'er_token_login_invalid'], true)) return true;
        $msg = is_array($body) ? (string) ($body['result_msg'] ?? '') : '';
        if (stripos($msg, 'token') !== false && (stripos($msg, 'invalid') !== false || stripos($msg, 'login') !== false || stripos($msg, 'expire') !== false)) return true;
        return false;
    }

    /** Fuerza el refresco del token del proveedor y lo persiste. Devuelve el access_token nuevo. */
    public function refresh($nombre)
    {
        switch ($nombre) {
            case 'Sungrow':   return $this->refreshSungrow();
            case 'Sigenergy': return $this->refreshSigenergy();
            default: throw new Exception("Refresco no soportado para '$nombre'.");
        }
    }

    // ------------------------------------------------------------------ helpers

    private function getProveedor($nombre)
    {
        $sql = "SELECT p.id, p.account, p.pwd, p.token_id,
                       b.tokenAuth, b.tokenRenovation, b.expires_at,
                       o.appkey, o.secret_key
                FROM proveedores p
                LEFT JOIN bearertoken b ON b.id = p.token_id
                LEFT JOIN proveedor_oauth o ON o.proveedor_id = p.id
                WHERE p.nombre = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function guardarToken($prov, $access, $refresh, $expiresInSec)
    {
        $expAt = (int) (microtime(true) * 1000) + $expiresInSec * 1000;
        if (!empty($prov['token_id'])) {
            $stmt = $this->db->prepare("UPDATE bearertoken SET tokenAuth=?, tokenRenovation=?, expires_at=? WHERE id=?");
            $stmt->bind_param('ssii', $access, $refresh, $expAt, $prov['token_id']);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("INSERT INTO bearertoken (tokenAuth, tokenRenovation, expires_at) VALUES (?,?,?)");
            $stmt->bind_param('ssi', $access, $refresh, $expAt);
            $stmt->execute();
            $tid = $this->db->insert_id;
            $this->db->query("UPDATE proveedores SET token_id=$tid WHERE id=" . (int) $prov['id']);
        }
        return $access;
    }

    private function httpJson($url, $payload, $headers)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        ]);
        $r = curl_exec($ch);
        return [curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode($r, true)];
    }

    // ------------------------------------------------------------------ Sungrow

    /**
     * Sungrow: preferimos el LOGIN DIRECTO (V1) con usuario+contraseña porque no
     * caduca nunca (se regenera desde credenciales, como Sigenergy). El token V1
     * se usa poniendo `token` en el BODY de las llamadas de datos (no Bearer).
     * Si no hubiera usuario/contraseña, cae al refresh_token OAuth (rotatorio).
     */
    private function refreshSungrow()
    {
        $p = $this->getProveedor('Sungrow');

        // --- Via 1 (preferida): login directo con usuario+contraseña ---
        if (!empty($p['account']) && !empty($p['pwd'])) {
            $payload = json_encode([
                'appkey'        => $p['appkey'],
                'user_account'  => $p['account'],
                'user_password' => $p['pwd'],
                'login_type'    => '1',
                'sys_code'      => '901',
            ]);
            [$code, $j] = $this->httpJson('https://gateway.isolarcloud.eu/openapi/login', $payload, [
                'Content-Type: application/json', 'x-access-key: ' . $p['secret_key'], 'sys_code: 901',
            ]);
            $d = $j['result_data'] ?? null;
            if ($code == 200 && ($j['result_code'] ?? null) == '1' && !empty($d['token'])) {
                // El login V1 no devuelve expires_in; guardamos validez conservadora (12h)
                // y el auto-refresco por error (isAuthError) cubre si caduca antes.
                return $this->guardarToken($p, $d['token'], '', 12 * 3600);
            }
            // si el login directo falla, intentamos el fallback OAuth
        }

        // --- Via 2 (fallback): refresh_token OAuth (rotatorio) ---
        if (!empty($p['tokenRenovation'])) {
            $payload = json_encode(['appkey' => $p['appkey'], 'refresh_token' => $p['tokenRenovation']]);
            [$code, $j] = $this->httpJson('https://gateway.isolarcloud.eu/openapi/apiManage/refreshToken', $payload, [
                'Content-Type: application/json', 'x-access-key: ' . $p['secret_key'], 'sys_code: 901',
            ]);
            $d = $j['result_data'] ?? $j;
            if ($code == 200 && !empty($d['access_token'])) {
                return $this->guardarToken($p, $d['access_token'], $d['refresh_token'] ?? $p['tokenRenovation'], (int) ($d['expires_in'] ?? 172799));
            }
        }

        throw new Exception('Sungrow: no se pudo obtener token (login directo y refresh OAuth fallaron).');
    }

    // ------------------------------------------------------------------ Sigenergy

    /**
     * Login OFICIAL de Sigenergy (Openapi "Based On Sigen Account").
     *
     *   POST openapi-eu.sigencloud.com/openapi/auth/login/password
     *   body JSON { username, password }   (contraseña en claro, sin AES)
     *
     * Ojo con dos cosas:
     *   - El dominio es openapi-eu, NO api-eu (ese era el del cliente web, un apaño).
     *   - El campo `data` de la respuesta es un STRING con JSON dentro (doble
     *     codificado): { "tokenType":"Bearer", "accessToken":"...", "expiresIn":43199 }.
     *   - Hace falta User-Agent de navegador: sin el, CloudFront responde 403 antes
     *     de llegar al origen (aunque sea la API oficial).
     *
     * El token dura ~12 h (expiresIn en segundos). Cuando el portal permita cambiar
     * a modo "Based On Key" (base64(AppKey:AppSecret)), solo cambia esta funcion.
     */
    private function refreshSigenergy()
    {
        $p = $this->getProveedor('Sigenergy');
        if (empty($p['account']) || empty($p['pwd'])) {
            throw new Exception('Sigenergy sin account/pwd en BD.');
        }
        $body = json_encode(['username' => $p['account'], 'password' => $p['pwd']]);
        [$code, $j] = $this->httpJson('https://openapi-eu.sigencloud.com/openapi/auth/login/password', $body, [
            'Accept: */*', 'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:152.0) Gecko/20100101 Firefox/152.0',
        ]);
        if ($code == 200 && isset($j['code']) && (int) $j['code'] === 0) {
            $d = is_string($j['data']) ? json_decode($j['data'], true) : ($j['data'] ?? null);
            if (!empty($d['accessToken'])) {
                // Sin refresh_token en este flujo: se re-loguea con usuario/contraseña.
                return $this->guardarToken($p, $d['accessToken'], '', (int) ($d['expiresIn'] ?? 43199));
            }
        }
        throw new Exception('Sigenergy auth oficial fallo (' . $code . '): ' . json_encode($j));
    }
}
