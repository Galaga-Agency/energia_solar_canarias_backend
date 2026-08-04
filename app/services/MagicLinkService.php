<?php

require_once __DIR__ . '/../models/conexion.php';

/**
 * Acceso sin contraseña por enlace magico.
 *
 * Sustituye al flujo de contraseña + codigo copiado a mano. El usuario escribe
 * su email, recibe un enlace y pulsa. Ya esta.
 *
 * Dos secretos distintos, cada uno con su vida:
 *
 *   1. MAGIC LINK  15 minutos, un solo uso. Viaja por correo dentro del enlace.
 *   2. HANDOFF     60 segundos, un solo uso. Lo devuelve el backend al frontend
 *                  en la redireccion, y el frontend lo canjea por el JWT.
 *
 * El handoff existe para que el JWT de sesion (30 dias) NO viaje nunca en una
 * URL. Las URLs acaban en el historial del navegador, en la cabecera Referer y
 * en los logs de cualquier proxy intermedio. Un handoff filtrado ahi no sirve
 * de nada: dura un minuto y solo se puede canjear una vez.
 *
 * De los dos secretos se guarda solo el SHA-256. Un volcado de la base de datos
 * no permite entrar como nadie, igual que con las contraseñas.
 *
 * @see db_migrations/2026_08_04_create_magic_link.sql
 */
class MagicLinkService
{
    /** Vida del enlace del correo. */
    const MAGIC_TTL_SEG = 900;      // 15 minutos

    /** Vida del codigo de traspaso al frontend. */
    const HANDOFF_TTL_SEG = 60;     // 1 minuto

    private $conn;

    public function __construct($conn = null)
    {
        $this->conn = $conn ?? Conexion::getInstance()->getConexion();
    }

    /**
     * Genera un secreto de 64 caracteres hexadecimales (32 bytes de entropia).
     *
     * random_bytes() es un CSPRNG. NUNCA usar rand(), mt_rand() ni uniqid()
     * para esto: son predecibles y un secreto de acceso predecible no es un
     * secreto.
     */
    private function generarSecreto()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Solo se guarda el hash. SHA-256 sin sal a proposito: no es una
     * contraseña, es un secreto de 256 bits generado por nosotros y de vida
     * cortisima. No hay diccionario que atacar, y la busqueda tiene que poder
     * hacerse por indice UNIQUE (un bcrypt con sal obligaria a recorrer la
     * tabla entera comparando fila por fila).
     */
    private function hash($secreto)
    {
        return hash('sha256', $secreto);
    }

    /**
     * Crea un enlace magico para un usuario y devuelve el token EN CLARO.
     *
     * El token en claro solo existe en memoria y en el correo que se envia;
     * en la base de datos queda unicamente su hash.
     *
     * @return string el token que hay que poner en la URL del correo
     */
    public function emitirMagicLink($usuarioId, $ip = null)
    {
        $token = $this->generarSecreto();

        $sql = "INSERT INTO magic_links (usuario_id, token_hash, expira_en, ip_solicitud)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la insercion del magic link');
        }

        $hash = $this->hash($token);
        $ttl  = self::MAGIC_TTL_SEG;
        $stmt->bind_param('isis', $usuarioId, $hash, $ttl, $ip);
        $stmt->execute();
        $stmt->close();

