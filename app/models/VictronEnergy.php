<?php
class VictronEnergy{
    private $url;
    private $api_key;
    private $id_access_token;

    //definimos el constructor de la clase
    public function __construct()
    {
        $this->url = 'https://monitoringapi.solaredge.com/';
        $this->api_key = '***REMOVED***';
        $this->id_access_token = 0;
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
    //Documentacion API https://vrm-api-docs.victronenergy.com/#/
}

?>