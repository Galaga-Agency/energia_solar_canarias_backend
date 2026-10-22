<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/ProveedorCachePolitica.php';

class ProveedorCachePoliticaTest extends TestCase
{
    public function testTiemposConservadoresPorProveedorYOperacion(): void
    {
        $this->assertSame(60, ProveedorCachePolitica::ttl('SolarEdge', 'tiempo real'));
        $this->assertSame(900, ProveedorCachePolitica::ttl('SolarEdge', 'inventario'));
        $this->assertSame(3600, ProveedorCachePolitica::ttl('SolarEdge', 'beneficios'));
        $this->assertSame(60, ProveedorCachePolitica::ttl('VictronEnergy', 'tiempo real'));
        $this->assertSame(120, ProveedorCachePolitica::ttl('Sungrow', 'alertas'));
    }

    public function testGoodWeYSigenergyConservanSusPoliticasPropias(): void
    {
        $this->assertNull(ProveedorCachePolitica::ttl('GoodWe', 'tiempo real'));
        $this->assertNull(ProveedorCachePolitica::ttl('Sigenergy', 'inventario'));
    }

    public function testOperacionesSinPoliticaNoSeCacheanPorAccidente(): void
    {
        $this->assertNull(ProveedorCachePolitica::ttl('SolarEdge', 'operacion desconocida'));
        $this->assertNull(ProveedorCachePolitica::ttl('Desconocido', 'tiempo real'));
    }
}
