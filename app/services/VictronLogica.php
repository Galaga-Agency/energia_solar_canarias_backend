<?php

/**
 * Reglas puras de Victron (VRM): sin red, sin BD, sin estado.
 *
 * Viven aparte de VictronEnergyService y de ApiControladorService para poder
 * probarlas con PHPUnit sin levantar nada. Todo lo que hay aqui se verifico
 * contra la API real de VRM (planta 335939, que dejo de reportar el
 * 2023-08-07 y sigue devolviendo su ultima foto con timestamps viejos).
 */
class VictronLogica
{
    /** Una instalacion que no reporta desde hace mas de una hora esta desconectada. */
    public const UMBRAL_DESCONEXION_S = 3600;

    /** Series de /stats que suman la energia SOLAR: a bateria, a consumo y a red. */
    public const SERIES_SOLARES = ['Pb', 'Pc', 'Pg'];

    /**
     * Cierto si el ultimo dato es mas viejo que el umbral, o si no hay dato.
     *
     * Sin `last_timestamp` no se puede afirmar que la planta este viva, asi que
     * cuenta como obsoleta.
     */
    public static function estaObsoleta($ultimoTimestamp, int $ahora, int $umbral = self::UMBRAL_DESCONEXION_S): bool
    {
        if (!is_numeric($ultimoTimestamp) || (int) $ultimoTimestamp <= 0) {
            return true;
        }
        return ($ahora - (int) $ultimoTimestamp) > $umbral;
    }

    /**
     * Estado operativo de la planta para la lista.
     *
     * Antes se ponia aqui el "Battery state" (atributo 215, `bst`): una planta
     * muerta desde 2023 salia como "Descargando" y el frontend lo traducia a
     * "funcionando". El estado de la bateria es otra cosa y viaja aparte.
     *
     *   ultimo dato > 1 h  -> 'disconnected' (manda sobre todo lo demas)
     *   alarma activa      -> 'error'
     *   resto              -> 'working'
     */
    public static function estadoPlanta($ultimoTimestamp, bool $alarma, int $ahora): string
    {
        if (self::estaObsoleta($ultimoTimestamp, $ahora)) {
            return 'disconnected';
        }
        return $alarma ? 'error' : 'working';
    }

    /** Hay alarma si VRM lo marca en `alarm` o cuenta alguna en `num_alarms`/`current_alarms`. */
    public static function tieneAlarma(array $planta): bool
    {
        if (!empty($planta['alarm'])) {
            return true;
        }
        if (isset($planta['num_alarms']) && is_numeric($planta['num_alarms']) && (int) $planta['num_alarms'] > 0) {
            return true;
        }
        return !empty($planta['current_alarms']) && is_array($planta['current_alarms']);
    }

