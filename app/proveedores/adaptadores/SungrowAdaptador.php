<?php

require_once __DIR__ . '/../ProveedorBase.php';
require_once __DIR__ . '/../../controllers/SungrowController.php';

/**
 * Sungrow (iSolarCloud).
 *
 * No ofrece:
 *   - resumen: no hay endpoint equivalente.
 *
 * Sus alertas son solo el RESUMEN (contadores): el listado detallado necesita permisos
 * E900 que esta cuenta no tiene. Y sus graficas salen vacias en los inversores
 * hibridos, que son la mayoria del parque; parece el mismo limite de plan. Ver el
 * README de bruno/.
 */
class SungrowAdaptador extends ProveedorBase
{
    private $c;

    public function __construct(?SungrowController $controlador = null)
    {
        $this->c = $controlador ?? new SungrowController();
    }

    public function nombre(): string { return 'Sungrow'; }
    public function clave(): string { return 'sungrow'; }

    public function plantas(int $pagina = 1, int $porPagina = 2000): array
    {
        return $this->decodificar($this->c->getAllPlants($pagina, $porPagina));
    }

    public function detalle(string $planta): ?array
    {
        return $this->decodificarONulo($this->c->getPlantDetails($planta));
    }

    public function tiempoReal(string $planta): array
    {
        return $this->decodificar($this->c->getPlantPowerRealtime($planta));
    }

    public function graficas(string $planta, array $params): array
    {
        return $this->decodificar($this->c->getGraficas($params + ['id' => $planta]));
    }

    public function inventario(string $planta): array
    {
        return $this->decodificar($this->c->getInventario($planta));
    }

    public function alertas(string $planta, int $pagina = 1, int $porPagina = 200): array
    {
        return $this->decodificar($this->c->getSiteAlarms($planta, $pagina, $porPagina));
    }

    public function beneficios(string $planta): array
    {
        return $this->decodificar($this->c->getPlantPowerBenefits($planta));
    }
}
