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
        // Definir los valores predeterminados de paginación
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;

        // Instanciar el objeto de acceso a la base de datos
        $usuariosDB = new UsuariosDB();

        // Obtener usuarios con paginación
        $usuarios = $usuariosDB->getUsers($page, $limit);

        // Verificar si se obtuvo un resultado
        if ($usuarios !== false) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $usuarios,
                'page' => $page,
                'limit' => $limit
            ]);
        } else {
            // Si hubo un error al obtener los usuarios
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener usuarios.'
            ]);
        }
    }


    public function consultarAdmin()
    {
        // Ejemplo de uso
        $usuariosDB = new UsuariosDB();
        $usuarios = $usuariosDB->getUsers();
        $response = $this->usuarios->getAllUsers();
        http_response_code($response->code);
        echo json_encode($usuarios);
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
