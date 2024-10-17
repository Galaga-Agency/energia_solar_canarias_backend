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
        if ($responseLogin->status == "success") {
            $this->dataUsuario = $responseLogin->data;
            $this->token = new Token;
            $this->insertToken = new InsertToken($this->dataUsuario, $this->token->value, $this->token->timeCreated);
            $responseInsertToken = $this->insertToken->execute();
            if ($responseInsertToken->status == "success") {
                $responseCorreo = $this->correo->login($this->dataUsuario, $this->idiomaUsuario);
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
$users = new UsuariosController;
$users->getAllUsers();
*/
