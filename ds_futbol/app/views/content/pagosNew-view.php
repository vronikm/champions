<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\pagosController;
	$insAlumno = new pagosController();	

	$alumno = ds_id_de_url($url, 1, APP_URL . 'pagosList/');

	$datos=$insAlumno->BuscarAlumno($alumno);
	
	if($datos->rowCount()==1){
		$datos=$datos->fetch(); 
		
		if ($datos['alumno_imagen']!=""){
			$foto = media_url('alumno', $datos['alumno_imagen']);
		}else{
			$foto=ds_recurso('ds_futbol/app/views/dist/img/alumno.jpg');
		}

		if($datos['pendiente']==1){
			$pendiente = 'Pendiente';
			$clase = '<a class="float-end text-danger">';
		}else{
			$pendiente = 'Al día';
			$clase = '<a class="float-end">';
		}
	
		$sede=$insAlumno->informacionSede($datos['alumno_sedeid']);
		if($sede->rowCount()==1){
			$sede=$sede->fetch(); 
		}

		$mesesEnEspanol = [
			'January' => 'Enero',
			'February' => 'Febrero',
			'March' => 'Marzo',
			'April' => 'Abril',
			'May' => 'Mayo',
			'June' => 'Junio',
			'July' => 'Julio',
			'August' => 'Agosto',
			'September' => 'Septiembre',
			'October' => 'Octubre',
			'November' => 'Noviembre',
			'December' => 'Diciembre'
		];

		$fechahoy = date('Y-m-d');
		// Crear un objeto DateTime
		$dateTime = new DateTime($fechahoy);
		// Obtener el nombre completo del mes
		$nombreMes = $dateTime->format('F');
		// Obtener el año
		$nombreMesEspanol = $mesesEnEspanol[$nombreMes];

		$anio = $dateTime->format('Y');
		$nombreMes = $nombreMesEspanol." / ".$anio;

		$saldo = "0.00";
		$disabled = "";
		$alert = "alert-info";
		$alerta = "N";
		$beca = "N";
		$textodescuento = "";
		$textodescripcion = "";
		$rubro_valor = $sede['sede_pension'] ?? "0.00";
		$rubro_inscripcion = $sede['sede_inscripcion'] ?? "0.00";
		
		$descuento=$insAlumno->AlumnoDescuento($alumno);
		if($descuento->rowCount()==1){
			$descuento=$descuento->fetch(); 
			if($descuento["descuento_rubroid"] == 'DBC'){
				$textodescuento ="Estudiante tiene Beca DEL 100%. ";
				$textodescripcion =$descuento["descuento_detalle"];
				$rubro_valor = $descuento["descuento_valor"];
				$rubro_inscripcion = $descuento['descuento_valor'];
				$disabled = "disabled";
				$alert = "alert-warning";
				$alerta = "S";		
				$beca = "S";	
			}if($descuento["descuento_rubroid"] == 'DBP'){
				$textodescuento ="Estudiante tiene Beca del 50%. ";
				$textodescripcion =$descuento["descuento_detalle"];
				$rubro_valor = $descuento["descuento_valor"];
				$rubro_inscripcion = $sede['sede_inscripcion'];
				$alert = "alert-warning";
				$disabled = " ";
				$alerta = "S";	
				$beca = "P";				
			}if($descuento["descuento_rubroid"] == 'DDS'){
				$textodescuento ="Estudiante tiene Descuento. ";
				$textodescripcion =$descuento["descuento_detalle"];
				$rubro_valor = $descuento["descuento_valor"];
				$rubro_inscripcion = $sede['sede_inscripcion'];
				$alert = "alert-info";
				$disabled = " ";
				$alerta = "S";	
				$beca = "N";				
			}
		}else{
			$textodescuento ="";
			$textodescripcion ="";
			$rubro_valor = $sede['sede_pension'];			
			$rubro_inscripcion = $sede['sede_inscripcion'];
			$disabled = " ";
			$alerta = "N";
			$beca = "N";
		}

		/* Las pensiones no pagadas desde la última: para un ACTIVO, hasta
		   el mes en curso. Un INACTIVO no entrena, y contarle los meses
		   desde que dejó de venir inflaba la deuda; pero si vino después de
		   su último pago, los meses hasta su última asistencia los entrenó
		   sin pagar y se le cobran (como en Pagos y en Cobranza › Pensiones
		   Inactivos). Lo registrado con saldo (pagosPendintes) se muestra
		   igual. */
		/* La regla vive en pagosController::resumenDeuda(), compartida con
		   la ficha de asistencia (buscarAsistencia). */
		$deuda = $insAlumno->resumenDeuda((int)$alumno);
		$ultimaAsistencia = $deuda['ultimaAsistencia'];
		if($deuda['pendiente']){
			$pendiente = "Pendiente";
			$clase = '<a class="float-end text-danger">';
		}

	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "pagosList/");
		exit();
	}
?>

