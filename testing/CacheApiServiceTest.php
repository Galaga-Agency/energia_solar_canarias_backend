<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/CacheApiService.php';

/** Cache en memoria para probar límites por recurso sin MySQL ni proveedores. */
class CacheApiMemoria extends CacheApiService
{
    public int $reloj = 1_000_000;
    public array $filas = [];

    protected function ahoraMs(): int
    {
        return $this->reloj;
    }

    protected function leer($clave)
    {
        return $this->filas[$clave] ?? null;
    }

    protected function guardar($clave, $proveedor, $ruta, $resp, $ttlSeg, $ahora)
    {
        $this->filas[$clave] = [
            'respuesta' => json_encode($resp),
            'creado_en' => $ahora,
            'ttl_seg' => $ttlSeg,
        ];
        return true;
    }

    protected function pedirTurno($nombre)
    {
        return true;
    }

    protected function soltarTurno($nombre)
    {
    }

    protected function borrarClave(string $clave): void
    {
        unset($this->filas[$clave]);
    }
}

class CacheApiServiceTest extends TestCase
{
    public function testVariantesDeUnaEstacionCompartenCooldownSinMezclarDatos()
    {
        $cache = new CacheApiMemoria(null);
        $llamadas = 0;
        $fetchDia = static function () use (&$llamadas) {
            $llamadas++;
            return ['code' => 0, 'data' => ['periodo' => 'Day']];
        };

        $dia = $cache->recordarConIntervalo('Sigenergy', 'history?id=1&level=Day', 3600, 'history:1', 315, $fetchDia);
        $mismaRuta = $cache->recordarConIntervalo(
            'Sigenergy', 'history?id=1&level=Day', 3600, 'history:1', 315,
            static function () use (&$llamadas) { $llamadas++; return ['code' => 0, 'data' => []]; }
        );
        $semana = $cache->recordarConIntervalo(
            'Sigenergy', 'history?id=1&level=Week', 3600, 'history:1', 315,
            static function () use (&$llamadas) { $llamadas++; return ['code' => 0, 'data' => ['periodo' => 'Week']]; }
        );

        $this->assertSame(1, $llamadas, 'La misma ruta se sirve de su cache y otra variante no vuelve a llamar al proveedor');
        $this->assertSame('Day', $dia['data']['periodo']);
        $this->assertSame('Day', $mismaRuta['data']['periodo']);
        $this->assertSame(1201, $semana['code']);
        $this->assertTrue($semana['_cache']['limitado_localmente']);
        $this->assertSame(315, $semana['_cache']['esperar_seg']);
    }

    public function testOtraVarianteSePuedePedirTrasCumplirElIntervalo()
    {
        $cache = new CacheApiMemoria(null);
        $llamadas = 0;
        $cache->recordarConIntervalo(
            'Sigenergy', 'history?id=1&level=Day', 3600, 'history:1', 315,
            static function () use (&$llamadas) { $llamadas++; return ['code' => 0, 'data' => ['periodo' => 'Day']]; }
        );
        $cache->reloj += 316_000;

        $semana = $cache->recordarConIntervalo(
            'Sigenergy', 'history?id=1&level=Week', 3600, 'history:1', 315,
            static function () use (&$llamadas) { $llamadas++; return ['code' => 0, 'data' => ['periodo' => 'Week']]; }
        );

        $this->assertSame(2, $llamadas);
        $this->assertSame('Week', $semana['data']['periodo']);
        $this->assertFalse($semana['_cache']['obsoleto']);
    }

    public function testFalloDeProveedorTambienAbreCooldownParaNoGolpearOtraVariante()
    {
        $cache = new CacheApiMemoria(null);
        $llamadas = 0;
        $fallo = $cache->recordarConIntervalo(
            'Sigenergy', 'history?id=1&level=Day', 315, 'history:1', 315,
            static function () use (&$llamadas) { $llamadas++; return ['code' => 1201, 'msg' => 'Access restriction', 'data' => []]; },
            static fn(array $r): bool => (int) ($r['code'] ?? -1) === 0
        );
        $otra = $cache->recordarConIntervalo(
            'Sigenergy', 'history?id=1&level=Year', 315, 'history:1', 315,
            static function () use (&$llamadas) { $llamadas++; return ['code' => 0, 'data' => []]; },
            static fn(array $r): bool => (int) ($r['code'] ?? -1) === 0
        );

        $this->assertSame(1, $llamadas);
        $this->assertSame(1201, $fallo['code']);
        $this->assertSame(1201, $otra['code']);
        $this->assertTrue($otra['_cache']['limitado_localmente']);
    }
}
