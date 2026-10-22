<?php

/**
 * Traduce entre SEMS+ y la forma de respuesta del portal SEMS antiguo.
 *
 * GoodWe dio de baja www.semsportal.com (su login muestra un aviso de
 * "Platform Service Discontinuation" y manda a SEMS+). La API antigua sigue
 * contestando al login, al listado y al detalle, pero las graficas
 * (GetChartByPlant, GetPlantPowerChart) devuelven `lines: null` para todas las
 * plantas, incluso con la planta produciendo. Comprobado en vivo el 2026-10-15.
 *
 * SEMS+ sirve esos datos desde eu-gateway.semsportal.com y acepta el mismo
 * token que da CrossLogin, en la cabecera `token`. Endpoints sacados del
 * inspector de red de eu-semsplus.goodwe.com:
 *
 *   POST v1/hems/power/statisticsAndPreV2   potencia minuto a minuto (kW)
 *   POST stations/statistics                energia por dia, mes o año (kWh)
 *   GET  stations/flow                      flujo de energia en tiempo real (kW)
 *
 * Aqui solo se traduce: devuelve el MISMO formato que daba el portal antiguo
 * (`data.lines[]` con `xy`), de modo que el frontend y precioRealDelCliente()
 * siguen funcionando sin tocarlos. No hace peticiones; eso es de GoodWeService.
 */
class GoodWeSemsPlus
{
    /** Codigo de SEMS+ para un token caducado o desconocido. */
    const SIN_SESION = 'C0602';

    /** Codigo de exito de SEMS+. */
    const OK = '00000';

    /** Tramos de la curva diaria. 15 min = 96 puntos, lo que espera lastDay() del frontend. */
    const MINUTOS_TRAMO = 15;

    const RUTA_CURVA = 'v1/hems/power/statisticsAndPreV2';
    const RUTA_ESTADISTICAS = 'stations/statistics';
    const RUTA_FLUJO = 'stations/flow';

    /** Series de la curva: item de SEMS+ => etiqueta. */
    const SERIES_CURVA = [
        'pSystem' => 'Producción',
        'pConsum' => 'Consumo',
        'pGrid'   => 'Red',
    ];

    /** Series de energia que se piden siempre (sirven para todas las graficas). */
    const ITEMS_ESTADISTICAS = [
        'proSystemTotalStats',
        'proConsumStats',
        'proGridStats',
        'proPurchaseStats',
        'proSelfConsumStats',
    ];

    /**
     * Que series lleva cada grafica, con los codigos chartIndexId del portal antiguo.
     * name => [item de SEMS+, etiqueta]. `PVGeneration` es el nombre que busca
     * precioRealDelCliente() para calcular los ingresos con la tarifa del cliente.
     */
    const SERIES_POR_GRAFICA = [
        '3' => [ // generacion de energia y ingresos
            'PVGeneration' => ['proSystemTotalStats', 'Producción'],
        ],
        '5' => [ // proporcion para uso personal
            'SelfUse' => ['proSelfConsumStats', 'Autoconsumo'],
            'Sell'    => ['proGridStats', 'Vertido a red'],
        ],
        '8' => [ // indice de contribucion
            'Consumption' => ['proConsumStats', 'Consumo'],
            'SelfUse'     => ['proSelfConsumStats', 'Autoconsumo'],
            'Buy'         => ['proPurchaseStats', 'Compra a red'],
        ],
    ];

    /** Grafica que se responde con totales (`modelData`) en vez de series. */
    const GRAFICA_TOTALES = '7';

    /**
     * Rango del portal antiguo (2/3/4, o la palabra) => dimension de SEMS+.
     */
    public static function dimension($rango): string
    {
        switch ((string) $rango) {
            case '3':
            case 'mes':
                return 'month';
            case '4':
            case 'año':
                return 'year';
            default:
                return 'day';
        }
    }

