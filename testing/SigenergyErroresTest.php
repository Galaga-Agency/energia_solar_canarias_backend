<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/utils/SigenergyErrores.php';

/**
 * Tests del catalogo de errores de Sigenergy.
 *
 * Lo que se protege aqui: Sigenergy responde SIEMPRE HTTP 200 y mete el error real
 * en el campo `code`. Si esta traduccion se rompe, los fallos vuelven a salir como
 * exitos y el frontend no puede distinguir "no hay datos" de "esta planta no es tuya".
 *
 * Ojo con el codigo 1201: marcarlo como cacheable seria el peor fallo posible,
 * porque serviriamos "Access restriction" como si fuera un dato bueno durante
 * 5 minutos a todos los usuarios. Ya paso una vez.
 */
class SigenergyErroresTest extends TestCase
{
    public function testCeroEsExito()
    {
        $this->assertTrue(SigenergyErrores::esExito(['code' => 0]));
        $this->assertFalse(SigenergyErrores::esExito(['code' => 1111]));
        $this->assertFalse(SigenergyErrores::esExito('no es un array'));
        $this->assertFalse(SigenergyErrores::esExito([]));
    }

    /** El rate-limit por estacion NO se puede cachear: es la razon de ser de la cache. */
    public function test1201EsTransitorioYNoSeCachea()
    {
        $e = SigenergyErrores::traducir(1201);
        $this->assertTrue($e['transitorio'], 'un 1201 cacheado se serviria como dato bueno 5 min');
        $this->assertSame(429, $e['http']);
        $this->assertTrue(SigenergyErrores::esTransitorio(1201));
    }

    /**
     * Una planta apagada (13008) SI se cachea: su limite de 1 acceso/5min se aplica
     * igual aunque responda error, asi que reintentar machacaria la API justo con
     * las plantas que no van a contestar.
     */
    public function test13008SeCacheaAunqueSeaError()
    {
        $this->assertFalse(SigenergyErrores::esTransitorio(13008));
        $e = SigenergyErrores::traducir(13008);
        $this->assertSame(200, $e['http']);
        $this->assertFalse($e['documentado'], '13008 no esta en el Error Code List oficial');
    }

    /**
     * "station info not found" (13001) tampoco se cachea reintentando: como el 13008,
     * su limite de 1 acceso/5min se aplica igual, asi que se marca no transitorio para
     * no gastar el cupo de esa estacion en un error. Antes caia en el generico (502).
     */
    public function test13001SeCacheaAunqueSeaError()
    {
        $this->assertFalse(SigenergyErrores::esTransitorio(13001), 'reintentar 13001 gasta el cupo 5min');
        $e = SigenergyErrores::traducir(13001);
        $this->assertSame(404, $e['http']);
        $this->assertFalse($e['documentado'], '13001 no esta en el Error Code List oficial');
        $this->assertNotEmpty($e['mensaje']);
    }

    public function testCodigosSeTraducenAlHttpQueTocaFactory()
    {
        $casos = [
            [1111, 404],   // Station not permitted -> no existe o no es nuestra
            [1106, 404],   // Station was not found
            [1000, 400],   // Param illegal -> normalmente bug nuestro
            [1402, 403],   // No permission
            [11002, 503],  // cuenta bloqueada
            [1502, 502],   // error interno de Sigenergy
            [-1, 504],     // no llegamos a contactar
        ];
        foreach ($casos as [$code, $http]) {
            $this->assertSame($http, SigenergyErrores::traducir($code)['http'], "code $code");
        }
    }

    public function testTodoErrorTraeMensajeYCausaEnCastellano()
    {
        foreach ([0, 1000, 1104, 1111, 1201, 11003, 13008] as $code) {
            $e = SigenergyErrores::traducir($code);
            $this->assertNotEmpty($e['mensaje'], "code $code sin mensaje");
            $this->assertNotEmpty($e['causa'], "code $code sin causa");
            $this->assertSame($code, $e['codigo']);
        }
    }

    /**
     * Ante un codigo que no conocemos preferimos reintentar antes que cachear
     * durante 5 minutos algo que no sabemos interpretar.
     */
    public function testCodigoDesconocidoNoSeCacheaYSeAvisa()
    {
        $e = SigenergyErrores::traducir(999999, 'algo raro');
        $this->assertTrue($e['transitorio']);
        $this->assertFalse($e['documentado']);
        $this->assertStringContainsString('999999', $e['causa']);
        $this->assertStringContainsString('algo raro', $e['causa']);
    }

    public function testTraducirDesdeLaRespuestaCruda()
    {
        $e = SigenergyErrores::deRespuesta(['code' => 1201, 'msg' => 'Access restriction', 'data' => []]);
        $this->assertSame(1201, $e['codigo']);
        $this->assertSame(429, $e['http']);
    }
}
