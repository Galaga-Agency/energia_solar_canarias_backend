<?php

/**
 * Utilidades de seguridad. ISO 27001:2022 A.8.5 (autenticacion) y A.8.15/8.16 (logging).
 */

if (!function_exists('ipCliente')) {
    /**
     * IP del cliente. Se usa REMOTE_ADDR (fiable). X-Forwarded-For NO se usa por
     * defecto porque se puede falsificar; si en el futuro hay un proxy de confianza
     * delante, se trataria aqui con una lista blanca de proxies.
     */
    function ipCliente(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

if (!function_exists('registrarEventoSeguridad')) {
    /**
     * Registra un evento de seguridad en el log del servidor (error_log).
     * Va al log del contenedor/Apache, no a la tabla `logs`, para no depender de
     * un usuario_id (los eventos de login pueden ser anonimos). NUNCA se registran
     * contrasenas ni tokens.
     *
     * @param string $evento  Etiqueta corta: login_ok, login_fallido, login_bloqueado...
     * @param string $detalle Contexto no sensible (email, id de usuario, codigo HTTP).
     */
    function registrarEventoSeguridad(string $evento, string $detalle = ''): void
    {
        error_log(sprintf('[SEGURIDAD] %s | ip=%s | %s', $evento, ipCliente(), $detalle));
    }
}
