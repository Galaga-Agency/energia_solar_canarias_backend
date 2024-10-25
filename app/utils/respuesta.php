<?php

class Paginacion
{
    private $currentPage;
    private $perPage;
    private $totalItems;
    private $totalPages;
    private $nextPageUrl;
    private $previousPageUrl;

    // Constructor para inicializar las propiedades
    public function __construct($currentPage = 1, $perPage = 200, $totalItems = 0, $totalPages = 0, $nextPageUrl = '', $previousPageUrl = '') {
        $this->currentPage = $currentPage;
        $this->perPage = $perPage;
        $this->totalItems = $totalItems;
        $this->totalPages = $totalPages;
        $this->nextPageUrl = $nextPageUrl;
        $this->previousPageUrl = $previousPageUrl;
    }

    // Getter y setter para 'currentPage'
    public function getCurrentPage() {
        return $this->currentPage;
    }

    public function setCurrentPage($currentPage) {
        $this->currentPage = $currentPage;
    }

    // Getter y setter para 'perPage'
    public function getPerPage() {
        return $this->perPage;
    }

    public function setPerPage($perPage) {
        $this->perPage = $perPage;
    }

    // Getter y setter para 'totalItems'
    public function getTotalItems() {
        return $this->totalItems;
    }

    public function setTotalItems($totalItems) {
        $this->totalItems = $totalItems;
    }

    // Getter y setter para 'totalPages'
    public function getTotalPages() {
        return $this->totalPages;
    }

    public function setTotalPages($totalPages) {
        $this->totalPages = $totalPages;
    }

    // Getter y setter para 'nextPageUrl'
    public function getNextPageUrl() {
        return $this->nextPageUrl;
    }

    public function setNextPageUrl($nextPageUrl) {
        $this->nextPageUrl = $nextPageUrl;
    }

    // Getter y setter para 'previousPageUrl'
    public function getPreviousPageUrl() {
        return $this->previousPageUrl;
    }

    public function setPreviousPageUrl($previousPageUrl) {
        $this->previousPageUrl = $previousPageUrl;
    }
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

    public function success($datos = [], $pagination = null)
    {
        $this->status = 'success';
        $this->code = 200;
        $this->message = '200 - Solicitud exitosa';
        $this->data = $datos;
        if ($pagination) {
            $this->pagination->setCurrentPage($pagination['currentPage']);
            $this->pagination->setPerPage($pagination['perPage']);
            $this->pagination->setTotalItems($pagination['totalItems']);
            $this->pagination->setTotalPages($pagination['totalPages']);
            $this->pagination->setNextPageUrl($pagination['nextPageUrl']);
            $this->pagination->setPreviousPageUrl($pagination['previousPageUrl']);
        }
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
