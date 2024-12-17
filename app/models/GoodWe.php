<?php
class GoodWe{
    private $url;
    private $account;
    private $pwd;
    private $proveedoresController;

    //definimos el constructor de la clase
    public function __construct()
    {
        $this->url = 'https://www.semsportal.com/';
        $this->account = "carlaisari@energiasolarcanarias.es";
        $this->pwd = "Good@20We19!";
    }

    //definimos el getter y setter
    public function getUrl(){
        return $this->url;
    }
    public function setUrl($url){
        $this->url = $url;
    }
    public function getAccount(){
        return $this->account;
    }
    public function setAccount($account){
        $this->account = $account;
    }
    public function getPwd(){
        return $this->pwd;
    }
    public function setPwd($pwd){
        $this->pwd = $pwd;
    }
}
//Esta clase es para loguearse
class GoodWeToken extends GoodWe{
    private $version;
    private $client;
    private $language;

    public function __construct()
    {
        parent::__construct();
        $this->version = 'v2.1.0';
        $this->client = 'ios';
        $this->language = 'en';
    }
    //definimos el getter y setter
    public function getVersion(){
        return $this->version;
    }
    public function setVersion($version){
        $this->version = $version;
    }
    public function getClient(){
        return $this->client;
    }
    public function setClient($client){
        $this->client = $client;
    }
    public function getLanguage(){
        return $this->language;
    }
    public function setLanguage($language){
        $this->language = $language;
    }
}
//Esta clase es la que se usa para pasar el token de usuario una vez logueado
class GoodWeTokenAuthentified extends GoodWeToken{
    private $timestamp;
    private $uid;
    private $token;
    
    public function __construct($token = 'da75ae92a1ad4d446bc75261cc916285', $timestamp = '1734430598789', $uid = '6c763cdd-b245-4d9b-ab3f-8ccaa1c9f8b7')
    {
        parent::__construct();
        $this->timestamp = $timestamp;
        $this->uid = $uid;
        // Inicializar el controlador de proveedores
        $proveedoresController = new ProveedoresController();

        //contiene un diccionario con tokenAuth y con tokenRenovation si estos estan en la bbdd
        $arrayToken = $proveedoresController->getTokenProveedor('GoodWe');
        $this->token = $arrayToken['tokenAuth'];
        $this->timestamp = $arrayToken['expires_at'];
    }
    public function getTimestamp(){
        return $this->timestamp;
    }
    public function setTimestamp($timestamp){
        $this->timestamp = $timestamp;
    }
    public function getUid(){
        return $this->uid;
    }
    public function setUid($uid){
        $this->uid = $uid;
    }
    public function getToken(){
        return $this->token;
    }
    public function setToken($token){
        $this->uid = $token;
    }
}
?>