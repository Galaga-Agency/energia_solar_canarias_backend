<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/../services/ProveedorTokenService.php';

/**
 * Modelo Sigenergy (SigenCloud).
 *
 * La url (api-eu.sigencloud.com) sale de la BD (proveedores). El token se
 * obtiene/renueva con ProveedorTokenService (re-autenticacion con usuario+
 * contraseña -> no caduca nunca). El token va en la cabecera Authorization: Bearer.
 *
 * Doc/base: https://api-eu.sigencloud.com/
 */
class Sigenergy
{
    const NOMBRE = 'Sigenergy';

    private $url;
    private $tokenService;
    private $conn;

    public function __construct()
    {
        $conn = Conexion::getInstance()->getConexion();
        $this->conn = $conn;

        $nombre = self::NOMBRE;
        $stmt = $conn->prepare("SELECT url FROM proveedores WHERE nombre = ? LIMIT 1");
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Aseguramos barra final para concatenar rutas
        $this->url = rtrim($row['url'] ?? 'https://api-eu.sigencloud.com', '/') . '/';

        $this->tokenService = new ProveedorTokenService($conn);
    }

    public function getUrl() { return $this->url; }
    public function setUrl($url) { $this->url = $url; }

    public function getTokenService() { return $this->tokenService; }

    /** Conexion viva, para servicios que la necesiten (p.ej. la cache de respuestas). */
    public function getConexion() { return $this->conn; }

    /** Devuelve un token valido (lo renueva si hace falta). */
    public function getValidToken() { return $this->tokenService->getValidToken(self::NOMBRE); }
}
