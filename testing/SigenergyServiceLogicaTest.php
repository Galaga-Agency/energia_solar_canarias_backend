<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/SigenergyService.php';

/**
 * Tests de la logica interna de SigenergyService que no necesita ni red ni BD.
 *
 * Se instancia con newInstanceWithoutConstructor() a proposito: el constructor
 * abre conexion a MySQL y monta la cache, y aqui solo se quiere probar el calculo.
 * Asi estos tests corren en cualquier sitio, tambien en CI sin base de datos.
 */
class SigenergyServiceLogicaTest extends TestCase
{
    private function metodo($nombre)
    {
        $m = new ReflectionMethod(SigenergyService::class, $nombre);
        // En PHP 8.2 (la version del contenedor y de produccion) hace falta para poder
        // invocar metodos privados. Desde 8.5 sobra y ademas avisa de obsoleto, asi que
        // solo se llama donde toca. Ojo al quitarlo: en 8.2 los tests fallarian.
        if (PHP_VERSION_ID < 80500) {
            $m->setAccessible(true);
        }
        return $m;
    }

    private function servicioSinConstructor()
    {
        return (new ReflectionClass(SigenergyService::class))->newInstanceWithoutConstructor();
    }

    // ---------------------------------------------------------------- desanidar

    /**
     * Sigenergy manda JSON dentro de JSON. En openapi/system/{id}/devices, `data` es
     * una LISTA DE STRINGS y ademas cada `attrMap` es OTRO string con JSON dentro.
     * Sin desanidar, al frontend le llegaba texto donde espera objetos.
     */
    public function testDesanidaListaDeStringsConJsonDentro()
    {
        $m = $this->metodo('desanidar');
        $crudo = [
            '{"systemId":"WGANI1","deviceType":"Inverter","attrMap":"{\"ratedActivePower\":15.0}"}',
            '{"systemId":"WGANI1","deviceType":"Battery","attrMap":"{\"ratedEnergy\":8.06}"}',
        ];
        $r = $m->invoke(null, $crudo);

        $this->assertIsArray($r[0]);
        $this->assertSame('Inverter', $r[0]['deviceType']);
        $this->assertIsArray($r[0]['attrMap'], 'attrMap tiene que quedar como objeto, no string');
        $this->assertSame(15.0, $r[0]['attrMap']['ratedActivePower']);
        $this->assertSame(8.06, $r[1]['attrMap']['ratedEnergy']);
    }

    /** Un texto normal (un nombre, una direccion) NO se debe tocar. */
    public function testNoDestrozaLosTextosNormales()
    {
        $m = $this->metodo('desanidar');
        $datos = ['systemName' => 'Coagrisan Cardonera', 'addr' => 'Calle {no soy json}', 'n' => 5];
        $this->assertSame($datos, $m->invoke(null, $datos));
    }

    public function testUnStringQueParecaJsonPeroEsteRotoSeQuedaIgual()
    {
        $m = $this->metodo('desanidar');
        $this->assertSame('{esto no cierra', $m->invoke(null, '{esto no cierra'));
    }

    // -------------------------------------------------------------- ttlHistorico

    /**
     * Un periodo YA CERRADO no cambia nunca, asi que se cachea 24h. El periodo en
     * curso mantiene el TTL corto porque aun se esta llenando.
     *
     * Importa porque el 1201 es por estacion y cada `level` que mira el usuario
     * (Dia/Semana/Mes/Año) es otra llamada a la MISMA estacion.
     */
    public function testHistoricoCerradoSeCacheaUnDiaYElEnCursoNo()
    {
        $m = $this->metodo('ttlHistorico');
        $s = $this->servicioSinConstructor();

        $hoy = date('Y-m-d');
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $mesPasado = date('Y-m-d', strtotime('first day of last month'));
        $anioPasado = date('Y-m-d', strtotime('-1 year'));

        // Cerrados -> TTL largo
        $this->assertSame(86400, $m->invoke($s, 'Day', $ayer), 'lo de ayer no cambia');
        $this->assertSame(86400, $m->invoke($s, 'Day', '2020-01-01'));
        $this->assertSame(86400, $m->invoke($s, 'Month', $mesPasado));
        $this->assertSame(86400, $m->invoke($s, 'Year', $anioPasado));

        // En curso -> TTL corto
        $this->assertSame(315, $m->invoke($s, 'Day', $hoy), 'hoy aun se esta llenando');
        $this->assertSame(315, $m->invoke($s, 'Month', $hoy));
        $this->assertSame(315, $m->invoke($s, 'Year', $hoy));
        $this->assertSame(315, $m->invoke($s, 'Lifetime', $hoy), 'Lifetime siempre incluye hoy');
    }

    /** Se compara contra el FIN del periodo: el "Month" del dia 3 es el mes en curso. */
    public function testElMesEnCursoNoSeConsideraCerradoAunquePidasElDiaUno()
    {
        $m = $this->metodo('ttlHistorico');
        $s = $this->servicioSinConstructor();
        $primeroDeEsteMes = date('Y-m-01');
        $this->assertSame(315, $m->invoke($s, 'Month', $primeroDeEsteMes));
    }

    public function testUnaFechaInvalidaCaeAlTtlCorto()
    {
        $m = $this->metodo('ttlHistorico');
        $s = $this->servicioSinConstructor();
        $this->assertSame(315, $m->invoke($s, 'Day', 'no-es-fecha'));
    }
}
