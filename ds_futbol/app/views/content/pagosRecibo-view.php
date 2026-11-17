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

	/* El aviso se arma más abajo, cuando ya se conoce el correo del representante. */
	

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
		/* Un número de recibo más corto de lo esperado (registros
		   antiguos o editados a mano) dejaba $pairs sin la posición 4.
		   Sin la hora, el QR va con la fecha sola. */
		$recibo_hora = isset($pairs[4]) ? $pairs[4].":".$pairs[2].":".$pairs[0] : '';
		
	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "pagosList/");
		exit();
	}

	$sede=$insAlumno->informacionSede($datos["alumno_sedeid"]);
	/* Si la sede no se encuentra, $sede seguía siendo el PDOStatement y
	   leerlo como array más abajo era un error fatal («No se pudo
	   completar la operación»). Se usa lo que ya trae el recibo. */
	$sede = $sede->rowCount() == 1 ? $sede->fetch() : [
		'sede_id' => (int)$datos['alumno_sedeid'], 'sede_nombre' => $datos['sede_nombre'],
		'sede_direccion' => '', 'sede_telefono' => '', 'sede_email' => '',
	];

	/*----------  Datos para enviar el recibo  ----------*/
	/* Correo y WhatsApp salen del representante del alumno. Se limpian los
	   espacios dobles que deja CONCAT cuando falta un segundo nombre. */
	$limpio = static fn ($t) => trim(preg_replace('/\s+/u', ' ', (string)$t));
	$repreNombre  = $limpio($datos['REPRESENTANTE'] ?? '');
	$repreCorreo  = trim((string)($datos['CORREO_REP'] ?? ''));

	/*----------  Resultado del envío por correo (ReciboEnvio vuelve con /1/, /0/ o /2/)  ----------*/
	$paraQuien = $repreCorreo !== '' ? ' a ' . $repreCorreo : '';
	$alerta = match ((string)$envio) {
		'1' => ['tipo' => 'simple', 'icono' => 'success', 'titulo' => 'Recibo enviado',
		        'texto' => 'El recibo se envió por correo' . $paraQuien . '.'],
		'0' => ['tipo' => 'simple', 'icono' => 'error', 'titulo' => 'No se pudo enviar el correo',
		        'texto' => 'El recibo no se envió' . $paraQuien . '. Revise que el correo del representante esté bien escrito e inténtelo de nuevo; '
		                 . 'si sigue fallando, el servidor no tiene configurado el correo saliente. Mientras tanto, puede enviarlo por WhatsApp.'],
		'2' => ['tipo' => 'simple', 'icono' => 'warning', 'titulo' => 'Sin correo del representante',
		        'texto' => 'El representante no tiene un correo válido registrado. Agréguelo en su ficha o envíe el recibo por WhatsApp.'],
		default => '',
	};
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

			<?php
				/* El recibo en pantalla replica la hoja del PDF (pagosReciboPDF):
				   misma cabecera —logo, organización, contacto y fecha—, las mismas
				   filas y los montos con el QR al pie. Los estilos, en core.css
				   (.ds-recibo). Todo lo que viene de la base sale escapado. */
				$e = static fn ($t) => htmlspecialchars(trim(preg_replace('/\s+/u', ' ', (string)$t)), ENT_QUOTES, 'UTF-8');
				$tsRecibo    = strtotime($datos['pago_fecharegistro']) ?: time();
				$conceptoRec = implode(' ', array_filter(array_map('trim', [(string)$datos['RUBRO'], (string)$datos['torneo_nombre'], (string)$datos['pago_periodo']]), 'strlen'));
				$detalleRec  = trim((string)$datos['pago_concepto']);
				$saldoRec    = (float)$datos['SALDO_INICIAL'];
				$contactoRec = array_filter([
					'Dirección' => trim((string)$sede['sede_direccion']),
					'Celular'   => trim((string)$sede['sede_telefono']),
					'Correo'    => trim((string)($sede['sede_email'] ?? '')),
				], 'strlen');
				$logoRec = ds_logo_url((int)$sede['sede_id']);
			?>
			<!-- Content Header (Page header) -->
			<div class="app-content-header no-print">
				<div class="container-fluid">
					<div class="row mb-2 align-items-center">
						<div class="col-sm-6">
							<h1 class="m-0">Recibo <?php echo $e($datos['RUBRO'] . ' ' . $datos['pago_periodo']); ?></h1>
						</div>
						<div class="col-sm-6">
							<ol class="breadcrumb float-sm-end">
								<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
								<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>pagosList/">Pagos</a></li>
								<li class="breadcrumb-item active" aria-current="page">Recibo</li>
							</ol>
						</div>
					</div>
				</div>
			</div>
			<!-- /.content-header -->

			<section class="app-content">
				<div class="container-fluid">

					<?php
					/* Acciones arriba y fuera de la hoja: se ven sin desplazarse y no
					   salen al imprimir. Ver PDF es la principal; el id btn_correo se
					   conserva porque ajax.js le engancha la confirmación. */
					?>
					<div class="ds-recibo-acciones no-print">
						<button type="button" class="btn btn-outline-secondary btn-sm" onclick="cerrarVentana()">
							<i class="fas fa-times"></i> Cerrar
						</button>
						<div class="ds-recibo-acciones__grupo">
							<a href="<?php echo APP_URL.'pagosReciboPDF/'.(int)$pagoid.'/'; ?>"
							   class="btn btn-primary btn-sm" target="_blank" rel="noopener">
								<i class="fas fa-file-pdf"></i> Ver PDF
							</a>

							<?php if ($repreCorreo !== ''): ?>
								<a href="<?php echo APP_URL.'pagosReciboEnvio/'.(int)$pagoid.'/'; ?>"
								   class="btn btn-success btn-sm" id="btn_correo"
								   title="Enviar el PDF a <?php echo $e($repreCorreo); ?>">
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

					<article class="ds-recibo" aria-label="Recibo <?php echo $e($datos['pago_recibo']); ?>">

						<!-- Cabecera: logo, organización y contacto, fecha de emisión -->
						<header class="ds-recibo__cab">
							<div class="ds-recibo__logo">
								<?php if ($logoRec !== ''): ?>
									<img src="<?php echo $e($logoRec); ?>" alt="Logo de <?php echo $e(ds_nombre_organizacion((int)$sede['sede_id'])); ?>">
								<?php endif; ?>
							</div>

							<div class="ds-recibo__org">
								<h2 class="ds-recibo__nombre"><?php echo $e(ds_nombre_organizacion_may((int)$sede['sede_id'])); ?></h2>
								<?php if ($contactoRec): ?>
									<dl class="ds-recibo__contacto">
										<?php foreach ($contactoRec as $etiqueta => $valor): ?>
											<div><dt><?php echo $etiqueta; ?>:</dt> <dd><?php echo $e($valor); ?></dd></div>
										<?php endforeach; ?>
									</dl>
								<?php endif; ?>
							</div>

							<div class="ds-recibo__fecha">
								<span class="ds-recibo__fecha-titulo">Fecha de emisión</span>
								<table>
									<thead><tr><th scope="col">Día</th><th scope="col">Mes</th><th scope="col">Año</th></tr></thead>
									<tbody><tr>
										<td><?php echo date('d', $tsRecibo); ?></td>
										<td><?php echo date('m', $tsRecibo); ?></td>
										<td><?php echo date('Y', $tsRecibo); ?></td>
									</tr></tbody>
								</table>
							</div>
						</header>

						<!-- Cuerpo: importe, número y datos del pago -->
						<div class="ds-recibo__cuerpo">
							<div class="ds-recibo__fila ds-recibo__fila--total">
								<div class="ds-recibo__par">
									<span class="ds-recibo__etq">Por</span>
									<span class="ds-recibo__importe">$<?php echo number_format((float)$datos['PAGO_INICIAL'], 2); ?></span>
								</div>
								<div class="ds-recibo__par ds-recibo__par--num">
									<span class="ds-recibo__etq">Recibo</span>
									<span class="ds-recibo__numero"><?php echo $e($datos['pago_recibo']); ?></span>
								</div>
							</div>

							<div class="ds-recibo__fila">
								<span class="ds-recibo__etq">Recibo de:</span>
								<span><?php echo $e($datos['alumno_primernombre'].' '.$datos['alumno_segundonombre'].' '.$datos['alumno_apellidopaterno'].' '.$datos['alumno_apellidomaterno']); ?>
									<span class="ds-recibo__nota">(<?php echo date('Y', strtotime($datos['alumno_fechanacimiento'])); ?>)</span></span>
							</div>
							<div class="ds-recibo__fila">
								<span class="ds-recibo__etq">La cantidad de:</span>
								<span><?php echo $e(ucfirst($insAlumno->textoLetras($datos['PAGO_INICIAL']))); ?></span>
							</div>
							<div class="ds-recibo__fila">
								<span class="ds-recibo__etq">Por concepto de:</span>
								<span><?php echo $e($conceptoRec . ($detalleRec !== '' ? ', ' . $detalleRec : '')); ?></span>
							</div>
							<div class="ds-recibo__fila">
								<span class="ds-recibo__etq">Forma de pago:</span>
								<span><?php echo $e($datos['FORMAPAGO']); ?></span>
							</div>

							<!-- Pie: montos y QR -->
							<div class="ds-recibo__pie">
								<div class="ds-recibo__montos">
									<div class="ds-recibo__montos-titulo">
										Monto
										<?php if ($saldoRec > 0): ?>
											<span class="badge text-bg-warning">Saldo pendiente</span>
										<?php else: ?>
											<span class="badge text-bg-success">Pagado</span>
										<?php endif; ?>
									</div>
									<dl>
										<div><dt>Subtotal:</dt><dd>$<?php echo number_format((float)$datos['DEUDA_INICIAL'], 2); ?></dd></div>
										<div><dt>Abono:</dt><dd>$<?php echo number_format((float)$datos['PAGO_INICIAL'], 2); ?></dd></div>
										<div class="ds-recibo__saldo<?php echo $saldoRec > 0 ? ' is-pendiente' : ''; ?>">
											<dt>Saldo:</dt><dd>$<?php echo number_format($saldoRec, 2); ?></dd>
										</div>
									</dl>
								</div>
								<div class="ds-recibo__qr ds-recibo-qr">
									<?php
										/* ds-recibo-qr: en pantalla pequeña el SVG escala (core.css);
										   al imprimir conserva su tamaño para leerse en papel. */
										echo $generator->render_svg($symbology, "Recibo ".$datos["pago_recibo"]."\n".$datos["pago_fecharegistro"]." | ".$recibo_hora."\n".$sede['sede_nombre']."\n".$sede["sede_telefono"]."\n".$sede["sede_email"], $optionsQR);
									?>
								</div>
							</div>
						</div>
					</article>
					<!-- /.ds-recibo -->

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
	/* Alumno adulto (parentesco 4TT, migración 086): recibe su propio recibo. */
	$waDatos['subtitulo'] = ($datos['PARENTESCO_REP'] ?? '') === '4TT'
		? 'Alumno titular'
		: 'Representante de ' . $waDatos['alumno'];
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
			/* Sin el /1/ o /0/ en la dirección: recargar no vuelve a avisar. */
			history.replaceState(null, '', location.pathname.replace(/\/[012]\/?$/, '/'));
		});
		</script>
	<?php endif; ?>

  </body>
</html>