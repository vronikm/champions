<?php	

	use app\controllers\pagosController;

	include 'app/lib/barcode.php';
	
	$generator = new barcode_generator();
	$symbology="qr";
	$optionsQR=array('sx'=>4,'sy'=>4,'p'=>-10);	
	


	$insAlumno = new pagosController();	

	$pagoid = ds_id_de_url($url, 1, APP_URL . 'pagosList/');
	//$mensaje=$insLogin->limpiarCadena($url[2]);	

	$alerta = "";

	// Capturamos el valor enviado en la URL
	$envio = $url[2] ?? ""; // <---- Asegúrate que $url esté disponible. $url[2] sería 1 o 0

	if($envio !== ""){
		if($envio == "1"){
			$alerta = [
				"tipo" => "simple",
				"titulo" => "Correo enviado",
				"texto" => "El correo fue enviado exitosamente.",
				"icono" => "success"
			];
		} elseif($envio == "0"){
			$alerta = [
				"tipo" => "simple",
				"titulo" => "Error de envío",
				"texto" => "No se pudo enviar el correo. Por favor, intente nuevamente.",
				"icono" => "error"
			];
		}
	}
	

	$datos=$insAlumno->generarRecibo($pagoid);
	
	if($datos->rowCount()==1){
		$datos=$datos->fetch(); 

		$fecha_recibo = strrev($datos["pago_recibo"]);
		$first12Chars = strrev(substr($datos["pago_recibo"], 0, 12));
		$nombre_sede  = $datos["sede_nombre"];
		
		$pairs = [];
		$length = strlen($first12Chars);

		for ($i = 0; $i < $length; $i += 2) {
			$pairs[] = substr($first12Chars, $i, 2);
		}
		$recibo_hora = $pairs[4].":".$pairs[2].":".$pairs[0];
		
	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "pagosList/");
		exit();
	}

	$sede=$insAlumno->informacionSede($datos["alumno_sedeid"]);
	if($sede->rowCount()==1){
		$sede=$sede->fetch();
	}

	/*----------  Datos para enviar el recibo  ----------*/
	/* Correo y WhatsApp salen del representante del alumno. Se limpian los
	   espacios dobles que deja CONCAT cuando falta un segundo nombre. */
	$limpio = static fn ($t) => trim(preg_replace('/\s+/u', ' ', (string)$t));
	$repreNombre  = $limpio($datos['REPRESENTANTE'] ?? '');
	$repreCorreo  = trim((string)($datos['CORREO_REP'] ?? ''));
	$repreCelular = preg_replace('/\D/', '', (string)($datos['CELULAR_REP'] ?? ''));
	$enlaceRecibo = $insAlumno->enlacePublicoRecibo((int)$pagoid);
	$waDatos = [
		'tipo'     => 'pago',
		'escuela'  => ds_nombre_organizacion(),
		'repre'    => $repreNombre,
		'celular'  => $repreCelular,
		'alumno'   => $limpio($datos['alumno_primernombre'].' '.$datos['alumno_segundonombre'].' '.$datos['alumno_apellidopaterno'].' '.$datos['alumno_apellidomaterno']),
		'concepto' => $limpio($datos['RUBRO'].' '.$datos['torneo_nombre'].' '.$datos['pago_periodo']),
		'detalle'  => $limpio($datos['pago_concepto']),
		'valor'    => number_format((float)$datos['PAGO_INICIAL'], 2, '.', ''),
		'saldo'    => number_format((float)$datos['SALDO_INICIAL'], 2, '.', ''),
		'fecha'    => date('d/m/Y', strtotime($datos['pago_fecha'] ?: $datos['pago_fecharegistro'])),
		'recibo'   => (string)$datos['pago_recibo'],
		'enlace'   => $enlaceRecibo['url'],
		'vence'    => $enlaceRecibo['vence'] ? date('d/m/Y', $enlaceRecibo['vence']) : '',
		'pdf'      => APP_URL . 'pagosReciboPDF/' . (int)$pagoid . '/',
	];

