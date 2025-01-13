<?php

require_once __DIR__ . "/../models/login.php";
require_once __DIR__ . "/../utils/token.php";
require_once __DIR__ . "/../models/insert_token.php";
require_once __DIR__ . "/../services/correo.php";
require_once __DIR__ . "/../DBObjects/usuariosDB.php";
require_once __DIR__ . "/../models/conexion.php";


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

    public function userPasswordRecover()
    {
        $usuarioDB = new UsuariosDB;
        $correo = new Correo;
        try {
            if ($usuarioDB->comprobarUsuario($this->datos['email'])) {
                $usuarioId = $usuarioDB->getIdUserPorEmail($this->datos['email']);
                $jwt = Conexion::jwtVolatil($usuarioId, $this->datos['email']);
                $usuario = $usuarioDB->getUser($usuarioId['usuario_id']);
                $respuesta = $correo->recuperarContrasena($usuario, $jwt, $this->idiomaUsuario);
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            } else {
                $respuesta = new Respuesta;
                $respuesta->_404();
                $respuesta->message = "404 - El usuario no existe";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            }
        } catch (Exception $e) {
            $respuesta = new Respuesta;
            $respuesta->_500($e);
            $respuesta->message = "500 - Error al recuperar la contraseña";
            http_response_code($respuesta->code);
            echo json_encode($respuesta);
        }
    }
    //Cambia la contraseña del usuario con un token de autentificacion
    public function changePasswordUser($datos)
    {
        $usuarioDB = new UsuariosDB;
        try {
            $jwt = Conexion::verifyJwtVolatil($datos['token']);
            if($jwt == false){
                $respuesta = new Respuesta;
                $respuesta->_401();
                $respuesta->message = "401 - Token inválido o expirado";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
                return;
            }
            if ($usuarioDB->comprobarUsuario($jwt->data->email)) {
                $usuarioId = $usuarioDB->getIdUserPorEmail($jwt->data->email);
                $contrasenaCambiada = $usuarioDB->putUserPassword($usuarioId['usuario_id'], $this->datos['password']);
                if($contrasenaCambiada){
                    $respuesta = new Respuesta;
                    $respuesta->success();
                    $respuesta->message = "200 - La contraseña ha sido cambiada con éxito";
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }else{
                    $respuesta = new Respuesta;
                    $respuesta->_500();
                    $respuesta->message = "500 - Algo salió mal al cambiar la contraseña";
                    http_response_code($respuesta->code);
                    echo json_encode($respuesta);
                }
            } else {
                $respuesta = new Respuesta;
                $respuesta->_404();
                $respuesta->message = "404 - El usuario no existe";
                http_response_code($respuesta->code);
                echo json_encode($respuesta);
            }
        } catch (Exception $e) {
            $respuesta = new Respuesta;
            $respuesta->_500($e);
            $respuesta->message = "500 - Error al recuperar la contraseña " . $e->getMessage();
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
