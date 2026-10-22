<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/VictronLogica.php';

/**
 * Reglas puras de Victron (VRM). Casos sacados de la API real:
 * la planta 335939 dejo de reportar el 2023-08-07 (last_timestamp 1691417704)
 * y VRM sigue devolviendo su ultima foto, con "Battery state" = Descargando.
 */
class VictronLogicaTest extends TestCase
{
    private const AHORA = 1792579617; // 2026-10-21, ultimo dato de una planta viva

    private function planta(array $extra = []): array
    {
        return $extra + [
            'idSite' => 335939,
            'alarm' => false,
            'num_alarms' => 0,
            'current_alarms' => [],
            'last_timestamp' => 1691417704,
            'extended' => [
                ['code' => 'bv', 'idDataAttribute' => 143, 'rawValue' => 50.95, 'formattedValue' => '50.95 V'],
                ['code' => 'bs', 'idDataAttribute' => 144, 'rawValue' => 99, 'formattedValue' => '99.0 %'],
                ['code' => 'bst', 'idDataAttribute' => 215, 'rawValue' => 2, 'formattedValue' => 'Descargando'],
                ['code' => 'solar_yield', 'idDataAttribute' => null, 'rawValue' => 5149.74, 'formattedValue' => '5150 W'],
            ],
        ];
    }

    public function testObsoletaPorEdadYSinDato()
    {
        $this->assertTrue(VictronLogica::estaObsoleta(1691417704, self::AHORA));
        $this->assertFalse(VictronLogica::estaObsoleta(self::AHORA - 60, self::AHORA));
        $this->assertFalse(VictronLogica::estaObsoleta(self::AHORA - 3600, self::AHORA), 'el limite exacto aun cuenta como vivo');
        $this->assertTrue(VictronLogica::estaObsoleta(self::AHORA - 3601, self::AHORA));
        $this->assertTrue(VictronLogica::estaObsoleta(null, self::AHORA));
        $this->assertTrue(VictronLogica::estaObsoleta('', self::AHORA));
        $this->assertTrue(VictronLogica::estaObsoleta(0, self::AHORA));
    }

    public function testEstadoPlanta()
    {
        // Desconectada manda sobre la alarma: una alarma vieja no es una averia de hoy.
        $this->assertSame('disconnected', VictronLogica::estadoPlanta(1691417704, true, self::AHORA));
        $this->assertSame('disconnected', VictronLogica::estadoPlanta(null, false, self::AHORA));
        $this->assertSame('error', VictronLogica::estadoPlanta(self::AHORA - 30, true, self::AHORA));
        $this->assertSame('working', VictronLogica::estadoPlanta(self::AHORA - 30, false, self::AHORA));
    }

    public function testTieneAlarma()
    {
        $this->assertFalse(VictronLogica::tieneAlarma($this->planta()));
        $this->assertTrue(VictronLogica::tieneAlarma($this->planta(['alarm' => true])));
        $this->assertTrue(VictronLogica::tieneAlarma($this->planta(['num_alarms' => 2])));
        $this->assertTrue(VictronLogica::tieneAlarma($this->planta(['current_alarms' => [['id' => 1]]])));
        $this->assertFalse(VictronLogica::tieneAlarma([]));
    }

    /** El caso del informe: la lista decia "Descargando" para una planta muerta en 2023. */
    public function testListadoDePlantaParadaSaleDesconectadaYConBateriaAparte()
    {
        $d = VictronLogica::datosListado($this->planta(), self::AHORA);
        $this->assertSame('disconnected', $d['status']);
        $this->assertSame('Descargando', $d['batteryState']);
        $this->assertSame(99, $d['batterySoc']);
        $this->assertSame(50.95, $d['batteryVoltage']);
        $this->assertSame(1691417704, $d['lastTimestamp']);
        // La potencia de su ultima foto no es produccion de ahora.
        $this->assertNull($d['potenciaSolarW']);
    }

    public function testListadoDePlantaVivaConservaPotencia()
    {
        $d = VictronLogica::datosListado($this->planta(['last_timestamp' => self::AHORA - 45]), self::AHORA);
        $this->assertSame('working', $d['status']);
        $this->assertSame(5149.74, $d['potenciaSolarW']);
    }

