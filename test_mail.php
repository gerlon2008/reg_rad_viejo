<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Buscando Servidor Exchange / SMTP interno en NA-SA</h2>";

// Nombres típicos de servidores internos de Exchange / Relay
$servidores = array(
    'mail.na-sa.com.ar',
    'smtp.na-sa.com.ar',
    'relay.na-sa.com.ar',
    'exchange.na-sa.com.ar',
    'correo.na-sa.com.ar',
    'webmail.na-sa.com.ar',
    'autodiscover.na-sa.com.ar',
    'na-sa.com.ar'
);

$puertos = array(25, 587);

foreach ($servidores as $host) {
    foreach ($puertos as $puerto) {
        echo "Probando <b>$host</b> en puerto <b>$puerto</b>... ";
        $fp = @fsockopen($host, $puerto, $errno, $errstr, 2);
        if ($fp) {
            $banner = fgets($fp, 512); // Lee el saludo de bienvenida del servidor
            echo "<span style='color:green; font-weight:bold;'>¡CONECTADO!</span><br>";
            echo "<pre style='background:#f4f4f4; padding:5px; margin-left:20px;'>Respuesta: " . htmlspecialchars($banner) . "</pre>";
            fclose($fp);
            break 2; // Si encontramos uno, frenamos
        } else {
            echo "<span style='color:gray;'>Sin respuesta</span><br>";
        }
    }
}
?>