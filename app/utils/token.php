<?php

require_once __DIR__ . '/../models/conexion.php';

class Token
{

    public $value;
    public $timeCreated;

    // Constructor que genera el token y guarda el tiempo de creación
    public function __construct($length = 32)
    {
        $this->value = $this->generateToken($length);
        $this->timeCreated = time(); // Obtiene el tiempo actual en segundos desde el 1 de enero de 1970
    }

    // Método para generar un token seguro
    private function generateToken($length = 32)
    {
        $bytes = random_bytes($length / 2);
        return bin2hex($bytes);
    }

    // Método para verificar si el token es válido dentro de 5 minutos
    public function isTokenValid($tiempoCreado)
    {
        $currentTime = time(); // Tiempo actual en segundos
        $timeElapsed = $currentTime - $tiempoCreado;
        // Verifica si han pasado menos de 5 minutos (300 segundos)
        return $timeElapsed <= 300;
    }

    // Método para borrar todos los tokens temporales del usuario
    public function deleteAllTokensUser($user)
    {

        $conexion = Conexion::getInstance();
        $conn = $conexion->getConexion();
        //borramos todos los tokens que excedan de 5 minutos
        $sql = "
            DELETE token 
            FROM token
            INNER JOIN usuarios ON token.usuario_id = usuarios.usuario_id
            WHERE usuarios.usuario_id = ? ";
        //preparamos la consulta contra injecciones
        $stmt = $conn->prepare($sql);
        //le metemos el parametro int y string
        $stmt->bind_param('s', $user);
        //ejecutamos la consulta
        $stmt->execute();
        //cerramos la consulta
        $stmt->close();
        // Mensaje de control
        //echo ("<script>console.log('PHP: Borrados correctamentes los token del usuario que han excedido los 5 minutos');</script>");
    }

    // Método para borrar el token temporal del usuario que exceda de 5 minutos
    public function deleteTokenUser($user)
    {
        $time_actual = time(); // Tiempo actual en segundos

        $conexion = Conexion::getInstance();
        $conn = $conexion->getConexion();
        //borramos todos los tokens que excedan de 5 minutos
        $sql = "
            DELETE token 
            FROM token
            INNER JOIN usuarios ON token.usuario_id = usuarios.usuario_id
            WHERE usuarios.usuario_id = ? 
            AND ? - token.time_token_login > 300;";
        //preparamos la consulta contra injecciones
        $stmt = $conn->prepare($sql);
        //le metemos el parametro int y string
        $stmt->bind_param('is', $time_actual, $user);
        //ejecutamos la consulta
        $stmt->execute();
        //cerramos la consulta
        $stmt->close();
        // Mensaje de control
        //echo ("<script>console.log('PHP: Borrados correctamentes los token del usuario que han excedido los 5 minutos');</script>");
    }
    // Método para borrar el token del usuario que exceda de 5 minutos
    public function deleteTokenUserPorEmail($user)
    {
        $time_actual = time(); // Tiempo actual en segundos

        $conexion = Conexion::getInstance();
        $conn = $conexion->getConexion();
        //borramos todos los tokens que excedan de 5 minutos
        $sql = "
        DELETE FROM token 
        USING token
        INNER JOIN usuarios ON token.usuario_id = usuarios.usuario_id
        WHERE usuarios.email = ? 
        AND ? - token.time_token_login > 300;";
        //preparamos la consulta contra injecciones
        $stmt = $conn->prepare($sql);
        //le metemos el parametro int y string
        $stmt->bind_param('si', $user, $time_actual);
        //ejecutamos la consulta
        $stmt->execute();
        /** 
         * VERIFICAR  SI SE PUEDE BORRAR CON UN USUARIO
         *if ($stmt->execute()) {
         *    echo "Tokens eliminados correctamente";
         *} else {
         *    echo "Error al eliminar tokens: " . $stmt->error;
         *}
         */
        //cerramos la consulta
        $stmt->close();
        // Mensaje de control
        //echo ("<script>console.log('PHP: Borrados correctamentes los token del usuario que han excedido los 5 minutos');</script>");
    }
    // Método para recoger el ultimo token del usuario
    public function getLastTokenUser($userId)
    {
        $conexion = Conexion::getInstance();
        $conn = $conexion->getConexion();
        //recogemos el ultimo token activo del usuario
        $sql = "
            SELECT token.token_id, token.token_login, token.time_token_login 
            FROM token 
            WHERE token.usuario_id = ? 
            ORDER BY token.time_token_login DESC 
            LIMIT 1;";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId); // Solo el `userId` como parámetro
        $stmt->execute();
        $result = $stmt->get_result();

        // Si hay un token, obtén los datos
        $lastToken = $result->fetch_assoc();

        $stmt->close();
        //echo ("<script>console.log('PHP: Borrados correctamentes los token del usuario que han excedido los 5 minutos');</script>");
        return $lastToken ? $lastToken : null; // Devuelve el token o null si no existe
    }
}

/*
//PRUEBAS
$token = new Token(70);
echo $token->value;
echo "/";
echo $token->timeCreated;
*/