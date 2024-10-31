<?php

require_once "conexion.php";
require_once "../utils/respuesta.php";
require_once "./../DBObjects/usuariosDB.php";

class Usuarios
{
    public $error;
    private $conexion;

    function __construct()
    {
        $this->conexion = new Conexion;
    }

    // Getter y setter para 'error'
    public function getError()
    {
        return $this->error;
    }

    public function setError($error)
    {
        $this->error = $error;
    }

    // Getter y setter para 'conexion'
    public function getConexion()
    {
        return $this->conexion;
    }

    public function setConexion($conexion)
    {
        $this->conexion = $conexion;
    }

    public function getAllUsers()
    {
        $respuesta = new Respuesta();
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
            $respuesta->success($data);
            $respuesta->message .= '- Consulta exitosa de todos los usuarios';
            return $respuesta;
        } catch (\Throwable $th) {
            $mensajeError = $th->getMessage();
            $archivoError = $th->getFile();
            $lineaError = $th->getLine();
            $trazaError = $th->getTraceAsString();
            $errores = [];
            $errores['mensajeError'] = $mensajeError;
            $errores['archivoError'] = $archivoError;
            $errores['lineaError'] = $lineaError;
            $errores['trazaError'] = $trazaError;
            $respuesta->_500($errores);
            $respuesta->message = 'Error en la función getAllUsers de la clase Usuarios al realizar la consulta de todos los usuarios';
            return $respuesta;
        }
    }

    public function getUser($id)
    {
        $respuesta = new Respuesta();
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
            $respuesta->success($data);
            $respuesta->message .= '- Consulta exitosa de todos los usuarios';
            return $respuesta;
        } catch (\Throwable $th) {
            $mensajeError = $th->getMessage();
            $archivoError = $th->getFile();
            $lineaError = $th->getLine();
            $trazaError = $th->getTraceAsString();
            $errores = [];
            $errores['mensajeError'] = $mensajeError;
            $errores['archivoError'] = $archivoError;
            $errores['lineaError'] = $lineaError;
            $errores['trazaError'] = $trazaError;
            $this->error->_500($errores);
            $this->error->message = 'Error en la función getAllUsers de la clase Usuarios al realizar la consulta de todos los usuarios';
            return $this->error;
        }
    }
}
