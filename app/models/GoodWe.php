<?php
class GoodWe{
    private $url;

    //definimos el constructor de la clase
    public function __construct()
    {
        $this->url = 'https://www.semsportal.com/';
    }

    //definimos el getter y setter
    public function getUrl(){
        return $this->url;
    }
    public function setUrl($url){
        $this->url = $url;
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
    
    public function __construct($token = 'da75ae92a1ad4d446bc75261cc916285', $timestamp = '1731319219018', $uid = '81cc2696-a88d-48f2-94ea-f146b3a32633')
    {
        parent::__construct();
        $this->timestamp = $timestamp;
        $this->uid = $uid;
        $this->token = $token;
    }
    public function getTimestamp(){
        return $this->timestamp;
    }
    public function setClient($timestamp){
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