<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/proveedores/ProveedorBase.php';
require_once __DIR__ . '/../app/proveedores/RegistroProveedores.php';

/**
 * Tests del contrato de proveedor, sin red ni BD.
 *
 * Los adaptadores se examinan SIN construirlos (reflexion y
 * newInstanceWithoutConstructor). No es un truco para esquivar un problema: los
 * controladores abren conexion a MySQL en su constructor, asi que construir un
 * adaptador arrastra media aplicacion. Y aqui no se prueba el comportamiento de las
 * APIs (eso son las pruebas de integracion), sino algo mas barato y mas util: que cada
 * proveedor DECLARE honestamente lo que ofrece.
 *
 * Ese mapa vivia repartido en `case`s del router ("No hay Alertas en la planta de
 * SolarEdge"), donde era facil olvidar uno: Sigenergy estaba en unas rutas y no en
 * otras, e inventario y alertas existian en el servicio pero eran inalcanzables desde
 * fuera. Ahora esta en un sitio y se comprueba.
 */
class ProveedorContratoTest extends TestCase
{
    private const TODAS = ['plantas', 'detalle', 'tiempoReal', 'graficas', 'inventario', 'alertas', 'beneficios', 'resumen'];

    /**
     * Quien ofrece que, y su clase. Es el mismo mapa que antes estaba disperso por el
     * router.
     */
    public static function proveedoresYSoportes(): array
    {
        return [
            'goodwe' => ['GoodWeAdaptador', 'GoodWe',
                ['plantas', 'detalle', 'tiempoReal', 'graficas', 'inventario']],
            'solaredge' => ['SolarEdgeAdaptador', 'SolarEdge',
                ['plantas', 'detalle', 'tiempoReal', 'graficas', 'inventario', 'beneficios', 'resumen']],
            'victronenergy' => ['VictronEnergyAdaptador', 'VictronEnergy',
                ['plantas', 'detalle', 'tiempoReal', 'graficas', 'inventario', 'alertas']],
            'sungrow' => ['SungrowAdaptador', 'Sungrow',
                ['plantas', 'detalle', 'tiempoReal', 'graficas', 'inventario', 'alertas', 'beneficios']],
            'sigenergy' => ['SigenergyAdaptador', 'Sigenergy',
                ['plantas', 'detalle', 'tiempoReal', 'graficas', 'inventario', 'alertas']],
        ];
    }

    /** Carga la clase del adaptador sin construirla. */
    private function clase(string $nombreClase): ReflectionClass
    {
        require_once __DIR__ . '/../app/proveedores/adaptadores/' . $nombreClase . '.php';
        return new ReflectionClass($nombreClase);
    }

    private function sinConstruir(string $nombreClase): Proveedor
    {
        return $this->clase($nombreClase)->newInstanceWithoutConstructor();
    }

    private function invocar(Proveedor $p, string $op)
    {
        switch ($op) {
            case 'plantas':    return $p->plantas(1, 10);
            case 'detalle':    return $p->detalle('X1');
            case 'tiempoReal': return $p->tiempoReal('X1');
            case 'graficas':   return $p->graficas('X1', []);
            case 'inventario': return $p->inventario('X1');
            case 'alertas':    return $p->alertas('X1', 1, 10);
            case 'beneficios': return $p->beneficios('X1');
            case 'resumen':    return $p->resumen('X1');
        }
        throw new InvalidArgumentException($op);
    }

    /** Lo no soportado lanza OperacionNoSoportada, y dice cual falta. */
    public function testLoNoSoportadoLanzaOperacionNoSoportada()
    {
        $vacio = new class extends ProveedorBase {
            public function nombre(): string { return 'Vacio'; }
            public function clave(): string { return 'vacio'; }
        };

        foreach (self::TODAS as $op) {
            try {
                $this->invocar($vacio, $op);
                $this->fail("$op deberia haber lanzado OperacionNoSoportada");
            } catch (OperacionNoSoportada $e) {
                $this->assertSame('Vacio', $e->proveedor());
                $this->assertSame($op, $e->operacion(), 'el error debe decir QUE operacion falta');
            }
        }
    }

