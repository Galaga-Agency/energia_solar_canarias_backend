<?php

require_once "../models/valid_token.php";
require_once "../utils/respuesta.php";


class TokenController
{
    private $datos;
    private $token;
    private $dataUsuario;
    private $id;
    private $validToken;
    public $error;
    public $respuesta;

    function __construct($datos)
    {
        $this->datos = json_decode($datos, true);
        $this->id = $this->datos['id'];
        $this->token = $this->datos['token'];
        $this->validToken = new ValidToken;
        $this->error = new Errores;
        $this->respuesta = new Respuesta;
    }

    public function validarToken()
    {
        if (isset($this->datos['id']) && isset($this->datos['token'])) {
            $responseValidToken = $this->validToken->execute($this->id, $this->token);
            http_response_code($responseValidToken->code);
            echo json_encode($responseValidToken);
        } else {
            $this->error->_400();
            $this->error->message = 'Error en el controlador token de la API, no se ha recibido la información requerida en la solicitud: id, token';
            http_response_code($this->error->code);
            echo json_encode($this->error);
        }
    }
}

/*
//PRUEBAS
$postBody = '{"email": "soporte@galagaagency.com","password": "***REMOVED***","idiomaUsuario": "es"}';
$loginController = new LoginController($postBody);
$loginController->userLogin();
*/