    public static function cuerpoCurva(string $planta, string $fecha): array
    {
        $dia = self::dia($fecha);
        return [
            'stationId' => $planta,
            'items' => array_keys(self::SERIES_CURVA),
            'timeScale' => 1,
            'timeZone' => -1,
            'startTime' => $dia . ' 00:00:00',
            'endTime' => $dia . ' 23:59:59',
        ];
    }

    /**
     * Ventana que devolvia el portal antiguo para cada grano, terminando en `fecha`:
     * 31 dias, 12 meses o 10 años. El frontend ya cuenta con esos tamaños
     * (trimToRange recorta la semana a los 7 ultimos).
     */
    public static function cuerpoEstadisticas(string $planta, $rango, string $fecha): array
    {
        $fin = new DateTimeImmutable(self::dia($fecha));
        $dimension = self::dimension($rango);

        switch ($dimension) {
            case 'month':
                $desde = $fin->modify('first day of this month')->modify('-11 months');
                $hasta = $fin->modify('last day of this month');
                break;
            case 'year':
                $desde = $fin->modify('first day of january this year')->modify('-9 years');
                $hasta = $fin->modify('last day of december this year');
                break;
            default:
                $desde = $fin->modify('-30 days');
                $hasta = $fin;
        }

        return [
            'stationId' => $planta,
            'isReport' => false,
            'items' => self::ITEMS_ESTADISTICAS,
            'dimension' => $dimension,
            'startTime' => $desde->format('Y-m-d') . ' 00:00:00',
            'endTime' => $hasta->format('Y-m-d') . ' 23:59:59',
        ];
    }

    /**
     * Curva minuto a minuto => lineas del portal antiguo, en tramos de 15 min y en W.
     *
     * El portal antiguo daba la potencia en vatios y el frontend arrastra la
     * unidad, asi que se mantiene W. Un tramo es la media de sus minutos con
     * dato. Los tramos sin ningun dato en ninguna serie (las horas que aun no
     * han pasado) no se envian: el grafico terminaria en una meseta vacia.
     */
    public static function respuestaCurva(string $planta, array $plus): array
    {
        $porItem = [];
        foreach (($plus['data']['dataList'] ?? []) as $serie) {
            $item = $serie['item'] ?? null;
            if (!isset(self::SERIES_CURVA[$item])) {
                continue;
            }
            foreach (($serie['powerData'] ?? []) as $punto) {
                $hora = self::tramo($punto['tp'] ?? '');
                if ($hora === null || !is_numeric($punto['power'] ?? null)) {
                    continue;
                }
                $porItem[$item][$hora][] = (float) $punto['power'];
            }
        }

        $tramos = [];
        foreach ($porItem as $valores) {
            foreach (array_keys($valores) as $hora) {
                $tramos[$hora] = true;
            }
        }
        ksort($tramos);

        $lineas = [];
        foreach (self::SERIES_CURVA as $item => $etiqueta) {
            $xy = [];
            foreach (array_keys($tramos) as $hora) {
                $valores = $porItem[$item][$hora] ?? [];
                $xy[] = [
                    'x' => $hora,
                    'y' => $valores ? round(array_sum($valores) / count($valores) * 1000, 1) : null,
                    'z' => null,
                ];
            }
            if ($xy) {
                $lineas[] = ['name' => 'PCurve_' . $item, 'label' => $etiqueta, 'unit' => 'W', 'xy' => $xy];
            }
        }

        return self::sobre($planta, ['lines' => $lineas], $plus['_cache'] ?? null);
    }

    /**
     * Energia por dia/mes/año => lineas del portal antiguo (o totales para la grafica 7).
     */
    public static function respuestaEstadisticas(string $planta, string $grafica, array $plus): array
    {
        $datos = $plus['data'] ?? [];

        if ($grafica === self::GRAFICA_TOTALES) {
            return self::sobre($planta, ['lines' => [], 'modelData' => self::totales($datos)], $plus['_cache'] ?? null);
        }

        $listas = [];
        foreach (($datos['dataList'] ?? []) as $serie) {
            $listas[$serie['item'] ?? ''] = $serie['statisticsList'] ?? [];
        }

        $lineas = [];
        foreach ((self::SERIES_POR_GRAFICA[$grafica] ?? self::SERIES_POR_GRAFICA['3']) as $nombre => [$item, $etiqueta]) {
            $xy = [];
            foreach (($listas[$item] ?? []) as $punto) {
                $xy[] = [
                    'x' => (string) ($punto['date'] ?? ''),
                    'y' => is_numeric($punto['val'] ?? null) ? round((float) $punto['val'], 2) : null,
                    'z' => null,
                ];
            }
            $lineas[] = ['name' => $nombre, 'label' => $etiqueta, 'unit' => 'kWh', 'xy' => $xy];
        }

        return self::sobre($planta, ['lines' => $lineas], $plus['_cache'] ?? null);
    }

