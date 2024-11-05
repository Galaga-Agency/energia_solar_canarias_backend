<?php
class HttpClient {
    /**
     * Método para realizar solicitudes GET
     *
     * @param string $url La URL a la que se va a realizar la solicitud
     * @param array $headers Un array opcional de encabezados HTTP a incluir en la solicitud
     * @return string La respuesta de la solicitud HTTP
     * @throws Exception Si hay un error en cURL
     */
    public function get($url, $headers = []) {
        // Inicializa una nueva sesión cURL
        $ch = curl_init();
        
        // Establece la URL de la solicitud
        curl_setopt($ch, CURLOPT_URL, $url);

        /**
         * Configura cURL para devolver la respuesta como una cadena en lugar de imprimirla directamente.
         * Esto es útil para capturar y procesar la respuesta.
         */
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        /**
         * Agrega los encabezados HTTP a la solicitud, si se proporcionan.
         * Esto se usa comúnmente para enviar claves de API, especificar tipos de contenido, etc.
         */
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        /**
         * Establece un tiempo máximo de espera para la solicitud en segundos.
         * Si la solicitud tarda más de este tiempo, cURL la cancelará automáticamente.
         */
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        /**
         * Desactiva la verificación del certificado SSL.
         * Esto es útil en entornos de prueba, pero se recomienda mantenerla activada (true) en producción por motivos de seguridad.
         */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Ejecuta la solicitud y guarda la respuesta
        $response = curl_exec($ch);

        // Verifica si ocurrió un error durante la ejecución de cURL
        if (curl_errno($ch)) {
            // Lanza una excepción con el mensaje de error
            throw new Exception('Error en cURL: ' . curl_error($ch));
        }

        // Cierra la sesión cURL y libera los recursos
        curl_close($ch);

        // Devuelve la respuesta de la solicitud HTTP
        return $response;
    }
}
?>
