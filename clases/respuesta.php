<?php

class Paginacion
{
    public $currentPage;
    public $perPage = 200;
    public $totalItems;
    public $totalPages;
    public $nextPageUrl;
    public $previousPageUrl;
}

class Respuesta
{
    public $status;
    public $code = 200;
    public $message;
    public $data;
    public $pagination;

    function __construct()
    {
        $this->pagination = new Paginacion;
    }

    public function success($datos,$pagination)
    {
        $this->status = 'success';
        $this->code = 200;
        $this->message = '200 - Solicitud exitosa';
        $this->data = $datos;
        $this->pagination->currentPage = $pagination['currentPage'];
        $this->pagination->perPage = $pagination['perPage'];
        $this->pagination->totalItems = $pagination['totalItems'];
        $this->pagination->totalPages = $pagination['totalPages'];
        $this->pagination->nextPageUrl = $pagination['nextPageUrl'];
        $this->pagination->previousPageUrl = $pagination['previousPageUrl'];
    }
}

class Errores
{
    public $status;
    public $code;
    public $message;
    public $errors;

    public function _400($errores = [])
    {
        $this->status = 'error';
        $this->code = 400;
        $this->message = '400 - Datos incompletos o incorrectos en la solicitud';
        $this->errors = $errores;
    }

    public function _401($errores = [])
    {
        $this->status = 'error';
        $this->code = 401;
        $this->message = '401 - No autorizado';
        $this->errors = $errores;
    }

    public function _405($errores = [])
    {
        $this->status = 'error';
        $this->code = 405;
        $this->message = '405 - Método no permitido';
        $this->errors = $errores;
    }

    public function _500($errores = [])
    {
        $this->status = 'error';
        $this->code = 500;
        $this->message = '500 - Error interno en el servidor o en la API';
        $this->errors = $errores;
    }
}
