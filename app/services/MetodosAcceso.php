<?php

/**
 * Las formas de entrar en la aplicacion y las reglas para activarlas.
 *
 * Solo logica: no toca la base de datos (eso es MetodosAccesoDB), para poder
 * probarla sin stack.
 *
 *   enlace    el correo con el enlace de un solo uso (POST /auth/magic-link).
 *             Activo por defecto: es como ha entrado siempre todo el mundo.
 *   password  email + contraseña, entra directo (POST /auth/password).
 *             Inactivo por defecto: cada usuario lo enciende en Ajustes.
 *
 * Para añadir un metodo nuevo (totp, sms, google, microsoft...) se declara aqui
 * con su valor por defecto y se implementa su entrada; la tabla no cambia.
 *
 * Fuera de esto queda el login antiguo /login + /token (contraseña + codigo por
 * correo): lo usa la app movil y no se ve afectado por estos interruptores.
 */
class MetodosAcceso
{
    const ENLACE = 'enlace';
    const PASSWORD = 'password';

    /** metodo => activo por defecto. */
    const METODOS = [
        self::ENLACE => true,
        self::PASSWORD => false,
    ];

    /**
     * Cambiar las formas de entrar exige haber entrado hace menos de esto (o dar la
     * contraseña actual). Con solo un token robado no se puede poner una contraseña
     * propia y apagar el enlace: eso convertiria un robo de 15 minutos en permanente.
     */
    const REAUTENTICACION_SEG = 600;

    public static function loginReciente(?int $loginEn, int $ahora): bool
    {
        return $loginEn !== null && $loginEn <= $ahora && $ahora - $loginEn <= self::REAUTENTICACION_SEG;
    }

    /** bcrypt solo usa los primeros 72 bytes: mas largo daria por buena una contraseña distinta. */
    const PASSWORD_MIN = 10;
    const PASSWORD_MAX_BYTES = 72;

    /**
     * Estado completo a partir de las filas guardadas: lo que no tenga fila
     * vale lo de por defecto, y lo guardado de un metodo que ya no existe se ignora.
     *
     * @param array<string, bool|int|string> $guardado metodo => activo
     * @return array<string, bool>
     */
    public static function combinar(array $guardado): array
    {
        $estado = [];
        foreach (self::METODOS as $metodo => $defecto) {
            $estado[$metodo] = array_key_exists($metodo, $guardado) ? (bool) $guardado[$metodo] : $defecto;
        }
        return $estado;
    }

    public static function existe(string $metodo): bool
    {
        return array_key_exists($metodo, self::METODOS);
    }

    /**
     * Por que no se puede hacer el cambio, o null si se puede.
     *
     * @param array<string, bool> $estado estado actual (de combinar())
     */
    public static function motivoRechazo(array $estado, string $metodo, bool $activo, ?string $password): ?string
    {
        if (!self::existe($metodo)) {
            return 'Método de acceso desconocido';
        }

        if (!$activo) {
            $restantes = array_filter($estado, fn($on, $m) => $on && $m !== $metodo, ARRAY_FILTER_USE_BOTH);
            if (!$restantes) {
                return 'Tiene que quedar al menos una forma de entrar';
            }
            return null;
        }

        // Encender la contraseña exige fijarla en la misma peticion: las cuentas
        // creadas sin contraseña tienen una aleatoria que nadie conoce, y un
        // metodo activo sin contraseña valida no serviria para entrar.
        if ($metodo === self::PASSWORD) {
            return self::motivoPasswordInvalida($password);
        }
        return null;
    }

    public static function motivoPasswordInvalida(?string $password): ?string
    {
        if ($password === null || $password === '') {
            return 'Para activar el acceso con contraseña hay que elegir una';
        }
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            return 'La contraseña tiene que tener al menos ' . self::PASSWORD_MIN . ' caracteres';
        }
        if (strlen($password) > self::PASSWORD_MAX_BYTES) {
            return 'La contraseña es demasiado larga';
        }
        return null;
    }
}
