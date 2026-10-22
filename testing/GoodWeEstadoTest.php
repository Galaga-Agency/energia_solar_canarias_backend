<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/GoodWeEstado.php';

/**
 * El status de una planta GoodWe es el PEOR de sus inversores. Una planta con un
 * inversor caido y otro produciendo no esta "desconectada": es una caida parcial.
 */
class GoodWeEstadoTest extends TestCase
{
    #[DataProvider('casos')]
    public function testEstado($codigo, $pac, string $esperado): void
    {
        $this->assertSame($esperado, GoodWeEstado::dePlanta($codigo, $pac));
    }

    public static function casos(): array
    {
        return [
            'genera' => [1, 3200, 'working'],
            'en espera' => [0, 0, 'waiting'],
            'averia' => [2, 0, 'error'],
            'caida de verdad' => [-1, 0, 'disconnected'],
            // Aparcamientos Tomas Miller, 2026-10-21: -1 con 1212 W.
            'caida parcial: un inversor vivo' => [-1, 1212, 'warning'],
            'codigo como texto' => ['-1', '263', 'warning'],
            'sin potencia conocida' => [-1, null, 'disconnected'],
            'codigo desconocido' => [7, 0, 'unknown'],
        ];
    }

    public function testParcialSoloConMenosUnoYPotencia(): void
    {
        $this->assertTrue(GoodWeEstado::esParcial(-1, 1212));
        $this->assertFalse(GoodWeEstado::esParcial(-1, 0));
        $this->assertFalse(GoodWeEstado::esParcial(1, 1212));
    }
}
