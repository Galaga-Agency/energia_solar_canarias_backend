<?php

require_once './../controllers/ProveedoresController.php';

class VictronEnergy{
    private $url;
    private $api_key;
    private $id_access_token;
    private $id_installation;
    private $proveedoresController;

    //definimos el constructor de la clase
    public function __construct()
    {
        $this->url = 'https://vrmapi.victronenergy.com/v2/';
        $this->id_access_token = 2160468;
        $this->id_installation = 58178;

        // Inicializar el controlador de proveedores
        $this->proveedoresController = new ProveedoresController();

        //contiene un diccionario con tokenAuth y con tokenRenovation si estos estan en la bbdd
        $arrayToken = $this->proveedoresController->getTokenProveedor('VictronEnergy');

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
    public function getIdAccessToken(){
        return $this->id_access_token;
    }
    public function setIdAccessToken($id_access_token){
        $this->id_access_token = $id_access_token;
    }
    public function getIdInstallation(){
        return $this->id_installation;
    }
    public function setIdInstallation($id_installation){
        $this->id_installation = $id_installation;
    }
    //Documentacion API https://vrm-api-docs.victronenergy.com/#/
}

?>