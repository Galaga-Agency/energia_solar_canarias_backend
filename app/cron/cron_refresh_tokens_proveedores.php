<?php
/**
 * CRON: mantenimiento periodico.
 *
 *  1. Refresco preventivo de tokens OAuth de proveedores (Sungrow y Sigenergy).
 *     Usa ProveedorTokenService (misma logica que la app). Renueva de forma
 *     preventiva los tokens que caduquen dentro del margen (por defecto 12h), para
 *     que nunca lleguen a caducar. Con --force renueva siempre.
 *
 *     La app ademas refresca BAJO DEMANDA (ante un 401), asi que este cron es la
 *     red de seguridad que mantiene vivo el refresh_token de Sungrow.
 *
 *  2. Limpieza de api_cache (ver limpiarCache mas abajo).
 *
 *  3. Limpieza de login_attempts: borra los intentos de acceso caducados
 *     (anti-fuerza-bruta), para que la tabla no crezca aunque no haya fallos.
 *
 * Uso:  php app/cron/cron_refresh_tokens_proveedores.php [--force]
 */

require_once __DIR__ . '/../services/ProveedorTokenService.php';

$FORCE = in_array('--force', $argv ?? []);
$MARGEN_MIN = 12 * 60; // refrescar si caduca en <12h

function logmsg($m) { echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n"; }

$c = json_decode(file_get_contents(__DIR__ . '/../../config/conexion.json'), true)[0];
$db = new mysqli($c['server'], $c['user'], $c['password'], $c['database'], (int) $c['port']);
if ($db->connect_errno) { exit("Error BD: " . $db->connect_error . "\n"); }

$svc = new ProveedorTokenService($db, $MARGEN_MIN);

logmsg('== Refresco de tokens de proveedores ==' . ($FORCE ? ' (FORCE)' : ''));
foreach (['Sungrow', 'Sigenergy'] as $nombre) {
    try {
        if ($FORCE) {
            $svc->refresh($nombre);
            logmsg("$nombre: OK renovado (force).");
        } else {
            $svc->getValidToken($nombre); // refresca solo si esta dentro del margen
            logmsg("$nombre: OK (valido o renovado si tocaba).");
        }
    } catch (Throwable $e) {
        logmsg("$nombre: ERROR -> " . $e->getMessage());
    }
}

/**
 * Borra de api_cache las filas ya caducadas.
 *
 * Hace falta porque nada mas las borra: `recordar()` solo compara `creado_en`
 * contra el TTL para decidir si sirve la fila, pero si esta pasada la deja ahi.
 * Y la clave incluye la ruta COMPLETA, asi que cada dia/mes/año que alguien mire
 * en una grafica deja su propia fila. Sin esto, la tabla solo crece.
 *
 * Se da un margen (GRACIA_SEG) antes de borrar: una fila recien caducada todavia
 * vale como "ultimo dato bueno" si el proveedor falla (la politica de servir algo
 * obsoleto antes que una pantalla rota, ver CacheApiService).
 */
function limpiarCache($db)
{
    $GRACIA_SEG = 24 * 3600;
    $ahoraMs = (int) (microtime(true) * 1000);

    $st = $db->prepare("DELETE FROM api_cache WHERE creado_en + (ttl_seg + ?) * 1000 < ?");
    if (!$st) { logmsg('cache: no se pudo preparar el DELETE -> ' . $db->error); return; }
    $st->bind_param('ii', $GRACIA_SEG, $ahoraMs);
    $st->execute();
    $borradas = $st->affected_rows;
    $st->close();

    $r = $db->query("SELECT COUNT(*) c FROM api_cache");
    $quedan = $r ? (int) $r->fetch_assoc()['c'] : -1;
    logmsg("cache: $borradas filas caducadas borradas, quedan $quedan.");
}

try {
    limpiarCache($db);
} catch (Throwable $e) {
    // Que un fallo limpiando no tumbe el refresco de tokens, que es lo critico.
    logmsg('cache: ERROR limpiando -> ' . $e->getMessage());
}

/**
 * Borra de login_attempts los intentos ya fuera de la ventana de bloqueo.
 *
 * El anti-fuerza-bruta (IntentosLoginDB) ya auto-purga al registrar un fallo,
 * pero eso solo ocurre cuando ALGUIEN falla un login. Este cron garantiza la
 * limpieza aunque no haya fallos. Se borran los intentos de mas de 1 hora, muy
 * por encima de la ventana por defecto (15 min): pasado eso ya no cuentan.
 * Si subes LOGIN_BLOQUEO_MIN por encima de 60, sube tambien RETENCION_MIN aqui.
 */
function limpiarLoginAttempts($db)
{
    $RETENCION_MIN = 60;
    $st = $db->prepare("DELETE FROM login_attempts WHERE creado_en < (NOW() - INTERVAL ? MINUTE)");
    if (!$st) {
        // Si la tabla aun no existe (migracion sin aplicar) no es un error grave.
        logmsg('login_attempts: no se pudo preparar el DELETE (¿tabla sin migrar?) -> ' . $db->error);
        return;
    }
    $st->bind_param('i', $RETENCION_MIN);
    $st->execute();
    $borradas = $st->affected_rows;
    $st->close();
    logmsg("login_attempts: $borradas intentos caducados borrados.");
}

try {
    limpiarLoginAttempts($db);
} catch (Throwable $e) {
    logmsg('login_attempts: ERROR limpiando -> ' . $e->getMessage());
}

/**
 * Borra enlaces magicos y codigos de traspaso caducados o ya usados.
 *
 * Nada mas los borra: el canje solo marca `consumido_en`, no elimina la fila
 * (a proposito, para poder investigar un acceso reciente). Sin esta limpieza
 * las tablas solo crecen, y `magic_links` recibe una fila por cada intento de
 * login de cada usuario.
 */
try {
    require_once __DIR__ . '/../services/MagicLinkService.php';
    (new MagicLinkService($db))->limpiarCaducados();
    logmsg('magic_links / auth_handoffs: limpieza completada.');
} catch (Throwable $e) {
    // Si las tablas aun no existen (migracion sin aplicar) no es grave: el
    // cron no debe caerse por esto.
    logmsg('magic_links: ERROR limpiando -> ' . $e->getMessage());
}

$db->close();
logmsg('== Fin ==');
