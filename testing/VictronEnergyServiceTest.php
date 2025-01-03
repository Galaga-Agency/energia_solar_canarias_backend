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

        // Se crea el mock de VictronEnergyService y se inyectan las dependencias
        $this->victronEnergyService = $this->createMock(VictronEnergyService::class);
        $this->victronEnergyService->setHttpClient($this->httpClientMock);
        $this->victronEnergyService->setVictronEnergy($this->victronEnergyMock);
    }

    public function testGetSiteEquipo()
    {
        $siteId = 123;
        $url = "https://example.com/installations/$siteId/system-overview";
        $expectedResponse = json_encode(['data' => 'some data']);

        // Configuramos los mocks
        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        // Simulamos la respuesta del método getSiteEquipo
        $this->victronEnergyService->method('getSiteEquipo')->willReturn(json_decode($expectedResponse, true));

        // Ejecutamos la prueba
        $result = $this->victronEnergyService->getSiteEquipo($siteId);

        // Verificamos que el resultado es el esperado
        $this->assertEquals(json_decode($expectedResponse, true), $result);
    }

    public function testGetSiteAlarms()
    {
        $siteId = 123;
        $pageIndex = 1;
        $pageSize = 200;
        $url = "https://example.com/installations/$siteId/alarm-log?page=$pageIndex&count=$pageSize";
        $expectedResponse = json_encode(['data' => 'some data']);

        // Configuramos los mocks
        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        // Simulamos la respuesta del método getSiteAlarms
        $this->victronEnergyService->method('getSiteAlarms')->willReturn(json_decode($expectedResponse, true));

        // Ejecutamos la prueba
        $result = $this->victronEnergyService->getSiteAlarms($siteId, $pageIndex, $pageSize);

        // Verificamos que el resultado es el esperado
        $this->assertEquals(json_decode($expectedResponse, true), $result);
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

        // Configuramos los mocks
        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        // Simulamos la respuesta del método getGraficoDetails
        $this->victronEnergyService->method('getGraficoDetails')->willReturn(json_decode($expectedResponse, true));

        // Ejecutamos la prueba
        $result = $this->victronEnergyService->getGraficoDetails($siteId, $timeStart, $timeEnd, $type, $interval);

        // Verificamos que el resultado es el esperado
        $this->assertEquals(json_decode($expectedResponse, true), $result);
    }

    public function testGetSiteDetails()
    {
        $siteId = 123;
        $installationId = 456;
        $url = "https://example.com/users/$installationId/installations?idSite=$siteId&extended=1";
        $expectedResponse = 'some data';

        // Configuramos los mocks
        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->victronEnergyMock->method('getIdInstallation')->willReturn($installationId);
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        // Simulamos la respuesta del método getSiteDetails
        $this->victronEnergyService->method('getSiteDetails')->willReturn($expectedResponse);

        // Ejecutamos la prueba
        $result = $this->victronEnergyService->getSiteDetails($siteId);

        // Verificamos que el resultado es el esperado
        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetSiteRealtime()
    {
        $siteId = 123;
        $url = "https://example.com/installations/$siteId/diagnostics";
        $expectedResponse = 'some data';

        // Configuramos los mocks
        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        // Simulamos la respuesta del método getSiteRealtime
        $this->victronEnergyService->method('getSiteRealtime')->willReturn($expectedResponse);

        // Ejecutamos la prueba
        $result = $this->victronEnergyService->getSiteRealtime($siteId);

        // Verificamos que el resultado es el esperado
        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetAllPlants()
    {
        $page = 1;
        $pageSize = 200;
        $installationId = 456;
        $url = "https://example.com/users/$installationId/installations?extended=1";
        $expectedResponse = json_encode(['data' => 'some data']);

        // Configuramos los mocks
        $this->victronEnergyMock->method('getUrl')->willReturn('https://example.com/');
        $this->victronEnergyMock->method('getIdInstallation')->willReturn($installationId);
        $this->httpClientMock->method('get')->with($url, $this->anything())->willReturn($expectedResponse);

        // Simulamos la respuesta del método getAllPlants
        $this->victronEnergyService->method('getAllPlants')->willReturn(json_decode($expectedResponse, true));

        // Ejecutamos la prueba
        $result = $this->victronEnergyService->getAllPlants($page, $pageSize);

        // Verificamos que el resultado es el esperado
        $this->assertEquals(json_decode($expectedResponse, true), $result);
    }
}
?>
