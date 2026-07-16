<?php

require_once __DIR__ . '/Proveedor.php';
require_once __DIR__ . '/OperacionNoSoportada.php';

/**
 * Base de los adaptadores: por defecto NADA esta soportado.
 *
 * Cada adaptador sobrescribe solo lo que su proveedor sabe hacer. Asi una clase de
 * proveedor se lee como la lista de lo que ofrece, y lo que no aparece es lo que no
 * tiene: SolarEdge no implementa alertas() y con eso queda dicho.
 *
 * Es lo contrario de obligar a los cinco a implementar los nueve metodos y rellenar
 * los que no aplican con un "throw" copiado (que es la version con interfaz de la
 * misma duplicacion que estamos quitando).
 */
abstract class ProveedorBase implements Proveedor
{
    /** Lanza OperacionNoSoportada con el nombre del metodo que la llamo. */
    protected function noSoportada(string $operacion): void
    {
        throw new OperacionNoSoportada($this->nombre(), $operacion);
    }

    /**
     * Decodifica lo que devuelven los controladores antiguos.
     *
     * Hacen json_encode de un array para que la fachada haga json_decode justo
     * despues. Ese ida y vuelta se queda aqui dentro: de la interfaz para afuera
     * siempre salen arrays.
     *
     * @param string|array|null $respuesta
     */
    protected function decodificar($respuesta): array
    {
        if (is_array($respuesta)) {
            return $respuesta;
        }
        if ($respuesta === null || $respuesta === '') {
            return [];
        }
        $d = json_decode((string) $respuesta, true);
        return is_array($d) ? $d : [];
    }

    /** Igual que decodificar(), pero admite null (p.ej. una planta que no existe). */
    protected function decodificarONulo($respuesta): ?array
    {
        if ($respuesta === null || $respuesta === '' || $respuesta === 'null') {
            return null;
        }
        $d = is_array($respuesta) ? $respuesta : json_decode((string) $respuesta, true);
        return is_array($d) ? $d : null;
    }

    public function plantas(int $pagina = 1, int $porPagina = 2000): array
    {
        $this->noSoportada('plantas');
    }

    public function detalle(string $planta): ?array
    {
        $this->noSoportada('detalle');
    }

    public function tiempoReal(string $planta): array
    {
        $this->noSoportada('tiempoReal');
    }

    public function graficas(string $planta, array $params): array
    {
        $this->noSoportada('graficas');
    }

    public function inventario(string $planta): array
    {
        $this->noSoportada('inventario');
    }

    public function alertas(string $planta, int $pagina = 1, int $porPagina = 200): array
    {
        $this->noSoportada('alertas');
    }

    public function beneficios(string $planta): array
    {
        $this->noSoportada('beneficios');
    }

    public function resumen(string $planta): array
    {
        $this->noSoportada('resumen');
    }
}
