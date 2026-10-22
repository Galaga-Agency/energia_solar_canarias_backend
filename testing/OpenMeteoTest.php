<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/OpenMeteo.php';

class OpenMeteoTest extends TestCase
{
    private function respuesta(): array
    {
        return [
            'current' => ['temperature_2m' => 23.5, 'relative_humidity_2m' => 70],
            'daily' => ['weather_code' => [1], 'temperature_2m_max' => [25], 'temperature_2m_min' => [19]],
        ];
    }

    public function testDevuelveFormaOriginalYUsaZonaCanaria(): void
    {
        $urls = [];
        $respuesta = $this->respuesta();
        $clima = new OpenMeteo(
            function (string $url) use (&$urls, $respuesta): array { $urls[] = $url; return $respuesta; },
            null,
            static fn(string $key, int $ttl, callable $fetch, callable $validar): array => $fetch(),
        );

        $raw = json_decode($clima->obtenerClimaCoordenadas(28.1, -15.4), true);
        $this->assertSame($respuesta, $raw);
        $this->assertStringContainsString('timezone=Atlantic%2FCanary', $urls[0]);
        $this->assertStringContainsString('temperature_2m', $urls[0]);
    }

    public function testUsaCacheDeQuinceMinutosPorUbicacion(): void
    {
        $llamadas = 0;
        $ttlVistos = [];
        $memoria = [];
        $respuesta = $this->respuesta();
        $cache = static function (string $key, int $ttl, callable $fetch, callable $validar) use (&$llamadas, &$ttlVistos, &$memoria): array {
            $ttlVistos[] = $ttl;
            if (!isset($memoria[$key])) {
                $valor = $fetch();
                if ($validar($valor)) { $memoria[$key] = $valor; }
                $llamadas++;
            }
            return $memoria[$key];
        };
        $clima = new OpenMeteo(
            static fn(string $url): array => $respuesta,
            null,
            $cache,
        );

        $clima->obtenerClimaCoordenadas(28.1, -15.4);
        $clima->obtenerClimaCoordenadas(28.1, -15.4);

        $this->assertSame(1, $llamadas);
        $this->assertSame([900, 900], $ttlVistos);
    }

    public function testGeocodificaNombresYRechazaCoordenadasInvalidas(): void
    {
        $nombre = null;
        $respuesta = $this->respuesta();
        $clima = new OpenMeteo(
            static fn(string $url): array => $respuesta,
            static function (string $valor) use (&$nombre): array { $nombre = $valor; return ['lat' => 28.1, 'lng' => -15.4]; },
            static fn(string $key, int $ttl, callable $fetch, callable $validar): array => $fetch(),
        );

        $clima->obtenerClima('  Mogán  ');
        $this->assertSame('Mogán', $nombre);
        $this->expectException(InvalidArgumentException::class);
        $clima->obtenerClimaCoordenadas(91, -15.4);
    }
}
