<?php
require_once '../utils/HttpClient.php';
require_once '../models/SolarEdge.php';

class SolarEdgeService {
    private $goodWe;
    private $httpClient;

    public function __construct() {
        $this->goodWe = new GoodWeTokenAuthentified();
        $this->httpClient = new HttpClient();
    }

    public function GetPlantDetailByPowerstationId($powerStationId) {
        $url = $this->goodWe->getUrl() . "api/v3/PowerStation/GetPlantDetailByPowerstationId";
        try {
            $response = $this->httpClient->get($url, 
            array('uid' => $powerStationId));
            $response = $this->httpClient->get($url);
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>
