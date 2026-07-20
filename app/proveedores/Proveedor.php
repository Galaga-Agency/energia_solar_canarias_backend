<?php

/**
 * Contrato comun de un proveedor de plantas solares.
 *
 * Nace de un problema medible: cada proveedor tenia su propio vocabulario para lo
 * mismo. "Dame los equipos de esta planta" se llamaba GetInverterAllPoint en GoodWe,
 * inventarioSolarEdge en SolarEdge, getSiteEquipo en VictronEnergy y getInventario en
 * Sungrow y Sigenergy. Sin un contrato, cada endpoint tenia que enumerar los cinco
 * casos: 43 metodos en ApiControladorService y 49 ramas en el router.
 *
 * Eso no era feo y ya: era la causa de los fallos. Sigenergy faltaba en unas rutas y
 * estaba en otras, el control de acceso hubo que ponerlo en nueve sitios y la rama de
 * cliente de /plants/graficas se quedo a medias. Con un contrato, esos olvidos dejan
 * de ser posibles: o lo implementas, o no compila.
 *
 * Las implementaciones son ADAPTADORES: no reescriben la logica de cada proveedor,
 * traducen su vocabulario a este. Asi el comportamiento de cada API no cambia.
 *
 * Todo devuelve arrays ya decodificados. Los controladores antiguos devuelven strings
 * JSON que la fachada volvia a decodificar; ese ida y vuelta se queda dentro del
 * adaptador y no se ve desde fuera.
 *
 * Lo que un proveedor no sepa hacer lanza OperacionNoSoportada (ver ProveedorBase),
 * que la fachada traduce a 404. Asi "SolarEdge no tiene alertas" se declara en la
 * clase de SolarEdge, en vez de estar escondido en un case del router.
 *
 * @see ProveedorBase       implementacion por defecto: todo no soportado
 * @see RegistroProveedores como se obtiene un proveedor por nombre
 */
interface Proveedor
{
    /** Nombre canonico, tal cual esta en la tabla `proveedores` (p.ej. "Sigenergy"). */
    public function nombre(): string;

    /** Clave del proveedor en la URL `?proveedor=` (p.ej. "sigenergy"). */
    public function clave(): string;

    /** Listado de plantas de la cuenta. */
    public function plantas(int $pagina = 1, int $porPagina = 2000): array;

    /** Detalle de una planta. null si no existe. */
    public function detalle(string $planta): ?array;

    /** Potencia y energia ahora mismo. */
    public function tiempoReal(string $planta): array;

    /** Serie temporal. $params depende del proveedor (level/date, rango, etc.). */
    public function graficas(string $planta, array $params): array;

    /** Equipos de la planta (inversores, baterias, medidores...). */
    public function inventario(string $planta): array;

    /** Alertas o incidencias de la planta. */
    public function alertas(string $planta, int $pagina = 1, int $porPagina = 200): array;

    /** Beneficios economicos. */
    public function beneficios(string $planta): array;

    /** Resumen/overview de la planta. */
    public function resumen(string $planta): array;
}
