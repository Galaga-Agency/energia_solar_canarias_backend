<?php
class SolarEdge{
    private $url;
    private $api_key;

    //definimos el constructor de la clase
    public function __construct()
    {
        $this->url = 'https://monitoringapi.solaredge.com/';
        $this->api_key = '***REMOVED***';
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