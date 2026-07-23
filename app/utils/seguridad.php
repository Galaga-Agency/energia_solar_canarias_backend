<?php

/**
 * Utilidades de seguridad. ISO 27001:2022 A.8.5 (autenticacion) y A.8.15/8.16 (logging).
 */

if (!function_exists('ipCoincideRegla')) {
    /**
     * Comprueba si una IP coincide con una regla, que puede ser una IP exacta
     * ("172.18.0.1") o un rango CIDR ("172.16.0.0/12", "10.0.0.0/8"). Sirve para
     * IPv4 e IPv6. El CIDR resuelve que la puerta de enlace de Docker no sea fija:
     * en vez de clavar una IP, se confia en el rango privado entero.
     */
    function ipCoincideRegla(string $ip, string $regla): bool
    {
        if (strpos($regla, '/') === false) {
            return $ip === $regla;
        }
        [$subred, $bits] = explode('/', $regla, 2);
        $ipBin = @inet_pton($ip);
        $subBin = @inet_pton($subred);
        // Falla si la IP no es valida o mezcla familias (IPv4 vs IPv6).
        if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
            return false;
        }
        $bits = (int) $bits;
        $bytesEnteros = intdiv($bits, 8);
        $bitsSueltos = $bits % 8;
        if ($bytesEnteros > 0 && strncmp($ipBin, $subBin, $bytesEnteros) !== 0) {
            return false;
        }
        if ($bitsSueltos === 0) {
            return true;
        }
        $mascara = chr((0xff << (8 - $bitsSueltos)) & 0xff);
        return (ord($ipBin[$bytesEnteros]) & ord($mascara)) === (ord($subBin[$bytesEnteros]) & ord($mascara));
    }
}

if (!function_exists('ipCliente')) {
    /**
     * IP real del cliente.
     *
     * Por defecto se usa REMOTE_ADDR, que no se puede falsear. El problema: hay un
     * proxy inverso (el vhost del VPS) por delante y el backend corre en Docker, asi
     * que REMOTE_ADDR es la puerta de enlace de la red Docker (172.18.0.1) para
     * TODOS los clientes. Eso deja el campo `ip` de logs y del anti-fuerza-bruta sin
     * valor para distinguir origenes.
     *
     * X-Forwarded-For lo pone el proxy, pero el cliente tambien puede mandarlo, asi
     * que solo se cree cuando la peticion viene de un proxy declarado como de
     * confianza en PROXIES_CONFIANZA. Cada entrada (separadas por coma) puede ser una
     * IP exacta ("172.18.0.1") o un rango CIDR ("172.16.0.0/12"), util porque la
     * puerta de enlace de Docker no es fija. Sin esa variable, se mantiene el
     * comportamiento seguro de siempre.
     *
     * El proxy AÑADE la IP que ve al final de la cadena XFF, asi que la real es la
     * ultima entrada valida que no sea a su vez un proxy de confianza; lo que el
     * cliente pudiera falsear queda a la izquierda y se ignora.
     */
    function ipCliente(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $confianza = array_filter(array_map('trim', explode(',', $_ENV['PROXIES_CONFIANZA'] ?? '')));
        $esDeConfianza = fn(string $ip) => (bool) array_filter($confianza, fn($r) => ipCoincideRegla($ip, $r));

        if ($confianza && $esDeConfianza($remote) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $cadena = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            for ($i = count($cadena) - 1; $i >= 0; $i--) {
                if (filter_var($cadena[$i], FILTER_VALIDATE_IP) && !$esDeConfianza($cadena[$i])) {
                    return $cadena[$i];
                }
            }
        }
        return $remote;
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
