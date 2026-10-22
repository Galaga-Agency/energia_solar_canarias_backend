<?php

/** Retención por proveedor según las condiciones de sus datos de geocodificación. */
final class GeocodificacionCachePolitica
{
    private const GOOGLE_TTL_SEG = 30 * 86400;
    private const NO_ENCONTRADO_TTL_SEG = 7 * 86400;
    private const ERROR_TTL_SEG = 300;

    public static function expirada(string $origen, string $consultadoEn, ?int $ahora = null): bool
    {
        $ahora ??= time();
        $timestamp = strtotime($consultadoEn);
        if ($timestamp === false) return true;

        $ttl = match ($origen) {
            'google', 'legacy' => self::GOOGLE_TTL_SEG,
            'no_encontrado' => self::NO_ENCONTRADO_TTL_SEG,
            'error_temporal' => self::ERROR_TTL_SEG,
            'nominatim' => null,
            default => self::ERROR_TTL_SEG,
        };

        return $ttl !== null && $timestamp < $ahora - $ttl;
    }
}
