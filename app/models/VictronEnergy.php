<?php

require_once __DIR__ . '/../controllers/ProveedoresController.php';

use Dotenv\Dotenv;

class VictronEnergy
{
    private $url;
    private $api_key;
    private $id_access_token;
    private $id_installation;
    private $proveedoresController;

    //definimos el constructor de la clase
    public function __construct()
    {
        try {
            // Cargar el archivo .env desde la carpeta config
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../config');
            $dotenv->load();

            // Asignar los valores del .env a las propiedades estáticas
            $this->url = $_ENV['VICTRONENERGY_URL'];
            $this->id_access_token = $_ENV['VICTRONENERGY_ACCESS_TOKEN'];
            $this->id_installation = $_ENV['VICTRONENERGY_ID_INSTALLATION'];
        } catch (Exception $e) {
            echo "Error al cargar el archivo .env GoodWe" . $e->getMessage();
        }
        // Inicializar el controlador de proveedores
        $this->proveedoresController = new ProveedoresController();

        //contiene un diccionario con tokenAuth y con tokenRenovation si estos estan en la bbdd
        $arrayToken = $this->proveedoresController->getTokenProveedor('VictronEnergy');

        // Obtener el token desde la base de datos y asignarlo a api_key
        $this->api_key = $arrayToken['tokenAuth'];
    }

    //definimos el getter y setter
    public function getUrl()
    {
        return $this->url;
    }
    public function setUrl($url)
    {
        $this->url = $url;
    }
    public function getApiKey()
    {
        return $this->api_key;
    }
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
    }
    public function getIdAccessToken()
    {
        return $this->id_access_token;
    }
    public function setIdAccessToken($id_access_token)
    {
        $this->id_access_token = $id_access_token;
    }
    public function getIdInstallation()
    {
        return $this->id_installation;
    }
    public function setIdInstallation($id_installation)
    {
        $this->id_installation = $id_installation;
    }
    //Documentacion API https://vrm-api-docs.victronenergy.com/#/
}
