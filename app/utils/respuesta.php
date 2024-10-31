<?php

class Respuesta
{
    public $status;
    public $code = 200;
    public $message;
    public $data;

    public function __construct() {}

    public function success($datos = [])
    {
        $this->status = true;
        $this->code = 200;
        $this->message = '200 - Solicitud exitosa';
        $this->data = $datos;
    }

    public function _400($errores = [])
    {
        $this->status = false;
        $this->code = 400;
        $this->message = '400 - Datos incompletos o incorrectos en la solicitud';
        $this->data = $errores;
    }

    public function _401($errores = [])
    {
        $this->status = false;
        $this->code = 401;
        $this->message = '401 - No autorizado';
        $this->data = $errores;
    }

    public function _403($errores = [])
    {
        $this->status = false;
        $this->code = 403;
        $this->message = '403 - Operación no autorizada. No eres administrador.';
        $this->data = $errores;
    }

    public function _404($errores = [])
    {
        $this->status = false;
        $this->code = 404;
        $this->message = '404 - Los datos de la peticion no han sido encontrados';
        $this->data = $errores;
    }

    public function _405($errores = [])
    {
        $this->status = false;
        $this->code = 405;
        $this->message = '405 - Método no permitido';
        $this->data = $errores;
    }

    public function _409($errores = [])
    {
        $this->status = false;
        $this->code = 409;
        $this->message = '409 - Ya registrado';
        $this->data = $errores;
    }

    public function _500($errores = [])
    {
        $this->status = false;
        $this->code = 500;
        $this->message = '500 - Error interno en el servidor o en la API';
        $this->data = $errores;
    }
}
class Paginacion extends Respuesta
{
    public $page;
    public $limit;

    public function __construct($page = 1, $limit = 200)
    {
        parent::__construct();
        $this->page = $page;
        $this->limit = $limit;
    }
}
