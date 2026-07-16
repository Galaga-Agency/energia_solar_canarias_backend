<?php

require_once __DIR__ . '/../proveedores/RegistroProveedores.php';
require_once __DIR__ . '/../proveedores/ErrorProveedor.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../controllers/LogsController.php';

/**
 * Fachada de los endpoints de planta: UN metodo por endpoint, no uno por proveedor.
 *
 * Sustituye a los bloques de ApiControladorService del tipo getInventarioSungrow /
 * getInventarioSigenergy / GetInverterAllPoint / inventarioSolarEdge /
 * getSiteEquipoVictronEnergy: cinco metodos casi identicos donde lo unico que cambiaba
 * era a quien se llamaba. Ahora se pide el proveedor al registro y se usa el contrato.
 *
 * Aqui vive lo comun (dar forma a la respuesta, los logs, los codigos HTTP) y en el
 * adaptador lo propio de cada API. Antes eso estaba mezclado y copiado cinco veces, y
 * de ahi salian las diferencias tontas entre proveedores.
 *
 * @see Proveedor            el contrato
 * @see RegistroProveedores  como se resuelve `?proveedor=`
 */
class ProveedorApiService
{
    private $registro;
    private $logs;

    public function __construct(?RegistroProveedores $registro = null, $logs = null)
    {
        $this->registro = $registro ?? RegistroProveedores::porDefecto();
        $this->logs = $logs ?? new LogsController();
    }

    public function inventario(string $clave, string $planta): void
    {
        $this->ejecutar($clave, 'inventario', fn(Proveedor $p) => $p->inventario($planta), $planta);
    }

    public function tiempoReal(string $clave, string $planta): void
    {
        $this->ejecutar($clave, 'tiempo real', fn(Proveedor $p) => $p->tiempoReal($planta), $planta);
    }

    public function alertas(string $clave, string $planta, int $pagina = 1, int $porPagina = 200): void
    {
        $this->ejecutar($clave, 'alertas', fn(Proveedor $p) => $p->alertas($planta, $pagina, $porPagina), $planta);
    }

    public function beneficios(string $clave, string $planta): void
    {
        $this->ejecutar($clave, 'beneficios', fn(Proveedor $p) => $p->beneficios($planta), $planta);
    }

    public function resumen(string $clave, string $planta): void
    {
        $this->ejecutar($clave, 'resumen', fn(Proveedor $p) => $p->resumen($planta), $planta);
    }

    /**
     * El unico sitio donde se decide que HTTP devolver.
     *
     * Estaba copiado en cada metodo por proveedor, y por eso habia diferencias sin
     * motivo. Los casos:
     *   proveedor que no existe -> 404
     *   proveedor que no ofrece esa operacion -> 404 (lo dice el, no un case del router)
     *   sin datos -> 400
     *   excepcion -> 500
     */
    private function ejecutar(string $clave, string $operacion, callable $llamada, string $planta): void
    {
        $respuesta = new Respuesta;
        try {
            $proveedor = $this->registro->obtener($clave);
            $datos = $llamada($proveedor);

            if ($datos === null || $datos === [] || isset($datos['error'])) {
                $this->logs->registrarLog(Logs::INFO, "No hay $operacion en {$proveedor->nombre()} para $planta");
                $respuesta->_400(is_array($datos) ? $datos : []);
                $respuesta->message = "No se han encontrado datos de $operacion";
                http_response_code(400);
            } else {
                $this->logs->registrarLog(Logs::INFO, "$operacion de {$proveedor->nombre()} para $planta");
                $respuesta->success($datos);
            }
        } catch (ProveedorDesconocido $e) {
            $respuesta->_404();
            $respuesta->message = 'El proveedor no es valido';
            http_response_code(404);
        } catch (ErrorProveedor $e) {
            // El proveedor dijo que no. El adaptador ya lo tradujo a un mensaje en
            // castellano y al HTTP que toca; aqui solo se emite.
            $this->logs->registrarLog(
                ($e->detalles()['reintentable'] ?? false) ? Logs::ERROR : Logs::INFO,
                "$clave $operacion: " . $e->getMessage() . ' | ' . json_encode($e->detalles())
            );
            $respuesta->status = false;
            $respuesta->code = $e->http();
            $respuesta->message = $e->getMessage();
            $respuesta->data = array_filter($e->detalles(), fn($v) => $v !== null);
            http_response_code($e->http());
        } catch (OperacionNoSoportada $e) {
            // No es un fallo: esa API no ofrece esto. Lo declara el propio proveedor.
            $this->logs->registrarLog(Logs::INFO, $e->getMessage());
            $respuesta->_404();
            $respuesta->message = $e->getMessage();
            http_response_code(404);
        } catch (Throwable $e) {
            $this->logs->registrarLog(Logs::ERROR, "Error en $operacion de $clave: " . $e->getMessage());
            $respuesta->_500();
            $respuesta->message = 'Error en el servidor de algun proveedor';
            http_response_code(500);
        }

        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }
}
