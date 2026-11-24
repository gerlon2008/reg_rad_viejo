<?php
/**
 * Módulo de envío de correo para Registro Radiológico (Nucleoeléctrica Argentina S.A.)
 * Envío mediante socket directo a Microsoft Exchange 2019 (correo.na-sa.com.ar:25)
 * Compatible con PHP 5.5+ (no requiere OpenSSL ni librerías externas)
 */

function registrar_log_mail($mensaje) {
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . "\n";
    $rutas = array();
    if (!empty($_SERVER["DOCUMENT_ROOT"])) {
        $rutas[] = $_SERVER["DOCUMENT_ROOT"] . '/ptr/registro_rad5/mail_envio_debug.log';
    }
    $rutas[] = __DIR__ . '/mail_envio_debug.log';
    foreach ($rutas as $ruta) {
        if (@file_put_contents($ruta, $linea, FILE_APPEND) !== false) {
            break;
        }
    }
}

function enviar_planilla_por_correo($id_planilla, $ruta_pdf, $nombre_archivo, $datos_planilla = array(), $miconex = null, $pdf_contenido_directo = null, $forzar_reenvio = false) {
    // -------------------------------------------------------------
    // 1. CONFIGURACIÓN DEL SERVIDOR Y DESTINATARIOS
    // -------------------------------------------------------------
    $servidor_smtp = 'correo.na-sa.com.ar';
    $puerto_smtp   = 25;
    $remitente     = 'registro_radiologico@na-sa.com.ar';
    $nombre_de     = 'Planilla Registro Radiologico';

    // LISTA DE DESTINATARIOS:
    // Para PRODUCCIÓN: descomentar la siguiente línea:
    // $destinatarios = array('ayudantejefedeturno@na-sa.com.ar');

    // Para PRUEBAS (actual):
    $destinatarios = array('gloncar@na-sa.com.ar');

    $trace = array();

    // -------------------------------------------------------------
    // 2. CONTROL DE DUPLICADOS (Evitar reenviar si ya fue enviada)
    // -------------------------------------------------------------
    $id_planilla = intval($id_planilla);
    if ($id_planilla <= 0) {
        registrar_log_mail("ERROR id_planilla inválido: $id_planilla");
        return array('exito' => false, 'mensaje' => 'ID de planilla inválido', 'trace' => $trace);
    }

    $dir_registros = (!empty($ruta_pdf) && is_string($ruta_pdf)) ? dirname($ruta_pdf) : ($_SERVER["DOCUMENT_ROOT"] . '/ptr/registro_rad5/Registros_pdfs');
    $archivo_lock = $dir_registros . '/.mail_enviado_' . $id_planilla . '.lock';

    if (!$forzar_reenvio) {
        // A) Control por base de datos (si la conexión está disponible)
        if ($miconex) {
            @mysql_query("CREATE TABLE IF NOT EXISTS `regrad_planillas_enviadas` (
                `id_planilla` INT(11) NOT NULL PRIMARY KEY,
                `fecha_envio` DATETIME NOT NULL,
                `archivo` VARCHAR(255) DEFAULT NULL,
                `destinatarios` TEXT DEFAULT NULL
            )", $miconex);

            $res_check = @mysql_query("SELECT id_planilla FROM `regrad_planillas_enviadas` WHERE id_planilla = $id_planilla LIMIT 1", $miconex);
            if ($res_check && mysql_num_rows($res_check) > 0) {
                registrar_log_mail("OMITIDO id_planilla=$id_planilla: ya figura en tabla regrad_planillas_enviadas");
                return array('exito' => true, 'mensaje' => 'Ya fue enviada anteriormente por correo (DB)', 'trace' => $trace);
            }
        }

        // B) Control por archivo testigo en disco (doble seguridad)
        if (file_exists($archivo_lock)) {
            registrar_log_mail("OMITIDO id_planilla=$id_planilla: ya existe archivo lock $archivo_lock");
            return array('exito' => true, 'mensaje' => 'Ya fue enviada anteriormente por correo (archivo lock)', 'trace' => $trace);
        }
    } else {
        $trace[] = 'Modo forzar_reenvio activo (se omite control de duplicados)';
    }

    // Obtener el contenido del PDF (directo en memoria o desde disco)
    $pdf_contenido = '';
    if (!empty($pdf_contenido_directo)) {
        $pdf_contenido = $pdf_contenido_directo;
    } else if (!empty($ruta_pdf) && file_exists($ruta_pdf) && filesize($ruta_pdf) > 0) {
        $pdf_contenido = file_get_contents($ruta_pdf);
    }

    if (empty($pdf_contenido)) {
        $msg = 'El contenido del PDF está vacío o no se encontró el archivo: ' . $ruta_pdf;
        registrar_log_mail("ERROR id_planilla=$id_planilla: $msg");
        return array('exito' => false, 'mensaje' => $msg, 'trace' => $trace);
    }

    $trace[] = 'PDF listo: ' . strlen($pdf_contenido) . ' bytes (' . $nombre_archivo . ')';

    // -------------------------------------------------------------
    // 3. CONSTRUCCIÓN DEL CORREO (ASUNTO Y CUERPO)
    // -------------------------------------------------------------
    $fecha_raw = isset($datos_planilla['fecha']) ? trim($datos_planilla['fecha']) : '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) {
        $fecha_str = date('d/m/Y', strtotime($fecha_raw));
    } else if (!empty($fecha_raw)) {
        $fecha_str = $fecha_raw;
    } else {
        $fecha_str = date('d/m/Y');
    }
    $horario_str = isset($datos_planilla['horarios']) ? $datos_planilla['horarios'] : 'Turno';
    $oficial_str = (isset($datos_planilla['of_apellido']) && isset($datos_planilla['of_nombre']))
                    ? strtoupper($datos_planilla['of_apellido'] . ', ' . $datos_planilla['of_nombre'])
                    : 'No especificado';
    $jefe_str    = (isset($datos_planilla['jefe_apellido']) && isset($datos_planilla['jefe_nombre']))
                    ? strtoupper($datos_planilla['jefe_apellido'] . ', ' . $datos_planilla['jefe_nombre'])
                    : 'No especificado';

    // Asunto del correo (codificado en Base64 para caracteres especiales/tildes)
    $asunto_texto = "Planilla Diaria Registro Radiológico - $fecha_str - $horario_str";
    $asunto_header = '=?UTF-8?B?' . base64_encode($asunto_texto) . '?=';

    // Mensaje en formato HTML (normalizado a CRLF para compatibilidad estricta con Exchange)
    $mensaje_html = "
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.5;'>
        <div style='background-color: #005a9c; color: white; padding: 12px 20px; border-radius: 4px 4px 0 0;'>
            <h2 style='margin: 0; font-size: 18px;'>Planilla Diaria de Registro Radiológico</h2>
        </div>
        <div style='border: 1px solid #ddd; border-top: none; padding: 20px; background-color: #fafafa; border-radius: 0 0 4px 4px;'>
            <p>Estimados,</p>
            <p>Se adjunta la <b>Planilla Diaria de Registro Radiológico</b> correspondiente al turno indicado a continuación:</p>
            
            <table style='width: 100%; max-width: 500px; border-collapse: collapse; margin: 15px 0; background: white; border: 1px solid #ccc;'>
                <tr style='background: #f0f0f0;'>
                    <td style='padding: 8px; border: 1px solid #ccc;'><b>Fecha:</b></td>
                    <td style='padding: 8px; border: 1px solid #ccc;'>$fecha_str</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ccc;'><b>Turno / Horario:</b></td>
                    <td style='padding: 8px; border: 1px solid #ccc;'>$horario_str</td>
                </tr>
                <tr style='background: #f0f0f0;'>
                    <td style='padding: 8px; border: 1px solid #ccc;'><b>Oficial de Radioprotección:</b></td>
                    <td style='padding: 8px; border: 1px solid #ccc;'>$oficial_str</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ccc;'><b>Jefe de Turno:</b></td>
                    <td style='padding: 8px; border: 1px solid #ccc;'>$jefe_str</td>
                </tr>
                <tr style='background: #f0f0f0;'>
                    <td style='padding: 8px; border: 1px solid #ccc;'><b>Archivo Adjunto:</b></td>
                    <td style='padding: 8px; border: 1px solid #ccc;'>$nombre_archivo</td>
                </tr>
            </table>

            <p style='color: #666; font-size: 12px; margin-top: 25px;'>
                <i>Este es un mensaje generado automáticamente por el Sistema de Registro Radiológico de Nucleoeléctrica Argentina S.A.</i>
            </p>
        </div>
    </body>
    </html>
    ";
    $mensaje_html = str_replace(array("\r\n", "\r", "\n"), "\r\n", $mensaje_html);
    $mensaje_html = preg_replace('/^\./m', '..', $mensaje_html);

    // -------------------------------------------------------------
    // 4. PREPARACIÓN MIME MULTIPART CON ADJUNTO PDF
    // -------------------------------------------------------------
    $boundary = "==_MIME_BOUNDARY_" . md5(time() . $id_planilla);
    $pdf_adjunto_b64 = chunk_split(base64_encode($pdf_contenido), 76, "\r\n");

    $cuerpo_mime  = "From: $nombre_de <$remitente>\r\n";
    $cuerpo_mime .= "To: " . implode(', ', $destinatarios) . "\r\n";
    $cuerpo_mime .= "Subject: $asunto_header\r\n";
    $cuerpo_mime .= "Date: " . date('r') . "\r\n";
    $cuerpo_mime .= "MIME-Version: 1.0\r\n";
    $cuerpo_mime .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    $cuerpo_mime .= "\r\n";

    // Parte HTML
    $cuerpo_mime .= "--$boundary\r\n";
    $cuerpo_mime .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cuerpo_mime .= "Content-Transfer-Encoding: 8bit\r\n";
    $cuerpo_mime .= "\r\n";
    $cuerpo_mime .= $mensaje_html . "\r\n";

    // Parte Adjunto PDF
    $cuerpo_mime .= "--$boundary\r\n";
    $cuerpo_mime .= "Content-Type: application/pdf; name=\"$nombre_archivo\"\r\n";
    $cuerpo_mime .= "Content-Transfer-Encoding: base64\r\n";
    $cuerpo_mime .= "Content-Disposition: attachment; filename=\"$nombre_archivo\"\r\n";
    $cuerpo_mime .= "\r\n";
    $cuerpo_mime .= $pdf_adjunto_b64 . "\r\n";

    // Cierre MIME y terminador SMTP
    $cuerpo_mime .= "--$boundary--\r\n";
    $cuerpo_mime .= ".\r\n";

    // -------------------------------------------------------------
    // 5. ENVÍO POR SOCKET SMTP A EXCHANGE 2019
    // -------------------------------------------------------------
    $socket = @fsockopen($servidor_smtp, $puerto_smtp, $errno, $errstr, 15);
    if (!$socket) {
        $msg = "No se pudo conectar a $servidor_smtp:$puerto_smtp: $errstr ($errno)";
        registrar_log_mail("ERROR id_planilla=$id_planilla: $msg");
        return array('exito' => false, 'mensaje' => $msg, 'trace' => $trace);
    }
    stream_set_timeout($socket, 30);

    $fn_escribir_todo = function($s, $datos) {
        $total = strlen($datos);
        $enviados = 0;
        while ($enviados < $total) {
            $chunk = substr($datos, $enviados, 8192);
            $escritos = @fwrite($s, $chunk);
            if ($escritos === false || $escritos === 0) {
                return $enviados;
            }
            $enviados += $escritos;
        }
        return $enviados;
    };

    $fn_leer = function($s) {
        $res = '';
        while (($linea = @fgets($s, 512)) !== false) {
            $res .= $linea;
            if (strlen($linea) >= 4 && substr($linea, 3, 1) == ' ') {
                break;
            }
        }
        return trim($res);
    };

    $fn_cmd = function($s, $cmd) use ($fn_leer, $fn_escribir_todo) {
        $fn_escribir_todo($s, $cmd . "\r\n");
        return $fn_leer($s);
    };

    // Saludo inicial de Exchange
    $banner = $fn_leer($socket);
    $trace[] = "BANNER: $banner";

    // Protocolo SMTP
    $ehlo_res = $fn_cmd($socket, "EHLO central.nasa");
    $trace[] = "EHLO: $ehlo_res";

    $from_res = $fn_cmd($socket, "MAIL FROM:<$remitente>");
    $trace[] = "MAIL FROM: $from_res";
    if (strpos($from_res, '250') === false) {
        $fn_cmd($socket, "QUIT");
        fclose($socket);
        $msg = "Exchange rechazó MAIL FROM: $from_res";
        registrar_log_mail("ERROR id_planilla=$id_planilla: $msg");
        return array('exito' => false, 'mensaje' => $msg, 'trace' => $trace);
    }

    $al_menos_uno = false;
    foreach ($destinatarios as $dest) {
        $dest = trim($dest);
        if (!empty($dest)) {
            $rcpt_res = $fn_cmd($socket, "RCPT TO:<$dest>");
            $trace[] = "RCPT TO <$dest>: $rcpt_res";
            if (strpos($rcpt_res, '250') !== false) {
                $al_menos_uno = true;
            }
        }
    }

    if (!$al_menos_uno) {
        $fn_cmd($socket, "QUIT");
        fclose($socket);
        $msg = 'El servidor Exchange rechazó los destinatarios.';
        registrar_log_mail("ERROR id_planilla=$id_planilla: $msg | " . implode(' ; ', $trace));
        return array('exito' => false, 'mensaje' => $msg, 'trace' => $trace);
    }

    $data_cmd_res = $fn_cmd($socket, "DATA");
    $trace[] = "DATA CMD: $data_cmd_res";
    if (strpos($data_cmd_res, '354') === false) {
        $fn_cmd($socket, "QUIT");
        fclose($socket);
        $msg = "Exchange no aceptó DATA: $data_cmd_res";
        registrar_log_mail("ERROR id_planilla=$id_planilla: $msg");
        return array('exito' => false, 'mensaje' => $msg, 'trace' => $trace);
    }

    $esperados = strlen($cuerpo_mime);
    $escritos = $fn_escribir_todo($socket, $cuerpo_mime);
    $trace[] = "MIME enviados: $escritos / $esperados bytes";

    $data_res = $fn_leer($socket);
    $trace[] = "DATA RES: $data_res";

    $fn_cmd($socket, "QUIT");
    fclose($socket);

    // -------------------------------------------------------------
    // 6. MARCAR COMO ENVIADA
    // -------------------------------------------------------------
    if ($escritos === $esperados && strpos($data_res, '250') !== false) {
        // Guardar lock en archivo
        @file_put_contents($archivo_lock, date('Y-m-d H:i:s') . " - Enviado a: " . implode(', ', $destinatarios));

        // Guardar registro en BD si hay conexion
        if ($miconex) {
            $dest_str = mysql_real_escape_string(implode(', ', $destinatarios), $miconex);
            $nom_str  = mysql_real_escape_string($nombre_archivo, $miconex);
            @mysql_query("INSERT IGNORE INTO `regrad_planillas_enviadas` (`id_planilla`, `fecha_envio`, `archivo`, `destinatarios`) 
                          VALUES ($id_planilla, NOW(), '$nom_str', '$dest_str')", $miconex);
        }

        registrar_log_mail("OK id_planilla=$id_planilla enviado a " . implode(', ', $destinatarios) . " ($escritos bytes) | $data_res");
        return array('exito' => true, 'mensaje' => 'Correo enviado exitosamente.', 'trace' => $trace);
    } else {
        $msg = "Error al transferir contenido DATA ($escritos/$esperados bytes): $data_res";
        registrar_log_mail("ERROR id_planilla=$id_planilla: $msg");
        return array('exito' => false, 'mensaje' => $msg, 'trace' => $trace);
    }
}
