<?php
/**
 * CRON: avisos de alertas por correo.
 *
 * Recorre los usuarios que han pedido recibir avisos, mira las alertas de sus
 * instalaciones y manda un correo con las que aun no se hayan avisado.
 *
 * Decisiones que importan:
 *
 *  - UN correo por usuario y pasada, no uno por alerta. Una tormenta que tumba
 *    doce plantas no puede convertirse en doce correos: el usuario silencia el
 *    remitente y deja de ver los avisos que si importan.
 *
 *  - Cada alerta se avisa UNA vez (tabla avisos_enviados). Las alertas siguen
 *    activas hasta que alguien arregla la planta, asi que sin esto una averia
 *    de tres dias serian avisos cada diez minutos durante tres dias.
 *
 *  - La frecuencia del usuario decide cuando toca: 'immediate' en cada pasada,
 *    'daily' y 'weekly' solo si no se le ha escrito dentro de la ventana.
 *
 * Uso:  php app/cron/cron_avisos_alertas.php [--dry-run]
 *
 * Cron sugerido (cada 10 minutos):
 *   *_/10 * * * * php /ruta/app/cron/cron_avisos_alertas.php >> /var/log/esc-avisos.log 2>&1
 */

require_once __DIR__ . '/../services/correo.php';
require_once __DIR__ . '/../DBObjects/preferenciasNotificacionesDB.php';
require_once __DIR__ . '/../proveedores/RegistroProveedores.php';
require_once __DIR__ . '/../services/GoodWeService.php';

$DRY = in_array('--dry-run', $argv ?? []);

// --solo-usuario=N limita el envio a un usuario. Para probar contra datos
// reales sin escribir a los demas usuarios del volcado de produccion.
$SOLO = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--solo-usuario=')) {
        $SOLO = (int) substr($arg, strlen('--solo-usuario='));
    }
}

