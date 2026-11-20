<?php
if (!isset($_POST['coche'])) {
    exit;
}

$img = $_POST['coche'];
$img = str_replace('data:image/png;base64,', '', $img);
$img = str_replace(' ', '+', $img);
$fileData = base64_decode($img);

if (!$fileData) {
    exit;
}

// Carpeta donde se guarda graf.jpg
$dirGraficos = __DIR__ . '/Graficos';
if (!is_dir($dirGraficos)) {
    @mkdir($dirGraficos, 0777, true);
}
$fileName = $dirGraficos . '/graf.jpg';

// Cargar la imagen PNG directamente desde los datos decodificados
if (function_exists('imagecreatefromstring')) {
    $image = @imagecreatefromstring($fileData);
} else {
    file_put_contents($fileName, $fileData);
    $image = @imagecreatefrompng($fileName);
}

if (!$image) {
    exit;
}

// Obtenemos el ancho y alto REALES del gráfico exportado por el navegador
$ancho = imagesx($image);
$alto  = imagesy($image);

// 1. Creamos el lienzo del tamaño exacto del gráfico
$tmp = imagecreatetruecolor($ancho, $alto);

// 2. Rellenamos el lienzo con fondo BLANCO puro
$blanco = imagecolorallocate($tmp, 255, 255, 255);
imagefill($tmp, 0, 0, $blanco);

// 3. Copiamos el gráfico con mezcla de transparencia sobre el fondo blanco
imagealphablending($tmp, true);
imagecopy($tmp, $image, 0, 0, 0, 0, $ancho, $alto);

// 4. Rotamos 90 grados indicando BLANCO como color de fondo para la rotación (elimina las franjas negras)
$blanco_rotacion = imagecolorallocate($tmp, 255, 255, 255);
$rotate = imagerotate($tmp, 90, $blanco_rotacion);

// 5. Guardamos en JPG con calidad máxima 100
imagejpeg($rotate, $fileName, 100);

// 6. Liberamos la memoria de GD
imagedestroy($tmp);
imagedestroy($image);
imagedestroy($rotate);
?>