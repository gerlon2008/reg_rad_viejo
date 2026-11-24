<?php
header('Content-Type: text/html; charset=utf-8');

$servidor = 'correo.na-sa.com.ar';
$puerto = 25;
$de = 'registro_radiologico@na-sa.com.ar';
// $para = 'ayudantejefedeturno@na-sa.com.ar';
$para = 'gloncar@na-sa.com.ar'; // Tu casilla

echo "<h3>Enviando correo de prueba a $para...</h3>";

$socket = fsockopen($servidor, $puerto, $errno, $errstr, 10);
if (!$socket) {
    die("No se pudo conectar: $errstr ($errno)");
}

function leer_respuesta($socket) {
    $res = '';
    while ($linea = fgets($socket, 512)) {
        $res .= $linea;
        if (substr($linea, 3, 1) == ' ') break;
    }
    return $res;
}

function enviar_comando($socket, $cmd) {
    fwrite($socket, $cmd . "\r\n");
    return leer_respuesta($socket);
}

// 1. Saludo inicial
leer_respuesta($socket);

// 2. Protocolo SMTP
enviar_comando($socket, "EHLO central.nasa");
enviar_comando($socket, "MAIL FROM:<$de>");
$rcpt_res = enviar_comando($socket, "RCPT TO:<$para>");

if (strpos($rcpt_res, '250') !== false) {
    enviar_comando($socket, "DATA");
    
    $cuerpo = "From: Planilla Radiologica <$de>\r\n" .
              "To: <$para>\r\n" .
              "Subject: Prueba Exitosa - Sistema Planillas\r\n" .
              "MIME-Version: 1.0\r\n" .
              "Content-Type: text/plain; charset=utf-8\r\n" .
              "\r\n" .
              "Hola! Si estas leyendo esto, el envio desde PHP a Microsoft Exchange funciona perfectamente sin credenciales ni trabas.\r\n" .
              ".";
              
    $data_res = enviar_comando($socket, $cuerpo);
    enviar_comando($socket, "QUIT");
    fclose($socket);
    
    echo "<h2 style='color:green;'>¡Correo enviado exitosamente!</h2>";
    echo "Fijate en tu Outlook si ya te llegó.";
} else {
    echo "<h2 style='color:red;'>El servidor rechazó el destinatario</h2>";
    echo "<pre>$rcpt_res</pre>";
    enviar_comando($socket, "QUIT");
    fclose($socket);
}
?>