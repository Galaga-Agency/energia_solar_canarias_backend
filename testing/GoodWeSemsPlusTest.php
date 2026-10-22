<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/GoodWeSemsPlus.php';

/**
 * Traduccion de SEMS+ a la forma del portal SEMS antiguo.
 *
 * Lo que se protege es el contrato con el frontend y con precioRealDelCliente():
 * `data.lines[]` con `name`, `label`, `unit` y `xy[{x, y}]`, `components.para`
 * con el id de la planta, y las ventanas de 31 dias / 12 meses / 10 años que
 * el frontend ya recorta. Las formas de SEMS+ estan copiadas de respuestas
 * reales (2026-10-15), reducidas a lo que se lee.
 */
class GoodWeSemsPlusTest extends TestCase
{
    const PLANTA = 'daf8d3b8-5b93-458a-9850-9db9f9d8f947';

    private function curva(array $puntosPorItem): array
    {
        $lista = [];
        foreach ($puntosPorItem as $item => $puntos) {
            $powerData = [];
            foreach ($puntos as $tp => $power) {
                $powerData[] = ['tp' => '2026-10-14 ' . $tp, 'power' => $power];
            }
            $lista[] = ['item' => $item, 'powerData' => $powerData];
        }
        return ['code' => '00000', 'data' => ['dataList' => $lista]];
    }

    public function testElCuerpoDeLaCurvaPideElDiaCompleto(): void
    {
        $c = GoodWeSemsPlus::cuerpoCurva(self::PLANTA, '2026-10-14');

        $this->assertSame(self::PLANTA, $c['stationId']);
        $this->assertSame(['pSystem', 'pConsum', 'pGrid'], $c['items']);
        $this->assertSame('2026-10-14 00:00:00', $c['startTime']);
        $this->assertSame('2026-10-14 23:59:59', $c['endTime']);
    }

    public function testLaCurvaSeAgrupaEnTramosDe15MinutosYEnVatios(): void
    {
        $plus = $this->curva([
            'pSystem' => ['12:00:00' => 10.0, '12:07:00' => 12.0, '12:14:00' => 14.0, '12:15:00' => 9.0],
            'pConsum' => ['12:00:00' => 4.0, '12:15:00' => 5.0],
        ]);

        $r = GoodWeSemsPlus::respuestaCurva(self::PLANTA, $plus);
        $pv = $r['data']['lines'][0];

        $this->assertSame('Producción', $pv['label']);
        $this->assertSame('W', $pv['unit']);
        $this->assertSame([['x' => '12:00', 'y' => 12000.0, 'z' => null], ['x' => '12:15', 'y' => 9000.0, 'z' => null]], $pv['xy']);
        $this->assertSame(4000.0, $r['data']['lines'][1]['xy'][0]['y']);
    }

    public function testLasHorasSinDatoNoSeEnvian(): void
    {
        // SEMS+ rellena el dia entero con null, tambien las horas que aun no han pasado.
        $plus = $this->curva(['pSystem' => ['11:00:00' => 3.0, '18:00:00' => null, '23:59:00' => null]]);

        $xs = array_column(GoodWeSemsPlus::respuestaCurva(self::PLANTA, $plus)['data']['lines'][0]['xy'], 'x');

        $this->assertSame(['11:00'], $xs);
    }

    public function testUnaSerieSinDatoEnUnTramoVaComoNullNoComoCero(): void
    {
        $plus = $this->curva(['pSystem' => ['07:00:00' => null, '08:00:00' => 1.0], 'pConsum' => ['07:00:00' => 2.0, '08:00:00' => 2.0]]);

        $pv = GoodWeSemsPlus::respuestaCurva(self::PLANTA, $plus)['data']['lines'][0];

        $this->assertNull($pv['xy'][0]['y']);
    }

    #[DataProvider('ventanas')]
    public function testLaVentanaEsLaQueDabaElPortalAntiguo($rango, string $dimension, string $desde, string $hasta): void
    {
        $c = GoodWeSemsPlus::cuerpoEstadisticas(self::PLANTA, $rango, '2026-10-15');

        $this->assertSame($dimension, $c['dimension']);
        $this->assertSame($desde, $c['startTime']);
        $this->assertSame($hasta, $c['endTime']);
    }

