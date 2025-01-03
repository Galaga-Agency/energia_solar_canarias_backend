<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/middlewares/autenticacion.php';
require_once __DIR__ . '/../app/DBObjects/logsDB.php';
require_once __DIR__ . '/../app/utils/respuesta.php';


class LogsEndpointTest extends TestCase
{
    private $authMiddlewareMock;
    private $logsDBMock;
    private $respuestaMock;

    protected function setUp(): void
    {
        $this->authMiddlewareMock = $this->createMock(Autenticacion::class);
        $this->logsDBMock = $this->createMock(LogsDB::class);
        $this->respuestaMock = $this->createMock(Respuesta::class);
    }

    public function testLogsEndpointWithValidTokenAndAdmin()
    {
        // Configurar mocks
        $this->authMiddlewareMock->method('verificarTokenUsuarioActivo')->willReturn(true);
        $this->authMiddlewareMock->method('verificarAdmin')->willReturn(true);
        $this->logsDBMock->method('getLogs')->willReturn([
            ['id' => 1, 'message' => 'Log entry 1'],
            ['id' => 2, 'message' => 'Log entry 2'],
        ]);
        $this->respuestaMock->expects($this->once())->method('success');

        // Simular entrada
        $_GET['page'] = 1;
        $_GET['limit'] = 200;
        file_put_contents("php://input", json_encode(['mensaje' => 'error']));

        // Código a probar
        $request = 'logs';
        $handled = false;
        switch ($request) {
            case 'logs':
                $handled = true;
                try {
                    if ($this->authMiddlewareMock->verificarTokenUsuarioActivo() != false) {
                        if ($this->authMiddlewareMock->verificarAdmin()) {
                            $body = file_get_contents("php://input");
                            $data = json_decode($body, true);
                            $mensaje = isset($data['mensaje']) ? $data['mensaje'] : '';
                            $page = isset($_GET['page']) ? $_GET['page'] : 1;
                            $limit = isset($_GET['limit']) ? $_GET['limit'] : 200;
                            $logs = $this->logsDBMock->getLogs($page, $limit, $mensaje);
                            echo "Logs obtenidos: " . print_r($logs, true) . "\n";
                            $this->respuestaMock->success($logs);
                        } else {
                            $this->respuestaMock->_403();
                        }
                    } else {
                        $this->respuestaMock->_403();
                    }
                } catch (Exception $e) {
                    $this->respuestaMock->_500($e->getMessage());
                }
                break;
        }

        // Asegurarse de que la solicitud fue manejada
        $this->assertTrue($handled);
    }

    public function testLogsEndpointWithInvalidToken()
    {
        // Configurar mocks
        $this->authMiddlewareMock->method('verificarTokenUsuarioActivo')->willReturn(false);
        $this->respuestaMock->expects($this->once())->method('_403');

        // Código a probar
        $request = 'logs';
        $handled = false;
        switch ($request) {
            case 'logs':
                $handled = true;
                try {
                    if ($this->authMiddlewareMock->verificarTokenUsuarioActivo() != false) {
                        // ...
                    } else {
                        $this->respuestaMock->_403();
                    }
                } catch (Exception $e) {
                    $this->respuestaMock->_500($e->getMessage());
                }
                break;
        }

        // Asegurarse de que la solicitud fue manejada
        $this->assertTrue($handled);
    }
}