    /**
     * Lo que la lista de plantas lee de una instalacion de VRM (`extended=1`).
     *
     * Devuelve SIEMPRE todas las claves, a null si no vienen: en el bucle del
     * listado estas variables no se reiniciaban y una planta sin bateria
     * heredaba el SOC, el voltaje y el estado de la anterior.
     *
     * @return array{status:string, batteryState:?string, batterySoc:mixed, batteryVoltage:mixed, potenciaSolarW:?float, lastTimestamp:?int}
     */
    public static function datosListado(array $planta, int $ahora): array
    {
        $out = [
            'batteryState' => null,
            'batterySoc' => null,
            'batteryVoltage' => null,
            'potenciaSolarW' => null,
        ];

        foreach (($planta['extended'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            // Los agregados (solar_yield, consumption...) traen idDataAttribute
            // a null y se reconocen por `code`.
            if (($item['code'] ?? null) === 'solar_yield' && isset($item['rawValue']) && is_numeric($item['rawValue'])) {
                $out['potenciaSolarW'] = (float) $item['rawValue'];
                continue;
            }
            if (!isset($item['idDataAttribute'], $item['rawValue'])) {
                continue;
            }
            switch ((int) $item['idDataAttribute']) {
                case 144:
                    $out['batterySoc'] = $item['rawValue'];
                    break;
                case 143:
                    $out['batteryVoltage'] = $item['rawValue'];
                    break;
                case 215:
                    $out['batteryState'] = $item['formattedValue'] ?? null;
                    break;
            }
        }

        $ultimo = isset($planta['last_timestamp']) && is_numeric($planta['last_timestamp'])
            ? (int) $planta['last_timestamp']
            : null;

        $out['lastTimestamp'] = $ultimo;
        $out['status'] = self::estadoPlanta($ultimo, self::tieneAlarma($planta), $ahora);

        // Una planta desconectada no produce "ahora": la potencia que VRM sigue
        // devolviendo es la de su ultima foto.
        if ($out['status'] === 'disconnected') {
            $out['potenciaSolarW'] = null;
        }

        return $out;
    }

    /**
     * Suma una serie de /stats (`[[ts_ms, valor], ...]`). Ignora lo que no sea par numerico.
     */
    public static function sumarSerie($serie): float
    {
        if (!is_array($serie)) {
            return 0.0; // VRM manda `false` cuando no hay datos
        }
        $total = 0.0;
        foreach ($serie as $punto) {
            $punto = (array) $punto;
            if (isset($punto[1]) && is_numeric($punto[1])) {
                $total += (float) $punto[1];
            }
        }
        return $total;
    }

    /**
     * Energia solar (kWh) de un /stats: Pb + Pc + Pg.
     *
     * Solo se sumaba Pb (solar a bateria): una planta que consume su solar al
     * momento salia con energia casi cero y sin ingresos.
     *
     * @param array|object $records el `records` de la respuesta
     */
    public static function energiaSolarKwh($records): float
    {
        $records = (array) $records;
        $total = 0.0;
        foreach (self::SERIES_SOLARES as $codigo) {
            $total += self::sumarSerie($records[$codigo] ?? null);
        }
        return $total;
    }

    /**
     * VRM no contesta /stats para rangos de mas de 5 anos (de 365 dias): con
     * start = syscreated de 2021 devolvia `totals: []` y la ficha decia "sin
     * dato". El rango se parte en ventanas de 4 anos, con margen.
     */
    public const VENTANA_MAXIMA_S = 4 * 365 * 86400;

    /**
     * Parte [desde, hasta] en ventanas contiguas de como mucho VENTANA_MAXIMA_S.
     *
     * @return list<array{0:int,1:int}>
     */
    public static function ventanasHistorico(int $desde, int $hasta, int $maximo = self::VENTANA_MAXIMA_S): array
    {
        if ($hasta <= $desde || $maximo <= 0) {
            return [];
        }
        $ventanas = [];
        for ($inicio = $desde; $inicio < $hasta; $inicio += $maximo) {
            $ventanas[] = [$inicio, min($inicio + $maximo, $hasta)];
        }
        return $ventanas;
    }

    /**
     * Suma los `totals` de varias ventanas, serie a serie. Una ventana sin datos
     * (VRM manda `[]`) no aporta nada; no anula las demas.
     *
     * @param list<array|object|null> $lista
     * @return array<string, float>
     */
    public static function sumarTotales(array $lista): array
    {
        $suma = [];
        foreach ($lista as $totals) {
            if (!is_array($totals) && !is_object($totals)) {
                continue;
            }
            foreach ((array) $totals as $codigo => $valor) {
                if (is_numeric($valor)) {
                    $suma[$codigo] = ($suma[$codigo] ?? 0.0) + (float) $valor;
                }
            }
        }
        return $suma;
    }

    /**
     * Total historico a partir de los `totals` de /stats?type=kwh&interval=years.
     *
     * Null cuando no viene ninguna de las tres series: "sin dato" no es 0 kWh.
     *
     * @param array|object|null $totals
     * @return array{kwh: float, Pb: ?float, Pc: ?float, Pg: ?float}|null
     */
    public static function totalHistorico($totals): ?array
    {
        if (!is_array($totals) && !is_object($totals)) {
            return null;
        }
        $totals = (array) $totals;
        $out = ['kwh' => 0.0];
        $alguno = false;
        foreach (self::SERIES_SOLARES as $codigo) {
            $valor = $totals[$codigo] ?? null;
            if (is_numeric($valor)) {
                $out[$codigo] = round((float) $valor, 3);
                $out['kwh'] += (float) $valor;
                $alguno = true;
            } else {
                $out[$codigo] = null;
            }
        }
        if (!$alguno) {
            return null;
        }
        $out['kwh'] = round($out['kwh'], 2);
        return $out;
    }
}
