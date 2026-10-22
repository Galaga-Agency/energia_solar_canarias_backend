<?php
/**
 * Crea (o reactiva) un ADMIN de pruebas en la BD LOCAL, para entrar en la app y
 * ver todas las plantas de todos los proveedores.
 *
 * No tiene contraseña conocida: se le pone una aleatoria que no se imprime. Se
 * entra por enlace magico, que en local llega a Mailpit (ver README, "Entrar en
 * local"):
 *
 *   docker compose exec -T app php /var/www/html/docker/crear_admin_local.php
 *   docker/login-local.sh admin.pruebas@galagaagency.com
 *
 * SOLO PARA LOCAL. Contra produccion crearia un admin de verdad.
 */

require_once __DIR__ . '/../app/models/conexion.php';

$email = $argv[1] ?? 'admin.pruebas@galagaagency.com';
$hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

$db = Conexion::getInstance()->getConexion();

$st = $db->prepare("SELECT usuario_id FROM usuarios WHERE email = ?");
$st->bind_param('s', $email);
$st->execute();
$fila = $st->get_result()->fetch_assoc();
$st->close();

if ($fila) {
    $st = $db->prepare("UPDATE usuarios SET password_hash=?, clase_id=1, activo=1, eliminado=0 WHERE usuario_id=?");
    $st->bind_param('si', $hash, $fila['usuario_id']);
    $st->execute();
    $st->close();
    echo "actualizado  id={$fila['usuario_id']}  $email (admin)\n";
} else {
    $st = $db->prepare("INSERT INTO usuarios (email, password_hash, clase_id, nombre, apellido, activo, eliminado) VALUES (?,?,1,'Admin','Pruebas',1,0)");
    $st->bind_param('ss', $email, $hash);
    $st->execute();
    echo "creado       id={$db->insert_id}  $email (admin)\n";
    $st->close();
}
