<?php
/**
 * Integracion con MySQL real y GoodWe SIMULADO: nunca llama al portal.
 * docker compose exec -T app php testing/integracion/goodwe_sesion.php
 * Prueba dos procesos, persistencia, sustitucion, rollback y primera asociacion.
 */
require_once __DIR__ . '/../../app/services/GoodWeSesionService.php';
require_once __DIR__ . '/../../app/services/ZohoService.php';
// Mismo comportamiento que el router web: los errores SQL no lanzan solos.
mysqli_report(MYSQLI_REPORT_OFF);

$config = json_decode(file_get_contents(__DIR__ . '/../../config/conexion.json'), true)[0];
if (!ZohoService::desactivado() || $config['server'] !== 'mysql' || $config['database'] !== 'esc') {
    throw new RuntimeException('Esta prueba solo se ejecuta en el stack local con Zoho desactivado');
}

class GoodWeHttpIntegracion extends HttpClient
{
    public function __construct(private string $contador) {}
    public function post($url, $headers = [], $data = '')
    {
        if ($url !== 'https://goodwe.invalid/api/v1/Common/CrossLogin') {
            throw new RuntimeException('La prueba no permite peticiones reales');
        }
        $f = fopen($this->contador, 'c+');
        flock($f, LOCK_EX);
        $numero = (int) stream_get_contents($f) + 1;
        ftruncate($f, 0);
        rewind($f);
        fwrite($f, (string) $numero);
        fflush($f);
        flock($f, LOCK_UN);
        fclose($f);
        usleep(300000);
        return json_encode(['hasError' => false, 'data' => [
            'uid' => 'uid-simulado', 'timestamp' => 1792400000000 + $numero,
            'token' => 'token-simulado-' . $numero,
            'client' => 'web', 'version' => 'v-prueba', 'language' => 'es',
        ]]);
    }
}

function servicioPrueba(string $cuenta, string $contador): GoodWeSesionService
{
    return new GoodWeSesionService(new GoodWeSesionDB(), new GoodWeHttpIntegracion($contador),
        'https://goodwe.invalid', $cuenta, 'password-simulado');
}

if (($argv[1] ?? '') === 'worker') {
    $sesion = servicioPrueba($argv[2], $argv[3])->obtener();
    echo json_encode(['token' => $sesion['token'], 'uid' => $sesion['uid']]);
    exit;
}

