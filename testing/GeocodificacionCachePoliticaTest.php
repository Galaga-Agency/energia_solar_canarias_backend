<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/GeocodificacionCachePolitica.php';

class GeocodificacionCachePoliticaTest extends TestCase
{
    public function testGoogleYFilasAntiguasCaducanALosTreintaDias(): void
    {
        $ahora = strtotime('2026-10-21 12:00:00 UTC');
        $reciente = gmdate('Y-m-d H:i:s', $ahora - 29 * 86400);
        $antigua = gmdate('Y-m-d H:i:s', $ahora - 31 * 86400);

        $this->assertFalse(GeocodificacionCachePolitica::expirada('google', $reciente, $ahora));
        $this->assertTrue(GeocodificacionCachePolitica::expirada('google', $antigua, $ahora));
        $this->assertTrue(GeocodificacionCachePolitica::expirada('legacy', $antigua, $ahora));
    }

    public function testNominatimSeConservaYLosFallosSeReintentanConLimite(): void
    {
        $ahora = strtotime('2026-10-21 12:00:00 UTC');
        $antigua = gmdate('Y-m-d H:i:s', $ahora - 90 * 86400);
        $reciente = gmdate('Y-m-d H:i:s', $ahora - 60);

        $this->assertFalse(GeocodificacionCachePolitica::expirada('nominatim', $antigua, $ahora));
        $this->assertFalse(GeocodificacionCachePolitica::expirada('no_encontrado', $reciente, $ahora));
        $this->assertTrue(GeocodificacionCachePolitica::expirada('no_encontrado', $antigua, $ahora));
        $this->assertTrue(GeocodificacionCachePolitica::expirada('error_temporal', $antigua, $ahora));
    }
}
