<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/proveedores/RegistroProveedores.php';
require_once __DIR__ . '/../app/proveedores/ProveedorBase.php';

/**
 * Proveedor de mentira: solo sabe listar plantas.
 *
 * Sirve para probar el registro y el contrato sin red ni BD. Que se pueda escribir en
 * 10 lineas es justamente la prueba de que el contrato es util: antes, para simular un
 * proveedor habia que imitar cinco metodos con cinco nombres distintos.
 */
class ProveedorFalso extends ProveedorBase
{
    public $vecesConstruido = 0;
    public function nombre(): string { return 'Falso'; }
    public function clave(): string { return 'falso'; }
    public function plantas(int $pagina = 1, int $porPagina = 2000): array
    {
        return ['pagina' => $pagina, 'porPagina' => $porPagina];
    }
}

class RegistroProveedoresTest extends TestCase
{
    private function registro(): RegistroProveedores
    {
        $r = new RegistroProveedores();
        $r->registrar('falso', fn() => new ProveedorFalso());
        return $r;
    }

    public function testDevuelveElProveedorPedido()
    {
        $p = $this->registro()->obtener('falso');
        $this->assertInstanceOf(Proveedor::class, $p);
        $this->assertSame('Falso', $p->nombre());
    }

    /** El `?proveedor=` llega de una URL: no se debe depender de mayusculas. */
    public function testLaClaveNoDistingueMayusculas()
    {
        $r = $this->registro();
        $this->assertSame('Falso', $r->obtener('FALSO')->nombre());
        $this->assertSame('Falso', $r->obtener('Falso')->nombre());
        $this->assertTrue($r->existe('FaLsO'));
    }

    public function testProveedorDesconocidoAvisaDeLosQueHay()
    {
        $r = $this->registro();
        $this->assertFalse($r->existe('noexiste'));
        try {
            $r->obtener('noexiste');
            $this->fail('deberia haber lanzado ProveedorDesconocido');
        } catch (ProveedorDesconocido $e) {
            $this->assertSame('noexiste', $e->clave());
            $this->assertStringContainsString('falso', $e->getMessage());
        }
    }

    /**
     * Solo se construye lo que se pide, y una sola vez.
     *
     * Importa: ApiControladorService instanciaba los CINCO controladores en su
     * constructor, en toda peticion, aunque solo hiciera falta uno.
     */
    public function testSoloConstruyeLoQueSePideYUnaSolaVez()
    {
        $construidos = 0;
        $r = new RegistroProveedores();
        $r->registrar('falso', function () use (&$construidos) {
            $construidos++;
            return new ProveedorFalso();
        });
        $r->registrar('otro', function () {
            $this->fail('no se pidio "otro": no deberia construirse');
        });

        $this->assertSame(0, $construidos, 'registrar no debe construir nada');

        $a = $r->obtener('falso');
        $b = $r->obtener('falso');
        $this->assertSame(1, $construidos, 'la segunda llamada debe reutilizar la instancia');
        $this->assertSame($a, $b);
    }

    public function testElRegistroPorDefectoTraeLosCincoProveedores()
    {
        $claves = RegistroProveedores::porDefecto()->claves();
        sort($claves);
        $this->assertSame(['goodwe', 'sigenergy', 'solaredge', 'sungrow', 'victronenergy'], $claves);
    }

    public function testLosParametrosLleganAlProveedor()
    {
        $r = $this->registro()->obtener('falso')->plantas(3, 25);
        $this->assertSame(['pagina' => 3, 'porPagina' => 25], $r);
    }
}
