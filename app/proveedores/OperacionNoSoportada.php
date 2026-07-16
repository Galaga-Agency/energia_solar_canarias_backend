<?php

/**
 * Un proveedor no ofrece esta operacion.
 *
 * No es un error: es informacion. SolarEdge no tiene alertas y GoodWe no tiene
 * beneficios, y eso es asi por como son sus APIs, no porque algo haya fallado.
 *
 * Antes esto vivia repartido en `case`s del router que respondian 404 a mano
 * ("No hay Alertas en la planta de SolarEdge"). Ahora cada proveedor lo declara en su
 * propia clase, no implementando el metodo, y la fachada lo traduce a 404 en un unico
 * sitio. La diferencia esta en que ya no se puede olvidar un caso: si un proveedor no
 * lo soporta, lo dice el, no el router.
 */
class OperacionNoSoportada extends RuntimeException
{
    private $proveedor;
    private $operacion;

    public function __construct(string $proveedor, string $operacion)
    {
        $this->proveedor = $proveedor;
        $this->operacion = $operacion;
        parent::__construct("$proveedor no ofrece '$operacion'");
    }

    public function proveedor(): string
    {
        return $this->proveedor;
    }

    public function operacion(): string
    {
        return $this->operacion;
    }
}
