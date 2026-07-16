<?php

class Respuesta
{
    public $status;
    public $code = 200;
    public $message;
    public $data;

    public function __construct() {}

    /**
     * Pone el codigo TAMBIEN en la respuesta HTTP, no solo en el cuerpo.
     *
     * Antes esto no se hacia aqui: $respuesta->_409() rellenaba el objeto y el
     * llamante tenia que acordarse ademas de http_response_code(409). En 28 sitios
     * no se acordaba, asi que salia un HTTP 200 con {"code":409} dentro.
     *
     * Y eso no es cosmetico: los clientes miran el codigo HTTP. El frontend hace
     * `if (!response.ok) throw ...`, y con un 200 daba el error por bueno. Asi es
     * como se podia crear un usuario con un email repetido: el backend contestaba
     * "El email ya esta registrado" y el navegador lo tomaba por un exito.
     *
     * Si las cabeceras ya salieron, PHP lo ignora sin ruido: no rompe nada.
     */
    private function aplicarHttp()
    {
        if (!headers_sent()) {
            http_response_code($this->code);
        }
    }

    public function success($datos = [])
    {
        $this->status = true;
        $this->code = 200;
        $this->message = '200 - Solicitud exitosa';
        $this->data = $datos;
        // Tambien en el exito: si antes se llamo a un _4xx() sobre este objeto, el
        // codigo HTTP ya estaria puesto y hay que devolverlo a 200.
        $this->aplicarHttp();
    }

    public function _400($errores = [])
    {
        $this->status = false;
        $this->code = 400;
        $this->message = '400 - Datos incompletos o incorrectos en la solicitud';
        $this->data = $errores;
        $this->aplicarHttp();
    }

    public function _401($errores = [])
    {
        $this->status = false;
        $this->code = 401;
        $this->message = '401 - No autorizado';
        $this->data = $errores;
        $this->aplicarHttp();
    }

    public function _403($errores = [])
    {
        $this->status = false;
        $this->code = 403;
        $this->message = '403 - Operación no autorizada. No eres administrador.';
        $this->data = $errores;
        $this->aplicarHttp();
    }

    public function _404($errores = [])
    {
        $this->status = false;
        $this->code = 404;
        $this->message = '404 - Los datos de la peticion no han sido encontrados';
        $this->data = $errores;
        $this->aplicarHttp();
    }

    public function _405($errores = [])
    {
        $this->status = false;
        $this->code = 405;
        $this->message = '405 - Método no permitido';
        $this->data = $errores;
        $this->aplicarHttp();
    }

    public function _409($errores = [])
    {
        $this->status = false;
        $this->code = 409;
        $this->message = '409 - Ya registrado';
        $this->data = $errores;
        $this->aplicarHttp();
    }

    public function _500($errores = [])
    {
        $this->status = false;
        $this->code = 500;
        $this->message = '500 - Error interno en el servidor o en la API';
        $this->data = $errores;
        $this->aplicarHttp();
    }
}
class Paginacion extends Respuesta
{
    public $page;
    public $limit;

    /**
     * Info de cache/rate-limit del proveedor, cuando aplica (hoy solo Sigenergy, que
     * limita la lista a 1 acceso cada 5 min por cuenta). Queda null en los proveedores
     * sin limite, y asi el frontend sabe si el dato es fresco y cuando repreguntar.
     * Ver CacheApiService.
     */
    public $cache = null;

    public function __construct($page = 1, $limit = 200)
    {
        parent::__construct();
        $this->page = $page;
        $this->limit = $limit;
    }
}
