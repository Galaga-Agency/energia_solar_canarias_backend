<?php

/**
 * Cache compartida para operaciones de planta que aún llaman directamente al proveedor.
 * GoodWe y Sigenergy tienen políticas propias por sus peculiaridades y límites.
 */
final class ProveedorCachePolitica
{
    private const TTL = [
        'solaredge' => [
            'tiempo real' => 60,
            'inventario' => 900,
            'beneficios' => 3600,
            'resumen' => 300,
        ],
        'sungrow' => [
            'tiempo real' => 60,
            'inventario' => 300,
            'alertas' => 120,
        ],
        'victronenergy' => [
            'tiempo real' => 60,
            'inventario' => 300,
            'alertas' => 120,
        ],
    ];

    public static function ttl(string $proveedor, string $operacion): ?int
    {
        return self::TTL[strtolower(trim($proveedor))][$operacion] ?? null;
    }
}
