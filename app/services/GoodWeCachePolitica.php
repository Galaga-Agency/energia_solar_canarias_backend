<?php

/** Identidad y tiempos de los datos BRUTOS de GoodWe, nunca respuestas de usuarios. */
class GoodWeCachePolitica
{
    public static function para(string $url, array $datos, ?string $hoy = null): ?array
    {
        $ruta = strtolower(rtrim((string) parse_url($url, PHP_URL_PATH), '/'));
        $historico = false;
        if (str_ends_with($ruta, '/getpowerflow') || str_ends_with($ruta, '/stations/flow')) {
            $ttl = 60;
        } elseif (str_ends_with($ruta, '/getplantdetailbypowerstationid')) {
            $ttl = 120;
        } elseif (str_ends_with($ruta, '/getpowerstationwarininginfobymulticondition')) {
            $ttl = 120;
        } elseif (str_ends_with($ruta, '/querypowerstationmonitor')) {
            $ttl = 600;
        } elseif (str_ends_with($ruta, '/getinverterallpoint')) {
            $ttl = 1800;
        } elseif (str_ends_with($ruta, '/statisticsandprev2') || str_ends_with($ruta, '/stations/statistics')) {
            $hoy = $hoy ?? (new DateTimeImmutable('now', new DateTimeZone('Atlantic/Canary')))->format('Y-m-d');
            $fin = substr((string) ($datos['endTime'] ?? ''), 0, 10);
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fin);
            // Ayer todavia no esta cerrado: los inversores suben datos con retraso y
            // GoodWe consolida durante el dia siguiente. Congelarlo 24 h nada mas
            // pasar la medianoche dejaba fuera esas correcciones.
            $ayer = (new DateTimeImmutable($hoy))->modify('-1 day')->format('Y-m-d');
            $historico = $fecha && $fecha->format('Y-m-d') === $fin && $fin < $ayer;
            $ttl = $historico ? 86400 : 300;
        } else {
            // Ningun login ni endpoint nuevo se cachea por accidente.
            return null;
        }
        return ['ttl' => $ttl, 'max_edad' => $historico ? 604800 : max(900, $ttl * 3)];
    }

    public static function identidad(string $cuenta, string $metodo, string $url, array $datos): string
    {
        // No incluir JWT, contraseña ni token del proveedor. Canonizar los mapas
        // evita llamadas duplicadas por el orden de propiedades del mismo cuerpo.
        return 'goodwe-datos-v1:' . json_encode([
            'cuenta' => hash('sha256', strtolower(trim($cuenta))),
            'metodo' => strtoupper($metodo),
            'url' => $url,
            'datos' => self::ordenar($datos),
        ], JSON_THROW_ON_ERROR);
    }

    private static function ordenar(array $datos): array
    {
        if (!array_is_list($datos)) ksort($datos);
        foreach ($datos as &$valor) {
            if (is_array($valor)) $valor = self::ordenar($valor);
        }
        return $datos;
    }

    public static function esValida(array $respuesta): bool
    {
        return ($respuesta['hasError'] ?? false) !== true
            && !isset($respuesta['error'])
            && in_array((string) ($respuesta['code'] ?? ''), ['0', '00000'], true)
            && array_key_exists('data', $respuesta);
    }
}
