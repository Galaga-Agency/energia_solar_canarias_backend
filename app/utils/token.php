<?php

class Token {

    public $value;
    public $timeCreated;

    // Constructor que genera el token y guarda el tiempo de creación
    public function __construct($length = 32) {
        $this->value = $this->generateToken($length);
        $this->timeCreated = time(); // Obtiene el tiempo actual en segundos desde el 1 de enero de 1970
    }

    // Método para generar un token seguro
    private function generateToken($length = 32) {
        $bytes = random_bytes($length / 2);
        return bin2hex($bytes);
    }

    // Método para verificar si el token es válido dentro de 5 minutos
    public function isTokenValid($tiempoCreado) {
        $currentTime = time(); // Tiempo actual en segundos
        $timeElapsed = $currentTime - $tiempoCreado;
        // Verifica si han pasado menos de 5 minutos (300 segundos)
        return $timeElapsed <= 300;
    }

}

/*
//PRUEBAS
$token = new Token(70);
echo $token->value;
echo "/";
echo $token->timeCreated;
*/