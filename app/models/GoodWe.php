<?php
use Dotenv\Dotenv;
class GoodWe{
    public static $url;
    public static $account;
    public static $pwd;
    private $proveedoresController;

    //definimos el constructor de la clase
    public function __construct()
    {
        try{
            // Cargar el archivo .env desde la carpeta config
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../config');
            $dotenv->load();

            // Asignar los valores del .env a las propiedades estáticas
            self::$url = $_ENV['GOODWE_URL'];
            self::$account = $_ENV['GOODWE_ACCOUNT'];
            self::$pwd = $_ENV['GOODWE_PASSWORD'];
        }catch(Exception $e){
            echo "Error al cargar el archivo .env GoodWe" . $e->getMessage();
        }
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
    
    /** Datos de una sesion; la persistencia y renovacion son de GoodWeSesionService. */
    public function __construct($token = '', $timestamp = '', $uid = '')
    {
        parent::__construct();
        $this->token = $token;
        $this->timestamp = $timestamp;
        $this->uid = $uid;
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
        // Antes escribia en $this->uid: tras un re-login el uid quedaba con el token
        // y la siguiente peticion iba con una cabecera invalida.
        $this->token = $token;
    }
}
?>