?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Recibo';
	$extras      = array (0 => 'dropzone',1 => 'swal',);
	require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">

		<!-- Preloader -->
		<!--?php require_once "app/views/inc/preloader.php"; ?-->
		<!-- /.Preloader -->

		<!-- Navbar -->
		<?php require_once "app/views/inc/navbar.php"; ?>
		<!-- /.navbar -->

		<!-- Main Sidebar Container -->
		<?php require_once "app/views/inc/main-sidebar.php"; ?>
		<!-- /.Main Sidebar Container -->  

		<!-- vista -->
		<div class="app-main">

			<!-- Content Header (Page header) -->
			<div class="app-content-header">
				<div class="container-fluid">
					<div class="row mb-2">
						<div class="col-sm-6">
							<h1 class="m-0">Recibo <?php echo $datos['RUBRO']." ".$datos['pago_periodo'] ; ?></h1>
						</div><!-- /.col -->
						<div class="col-sm-6">
							<ol class="breadcrumb float-sm-end">
								<li class="breadcrumb-item"><a href="#">Inicio</a></li>
								<li class="breadcrumb-item active">Ficha Alumno</li>
							</ol>
						</div><!-- /.col -->
					</div><!-- /.row -->
				</div><!-- /.container-fluid -->
			</div>
			<!-- /.content-header -->
			
			<section class="app-content">
				<div class="container-fluid">
					<div class="row">
						<div class="col-1">
						</div>
						<div class="col-10">
							<!-- Main content -->
							<div class="invoice p-3 mb-3">							
								<!-- info row -->
								<div class="row invoice-info">
									<div class="col-sm-6 invoice-col">										
										<address class="text-center">												
											<img src="<?php echo ds_logo_url((int)$sede["sede_id"]) ?>" style="width: 120px; height: 120px;"/>										
											<br>Dirección: <?php echo $sede["sede_direccion"]; ?><br>
											Celular: <?php echo $sede["sede_telefono"]; ?> - ECUADOR										
										</address>
									</div>
									<!-- /.col -->
									<div class="col-sm-6 invoice-col">									
										<address class="text-center">	
											<strong class="profile-username"><?php echo ds_nombre_organizacion_may((int)$sede["sede_id"]); ?></strong><br><br>										
											<div class="row">
												<div class="col-12 table-responsive">
													<div class="row">
														<div class="col-4"></div>														
														<div class="col-4">
															<div class="table-responsive">
															<table class="table table-striped table-sm">
																<thead>
																	<tr style="font-size: 14px">
																		<th>DIA</th>
																		<th>MES</th>
																		<th>AÑO</th>																
																	</tr>
																</thead>
																<tbody>
																	<tr style="font-size: 14px">
																		<td><?php echo  date('d', strtotime($datos['pago_fecharegistro'])); ?></td>
																		<td><?php echo date('m', strtotime($datos['pago_fecharegistro'])); ?></td>
																		<td><?php echo date('Y', strtotime($datos['pago_fecharegistro'])); ?></td>												
																	</tr>														
																</tbody>
															</table>
															</div>
														</div>
														<div class="col-4"></div>
													</div>	
												</div>
												<!-- /.col -->
											</div>
										</address>
									</div>
									<!-- /.col -->								
								</div>
								<!-- Table row -->
								<div class="row">
									<div class="col-12 table-responsive">
										<table class="table table-striped table-sm">											
											<tbody>
												<tr>													
													<th style="width:200px;">POR</th>	
													<th>$ <?php echo $datos['PAGO_INICIAL']; ?></th>
													<th class="text-end">RECIBO </th>
													<th class="text-center"><?php echo $datos['pago_recibo']; ?></th>																							
												</tr>	
												<tr>													
													<th>Recibo de: </th>	
													<td colspan="3"><?php echo $datos['alumno_primernombre']." ".$datos['alumno_segundonombre']." ".$datos['alumno_apellidopaterno']." ".$datos['alumno_apellidomaterno']." (".date('Y', strtotime($datos['alumno_fechanacimiento'])).")"; ?></td>																										
												</tr>	
												<tr>													
													<th>La Cantidad de: </th>	
													<td colspan="3"><?php echo ucfirst($insAlumno->textoLetras($datos['PAGO_INICIAL'])); ?></td>																										
												</tr>		
												<tr>													
													<th>Por Concepto de: </th>	
													<td colspan="3"><?php echo $datos['RUBRO']." ".$datos['torneo_nombre']." ".$datos['pago_periodo'].", ".$datos['pago_concepto']; ?></td>																										
												</tr>	
												<tr>													
													<th>Forma de pago: </th>	
													<td colspan="3"><?php echo $datos['FORMAPAGO']; ?></td>																										
												</tr>							
											</tbody>
										</table>
									</div>
									<!-- /.col -->
								</div>
								<!-- /.row -->
								<div class="row">
									<div class="col-4">
										<p class="lead">MONTO</p>

										<div class="table-responsive table-sm">
											<table class="table">
												<tr>
													<th style="width:50%">SUBTOTAL:</th>
													<td>$ <?php echo number_format($datos['DEUDA_INICIAL'], 2); ?></td>
												</tr>
												<tr>
													<th>ABONO:</th>
													<td>$ <?php echo $datos['PAGO_INICIAL']; ?></td>
												</tr>
												<tr>
													<th>SALDO:</th>
													<td>$ <?php echo $datos['SALDO_INICIAL']; ?></td>
												</tr>												
											</table>
										</div>
									</div>
									<!-- accepted payments column -->
									<div class="col-4">										
									</div>

									<div class="col-4 ds-recibo-qr">
										<?php /* ds-recibo-qr: el SVG sale con 160 px fijos y en un
												 telefono se sale 24 px de la pantalla, porque la
												 columna mide unos 110. La regla de core.css lo deja
												 escalar SOLO en pantalla pequenia; al imprimir
												 conserva su tamanio, que es lo que hace falta para
												 que el codigo se pueda leer en papel. */ ?>
										<?php
											('Content-Type: image/svg+xml');
											$svg = $generator->render_svg($symbology,"Recibo ".$datos["pago_recibo"]. "\n".$datos["pago_fecharegistro"]. " | ".$recibo_hora."\n".$sede['sede_nombre']."\n".$sede["sede_telefono"]."\n".$sede["sede_email"], $optionsQR);
											echo $svg;
										?>
									</div>
									<!-- /.col -->
									
									<!-- /.col -->
								</div>
								<!-- /.row -->
								

								<!-- this row will not appear when printing -->
								<div class="row no-print">
									<div class="col-12">

										<!--button type="button" class="btn btn-success float-end btn-sm" style="margin-right: 60px;"><i class="far fa-credit-card"></i> Enviar recibo
										</button>
										<button type="button" class="btn btn-primary float-end" style="margin-right: 5px;">
											<i class="fas fa-download"></i> Descargar recibo
										</button-->
										<?php
										/* Pie en flex, no en float.
										   Con float-end los botones se colocan en orden
										   INVERSO al del código, así que el orden visual y
										   el orden de lectura del archivo no coincidían, y
										   el hueco entre ellos se sostenía con un
										   margin-right: 135px puesto a ojo. Aquí el orden
										   del código es el orden en pantalla y el ms-auto
										   empuja el grupo a la derecha sin números mágicos.
										   El id btn_correo se conserva: ajax.js le engancha
										   la confirmación y lee su href. */
										?>
										<div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
											<button class="btn btn-dark btn-sm" onclick="cerrarVentana()">Cerrar</button>

											<a href="<?php echo APP_URL.'pagosReciboPDF/'.$pagoid.'/'; ?>"
											   class="btn btn-dark btn-sm ms-auto" target="_blank">
												<i class="fas fa-print"></i> Ver recibo
											</a>

											<?php if ($repreCorreo !== ''): ?>
												<a href="<?php echo APP_URL.'pagosReciboEnvio/'.$pagoid.'/'; ?>"
												   class="btn btn-success btn-sm" id="btn_correo"
												   title="Enviar el PDF a <?php echo htmlspecialchars($repreCorreo, ENT_QUOTES, 'UTF-8'); ?>">
													<i class="fas fa-envelope"></i> Enviar por correo
												</a>
											<?php else: ?>
												<span class="d-inline-block" tabindex="0" title="El representante no tiene correo registrado">
													<button type="button" class="btn btn-success btn-sm" disabled>
														<i class="fas fa-envelope"></i> Enviar por correo
													</button>
												</span>
											<?php endif; ?>

											<button type="button" class="btn btn-sm btn-whatsapp" data-bs-toggle="modal" data-bs-target="#modalWhatsapp">
												<i class="fab fa-whatsapp"></i> Enviar por WhatsApp
											</button>
										</div>

									</div>
								</div>
							</div>
							<!-- /.invoice -->							
						</div><!-- /.col -->
						<div class="col-1">
						</div>
					</div><!-- /.row -->
				</div><!-- /.container-fluid -->
			</section>
      
		</div>
		<!-- /.vista -->

		<?php require_once "app/views/inc/footer.php"; ?>

		<!-- Control Sidebar -->
		<aside class="control-sidebar control-sidebar-dark">
		<!-- Control sidebar content goes here -->
		</aside>
      <!-- /.control-sidebar -->
    </div>
    <!-- ./wrapper -->

    
	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<!-- Bootstrap 4 -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	
	<!-- Select2 -->
	<!-- Bootstrap4 Duallistbox -->
	<!-- InputMask -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/inputmask/jquery.inputmask.min.js"></script>
	<!-- date-range-picker -->
	<!-- bootstrap color picker -->
	<!-- Tempusdominus Bootstrap 4 -->
	<!-- Bootstrap Switch -->
	<!-- BS-Stepper -->
	
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
		
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>

	<!--script src="app/views/dist/js/main.js" ></script-->
	
	<!-- fileinput -->
    
	<script>
        // Esta función se llama cuando el botón es clickeado
        function printPage() {
            window.addEventListener("load", window.print());
        }

		function cerrarVentana() {
			window.close();
		}
    </script>

	<?php
	/* Envío por WhatsApp: la pieza es del núcleo y la comparten Fútbol y
	   Arena. Aquí sólo se redacta lo propio de un recibo de pago. */
	$waDatos['subtitulo'] = 'Representante de ' . $waDatos['alumno'];
	$waDatos['intro']     = $waDatos['escuela'] . ' le confirma el pago registrado:';
	$waDatos['lineas']    = array_values(array_filter([
		'🧾 Recibo N.º ' . $waDatos['recibo'],
		'⚽ Alumno: ' . $waDatos['alumno'],
		'📌 Concepto: ' . $waDatos['concepto'] . ($waDatos['detalle'] !== '' ? ' · ' . $waDatos['detalle'] : ''),
		'💵 Valor pagado: USD ' . $waDatos['valor'],
		(float)$waDatos['saldo'] > 0 ? '⏳ Saldo pendiente: USD ' . $waDatos['saldo'] : '',
		'📅 Fecha: ' . $waDatos['fecha'],
	]));
	require DS_HUB_PATH . "ds_core/inc/whatsappRecibo.php";
	?>

	<?php if($alerta): ?>
		<script>
		document.addEventListener("DOMContentLoaded", function() {
			let alerta = <?php echo json_encode($alerta); ?>;
			alertas_ajax(alerta); // Usamos tu función de alertas que ya tienes en ajax.js
		});
		</script>
	<?php endif; ?>

  </body>
</html>