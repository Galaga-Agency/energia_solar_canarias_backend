<?php
require_once __DIR__ . '/../utils/HttpClient.php';
require_once __DIR__ . '/../models/VictronEnergy.php';

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

    //Recoger datos detallados de la planta
    public function getSiteEquipo($siteId) {
        $url = $this->victronEnergy->getUrl() . "installations/$siteId/system-overview";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Recoger datos detallados de la planta
    public function getSiteAlarms($siteId,$pageIndex = 1,$pageSize = 200) {
        $url = $this->victronEnergy->getUrl() . "installations/$siteId/alarm-log?page=$pageIndex&count=$pageSize";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return json_decode($response);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
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

    //Recoger datos detallados de la planta
    public function getSiteRealtime($siteId) {
        $url = $this->victronEnergy->getUrl() . "installations/". $siteId ."/diagnostics";
        try {
            $response = $this->httpClient->get($url, $this->header);
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    //Método que recoje todas las plantas
    public function getAllPlants($page = 1, $pageSize = 200) {
        $url = $this->victronEnergy->getUrl() . "users/". $this->victronEnergy->getIdInstallation()."/installations?extended=1";
        try {
            $response = $this->httpClient->get($url,$this->header);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
}
?>
