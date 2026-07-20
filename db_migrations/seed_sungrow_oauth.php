<?php
/**
 * Crea la tabla proveedor_oauth (si no existe) y vuelca la config OAuth de Sungrow
 * leyendo los valores desde config/.env. Usa la conexion del proyecto (config/conexion.json).
 *
 * Uso (dentro del contenedor app):  docker compose exec app php db_migrations/seed_sungrow_oauth.php
 */

$root = dirname(__DIR__);

// --- Cargar .env manualmente (sin depender de Dotenv) ---
$env = [];
foreach (file($root . '/config/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#' || strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}

$appkey  = $env['SUNGROW_API_KEY']    ?? null;
$secret  = $env['SUNGROW_SECRET_KEY'] ?? null;
$rsa     = $env['RSA_PUBLIC_KEY']      ?? null;
$authUrl = $env['AUTHORIZATION_URL']   ?? null;
$redir   = $env['REDIRECT_URL']        ?? null;

if (!$appkey || !$secret) { fwrite(STDERR, "Faltan SUNGROW_API_KEY / SUNGROW_SECRET_KEY en .env\n"); exit(1); }

// application_id y cloud_id salen de la authorization_url si estan presentes
$appId = null; $cloudId = null;
if ($authUrl) {
    if (preg_match('/applicationId=([^&]+)/', $authUrl, $m)) $appId = $m[1];
    if (preg_match('/cloudId=([^&]+)/', $authUrl, $m))       $cloudId = $m[1];
}

// --- Conexion (misma config que el backend) ---
$c = json_decode(file_get_contents($root . '/config/conexion.json'), true)[0];
$m = new mysqli($c['server'], $c['user'], $c['password'], $c['database'], (int)$c['port']);
if ($m->connect_errno) { fwrite(STDERR, "Error BD: " . $m->connect_error . "\n"); exit(1); }

// --- Crear tabla ---
$m->query(file_get_contents(__DIR__ . '/2026_07_14_create_proveedor_oauth.sql'));

// --- Localizar (o crear) el proveedor Sungrow ---
$res = $m->query("SELECT id FROM proveedores WHERE nombre = 'Sungrow' LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $proveedorId = (int)$row['id'];
} else {
    $stmt = $m->prepare("INSERT INTO proveedores (nombre, url, account, pwd) VALUES ('Sungrow', 'https://gateway.isolarcloud.eu', '', '')");
    $stmt->execute();
    $proveedorId = $m->insert_id;
    $stmt->close();
}

// --- Upsert de la config OAuth ---
$sql = "INSERT INTO proveedor_oauth (proveedor_id, appkey, secret_key, rsa_public_key, authorization_url, redirect_uri, application_id, cloud_id)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            appkey=VALUES(appkey), secret_key=VALUES(secret_key), rsa_public_key=VALUES(rsa_public_key),
            authorization_url=VALUES(authorization_url), redirect_uri=VALUES(redirect_uri),
            application_id=VALUES(application_id), cloud_id=VALUES(cloud_id)";
$stmt = $m->prepare($sql);
$stmt->bind_param('isssssss', $proveedorId, $appkey, $secret, $rsa, $authUrl, $redir, $appId, $cloudId);
$stmt->execute();
$stmt->close();

// --- Limpiar las columnas reutilizadas en proveedores (ahora viven en proveedor_oauth) ---
$m->query("UPDATE proveedores SET account='', pwd='' WHERE id=$proveedorId");

echo "OK. Sungrow (proveedor_id=$proveedorId) -> proveedor_oauth actualizado.\n";
echo "  appkey: " . substr($appkey,0,6) . "...\n";
echo "  application_id: " . ($appId ?? '-') . " | cloud_id: " . ($cloudId ?? '-') . "\n";

// --- Verificacion ---
$v = $m->query("SELECT p.id, p.nombre, p.url, o.appkey, LEFT(o.secret_key,6) sec, o.application_id, o.cloud_id,
                       CHAR_LENGTH(o.rsa_public_key) rsa_len, o.redirect_uri
                FROM proveedores p JOIN proveedor_oauth o ON o.proveedor_id=p.id WHERE p.id=$proveedorId");
print_r($v->fetch_assoc());
$m->close();
