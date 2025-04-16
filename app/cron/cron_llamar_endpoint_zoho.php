<?php

require_once __DIR__ . '/../../config/vendor/autoload.php'; // Ajustá si tu composer está en otra ruta

use Dotenv\Dotenv;

// Cargar .env desde carpeta /config
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../config');
$dotenv->load();

$url = 'https://app-energiasolarcanarias-backend.com/zoho/actualizarDatosPlantas';
$token = $_ENV['TOKEN_SOPORTE'] ?? null;

if (!$token) {
    exit("No se encontró el token TOKEN_SOPORTE en el archivo .env\n");
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Token ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);
