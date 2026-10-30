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

		/* Las pensiones no pagadas desde la última se calculan sólo para
		   alumnos ACTIVOS, como en el listado de pagos: un inactivo no
		   entrena, y contarle los meses desde que dejó de venir inflaba la
		   deuda. Lo registrado con saldo (pagosPendintes) se muestra igual. */
		if($beca != "S" && $datos['alumno_estado'] === 'A'){
			$pension = $insAlumno->pensionesPendientes($alumno);
			if($pension != ""){
				$pendiente = "Pendiente";
				$clase = '<a class="float-end text-danger">';
			}
		}else{
			$pension = "";
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
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Registro de pagos';
	$extras      = array (0 => 'lightbox',1 => 'swal',);
	$cabeceraExtra = <<<'CSS'
	<style>
		.oculto{
			display: none;
		}

		.errorMSG {
		  display: none;
		}

		input:invalid {
		  box-shadow: 0 0 2px 1px red;
		}

		input:invalid ~ .errorMSG{
		 
		  width: 180px;
		  font-size: 12px;		  
		  color: red;
		  vertical-align: top;
		  margin: 0;
		}

		input:focus:invalid {
		  box-shadow: none;
		}
	</style>
CSS;
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
							<h1 class="m-0">Pagos alumno</h1>
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

			<!-- Main content -->
			<section class="app-content">				
				<!-- /.container-fluid información alumno -->
				<div class="container-fluid">

					<div class="row">
						<div class="col-md-3">	
							<!-- Profile Image -->
							<div class="card card-primary card-outline">
								<div class="card-body box-profile">
									<div class="text-center">
										<img class="profile-user-img img-fluid rounded-circle"
											src="<?php echo $foto; ?>"
											alt="User profile picture">
									</div>

									<h3 class="profile-username text-center"><?php echo $datos['alumno_primernombre']." ".$datos['alumno_apellidopaterno'] ; ?></h3>

									<p class="text-muted text-center"><?php echo $datos['alumno_identificacion']; ?></p>

									<ul class="list-group list-group-unbordered mb-3">
										<li class="list-group-item">
											<b>Categoría</b> <a class="float-end"><?php echo $datos['anio']; ?></a>
										</li>
										<li class="list-group-item">
											<b>Estado alumno</b> <a class="float-end"><?php echo $datos['estado']; ?></a>
										</li>
										<li class="list-group-item">
											<b>Fecha de ingreso</b> <a class="float-end"><?php echo $datos['alumno_fechaingreso']; ?></a>
										</li>
										<li class="list-group-item">
											<b>Estado pagos</b> 												
											<?php												
												echo $clase.$pendiente.'</a>'; 												
											?>
										</li>
										<li class="list-group-item">
											<b>Detalle rubros pendientes</b> 
											<div class="table-responsive">
											<table class="table table-sm">	

												<?php 
												echo $pension;
												echo $insAlumno->pagosPendintes($alumno); 
												?>											
											</table>
											</div>											
										</li>
									</ul>
								</div>
								<!-- /.card-body -->
							</div>
							<!-- /.card -->
						</div>

						<div class="col-md-9">
							<div class="card">
								<div class="card-header p-2">
									<ul class="nav nav-pills">
										<li class="nav-item"><a class="nav-link active" href="#pension" data-bs-toggle="tab">Pensiones</a></li>
										<li class="nav-item"><a class="nav-link" href="#inscripcion" data-bs-toggle="tab">Inscripción</a></li>
										<li class="nav-item"><a class="nav-link" href="#torneo" data-bs-toggle="tab">Campeonato</a></li>
										<li class="nav-item"><a class="nav-link" href="#uniforme" data-bs-toggle="tab">Nuevo Uniforme</a></li>										
										<li class="nav-item"><a class="nav-link" href="#kit" data-bs-toggle="tab">Adicionales entrenamiento</a></li>									
										<li class="nav-item"><a class="nav-link" href="#otros" data-bs-toggle="tab">Otros</a></li>									
									</ul>
								</div><!-- /.card-header -->
							
								<div class="card-body">
									<div class="tab-content">
										<!-- /.tab-pane -->
										<div class="active tab-pane" id="pension"> 
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
											<input type="hidden" name="modulo_pagos" value="registrar">											
											<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
											<input type="hidden" name="pago_rubro" value="pension">
																	<!-- Post -->
											<div class="row">
												<div class="col-md-4">
													<div class="mb-3 campo">
														<label for="pago_fecha">Fecha de pago</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control js-fecha-en-palabras" id="pago_fecha" name="pago_fecha" value="<?php echo $fechahoy; ?>" <?php echo $disabled; ?> required>
															
														</div>
														<!-- /.input group -->
													</div>
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="pago_fecharegistro">Fecha de registro</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" id="pago_fecharegistro" name="pago_fecharegistro" value="<?php echo $fechahoy; ?>" <?php echo $disabled; ?> required>
														</div>
														<!-- /.input group -->
													</div>								
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="pago_periodo">Periodo(mes/año)</label>															
														<input type="text" class="form-control" id="pago_periodo" name="pago_periodo" value="<?php echo $nombreMes; ?>" <?php echo $disabled; ?> required>															
													</div>								
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="pago_valor">Pago</label>
														<input type="text" class="form-control text-end" id="pago_valor" name="pago_valor" placeholder="0.00" pattern="^\d+(\.\d{1,2})?$" <?php echo ' value="'.$rubro_valor.'" '.$disabled; ?>  required>
													</div>
												</div>

												<div class="col-md-4">
													<div class="mb-3">
														<label for="pago_saldo">Saldo pendiente</label>
														<input type="text" class="form-control text-end" id="pago_saldo" name="pago_saldo" placeholder="0.00" <?php echo ' value="'.$saldo.'" '.$disabled; ?>>
													</div>
												</div>
												
												<div class="col-md-4">
													<div class="mb-3">
													<label for="pago_formapagoid">Forma de pago</label>
													<select class="form-select" id="pago_formapagoid" name="pago_formapagoid" <?php echo $disabled; ?>>																									
														<?php echo $insAlumno->listarOptionPago(); ?>
													</select>	
													</div>
												</div>
												<div class="container-fluid">
													<div class="row mb-2">
														<div class="col-md-2">
															<div class="mb-3">
																<label for="pago_archivo">Imagen Pago</label>		
																<div class="input-group">											
																	<div class="fileinput fileinput-new" data-provides="fileinput">
																	<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput"><img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg'); ?>" alt="" id="miImagen"></div>
																			<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
																		<div>
																			<span class="bton bton-white bton-file">
																				<span class="fileinput-new">Subir Pago</span>
																				<span class="fileinput-exists">Cambiar</span>
																				<input type="file" name="pago_archivo" id="pago_archivo" <?php echo $disabled; ?>>
																			</span>
																			<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
																		</div>
																	</div>
																</div>		
															</div>
														<!-- /.mb-3 -->	
														</div>
														<div class="col-md-10">
															<div class="col-md-12">
																<div class="mb-3">
																<label for="pago_concepto">Detalle</label>
																<textarea class="form-control" id="pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="3" <?php echo $disabled; ?>></textarea>
																</div>
															</div>
															<?php 
																if($alerta == "S"){
																	echo '
																	<div class="col-md-12">
																		<div class="alert '.$alert.' alert-dismissible">
																			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
																			<h5><i class="icon fas fa-info"></i> Aviso!</h5>
																			'.$textodescuento.$textodescripcion.'
																		</div>
																	</div>
																	';
																}
															?>
														</div>
														
													</div>
												</div>
											</div>		
																					
											<!-- /.post -->
											<?php
												if($beca != 'S'){
													echo '						
														' . ds_acciones_form('', ['limpiar' => true]) . '
													';
												}
												
											?>
											</form>

											<div class="tab-custom-content">
												<p class="lead mb-0">Pagos realizados</p>
											</div>
											<div class="tab-content" id="custom-content-above-tabContent">
												<div class="table-responsive">
												<table id="example1" class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>															
															<th>Fecha registro</th>
															<th>Mes/Año</th>
															<th>Pago</th>
															<th>Saldo</th>	
															<th>Recibo</th>													
															<th>Estado</th>															
															<th style="width:280px;">Opciones</th>																
														</tr>
													</thead>
													<tbody>
														<?php 
															echo $insAlumno->listarPagosRubro($alumno,'RPE'); 
														?>								
													</tbody>
												</table>
												</div>
											</div>
											
										</div>											

										<!-- /.tab-pane -->
										<div class="tab-pane" id="inscripcion">
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
											<input type="hidden" name="modulo_pagos" value="registrar">											
											<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
											<input type="hidden" name="pago_rubro" value="inscripcion">
																	<!-- Post -->
												<div class="row">
													<div class="col-md-4">
														<div class="mb-3">
															<label for="inscripcion_pago_fecha">Fecha pago inscripción</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control js-fecha-en-palabras" id="inscripcion_pago_fecha" name="pago_fecha" <?php echo $disabled; ?> required>
															</div>
															<!-- /.input group -->
														</div>
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="inscripcion_pago_fecharegistro">Fecha registro inscripción</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control" id="inscripcion_pago_fecharegistro" name="pago_fecharegistro" <?php echo $disabled; ?> required>
															</div>
															<!-- /.input group -->
														</div>								
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="inscripcion_pago_periodo">Periodo inscripción</label>															
															<input type="text" class="form-control" id="inscripcion_pago_periodo" name="pago_periodo" <?php echo $disabled; ?> required>															
														</div>								
													</div>
													
													<div class="col-md-4">
														<div class="mb-3">
															<label for="inscripcion_pago_valor">Pago inscripción</label>
															<input type="text" class="form-control text-end" id="inscripcion_pago_valor" name="pago_valor" placeholder="0.00" <?php echo ' value="'.$rubro_inscripcion.'" '.$disabled; ?>  required>
														</div>
													</div>

													<div class="col-md-4">
														<div class="mb-3">
															<label for="inscripcion_pago_saldo">Saldo inscripción</label>
															<input type="text" class="form-control text-end" id="inscripcion_pago_saldo" name="pago_saldo" placeholder="0.00"  <?php echo ' value="'.$saldo.'" '.$disabled; ?> >
														</div>
													</div>
													
													<div class="col-md-4">
														<div class="mb-3">
														<label for="inscripcion_pago_formapagoid">Forma de pago inscripción</label>
														<select class="form-select" id="inscripcion_pago_formapagoid" name="pago_formapagoid" onchange="ocultarDiv()" <?php echo $disabled; ?>>																									
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>	
														</div>
													</div>
													
													<div class="container-fluid">
														<div class="row mb-2">

															<div class="col-2">
																<div class="mb-3">
																	<label for="inscripcion_pago_archivo">Imagen pago</label>		
																	<div class="input-group">											
																		<div class="fileinput fileinput-new" data-provides="fileinput">
																		<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput"><img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg'); ?>" alt="" id="inscripcion_miImagen"></div>
																			<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
																			<div>
																				<span class="bton bton-white bton-file">
																					<span class="fileinput-new">Subir Pago</span>
																					<span class="fileinput-exists">Cambiar</span>
																					<input type="file" name="pago_archivo" id="inscripcion_pago_archivo" <?php echo $disabled; ?>>
																				</span>
																				<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
																			</div>
																		</div>
																	</div>		
																</div>
															<!-- /.mb-3 -->	
															</div>
															<div class="col-10">
																<div class="col-12">
																	<div class="mb-3">
																	<label for="inscripcion_pago_concepto">Detalle inscripción</label>
																	<textarea class="form-control" id="inscripcion_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="3" <?php echo $disabled; ?>></textarea>
																	</div>
																</div>
																<?php 
																	if($beca == 'S'){
																		echo '
																		<div class="col-md-12">
																			<div class="alert '.$alert.' alert-dismissible">
																				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
																				<h5><i class="icon fas fa-info"></i> Aviso!</h5>
																				'.$textodescuento.$textodescripcion.'
																			</div>
																		</div>
																		';
																	}
																?>
															</div>
														</div>
													</div>
												</div>

													
											
											<!-- /.post -->
											<?php
												if($beca != 'S'){
													echo '
														' . ds_acciones_form('', ['limpiar' => true]) . '
													';							
												}
											?>
											</form>	
											<div class="tab-custom-content">
												<p class="lead mb-0">Pagos realizados de inscripción</p>
											</div>
											<div class="tab-content" id="inscripcion_custom-content-above-tabContent">
												<div class="table-responsive">
												<table id="inscripcion_example1" class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>															
															<th>Fecha registro</th>
															<th>Mes/Año</th>
															<th>Pago</th>
															<th>Saldo</th>	
															<th>Recibo</th>													
															<th>Estado</th>															
															<th style="width:280px;">Opciones</th>																	
														</tr>
													</thead>
													<tbody>
														<?php 
															echo $insAlumno->listarPagosRubro($alumno,'RIN'); 
														?>								
													</tbody>
												</table>
												</div>
											</div>
										</div>
										
										<!-- /.tab-pane -->
										<div class="tab-pane" id="torneo"> 
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
											<input type="hidden" name="modulo_pagos" value="registrarcampeonato">											
											<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
											<input type="hidden" name="pago_rubro" value="campeonato">
																	<!-- Post -->
											<div class="row">
												<div class="col-md-4">
													<div class="mb-3 campo">
														<label for="torneo_pago_fecha">Fecha de pago campeonato</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control js-fecha-en-palabras" id="torneo_pago_fecha" name="pago_fecha" value="<?php echo $fechahoy; ?>" required>
															
														</div>
														<!-- /.input group -->
													</div>
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="torneo_pago_fecharegistro">Fecha de registro campeonato</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" id="torneo_pago_fecharegistro" name="pago_fecharegistro" value="<?php echo $fechahoy; ?>" required>
														</div>
														<!-- /.input group -->
													</div>								
												</div>
												<div class="col-md-4">
													<div class="mb-3">
													<label for="torneo_pago_campeonatoid">Campeonato</label>
													<select id="torneo_pago_campeonatoid" class="form-select" name="pago_campeonatoid" <?php echo $disabled; ?>>																									
														<?php echo $insAlumno->listarCampeonatos(); ?>
													</select>	
													</div>
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="torneo_pago_valor">Pago campeonato</label>
														<input type="text" class="form-control text-end" id="torneo_pago_valor" name="pago_valor" placeholder="0.00" pattern="^\d+(\.\d{1,2})?$" value="" required>
													</div>
												</div>

												<div class="col-md-4">
													<div class="mb-3">
														<label for="torneo_pago_saldo">Saldo campeonato</label>
														<input type="text" class="form-control text-end" id="torneo_pago_saldo" name="pago_saldo" placeholder="0.00" value="0.00"; >
													</div>
												</div>
												
												<div class="col-md-4">
													<div class="mb-3">
													<label for="torneo_pago_formapagoid">Forma de pago campeonato</label>
													<select class="form-select" id="torneo_pago_formapagoid" name="pago_formapagoid">																									
														<?php echo $insAlumno->listarOptionPago(); ?>
													</select>	
													</div>
												</div>
												<div class="container-fluid">
													<div class="row mb-2">
														<div class="col-md-2">
															<div class="mb-3">
																<label for="torneo_pago_archivo">Imagen Pago</label>		
																<div class="input-group">											
																	<div class="fileinput fileinput-new" data-provides="fileinput">
																	<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput"><img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg'); ?>" alt="" id="torneo_miImagen"></div>
																			<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
																		<div>
																			<span class="bton bton-white bton-file">
																				<span class="fileinput-new">Subir Pago</span>
																				<span class="fileinput-exists">Cambiar</span>
																				<input type="file" name="pago_archivo" id="torneo_pago_archivo">
																			</span>
																			<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
																		</div>
																	</div>
																</div>		
															</div>
														<!-- /.mb-3 -->	
														</div>
														<div class="col-md-10">
															<div class="col-md-12">
																<div class="mb-3">
																<label for="torneo_pago_concepto">Detalle campeonato</label>
																<textarea class="form-control" id="torneo_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="3"></textarea>
																</div>
															</div>															
														</div>														
													</div>
												</div>
											</div>		
																					
											<!-- /.post -->
											<?php echo ds_acciones_form('', ['limpiar' => true]); ?>
											</form>

											<div class="tab-custom-content">
												<p class="lead mb-0">Pagos realizados de campeonatos</p>
											</div>
											<div class="tab-content" id="torneo_custom-content-above-tabContent">
												<div class="table-responsive">
												<table id="torneo_example1" class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>															
															<th>Fecha registro</th>		
															<th>Campeonato</th>													
															<th>Pago</th>
															<th>Saldo</th>
															<th>Recibo</th>													
															<th>Estado</th>															
															<th style="width:280px;">Opciones</th>																
														</tr>
													</thead>
													<tbody>
														<?php 
															echo $insAlumno->listarPagosRubro($alumno,'RPC'); 
														?>								
													</tbody>
												</table>
												</div>
											</div>
											
										</div>

										<!-- /.tab-pane -->
										<div class="tab-pane" id="uniforme">
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
											<input type="hidden" name="modulo_pagos" value="registraruniforme">											
											<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
											<input type="hidden" name="pago_rubro" value="uniforme">
																	<!-- Post -->
												<div class="row">
													<div class="col-md-3">
														<div class="mb-3">
															<label for="uniforme_pago_fecha">Fecha pago uniforme</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control js-fecha-en-palabras" id="uniforme_pago_fecha" name="pago_fecha" required>
															</div>
															<!-- /.input group -->
														</div>
													</div>
													<div class="col-md-3">
														<div class="mb-3">
															<label for="uniforme_pago_fecharegistro">Fecha registro uniforme</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control" id="uniforme_pago_fecharegistro" name="pago_fecharegistro" required>
															</div>
															<!-- /.input group -->
														</div>								
													</div>
													<div class="col-md-3">
														<div class="mb-3">
															<label for="uniforme_pago_periodo">Periodo uniforme</label>															
															<input type="text" class="form-control" id="uniforme_pago_periodo" name="pago_periodo" required>															
														</div>								
													</div>
																									
													<div class="col-md-3">
														<div class="mb-3">
														<label for="uniforme_pago_talla">Talla</label>
														<select class="form-select" id="uniforme_pago_talla" name="pago_talla" required>																									
															<?php echo $insAlumno->listarOptionTalla(""); ?>
														</select>	
														</div>
													</div>
													
													<div class="col-md-4">
														<div class="mb-3">
															<label for="uniforme_pago_valor">Pago uniforme</label>
															<input type="text" class="form-control text-end" id="uniforme_pago_valor" name="pago_valor" placeholder="0.00" required>
														</div>
													</div>

													<div class="col-md-4">
														<div class="mb-3">
															<label for="uniforme_pago_saldo">Saldo uniforme</label>
															<input type="text" class="form-control text-end" id="uniforme_pago_saldo" name="pago_saldo" placeholder="0.00">
														</div>
													</div>
													
													<div class="col-md-4">
														<div class="mb-3">
														<label for="uniforme_pago_formapagoid">Forma de pago uniforme</label>
														<select class="form-select" id="uniforme_pago_formapagoid" name="pago_formapagoid" onchange="ocultarDiv()" >																									
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>	
														</div>
													</div>													
													
													<div class="col-md-2" id="uniforme_miDiv">
														<div class="mb-3">
															<label for="uniforme_pago_archivo">Imagen Pago</label>		
															<div class="input-group">											
																<div class="fileinput fileinput-new" data-provides="fileinput">
																	<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput"><img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg'); ?>" alt="" id="uniforme_miImagen"></div>
																	<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
																	<div>
																		<span class="bton bton-white bton-file">
																			<span class="fileinput-new">Subir Pago</span>
																			<span class="fileinput-exists">Cambiar</span>
																			<input type="file" name="pago_archivo" id="uniforme_pago_archivo">
																		</span>
																		<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
																	</div>
																</div>
															</div>		
														</div>
													<!-- /.mb-3 -->	
													</div>

													<div class="col-md-10">
														<div class="col-md-12">
															<div class="mb-3">
																<label for="uniforme_pago_concepto">Detalle uniforme</label>
																<textarea class="form-control" id="uniforme_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="3" ></textarea>
															</div>
														</div>
													</div>
												</div>											
											<!-- /.post -->
											<?php echo ds_acciones_form('', ['limpiar' => true]); ?>
											</form>	
											
											<div class="tab-custom-content">
												<p class="lead mb-0">Pagos de Nuevo Uniforme realizados</p>
											</div>
											<div class="tab-content" id="uniforme_custom-content-above-tabContent">
												<div class="table-responsive">
												<table id="uniforme_example1" class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>
															<th>Fecha registro</th>
															<th>Periodo</th>
															<th>Talla</th>
															<th>Pago</th>
															<th>Saldo</th>															
															<th>Recibo</th>
															<th>Estado</th>
															<th style="width:280px;">Opciones</th>																
														</tr>
													</thead>
													<tbody>
														<?php 
															echo $insAlumno->listarPagosRubro($alumno,'RNU');
														?>								
													</tbody>
												</table>
												</div>
											</div>											
										</div>

										<!-- /.tab-pane -->										
										<div class="tab-pane" id="kit">
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
											<input type="hidden" name="modulo_pagos" value="registrar">											
											<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
											<input type="hidden" name="pago_rubro" value="kit">
																	<!-- Post -->
												<div class="row">
													<div class="col-md-4">
														<div class="mb-3">
															<label for="kit_pago_fecha">Fecha pago accesorio entrenamiento</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control js-fecha-en-palabras" id="kit_pago_fecha" name="pago_fecha" required>
															</div>
															<!-- /.input group -->
														</div>
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="kit_pago_fecharegistro">Fecha registro accesorio entrenamiento</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control" id="kit_pago_fecharegistro" name="pago_fecharegistro" required>
															</div>
															<!-- /.input group -->
														</div>								
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="kit_pago_periodo">Periodo accesorio entrenamiento</label>															
															<input type="text" class="form-control" id="kit_pago_periodo" name="pago_periodo" required>															
														</div>								
													</div>
													
													<div class="col-md-4">
														<div class="mb-3">
															<label for="kit_pago_valor">Pago accesorio entrenamiento</label>
															<input type="text" class="form-control text-end" id="kit_pago_valor" name="pago_valor" placeholder="0.00" required>
														</div>
													</div>

													<div class="col-md-4">
														<div class="mb-3">
															<label for="kit_pago_saldo">Saldo accesorio entrenamiento</label>
															<input type="text" class="form-control text-end" id="kit_pago_saldo" name="pago_saldo" placeholder="0.00">
														</div>
													</div>
													
													<div class="col-md-4">
														<div class="mb-3">
														<label for="kit_pago_formapagoid">Forma de pago</label>
														<select class="form-select" id="kit_pago_formapagoid" name="pago_formapagoid" onchange="ocultarDiv()" >																									
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>	
														</div>
													</div>													
													
													<div class="col-md-2" id="kit_miDiv">
														<div class="mb-3">
															<label for="kit_pago_archivo">Imagen Pago</label>		
															<div class="input-group">											
																<div class="fileinput fileinput-new" data-provides="fileinput">
																	<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput"><img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg'); ?>" alt="" id="kit_miImagen"></div>
																	<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
																	<div>
																		<span class="bton bton-white bton-file">
																			<span class="fileinput-new">Subir Pago</span>
																			<span class="fileinput-exists">Cambiar</span>
																			<input type="file" name="pago_archivo" id="kit_pago_archivo">
																		</span>
																		<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
																	</div>
																</div>
															</div>		
														</div>
													<!-- /.mb-3 -->	
													</div>

													<div class="col-md-10">
														<div class="col-md-12">
															<div class="mb-3">
																<label for="kit_pago_concepto">Detalle accesorios entrenamiento</label>
																<textarea class="form-control" id="kit_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="3" ></textarea>
															</div>
														</div>
													</div>
												</div>										
												<!-- /.post -->
											<?php echo ds_acciones_form('', ['limpiar' => true]); ?>
											</form>	
											
											<div class="tab-custom-content">
												<p class="lead mb-0">Pagos realizados por accesorios de entrenamiento</p>
											</div>
											<div class="tab-content" id="kit_custom-content-above-tabContent">
												<div class="table-responsive">
												<table id="kit_example1" class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>
															<th>Fecha registro</th>		
															<th>Periodo</th>													
															<th>Pago</th>
															<th>Saldo</th>														
															<th>Recibo</th>
															<th>Estado</th>
															<th style="width:300px;">Opciones</th>																
														</tr>
													</thead>
													<tbody>
														<?php 
															echo $insAlumno->listarPagosRubro($alumno,'RKE'); 
														?>								
													</tbody>
												</table>
												</div>
											</div>	
										</div>

										<!-- /.tab-pane -->										
										<div class="tab-pane" id="otros">
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
											<input type="hidden" name="modulo_pagos" value="registrar">											
											<input type="hidden" name="pago_alumnoid" value="<?php echo $datos['alumno_id']; ?>">
											<input type="hidden" name="pago_rubro" value="otros">
																	<!-- Post -->
												<div class="row">
													<div class="col-md-4">
														<div class="mb-3">
															<label for="otros_pago_fecha">Fecha pago Otros</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control js-fecha-en-palabras" id="otros_pago_fecha" name="pago_fecha" required>
															</div>
															<!-- /.input group -->
														</div>
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="otros_pago_fecharegistro">Fecha registro Otros</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control" id="otros_pago_fecharegistro" name="pago_fecharegistro" required>
															</div>
															<!-- /.input group -->
														</div>								
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="otros_pago_periodo">Periodo Otros</label>															
															<input type="text" class="form-control" id="otros_pago_periodo" name="pago_periodo" required>															
														</div>								
													</div>
													
													<div class="col-md-4">
														<div class="mb-3">
															<label for="otros_pago_valor">Pago Otros</label>
															<input type="text" class="form-control text-end" id="otros_pago_valor" name="pago_valor" placeholder="0.00" required>
														</div>
													</div>

													<div class="col-md-4">
														<div class="mb-3">
															<label for="otros_pago_saldo">Saldo Otros</label>
															<input type="text" class="form-control text-end" id="otros_pago_saldo" name="pago_saldo" placeholder="0.00">
														</div>
													</div>
													
													<div class="col-md-4">
														<div class="mb-3">
														<label for="otros_pago_formapagoid">Forma de pago Otros</label>
														<select class="form-select" id="otros_pago_formapagoid" name="pago_formapagoid" onchange="ocultarDiv()" >																									
															<?php echo $insAlumno->listarOptionPago(); ?>
														</select>	
														</div>
													</div>										

													<div class="col-md-2" id="otros_miDiv">
														<div class="mb-3">
															<label for="otros_pago_archivo">Imagen Pago</label>		
															<div class="input-group">											
																<div class="fileinput fileinput-new" data-provides="fileinput">
																	<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput"><img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg'); ?>" alt="" id="otros_miImagen"></div>
																	<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
																	<div>
																		<span class="bton bton-white bton-file">
																			<span class="fileinput-new">Subir Pago</span>
																			<span class="fileinput-exists">Cambiar</span>
																			<input type="file" name="pago_archivo" id="otros_pago_archivo">
																		</span>
																		<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
																	</div>
																</div>
															</div>		
														</div>
													<!-- /.mb-3 -->	
													</div>

													<div class="col-md-10">
														<div class="col-md-12">
															<div class="mb-3">
																<label for="otros_pago_concepto">Detalle Otros</label>
																<textarea class="form-control" id="otros_pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="3" ></textarea>
															</div>
														</div>
													</div>
												</div>											
											<!-- /.post -->

											<?php echo ds_acciones_form('', ['limpiar' => true]); ?>
											</form>	
											
											<div class="tab-custom-content">
												<p class="lead mb-0">Pagos Otros realizados</p>
											</div>
											<div class="tab-content" id="otros_custom-content-above-tabContent">
												<div class="table-responsive">
												<table id="otros_example1" class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>
															<th>Fecha registro</th>
															<th>Periodo</th>
															<th>Pago</th>
															<th>Saldo</th>														
															<th>Recibo</th>
															<th>Estado</th>		
															<th style="width:300px;">Opciones</th>																
														</tr>
													</thead>
													<tbody>
														<?php 
															echo $insAlumno->listarPagosRubro($alumno,'ROT');  
														?>								
													</tbody>
												</table>
												</div>
											</div>											
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
	<!-- fileinput -->

	<script src="<?php echo ds_recurso('ds_core/assets/js/visor.js'); ?>"></script>
		
	<script>
		$(document).ready(function () {
			$(".js-fecha-en-palabras").keyup(function () {
				var value = $(this).val();
				
				// Dividir la fecha manualmente para evitar problemas de zona horaria
				var partes = value.split('-'); // Formato esperado: YYYY-MM-DD
				
				if (partes.length === 3) {
					var año = parseInt(partes[0]);
					var mesNumero = parseInt(partes[1]) - 1; // Restar 1 porque los meses van de 0 a 11
					var dia = parseInt(partes[2]);
					
					// Array con los nombres de los meses
					var nombresMeses = [
						"Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
						"Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
					];
					
					var mesNombre = nombresMeses[mesNumero];
					$("#pago_periodo").val(mesNombre + " / " + año);
				}
			});
		});
	</script>

	<script>	
		$(function () {
			

			$('.btn[data-filter]').on('click', function() {
			$('.btn[data-filter]').removeClass('active');
			$(this).addClass('active');
			});
		})
	</script>

	<script>
		$(function () {
			/* Lo único del bloque de ejemplo de AdminLTE que esta página
			   usaba de verdad. El resto —tempusdominus, daterangepicker,
			   duallistbox, colorpicker, bootstrap-switch y bs-stepper—
			   apuntaba a selectores inexistentes: se descargaban seis
			   librerías para ejecutar código contra nada. */
			$('[data-mask]').inputmask()
		})
	</script>

	<script>
		// Guardar el tab activo cuando el usuario cambia de pestaña
		document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
			tab.addEventListener('shown.bs.tab', function (e) {
				localStorage.setItem('activeTab', e.target.getAttribute('href'));
			});
		});

		// Antes de enviar cualquier formulario Ajax, guardar el tab activo
		document.querySelectorAll("form.FormularioAjax").forEach(form => {
			form.addEventListener("submit", function () {
				let activeTab = document.querySelector(".nav-pills .nav-link.active");
				if (activeTab) {
					localStorage.setItem("activeTab", activeTab.getAttribute("href"));
				}
			});
		});

		// Restaurar el tab activo después del reload
		window.addEventListener("load", function () {
			let activeTab = localStorage.getItem("activeTab");
			if (activeTab) {
				let tab = document.querySelector(`a[href="${activeTab}"]`);
				if (tab) {
					new bootstrap.Tab(tab).show(); // Bootstrap 4/5 activa el tab
				}
			}
		});
	</script>
	
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>