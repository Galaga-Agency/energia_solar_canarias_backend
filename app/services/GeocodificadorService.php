<?php
require_once __DIR__ . '/../models/conexion.php';

/**
 * Direccion -> coordenadas, para las plantas cuyo proveedor no las da.
 *
 * Sigenergy no expone coordenadas en su Openapi y el detalle de GoodWe tampoco
 * las trae, pero los cinco proveedores dan una direccion. Una planta con
 * direccion tiene que poder salir en el mapa, venga de donde venga.
 *
 * Se apoya en Nominatim (OpenStreetMap): gratuito y sin clave, a cambio de dos
 * condiciones que aqui se respetan —
 *
 *   · User-Agent identificable. Sin el, bloquean la IP.
 *   · Maximo 1 peticion por segundo, y no repetir consultas ya hechas. De ahi
 *     la tabla `geocodificacion_cache` y la pausa entre llamadas.
 *
 * NO reutiliza app/models/Nominatim.php a proposito: aquel hace `die()` cuando
 * la peticion falla o no hay resultado, lo que aqui tumbaria la lista entera de
 * plantas por una sola direccion mala. Aqui un fallo devuelve null y la planta
 * simplemente no sale en el mapa.
 */
class GeocodificadorService
{
    /** Segundos entre llamadas a Nominatim (su politica: maximo 1/s). */
    private const PAUSA_ENTRE_LLAMADAS = 1;

    /** Cuantas direcciones nuevas se resuelven como maximo en una peticion. */
    private const MAXIMO_POR_PETICION = 8;

    private mysqli $db;
    private int $resueltasEstaPeticion = 0;
    /** Cache en memoria, para no releer la tabla dentro de la misma peticion. */
    private array $memoria = [];

    public function __construct()
    {
        $this->db = Conexion::getInstance()->getConexion();
    }

    /**
     * Normaliza antes de hashear: "Calle X, Arucas" y "calle x,  arucas" son la
     * misma consulta y no deben ocupar dos filas ni gastar dos peticiones.
     */
    private function normalizar(string $direccion): string
    {
        $limpia = trim(preg_replace('/\s+/u', ' ', $direccion));
        return mb_strtolower($limpia, 'UTF-8');
    }

    /**
     * Coordenadas de una direccion, o null si no se pueden obtener.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function coordenadas(?string $direccion): ?array
    {
        if ($direccion === null) { return null; }

        $normalizada = $this->normalizar($direccion);
        // Una direccion de tres letras no es una direccion; preguntarlo solo
        // gasta cuota y devuelve cualquier cosa.
        if (mb_strlen($normalizada) < 5) { return null; }

        if (array_key_exists($normalizada, $this->memoria)) {
            return $this->memoria[$normalizada];
        }

        $hash = hash('sha256', $normalizada);

        $cacheada = $this->leerCache($hash);
        if ($cacheada !== false) {
            // Puede ser null: "ya se pregunto y no existe". Se respeta igual,
            // porque si no se reintentaria en cada carga para siempre.
            $this->memoria[$normalizada] = $cacheada;
            return $cacheada;
        }

        // Tope por peticion: un cliente con cuarenta plantas sin coordenadas no
        // puede convertir una carga de pantalla en cuarenta segundos de espera.
        // Las que falten se resolveran en las siguientes cargas, y quedaran
        // cacheadas para siempre.
        if ($this->resueltasEstaPeticion >= self::MAXIMO_POR_PETICION) {
            return null;
        }

        $coordenadas = $this->consultarNominatim($direccion);
        $this->resueltasEstaPeticion++;

        $this->guardarCache($hash, $direccion, $coordenadas);
        $this->memoria[$normalizada] = $coordenadas;

        return $coordenadas;
    }

    /**
     * @return array{lat: float, lng: float}|null|false  false = no cacheada
     */
    private function leerCache(string $hash)
    {
        $stmt = $this->db->prepare(
            "SELECT latitud, longitud FROM geocodificacion_cache
              WHERE direccion_hash = ? LIMIT 1"
        );
        if (!$stmt) { return false; }

        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila) { return false; }
        if ($fila['latitud'] === null || $fila['longitud'] === null) { return null; }

        return ['lat' => (float) $fila['latitud'], 'lng' => (float) $fila['longitud']];
    }

    private function guardarCache(string $hash, string $direccion, ?array $coordenadas): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO geocodificacion_cache (direccion_hash, direccion, latitud, longitud)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE latitud = VALUES(latitud), longitud = VALUES(longitud)"
        );
        if (!$stmt) { return; }

        $lat = $coordenadas['lat'] ?? null;
        $lng = $coordenadas['lng'] ?? null;
        $stmt->bind_param('ssdd', $hash, $direccion, $lat, $lng);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function consultarNominatim(string $direccion): ?array
    {
        // Solo entre llamadas REALES: la primera de la peticion no espera, y
        // las cacheadas no pasan por aqui.
        if ($this->resueltasEstaPeticion > 0) {
            sleep(self::PAUSA_ENTRE_LLAMADAS);
        }

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
             . urlencode($direccion);

        $contexto = stream_context_create([
            'http' => [
                // Obligatorio en su politica de uso; sin el, bloquean la IP.
                'header'        => "User-Agent: ESC-Backend/1.0 (soporte@galagaagency.com)\r\n",
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);

        // @ y no try/catch: file_get_contents avisa con warning, no con
        // excepcion. Un fallo de red no puede tumbar la lista de plantas.
        $respuesta = @file_get_contents($url, false, $contexto);
        if ($respuesta === false) { return null; }

        $datos = json_decode($respuesta, true);
        if (!is_array($datos) || !isset($datos[0]['lat'], $datos[0]['lon'])) {
            return null;
        }

        return ['lat' => (float) $datos[0]['lat'], 'lng' => (float) $datos[0]['lon']];
    }
}
