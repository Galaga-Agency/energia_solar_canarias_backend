<?php
session_start();

// Crear la imagen
$imageWidth = 200;
$imageHeight = 70;
$image = imagecreatetruecolor($imageWidth, $imageHeight);

// Configurar colores
$backgroundColor = imagecolorallocate($image, rand(200, 255), rand(200, 255), rand(200, 255));
imagefill($image, 0, 0, $backgroundColor);

// Añadir ruido al fondo
for ($i = 0; $i < 1000; $i++) {
    $noiseColor = imagecolorallocate($image, rand(150, 255), rand(150, 255), rand(150, 255));
    imagesetpixel($image, rand(0, $imageWidth), rand(0, $imageHeight), $noiseColor);
}

// Generar texto aleatorio para el CAPTCHA
$captchaText = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);

// Guardar el texto en la sesión
$_SESSION['captcha'] = $captchaText;

// Configuración de las letras
$fonts = [__DIR__ . '/fonts/ARIAL.TTF',__DIR__ . '/fonts/COMICATE.TTF',__DIR__ . '/fonts/SIXTY.TTF']; // Rutas a tus fuentes
$fontSize = 20;
$textX = 20;
$textY = rand(35, 55);

// Añadir las letras desorganizadas a la imagen
for ($i = 0; $i < strlen($captchaText); $i++) {
    $angle = rand(-30, 30); // Rotar la letra
    $fontColor = imagecolorallocate($image, rand(0, 100), rand(0, 100), rand(0, 100));
    $font = $fonts[array_rand($fonts)];
    imagettftext(
        $image,
        $fontSize,
        $angle,
        $textX,
        $textY,
        $fontColor,
        $font,
        $captchaText[$i]
    );
    $textX += rand(25, 35); // Espaciado irregular entre letras
}

// Dibujar líneas aleatorias para mayor complejidad
for ($i = 0; $i < 5; $i++) {
    $lineColor = imagecolorallocate($image, rand(100, 200), rand(100, 200), rand(100, 200));
    imageline($image, rand(0, $imageWidth), rand(0, $imageHeight), rand(0, $imageWidth), rand(0, $imageHeight), $lineColor);
}

// Mostrar la imagen
header("Content-Type: image/png");
imagepng($image);
imagedestroy($image);
?>
