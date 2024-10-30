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

    public function crearUser() {
        // Obtener el JSON desde el cuerpo de la solicitud
        $postBody = file_get_contents("php://input");
        $data = json_decode($postBody, true); // Decodificar el JSON en un array asociativo
    
        // Validar que los datos requeridos existan en el JSON
        if (isset($data['email'], $data['password'], $data['clase'], $data['nombre'], $data['apellido'], $data['imagen'], $data['movil'], $data['activo'], $data['eliminado'])) {
            // Instancia de la base de datos
            $usuariosDB = new UsuariosDB();
            if(!$usuariosDB->comprobarClaseExiste($data['clase'])){
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El nombre de la clase no existe'
                    ]);
                return;
            }
            //Llamara a la funcion para verificar si el email del usuario ya existe en la base de datos ! se pone para que si no esta registrado pase el filtro
            if(!$usuariosDB->comprobarUsuario($data['email'])){
            // Llamar a la función para crear el usuario en la base de datos
            $result = $usuariosDB->insertUser($data);
            }else{
                http_response_code(409);
                echo json_encode(["success" => false, "message" => "El email ya está registrado."]);
                return;
            }
            if ($result) {
                http_response_code(201); // Código de creado
                echo json_encode(["success" => true, "message" => "Usuario creado exitosamente."]);
            } else {
                http_response_code(500); // Error de servidor
                echo json_encode(["success" => false, "message" => "Error al crear el usuario."]);
            }
        } else {
            // Respuesta si faltan datos en el JSON
            http_response_code(400); // Error de solicitud
            echo json_encode(["success" => false, "message" => "Datos incompletos en la solicitud."]);
        }
    }

    public function actualizarUser($id) {
        // Obtener el JSON desde el cuerpo de la solicitud
        $postBody = file_get_contents("php://input");
        $data = json_decode($postBody, true); // Decodificar el JSON en un array asociativo
    
        // Validar que los datos requeridos existan en el JSON
        if (isset($data['email'], $data['password'], $data['clase'], $data['nombre'], $data['apellido'], $data['imagen'], $data['movil'], $data['activo'], $data['eliminado'])) {
            // Instancia de la base de datos
            $usuariosDB = new UsuariosDB();
            if(!$usuariosDB->comprobarClaseExiste($data['clase'])){
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El nombre de la clase no existe'
                    ]);
                return;
            }
            if($usuariosDB->verificarEstadoUsuario($id)){
            // Verificar si el email pertenece a otro usuario
            if (!$usuariosDB->comprobarUsuario($data['email']) || $usuariosDB->esMismoUsuario($id, $data['email'])) {
                // Llamar a la función para actualizar el usuario en la base de datos
                $result = $usuariosDB->updateUser($id, $data);
                
                if ($result) {
                    http_response_code(200); // Código de éxito
                    echo json_encode(["success" => true, "message" => "Usuario actualizado exitosamente."]);
                } else {
                    http_response_code(500); // Error de servidor
                    echo json_encode(["success" => false, "message" => "Error al actualizar el usuario."]);
                }
            } else {
                http_response_code(409);
                echo json_encode(["success" => false, "message" => "El email ya está registrado en otro usuario."]);
            }
        } else {
            // Respuesta si faltan datos en el JSON
            http_response_code(400); // Error de solicitud
            echo json_encode(["success" => false, "message" => "Datos incompletos en la solicitud."]);
        }
    }else{
        http_response_code(404); // Código de éxito
        echo json_encode(["success" => false, "message" => "El usuario no se a encontrado"]);
    }
    }

    public function eliminarUser($id) {
        // Instancia de la base de datos
        $usuariosDB = new UsuariosDB();
        if($usuariosDB->verificarEstadoUsuario($id)){
    
        // Llamar a la función para realizar el borrado lógico
        $result = $usuariosDB->borrarUser($id);
        }else{
            http_response_code(404); // Código de éxito
            echo json_encode(["success" => false, "message" => "El usuario no se a encontrado"]);
        }
        if(isset($result)){
            if ($result) {
                http_response_code(200); // Código de éxito
                echo json_encode(["success" => true, "message" => "Usuario eliminado."]);
            } else {
                http_response_code(500); // Error de servidor
                echo json_encode(["success" => false, "message" => "Error al eliminar el usuario."]);
            }
        }
    }
    
    
    
}

/*
//PRUEBAS
$users = new UsuariosController;
$users->getAllUsers();
*/
