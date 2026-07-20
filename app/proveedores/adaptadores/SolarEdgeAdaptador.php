<?php

require_once __DIR__ . '/../ProveedorBase.php';
require_once __DIR__ . '/../../controllers/SolarEdgeController.php';

/**
 * SolarEdge (monitoring API).
 *
 * No ofrece:
 *   - alertas: su API no las expone. El router ya respondia 404 a proposito.
 *
 * Es el unico con beneficios y resumen, y el unico cuya grafica recibe los parametros
 * sueltos en vez de un array; la traduccion se hace aqui.
 *
 * Ojo con las paginas: su API rechaza la peticion ENTERA si pageSize pasa de 100 (no
 * la recorta), y startIndex es un desplazamiento, no un numero de pagina. Eso ya lo
 * controla SolarEdgeService::getAllPlants.
 */
class SolarEdgeAdaptador extends ProveedorBase
{
    private $c;

    public function __construct(?SolarEdgeController $controlador = null)
    {
        $this->c = $controlador ?? new SolarEdgeController();
    }

    public function nombre(): string { return 'SolarEdge'; }
    public function clave(): string { return 'solaredge'; }

    public function plantas(int $pagina = 1, int $porPagina = 2000): array
    {
        return $this->decodificar($this->c->getAllPlants($pagina, $porPagina));
    }

    public function detalle(string $planta): ?array
    {
        return $this->decodificarONulo($this->c->getSiteDetails($planta));
    }

    public function tiempoReal(string $planta): array
    {
        return $this->decodificar($this->c->getPlantPowerRealtime($planta));
    }

    /**
     * $params: dia (timeUnit), fechaInicio, fechaFin. Son los nombres que ya usaba
     * getEnergyDashBoardCuerpo(), se mantienen para no romper al frontend.
     */
    public function graficas(string $planta, array $params): array
    {
        return $this->decodificar($this->c->getPowerDashboard(
            $planta,
            $params['dia'] ?? $params['timeUnit'] ?? null,
            $params['fechaFin'] ?? $params['endTime'] ?? null,
            $params['fechaInicio'] ?? $params['startTime'] ?? null
        ));
    }

    public function inventario(string $planta): array
    {
        return $this->decodificar($this->c->inventarioSolarEdge($planta));
    }

    public function beneficios(string $planta): array
    {
        return $this->decodificar($this->c->getPlantPowerBenefits($planta));
    }

    public function resumen(string $planta): array
    {
        return $this->decodificar($this->c->overviewSolarEdge($planta));
    }
}
