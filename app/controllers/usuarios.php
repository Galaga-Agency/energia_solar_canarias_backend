<?php

require_once "../models/usuarios.php";

class UsuariosController
{
    private $usuarios;

    function __construct()
    {
        $this->usuarios = new Usuarios;
    }

    public function getAllUsers()
    {
        $response = $this->usuarios->getAllUsers();
        http_response_code($response->code);
        echo json_encode($response);
    }


    public function getUser($id)
    {
        $response = $this->usuarios->getUser($id);
        http_response_code($response->code);
        echo json_encode($response);
    }
}

/*
//PRUEBAS
$users = new UsuariosController;
$users->getAllUsers();
*/
