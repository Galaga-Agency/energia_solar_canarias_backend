<?php
require_once __DIR__ . "/../models/conexion.php";

/**
 * Preferencias de notificacion, una fila por usuario.
 *
 * Sin fila significa "valores por defecto": asi los usuarios que ya existian no
 * necesitan que se les siembre nada, y borrar la fila equivale a resetear.
 */
class PreferenciasNotificacionesDB
{
    /** Lo que recibe quien nunca ha tocado esta pantalla. */
    /**
     * Lo que ve quien no ha tocado nunca esta pantalla.
     *
     * `activas` en false: nadie recibe correos sin haberlos pedido. Tiene que
     * coincidir con lo que hace el cron, que ya no escribe a quien no tenga
     * fila — si aqui dijera true, el interruptor apareceria encendido en
     * Ajustes mientras no llega ningun correo, que es peor que estar apagado.
     *
     * El resto son los valores con los que arranca el formulario cuando el
     * usuario SI active los avisos, no preferencias en uso.
     */
    private const DEFECTOS = [
        'activas'          => false,
        'email'            => true,
        'severidad_minima' => 'critical',
        'frecuencia'       => 'immediate',
    ];

    private const SEVERIDADES = ['critical', 'warning', 'info'];
    private const FRECUENCIAS = ['immediate', 'daily', 'weekly'];

    public function obtener($usuarioId)
    {
        try {
            $conn = Conexion::getInstance()->getConexion();
            $stmt = $conn->prepare(
                "SELECT activas, email, severidad_minima, frecuencia
                   FROM preferencias_notificaciones
                  WHERE usuario_id = ?
                  LIMIT 1"
            );
            if (!$stmt) {
                return self::DEFECTOS;
            }

            $stmt->bind_param('i', $usuarioId);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$fila) {
                return self::DEFECTOS;
            }

            // tinyint llega como "0"/"1": se normaliza aqui para que el
            // frontend reciba booleanos de verdad y no cadenas.
            return [
                'activas'          => (bool) $fila['activas'],
                'email'            => (bool) $fila['email'],
                'severidad_minima' => $fila['severidad_minima'],
                'frecuencia'       => $fila['frecuencia'],
            ];
        } catch (Exception $e) {
            error_log("Error en obtener preferencias: " . $e->getMessage());
            return self::DEFECTOS;
        }
    }

    public function guardar($usuarioId, $datos)
    {
        try {
            $conn = Conexion::getInstance()->getConexion();

            $activas = !empty($datos['activas']) ? 1 : 0;
            $email   = !empty($datos['email']) ? 1 : 0;

            // Lista blanca, no lo que llegue: estos valores acaban decidiendo
            // que correos se envian, y una cadena arbitraria aqui es un fallo
            // silencioso en el proceso que los manda.
            $severidad = in_array($datos['severidad_minima'] ?? '', self::SEVERIDADES, true)
                ? $datos['severidad_minima']
                : self::DEFECTOS['severidad_minima'];

            $frecuencia = in_array($datos['frecuencia'] ?? '', self::FRECUENCIAS, true)
                ? $datos['frecuencia']
                : self::DEFECTOS['frecuencia'];

            $stmt = $conn->prepare(
                "INSERT INTO preferencias_notificaciones
                     (usuario_id, activas, email, severidad_minima, frecuencia)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     activas = VALUES(activas),
                     email = VALUES(email),
                     severidad_minima = VALUES(severidad_minima),
                     frecuencia = VALUES(frecuencia)"
            );
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('iiiss', $usuarioId, $activas, $email, $severidad, $frecuencia);
            $ok = $stmt->execute();
            $stmt->close();

            return $ok;
        } catch (Exception $e) {
            error_log("Error en guardar preferencias: " . $e->getMessage());
            return false;
        }
    }
}
