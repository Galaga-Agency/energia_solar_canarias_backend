<?php

require_once "conexion.php";
require_once "respuesta.php";

class Usuarios
{
    public $conexion;
    public $respuesta;
    public $error;

    function __construct()
    {
        try {
            $this->conexion = new Conexion;
            $this->respuesta = new Respuesta;
            $this->error = new Errores;
        } catch (\Throwable $th) {
            $this->error->_500($th);
            $this->error->message = 'Error en la función constructora de la clase Usuarios al crear los objetos en la propiedades: conexion, respuesta, error';
            return $this->error;
        }
    }

    public function getAllUsers()
    {
        try {
            //Consulta de datos
            $data = [];
            $data[0] = [
                'id' => 1,
                'nombre' => 'Panel Solar X',
                'precio' => 400,
                'descripcion' => 'Panel solar de alta eficiencia para uso residencial.',
                'stock' => 50,
                'categoria' => 'Energía solar',
                'marca' => 'Energía Solar Canarias',
                'disponible' => true
            ];
            $data[1] = [
                'id' => 2,
                'nombre' => 'Panel Solar X2',
                'precio' => 700,
                'descripcion' => 'Panel solar de alta eficiencia para uso residencial.',
                'stock' => 50,
                'categoria' => 'Energía solar',
                'marca' => 'Energía Solar Canarias',
                'disponible' => false
            ];
            //Información de paginación
            $pagination = [];
            $pagination['currentPage'] = '2';
            $pagination['perPage'] = '200';
            $pagination['totalItems'] = '600';
            $pagination['totalPages'] = '3';
            $pagination['nextPageUrl'] = '/usuarios/pages/3';
            $pagination['previousPageUrl'] = '/usuarios/pages/1';
            //Retornar respuesta
            $this->respuesta->success($data, $pagination);
            $this->respuesta->message .= '- Consulta exitosa de todos los usuarios';
            return $this->respuesta;
        } catch (\Throwable $th) {
            $this->error->_500($th);
            $this->error->message = 'Error en la función getAllUsers de la clase Usuarios al realizar la consulta de todos los usuarios';
            return $this->error;
        }
    }

    public function getUser($id)
    {
        try {
            //Consulta de datos
            $data = [];
            $data[0] = [
                'id' => $id,
                'nombre' => 'Panel Solar X',
                'precio' => 400,
                'descripcion' => 'Panel solar de alta eficiencia para uso residencial.',
                'stock' => 50,
                'categoria' => 'Energía solar',
                'marca' => 'Energía Solar Canarias',
                'disponible' => true
            ];
            //Información de paginación
            $pagination = [];
            $pagination['currentPage'] = '';
            $pagination['perPage'] = '';
            $pagination['totalItems'] = '';
            $pagination['totalPages'] = '';
            $pagination['nextPageUrl'] = '';
            $pagination['previousPageUrl'] = '';
            //Retornar respuesta
            $this->respuesta->success($data, $pagination);
            $this->respuesta->message .= '- Consulta exitosa de todos los usuarios';
            return $this->respuesta;
        } catch (\Throwable $th) {
            $this->error->_500($th);
            $this->error->message = 'Error en la función getAllUsers de la clase Usuarios al realizar la consulta de todos los usuarios';
            return $this->error;
        }
    }
}
