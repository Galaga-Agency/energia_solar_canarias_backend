<?php

require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../utils/seguridad.php';
require_once __DIR__ . '/../DBObjects/intentosLoginDB.php';
require_once __DIR__ . '/../DBObjects/metodosAccesoDB.php';
require_once __DIR__ . '/../services/MetodosAcceso.php';
require_once __DIR__ . '/SesionWebController.php';

/**
 * Entrar con email + contraseña, y elegir con que metodos se puede entrar.
 *
 *   POST /auth/password            {email, password} -> sesion (mismo formato que /auth/handoff)
 *   GET  /usuario/metodos-acceso   estado de los metodos del propio usuario
 *   PUT  /usuario/metodos-acceso   {metodo, activo, password?}
 *
 * Ver documentacion/autenticacion.md.
 */
class AccesoController
{
    /** Respuesta unica para cualquier fallo de /auth/password: no dice si el email existe. */
    const NO_AUTORIZADO = 'Email o contraseña incorrectos, o el acceso con contraseña no está activado en esta cuenta';

    /**
     * Hash de relleno para comparar cuando el email no existe: el tiempo de
     * respuesta no tiene que delatar que cuentas hay.
     */
    const HASH_RELLENO = '$2y$10$QL9PDexOFCoaOjARRiIGf.IIG7syYUszOzpC0ehxR2TU3CAXMWGn6';

    private $metodosDB;

    public function __construct(?MetodosAccesoDB $metodosDB = null)
    {
        $this->metodosDB = $metodosDB ?? new MetodosAccesoDB();
    }

    public function entrarConPassword($datosJson)
    {
        $respuesta = new Respuesta;
        // Toda sesion web es renovable (sesion en BD + refresh en cookie): el JWT
        // largo sin sesion ya no se emite ni se acepta.
        if (!SesionWebController::permitida()) {
            $respuesta->_403(); $respuesta->message = 'Origen o formato de acceso no permitido';
            $this->enviar($respuesta); return;
        }
        $datos = json_decode((string) $datosJson, true) ?: [];
        $email = strtolower(trim((string) ($datos['email'] ?? '')));
        $password = (string) ($datos['password'] ?? '');
        $ip = ipCliente();

        if ($email === '' || $password === '') {
            $respuesta->_400();
            $respuesta->message = 'Faltan el email o la contraseña';
            $this->enviar($respuesta);
            return;
        }

        // Anti fuerza bruta (A.8.5): mismo contador que el resto de entradas.
        $intentos = new IntentosLoginDB();
        if ($intentos->estaBloqueado($email, $ip)) {
            registrarEventoSeguridad('password_bloqueado', 'email=' . $email);
            $respuesta->_429();
            $respuesta->message = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
            $this->enviar($respuesta);
            return;
        }

        $fila = $this->usuarioPorEmail($email);
        $hash = $fila['password_hash'] ?? self::HASH_RELLENO;
        $passwordOk = password_verify($password, $hash);

        $motivo = null;
        if (!$fila) {
            $motivo = 'email_desconocido';
        } elseif (!$passwordOk) {
            $motivo = 'password_incorrecta';
        } elseif (!$this->metodosDB->permitido((int) $fila['usuario_id'], MetodosAcceso::PASSWORD)) {
            $motivo = 'metodo_desactivado';
        }

        if ($motivo !== null) {
            $intentos->registrarFallo($email, $ip);
            registrarEventoSeguridad('password_fallido', 'email=' . $email . ' motivo=' . $motivo);
            $respuesta->_401();
            $respuesta->message = self::NO_AUTORIZADO;
            $this->enviar($respuesta);
            return;
        }

        $intentos->limpiar($email, $ip);
        registrarEventoSeguridad('password_ok', 'email=' . $email);

        $sesion = self::datosSesion((int) $fila['usuario_id']);
        if (!$sesion) { $respuesta->_401(); $this->enviar($respuesta); return; }
        $respuesta->success($sesion);
        $respuesta->message = 'Sesión iniciada';
        $this->enviar($respuesta);
    }

    public function obtenerMetodos(int $usuarioId)
    {
        $respuesta = new Respuesta;
        $respuesta->success($this->metodosDB->obtener($usuarioId));
        $this->enviar($respuesta);
    }