function logmsg($m) { echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n"; }

/**
 * Severidad comun a partir de lo que devuelva cada proveedor.
 *
 * Cada uno la nombra a su manera y algunos usan escalas invertidas, asi que se
 * mira lo que hay y, ante la duda, se trata como aviso y no como averia: es
 * peor mandar un correo de alarma por algo informativo que al reves.
 */
function severidadNormalizada(array $item): string
{
    // GoodWe usa warninglevel numerico; los demas cadenas. Se mira el que
    // exista, en ese orden.
    $bruto = strtolower((string) (
        $item['severity'] ?? $item['level'] ?? $item['warninglevel'] ?? $item['status'] ?? ''
    ));

    if (in_array($bruto, ['critical', 'fault', 'error', '1', 'high'], true)) {
        return 'critical';
    }
    // OJO: GoodWe usa warninglevel 0 y 1 para averias reales (0 = aviso,
    // 1 = fallo). Tratar el 0 como "informativo" silenciaria alarmas de verdad.
    if (in_array($bruto, ['info', 'information', 'low'], true)) {
        return 'info';
    }
    if ($bruto === '0') {
        return 'warning';
    }
    return 'warning';
}

$c = json_decode(file_get_contents(__DIR__ . '/../../config/conexion.json'), true)[0];
$db = new mysqli($c['server'], $c['user'], $c['password'], $c['database'], (int) $c['port']);
if ($db->connect_errno) { exit("Error BD: " . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

/** Cuanto tiene que pasar entre correos, por frecuencia. */
$VENTANAS = [
    'immediate' => 0,
    'daily'     => 24 * 3600,
    'weekly'    => 7 * 24 * 3600,
];

/** Que severidades entran, segun el minimo elegido. */
$INCLUYE = [
    'critical' => ['critical'],
    'warning'  => ['critical', 'warning'],
    'info'     => ['critical', 'warning', 'info'],
];

// Usuarios con avisos activos. LEFT JOIN: sin fila de preferencias valen los
// valores por defecto, que son "activo y solo averias".
$sql = "SELECT u.usuario_id, u.email, u.nombre, c.nombre AS clase,
               COALESCE(p.activas, 1)                  AS activas,
               COALESCE(p.email, 1)                    AS canal_email,
               COALESCE(p.severidad_minima, 'critical') AS severidad,
               COALESCE(p.frecuencia, 'immediate')      AS frecuencia
          FROM usuarios u
     LEFT JOIN preferencias_notificaciones p ON p.usuario_id = u.usuario_id
     INNER JOIN clases c ON c.clase_id = u.clase_id
         WHERE u.eliminado = 0 AND u.activo = 1
           AND COALESCE(p.activas, 1) = 1
           AND COALESCE(p.email, 1) = 1";

$usuarios = [];
if ($res = $db->query($sql)) {
    while ($fila = $res->fetch_assoc()) { $usuarios[] = $fila; }
}
logmsg(count($usuarios) . ' usuario(s) con avisos activos');

$correo = new Correo();
$registro = RegistroProveedores::porDefecto();
$totalEnviados = 0;

foreach ($usuarios as $usuario) {
    $uid = (int) $usuario['usuario_id'];

    if ($SOLO !== null && $uid !== $SOLO) { continue; }

    // Ventana de frecuencia: si es 'daily' o 'weekly' y ya se le escribio
    // dentro de la ventana, se salta — las alertas nuevas iran en el proximo.
    $ventana = $VENTANAS[$usuario['frecuencia']] ?? 0;
    if ($ventana > 0) {
        $stmt = $db->prepare(
            "SELECT MAX(enviado_en) AS ultimo FROM avisos_enviados WHERE usuario_id = ?"
        );
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $ultimo = $stmt->get_result()->fetch_assoc()['ultimo'] ?? null;
        $stmt->close();

        if ($ultimo && (time() - strtotime($ultimo)) < $ventana) {
            continue;
        }
    }

    // Alertas de sus plantas, pedidas a cada proveedor.
    //
    // No hay tabla de alertas: el backend las consulta en vivo y no las guarda,
    // asi que el cron hace exactamente lo mismo que la app. Una peticion por
    // planta, y las que fallen se saltan — un proveedor caido no puede impedir
    // que se avise de los otros cuatro.
    $severidades = $INCLUYE[$usuario['severidad']] ?? $INCLUYE['critical'];

    // El proveedor se guarda por id, no por nombre: hay que unir con la tabla
    // de proveedores para saber a que adaptador llamar.
    //
    // Un admin ve TODAS las plantas en la aplicacion, asi que tambien tiene que
    // recibir avisos de todas: si no, el unico que puede actuar sobre una
    // averia es justo el que no se entera.
    $esAdmin = strtolower((string) $usuario['clase']) === 'admin';

    if ($esAdmin) {
        $stmt = $db->prepare(
            "SELECT DISTINCT pa.planta_id, pr.nombre AS proveedor
               FROM plantas_asociadas pa
         INNER JOIN proveedores pr ON pr.id = pa.proveedor_id"
        );
        $stmt->execute();
    } else {
        $stmt = $db->prepare(
            "SELECT pa.planta_id, pr.nombre AS proveedor
               FROM plantas_asociadas pa
         INNER JOIN proveedores pr ON pr.id = pa.proveedor_id
              WHERE pa.usuario_id = ?"
        );
        $stmt->bind_param('i', $uid);
        $stmt->execute();
    }

    $res = $stmt->get_result();
    $plantas = [];
    while ($fila = $res->fetch_assoc()) { $plantas[] = $fila; }
    $stmt->close();

    $alertas = [];
    logmsg("usuario $uid: " . count($plantas) . ' planta(s)');

    // GoodWe aparte: su endpoint de alarmas NO acepta una planta, devuelve las
    // de todo el parque. Por eso el adaptador declara 'alertas' no soportada —
    // fingir lo contrario devolveria a un cliente las alertas de plantas que no
    // son suyas. Aqui se pide una sola vez y se filtra por las plantas del
    // usuario, que es la parte que el contrato por planta no puede hacer.
    $plantasGoodWe = [];
    foreach ($plantas as $planta) {
        if (strtolower($planta['proveedor']) === 'goodwe') {
            $plantasGoodWe[(string) $planta['planta_id']] = true;
        }
    }

    if ($esAdmin || !empty($plantasGoodWe)) {
        try {
            $goodwe = new GoodWeService();
            // status=0 son las ACTIVAS. Con 3 vuelve el historico entero (3.859
            // registros en produccion), casi todos ya resueltos: avisar de eso
            // seria mandar un correo con anos de averias arregladas.
            $parque = $goodwe->GetPowerStationWariningInfoByMultiCondition(1, 200, 0);
            $lista = $parque['data']['list'] ?? $parque['data'] ?? [];

            foreach ((is_array($lista) ? $lista : []) as $item) {
                if (!is_array($item)) { continue; }

                // stationId con I mayuscula: asi lo devuelve GoodWe. Con
                // 'stationid' no casaba ninguna planta y el cron no veia nada.
                // stationId con I mayuscula: asi lo devuelve GoodWe.
                $plantaId = (string) (
                    $item['stationId'] ?? $item['stationid'] ?? $item['powerstation_id'] ?? ''
                );
                if ($plantaId === '') { continue; }

                // El admin ve el parque entero en la aplicacion, incluidas las
                // plantas que nadie tiene asignadas — y es quien puede actuar.
                // Al usuario normal solo le llegan las suyas.
                if (!$esAdmin && !isset($plantasGoodWe[$plantaId])) { continue; }

                $severidad = severidadNormalizada($item);
                if (!in_array($severidad, $severidades, true)) { continue; }

                $alertaId = (string) ($item['warningid'] ?? $item['id'] ?? '');
                if ($alertaId === '') { continue; }

                $chk = $db->prepare(
                    "SELECT 1 FROM avisos_enviados
                      WHERE usuario_id = ? AND proveedor = 'goodwe' AND alerta_id = ?
                      LIMIT 1"
                );
                $chk->bind_param('is', $uid, $alertaId);
                $chk->execute();
                $ya = (bool) $chk->get_result()->fetch_row();
                $chk->close();
                if ($ya) { continue; }

                $alertas[] = [
                    'alerta_id'     => $alertaId,
                    'proveedor'     => 'goodwe',
                    'severidad'     => $severidad,
                    'mensaje'       => (string) (
                        $item['warningname'] ?? $item['warningstr'] ?? $item['message'] ?? ''
                    ),
                    'planta_nombre' => (string) ($item['stationname'] ?? $plantaId),
                ];
            }

            logmsg('  goodwe (parque): ' . count($alertas) . ' alerta(s) nueva(s)');
        } catch (Throwable $e) {
            logmsg('  goodwe (parque): ' . $e->getMessage());
        }
    }

    foreach ($plantas as $planta) {
        $clave = strtolower($planta['proveedor']);

        // Ya resuelto arriba, en una sola llamada al parque.
        if ($clave === 'goodwe') { continue; }

        try {
            $proveedor = $registro->obtener($clave);
        } catch (Throwable $e) {
            logmsg("  proveedor desconocido: $clave");
            continue;
        }

        try {
            $respuesta = $proveedor->alertas((string) $planta['planta_id'], 1, 50);
        } catch (Throwable $e) {
            // SolarEdge no tiene endpoint de alertas y responde 404; los demas
            // pueden estar caidos. En ninguno de los dos casos se para el cron.
            logmsg("  " . $clave . '/' . $planta['planta_id'] . ': ' . $e->getMessage());
            continue;
        }

        $lista = $respuesta['data'] ?? $respuesta['alertas'] ?? $respuesta;
        if (!is_array($lista)) { continue; }

        foreach ($lista as $item) {
            if (!is_array($item)) { continue; }

            $severidad = severidadNormalizada($item);
            if (!in_array($severidad, $severidades, true)) { continue; }

            $alertaId = (string) (
                $item['id'] ?? $item['warningid'] ?? $item['alarm_id'] ?? ''
            );
            if ($alertaId === '') { continue; }

            // Ya avisada: se comprueba aqui y no en SQL porque las alertas
            // vienen del proveedor, no de una tabla contra la que unir.
            $chk = $db->prepare(
                "SELECT 1 FROM avisos_enviados
                  WHERE usuario_id = ? AND proveedor = ? AND alerta_id = ?
                  LIMIT 1"
            );
            $chk->bind_param('iss', $uid, $clave, $alertaId);
            $chk->execute();
            $yaAvisada = (bool) $chk->get_result()->fetch_row();
            $chk->close();
            if ($yaAvisada) { continue; }

            $alertas[] = [
                'alerta_id'     => $alertaId,
                'proveedor'     => $clave,
                'severidad'     => $severidad,
                'mensaje'       => (string) (
                    $item['warningstr'] ?? $item['message'] ?? $item['fault_name'] ?? ''
                ),
                'planta_nombre' => (string) (
                    $item['stationname'] ?? $item['plantName'] ?? $planta['planta_id']
                ),
            ];

            // Tope por correo: veinte incidencias ya dicen "hay un problema
            // gordo"; doscientas solo hacen el correo ilegible.
            if (count($alertas) >= 20) { break 2; }
        }
    }

    if (empty($alertas)) { continue; }

    $paraCorreo = array_map(fn($a) => [
        'planta'    => $a['planta_nombre'] ?? '',
        'mensaje'   => $a['mensaje'] ?? '',
        'severidad' => $a['severidad'] ?? 'warning',
    ], $alertas);

    if ($DRY) {
        logmsg("DRY-RUN usuario $uid: " . count($alertas) . ' alerta(s)');
        continue;
    }

    $enviado = $correo->avisoAlertas(
        ['email' => $usuario['email'], 'nombre' => $usuario['nombre']],
        $paraCorreo
    );

    if (!$enviado) {
        logmsg("ERROR enviando a usuario $uid");
        continue;
    }

    // Solo se marcan DESPUES de un envio correcto: al reves, un fallo de SMTP
    // silenciaria esas alertas para siempre.
    $ins = $db->prepare(
        "INSERT IGNORE INTO avisos_enviados (usuario_id, proveedor, alerta_id)
         VALUES (?, ?, ?)"
    );
    foreach ($alertas as $a) {
        $ins->bind_param('iss', $uid, $a['proveedor'], $a['alerta_id']);
        $ins->execute();
    }
    $ins->close();

    $totalEnviados++;
    logmsg("Enviado a usuario $uid: " . count($alertas) . ' alerta(s)');
}

logmsg("Hecho. $totalEnviados correo(s) enviado(s).");
$db->close();
