<?php
/**
 * Script de prueba de envío de correo con PDF adjunto
 * Ejecutar desde el navegador para verificar la recepción del correo en Outlook con el PDF legible.
 */
header('Content-Type: text/html; charset=utf-8');
require_once(__DIR__ . '/enviar_mail_planilla.php');

echo "<h2>Prueba de Envío de Correo con PDF Adjunto a Exchange</h2>";

// 1. Buscamos si ya existe algún PDF en Registros_pdfs o creamos un archivo PDF mínimo de prueba
$dir_pdf = __DIR__ . '/Registros_pdfs';
if (!is_dir($dir_pdf)) {
    @mkdir($dir_pdf, 0777, true);
}

// Intentar encontrar un PDF existente en la carpeta
$archivos = glob($dir_pdf . '/*.pdf');
if (!empty($archivos)) {
    $ruta_pdf_prueba = $archivos[0];
    $nombre_pdf_prueba = basename($ruta_pdf_prueba);
    echo "<p>Usando PDF existente para la prueba: <b>$nombre_pdf_prueba</b></p>";
} else {
    // Si no hay ninguno, creamos un archivo de prueba simulado
    $ruta_pdf_prueba = $dir_pdf . '/Prueba_Planilla_Radiologica.pdf';
    $nombre_pdf_prueba = 'Prueba_Planilla_Radiologica.pdf';
    
    // PDF mínimo válido (RFC compliant)
    $contenido_pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF";
    file_put_contents($ruta_pdf_prueba, $contenido_pdf);
    echo "<p>Creando archivo de prueba: <b>$nombre_pdf_prueba</b></p>";
}

// Datos simulados de la planilla
$datos_ejemplo = array(
    'fecha'         => date('d/m/Y'),
    'horarios'      => '06:00 a 14:00 (Turno Prueba)',
    'of_apellido'   => 'Loncar',
    'of_nombre'     => 'Gerardo',
    'jefe_apellido' => 'Prueba',
    'jefe_nombre'   => 'Jefe Turno'
);

// Usamos un ID ficticio para la prueba (ej: 999999) y borramos el lock si existía de una prueba previa
$id_prueba = 999999;
$lock_prueba = $dir_pdf . '/.mail_enviado_' . $id_prueba . '.lock';
if (file_exists($lock_prueba)) {
    @unlink($lock_prueba);
}

echo "<p>Iniciando envío a través de <code>enviar_planilla_por_correo()</code>...</p>";

$resultado = enviar_planilla_por_correo($id_prueba, $ruta_pdf_prueba, $nombre_pdf_prueba, $datos_ejemplo);

if ($resultado['exito']) {
    echo "<h3 style='color:green;'>¡Éxito! " . htmlspecialchars($resultado['mensaje']) . "</h3>";
    echo "<p>Revisá tu bandeja de entrada en Outlook (<b>gloncar@na-sa.com.ar</b>). Deberías recibir el correo con el diseño institucional y el PDF adjunto.</p>";
} else {
    echo "<h3 style='color:red;'>Error en el envío: " . htmlspecialchars($resultado['mensaje']) . "</h3>";
}
?>
