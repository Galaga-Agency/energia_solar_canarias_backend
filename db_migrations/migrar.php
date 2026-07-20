<?php
/**
 * Migrador: aplica los .sql de db_migrations/ que aun no se hayan aplicado.
 *
 * Existe porque las migraciones se aplicaban a mano y olvidarse rompe cosas en
 * silencio: sin la tabla `api_cache`, por ejemplo, Sigenergy responde 400 en todas
 * las llamadas. Con esto el despliegue ya no depende de que alguien se acuerde.
 *
 * Como decide que aplicar: lleva la cuenta en la tabla `migraciones`. Se ejecuta
 * lo que este en db_migrations/*.sql y NO figure ya ahi, por orden alfabetico
 * (de ahi que los ficheros empiecen por fecha: 2026_07_16_...).
 *
 * Solo toca los .sql. Los .php de esta carpeta (p.ej. seed_sungrow_oauth.php) son
 * seeds que leen config/.env y se lanzan a mano: no son cambios de esquema.
 *
 * OJO: esto NO construye el esquema desde cero. El esquema base sale del volcado
 * (db_init/esc_dump.sql, que MySQL importa al primer arranque) y las migraciones son
 * los cambios que van ENCIMA. De hecho hay migraciones con claves foraneas a tablas
 * del volcado (proveedor_oauth -> proveedores), asi que contra una base vacia fallan.
 * El orden correcto es siempre: volcado primero, migraciones despues.
 *
 * Uso:
 *   php db_migrations/migrar.php            aplica lo que falte
 *   php db_migrations/migrar.php --estado   solo informa, no toca nada
 *
 * Devuelve 0 si todo fue bien y 1 si algo fallo, para que un despliegue se pare.
 */

$SOLO_ESTADO = in_array('--estado', $argv ?? [], true);
$DIR = __DIR__;
$RAIZ = dirname(__DIR__);

// El lock es del SERVIDOR MySQL, no del proceso: asi dos contenedores que arranquen
// a la vez no aplican la misma migracion por duplicado. Mismo truco que CacheApiService.
const LOCK = 'esc_migraciones';
const LOCK_ESPERA_SEG = 30;