    public static function ventanas(): array
    {
        return [
            '31 dias' => [2, 'day', '2026-09-15 00:00:00', '2026-10-15 23:59:59'],
            '12 meses' => ['3', 'month', '2025-11-01 00:00:00', '2026-10-31 23:59:59'],
            '10 años' => ['4', 'year', '2017-01-01 00:00:00', '2026-12-31 23:59:59'],
            'palabra mes' => ['mes', 'month', '2025-11-01 00:00:00', '2026-10-31 23:59:59'],
        ];
    }

    private function estadisticas(): array
    {
        $serie = function ($valores) {
            return array_map(fn($d, $v) => ['date' => $d, 'val' => $v], array_keys($valores), $valores);
        };
        return ['code' => '00000', 'data' => [
            'production' => 473.4, 'proGrid' => 244.59, 'proGridRate' => 51.67,
            'proPurchase' => 89.26, 'proPurchaseRate' => 28.06, 'proSelfConsum' => 228.81,
            'proSelfConsumRate' => 48.33, 'proConsum' => 318.07, 'contributionRate' => 71.94,
            'dataList' => [
                ['item' => 'proSystemTotalStats', 'unit' => 'kWh', 'statisticsList' => $serie(['2026-10-12' => 136.5, '2026-10-13' => 150.123])],
                ['item' => 'proSelfConsumStats', 'unit' => 'kWh', 'statisticsList' => $serie(['2026-10-12' => 60.0, '2026-10-13' => 70.0])],
                ['item' => 'proGridStats', 'unit' => 'kWh', 'statisticsList' => $serie(['2026-10-12' => 76.5, '2026-10-13' => 80.0])],
            ],
        ]];
    }

    public function testLaGeneracionSaleComoPVGenerationParaElCalculoDeIngresos(): void
    {
        $r = GoodWeSemsPlus::respuestaEstadisticas(self::PLANTA, '3', $this->estadisticas());

        $linea = $r['data']['lines'][0];
        $this->assertSame('PVGeneration', $linea['name']);
        $this->assertSame('kWh', $linea['unit']);
        $this->assertSame([['x' => '2026-10-12', 'y' => 136.5, 'z' => null], ['x' => '2026-10-13', 'y' => 150.12, 'z' => null]], $linea['xy']);
        // precioRealDelCliente() saca de aqui el id de la planta.
        $this->assertSame(self::PLANTA, json_decode($r['components']['para'], true)['model']['Id']);
    }

    public function testUsoPersonalLlevaAutoconsumoYVertido(): void
    {
        $nombres = array_column(GoodWeSemsPlus::respuestaEstadisticas(self::PLANTA, '5', $this->estadisticas())['data']['lines'], 'name');

        $this->assertSame(['SelfUse', 'Sell'], $nombres);
    }

    public function testEstadisticasDevuelveTotalesConLaContribucionEnFraccion(): void
    {
        $m = GoodWeSemsPlus::respuestaEstadisticas(self::PLANTA, '7', $this->estadisticas())['data']['modelData'];

        $this->assertSame(473.4, $m['sum']);
        $this->assertSame(244.59, $m['sell']);
        $this->assertSame(0.7194, $m['contributionRatio']);
    }

    public function testElFlujoSeTraduceAPowerflowEnVatios(): void
    {
        $f = GoodWeSemsPlus::flujoComoPowerflow(['data' => ['pSystem' => 12.915, 'pGrid' => 6.64, 'pConsum' => 6.275]]);

        $this->assertSame('12915W', $f['pv']);
        $this->assertSame('6640W', $f['grid']);
        $this->assertSame('6275W', $f['load']);
        $this->assertArrayNotHasKey('bettery', $f, 'una planta sin bateria no debe pintar una bateria vacia');
    }

    public function testSinMedidaNoHayFlujo(): void
    {
        // Planta desconectada: SEMS+ contesta solo con pAc = 0.
        $this->assertNull(GoodWeSemsPlus::flujoComoPowerflow(['data' => ['status' => '0', 'pAc' => 0, 'flows' => []]]));
    }
}
