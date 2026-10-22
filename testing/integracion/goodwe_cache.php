<?php
/**
 * Cache + autorizacion con MySQL y HTTP reales, sin consultar GoodWe.
 * Ejecutar en el contenedor local con las cuatro GOODWE_* de prueba indicadas
 * en documentacion/goodwe.md. Arranca un servidor PHP temporal aislado del frontend.
 */
require_once __DIR__ . '/../../app/services/GoodWeService.php';
require_once __DIR__ . '/../../app/services/ZohoService.php';
date_default_timezone_set('Atlantic/Canary');
if (!ZohoService::desactivado()
    || getenv('GOODWE_URL') !== 'http://127.0.0.1:9/'
    || getenv('GOODWE_PLUS_URL') !== 'http://127.0.0.1:9/sems'
    || getenv('GOODWE_ACCOUNT') !== 'goodwe-cache-test@example.invalid'
    || getenv('GOODWE_PASSWORD') !== 'solo-pruebas') {
    throw new RuntimeException('Se requieren el stack local y las credenciales ficticias de la prueba');
}
new GoodWe();
$db = Conexion::getInstance()->getConexion();

if (($argv[1] ?? '') === 'worker') {
    $r = (new CacheApiService($db))->recordarProtegido('GoodWe', $argv[2], 60, 900, function () use ($argv) {
        file_put_contents($argv[3], "consulta\n", FILE_APPEND | LOCK_EX);
        usleep(300000);
        return ['code' => 0, 'data' => ['simulado' => true]];
    }, [GoodWeCachePolitica::class, 'esValida']);
    echo json_encode($r);
    exit;
}

$ok = 0;
function comprobarCache(bool $condicion, string $texto): void
{
    global $ok;
    if (!$condicion) throw new RuntimeException('FALLO: ' . $texto);
    ++$ok; echo 'OK: ' . $texto . PHP_EOL;
}

$creados = []; $claves = []; $procesos = []; $servidor = null;
$log = tempnam(sys_get_temp_dir(), 'esc-cache-http-');
$contador = tempnam(sys_get_temp_dir(), 'esc-cache-count-');
$marca = 'cache-' . bin2hex(random_bytes(8));
$plantaA = $marca . '-a'; $plantaB = $marca . '-b'; $otraMarca = $marca . '-solar';
$base = 'http://127.0.0.1:18088';

function sembrarCache(string $url, array $datos, array $respuesta, string $metodo = 'POST'): void
{
    global $db, $claves;
    $ruta = GoodWeCachePolitica::identidad((string) GoodWe::$account, $metodo, $url, $datos);
    $clave = 'GoodWe:' . hash('sha256', $ruta);
    $claves[] = $clave;
    $json = json_encode($respuesta, JSON_THROW_ON_ERROR);
    $ahora = (int) (microtime(true) * 1000);
    $ttl = GoodWeCachePolitica::para($url, $datos)['ttl'];
    $st = $db->prepare('INSERT INTO api_cache (clave, proveedor, ruta, respuesta, creado_en, ttl_seg) VALUES (?,\'GoodWe\',?,?,?,?)');
    $st->bind_param('sssii', $clave, $ruta, $json, $ahora, $ttl); $st->execute(); $st->close();
}

function solicitarCache(string $ruta, ?string $token, ?array $body = null): array
{
    global $base;
    $ch = curl_init($base . $ruta);
    $headers = ['Content-Type: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
    $raw = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$http, json_decode((string) $raw, true), (string) $raw];
}

