<?php

require_once "../middlewares/autenticacion.php";
require_once "../DBObjects/logsDB.php";
require_once "../enums/Logs.php";

class LogsController {
    private Autenticacion $authMiddleware;
    private LogsDB $logsDB;

    public function __construct() {
        $this->authMiddleware = new Autenticacion();
        $this->logsDB = new LogsDB();
    }

    /**
     * Registra un log en la base de datos.
     * 
     * @param Logs $level Nivel del log (INFO, GET, POST, etc.).
     * @param string $message Mensaje adicional para el log.
     * @return bool True si el log fue registrado, False en caso contrario.
     */
    public function registrarLog(Logs $level, string $message): bool {
        try {
            // Obtener el ID del usuario activo
            $userId = $this->authMiddleware->obtenerIdUsuarioActivo();
    
            if (!$userId) {
                throw new Exception("No se pudo obtener el ID del usuario activo.");
            }
    
            // Verificar si el usuario es administrador
            if (!$this->authMiddleware->verificarAdmin()) {
                throw new Exception("El usuario no es administrador, no es necesario registrar su log.");
            }
    
            // Formatear el mensaje del log
            $formattedMessage = Logs::logMessage($level, $message, $userId);

    
            // Registrar el log en la base de datos
            return $this->logsDB->postLogs($userId, $level, $formattedMessage);
        } catch (Exception $e) {
            error_log("Error al registrar log: " . $e->getMessage());
            return false;
        }
    }
}
