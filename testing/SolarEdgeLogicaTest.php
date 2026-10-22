<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/SolarEdgeLogica.php';

class SolarEdgeLogicaTest extends TestCase
{
    public function testConvierteElFlujoDeVatiosAKilovatiosYConservaLasConexiones(): void
    {
        $respuesta = SolarEdgeLogica::flujoEnKw([
            'siteCurrentPowerFlow' => [
                'unit' => 'W',
                'connections' => [['from' => 'PV', 'to' => 'Load']],
                'PV' => ['currentPower' => 1800],
                'LOAD' => ['currentPower' => 950],
                'GRID' => ['currentPower' => 0],
                'STORAGE' => ['currentPower' => 850, 'status' => 'Charging', 'chargeLevel' => 95],
            ],
        ]);

        $flujo = $respuesta['siteCurrentPowerFlow'];
        $this->assertSame('kW', $flujo['unit']);
        $this->assertSame(1.8, $flujo['PV']['currentPower']);
        $this->assertSame(0.95, $flujo['LOAD']['currentPower']);
        $this->assertSame(0.0, $flujo['GRID']['currentPower']);
        $this->assertSame(0.85, $flujo['STORAGE']['currentPower']);
        $this->assertSame('Charging', $flujo['STORAGE']['status']);
        $this->assertSame([['from' => 'PV', 'to' => 'Load']], $flujo['connections']);
    }

    public function testNoVuelveAEscalarUnFlujoQueYaVieneEnKilovatios(): void
    {
        $flujo = SolarEdgeLogica::flujoEnKw([
            'unit' => 'kW',
            'PV' => ['currentPower' => 1.8],
        ]);

        $this->assertSame('kW', $flujo['unit']);
        $this->assertSame(1.8, $flujo['PV']['currentPower']);
    }

    public function testUnaUnidadDePotenciaDesconocidaNoSePresentaComoKilovatios(): void
    {
        $flujo = SolarEdgeLogica::flujoEnKw([
            'unit' => 'MW',
            'PV' => ['currentPower' => 0.0018],
        ]);

        $this->assertNull($flujo['PV']['currentPower']);
        $this->assertSame(0.0018, $flujo['PV']['currentPowerRaw']);
    }

    public function testConvierteSeriesWhAKwhYPreservaValoresAusentes(): void
    {
        $serie = SolarEdgeLogica::serieEnergiaEnKwh([
            ['date' => '2026-10-21 12:00:00', 'value' => 1234],
            ['date' => '2026-10-21 12:15:00', 'value' => null],
        ], 'Wh');

        $this->assertSame(1.234, $serie[0]['value']);
        $this->assertNull($serie[1]['value']);
    }

    public function testNoEscalaSeriesQueYaVienenEnKwh(): void
    {
        $serie = SolarEdgeLogica::serieEnergiaEnKwh([
            ['date' => '2026-10-21', 'value' => 1.234],
        ], 'kWh');

        $this->assertSame(1.234, $serie[0]['value']);
    }

    public function testUnidadDesconocidaNoSeEtiquetaComoKwh(): void
    {
        $serie = SolarEdgeLogica::serieEnergiaEnKwh([
            ['date' => '2026-10-21', 'value' => 1234],
        ], 'MWh');

        $this->assertNull($serie[0]['value']);
    }
}
