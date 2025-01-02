<?php

require_once __DIR__ . '/../controllers/ProveedoresController.php';

class SolarEdge{
    private $url;
    private $api_key;
    private $proveedoresController;

    //definimos el constructor de la clase
    public function __construct()
    {
        $this->url = 'https://monitoringapi.solaredge.com/';
        // Inicializar el controlador de proveedores
        $this->proveedoresController = new ProveedoresController();

        //contiene un diccionario con tokenAuth y con tokenRenovation si estos estan en la bbdd
        $arrayToken = $this->proveedoresController->getTokenProveedor('SolarEdge');

        // Obtener el token desde la base de datos y asignarlo a api_key
        $this->api_key = $arrayToken['tokenAuth'];
    }

    //definimos el getter y setter
    public function getUrl(){
        return $this->url;
    }
    public function setUrl($url){
        $this->url = $url;
    }
    public function getApiKey(){
        return $this->api_key;
    }
    public function setApiKey($api_key){
        $this->api_key = $api_key;
    }
}

?>