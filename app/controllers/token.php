<?php

require_once "../models/valid_token.php";
require_once "../utils/respuesta.php";
require_once "../controllers/LogsController.php";


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
            $responseValidToken = $this->validToken->execute($this->id, $this->token);
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

/*
//PRUEBAS
$postBody = '{"email": "soporte@galagaagency.com","password": "Galaga2024!","idiomaUsuario": "es"}';
$loginController = new LoginController($postBody);
$loginController->userLogin();
*/
