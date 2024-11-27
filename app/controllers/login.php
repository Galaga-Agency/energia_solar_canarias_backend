<?php

require_once "../models/login.php";
require_once "../utils/token.php";
require_once "../models/insert_token.php";
require_once "../services/correo.php";

class LoginController
{
    private $login;
    private $datos;
    private $token;
    private $insertToken;
    private $correo;
    private $dataUsuario;
    private $idiomaUsuario;

    function __construct($datos)
    {
        $this->datos = json_decode($datos, true);
        $this->login = new Login($this->datos);
        $this->correo = new Correo;
        if (isset($this->datos['idiomaUsuario'])) {
            $this->idiomaUsuario = $this->datos['idiomaUsuario'];
        } else {
            $this->idiomaUsuario = 'es';
        }
    }

    public function userLogin()
    {
        $responseLogin = $this->login->userLogin();
        if ($responseLogin->status) {
            $this->dataUsuario = $responseLogin->data;
            $this->token = new Token;
            $this->insertToken = new InsertToken($this->dataUsuario, $this->token->value, $this->token->timeCreated);
            $responseInsertToken = $this->insertToken->execute();

            /*
            //PRUEBAS
            http_response_code($responseInsertToken->code);
            echo json_encode($responseInsertToken);
            */

            if ($responseInsertToken->status) {
                $token = new Token();
                $tokenUser = $token->getLastTokenUser($this->dataUsuario['id']);
                $this->dataUsuario['tokenLogin'] = $tokenUser['token_login'];
                $this->dataUsuario['timeTokenLogin'] = $tokenUser['time_token_login'];
                $responseCorreo = $this->correo->login($this->dataUsuario, $this->token->value, $this->idiomaUsuario,);
                http_response_code($responseCorreo->code);
                echo json_encode($responseCorreo);
            } else {
                http_response_code($responseInsertToken->code);
                echo json_encode($responseInsertToken);
            }
        } else {
            http_response_code($responseLogin->code);
            echo json_encode($responseLogin);
        }
    }
}

/*
//PRUEBAS
$postBody = '{"email": "soporte@galagaagency.com","password": "Galaga2024!","idiomaUsuario": "es"}';
$loginController = new LoginController($postBody);
$loginController->userLogin();
*/
