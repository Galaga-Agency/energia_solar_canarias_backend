<?php

enum Logs: string {
    // Casos del enum
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case DEBUG = 'DEBUG';
    case CRITICAL = 'CRITICAL';
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';

    // Verificar si un valor es válido para el enum
    public static function isValid(string $value): bool {
        return in_array($value, array_column(self::cases(), 'value'), true);
    }

    // Formatear un mensaje de log
    public static function logMessage(Logs $level, string $mensaje, int $userId): string {
        // Obtener el timestamp en el formato utilizado por MySQL
        $timestamp = date('Y-m-d H:i:s');

        // Construir el log en el formato deseado
        return sprintf("[%s] %d %s: %s", $timestamp, $userId, $level->value, $mensaje);
    }
}
?>