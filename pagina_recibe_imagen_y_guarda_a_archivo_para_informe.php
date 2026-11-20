<?php
$img = $_POST['coche'];
$img = str_replace('data:image/png;base64,', '', $img);
$img = str_replace(' ', '+', $img);
$fileData = base64_decode($img);


//saving
$fileName = 'Graficos/graf.jpg';
unlink($fileName);
file_put_contents($fileName, $fileData);
// Quality is a number between 0 (best compression) and 100 (best quality)
$image = imagecreatefrompng('Graficos/graf.jpg');

/* Creamos la imagen de destino */
$tmp = imagecreatetruecolor(1000, 800);
/* Alojamos el color de fondo y rellenamos con él la imagen */
$fondo = imagecolorallocate($tmp, 255, 255, 255);
imagefill($tmp, 0, 0, $fondo);
/* Con "true" forzamos la mezcla de la imagen usando la transparencia */
imagealphablending($tmp, true);
/* Copiamos la imagen con mezcla de transparencia */
imagecopyresampled(
  $tmp, $image,
  0, 0, 0, 0,
  1000,
  800,
  1000,
  800
);

$rotate = imagerotate($tmp, '90', 0);
imagejpeg($rotate, 'Graficos/graf.jpg', 100);



imagedestroy($tmp);
imagedestroy($image);
imagedestroy($rotate);

?>