    public function cambiarMetodo(int $usuarioId, $datosJson, ?string $jwt = null)
    {
        $respuesta = new Respuesta;
        $datos = json_decode((string) $datosJson, true) ?: [];
        $metodo = (string) ($datos['metodo'] ?? '');
        $activo = filter_var($datos['activo'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $password = isset($datos['password']) ? (string) $datos['password'] : null;

        if ($activo === null) {
            $respuesta->_400();
            $respuesta->message = 'Falta `activo` (true o false)';
            $this->enviar($respuesta);
            return;
        }

        $estado = $this->metodosDB->obtener($usuarioId);
        $rechazo = MetodosAcceso::motivoRechazo($estado, $metodo, $activo, $password);
        // Cambiar la contraseña con el metodo ya encendido tambien pasa por aqui.
        if ($rechazo === null && $password !== null) {
            $rechazo = MetodosAcceso::motivoPasswordInvalida($password);
        }
        if ($rechazo !== null) {
            $respuesta->_400();
            $respuesta->message = $rechazo;
            $this->enviar($respuesta);
            return;
        }

        // Volver a identificarse: login de hace menos de 10 min, o la contraseña actual.
        $pase = $this->reautenticado($usuarioId, $jwt, $datos['passwordActual'] ?? null, $estado);
        if ($pase !== true) {
            $respuesta->code = $pase === 'bloqueado' ? 429 : 428;
            $respuesta->status = false;
            $respuesta->data = ['reautenticar' => true];
            $respuesta->message = $pase === 'bloqueado'
                ? 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.'
                : 'Por seguridad, vuelve a entrar (o escribe tu contraseña actual) para cambiar las formas de entrar.';
            $this->enviar($respuesta);
            return;
        }

        // La contraseña solo se guarda si se esta encendiendo (o ya estaba) el
        // acceso con contraseña: apagarlo no la toca.
        $guardarPassword = ($metodo === MetodosAcceso::PASSWORD && $activo) ? $password : null;
        if (!$this->metodosDB->guardar($usuarioId, $metodo, $activo, $guardarPassword)) {
            $respuesta->_500();
            $respuesta->message = 'No se pudo guardar el cambio';
            $this->enviar($respuesta);
            return;
        }

        registrarEventoSeguridad('metodo_acceso_cambiado', 'id=' . $usuarioId . ' metodo=' . $metodo . ' activo=' . ($activo ? 1 : 0)
            . ($guardarPassword !== null ? ' password=nueva' : ''));
        $this->avisarPorCorreo($usuarioId, $metodo, $activo, $guardarPassword !== null, $estado);

        $respuesta->success($this->metodosDB->obtener($usuarioId));
        $respuesta->message = 'Guardado';
        $this->enviar($respuesta);
    }

    /**
     * true si puede cambiar sus formas de entrar; si no, 'reautenticar' o 'bloqueado'.
     *
     * Vale haber entrado hace menos de MetodosAcceso::REAUTENTICACION_SEG (la hora del
     * login sale de la sesion renovable, o del iat de los JWT antiguos, que se emiten
     * al entrar), o dar la contraseña actual si el acceso con contraseña esta activo.
     * Las claves de API no sirven: no hay login detras.
     */
    private function reautenticado(int $usuarioId, ?string $jwt, $passwordActual, array $estado)
    {
        if (MetodosAcceso::loginReciente(self::momentoDelLogin($usuarioId, $jwt), time())) return true;
        if (!$estado[MetodosAcceso::PASSWORD] || !is_string($passwordActual) || $passwordActual === '') return 'reautenticar';

        $intentos = new IntentosLoginDB();
        $ip = ipCliente();
        $clave = 'reauth:' . $usuarioId;
        if ($intentos->estaBloqueado($clave, $ip)) return 'bloqueado';

        $conn = Conexion::getInstance()->getConexion();
        $stmt = $conn->prepare("SELECT password_hash FROM usuarios WHERE usuario_id = ? LIMIT 1");
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($fila && password_verify($passwordActual, $fila['password_hash'])) {
            $intentos->limpiar($clave, $ip);
            return true;
        }
        $intentos->registrarFallo($clave, $ip);
        registrarEventoSeguridad('reautenticacion_fallida', 'id=' . $usuarioId);
        return 'reautenticar';
    }

    /** Cuando entro el usuario de este JWT (no cuando se renovo). null si no se sabe. */
    private static function momentoDelLogin(int $usuarioId, ?string $jwt): ?int
    {
        $claims = $jwt !== null ? Conexion::claimsJwt($jwt) : null;
        if (!$claims) return null;
        if (isset($claims->sid) && is_string($claims->sid)) {
            $conn = Conexion::getInstance()->getConexion();
            $stmt = $conn->prepare("SELECT creado_en FROM auth_sesiones WHERE id = ? AND usuario_id = ? LIMIT 1");
            $stmt->bind_param('si', $claims->sid, $usuarioId);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $fila ? (int) $fila['creado_en'] : null;
        }
        return isset($claims->iat) ? (int) $claims->iat : null;
    }

    /** Correo de aviso al dueño de la cuenta. Un fallo de correo no deshace el cambio. */
    private function avisarPorCorreo(int $usuarioId, string $metodo, bool $activo, bool $passwordNueva, array $antes): void
    {
        $perfil = self::perfil($usuarioId);
        if (!$perfil) return;
        $cambios = [];
        if ($metodo === MetodosAcceso::PASSWORD) {
            if ($activo && !$antes[MetodosAcceso::PASSWORD]) $cambios[] = 'Se ha activado el acceso con correo y contraseña.';
            elseif ($activo && $passwordNueva) $cambios[] = 'Se ha cambiado la contraseña.';
            elseif (!$activo) $cambios[] = 'Se ha desactivado el acceso con correo y contraseña.';
        } elseif ($metodo === MetodosAcceso::ENLACE) {
            $cambios[] = $activo ? 'Se ha activado el acceso por enlace al correo.' : 'Se ha desactivado el acceso por enlace al correo.';
        }
        if ($cambios) {
            require_once __DIR__ . '/../services/correo.php';
            (new Correo())->avisoCambioAcceso($perfil, $cambios, 'es');
        }
    }

    /**
     * Lo que recibe el frontend al iniciar sesion, venga de donde venga
     * (enlace o contraseña). null si la cuenta no esta activa.
     */
    public static function datosSesion(int $usuarioId): ?array
    {
        $perfil = self::perfil($usuarioId);
        if (!$perfil) return null;
        return array_merge($perfil, SesionWebController::emitir($usuarioId));
    }

    public static function perfil(int $usuarioId): ?array
    {
        $conn = Conexion::getInstance()->getConexion();
        $stmt = $conn->prepare(
            "SELECT usuarios.usuario_id, usuarios.email, clases.nombre AS clase,
                    usuarios.nombre, usuarios.apellido, usuarios.movil, usuarios.imagen
               FROM usuarios
         INNER JOIN clases ON clases.clase_id = usuarios.clase_id
              WHERE usuarios.usuario_id = ?
                AND usuarios.eliminado = 0
                AND usuarios.activo = 1
              LIMIT 1"
        );
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila) {
            return null;
        }

        return [
            'usuario_id' => (int) $fila['usuario_id'],
            'email'      => $fila['email'],
            'clase'      => $fila['clase'],
            'nombre'     => $fila['nombre'],
            'apellido'   => $fila['apellido'],
            'movil'      => $fila['movil'],
            'imagen'     => $fila['imagen'],
        ];
    }

    /** Solo cuentas activas y no eliminadas: las demas no pueden entrar por ningun metodo. */
    private function usuarioPorEmail(string $email): ?array
    {
        $conn = Conexion::getInstance()->getConexion();
        $stmt = $conn->prepare(
            "SELECT usuario_id, password_hash FROM usuarios
              WHERE (email = ? OR email_respaldo = ?) AND activo = 1 AND eliminado = 0
              LIMIT 1"
        );
        // El email de respaldo tambien vale, igual que en el enlace (MagicLinkService).
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $fila ?: null;
    }

    private function enviar(Respuesta $respuesta): void
    {
        http_response_code($respuesta->code);
        echo json_encode($respuesta);
    }
}
