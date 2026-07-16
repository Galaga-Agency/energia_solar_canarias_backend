<?php

require_once __DIR__ . '/../ProveedorBase.php';
require_once __DIR__ . '/../../controllers/GoodWeController.php';

/**
 * GoodWe (SEMS Portal).
 *
 * No ofrece:
 *   - beneficios ni resumen: el router ya respondia 404 para los dos.
 *
 * OJO con las alertas: GoodWe es el unico cuyo endpoint NO acepta una planta.
 * GetPowerStationWariningInfoByMultiCondition devuelve las alarmas de TODO el parque
 * filtradas por estado. Aqui se declara no soportada a proposito: el contrato dice
 * "alertas DE UNA PLANTA" y GoodWe no sabe hacer eso.
 *
 * Fingir que si (ignorando el id y devolviendo las de todas) seria peor que no
 * ofrecerla: un cliente pediria las de su planta y recibiria las del parque entero,
 * que es justo la fuga que cerramos con puedeVerPlanta(). El listado global sigue
 * disponible para admin en su ruta, fuera de este contrato.
 */
class GoodWeAdaptador extends ProveedorBase
{
    private $c;

    public function __construct(?GoodWeController $controlador = null)
    {
        $this->c = $controlador ?? new GoodWeController();
    }

    public function nombre(): string { return 'GoodWe'; }
    public function clave(): string { return 'goodwe'; }

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
        return $this->decodificar($this->c->getChartByPlants($params + ['id' => $planta]));
    }

    /** En GoodWe el inventario se llama GetInverterAllPoint. */
    public function inventario(string $planta): array
    {
        return $this->decodificar($this->c->GetInverterAllPoint($planta));
    }
}
