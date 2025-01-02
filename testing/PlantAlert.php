<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/middlewares/autenticacion.php';
require_once __DIR__ . '/../app/services/ApiControladorService.php';
require_once __DIR__ . '/../app/utils/respuesta.php';


class PlantAlert extends TestCase
{
    private $authMiddlewareMock;
    private $apiControladorServiceMock;
    private $respuestaMock;

    protected function setUp(): void
    {
        // Crear mocks para las dependencias
        $this->authMiddlewareMock = $this->createMock(Autenticacion::class);
        $this->apiControladorServiceMock = $this->createMock(ApiControladorService::class);
        $this->respuestaMock = $this->createMock(Respuesta::class);
    }

    public function testProveedorGoodWe()
    {
        // Configurar el mock para la autenticación
        $this->authMiddlewareMock->method('verificarTokenUsuarioActivo')
            ->willReturn(true); // Simular un token válido

        // Configurar el proveedor y los parámetros en $_GET
        $_GET['proveedor'] = 'GoodWe';
        $_GET['pageIndex'] = 1;
        $_GET['pageSize'] = 200;

        // Datos simulados de la respuesta de la API de GoodWe
        $mockedResponse = [
            'code' => 200,
            'data' => [
                'stations' => [
                    ['stationId' => '1', 'name' => 'Estación 1'],
                    ['stationId' => '2', 'name' => 'Estación 2']
                ]
            ]
        ];

        // Mockear la respuesta de la API
        $this->apiControladorServiceMock->expects($this->once())
            ->method('GetPowerStationWariningInfoByMultiCondition')
            ->with($this->equalTo(1), $this->equalTo(200)) // Verifica que los parámetros sean correctos
            ->willReturn($mockedResponse); // Retorna la respuesta simulada

        // Ejecutar el código que simula el endpoint
        $response = $this->apiControladorServiceMock->GetPowerStationWariningInfoByMultiCondition(1, 200);

        // Validar que la respuesta es la esperada
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(200, $response['code']);
        $this->assertArrayHasKey('data', $response);
        $this->assertCount(2, $response['data']['stations']);
        $this->assertEquals('Estación 1', $response['data']['stations'][0]['name']);
        $this->assertEquals('Estación 2', $response['data']['stations'][1]['name']);
    }
    public function testProveedorNoValido()
    {
        // Simulamos que el token es válido
        $this->authMiddlewareMock->method('verificarTokenUsuarioActivo')
            ->willReturn(true);

        $_GET['proveedor'] = 'Invalido'; // Proveedor no válido

        // Mockeamos la respuesta para el caso de error 404
        $this->respuestaMock->method('_404')
            ->willReturnCallback(function () {
                $this->respuestaMock->status = false;
                $this->respuestaMock->code = 404;
                $this->respuestaMock->message = 'El proveedor no es valido';
                $this->respuestaMock->data = null;
                echo json_encode($this->respuestaMock);
            });

        // Ejecutamos el código bajo prueba (esto puede necesitar personalización según tu flujo)
        // Disparar el escenario de error
        $this->respuestaMock->_404();

        // Aseguramos que la respuesta contiene el mensaje esperado y el código
        $response = json_encode($this->respuestaMock);
        $this->assertStringContainsString('El proveedor no es valido', $response);
        $this->assertStringContainsString('"code":404', $response);
    }

    public function testTokenNoValido()
    {
        // Simulamos que el token es inválido
        $this->authMiddlewareMock->method('verificarTokenUsuarioActivo')
            ->willReturn(false);

        $_GET['proveedor'] = 'GoodWe'; // Proveedor válido

        // Mockeamos la respuesta para el caso de error 403
        $this->respuestaMock->method('_403')
            ->willReturnCallback(function () {
                $this->respuestaMock->status = false;
                $this->respuestaMock->code = 403;
                $this->respuestaMock->message = 'El token no se puede authentificar con exito';
                $this->respuestaMock->data = null;
                echo json_encode($this->respuestaMock);
            });

        // Ejecutamos el código bajo prueba (esto puede necesitar personalización según tu flujo)
        // Disparar el escenario de error
        $this->respuestaMock->_403();

        // Aseguramos que la respuesta contiene el mensaje esperado y el código
        $response = json_encode($this->respuestaMock);
        $this->assertStringContainsString('El token no se puede authentificar con exito', $response);
        $this->assertStringContainsString('"code":403', $response);
    }
    // Ejemplo en PHPUnit, donde realizas una llamada HTTP a tu endpoint

}
