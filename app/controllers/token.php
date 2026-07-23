<?php

require_once __DIR__ . '/../models/valid_token.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../controllers/LogsController.php';
require_once __DIR__ . '/../DBObjects/intentosLoginDB.php';
require_once __DIR__ . '/../utils/seguridad.php';


class TokenController
{
    private $datos;
    private $token;
    private $id;
    private $validToken;
    public $respuesta;

    function __construct($datos)
    {
        $this->datos = json_decode($datos, true);
        $this->id = $this->datos['id'];
        $this->token = $this->datos['token'];
        $this->validToken = new ValidToken;
        $this->respuesta = new Respuesta;
    }

    public function validarToken()
    {
        if (isset($this->datos['id']) && isset($this->datos['token'])) {
            $ip = ipCliente();
            $identificador = 'user:' . $this->id;
            $intentos = new IntentosLoginDB();

            // Anti fuerza bruta sobre el codigo enviado por email (A.8.5).
            if ($intentos->estaBloqueado($identificador, $ip)) {
                registrarEventoSeguridad('codigo_bloqueado', 'id=' . $this->id);
                $respuesta = new Respuesta;
                $respuesta->_429();
                $respuesta->message = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                return;
            }

            $responseValidToken = $this->validToken->execute($this->id, $this->token);
            if (isset($responseValidToken->status) && $responseValidToken->status) {
                $intentos->limpiar($identificador, $ip);
                registrarEventoSeguridad('codigo_ok', 'id=' . $this->id);
            } else {
                $intentos->registrarFallo($identificador, $ip);
                registrarEventoSeguridad('codigo_fallido', 'id=' . $this->id);
            }
            http_response_code($responseValidToken->code);
            echo json_encode($responseValidToken);
        } else {
            $respuesta = new Respuesta;
            $respuesta->_400();
            $respuesta->message = 'Error en el controlador token de la API, no se ha recibido la información requerida en la solicitud: id, token';
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }
    }
}
