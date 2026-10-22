<?php

/**
 * Conversiones del Monitoring API V1 de SolarEdge a las unidades comunes de la app.
 *
 * El proveedor incluye una unidad por respuesta: el flujo puede venir en W o kW,
 * mientras que energy/energyDetails declaran Wh o kWh. La interfaz de plantas trabaja
 * con kW y kWh, por lo que no se deben asumir unidades a partir del valor.
 */
class SolarEdgeLogica
{
    /** Normaliza un currentPowerFlow (envuelto o plano) a kW. */
    public static function flujoEnKw(array $respuesta): array
    {
        $anidado = array_key_exists('siteCurrentPowerFlow', $respuesta);
        $flujo = $anidado ? $respuesta['siteCurrentPowerFlow'] : $respuesta;
        if (!is_array($flujo) || $flujo === []) {
            return $respuesta;
        }

        $unidad = strtolower(trim((string) ($flujo['unit'] ?? '')));
        $factor = match ($unidad) {
            'w', 'watt', 'watts' => 0.001,
            'kw', 'kilowatt', 'kilowatts' => 1.0,
            default => null,
        };

        foreach (['GRID', 'LOAD', 'PV', 'STORAGE'] as $nodo) {
            if (!isset($flujo[$nodo]) || !is_array($flujo[$nodo]) || !array_key_exists('currentPower', $flujo[$nodo])) {
                continue;
            }

            $valor = $flujo[$nodo]['currentPower'];
            if ($valor === null || $valor === '') {
                continue;
            }
            if (!is_numeric($valor) || $factor === null) {
                // Una unidad desconocida no se debe presentar como si fueran kW.
                $flujo[$nodo]['currentPowerRaw'] = $valor;
                $flujo[$nodo]['currentPower'] = null;
                continue;
            }

            $flujo[$nodo]['currentPower'] = round((float) $valor * $factor, 6);
        }

        if ($factor !== null) {
            $flujo['unit'] = 'kW';
        }

        if ($anidado) {
            $respuesta['siteCurrentPowerFlow'] = $flujo;
            return $respuesta;
        }

        return $flujo;
    }

    /**
     * Convierte los puntos de energía declarados en Wh/kWh a kWh.
     * Puntos sin lectura mantienen null; una unidad no reconocida nunca se etiqueta
     * falsamente como kWh.
     */
    public static function serieEnergiaEnKwh(array $puntos, ?string $unidad): array
    {
        $unidadNormalizada = strtolower(trim((string) $unidad));
        $factor = match ($unidadNormalizada) {
            'wh' => 0.001,
            'kwh' => 1.0,
            default => null,
        };

        foreach ($puntos as &$punto) {
            if (!is_array($punto) || !array_key_exists('value', $punto) || $punto['value'] === null) {
                continue;
            }

            if ($factor === null || !is_numeric($punto['value'])) {
                $punto['value'] = null;
                continue;
            }

            $punto['value'] = round((float) $punto['value'] * $factor, 6);
        }
        unset($punto);

        return $puntos;
    }
}
