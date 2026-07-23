<?php

/**
 * Cabeceras de seguridad HTTP. ISO 27001:2022 A.8.28 (codificacion segura).
 *
 * Se incluye al inicio de los dos puntos de entrada:
 *   - index.php                (las paginas de documentacion, HTML)
 *   - app/routers/rutas.php    (la API, JSON)
 *
 * No afecta al frontend Next: es otra app que solo consume la API por fetch;
 * lo cruzado lo gobierna CORS, no estas cabeceras. La CSP solo rige lo que
 * sirve ESTE backend (sus paginas de doc), y por eso permite los CDN que esas
 * paginas usan (Tailwind, Font Awesome) para no dejarlas sin estilos.
 */

if (!headers_sent()) {
    // El navegador no "adivina" tipos MIME (frena XSS por content-sniffing).
    header('X-Content-Type-Options: nosniff');
    // Nadie puede embeber el sitio en un iframe externo (anti-clickjacking).
    header('X-Frame-Options: SAMEORIGIN');
    // No filtrar la URL completa como referer hacia otros dominios.
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Desactiva APIs del navegador que la app no usa.
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // Fuerza HTTPS durante 1 anio (el sitio ya sirve por HTTPS).
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

    // CSP: lo propio ('self') + los CDN de las paginas de doc + inline que esas
    // paginas necesitan (estilos y el JS de copiar al portapapeles).
    $csp = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://cdnjs.cloudflare.com data:; "
        . "img-src 'self' data: https:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "form-action 'self'";
    header('Content-Security-Policy: ' . $csp);
}