function logmsg($m) { echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n"; }

function conectar($raiz)
{
    $ruta = $raiz . '/config/conexion.json';
    if (!is_file($ruta)) {
        throw new RuntimeException("No existe $ruta");
    }
    $c = json_decode(file_get_contents($ruta), true)[0];
    $db = new mysqli($c['server'], $c['user'], $c['password'], $c['database'], (int) $c['port']);
    if ($db->connect_errno) {
        throw new RuntimeException('Error de conexion: ' . $db->connect_error);
    }
    $db->set_charset('utf8mb4');
    return $db;
}

/** La tabla de control se crea sola: es el unico esquema que no puede migrarse. */
function crearTablaControl($db)
{
    $db->query("CREATE TABLE IF NOT EXISTS `migraciones` (
        `nombre`     VARCHAR(191) NOT NULL,
        `checksum`   CHAR(64)     NOT NULL COMMENT 'sha256 del fichero al aplicarlo',
        `aplicada_en` DATETIME    NOT NULL,
        PRIMARY KEY (`nombre`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function yaAplicadas($db)
{
    $r = $db->query("SELECT nombre, checksum FROM migraciones");
    $out = [];
    while ($f = $r->fetch_assoc()) {
        $out[$f['nombre']] = $f['checksum'];
    }
    return $out;
}

/**
 * Ejecuta un fichero .sql que puede tener varias sentencias.
 *
 * OJO: MySQL no hace rollback de los CREATE/ALTER, asi que si una migracion falla
 * a medias NO se deshace sola. Por eso las migraciones deben ser idempotentes
 * (CREATE TABLE IF NOT EXISTS, ALTER ... IF NOT EXISTS): volver a lanzarlas tras
 * arreglar el fallo no debe romper nada.
 */
function ejecutarSql($db, $sql)
{
    $db->multi_query($sql);
    do {
        if ($res = $db->store_result()) {
            $res->free();
        }
    } while ($db->more_results() && $db->next_result());

    if ($db->errno) {
        throw new RuntimeException($db->error);
    }
}

// -----------------------------------------------------------------------------

$db = conectar($RAIZ);
crearTablaControl($db);

$ficheros = glob($DIR . '/*.sql');
sort($ficheros, SORT_STRING);

$aplicadas = yaAplicadas($db);
$pendientes = [];
$modificadas = [];

foreach ($ficheros as $ruta) {
    $nombre = basename($ruta);
    $checksum = hash_file('sha256', $ruta);
    if (!isset($aplicadas[$nombre])) {
        $pendientes[] = [$nombre, $ruta, $checksum];
    } elseif ($aplicadas[$nombre] !== $checksum) {
        $modificadas[] = $nombre;
    }
}

// Editar una migracion ya aplicada es un clasico: en tu maquina "funciona" porque la
// aplicaste antes del cambio, pero en produccion se aplicara la version nueva y en
// otro entorno la vieja. Se avisa y no se falla, porque puede ser solo un comentario.
foreach ($modificadas as $n) {
    logmsg("AVISO: '$n' cambio DESPUES de aplicarse. No se vuelve a ejecutar. Si el cambio "
        . "es de esquema, haz una migracion nueva en vez de tocar esta.");
}

if ($SOLO_ESTADO) {
    logmsg('Aplicadas: ' . count($aplicadas) . ' | Pendientes: ' . count($pendientes));
    foreach ($pendientes as [$n]) {
        logmsg("  pendiente: $n");
    }
    $db->close();
    exit(0);
}

if (!$pendientes) {
    logmsg('Base de datos al dia (' . count($aplicadas) . ' migraciones aplicadas).');
    $db->close();
    exit(0);
}

// Turno: si arrancan varios contenedores a la vez, solo uno migra; los demas esperan
// y al entrar ya no encuentran nada pendiente.
$st = $db->prepare('SELECT GET_LOCK(?, ?)');
$lock = LOCK;
$espera = LOCK_ESPERA_SEG;
$st->bind_param('si', $lock, $espera);
$st->execute();
$tengoTurno = (int) ($st->get_result()->fetch_row()[0] ?? 0) === 1;
$st->close();

if (!$tengoTurno) {
    logmsg('ERROR: otro proceso lleva mas de ' . LOCK_ESPERA_SEG . 's migrando. Se aborta.');
    $db->close();
    exit(1);
}

$fallo = false;
try {
    // Se relee dentro del turno: otro proceso pudo aplicarlas mientras esperabamos.
    $aplicadas = yaAplicadas($db);

    foreach ($pendientes as [$nombre, $ruta, $checksum]) {
        if (isset($aplicadas[$nombre])) {
            logmsg("$nombre: ya la aplico otro proceso, se salta.");
            continue;
        }

        logmsg("Aplicando $nombre ...");
        try {
            ejecutarSql($db, file_get_contents($ruta));
        } catch (Throwable $e) {
            logmsg("ERROR en $nombre -> " . $e->getMessage());
            logmsg('Se para aqui: las siguientes migraciones no se aplican, porque '
                . 'suelen depender de las anteriores.');
            $fallo = true;
            break;
        }

        $st = $db->prepare('INSERT INTO migraciones (nombre, checksum, aplicada_en) VALUES (?,?,NOW())');
        $st->bind_param('ss', $nombre, $checksum);
        $st->execute();
        $st->close();
        logmsg("$nombre: OK");
    }
} finally {
    $st = $db->prepare('SELECT RELEASE_LOCK(?)');
    $st->bind_param('s', $lock);
    $st->execute();
    $st->get_result();
    $st->close();
}

$db->close();
logmsg($fallo ? '== Migracion INCOMPLETA ==' : '== Base de datos al dia ==');
exit($fallo ? 1 : 0);