<?php
	/*
	| Presentación (2026-11-27): pensada primero para el celular.
	| Respaldo de la versión anterior: etiqueta git
	| respaldo/pagosNew-antes-ux-2026-11-27 y copia en
	| C:\wamp64\respaldos\champions_cf_new\2026-11-27_pagosNew\.
	| Los nombres, ids y valores que viajan al servidor no cambian.
	*/
	/* Hay textos que ya se guardan escapados (limpiarCadena): se
	   decodifican antes para no mostrar «&quot;». */
	$pgE = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$esPendiente = ($pendiente === 'Pendiente');
	$sinPago     = ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg');
	$fechaCorta  = static fn($f) => $f ? date('d/m/Y', strtotime($f)) : '';

	/* Lo que debe: pensiones sin pagar + rubros con saldo. */
	$filasDeuda = $deuda['filas'];
	$totalDeuda = $deuda['total'];
	$nDeuda     = $deuda['n'];

	/* El comprobante: miniatura pequeña y un botón; el <input type=file>
	   nativo queda encima del botón, invisible. foto.js entiende este
	   marcado (fileinput-new / fileinput-exists). */
	$pgComprobante = static function (string $id, string $imgId, string $dis) use ($sinPago, $pgE): string {
		return '
			<label class="form-label" for="' . $id . '">Comprobante</label>
			<div class="fileinput fileinput-new ds-pg-foto" data-provides="fileinput">
				<div class="fileinput-new thumbnail" data-trigger="fileinput"><img src="' . $pgE($sinPago) . '" alt="Sin comprobante" id="' . $imgId . '"></div>
				<div class="fileinput-preview fileinput-exists thumbnail"></div>
				<div class="ds-pg-foto__botones">
					<span class="bton bton-white bton-file btn btn-outline-secondary">
						<i class="fas fa-camera me-1"></i><span class="fileinput-new">Subir foto</span><span class="fileinput-exists">Cambiar</span>
						<input type="file" name="pago_archivo" id="' . $id . '" accept="image/jpeg,image/png" ' . $dis . '>
					</span>
					<a href="#" class="bton bton-orange fileinput-exists btn btn-link text-danger" data-bs-dismiss="fileinput">Quitar</a>
					<div class="form-text">JPG o PNG, hasta 3 MB</div>
				</div>
			</div>';
	};

	/* Historial de cada rubro: tabla en escritorio, tarjetas en el celular
	   (las etiquetas de cada dato las pone el script desde el <thead>). */
	$pgHistorial = static function (string $titulo, string $tablaId, array $columnas, string $filas): string {
		$n = substr_count($filas, '<tr');
		$html = '<section class="ds-pg-historial">
			<h3 class="ds-pg-historial__titulo">' . $titulo . ' <span class="badge rounded-pill text-bg-secondary">' . $n . '</span></h3>';
		if ($n === 0) {
			return $html . '<p class="ds-pg-historial__vacio"><i class="far fa-folder-open me-1"></i>Aún no hay pagos registrados.</p></section>';
		}
		$th = '';
		foreach ($columnas as $c) { $th .= '<th>' . $c . '</th>'; }
		return $html . '
			<div class="table-responsive">
				<table id="' . $tablaId . '" class="table table-bordered table-striped table-sm ds-pg-tabla">
					<thead><tr>' . $th . '<th class="ds-pg-tabla__acciones">Opciones</th></tr></thead>
					<tbody>' . $filas . '</tbody>
				</table>
			</div>
		</section>';
	};

	$pgAviso = static function (string $clase, string $titulo, string $detalle) use ($pgE): string {
		return '<div class="alert ' . $clase . ' ds-pg-aviso" role="status">
			<i class="fas fa-info-circle"></i>
			<div><strong>' . $pgE(trim($titulo)) . '</strong> ' . $pgE($detalle) . '</div>
		</div>';
	};

	$accionesPago = ds_acciones_form('', ['limpiar' => true, 'respaldo' => APP_URL . 'pagosList/']);
	$colsMes = ['No', 'Fecha registro', 'Mes/Año', 'Pago', 'Saldo', 'Recibo', 'Estado'];
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Registro de pagos';
	$extras      = array (0 => 'lightbox',1 => 'swal',);
	$cabeceraExtra = <<<'CSS'
	<style>
		input:invalid { box-shadow: 0 0 2px 1px var(--bs-danger); }
		input:focus:invalid { box-shadow: none; }

		/* ---------- Ficha del alumno ---------- */
		.ds-pg-alumno__cabeza { display: flex; align-items: center; gap: .875rem; }
		.ds-pg-alumno__foto {
			width: 72px; height: 72px; flex: 0 0 72px;
			border-radius: 50%; object-fit: cover;
			border: 2px solid var(--bs-border-color); background: var(--bs-tertiary-bg);
		}
		.ds-pg-alumno__id { min-width: 0; }
		.ds-pg-alumno__nombre { font-size: 1.15rem; font-weight: 600; margin: 0; line-height: 1.25; overflow-wrap: anywhere; }
		.ds-pg-alumno__cedula { font-size: .85rem; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; }
		.ds-pg-alumno__chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .35rem; }
		.ds-pg-alumno__chips .badge { font-weight: 500; }
		.ds-pg-alumno__datos {
			display: grid; grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr));
			gap: .5rem 1rem; margin: 1rem 0 0; padding: .75rem 0 0;
			border-top: 1px solid var(--bs-border-color-translucent);
		}
		.ds-pg-alumno__datos dt { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); }
		.ds-pg-alumno__datos dd { margin: 0; font-variant-numeric: tabular-nums; }

		/* ---------- Pestañas: una sola línea que se desliza en el celular ---------- */
		.ds-pg-tabs { flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; gap: .25rem; -webkit-overflow-scrolling: touch; }
		.ds-pg-tabs::-webkit-scrollbar { display: none; }
		.ds-pg-tabs .nav-link { white-space: nowrap; padding: .5rem .8rem; }
		.ds-pg-tabs .nav-link i { margin-right: .35rem; opacity: .85; }

		/* ---------- Formulario ---------- */
		.ds-pg-form .form-label { font-weight: 500; margin-bottom: .3rem; }
		.ds-pg-form input[type="date"] { min-width: 0; }
		.ds-pg-form .ds-pg-monto { text-align: right; font-variant-numeric: tabular-nums; }
		.ds-pg-form .form-control[readonly] { background-color: var(--bs-secondary-bg); }
		.ds-pg-aviso { display: flex; gap: .6rem; align-items: flex-start; }
		.ds-pg-aviso i { margin-top: .2rem; }

		/* Comprobante: miniatura y botón en una línea, no una imagen de 158 px. */
		.ds-pg-foto { display: flex !important; align-items: center; gap: .75rem; }
		.ds-pg-foto .thumbnail {
			width: 64px; height: 64px; flex: 0 0 64px;
			border: 1px dashed var(--bs-border-color); border-radius: var(--bs-border-radius);
			background: var(--bs-tertiary-bg); overflow: hidden; cursor: pointer;
		}
		.ds-pg-foto .thumbnail img { width: 100%; height: 100%; object-fit: cover; }
		.ds-pg-foto.fileinput-new .fileinput-preview { display: none !important; }
		.ds-pg-foto__botones { min-width: 0; }
		.ds-pg-foto .bton-file { position: relative; overflow: hidden; }
		.ds-pg-foto .bton-file input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; font-size: 0; }
		.ds-pg-foto .bton-file:focus-within { outline: 2px solid var(--bs-primary); outline-offset: 2px; }
		.ds-pg-foto .form-text { margin-top: .15rem; font-size: .75rem; }

		/* ---------- Historial ---------- */
		.ds-pg-historial { margin-top: 1.5rem; }
		.ds-pg-historial__titulo { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem; }
		.ds-pg-historial__vacio { color: var(--bs-secondary-color); padding: 1rem; border: 1px dashed var(--bs-border-color); border-radius: var(--bs-border-radius); text-align: center; margin: 0; }
		.ds-pg-tabla td, .ds-pg-tabla th { vertical-align: middle; }
		.ds-pg-tabla td:nth-last-child(-n+1) { white-space: nowrap; }
		.ds-pg-tabla .ds-pg-tabla__acciones { width: 1%; }
		.ds-pg-tabla td:last-child { display: flex; flex-direction: row-reverse; justify-content: flex-start; gap: .3rem; }
		.ds-pg-tabla td:last-child .btn { float: none !important; margin: 0 !important; }
		.ds-pg-tabla td:last-child form { margin: 0; }

		@media (max-width: 767.98px) {
			/* Cada pago es una tarjeta: nada se sale de la pantalla y los
			   botones quedan a la vista. */
			.ds-pg-historial .table-responsive { overflow: visible; }
			.ds-pg-tabla, .ds-pg-tabla tbody { display: block; border: 0; }
			.ds-pg-tabla thead { display: none; }
			.ds-pg-tabla tr {
				display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem .75rem;
				padding: .75rem .9rem; margin-bottom: .6rem;
				border: 1px solid var(--bs-border-color); border-left-width: 4px;
				border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg);
			}
			.ds-pg-tabla tr.text-danger  { border-left-color: var(--bs-danger); }
			.ds-pg-tabla tr.text-primary { border-left-color: var(--bs-primary); }
			.ds-pg-tabla td {
				display: block; border: 0 !important; padding: 0 !important;
				box-shadow: none !important; background: none !important; min-width: 0;
				font-variant-numeric: tabular-nums; white-space: normal !important; overflow-wrap: anywhere; font-size: .9rem;
			}
			.ds-pg-tabla td::before {
				content: attr(data-label); display: block;
				font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em;
				color: var(--bs-secondary-color);
			}
			.ds-pg-tabla td:first-child { display: none; }
			.ds-pg-tabla td:last-child {
				grid-column: 1 / -1; display: flex; flex-wrap: wrap;
				padding-top: .6rem !important; border-top: 1px dashed var(--bs-border-color) !important;
			}
			.ds-pg-tabla td:last-child::before { display: none; }
			.ds-pg-tabla td:last-child .btn { min-height: 2.5rem; min-width: 2.5rem; display: inline-flex; align-items: center; justify-content: center; }

			/* Guardar siempre a mano mientras se llena el formulario. */
			.ds-pg-form .ds-acciones {
				position: sticky; bottom: 0; z-index: 5;
				display: grid; grid-template-columns: 1fr 1fr; gap: .5rem;
				margin: 1rem -1rem 0; padding: .75rem 1rem;
				background: var(--bs-body-bg); border-top: 1px solid var(--bs-border-color);
				box-shadow: 0 -6px 14px rgba(0, 0, 0, .06);
			}
			.ds-pg-form .ds-acciones .btn { width: auto; min-width: 0; }
			.ds-pg-form .ds-acciones [type="submit"] { grid-column: 1 / -1; order: -1; min-height: 2.75rem; }
		}

		@media (min-width: 992px) {
			.ds-pg-col-alumno .card { position: sticky; top: 1rem; }
		}
	</style>