try {
    // Procesos distintos comparten el mismo lock y solo ejecutan un fetch.
    $ruta = $marca . ':concurrencia';
    $claves[] = 'GoodWe:' . hash('sha256', $ruta);
    foreach ([1, 2] as $i) {
        $pipes = [];
        $p = proc_open([PHP_BINARY, __FILE__, 'worker', $ruta, $contador], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]); $procesos[] = [$p, $pipes];
    }
    foreach ($procesos as [$p, $pipes]) {
        $salida = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        comprobarCache(proc_close($p) === 0 && (json_decode($salida, true)['data']['simulado'] ?? false), 'worker recibe datos correctos');
    }
    $procesos = [];
    comprobarCache(count(file($contador)) === 1, 'dos procesos simultaneos ejecutan una sola consulta');
    $db->query("UPDATE api_cache SET creado_en = creado_en - 61000 WHERE clave = '" . $db->real_escape_string($claves[0]) . "'");
    $llamadas = 0;
    $r = (new CacheApiService($db))->recordarProtegido('GoodWe', $ruta, 60, 900, function () use (&$llamadas) {
        ++$llamadas; return ['code' => 0, 'data' => ['renovada' => true]];
    }, [GoodWeCachePolitica::class, 'esValida']);
    comprobarCache($llamadas === 1 && $r['data']['renovada'], 'al caducar el TTL se consulta y sustituye la cache');

    $goodwe = (int) $db->query("SELECT id FROM proveedores WHERE nombre='GoodWe' LIMIT 1")->fetch_assoc()['id'];
    $solar = (int) $db->query("SELECT id FROM proveedores WHERE nombre='SolarEdge' LIMIT 1")->fetch_assoc()['id'];
    foreach (['a', 'b'] as $letra) {
        $email = $marca . '-' . $letra . '@example.invalid';
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $st = $db->prepare("INSERT INTO usuarios (email,password_hash,clase_id,nombre,apellido,activo,eliminado) VALUES (?,?,2,'Cache','Pruebas',1,0)");
        $st->bind_param('ss', $email, $hash); $st->execute(); $creados[] = $db->insert_id; $st->close();
    }
    foreach ([[$creados[0], $plantaA, $goodwe], [$creados[1], $plantaB, $goodwe], [$creados[0], $otraMarca, $solar]] as [$uid, $planta, $proveedor]) {
        $st = $db->prepare('INSERT INTO plantas_asociadas (usuario_id,planta_id,proveedor_id) VALUES (?,?,?)');
        $st->bind_param('isi', $uid, $planta, $proveedor); $st->execute(); $st->close();
    }
    // Sesiones reales: los JWT sin sesion ya no se aceptan.
    require_once __DIR__ . '/../../app/services/SesionWebService.php';
    $tokens = array_map(fn($uid) => (new SesionWebService())->crear($uid)['publico']['tokenIdentificador'], [$creados[0], $creados[1]]);
    $fecha = date('Y-m-d');
    foreach ([$plantaA, $plantaB] as $planta) {
        sembrarCache(GoodWe::$url . 'api/v2/PowerStation/GetPowerflow', ['PowerStationId' => $planta], ['code' => 0, 'data' => ['id' => $planta, 'powerflow' => ['pv' => '1000W']]]);
        sembrarCache(GoodWe::$url . 'api/v3/PowerStation/GetInverterAllPoint', ['PowerStationId' => $planta], ['code' => 0, 'data' => ['id' => $planta, 'devices' => []]]);
        sembrarCache(GoodWe::$url . 'api/v3/PowerStation/GetPlantDetailByPowerstationId', ['powerStationId' => $planta], ['code' => 0, 'data' => ['id' => $planta, 'info' => ['status' => 1]]], 'GET');
        sembrarCache('http://127.0.0.1:9/sems/' . GoodWeSemsPlus::RUTA_CURVA, GoodWeSemsPlus::cuerpoCurva($planta, $fecha), [
            'code' => '00000', 'data' => ['dataList' => [['item' => 'pSystem', 'powerData' => [['tp' => $fecha . ' 12:00:00', 'power' => 1.2]]]]],
        ]);
    }
    $alarmasDatos = ['adcode' => '', 'township' => '', 'orgid' => '', 'stationid' => '', 'warninglevel' => 7, 'status' => '0',
        'starttime' => date('m/d/Y', strtotime('-1 month +1 day')) . ' 00:00:00', 'endtime' => date('m/d/Y') . ' 23:59:59',
        'page_size' => '37', 'page_index' => '97', 'device_type' => [], 'fault_classification' => [], 'standard_faultLevel' => []];
    sembrarCache(GoodWe::$url . 'api/SmartOperateMaintenance/GetPowerStationWariningInfoByMultiCondition', $alarmasDatos,
        ['code' => 0, 'data' => ['record' => 999, 'allStationCount' => 999, 'list' => array_map(fn($id) => ['stationId' => $id], [$plantaA, $plantaB, $otraMarca])]]);

    $pipes = [];
    $servidor = proc_open([PHP_BINARY, '-S', '127.0.0.1:18088', __DIR__ . '/../../app/routers/rutas.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname(__DIR__, 2));
    fclose($pipes[0]);
    for ($i = 0; $i < 30; ++$i) {
        $socket = @fsockopen('127.0.0.1', 18088, $errno, $errstr, 0.1);
        if ($socket) { fclose($socket); break; }
        usleep(50000);
    }
    foreach ([[$tokens[0], $plantaA, $plantaB], [$tokens[1], $plantaB, $plantaA]] as [$token, $propia, $ajena]) {
        $rutas = [
            ['/plant/power/realtime/%s?proveedor=goodwe', null],
            ['/plant/inventario/%s?proveedor=goodwe', null],
            ['/plants/details/%s?proveedor=goodwe', null],
            ['/plants/graficas?proveedor=goodwe', ['id' => $propia, 'date' => $fecha, 'chartIndexId' => 'potencia', 'full_script' => 'false']],
        ];
        foreach ($rutas as [$ruta, $body]) {
            [$http, $r, $raw] = solicitarCache(sprintf($ruta, $propia), $token, $body);
            comprobarCache($http === 200 && str_contains($raw, $propia) && str_contains($raw, '"cacheado":true') && !str_contains($raw, $ajena), 'cache propia correcta en ' . $ruta);
            if ($body) $body['id'] = $ajena;
            [$http, $r, $raw] = solicitarCache(sprintf($ruta, $ajena), $token, $body);
            comprobarCache(in_array($http, [403, 404], true) && !str_contains($raw, $ajena), 'cache caliente ajena denegada en ' . $ruta);
        }
        [$http, $r, $raw] = solicitarCache('/plant/alert?proveedor=goodwe&pageIndex=97&pageSize=37&status=0', $token);
        comprobarCache($http === 200 && ($r['data']['data'] ?? []) === ['list' => [['stationId' => $propia]], 'record' => 1], 'alarmas y recuentos limitados a plantas GoodWe propias');
    }
    $db->query('DELETE FROM plantas_asociadas WHERE usuario_id = ' . (int) $creados[0] . ' AND proveedor_id = ' . $goodwe);
    foreach (['/plant/power/realtime/%s?proveedor=goodwe', '/plant/inventario/%s?proveedor=goodwe', '/plants/details/%s?proveedor=goodwe'] as $ruta) {
        [$http, $r, $raw] = solicitarCache(sprintf($ruta, $plantaA), $tokens[0]);
        comprobarCache(in_array($http, [403, 404], true) && !str_contains($raw, $plantaA), 'retirar acceso bloquea inmediatamente cache existente');
    }
    [$http, $r] = solicitarCache('/plant/alert?proveedor=goodwe&pageIndex=97&pageSize=37&status=0', $tokens[0]);
    comprobarCache($http === 200 && $r['data']['data'] === ['list' => [], 'record' => 0], 'retirar acceso se refleja tambien en alarmas cacheadas');
    [$http] = solicitarCache('/plant/power/realtime/' . $plantaB . '?proveedor=goodwe', null);
    comprobarCache($http === 403, 'sin sesion no se sirve la cache caliente');
    comprobarCache((int) $db->query("SELECT COUNT(*) AS n FROM goodwe_sesiones WHERE cuenta_hash = '" . hash('sha256', 'http://127.0.0.1:9|goodwe-cache-test@example.invalid') . "'")->fetch_assoc()['n'] === 0, 'ninguna peticion necesito login ni consultas reales a GoodWe');
} finally {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    foreach ($procesos as [$p]) if (is_resource($p)) { proc_terminate($p); proc_close($p); }
    foreach ($claves as $clave) {
        $pausa = $clave . ':pausa';
        $st = $db->prepare('DELETE FROM api_cache WHERE clave IN (?,?)');
        $st->bind_param('ss', $clave, $pausa); $st->execute(); $st->close();
    }
    foreach ($creados as $id) {
        $db->query('DELETE FROM plantas_asociadas WHERE usuario_id = ' . (int) $id);
        $db->query('DELETE FROM usuarios WHERE usuario_id = ' . (int) $id);
    }
    unlink($contador); unlink($log);
}
echo $ok . ' comprobaciones correctas. Datos temporales eliminados.' . PHP_EOL;