    /**
     * nombre() tiene que coincidir con la tabla `proveedores`: es lo que se usa para
     * buscar en plantas_asociadas, asi que una errata aqui deja a un cliente sin ver
     * sus plantas.
     */
    #[DataProvider('proveedoresYSoportes')]
    public function testSeIdentificanConSuNombreCanonicoYSuClave(string $clase, string $nombre, array $soportadas)
    {
        $p = $this->sinConstruir($clase);
        $this->assertSame($nombre, $p->nombre());
        $this->assertSame(strtolower($nombre), $p->clave(), 'la clave es el nombre en minusculas');
        $this->assertInstanceOf(Proveedor::class, $p);
    }

    /**
     * El mapa de quien ofrece que: se mira que metodos sobrescribe cada adaptador.
     */
    #[DataProvider('proveedoresYSoportes')]
    public function testCadaProveedorDeclaraLoQueOfrece(string $clase, string $nombre, array $soportadas)
    {
        $r = $this->clase($clase);
        foreach (self::TODAS as $op) {
            $loImplementa = $r->getMethod($op)->getDeclaringClass()->getName() !== ProveedorBase::class;
            $deberia = in_array($op, $soportadas, true);
            $this->assertSame(
                $deberia,
                $loImplementa,
                $deberia ? "$nombre deberia ofrecer $op" : "$nombre NO deberia ofrecer $op"
            );
        }
    }

    /**
     * Las claves del registro y las que declaran los adaptadores tienen que cuadrar.
     * Si no, `?proveedor=x` encontraria una clase que se llama a si misma de otra
     * forma, y el control de acceso buscaria en plantas_asociadas por un nombre que
     * no existe.
     */
    #[DataProvider('proveedoresYSoportes')]
    public function testLaClaveDelRegistroCoincideConLaQueDeclaraElAdaptador(string $clase, string $nombre, array $soportadas)
    {
        $p = $this->sinConstruir($clase);
        $this->assertTrue(
            RegistroProveedores::porDefecto()->existe($p->clave()),
            $p->clave() . ' no esta registrada en porDefecto()'
        );
    }

    /**
     * GoodWe no ofrece alertas POR PLANTA a proposito: su endpoint devuelve las de
     * TODO el parque ignorando el id. Fingir que las soporta seria devolverle a un
     * cliente las alarmas de instalaciones ajenas, justo la fuga que cerro
     * puedeVerPlanta().
     */
    public function testGoodWeNoFingeTenerAlertasPorPlanta()
    {
        $p = $this->sinConstruir('GoodWeAdaptador');
        $this->expectException(OperacionNoSoportada::class);
        $p->alertas('X1');
    }

    /** SolarEdge es el unico sin alertas: su API no las expone. */
    public function testSolarEdgeNoTieneAlertas()
    {
        $p = $this->sinConstruir('SolarEdgeAdaptador');
        $this->expectException(OperacionNoSoportada::class);
        $p->alertas('X1');
    }

    /** Solo SolarEdge tiene resumen/overview. */
    public function testSoloSolarEdgeTieneResumen()
    {
        foreach (['GoodWeAdaptador', 'VictronEnergyAdaptador', 'SungrowAdaptador', 'SigenergyAdaptador'] as $c) {
            try {
                $this->sinConstruir($c)->resumen('X1');
                $this->fail("$c no deberia ofrecer resumen");
            } catch (OperacionNoSoportada $e) {
                $this->assertSame('resumen', $e->operacion());
            }
        }
        $r = $this->clase('SolarEdgeAdaptador')->getMethod('resumen');
        $this->assertNotSame(ProveedorBase::class, $r->getDeclaringClass()->getName());
    }
}
