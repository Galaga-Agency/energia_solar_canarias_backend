<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/../services/ProveedorTokenService.php';

/**
 * Modelo Sungrow (iSolarCloud).
 *
 * Credenciales (url, appkey, secret) desde la BD: proveedores + proveedor_oauth.
 * El token se obtiene y renueva a traves de ProveedorTokenService (login directo
 * con usuario+contraseña -> no caduca nunca). El token V1 va en el BODY de las
 * llamadas, no en la cabecera.
 *
 * Doc API: https://developer-api.isolarcloud.com/
 */
class Sungrow
{
    const NOMBRE = 'Sungrow';

    private $url;
    private $appkey;
    private $secretKey;
    private $tokenService;

    public function __construct()
    {
        $conn = Conexion::getInstance()->getConexion();

        // Cargar credenciales desde la BD
        $stmt = $conn->prepare(
            "SELECT p.url, o.appkey, o.secret_key
             FROM proveedores p
             JOIN proveedor_oauth o ON o.proveedor_id = p.id
             WHERE p.nombre = ? LIMIT 1"
        );
        $nombre = self::NOMBRE;
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $this->url       = $row['url'] ?? 'https://gateway.isolarcloud.eu';
        $this->appkey    = $row['appkey'] ?? '';
        $this->secretKey = $row['secret_key'] ?? '';

        $this->tokenService = new ProveedorTokenService($conn);
    }

    public function getUrl() { return $this->url; }
    public function setUrl($url) { $this->url = $url; }

    public function getAppkey() { return $this->appkey; }
    public function setAppkey($appkey) { $this->appkey = $appkey; }

    public function getSecretKey() { return $this->secretKey; }
    public function setSecretKey($secretKey) { $this->secretKey = $secretKey; }

    public function getTokenService() { return $this->tokenService; }

    /** Devuelve un token valido (lo renueva si hace falta). */
    public function getValidToken() { return $this->tokenService->getValidToken(self::NOMBRE); }
}
