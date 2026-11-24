<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/ptr/Connections/miconex.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/ptr/Functions/funciones.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/ptr/Connections/niveles.php");

session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {
	$tipo_user = $_SESSION['portal_nivel'];
	$id_user = $_SESSION['id_user'];
	$of_rad_registro = $_SESSION['id_user'];
} else {
	echo "Esta pagina es solo para usuarios registrados.<br>";
	exit;
}

mysql_set_charset('utf8', $miconex);
mysql_select_db($database_miconex, $miconex);

// 1. Consulta ultrarrápida de turnos usando la vista unificada
$query_registros = "SELECT * FROM vista_libro_actas_turnos ORDER BY fecha DESC, fecha_hora_abierto DESC LIMIT 15";
$registros = mysql_query($query_registros, $miconex) or die(mysql_error());
$row_registros = mysql_fetch_assoc($registros);
$totalRows_registros = mysql_num_rows($registros);

// 2. Consulta ultrarrápida de TODAS las tareas (Minizonas + Registro Diario) usando la vista unificada
$query_todas_las_tareas = "SELECT * FROM vista_actas_y_tareas_ptr ORDER BY fecha_hora DESC";
$todas_las_tareas = mysql_query($query_todas_las_tareas, $miconex) or die(mysql_error());
$totalRows_todas_las_tareas = mysql_num_rows($todas_las_tareas);

