<?php

require_once __DIR__ . '/../ProveedorBase.php';
require_once __DIR__ . '/../../controllers/VictronEnergyController.php';

/**
 * VictronEnergy (VRM).
 *
 * No ofrece:
 *   - beneficios ni resumen: el router ya respondia 404 para los dos.
 *
 * Su vocabulario es el que mas se aparta: "site" en vez de "plant" (getSiteDetails,
 * getSiteRealtime, getSiteEquipo). Aqui se traduce y desde fuera se llama igual que
 * los demas.
 */
class VictronEnergyAdaptador extends ProveedorBase
{
    private $c;

    public function __construct(?VictronEnergyController $controlador = null)
    {
        $this->c = $controlador ?? new VictronEnergyController();
    }

    public function nombre(): string { return 'VictronEnergy'; }
    public function clave(): string { return 'victronenergy'; }

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
        return $this->decodificar($this->c->getSiteRealtime($planta));
    }

    public function graficas(string $planta, array $params): array
    {
        return $this->decodificar($this->c->getGraficoDetails($params + ['id' => $planta]));
    }

    public function inventario(string $planta): array
    {
        return $this->decodificar($this->c->getSiteEquipo($planta));
    }

    public function alertas(string $planta, int $pagina = 1, int $porPagina = 200): array
    {
        return $this->decodificar($this->c->getSiteAlarms($planta, $pagina, $porPagina));
    }
}
