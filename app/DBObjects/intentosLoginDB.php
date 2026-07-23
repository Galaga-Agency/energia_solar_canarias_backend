<?php

require_once __DIR__ . '/../models/conexion.php';

/**
 * Anti-fuerza-bruta: cuenta intentos fallidos por (identificador, ip) dentro de
 * una ventana temporal y decide el bloqueo. ISO 27001:2022 A.8.5.
 *
 * Se usa en /login (identificador = email) y en /token (identificador = "user:<id>",
 * para frenar el bruteforce del codigo enviado por email).
 *
 * Umbrales configurables por .env:
 *   LOGIN_MAX_INTENTOS  (por defecto 5)
 *   LOGIN_BLOQUEO_MIN   (por defecto 15 minutos)
 */
class IntentosLoginDB
{
    private const MAX_INTENTOS_DEF = 5;
    private const VENTANA_MIN_DEF  = 15;

    private function conn()
    {
        return Conexion::getInstance()->getConexion();
    }

    public static function maxIntentos(): int
    {
        $v = (int) ($_ENV['LOGIN_MAX_INTENTOS'] ?? self::MAX_INTENTOS_DEF);
        return $v > 0 ? $v : self::MAX_INTENTOS_DEF;
    }

    public static function ventanaMin(): int
    {
        $v = (int) ($_ENV['LOGIN_BLOQUEO_MIN'] ?? self::VENTANA_MIN_DEF);
        return $v > 0 ? $v : self::VENTANA_MIN_DEF;
    }

    /** ¿Está bloqueado ahora mismo este identificador desde esta IP? */
    public function estaBloqueado(string $identificador, string $ip): bool
    {
        return $this->contarRecientes($identificador, $ip) >= self::maxIntentos();
    }

    /** Nº de fallos de (identificador, ip) dentro de la ventana de bloqueo. */
    public function contarRecientes(string $identificador, string $ip): int
    {
        $conn = $this->conn();
        $sql = "SELECT COUNT(*) AS n FROM login_attempts
                WHERE identificador = ? AND ip = ?
                  AND creado_en > (NOW() - INTERVAL ? MINUTE)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $ventana = self::ventanaMin();
        $stmt->bind_param('ssi', $identificador, $ip, $ventana);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['n'] ?? 0);
    }

    /** Apunta un intento fallido. */
    public function registrarFallo(string $identificador, string $ip): void
    {
        $conn = $this->conn();
        $stmt = $conn->prepare("INSERT INTO login_attempts (identificador, ip) VALUES (?, ?)");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ss', $identificador, $ip);
        $stmt->execute();
        $stmt->close();
    }

    /** Al acertar, se limpian los fallos de ese identificador+ip. */
    public function limpiar(string $identificador, string $ip): void
    {
        $conn = $this->conn();
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE identificador = ? AND ip = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ss', $identificador, $ip);
        $stmt->execute();
        $stmt->close();
    }
}