$conn = Conexion::getInstance()->getConexion();
$proveedor = $conn->query("SELECT id, token_id FROM proveedores WHERE nombre = 'GoodWe' LIMIT 1")->fetch_assoc();
if (!$proveedor || !$proveedor['token_id']) {
    throw new RuntimeException('Se necesita el proveedor GoodWe y su token local para restaurar la prueba');
}
$originalId = (int) $proveedor['token_id'];
$original = $conn->query('SELECT * FROM bearertoken WHERE id = ' . $originalId)->fetch_assoc();
$cuenta = 'auditoria-' . bin2hex(random_bytes(12)) . '@example.invalid';
$hash = hash('sha256', 'https://goodwe.invalid|' . $cuenta);
$contador = tempnam(sys_get_temp_dir(), 'esc-goodwe-');
$nuevoId = null;
$restriccion = 'esc_goodwe_audit_' . bin2hex(random_bytes(6));
$procesos = [];
$ok = 0;
function comprobar(bool $condicion, string $descripcion): void
{
    global $ok;
    if (!$condicion) { throw new RuntimeException('FALLO: ' . $descripcion); }
    ++$ok;
    echo 'OK: ' . $descripcion . PHP_EOL;
}
try {
    for ($i = 0; $i < 2; ++$i) {
        $pipes = [];
        $p = proc_open([PHP_BINARY, __FILE__, 'worker', $cuenta, $contador],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) { throw new RuntimeException('No se pudo iniciar el proceso de prueba'); }
        fclose($pipes[0]);
        $procesos[] = [$p, $pipes];
    }
    $resultados = [];
    foreach ($procesos as [$p, $pipes]) {
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        comprobar(proc_close($p) === 0, 'el proceso termina correctamente');
        $resultados[] = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
    }
    $procesos = [];
    comprobar(trim(file_get_contents($contador)) === '1', 'dos procesos solo hacen un login');
    comprobar($resultados[0] === $resultados[1], 'ambos procesos reciben la misma sesion');

    $almacen = new GoodWeSesionDB();
    $sesion = $almacen->obtener($hash);
    comprobar($sesion['uid'] === 'uid-simulado' && $sesion['client'] === 'web', 'se persiste la cabecera completa');
    comprobar(servicioPrueba($cuenta, $contador)->obtener() === $sesion, 'otra instancia reutiliza lo persistido');
    comprobar(trim(file_get_contents($contador)) === '1', 'reutilizar no genera otro login');

    // Recien guardado y ya rechazado: otra instancia acaba de entrar con la cuenta.
    $conflicto = false;
    try { servicioPrueba($cuenta, $contador)->renovar($sesion['token']); }
    catch (GoodWeSesionEnConflicto $e) { $conflicto = true; }
    comprobar($conflicto, 'un token recien guardado y rechazado no dispara otro login');
    comprobar(trim(file_get_contents($contador)) === '1', 'el enfriamiento no hace ningun login');
    $envejecer = function () use ($conn, $hash) {
        $st = $conn->prepare('UPDATE goodwe_sesiones SET actualizado_en = NOW() - INTERVAL ' . (GoodWeSesionService::ENFRIAMIENTO_SEG + 5) . ' SECOND WHERE cuenta_hash = ?');
        $st->bind_param('s', $hash); $st->execute(); $st->close();
    };
    $envejecer();
    $nueva = servicioPrueba($cuenta, $contador)->renovar($sesion['token']);
    comprobar($nueva['token'] !== $sesion['token'], 'renovar sustituye el token rechazado');
    comprobar($almacen->obtener($hash) == $nueva, 'la sustitucion queda persistida');
    $espejo = $conn->query('SELECT tokenAuth FROM bearertoken WHERE id = ' . $originalId)->fetch_assoc();
    comprobar($espejo['tokenAuth'] === $nueva['token'], 'bearertoken conserva la compatibilidad');

    $invalida = $nueva;
    $invalida['token'] = 'no-debe-guardarse';
    $invalida['datoInvalido'] = fopen('php://memory', 'r');
    $fallo = false;
    try { $almacen->guardar($hash, $invalida); }
    catch (RuntimeException $e) { $fallo = true; }
    finally { fclose($invalida['datoInvalido']); }
    comprobar($fallo, 'se detecta el fallo de persistencia provocado');
    comprobar($almacen->obtener($hash) == $nueva, 'el rollback conserva la sesion anterior');
    $espejo = $conn->query('SELECT tokenAuth FROM bearertoken WHERE id = ' . $originalId)->fetch_assoc();
    comprobar($espejo['tokenAuth'] === $nueva['token'], 'el rollback tampoco deja un token parcial en bearertoken');

    $sqlRestriccion = "ALTER TABLE goodwe_sesiones ADD CONSTRAINT $restriccion
        CHECK (cuenta_hash <> '$hash' OR JSON_UNQUOTE(JSON_EXTRACT(cabecera, '$.token')) <> 'rechazado-por-db')";
    comprobar($conn->query($sqlRestriccion) === true, 'se prepara un rechazo SQL real para esta cuenta simulada');
    $rechazada = $nueva;
    $rechazada['token'] = 'rechazado-por-db';
    $fallo = false;
    try { $almacen->guardar($hash, $rechazada); }
    catch (RuntimeException $e) { $fallo = true; }
    comprobar($fallo, 'el error SQL se detecta aunque mysqli_report este apagado');
    comprobar($almacen->obtener($hash) == $nueva, 'un rechazo SQL conserva la sesion anterior');
    $espejo = $conn->query('SELECT tokenAuth FROM bearertoken WHERE id = ' . $originalId)->fetch_assoc();
    comprobar($espejo['tokenAuth'] === $nueva['token'], 'un rechazo SQL revierte tambien bearertoken');

    $conn->query('UPDATE proveedores SET token_id = NULL WHERE id = ' . (int) $proveedor['id']);
    $envejecer();
    $nueva = servicioPrueba($cuenta, $contador)->renovar($nueva['token']);
    $fila = $conn->query('SELECT token_id FROM proveedores WHERE id = ' . (int) $proveedor['id'])->fetch_assoc();
    $nuevoId = (int) $fila['token_id'];
    comprobar($nuevoId > 0 && $nuevoId !== $originalId, 'sin token asociado se crea y asocia uno');
    $espejo = $conn->query('SELECT tokenAuth FROM bearertoken WHERE id = ' . $nuevoId)->fetch_assoc();
    comprobar($espejo['tokenAuth'] === $nueva['token'], 'la primera asociacion guarda el token correcto');
    $nombreLock = substr('esc_goodwe_' . $hash, 0, 64);
    $st = $conn->prepare('SELECT IS_FREE_LOCK(?) AS libre');
    $st->bind_param('s', $nombreLock); $st->execute();
    comprobar((int) $st->get_result()->fetch_assoc()['libre'] === 1, 'el bloqueo queda liberado');
    $st->close();
} finally {
    $conn->query('ALTER TABLE goodwe_sesiones DROP CHECK ' . $restriccion);
    foreach ($procesos as [$p, $pipes]) {
        if (is_resource($p)) { proc_terminate($p); proc_close($p); }
    }
    // Se conservan los valores anteriores sin imprimir ni copiar credenciales.
    $st = $conn->prepare('UPDATE proveedores SET token_id = ? WHERE id = ?');
    $st->bind_param('ii', $originalId, $proveedor['id']); $st->execute(); $st->close();
    $st = $conn->prepare('UPDATE bearertoken SET tokenAuth=?, tokenRenovation=?, expires_at=?, created_at=?, updated_at=? WHERE id=?');
    $st->bind_param('sssssi', $original['tokenAuth'], $original['tokenRenovation'], $original['expires_at'], $original['created_at'], $original['updated_at'], $originalId);
    $st->execute(); $st->close();
    if ($nuevoId) { $conn->query('DELETE FROM bearertoken WHERE id = ' . $nuevoId); }
    $st = $conn->prepare('DELETE FROM goodwe_sesiones WHERE cuenta_hash = ?');
    $st->bind_param('s', $hash); $st->execute(); $st->close();
    unlink($contador);
}
echo $ok . ' comprobaciones correctas. Estado local restaurado.' . PHP_EOL;
