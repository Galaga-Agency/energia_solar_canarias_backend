<?php

/**
 * Un proveedor ha respondido con un error de negocio.
 *
 * No es lo mismo que OperacionNoSoportada (eso es "esta API no hace eso"): esto es
 * "lo intente y me dijo que no". Por ejemplo Sigenergy con "Station not permitted".
 *
 * Existe porque cada API señala los fallos a su manera y eso NO puede quedarse en la
 * fachada. Sigenergy responde SIEMPRE HTTP 200 y mete el error real en un campo `code`
 * del cuerpo; antes eso se traducia en ApiControladorService::fallaSigenergy(), a la
 * que habia que acordarse de llamar endpoint por endpoint. Cuando se movio el router
 * al contrato, los endpoints que no la llamaban volvieron a devolver los fallos como
 * exitos.
 *
 * Ahora la rareza vive en el adaptador del proveedor: el traduce su formato a esta
 * excepcion y la fachada la convierte en respuesta HTTP, en un unico sitio. Ya no hay
 * nada que "acordarse de llamar".
 */
class ErrorProveedor extends RuntimeException
{
    private $http;
    private $detalles;

    /**
     * @param string $mensaje  para el usuario final, en castellano
     * @param int    $http     codigo con el que debemos responder
     * @param array  $detalles contexto para depurar (codigo original, causa...)
     */
    public function __construct(string $mensaje, int $http = 502, array $detalles = [])
    {
        parent::__construct($mensaje);
        $this->http = $http;
        $this->detalles = $detalles;
    }

    public function http(): int
    {
        return $this->http;
    }

    public function detalles(): array
    {
        return $this->detalles;
    }
}