        return $token;
    }

    /**
     * Canjea un enlace magico. Devuelve el usuario_id o null si no vale.
     *
     * El canje es ATOMICO: el UPDATE marca la fila como consumida y solo
     * continua si afecto a una fila. Si dos peticiones llegan a la vez con el
     * mismo token (el usuario pulsa dos veces, o un escaner de correo hace
     * prefetch), MySQL serializa los UPDATE y solo uno ve affected_rows = 1.
     *
     * Comprobar primero y actualizar despues seria una condicion de carrera:
     * ambas peticiones verian el token como valido antes de que ninguna lo
     * marcase, y el enlace de "un solo uso" valdria dos veces.
     *
     * @return int|null usuario_id si el canje es valido
     */
    public function canjearMagicLink($token)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        $hash = $this->hash($token);

        $sql = "UPDATE magic_links
                   SET consumido_en = NOW()
                 WHERE token_hash = ?
                   AND consumido_en IS NULL
                   AND expira_en > NOW()";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $afectadas = $stmt->affected_rows;
        $stmt->close();

        // 0 filas = no existe, ya se uso, o caduco. Los tres casos son "no
        // vale", y desde fuera son indistinguibles a proposito: decir cual es
        // le confirmaria a un atacante que un token existio.
        if ($afectadas !== 1) {
            return null;
        }

        $sql = "SELECT usuario_id FROM magic_links WHERE token_hash = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $stmt->bind_result($usuarioId);
        $encontrado = $stmt->fetch();
        $stmt->close();

        return $encontrado ? (int) $usuarioId : null;
    }

    /**
     * Crea el codigo de traspaso que se le da al frontend en la redireccion.
     */
    public function emitirHandoff($usuarioId)
    {
        $codigo = $this->generarSecreto();

        $sql = "INSERT INTO auth_handoffs (usuario_id, code_hash, expira_en)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la insercion del handoff');
        }

        $hash = $this->hash($codigo);
        $ttl  = self::HANDOFF_TTL_SEG;
        $stmt->bind_param('isi', $usuarioId, $hash, $ttl);
        $stmt->execute();
        $stmt->close();

        return $codigo;
    }

    /**
     * Canjea el codigo de traspaso. Mismo UPDATE atomico que el magic link.
     *
     * @return int|null usuario_id si el canje es valido
     */
    public function canjearHandoff($codigo)
    {
        if (!is_string($codigo) || $codigo === '') {
            return null;
        }

        $hash = $this->hash($codigo);

        $sql = "UPDATE auth_handoffs
                   SET consumido_en = NOW()
                 WHERE code_hash = ?
                   AND consumido_en IS NULL
                   AND expira_en > NOW()";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $afectadas = $stmt->affected_rows;
        $stmt->close();

        if ($afectadas !== 1) {
            return null;
        }

        $sql = "SELECT usuario_id FROM auth_handoffs WHERE code_hash = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $stmt->bind_result($usuarioId);
        $encontrado = $stmt->fetch();
        $stmt->close();

        return $encontrado ? (int) $usuarioId : null;
    }

    /**
     * Busca un usuario por email para el alta de sesion.
     *
     * Aplica las MISMAS reglas que el login con contraseña: un usuario
     * eliminado o inactivo no entra. Si esto se olvidase, el enlace magico
     * seria una puerta trasera para cuentas dadas de baja.
     *
     * @return array|null datos del usuario, o null si no puede entrar
     */
    public function buscarUsuarioPorEmail($email)
    {
        $sql = "SELECT usuarios.usuario_id, usuarios.email, clases.nombre AS clase,
                       usuarios.nombre, usuarios.apellido, usuarios.movil,
                       usuarios.imagen, usuarios.activo, usuarios.eliminado
                  FROM usuarios
            INNER JOIN clases ON clases.clase_id = usuarios.clase_id
                 WHERE usuarios.email = ?
                 LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $fila = $resultado ? $resultado->fetch_assoc() : null;
        $stmt->close();

        if (!$fila) {
            return null;
        }
        if ((int) $fila['eliminado'] === 1 || (int) $fila['activo'] === 0) {
            return null;
        }

        return [
            'id'       => (int) $fila['usuario_id'],
            'email'    => $fila['email'],
            'clase'    => $fila['clase'],
            'nombre'   => $fila['nombre'],
            'apellido' => $fila['apellido'],
            'movil'    => $fila['movil'],
            'imagen'   => $fila['imagen'],
            'activo'   => $fila['activo'],
        ];
    }

    /**
     * Borra enlaces y handoffs caducados o ya usados.
     *
     * Nada mas los borra: el canje solo marca `consumido_en`, no elimina la
     * fila. Sin esto las tablas solo crecen. Lo llama el cron de mantenimiento.
     *
     * Se deja un margen de un dia antes de borrar los consumidos, por si hay
     * que investigar un acceso reciente.
     */
    public function limpiarCaducados()
    {
        $this->conn->query(
            "DELETE FROM magic_links
              WHERE expira_en < DATE_SUB(NOW(), INTERVAL 1 DAY)
                 OR (consumido_en IS NOT NULL
                     AND consumido_en < DATE_SUB(NOW(), INTERVAL 1 DAY))"
        );
        $this->conn->query(
            "DELETE FROM auth_handoffs
              WHERE expira_en < DATE_SUB(NOW(), INTERVAL 1 DAY)
                 OR (consumido_en IS NOT NULL
                     AND consumido_en < DATE_SUB(NOW(), INTERVAL 1 DAY))"
        );
    }
}
