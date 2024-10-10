<?php

class Respuesta
{
    public $status;
    public $code;
    public $message;
    public $data;

    public function success ($datos)
    {
        $this->status = 'success';
        $this->code = 200;
        $this->message = '200 - Solicitud exitosa';
        $this->data = $datos;
    }

}

class Errores
{
    public $status;
    public $code;
    public $message;
    public $errors;
    
    public function _400 ($errores)
    {
        $this->status = 'error';
        $this->code = 400;
        $this->message = '400 - Datos incompletos o incorrectos en la solicitud';
        $this->errors = $errores;
    }

    public function _401 ($errores)
    {
        $this->status = 'error';
        $this->code = 401;
        $this->message = '401 - No autorizado';
        $this->errors = $errores;
    }

    public function _405 ($errores)
    {
        $this->status = 'error';
        $this->code = 405;
        $this->message = '405 - Método no permitido';
        $this->errors = $errores;
    }

    public function _500 ($errores)
    {
        $this->status = 'error';
        $this->code = 500;
        $this->message = '500 - Error interno en el servidor o en la API';
        $this->errors = $errores;
    }

}
