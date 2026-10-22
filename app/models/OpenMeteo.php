<?php

require_once __DIR__ . '/../services/CacheApiService.php';
require_once __DIR__ . '/../models/conexion.php';

/**
 * Adaptador de clima: Open-Meteo en la API pública y coordenadas compartidas.
 *
 * Conserva el payload original (current/daily) porque el frontend lo interpreta
 * directamente. Las respuestas válidas se guardan 15 minutos, coherente con la
 * frecuencia de actualización de la predicción y con un uso razonable del servicio.
 */
class OpenMeteo
{
    private const CACHE_TTL_SEG = 900;
    private const CAMPOS_ACTUALES = 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,rain,showers,snowfall,cloud_cover,wind_speed_10m,wind_direction_10m';
    private const CAMPOS_DIARIOS = 'weather_code,temperature_2m_max,temperature_2m_min,uv_index_max';

    /** @var callable(string): mixed */
    private $solicitar;

    /** @var callable(string): ?array */
    private $geocodificar;

    /** @var callable(string, int, callable, callable): array|null */
    private $usarCache;

    public function __construct(?callable $solicitar = null, ?callable $geocodificar = null, ?callable $usarCache = null)
    {
        $this->solicitar = $solicitar ?? [$this, 'solicitarHttp'];
        $this->geocodificar = $geocodificar ?? static function (string $nombre): ?array {
            require_once __DIR__ . '/../services/GeocodificadorService.php';
            return (new GeocodificadorService())->coordenadas($nombre);
        };
        $this->usarCache = $usarCache;
    }

    public function obtenerClima($localizacion): string
    {
        if (!is_string($localizacion) || trim($localizacion) === '') {
            throw new InvalidArgumentException('Indica una ubicación válida');
        }

        $coordenadas = ($this->geocodificar)(trim($localizacion));
        if (!is_array($coordenadas) || !isset($coordenadas['lat'], $coordenadas['lng'])) {
            throw new InvalidArgumentException('No se pudo localizar la ubicación');
        }

        return $this->obtenerClimaCoordenadas($coordenadas['lat'], $coordenadas['lng']);
    }

    public function obtenerClimaCoordenadas($latitude, $longitude): string
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            throw new InvalidArgumentException('Las coordenadas deben ser numéricas');
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;
        if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidArgumentException('Las coordenadas están fuera de rango');
        }

        $consulta = [
            'latitude' => $lat,
            'longitude' => $lng,
            'current' => self::CAMPOS_ACTUALES,
            'daily' => self::CAMPOS_DIARIOS,
            'timezone' => 'Atlantic/Canary',
        ];
        $ruta = http_build_query($consulta, '', '&', PHP_QUERY_RFC3986);
        $url = 'https://api.open-meteo.com/v1/forecast?' . $ruta;
        $cargar = fn() => ['code' => 200, 'data' => ($this->solicitar)($url)];
        $valida = static fn($respuesta): bool => is_array($respuesta)
            && (int) ($respuesta['code'] ?? -1) === 200
            && is_array($respuesta['data'] ?? null)
            && is_array($respuesta['data']['current'] ?? null)
            && is_array($respuesta['data']['daily'] ?? null);

        if ($this->usarCache !== null) {
            $respuesta = ($this->usarCache)($ruta, self::CACHE_TTL_SEG, $cargar, $valida);
        } else {
            $cache = new CacheApiService(Conexion::getInstance()->getConexion());
            $respuesta = $cache->recordar('open-meteo', $ruta, self::CACHE_TTL_SEG, $cargar, $valida);
        }

        if ((int) ($respuesta['code'] ?? -1) !== 200
            || !is_array($respuesta['data'] ?? null)
            || !isset($respuesta['data']['current'], $respuesta['data']['daily'])) {
            throw new RuntimeException('Open-Meteo no devolvió una predicción válida');
        }

        return json_encode($respuesta['data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    private function solicitarHttp(string $url): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('No se pudo iniciar la consulta de clima');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $cuerpo = curl_exec($curl);
        $estado = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($cuerpo) || $estado < 200 || $estado >= 300) {
            throw new RuntimeException('Open-Meteo no está disponible' . ($error !== '' ? ': ' . $error : ''));
        }

        $datos = json_decode($cuerpo, true);
        if (!is_array($datos) || !isset($datos['current'], $datos['daily'])) {
            throw new RuntimeException('Open-Meteo devolvió una respuesta ilegible');
        }

        return $datos;
    }
}