// Función auxiliar para quitar .000 en enteros y poner coma en decimales
function formatear_numero_acta($val)
{
	if ($val === null || $val === '') return '';
	$n = floatval($val);
	return ($n == floor($n)) ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es" style="height:100%">

<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="viewport">
	<title>Registro Radiologico Online</title>
	<link rel="stylesheet" href="/ptr/css/portal_syr.css" />
	<link rel="stylesheet" href="/ptr/prueba_mario/main.css" />
	<link rel="stylesheet" href="/ptr/prueba_mario/jquery.dataTables.css">
	<link rel="stylesheet" href="/ptr/jquery-ui-1.11.4.custom/dataTables.jqueryui.css">
	<link rel="stylesheet" href="/ptr/jquery-ui-1.11.4.custom - verde/jquery-ui.min.css" />
	<script src="/ptr/prueba_mario/jquery-2.1.3.min.js"></script>
	<script src="/ptr/prueba_mario/jquery-ui-1.11.4.min.js"></script>
	<script src="/ptr/prueba_mario/jquery.dataTables-1.10.7.min.js"></script>
	<script src="/ptr/prueba_mario/dataTables.jqueryui.min.js"></script>

	<style type="text/css">
		table.dataTable tbody tr.myeven {
			background-color: #ffffff;
		}

		table.dataTable tbody tr.myodd {
			background-color: #e6e6e6;
		}

		.sombra {
			-webkit-box-shadow: 1px 0px 14px 2px rgba(0, 0, 0, 0.25);
			box-shadow: 1px 0px 14px 2px rgba(0, 0, 0, 0.25);
		}

		.ui-widget {
			font-family: Ubuntu;
		}
	</style>
	<script type="text/javascript">
		$(function() {
			$(document).tooltip();
		});
	</script>
</head>

<body style="font-family:Ubuntu;width:100%;height:100%;padding:0px;margin:0px">
	<div class="container">
		<div class="mainbody" style="background-image: background-size:100% auto;background-image:url('../images/fondo_verde_registro_rad.jpg');">
			<div style="display: inline-block">
				<h2>Vista Libro de Actas (Optimizado)</h2>
				<div style="width: 1200px;margin: auto" id="tabla_contenedor">

					<?php
					if ($totalRows_registros == 0) { ?>
						<h3>NO EXISTEN REGISTROS EN LIBRO DE ACTAS</h3>
						<?php } else {
						do { ?>
							<div style="margin-top: 15px" class="ui-widget-content ui-corner-all">
								<table style="width: 90%" align="center">
									<tr>
										<td style="width: 33.33%">
											<h3>Fecha: <?php echo formafecha6($row_registros['fecha']) ?></h3>
										</td>
										<td style="width: 33.33%">
											<h3>Turno: <?php echo $row_registros['horarios'] ?></h3>
										</td>
										<td style="width: 33.33%">
											<h3>Oficial: <?php echo strtoupper($row_registros['of_apellido'] . ", " . $row_registros['of_nombre']) ?></h3>
										</td>
									</tr>
								</table>
								<table style="width: 90%;margin-top: -15px;" align="center">
									<tr>
										<td class="ui-widget-content ui-corner-all" style="width: 25%">Ventilación General: <br><span style="font-weight: bold"><?php echo ($row_registros['vent_gral_gbq']) ?> GBq</span></td>
										<td class="ui-widget-content ui-corner-all" style="width: 25%">Ventilación Reactor: <br><span style="font-weight: bold"><?php echo ($row_registros['vent_reactor_gbq']) ?> GBq</span></td>
										<td class="ui-widget-content ui-corner-all" style="width: 25%">Emisión C14: <br><span style="font-weight: bold"><?php echo ($row_registros['carbono14_mbq']) ?> MBq</span></td>
										<td class="ui-widget-content ui-corner-all" style="width: 25%">Dedo Frío 73120: <br><span style="font-weight: bold"><?php echo ($row_registros['dedo_frio_ppm']) ?> ppm</span></td>
									</tr>
									<tr>
										<td colspan="4" style="padding-top: 8px">

											<!-- 1. Novedades Generales en formato Tarjeta -->
											<?php if (!empty($row_registros['novedades']) && trim($row_registros['novedades']) != '') { ?>
												<div style="background-color: white; border-radius: 6px; border: 1px solid #d4d4d4; border-left: 5px solid #1e5799; box-shadow: 1px 1px 4px rgba(0,0,0,0.1); margin-bottom: 10px; padding: 10px 14px; text-align: left;">
													<div style="font-size: 15px; font-weight: bold; color: #1e5799; margin-bottom: 5px;">
														📝 Novedades Generales / Históricas
													</div>
													<div style="font-size: 14px; color: #222; line-height: 1.4;">
														<?php echo nl2br(htmlspecialchars($row_registros['novedades'])); ?>
													</div>
												</div>
											<?php } ?>

											<!-- 2. Tareas y Actas unificadas de este turno (Minizonas + Registro Diario) -->
											<div style="width: 100%; text-align: left;">
												<?php
												$hay_tareas = false;
												if ($totalRows_todas_las_tareas > 0) {
													mysql_data_seek($todas_las_tareas, 0);
													while ($t = mysql_fetch_assoc($todas_las_tareas)) {
														if ($t['id_planilla_diaria'] == $row_registros['id_planilla_diaria']) {
															$hay_tareas = true;
															
															$es_minizona = ($t['tipo_origen'] === 'MINIZONA');
															if ($es_minizona) {
																$nombre_mz = !empty($t['sub_origen']) ? $t['sub_origen'] : 'Minizona';
																$origen_badge = '<span style="background-color: #fff3e0; border: 1px solid #ffe0b2; color: #e65100; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; margin-left: 8px;">📍 Minizona ' . $nombre_mz . '</span>';
																$color_borde = '#ff9800'; // Naranja
															} else {
																$origen_badge = '<span style="background-color: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; margin-left: 8px;">📋 Oficial Turno Registro Diario</span>';
																$color_borde = '#73a832'; // Verde
															}

															$recinto_lbl = !empty($t['nombre_recinto']) ? $t['nombre_recinto'] : 'S/E';
															$ptr_lbl = !empty($t['id_ptr']) ? " | 📋 PTR: " . $t['id_ptr'] : '';
															$oficial_asoc = !empty($t['oficial_tarea']) ? $t['oficial_tarea'] : strtoupper($row_registros['of_apellido'] . ", " . $row_registros['of_nombre']);
															
															$fecha_carga_formateada = '';
															if (!empty($t['fecha_hora'])) {
																$partes_fh = explode(' ', $t['fecha_hora']);
																if (count($partes_fh) == 2) {
																	$partes_f = explode('-', $partes_fh[0]);
																	if (count($partes_f) == 3) {
																		$fecha_carga_formateada = $partes_f[2] . '/' . $partes_f[1] . '/' . $partes_f[0] . ' ' . substr($partes_fh[1], 0, 5);
																	}
																}
															}
												?>
															<div style="background-color: white; border-radius: 6px; border: 1px solid #d4d4d4; border-left: 5px solid <?php echo $color_borde; ?>; box-shadow: 1px 1px 4px rgba(0,0,0,0.1); margin-bottom: 10px; padding: 10px 14px; position: relative;">
																
																<?php if (!empty($fecha_carga_formateada)) { ?>
																	<div style="position: absolute; right: 12px; top: 10px; font-style: italic; font-size: 11px; color: #666;">
																		<i class="far fa-clock mr-1"></i> <?php echo $fecha_carga_formateada; ?>
																	</div>
																<?php } ?>

																<div style="font-size: 15px; font-weight: bold; color: #1e5799; margin-bottom: 5px; padding-right: 110px;">
																	📍 Recinto: <?php echo $recinto_lbl . $ptr_lbl . $origen_badge; ?>
																</div>
																
																<div style="margin-bottom: 6px; font-size: 13px;">
																	<span style="background-color: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 3px; padding: 2px 6px; color: #2e7d32; font-weight: bold;">👷 Oficial: <?php echo $oficial_asoc; ?></span>
																	<?php if (!empty($t['ayudante_tarea'])) { ?>
																		<span style="background-color: #e3f2fd; border: 1px solid #bbdefb; border-radius: 3px; padding: 2px 6px; color: #1565c0; margin-left: 8px;">🤝 Asistió: <?php echo $t['ayudante_tarea']; ?></span>
																	<?php } ?>
																</div>

																<?php
																$meds = array();
																if ($t['tasa_dosis'] !== null && $t['tasa_dosis'] !== '') $meds[] = "<b>Dosis:</b> " . formatear_numero_acta($t['tasa_dosis']) . " µSv/h";
																if ($t['tritio_dac'] !== null && $t['tritio_dac'] !== '') $meds[] = "<b>Tritio:</b> " . formatear_numero_acta($t['tritio_dac']) . " DAC";
																if ($t['contaminacion_beta_gamma'] !== null && $t['contaminacion_beta_gamma'] !== '') $meds[] = "<b>β+γ:</b> " . formatear_numero_acta($t['contaminacion_beta_gamma']) . " Bq/cm²";
																if ($t['contaminacion_alfa'] !== null && $t['contaminacion_alfa'] !== '') $meds[] = "<b>α:</b> " . formatear_numero_acta($t['contaminacion_alfa']) . " Bq/cm²";

																if ($t['derrame_agua'] == 1) $meds[] = '<span style="color: #0277bd; font-weight: bold;">💧 Hubo Derrame</span>';
																if ($t['dispersion_aerosoles'] == 1) $meds[] = '<span style="color: #c62828; font-weight: bold;">☣️ Aerosoles: ' . formatear_numero_acta($t['aerosoles_dac']) . ' DAC</span>';

																if (count($meds) > 0) { ?>
																	<div style="font-size: 13px; color: #333; margin-bottom: 6px; background-color: #f9f9f9; padding: 4px 8px; border-radius: 4px;">
																		<?php echo implode(" | ", $meds); ?>
																	</div>
																<?php } ?>

																<div style="font-size: 14px; color: #222; line-height: 1.4;">
																	<b>Observaciones:</b> <?php echo nl2br(htmlspecialchars($t['observaciones'])); ?>
																</div>
															</div>
												<?php
														}
													}
												}
												if (!$hay_tareas && (empty($row_registros['novedades']) || trim($row_registros['novedades']) == '')) {
													echo '<div style="color: gray; font-style: italic; padding: 5px 0 10px 0;">- No se registraron tareas en este turno -</div>';
												}
												?>
											</div>
										</td>
									</tr>
								</table>
							</div>
							<br>
					<?php } while ($row_registros = mysql_fetch_assoc($registros));
					} ?>

				</div>
			</div>
		</div>
		<div class="footer" style="background-color: #457315">
			<div style="text-align: right;margin-top: 10px"></div>
		</div>
		<div class="header" style="background-color: #acdc74">
			<img style="position:relative;left:5px;top:5px;float:left;max-width:120px" src="../images/logo_nasa.png">
			<div style="position:relative;height:100%;margin:auto;font-size:25px;font-family:arial;font-weight:bold;left:-88px;top:53px;float:left;text-shadow: 4px 4px 9px rgba(150, 150, 150, 0.6);">NA-SA</div>
			<div style="text-align:left;margin-left:-154px;text-shadow: 4px 4px 9px rgba(150, 150, 150, 0.6);">
				<h2 style="font-size:25px">Registro Radiológico Online - SyR</h2>
			</div>
			<div style="text-align:right;top: 0px;text-shadow: none;position:absolute;right: 15px">
				<h2 style="text-shadow: none;font-size:15px">Sesión iniciada: <?php echo ucwords($_SESSION['nombre_apellido']) ?></h2>
			</div>
		</div>
	</div>
	<div id="bot_volver_id" style="position:fixed;right:5px;bottom:45px"><img src="../images/icono_volver.png" onclick="window.location.replace('inicio.php');" style="cursor:pointer;height:50px"></div>
</body>

</html>