<?php

require_once "../utils/respuesta.php";

class Autenticacion
{

    public $usuario;
    public $apiKey;
    public $login;
    public $tokenLogin;
    public $error;
    public $usuarioEsperado;
    public $apiKeyEsperada;

    function __construct()
    {
        $this->error = new Errores;
    }

    public function autenticar()
    {
        // Verificar si la cabecera 'usuario' y 'apiKey' están presentes en $_SERVER
        $this->usuario = isset($_SERVER['HTTP_USUARIO']) ? $_SERVER['HTTP_USUARIO'] : null;
        $this->apiKey = isset($_SERVER['HTTP_APIKEY']) ? $_SERVER['HTTP_APIKEY'] : null;

        // Verificar que ambas cabeceras están presentes
        if ($this->usuario && $this->apiKey) {
            // Aquí podrías comparar con los valores esperados o buscarlos en una base de datos
            $this->usuarioEsperado = 'anfego1';
            $this->apiKeyEsperada = '***REMOVED***';

            if ($this->usuario === $this->usuarioEsperado && $this->apiKey === $this->apiKeyEsperada) {
                //Aquí entraría a verificar las cookies de seguridad
                return;
            } else {
                $this->error->_401();
                $this->error->message = 'No autorizad@, las credenciales no son válidas. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
                http_response_code($this->error->code);
                echo json_encode($this->error);
                die();
            }
        } else {
            $this->error->_401();
            $this->error->message = 'No autorizad@, las credenciales no son válidas. Para cualquier duda o asesoría contactar por favor con soporte@galagaagency.com';
            http_response_code($this->error->code);
            echo json_encode($this->error);
            die();
        }
    }
}
