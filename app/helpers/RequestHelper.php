<?php

class RequestHelper
{
    /**
     * Obtiene un parámetro desde $_GET o desde los headers
     */
    public static function getParam(string $key): ?string
    {
        // Buscar primero en GET
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        // Buscar en headers
        $headers = array_change_key_case(getallheaders(), CASE_LOWER); // Normaliza los headers
        $keyLower = strtolower($key);

        return $headers[$keyLower] ?? null;
    }

    /**
     * Obtener todos los headers normalizados
     */
    public static function getHeaders(): array
    {
        return array_change_key_case(getallheaders(), CASE_LOWER);
    }

    /**
     * Verifica si el parámetro está presente en GET o headers
     */
    public static function hasParam(string $key): bool
    {
        return isset($_GET[$key]) || isset(self::getHeaders()[strtolower($key)]);
    }
}
