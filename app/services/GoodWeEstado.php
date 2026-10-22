<?php

/**
 * Estado de una planta GoodWe, una sola vez para la lista y el detalle.
 *
 * El `status` que da GoodWe para la planta es el PEOR de sus inversores:
 * -1 sin conexion, 0 en espera, 1 generando, 2 averia. Una planta con un inversor
 * caido y otro produciendo llega como -1 con potencia > 0, y se mostraba como
 * "Desconectado" con 1,2 kW (Aparcamientos Tomas Miller, 2026-10-21). Eso no es
 * una planta caida: es una caida parcial, y se marca como aviso.
 */
class GoodWeEstado
{
    public static function dePlanta($codigo, $potenciaW = 0): string
    {
        switch ((int) $codigo) {
            case 2:
                return 'error';
            case 1:
                return 'working';
            case 0:
                return 'waiting';
            case -1:
                // Genera algo: al menos un inversor esta vivo.
                return is_numeric($potenciaW) && (float) $potenciaW > 0 ? 'warning' : 'disconnected';
            default:
                return 'unknown';
        }
    }

    /** true si es una caida parcial (algun inversor sin conexion pero la planta produce). */
    public static function esParcial($codigo, $potenciaW = 0): bool
    {
        return (int) $codigo === -1 && is_numeric($potenciaW) && (float) $potenciaW > 0;
    }
}