    /** Antes las variables se arrastraban de una planta a la siguiente. */
    public function testListadoSinBateriaNoHeredaNada()
    {
        $primera = VictronLogica::datosListado($this->planta(), self::AHORA);
        $this->assertSame(99, $primera['batterySoc']);

        $sinBateria = VictronLogica::datosListado(
            ['last_timestamp' => self::AHORA - 10, 'alarm' => false, 'extended' => []],
            self::AHORA
        );
        $this->assertNull($sinBateria['batterySoc']);
        $this->assertNull($sinBateria['batteryVoltage']);
        $this->assertNull($sinBateria['batteryState']);
        $this->assertNull($sinBateria['potenciaSolarW']);
        $this->assertSame('working', $sinBateria['status']);

        // Sin `extended` en absoluto tampoco revienta.
        $this->assertSame('disconnected', VictronLogica::datosListado([], self::AHORA)['status']);
    }

    public function testEnergiaSolarSumaPbPcPg()
    {
        $records = (object) [
            'Pb' => [[1, 1.5], [2, 2.5]],
            'Pc' => [[1, 3.0]],
            'Pg' => [[1, 0.25]],
            'Bc' => [[1, 100.0]], // bateria a consumo: no es solar
        ];
        $this->assertEqualsWithDelta(7.25, VictronLogica::energiaSolarKwh($records), 1e-9);
        // VRM manda `false` en las series sin datos.
        $this->assertSame(0.0, VictronLogica::energiaSolarKwh(['Pb' => false, 'Pc' => false]));
        $this->assertSame(0.0, VictronLogica::energiaSolarKwh([]));
    }

    public function testTotalHistorico()
    {
        // `totals` reales de 357538 (Pg no viene: sin red).
        $t = VictronLogica::totalHistorico((object) [
            'Pc' => 70217.33125778091, 'Bc' => 35302.18, 'gc' => 32490.23,
            'gb' => 43502.13, 'Pb' => 18285.35836541951, 'kwh' => 199797.25,
        ]);
        $this->assertSame(88502.69, $t['kwh']);
        $this->assertNull($t['Pg']);
        $this->assertSame(18285.358, $t['Pb']);

        // 335939: 10,48 + 0,80 — antes la ficha decia "0 kWh".
        $this->assertSame(11.28, VictronLogica::totalHistorico(['Pb' => 10.480000289157033, 'Pc' => 0.800000011920929])['kwh']);

        $this->assertSame(1.0, VictronLogica::totalHistorico(['Pg' => 1])['kwh']);
        $this->assertNull(VictronLogica::totalHistorico(['Bc' => 5, 'kwh' => 5]), 'sin series solares no es 0 kWh, es sin dato');
        $this->assertNull(VictronLogica::totalHistorico(false));
        $this->assertNull(VictronLogica::totalHistorico(null));
    }

    public function testVentanasHistoricoNoPasanDeCuatroAnos()
    {
        $max = VictronLogica::VENTANA_MAXIMA_S;
        // 98081: alta el 2021-03-05; un unico rango de 5,6 anos VRM lo devolvia vacio.
        $v = VictronLogica::ventanasHistorico(1614953241, 1792580230);
        $this->assertCount(2, $v);
        $this->assertSame([1614953241, 1614953241 + $max], $v[0]);
        $this->assertSame([1614953241 + $max, 1792580230], $v[1]);

        $this->assertSame([[100, 200]], VictronLogica::ventanasHistorico(100, 200));
        $this->assertSame([], VictronLogica::ventanasHistorico(200, 200));
        $this->assertSame([], VictronLogica::ventanasHistorico(300, 200));
        foreach (VictronLogica::ventanasHistorico(0, 10 * $max + 5) as [$a, $b]) {
            $this->assertLessThanOrEqual($max, $b - $a);
        }
    }

    public function testSumarTotalesDeVariasVentanas()
    {
        $suma = VictronLogica::sumarTotales([
            (object) ['Pb' => 3322.77, 'Pc' => 2953.58, 'Bc' => 2739.13],
            [], // ventana sin datos
            ['Pb' => 1096.05, 'Pc' => 878.70, 'Pg' => 1.5, 'x' => 'no'],
            null,
        ]);
        $this->assertEqualsWithDelta(4418.82, $suma['Pb'], 1e-9);
        $this->assertEqualsWithDelta(3832.28, $suma['Pc'], 1e-9);
        $this->assertSame(1.5, $suma['Pg']);
        $this->assertArrayNotHasKey('x', $suma);
        $this->assertSame(8252.6, VictronLogica::totalHistorico($suma)['kwh']);
        $this->assertNull(VictronLogica::totalHistorico(VictronLogica::sumarTotales([[], []])));
    }
}