    /**
     * Flujo de SEMS+ => bloque `powerflow` del portal antiguo, o null si no hay medida.
     *
     * Solo se usa cuando el portal antiguo dice que la planta no tiene flujo:
     * SEMS+ no informa de la bateria en este endpoint, y el antiguo si.
     * Las potencias van como el antiguo, en texto con unidad ("12915W"). La red
     * se manda en magnitud: el frontend deduce el sentido del balance.
     */
    public static function flujoComoPowerflow(array $plus): ?array
    {
        $d = $plus['data'] ?? [];
        if (!is_numeric($d['pSystem'] ?? null)) {
            return null;
        }
        $w = function ($kw) {
            return is_numeric($kw) ? round(abs((float) $kw) * 1000, 1) . 'W' : '0W';
        };
        return [
            'pv' => $w($d['pSystem']),
            'pvStatus' => (float) $d['pSystem'] > 0 ? 1 : 0,
            'load' => $w($d['pConsum'] ?? null),
            'loadStatus' => 1,
            'grid' => $w($d['pGrid'] ?? null),
            'gridStatus' => 1,
            // Sin claves de bateria: con '0W' el diagrama pintaba una bateria
            // vacia en plantas que no tienen.
        ];
    }

    private static function totales(array $d): array
    {
        $n = function ($v) {
            return is_numeric($v) ? round((float) $v, 2) : 0;
        };
        return [
            'sum' => $n($d['production'] ?? null),
            'sell' => $n($d['proGrid'] ?? null),
            'sellPercent' => $n($d['proGridRate'] ?? null),
            'buy' => $n($d['proPurchase'] ?? null),
            'buyPercent' => $n($d['proPurchaseRate'] ?? null),
            'selfUseOfPv' => $n($d['proSelfConsum'] ?? null),
            'selfUseRatio' => $n($d['proSelfConsumRate'] ?? null),
            'consumptionOfLoad' => $n($d['proConsum'] ?? null),
            // El frontend lo trata como FRACCION (GOODWE_MODEL_FRACTIONS); SEMS+ lo da en %.
            'contributionRatio' => round($n($d['contributionRate'] ?? null) / 100, 4),
        ];
    }

    /** El sobre del portal antiguo; `components.para` lleva el id que lee precioRealDelCliente(). */
    private static function sobre(string $planta, array $datos, ?array $meta = null): array
    {
        $respuesta = [
            'language' => 'en',
            'hasError' => false,
            'code' => 0,
            'msg' => 'success',
            'data' => $datos,
            'components' => ['para' => json_encode(['model' => ['Id' => $planta]])],
        ];
        if ($meta !== null) $respuesta['_cache'] = $meta;
        return $respuesta;
    }

    /** "2026-10-14 13:07:00" => "13:00" (inicio de su tramo de 15 min). */
    private static function tramo(string $tp): ?string
    {
        if (!preg_match('/(\d{2}):(\d{2})(:\d{2})?$/', trim($tp), $m)) {
            return null;
        }
        $minuto = intdiv((int) $m[2], self::MINUTOS_TRAMO) * self::MINUTOS_TRAMO;
        return sprintf('%02d:%02d', (int) $m[1], $minuto);
    }

    private static function dia(string $fecha): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr(trim($fecha), 0, 10));
        return $d ? $d->format('Y-m-d') : date('Y-m-d');
    }
}
