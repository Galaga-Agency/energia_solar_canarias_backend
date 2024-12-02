<?php
require_once '../DBObjects/apiAccesosDB.php';

class ApiAccesosController {
    private $apiAccesosDB;
    private $logsController;

    public function __construct() {
        $this->apiAccesosDB = new ApiAccesosDB();
        $this->logsController = new LogsController();
    }

     /**
     * Crear o actualizar un acceso a la API para un usuario
     *
     * @param int $usuarioId El ID del usuario
     * @param string $api_key La clave de la API
     * @param string $api_scope El alcance (scope) del acceso
     * @return bool Devuelve true si se realizó la operación correctamente, false en caso contrario
     */
    public function upsertApiAcceso($usuarioId, $api_key, $api_scope) {
        try {
            $resultado = $this->apiAccesosDB->upsertApiAcceso($usuarioId, $api_key, $api_scope);

            if ($resultado) {
                $this->logsController->registrarLog(Logs::WARNING, 'Se creó o actualizó un acceso a un token permanente.');
                return $resultado;
            } else {
                $this->logsController->registrarLog(Logs::ERROR, 'No se pudo crear o actualizar el acceso a la API.');
                return false;
            }
        } catch (Exception $e) {
            $this->logsController->registrarLog(Logs::ERROR, 'Error en upsertApiAcceso: ' . $e->getMessage());
            return false;
        }
    }

    public function verificarApiAcceso($apiAcceso) {
        try {
            $acceso = $this->apiAccesosDB->verificarAccesoApiKey($apiAcceso);
            if (!$acceso) {
                throw new Exception("Acceso no válido para el token proporcionado.");
            }
    
            $this->logsController->registrarLog(Logs::WARNING, 'Se creó un acceso a un token permanente');
            return $acceso;
        } catch (Exception $e) {
            error_log("Error en verificarApiAcceso: " . $e->getMessage());
            return false;
        }
    }
    
    public function devolverIdPorAccesoApiKey($apiAcceso){      
        $userId = $this->apiAccesosDB->devolverIdPorAccesoApiKey($apiAcceso);
        $this->logsController->registrarLog(Logs::WARNING, 'se crea un acceso a un token permanente');
        return $userId;
    }
}
