<?php
require_once '../utils/HttpClient.php';
require_once '../models/VictronEnergy.php';

class VictronEnergyService {
    private $victronEnergy;
    private $httpClient;
    private $header;

    public function __construct() {
        $this->victronEnergy = new VictronEnergy();
        $this->httpClient = new HttpClient();
        $this->header = [
            'x-authorization: ' . $this->victronEnergy->getApiKey()
        ];
    }

    //recoger el grafico de las plantas / parece que tambien funciona para recoger en tiempo real los valores
    public function getGraficoDetails($siteId,$timeStart,$timeEnd,$type,$interval) {
        $url = $this->victronEnergy->getUrl() . "installations/$siteId/stats?end=$timeEnd&interval=hours&start=$timeStart&type=$type&interval=$interval";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoger datos detallados de la planta
    public function getSiteDetails($siteId) {
        $url = $this->victronEnergy->getUrl() . "users/". $this->victronEnergy->getIdInstallation() ."/installations?idSite=$siteId&extended=1";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Método que recoje todas las plantas
    public function getAllPlants($page = 1, $pageSize = 200) {
        $url = $this->victronEnergy->getUrl() . "users/". $this->victronEnergy->getIdInstallation()."/installations";
        try {
            $response = $this->httpClient->get($url,$this->header);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
}
?>
