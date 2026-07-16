<?php
/**
 * Crea (o reactiva) los dos usuarios cliente de prueba que usan las pruebas de
 * integracion. Son de clase 2 (`usuario`, solo vista).
 *
 * Son DOS a proposito: con uno solo no se puede comprobar lo importante, que es que
 * un cliente no vea las plantas de otro.
 *
 * SOLO PARA LOCAL. No lo ejecutes contra produccion: crea usuarios con una
 * contraseña conocida y escrita en el repo.
 *
 * Uso:  docker compose exec -T app php /var/www/html/testing/integracion/crear_usuarios_prueba.php
 */

require_once __DIR__ . '/../../app/models/conexion.php';

$PASS = getenv('ESC_PASS_PRUEBAS') ?: 'PruebasESC2026!';

$usuarios = [
    ['cliente.pruebas@galagaagency.com',  'Cliente', 'Pruebas'],
    ['cliente.pruebas2@galagaagency.com', 'Cliente', 'Pruebas Dos'],
];

$db = Conexion::getInstance()->getConexion();

foreach ($usuarios as [$email, $nombre, $apellido]) {
    $hash = password_hash($PASS, PASSWORD_BCRYPT);

    $st = $db->prepare("SELECT usuario_id FROM usuarios WHERE email = ?");
    $st->bind_param('s', $email);
    $st->execute();
    $fila = $st->get_result()->fetch_assoc();
    $st->close();

    if ($fila) {
        $st = $db->prepare("UPDATE usuarios SET password_hash=?, clase_id=2, activo=1, eliminado=0 WHERE usuario_id=?");
        $st->bind_param('si', $hash, $fila['usuario_id']);
        $st->execute();
        $st->close();
        echo "actualizado  id={$fila['usuario_id']}  $email\n";
    } else {
        $st = $db->prepare("INSERT INTO usuarios (email, password_hash, clase_id, nombre, apellido, activo, eliminado) VALUES (?,?,2,?,?,1,0)");
        $st->bind_param('ssss', $email, $hash, $nombre, $apellido);
        $st->execute();
        echo "creado       id={$db->insert_id}  $email\n";
        $st->close();
    }
}

echo "\nOJO: si los ids no son 1086 y 1087, actualiza CLIENTE1/CLIENTE2 en comun.py.\n";
