<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/SungrowService.php';

/**
 * Tests de la logica del panel de graficas de Sungrow (level + multipunto).
 *
 * Se prueban los helpers PUROS (sin BD ni API): la normalizacion de los puntos
 * pedidos y el rango de fechas por nivel. Lo que hace la llamada a la API real
 * (intradia vs agregado dia/mes/año) se verifico a mano contra la cuenta; aqui se
 * fija que la traduccion de parametros del panel no se rompa.
 *
 * Recordatorio: esto solo devuelve datos en inversores tipo 1. Los HIBRIDOS
 * (device_type 14) dan serie vacia por el plan de API de Sungrow, no por el codigo.
 */
class SungrowGraficasTest extends TestCase
{
    public function testNormalizarPuntosAceptaCsvArrayYUnoSuelto()
    {
        // csv con espacios
        $this->assertSame(['p24', 'p14', 'p18'], SungrowService::normalizarPuntos(['points' => 'p24,p14, p18']));
        // array con duplicados -> se limpian
        $this->assertSame(['p24', 'p18'], SungrowService::normalizarPuntos(['points' => ['p24', 'p24', 'p18']]));
        // 'point' suelto (compat)
        $this->assertSame(['p24'], SungrowService::normalizarPuntos(['point' => 'p24']));
    }

    /** Sin puntos: por defecto potencia activa (p24), nunca lista vacia. */
    public function testNormalizarPuntosPorDefectoP24()
    {
        $this->assertSame(['p24'], SungrowService::normalizarPuntos([]));
        $this->assertSame(['p24'], SungrowService::normalizarPuntos(['points' => '']));
        $this->assertSame(['p24'], SungrowService::normalizarPuntos(['points' => [' ', '']]));
    }

    /**
     * El rango por nivel debe salir en el formato que exige el query_type del endpoint
     * agregado: Week/Month en yyyyMMdd (query_type 1) y Year en yyyyMM (query_type 2).
     * Un formato que no cuadra con el query_type da "Parameter:start_time and end_time
     * length" en la API.
     */
    public function testRangoNivelFormatoPorNivel()
    {
        $this->assertSame(['20260709', '20260715'], SungrowService::rangoNivel('week', '2026-07-15'));
        $this->assertSame(['20260701', '20260731'], SungrowService::rangoNivel('month', '2026-07-15'));
        $this->assertSame(['202601', '202612'], SungrowService::rangoNivel('year', '2026-07-15'));
        // Nivel intradia / desconocido -> el dia de referencia (yyyyMMdd).
        $this->assertSame(['20260715', '20260715'], SungrowService::rangoNivel('day', '2026-07-15'));
    }

    /** El mes usa el ultimo dia real (28/29/30/31), no siempre 30. */
    public function testRangoMesRespetaFinDeMes()
    {
        $this->assertSame(['20260201', '20260228'], SungrowService::rangoNivel('month', '2026-02-10')); // febrero no bisiesto
        $this->assertSame(['20240201', '20240229'], SungrowService::rangoNivel('month', '2024-02-10')); // febrero bisiesto
    }

    /** El catalogo mapea las etiquetas del filtro a ids de punto; p24 = potencia activa. */
    public function testCatalogoDePuntos()
    {
        $this->assertSame('p24', SungrowService::CATALOGO_PUNTOS['potencia_activa_total']);
        $this->assertSame('p18', SungrowService::CATALOGO_PUNTOS['voltaje_fase_a']);
        $this->assertSame('p21', SungrowService::CATALOGO_PUNTOS['corriente_fase_a']);
        // Todos los valores son ids pXX validos.
        foreach (SungrowService::CATALOGO_PUNTOS as $etiqueta => $punto) {
            $this->assertMatchesRegularExpression('/^p\d+$/', $punto, $etiqueta);
        }
    }
}
