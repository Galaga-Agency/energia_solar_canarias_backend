<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/SungrowService.php';

/**
 * Tests de la normalizacion de unidades de Sungrow (iSolarCloud).
 *
 * Lo que se protege: su API AUTOESCALA cada medida por planta. El mismo campo llega
 * como "39.7 kWh" en una planta y "48.364 MWh" en otra, y en chino como "1.814 万度"
 * (万 = 10.000). Leer el `value` ignorando el `unit` mezcla escalas y falsea los
 * totales por factores de 1.000 o 10.000.
 *
 * Esto no es teorico: se publico total_income = 1.813 cuando eran 18.130 EUR.
 */
class SungrowNormalizacionTest extends TestCase
{
    public function testConvierteAUnidadCanonica()
    {
        $casos = [
            // [valor, unidad,  esperado, unidad esperada]
            [39.7,    'kWh',    39.7,     'kWh'],
            [48.364,  'MWh',    48364.0,  'kWh'],   // el caso que mezclaba escalas
            [1500,    'Wh',     1.5,      'kWh'],
            [2.5,     'kW',     2.5,      'kW'],
            [3,       'MW',     3000.0,   'kW'],
            [5.52,    'kWp',    5.52,     'kWp'],
        ];
        foreach ($casos as [$v, $u, $esp, $uEsp]) {
            $r = SungrowService::normalizarMedida(['value' => $v, 'unit' => $u]);
            $this->assertEquals($esp, $r['value'], "$v $u");
            $this->assertSame($uEsp, $r['unit'], "$v $u");
        }
    }

    /**
     * El bug de los 10.000: 万 significa "diez mil". Sin esto, 1.813 万欧元 se
     * publicaba como 1,81 EUR en vez de 18.130 EUR.
     */
    public function testElFactorChinoDeDiezMil()
    {
        $r = SungrowService::normalizarMedida(['value' => 1.813, 'unit' => '万欧元']);
        $this->assertEquals(18130.0, $r['value']);
        $this->assertSame('EUR', $r['unit']);

        $r = SungrowService::normalizarMedida(['value' => 1.814, 'unit' => '万度']);
        $this->assertEquals(18140.0, $r['value']);
        $this->assertSame('kWh', $r['unit']);
    }

    public function testUnidadesChinasSueltas()
    {
        $casos = [
            ['度', 'kWh', 5, 5.0],
            ['欧元', 'EUR', 12, 12.0],
            ['千克', 'kg', 3, 3.0],
            ['吨', 'kg', 2, 2000.0],
            ['小时', 'h', 4, 4.0],
        ];
        foreach ($casos as [$u, $uEsp, $v, $esp]) {
            $r = SungrowService::normalizarMedida(['value' => $v, 'unit' => $u]);
            $this->assertEquals($esp, $r['value'], $u);
            $this->assertSame($uEsp, $r['unit'], $u);
        }
    }

    /** Una unidad que no conocemos se deja tal cual: mejor no tocarla que inventar. */
    public function testUnidadDesconocidaSeRespeta()
    {
        $r = SungrowService::normalizarMedida(['value' => 7, 'unit' => 'ZZZ']);
        $this->assertEquals(7, $r['value']);
        $this->assertSame('ZZZ', $r['unit']);
    }
}
