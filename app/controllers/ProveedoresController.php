<?php

require_once './../DBObjects/proveedoresDB.php';

class ProveedoresController{
    private $proveedoresDB;

    function __construct()
    {
        $this->proveedoresDB = new ProveedoresDB;
    }

    public function setTokenProveedor($nombreProveedor, $token, $tokenRenovation = '', $expires_at = null) {
        try {
            // Paso 1: Verificar si el proveedor existe y obtener su token_id
            $tokenId = $this->proveedoresDB->verificarTokenProveedor($nombreProveedor);

            if ($tokenId === false) {
                // El proveedor no existe, no se inserta el token
                throw new Exception("El proveedor '$nombreProveedor' no existe en la base de datos.");
            }
    
            // Paso 2: Si el token ya existe, actualizarlo
            if ($tokenId) {
                return $this->proveedoresDB->actualizarToken($tokenId, $token, $tokenRenovation, $expires_at);
            }
    
            // Paso 3: Si no existe un token asociado, insertar uno nuevo y asociarlo
            return $this->proveedoresDB->insertarTokenYAsociar($nombreProveedor, $token, $tokenRenovation, $expires_at);
        } catch (Exception $e) {
            error_log("Error en setTokenProveedor: " . $e->getMessage());
            return false;
        }
    }
    public function getTokenProveedor($nombreProveedor) {
        try {
            // Paso 1: Verificar si el proveedor existe y obtener su token_id
            $tokenId = $this->proveedoresDB->verificarTokenProveedor($nombreProveedor);
    
            if ($tokenId === false) {
                // El proveedor no existe, no se inserta el token
                throw new Exception("El proveedor '$nombreProveedor' no existe en la base de datos.");
            }
    
            // Paso 2: Recogemos el token del proveedor
            $tokenProveedor = $this->proveedoresDB->getTokenProveedor($nombreProveedor);

            //Si actualmente el token del proveedor no existe devolvemos una cadena de strings vacia sino devolvemos el tokenAuth y tokenRenovation
            if($this->proveedoresDB->getTokenProveedor($nombreProveedor) == false){
                return [
                    'tokenAuth' => '',
                    'tokenRenovation' => '',
                    'expires_at' => ''
                ];
            }else{
                return $tokenProveedor;
            }

        } catch (Exception $e) {
            error_log("Error en setTokenProveedor: " . $e->getMessage());
            return false;
        }
    }
}
?>