CSS;
	require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">

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
							<h1 class="m-0">Pagos alumno</h1>
						</div><!-- /.col -->
						<div class="col-sm-6">
							<ol class="breadcrumb float-sm-end">
								<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
								<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>pagosList/">Pagos</a></li>
								<li class="breadcrumb-item active" aria-current="page"><?php echo $pgE($datos['alumno_primernombre'].' '.$datos['alumno_apellidopaterno']); ?></li>
							</ol>
						</div><!-- /.col -->
					</div><!-- /.row -->
				</div><!-- /.container-fluid -->
			</div>
			<!-- /.content-header -->

			<!-- Main content -->
			<section class="app-content">
				<div class="container-fluid">
					<div class="row g-3">

						<!-- Ficha del alumno -->
						<div class="col-lg-4 col-xl-3 ds-pg-col-alumno">
							<div class="card card-primary card-outline ds-pg-alumno">
								<div class="card-body">
									<div class="ds-pg-alumno__cabeza">
										<a href="<?php echo $pgE($foto); ?>" data-bs-toggle="lightbox" data-title="<?php echo $pgE($datos['alumno_primernombre'].' '.$datos['alumno_apellidopaterno']); ?>">
											<img class="ds-pg-alumno__foto" src="<?php echo $pgE($foto); ?>" alt="Foto del alumno">
										</a>
										<div class="ds-pg-alumno__id">
											<h2 class="ds-pg-alumno__nombre"><?php echo $pgE($datos['alumno_primernombre']." ".$datos['alumno_apellidopaterno']); ?></h2>
											<div class="ds-pg-alumno__cedula"><?php echo $pgE($datos['alumno_identificacion']); ?></div>
											<div class="ds-pg-alumno__chips">
												<span class="badge rounded-pill <?php echo $datos['alumno_estado'] === 'A' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $pgE($datos['estado']); ?></span>
												<span class="badge rounded-pill bg-body-secondary text-body-emphasis border">Categoría <?php echo $pgE($datos['anio']); ?></span>
											</div>
										</div>
									</div>

									<dl class="ds-pg-alumno__datos">
										<div><dt>Ingreso</dt><dd><?php echo $pgE($fechaCorta($datos['alumno_fechaingreso'])); ?></dd></div>
										<div><dt>Última asistencia</dt><dd><?php echo $ultimaAsistencia ? $pgE($fechaCorta($ultimaAsistencia)) : '<span class="text-body-secondary">Sin asistencias</span>'; ?></dd></div>
										<?php if (!empty($sede['sede_nombre'])) { ?>
										<div><dt>Sede</dt><dd><?php echo $pgE($sede['sede_nombre']); ?></dd></div>
										<?php } ?>
									</dl>

									<?php if ($esPendiente && $nDeuda > 0) { ?>
									<div class="ds-pg-deuda ds-pg-deuda--pendiente">
										<details id="ds-pg-deuda">
											<summary>
												<span class="ds-pg-deuda__rotulo"><i class="fas fa-exclamation-circle me-1"></i>Pendiente</span>
												<span class="ds-pg-deuda__total">$<?php echo number_format($totalDeuda, 2, '.', ','); ?></span>
												<span class="ds-pg-deuda__n"><?php echo $nDeuda; ?> <?php echo $nDeuda === 1 ? 'rubro' : 'rubros'; ?> · <span class="ds-pg-deuda__ver">detalle</span></span>
											</summary>
											<div class="ds-pg-deuda__lista">
												<table class="table table-sm"><tbody><?php echo $filasDeuda; ?></tbody></table>
											</div>
										</details>
									</div>
									<?php } elseif ($esPendiente) { ?>
									<div class="ds-pg-deuda ds-pg-deuda--pendiente">
										<div class="ds-pg-deuda__cabeza"><span class="ds-pg-deuda__rotulo"><i class="fas fa-exclamation-circle me-1"></i>Pendiente</span></div>
									</div>
									<?php } else { ?>
									<div class="ds-pg-deuda ds-pg-deuda--aldia">
										<div class="ds-pg-deuda__cabeza">
											<span class="ds-pg-deuda__rotulo"><i class="fas fa-check-circle me-1"></i>Al día</span>
											<span class="ds-pg-deuda__n">Sin rubros pendientes</span>
										</div>
									</div>
									<?php } ?>
								</div>
							</div>
						</div>

						<!-- Registro de pagos -->
						<div class="col-lg-8 col-xl-9">
							<div class="card">
								<div class="card-header p-2">
									<ul class="nav nav-pills ds-pg-tabs" role="tablist">
										<li class="nav-item"><a class="nav-link active" href="#pension" data-bs-toggle="tab"><i class="fas fa-calendar-check"></i>Pensiones</a></li>
										<li class="nav-item"><a class="nav-link" href="#inscripcion" data-bs-toggle="tab"><i class="fas fa-id-card"></i>Inscripción</a></li>
										<li class="nav-item"><a class="nav-link" href="#torneo" data-bs-toggle="tab"><i class="fas fa-trophy"></i>Campeonato</a></li>
										<li class="nav-item"><a class="nav-link" href="#uniforme" data-bs-toggle="tab" title="Nuevo uniforme"><i class="fas fa-tshirt"></i>Uniforme</a></li>
										<li class="nav-item"><a class="nav-link" href="#kit" data-bs-toggle="tab" title="Adicionales de entrenamiento"><i class="fas fa-dumbbell"></i>Adicionales</a></li>
										<li class="nav-item"><a class="nav-link" href="#otros" data-bs-toggle="tab"><i class="fas fa-ellipsis-h"></i>Otros</a></li>
									</ul>
								</div><!-- /.card-header -->

								<div class="card-body">
									<div class="tab-content">

										<!-- Pensiones -->
										<div class="active tab-pane" id="pension">
											<form class="FormularioAjax ds-pg-form" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
												<input type="hidden" name="modulo_pagos" value="registrar">
												<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
												<input type="hidden" name="pago_rubro" value="pension">
												<?php if ($alerta == "S") { echo $pgAviso($alert, $textodescuento, $textodescripcion); } ?>
												<div class="row g-3">
													<div class="col-6 col-md-4">
														<label class="form-label" for="pago_fecha">Fecha de pago</label>
														<input type="date" class="form-control js-fecha-en-palabras" id="pago_fecha" name="pago_fecha" value="<?php echo $fechahoy; ?>" <?php echo $disabled; ?> required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="pago_fecharegistro">Fecha de registro</label>
														<input type="date" class="form-control" id="pago_fecharegistro" name="pago_fecharegistro" value="<?php echo $fechahoy; ?>" <?php echo $disabled; ?> required>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="pago_periodo">Periodo (mes / año)</label>
														<input type="text" class="form-control" id="pago_periodo" name="pago_periodo" value="<?php echo $nombreMes; ?>" <?php echo $disabled; ?> required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="pago_valor">Valor pagado</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="pago_valor" name="pago_valor" data-ds-total="<?php echo $pgE($rubro_valor); ?>" data-ds-saldo="pago_saldo" placeholder="0.00" pattern="^\d+(\.\d{1,2})?$" value="<?php echo $rubro_valor; ?>" <?php echo $disabled; ?> required></div>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="pago_saldo">Saldo pendiente</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="pago_saldo" name="pago_saldo" placeholder="0.00" value="<?php echo $saldo; ?>" <?php echo $disabled; ?>></div>
														<?php if ($beca != "S") { ?><div class="form-text">Total: $<?php echo $pgE($rubro_valor); ?></div><?php } ?>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="pago_formapagoid">Forma de pago</label>
														<select class="form-select" id="pago_formapagoid" name="pago_formapagoid" <?php echo $disabled; ?>>
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>
													</div>
													<div class="col-12 col-md-8">
														<label class="form-label" for="pago_concepto">Detalle</label>
														<textarea class="form-control" id="pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="2" <?php echo $disabled; ?>></textarea>
													</div>
													<div class="col-12 col-md-4">
														<?php echo $pgComprobante('pago_archivo', 'miImagen', $disabled); ?>
													</div>
												</div>
												<?php if ($beca != 'S') { echo $accionesPago; } ?>
											</form>

											<?php echo $pgHistorial('Pagos realizados', 'example1', $colsMes, $insAlumno->listarPagosRubro($alumno, 'RPE')); ?>
										</div>

										<!-- Inscripción -->
										<div class="tab-pane" id="inscripcion">
											<form class="FormularioAjax ds-pg-form" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
												<input type="hidden" name="modulo_pagos" value="registrar">
												<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
												<input type="hidden" name="pago_rubro" value="inscripcion">
												<?php if ($beca == 'S') { echo $pgAviso($alert, $textodescuento, $textodescripcion); } ?>
												<div class="row g-3">
													<div class="col-6 col-md-4">
														<label class="form-label" for="inscripcion_pago_fecha">Fecha de pago</label>
														<input type="date" class="form-control js-fecha-en-palabras" id="inscripcion_pago_fecha" name="pago_fecha" <?php echo $disabled; ?> required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="inscripcion_pago_fecharegistro">Fecha de registro</label>
														<input type="date" class="form-control" id="inscripcion_pago_fecharegistro" name="pago_fecharegistro" <?php echo $disabled; ?> required>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="inscripcion_pago_periodo">Periodo</label>
														<input type="text" class="form-control" id="inscripcion_pago_periodo" name="pago_periodo" <?php echo $disabled; ?> required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="inscripcion_pago_valor">Valor pagado</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="inscripcion_pago_valor" name="pago_valor" data-ds-total="<?php echo $pgE($rubro_inscripcion); ?>" data-ds-saldo="inscripcion_pago_saldo" placeholder="0.00" value="<?php echo $rubro_inscripcion; ?>" <?php echo $disabled; ?> required></div>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="inscripcion_pago_saldo">Saldo pendiente</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="inscripcion_pago_saldo" name="pago_saldo" placeholder="0.00" value="<?php echo $saldo; ?>" <?php echo $disabled; ?>></div>
														<?php if ($beca != "S") { ?><div class="form-text">Total: $<?php echo $pgE($rubro_inscripcion); ?></div><?php } ?>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="inscripcion_pago_formapagoid">Forma de pago</label>
														<select class="form-select" id="inscripcion_pago_formapagoid" name="pago_formapagoid" <?php echo $disabled; ?>>
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>
													</div>
													<div class="col-12 col-md-8">
														<label class="form-label" for="inscripcion_pago_concepto">Detalle</label>
														<textarea class="form-control" id="inscripcion_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="2" <?php echo $disabled; ?>></textarea>
													</div>
													<div class="col-12 col-md-4">
														<?php echo $pgComprobante('inscripcion_pago_archivo', 'inscripcion_miImagen', $disabled); ?>
													</div>
												</div>
												<?php if ($beca != 'S') { echo $accionesPago; } ?>
											</form>

											<?php echo $pgHistorial('Pagos de inscripción', 'inscripcion_example1', $colsMes, $insAlumno->listarPagosRubro($alumno, 'RIN')); ?>
										</div>

										<!-- Campeonato -->
										<div class="tab-pane" id="torneo">
											<form class="FormularioAjax ds-pg-form" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
												<input type="hidden" name="modulo_pagos" value="registrarcampeonato">
												<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
												<input type="hidden" name="pago_rubro" value="campeonato">
												<div class="row g-3">
													<div class="col-6 col-md-4">
														<label class="form-label" for="torneo_pago_fecha">Fecha de pago</label>
														<input type="date" class="form-control js-fecha-en-palabras" id="torneo_pago_fecha" name="pago_fecha" value="<?php echo $fechahoy; ?>" required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="torneo_pago_fecharegistro">Fecha de registro</label>
														<input type="date" class="form-control" id="torneo_pago_fecharegistro" name="pago_fecharegistro" value="<?php echo $fechahoy; ?>" required>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="torneo_pago_campeonatoid">Campeonato</label>
														<select id="torneo_pago_campeonatoid" class="form-select" name="pago_campeonatoid" <?php echo $disabled; ?>>
															<?php echo $insAlumno->listarCampeonatos(); ?>
														</select>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="torneo_pago_valor">Valor pagado</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="torneo_pago_valor" name="pago_valor" placeholder="0.00" pattern="^\d+(\.\d{1,2})?$" value="" required></div>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="torneo_pago_saldo">Saldo pendiente</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="torneo_pago_saldo" name="pago_saldo" placeholder="0.00" value="0.00"></div>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="torneo_pago_formapagoid">Forma de pago</label>
														<select class="form-select" id="torneo_pago_formapagoid" name="pago_formapagoid">
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>
													</div>
													<div class="col-12 col-md-8">
														<label class="form-label" for="torneo_pago_concepto">Detalle</label>
														<textarea class="form-control" id="torneo_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="2"></textarea>
													</div>
													<div class="col-12 col-md-4">
														<?php echo $pgComprobante('torneo_pago_archivo', 'torneo_miImagen', ''); ?>
													</div>
												</div>
												<?php echo $accionesPago; ?>
											</form>

											<?php echo $pgHistorial('Pagos de campeonatos', 'torneo_example1', ['No', 'Fecha registro', 'Campeonato', 'Pago', 'Saldo', 'Recibo', 'Estado'], $insAlumno->listarPagosRubro($alumno, 'RPC')); ?>
										</div>

										<!-- Nuevo uniforme -->
										<div class="tab-pane" id="uniforme">
											<form class="FormularioAjax ds-pg-form" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
												<input type="hidden" name="modulo_pagos" value="registraruniforme">
												<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
												<input type="hidden" name="pago_rubro" value="uniforme">
												<div class="row g-3">
													<div class="col-6 col-md-3">
														<label class="form-label" for="uniforme_pago_fecha">Fecha de pago</label>
														<input type="date" class="form-control js-fecha-en-palabras" id="uniforme_pago_fecha" name="pago_fecha" required>
													</div>
													<div class="col-6 col-md-3">
														<label class="form-label" for="uniforme_pago_fecharegistro">Fecha de registro</label>
														<input type="date" class="form-control" id="uniforme_pago_fecharegistro" name="pago_fecharegistro" required>
													</div>
													<div class="col-6 col-md-3">
														<label class="form-label" for="uniforme_pago_periodo">Periodo</label>
														<input type="text" class="form-control" id="uniforme_pago_periodo" name="pago_periodo" required>
													</div>
													<div class="col-6 col-md-3">
														<label class="form-label" for="uniforme_pago_talla">Talla</label>
														<select class="form-select" id="uniforme_pago_talla" name="pago_talla" required>
															<?php echo $insAlumno->listarOptionTalla(""); ?>
														</select>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="uniforme_pago_valor">Valor pagado</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="uniforme_pago_valor" name="pago_valor" placeholder="0.00" required></div>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="uniforme_pago_saldo">Saldo pendiente</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="uniforme_pago_saldo" name="pago_saldo" placeholder="0.00"></div>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="uniforme_pago_formapagoid">Forma de pago</label>
														<select class="form-select" id="uniforme_pago_formapagoid" name="pago_formapagoid">
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>
													</div>
													<div class="col-12 col-md-8">
														<label class="form-label" for="uniforme_pago_concepto">Detalle</label>
														<textarea class="form-control" id="uniforme_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="2"></textarea>
													</div>
													<div class="col-12 col-md-4" id="uniforme_miDiv">
														<?php echo $pgComprobante('uniforme_pago_archivo', 'uniforme_miImagen', ''); ?>
													</div>
												</div>
												<?php echo $accionesPago; ?>
											</form>

											<?php echo $pgHistorial('Pagos de nuevo uniforme', 'uniforme_example1', ['No', 'Fecha registro', 'Periodo', 'Talla', 'Pago', 'Saldo', 'Recibo', 'Estado'], $insAlumno->listarPagosRubro($alumno, 'RNU')); ?>
										</div>

										<!-- Adicionales de entrenamiento -->
										<div class="tab-pane" id="kit">
											<form class="FormularioAjax ds-pg-form" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
												<input type="hidden" name="modulo_pagos" value="registrar">
												<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
												<input type="hidden" name="pago_rubro" value="kit">
												<div class="row g-3">
													<div class="col-6 col-md-4">
														<label class="form-label" for="kit_pago_fecha">Fecha de pago</label>
														<input type="date" class="form-control js-fecha-en-palabras" id="kit_pago_fecha" name="pago_fecha" required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="kit_pago_fecharegistro">Fecha de registro</label>
														<input type="date" class="form-control" id="kit_pago_fecharegistro" name="pago_fecharegistro" required>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="kit_pago_periodo">Periodo</label>
														<input type="text" class="form-control" id="kit_pago_periodo" name="pago_periodo" required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="kit_pago_valor">Valor pagado</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="kit_pago_valor" name="pago_valor" placeholder="0.00" required></div>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="kit_pago_saldo">Saldo pendiente</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="kit_pago_saldo" name="pago_saldo" placeholder="0.00"></div>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="kit_pago_formapagoid">Forma de pago</label>
														<select class="form-select" id="kit_pago_formapagoid" name="pago_formapagoid">
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>
													</div>
													<div class="col-12 col-md-8">
														<label class="form-label" for="kit_pago_concepto">Detalle</label>
														<textarea class="form-control" id="kit_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="2"></textarea>
													</div>
													<div class="col-12 col-md-4" id="kit_miDiv">
														<?php echo $pgComprobante('kit_pago_archivo', 'kit_miImagen', ''); ?>
													</div>
												</div>
												<?php echo $accionesPago; ?>
											</form>

											<?php echo $pgHistorial('Pagos de adicionales de entrenamiento', 'kit_example1', $colsMes, $insAlumno->listarPagosRubro($alumno, 'RKE')); ?>
										</div>

										<!-- Otros -->
										<div class="tab-pane" id="otros">
											<form class="FormularioAjax ds-pg-form" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
												<input type="hidden" name="modulo_pagos" value="registrar">
												<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
												<input type="hidden" name="pago_rubro" value="otros">
												<div class="row g-3">
													<div class="col-6 col-md-4">
														<label class="form-label" for="otros_pago_fecha">Fecha de pago</label>
														<input type="date" class="form-control js-fecha-en-palabras" id="otros_pago_fecha" name="pago_fecha" required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="otros_pago_fecharegistro">Fecha de registro</label>
														<input type="date" class="form-control" id="otros_pago_fecharegistro" name="pago_fecharegistro" required>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="otros_pago_periodo">Periodo</label>
														<input type="text" class="form-control" id="otros_pago_periodo" name="pago_periodo" required>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="otros_pago_valor">Valor pagado</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="otros_pago_valor" name="pago_valor" placeholder="0.00" required></div>
													</div>
													<div class="col-6 col-md-4">
														<label class="form-label" for="otros_pago_saldo">Saldo pendiente</label>
														<div class="input-group"><span class="input-group-text">$</span>
														<input type="text" inputmode="decimal" class="form-control ds-pg-monto" id="otros_pago_saldo" name="pago_saldo" placeholder="0.00"></div>
													</div>
													<div class="col-12 col-md-4">
														<label class="form-label" for="otros_pago_formapagoid">Forma de pago</label>
														<select class="form-select" id="otros_pago_formapagoid" name="pago_formapagoid">
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>
													</div>
													<div class="col-12 col-md-8">
														<label class="form-label" for="otros_pago_concepto">Detalle</label>
														<textarea class="form-control" id="otros_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="2"></textarea>
													</div>
													<div class="col-12 col-md-4" id="otros_miDiv">
														<?php echo $pgComprobante('otros_pago_archivo', 'otros_miImagen', ''); ?>
													</div>
												</div>
												<?php echo $accionesPago; ?>
											</form>

											<?php echo $pgHistorial('Otros pagos', 'otros_example1', $colsMes, $insAlumno->listarPagosRubro($alumno, 'ROT')); ?>
										</div>

									</div>
									<!-- /.tab-content -->
								</div><!-- /.card-body -->
							</div>
							<!-- /.card -->
						</div>
					</div>
				</div>
			</section>
			<!-- /.content -->
		</div>
		<!-- /.vista -->

		<?php require_once "app/views/inc/footer.php"; ?>
    </div>
    <!-- ./wrapper -->

	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/visor.js'); ?>"></script>

	<script>
	(function () {
		'use strict';

		/* Periodo de la pensión según la fecha de pago. Antes escuchaba
		   «keyup», que no se dispara al elegir la fecha en el calendario
		   (lo normal en el celular). */
		var meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
		             'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
		var fecha = document.getElementById('pago_fecha');
		var periodo = document.getElementById('pago_periodo');
		function ponerPeriodo() {
			var p = (fecha.value || '').split('-');
			if (p.length === 3 && meses[parseInt(p[1], 10) - 1]) {
				periodo.value = meses[parseInt(p[1], 10) - 1] + ' / ' + parseInt(p[0], 10); periodo.dispatchEvent(new Event('change'));
			}
		}
		if (fecha && periodo) {
			fecha.addEventListener('change', ponerPeriodo);
			fecha.addEventListener('input', ponerPeriodo);
		}

		/* Abono: lo que falta para completar el valor del rubro (pensión o
		   inscripción) pasa solo a «Saldo pendiente». Sigue siendo
		   editable a mano. Pagar más que el total deja el saldo en 0. */
		document.querySelectorAll('input[data-ds-total][data-ds-saldo]').forEach(function (valor) {
			var total = parseFloat(valor.getAttribute('data-ds-total'));
			var saldo = document.getElementById(valor.getAttribute('data-ds-saldo'));
			if (!saldo || isNaN(total)) { return; }
			valor.addEventListener('input', function () {
				var texto = valor.value.trim().replace(',', '.');
				var pagado = texto === '' ? 0 : parseFloat(texto);
				if (isNaN(pagado)) { return; }
				saldo.value = Math.max(total - pagado, 0).toFixed(2);
			});
		});

		/* Forma de pago «Justificado» (FJU): el alumno no asiste ese mes y
		   no paga. Valor y saldo quedan en 0.00 y bloqueados (así el
		   servidor lo registra como justificado), y el Detalle pide el
		   motivo con un texto inicial editable. Al volver a otra forma de
		   pago se repone el valor que corresponde: el del rubro si se
		   conoce (pensión, inscripción) o el que había antes. */
		document.querySelectorAll('form.ds-pg-form').forEach(function (form) {
			var forma   = form.querySelector('[name="pago_formapagoid"]');
			var valor   = form.querySelector('[name="pago_valor"]');
			var saldo   = form.querySelector('[name="pago_saldo"]');
			var detalle = form.querySelector('[name="pago_concepto"]');
			var periodo = form.querySelector('[name="pago_periodo"]');
			if (!forma || !valor || !saldo || !detalle || forma.disabled) { return; }

			var justificado = false, textoAuto = '', previo = { valor: '', saldo: '' };
			var placeholder = detalle.getAttribute('placeholder') || '';

			function textoJustificacion() {
				var p = periodo ? periodo.value.trim() : '';
				return 'Justificado: el alumno no asistirá' + (p ? ' en ' + p : '') + '.';
			}
			function bloquear(si) {
				valor.readOnly = si; saldo.readOnly = si;
				detalle.required = si;
				detalle.setAttribute('placeholder', si ? 'Motivo de la justificación' : placeholder);
			}
			function aplicar() {
				var ahora = forma.value === 'FJU';
				if (ahora === justificado) { return; }
				justificado = ahora;
				if (ahora) {
					previo = { valor: valor.value, saldo: saldo.value };
					valor.value = '0.00';
					saldo.value = '0.00';
					if (detalle.value.trim() === '') { textoAuto = textoJustificacion(); detalle.value = textoAuto; }
				} else {
					var total = parseFloat(valor.getAttribute('data-ds-total'));
					if (!isNaN(total)) {
						valor.value = total.toFixed(2);
						saldo.value = '0.00';
					} else {
						valor.value = previo.valor;
						saldo.value = previo.saldo;
					}
					if (textoAuto !== '' && detalle.value === textoAuto) { detalle.value = ''; }
					textoAuto = '';
				}
				bloquear(ahora);
			}
			forma.addEventListener('change', aplicar);

			/* Si cambia el periodo y el texto sigue siendo el propuesto, se
			   actualiza el mes. */
			if (periodo) {
				['input', 'change'].forEach(function (ev) {
					periodo.addEventListener(ev, function () {
						if (textoAuto !== '' && detalle.value === textoAuto) {
							textoAuto = textoJustificacion();
							detalle.value = textoAuto;
						}
					});
				});
			}

			/* Limpiar devuelve el formulario a su estado inicial. */
			form.addEventListener('reset', function () {
				justificado = false; textoAuto = '';
				bloquear(false);
				setTimeout(aplicar, 0);
			});

			aplicar();
		});

		/* En el celular cada pago es una tarjeta: cada dato lleva el
		   nombre de su columna. */
		document.querySelectorAll('.ds-pg-tabla').forEach(function (tabla) {
			var nombres = Array.prototype.map.call(tabla.querySelectorAll('thead th'), function (th) {
				return th.textContent.trim();
			});
			tabla.querySelectorAll('tbody tr').forEach(function (fila) {
				Array.prototype.forEach.call(fila.children, function (celda, i) {
					if (nombres[i]) { celda.setAttribute('data-label', nombres[i]); }
				});
			});
		});

		/* El detalle de lo que debe: abierto en escritorio, plegado en el
		   celular para que el formulario quede a la vista. */
		var deuda = document.getElementById('ds-pg-deuda');
		if (deuda && window.matchMedia('(min-width: 992px)').matches) { deuda.open = true; }

		/* La pestaña activa se recuerda tras guardar (la página se recarga)
		   y, en el celular, se desliza hasta quedar a la vista. */
		var barra = document.querySelector('.ds-pg-tabs');
		function centrar(enlace) {
			if (!barra || !enlace) { return; }
			var li = enlace.parentElement;
			barra.scrollLeft = li.offsetLeft - (barra.clientWidth - li.offsetWidth) / 2;
		}
		function guardarPestana(href) { try { localStorage.setItem('activeTab', href); } catch (e) {} }

		document.querySelectorAll('.ds-pg-tabs a[data-bs-toggle="tab"]').forEach(function (tab) {
			tab.addEventListener('shown.bs.tab', function (e) {
				guardarPestana(e.target.getAttribute('href'));
				centrar(e.target);
			});
		});
		document.querySelectorAll('form.FormularioAjax.ds-pg-form').forEach(function (form) {
			form.addEventListener('submit', function () {
				var activa = document.querySelector('.ds-pg-tabs .nav-link.active');
				if (activa) { guardarPestana(activa.getAttribute('href')); }
			});
		});
		window.addEventListener('load', function () {
			var guardada = null;
			try { guardada = localStorage.getItem('activeTab'); } catch (e) {}
			var tab = guardada ? document.querySelector('.ds-pg-tabs a[href="' + guardada + '"]') : null;
			if (tab) { bootstrap.Tab.getOrCreateInstance(tab).show(); centrar(tab); }
		});
	})();
	</script>

	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>
