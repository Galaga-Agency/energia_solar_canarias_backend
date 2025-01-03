<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/services/VictronEnergyService.php';
require_once __DIR__ . '/../app/utils/HttpClient.php';
require_once __DIR__ . '/../app/models/VictronEnergy.php';

class VictronEnergyServiceTest extends TestCase
{
    private $victronEnergyService;
    private $httpClientMock;
    private $victronEnergyMock;

    protected function setUp(): void
    {
        $this->httpClientMock = $this->createMock(HttpClient::class);
        $this->victronEnergyMock = $this->createMock(VictronEnergy::class);

        $this->victronEnergyService = new VictronEnergyService();
        $this->victronEnergyService->setHttpClient($this->httpClientMock);
        $this->victronEnergyService->setVictronEnergy($this->victronEnergyMock);
    }

    public function testGetSiteEquipo()
    {
        $siteId = 123;
        $url = "https://example.com/installations/$siteId/system-overview";
        $expectedResponse = json_encode(['data' => 'some data']);

        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        $result = $this->victronEnergyService->getSiteEquipo($siteId);
        $this->assertEquals(json_decode($expectedResponse), $result);
        echo json_encode($result);
        echo "\n" . 'testGetSiteEquipo' . "\n";
    }

    public function testGetSiteAlarms()
    {
        $siteId = 123;
        $pageIndex = 1;
        $pageSize = 200;
        $url = "https://example.com/installations/$siteId/alarm-log?page=$pageIndex&count=$pageSize";
        $expectedResponse = json_encode(['data' => 'some data']);

        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        $result = $this->victronEnergyService->getSiteAlarms($siteId, $pageIndex, $pageSize);
        $this->assertEquals(json_decode($expectedResponse), $result);
        echo json_encode($result);
        echo "\n" . 'testGetSiteAlarms' . "\n";
    }

    public function testGetGraficoDetails()
    {
        $siteId = 123;
        $timeStart = '2021-01-01';
        $timeEnd = '2021-01-02';
        $type = 'power';
        $interval = 'hours';
        $url = "https://example.com/installations/$siteId/stats?end=$timeEnd&interval=hours&start=$timeStart&type=$type&interval=$interval";
        $expectedResponse = json_encode(['data' => 'some data']);

        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        $result = $this->victronEnergyService->getGraficoDetails($siteId, $timeStart, $timeEnd, $type, $interval);
        $this->assertEquals(json_decode($expectedResponse), $result);
        echo json_encode($result);
        echo "\n" . 'testGetGraficoDetails' . "\n";
    }

    public function testGetSiteDetails()
    {
        $siteId = 123;
        $installationId = 456;
        $url = "https://example.com/users/$installationId/installations?idSite=$siteId&extended=1";
        $expectedResponse = 'some data';

        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->victronEnergyMock->method('getIdInstallation')->willReturn($installationId);
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        $result = $this->victronEnergyService->getSiteDetails($siteId);
        $this->assertEquals($expectedResponse, $result);
        echo json_encode($result);
        echo "\n" . 'testGetSiteDetails' . "\n";
    }

    public function testGetSiteRealtime()
    {
        $siteId = 123;
        $url = "https://example.com/installations/$siteId/diagnostics";
        $expectedResponse = 'some data';

        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        $result = $this->victronEnergyService->getSiteRealtime($siteId);
        $this->assertEquals($expectedResponse, $result);
        echo json_encode($result);
        echo "\n" . 'testGetSiteRealtime' . "\n";
    }

    public function testGetAllPlants()
    {
        $page = 1;
        $pageSize = 200;
        $installationId = 456;
        $url = "https://example.com/users/$installationId/installations?extended=1";
        $expectedResponse = json_encode(['data' => 'some data']);

        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->victronEnergyMock->method('getIdInstallation')->willReturn($installationId);
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        $result = $this->victronEnergyService->getAllPlants($page, $pageSize);
        $this->assertEquals(json_decode($expectedResponse, true), $result);
        echo json_encode($result);
        echo "\n" . 'testGetAllPlants' . "\n";
    }
}
?>