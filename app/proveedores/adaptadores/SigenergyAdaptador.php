<?php

require_once __DIR__ . '/../ProveedorBase.php';
require_once __DIR__ . '/../ErrorProveedor.php';
require_once __DIR__ . '/../../controllers/SigenergyController.php';
require_once __DIR__ . '/../../utils/SigenergyErrores.php';

/**
 * Sigenergy sobre la Openapi oficial (openapi-eu.sigencloud.com).
 *
 * No ofrece:
 *   - beneficios: su API no expone datos economicos.
 *   - resumen: no hay endpoint equivalente.
 *
 * Sus alertas NO son alarmas de verdad: la Openapi no tiene endpoint REST de alarmas
 * (solo push MQTT), asi que lo que sale son incidencias deducidas del estado de la
 * planta y de sus equipos. Ver SigenergyService::getSiteAlarms.
 */
class SigenergyAdaptador extends ProveedorBase
{
    private $c;

    public function __construct(?SigenergyController $controlador = null)
    {
        $this->c = $controlador ?? new SigenergyController();
    }

    public function nombre(): string { return 'Sigenergy'; }
    public function clave(): string { return 'sigenergy'; }

    /**
     * Traduce la forma de señalar errores de Sigenergy a la del contrato.
     *
     * Su API responde SIEMPRE HTTP 200 y mete el error real en `code` (0 = exito). Sin
     * esto, un "Station not permitted" sale como 200 con data vacio y nadie puede
     * distinguir "no hay datos" de "esta planta no es tuya".
     *
     * Va en TODAS las salidas del adaptador a proposito: antes esta traduccion habia
     * que invocarla a mano endpoint por endpoint (fallaSigenergy), y los que se
     * olvidaban devolvian los fallos como exitos. Aqui no hay nada que recordar.
     */
    private function oFalla(array $respuesta): array
    {
        if (!array_key_exists('code', $respuesta) || SigenergyErrores::esExito($respuesta)) {
            return $respuesta;
        }
        $e = SigenergyErrores::deRespuesta($respuesta);
        throw new ErrorProveedor($e['mensaje'], $e['http'], [
            'proveedor' => 'Sigenergy',
            'codigo_sigenergy' => $e['codigo'],
            'msg_sigenergy' => $respuesta['msg'] ?? null,
            'causa' => $e['causa'],
            'reintentable' => $e['transitorio'],
            'documentado' => $e['documentado'],
            // En un 1201 esto dice cuanto falta para poder reintentar.
            '_cache' => $respuesta['_cache'] ?? null,
        ]);
    }

    public function plantas(int $pagina = 1, int $porPagina = 2000): array
    {
        return $this->oFalla($this->decodificar($this->c->getAllPlants($pagina, $porPagina)));
    }

    public function detalle(string $planta): ?array
    {
        // getPlantDetails busca en la lista ya cacheada y devuelve null si no esta:
        // no trae envoltorio `code`, asi que no hay nada que traducir.
        return $this->decodificarONulo($this->c->getPlantDetails($planta));
    }

    public function tiempoReal(string $planta): array
    {
        return $this->oFalla($this->decodificar($this->c->getPlantPowerRealtime($planta)));
    }

    public function graficas(string $planta, array $params): array
    {
        // getGraficas saca el id del propio cuerpo; se fuerza para que mande el que
        // llega por el contrato y no el que viniera en el body.
        return $this->oFalla($this->decodificar($this->c->getGraficas($params + ['id' => $planta])));
    }

    public function inventario(string $planta): array
    {
        return $this->oFalla($this->decodificar($this->c->getInventario($planta)));
    }

    public function alertas(string $planta, int $pagina = 1, int $porPagina = 200): array
    {
        return $this->oFalla($this->decodificar($this->c->getSiteAlarms($planta, $pagina, $porPagina)));
    }
}
