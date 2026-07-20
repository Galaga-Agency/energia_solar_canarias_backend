<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/proveedores/adaptadores/SigenergyAdaptador.php';

/**
 * El adaptador de Sigenergy traduce su forma de señalar errores a la del contrato.
 *
 * Se prueba con un doble del controlador (por eso el constructor admite uno): asi no
 * hay red ni BD, y no se gasta cuota de una API que limita los accesos por estacion.
 *
 * Esto existe por un fallo real cometido durante el refactor: al pasar el router al
 * contrato, la traduccion de errores se quedo fuera y un "Station not permitted"
 * volvio a salir como 200 con status=true. Antes vivia en
 * ApiControladorService::fallaSigenergy(), a la que habia que acordarse de llamar
 * endpoint por endpoint; ahora vive en el adaptador y no hay nada que recordar. Estos
 * tests son los que impiden que se vuelva a perder.
 */
class SigenergyAdaptadorTest extends TestCase
{
    /** Doble del controlador que devuelve lo que se le diga, ya en JSON. */
    private function conRespuesta(array $respuesta): SigenergyAdaptador
    {
        $c = $this->createMock(SigenergyController::class);   // no llama al constructor real
        foreach (['getAllPlants', 'getPlantPowerRealtime', 'getInventario', 'getSiteAlarms', 'getGraficas'] as $m) {
            $c->method($m)->willReturn(json_encode($respuesta));
        }
        return new SigenergyAdaptador($c);
    }

    public function testSeIdentificaBien()
    {
        $a = $this->conRespuesta(['code' => 0, 'data' => []]);
        $this->assertSame('Sigenergy', $a->nombre());
        $this->assertSame('sigenergy', $a->clave());
    }

    /** code=0 es exito: pasa tal cual. */
    public function testUnaRespuestaBuenaPasaSinTocar()
    {
        $r = $this->conRespuesta(['code' => 0, 'msg' => 'success', 'data' => ['pvPower' => 3.6]])
            ->tiempoReal('X1');
        $this->assertSame(0, $r['code']);
        $this->assertSame(3.6, $r['data']['pvPower']);
    }

    /**
     * El caso que motivo todo: Sigenergy responde HTTP 200 con el error dentro.
     * Sin traducir, el frontend no puede distinguir "no hay datos" de "no es tuya".
     */
    public function testStationNotPermittedSeTraduceA404()
    {
        $a = $this->conRespuesta(['code' => 1111, 'msg' => 'Station not permitted', 'data' => []]);
        try {
            $a->tiempoReal('NOEXISTE');
            $this->fail('deberia haber lanzado ErrorProveedor');
        } catch (ErrorProveedor $e) {
            $this->assertSame(404, $e->http());
            $this->assertSame('Planta no encontrada o sin acceso', $e->getMessage());
            $d = $e->detalles();
            $this->assertSame(1111, $d['codigo_sigenergy']);
            $this->assertSame('Station not permitted', $d['msg_sigenergy']);
            $this->assertFalse($d['reintentable']);
        }
    }

    /** El rate-limit por estacion sale como 429 y marcado como reintentable. */
    public function testAccessRestrictionSeTraduceA429()
    {
        $a = $this->conRespuesta(['code' => 1201, 'msg' => 'Access restriction', 'data' => []]);
        try {
            $a->graficas('X1', ['level' => 'Day']);
            $this->fail('deberia haber lanzado ErrorProveedor');
        } catch (ErrorProveedor $e) {
            $this->assertSame(429, $e->http());
            $this->assertTrue($e->detalles()['reintentable']);
        }
    }

    /** Una planta apagada (13008): error estable, no reintentable. */
    public function testStationDisconnectSeTraduce()
    {
        $a = $this->conRespuesta(['code' => 13008, 'msg' => 'station disconnect', 'data' => []]);
        try {
            $a->inventario('X1');
            $this->fail('deberia haber lanzado ErrorProveedor');
        } catch (ErrorProveedor $e) {
            $this->assertFalse($e->detalles()['reintentable']);
            $this->assertFalse($e->detalles()['documentado'], '13008 no esta en el Error Code List oficial');
        }
    }

    /**
     * La traduccion va en TODAS las salidas. Antes habia que invocarla a mano en cada
     * endpoint, y los que se olvidaban devolvian los fallos como exitos: eso es
     * exactamente lo que paso al mover el router al contrato.
     */
    public function testLaTraduccionSeAplicaEnTodosLosEndpoints()
    {
        $a = $this->conRespuesta(['code' => 1111, 'msg' => 'Station not permitted', 'data' => []]);
        $llamadas = [
            'plantas'    => fn() => $a->plantas(1, 10),
            'tiempoReal' => fn() => $a->tiempoReal('X1'),
            'inventario' => fn() => $a->inventario('X1'),
            'alertas'    => fn() => $a->alertas('X1'),
            'graficas'   => fn() => $a->graficas('X1', []),
        ];
        foreach ($llamadas as $nombre => $f) {
            try {
                $f();
                $this->fail("$nombre deberia traducir el error de Sigenergy");
            } catch (ErrorProveedor $e) {
                $this->assertSame(404, $e->http(), "$nombre");
            }
        }
    }

    /**
     * detalle() sale de la lista ya cacheada y no trae envoltorio `code`: no hay nada
     * que traducir, y una planta que no existe es null, no un error.
     */
    public function testDetalleDevuelveNuloSiNoExiste()
    {
        $c = $this->createMock(SigenergyController::class);
        $c->method('getPlantDetails')->willReturn('null');
        $this->assertNull((new SigenergyAdaptador($c))->detalle('NOEXISTE'));
    }
}
