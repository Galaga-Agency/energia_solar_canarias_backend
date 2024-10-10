<?php

require_once "conexion.php";
require_once "respuesta.php";


class Usuarios
{

    public function getAllUsers()
    {
        // Lógica para devolver una lista de productos
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
        $respuesta->success($data);
        echo json_encode($respuesta);
    }
    
    public function getUser($id)
    {
        // Lógica para devolver una lista de productos
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
        $respuesta->success($data);
        echo json_encode($respuesta);
    }

}
