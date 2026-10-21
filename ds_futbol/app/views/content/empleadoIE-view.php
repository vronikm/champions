<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\empleadoController;
	$insEmpleado = new empleadoController();	

	$empleadoid	= (($url[1] ?? "") != "") ? $url[1] : 0;
	$ingreso_id = (($url[2] ?? "") != "") ? $url[2] : 0;
	$egreso_id 	= (($url[2] ?? "") != "") ? $url[2] : 0;

	$factura 	 = ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg');
	$comprobante = ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg');
	$especialidad = '';

	$datos=$insEmpleado->BuscarEmpleado($empleadoid);

	if($datos->rowCount()==1){
		$datos=$datos->fetch(); 
		$especialidad = $datos['Especialidad'];

		if ($especialidad =="TPP"){
			$datos['catalogo_valor']=="EEG";
		}
		
		if ($datos['empleado_foto']!=""){
			$foto = media_url('empleado', $datos['empleado_foto']);
		}else{
			$foto=ds_recurso('ds_futbol/app/views/dist/img/default.png');
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
		
	}else{
		include "app/views/inc/error_alert.php";
		exit;
	}
	
	$modulo_egreso 		= 'registrar';

	if($ingreso_id != 0){
		$datosIngreso=$insEmpleado->BuscarIngreso($ingreso_id);		
		if($datosIngreso->rowCount()==1){
			$datosIngreso=$datosIngreso->fetch(); 
			if ($datosIngreso['ingreso_factura']!=""){
				$factura = media_url('ingreso', $datosIngreso['ingreso_factura']);
			}else{
				$factura = ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg');
			}
			if ($datosIngreso['ingreso_comprobante']!=""){
				$comprobante = media_url('ingreso', $datosIngreso['ingreso_comprobante']);
			}else{
				$comprobante = ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg');
			}
			$modulo_ingreso 		= 'actualizar';
			$ingreso_formapagoid	= $datosIngreso['ingreso_formapagoid'];
			$ingreso_tipoingresoid	= $datosIngreso['ingreso_tipoingresoid'];
			$ingreso_empleadoid		= $datosIngreso['ingreso_empleadoid'];
			$ingreso_valor			= $datosIngreso['ingreso_valor'];
			$ingreso_concepto		= $datosIngreso['ingreso_concepto'];
			$ingreso_fechafactura	= $datosIngreso['ingreso_fechafactura'];
			$ingreso_fechapago		= $datosIngreso['ingreso_fechapago'];
			$ingreso_periodo		= $datosIngreso['ingreso_periodo'];
			$ingreso_estado			= $datosIngreso['ingreso_estado'];
			$ingreso_factura		= $datosIngreso['ingreso_factura'];
			$ingreso_comprobante	= $datosIngreso['ingreso_comprobante'];
			$ingreso_fechasistema	= $datosIngreso['ingreso_fechasistema'];

			$egreso_formaegresoid 	= '';
			$egreso_tipoid		 	= '';
			$egreso_empleadoid 		= '';		
			$egreso_valor 			= '';
			$egreso_saldo 			= '';
			$egreso_concepto 		= '';
			$egreso_fechaegreso		= '';
			$egreso_fecharegistro	= '';
			$egreso_periodo 		= '';
			$egreso_estado 			= 'A';
		}
	}else{
		$modulo_ingreso 		= 'registrar';
		$ingreso_formapagoid 	= '';
		$ingreso_tipoingresoid 	= '';
		$ingreso_empleadoid 	= '';		
		$ingreso_valor 			= '';
		$ingreso_concepto 		= '';
		$ingreso_fechafactura	= '';
		$ingreso_fechapago		= '';
		$ingreso_periodo 		= '';
		$ingreso_estado 		= 'A';
	}
	if($egreso_id != 0){
		$datosEgreso=$insEmpleado->BuscarEgreso($egreso_id);		
		if($datosEgreso->rowCount()==1){
			$datosEgreso=$datosEgreso->fetch(); 

			$modulo_egreso 			= 'actualizar';
			$egreso_formaegresoid	= $datosEgreso['egreso_formaegresoid'];
			$egreso_tipoid			= $datosEgreso['egreso_tipoid'];
			$egreso_empleadoid		= $datosEgreso['egreso_empleadoid'];
			$egreso_valor			= $datosEgreso['egreso_valor'];
			$egreso_pendiente		= $datosEgreso['egreso_pendiente'];
			$egreso_concepto		= $datosEgreso['egreso_concepto'];
			$egreso_fechaegreso		= $datosEgreso['egreso_fechaegreso'];
			$egreso_fecharegistro	= $datosEgreso['egreso_fecharegistro'];
			$egreso_periodo			= $datosEgreso['egreso_periodo'];
			$egreso_estado			= $datosEgreso['egreso_estado'];
			$egreso_fechasistema	= $datosEgreso['egreso_fechasistema'];
		}
	}else{
		$modulo_egreso 		= 'registrar';
		$egreso_formaegresoid 	= '';
		$egreso_tipoid		 	= '';
		$egreso_empleadoid 		= '';		
		$egreso_valor 			= '';
		$egreso_pendiente		= '';
		$egreso_concepto 		= '';
		$egreso_fechaegreso		= '';
		$egreso_fecharegistro	= '';
		$egreso_periodo 		= '';
		$egreso_estado 			= 'P';
	}
	
	$consolidadoanticipo=$insEmpleado->ConsolidadoAnticipo($empleadoid);
	if($consolidadoanticipo->rowCount()==1){
		$consolidadoanticipo=$consolidadoanticipo->fetch();
		if($consolidadoanticipo["ANTICIPO_PENDIENTE"] > 0){
			$textoegreso ="Egresos pendientes";
			$textodetalle ="Empleado tiene egresos pendientes de descargo. ";
			$clase = '<a class="float-end text-danger">';
			$alert = "alert-warning";
			$alerta = "S";
		}
		else{
			$textoegreso = 'Sin egresos pendientes';
			$clase = '<a class="float-end">';
			$clase = '<a class="float-end text-danger">';
			$alerta = "N";
		}
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Registro ingresos';
	$extras      = array (0 => 'swal',);
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
							<h1 class="m-0">Honorarios de empleados</h1>
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

									<h3 class="profile-username text-center"><?php echo $datos['empleado_nombre']; ?></h3>

									<p class="text-muted text-center"><?php echo $datos['empleado_identificacion']; ?></p>

									<ul class="list-group list-group-unbordered mb-3">
										<li class="list-group-item">
											<b>Tipo</b> <a class="float-end"><?php echo $datos['Especialidad']; ?></a>
										</li>
										<li class="list-group-item">
											<b>Estado empleado</b> <a class="float-end"><?php echo $datos['estado']; ?></a>
										</li>
										<li class="list-group-item">
											<b>Fecha de ingreso</b> <a class="float-end"><?php echo $datos['empleado_fechaingreso']; ?></a>
										</li>
										<li class="list-group-item">
											<b>Estado</b> <a class="float-end"><?php echo $clase.$textoegreso.'</a>'; ?></a>
										</li>
										<li class="list-group-item">
											<b>Detalle egresos pendientes</b> 
											<div class="table-responsive">
											<table class="table table-sm">	

												<?php 
												echo $insEmpleado->AnticipoPendiente($empleadoid); 
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
										<li class="nav-item"><a class="nav-link active" href="#ingreso" data-bs-toggle="tab">Ingresos</a></li>
										<li class="nav-item"><a class="nav-link" href="#egreso" data-bs-toggle="tab">Egresos</a></li>									
									</ul>
								</div><!-- /.card-header -->

								<div class="card-body">
									<div class="tab-content">
										<div class="active tab-pane" id="ingreso"> 
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/empleadoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
												<input type="hidden" name="modulo_ingreso" value="<?php echo $modulo_ingreso; ?>">									
												<input type="hidden" name="ingreso_id" value="<?php echo $ingreso_id; ?>">
												<input type="hidden" name="ingreso_empleadoid" value="<?php echo $datos['empleado_id']; ?>">
												<!-- Post -->
												<div class="row">
													<div class="col-md-4">
														<div class="mb-3">
															<label for="ingreso_fechafactura">Fecha factura</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
																												
																<input type="date" class="form-control" id="ingreso_fechafactura" name="ingreso_fechafactura" value="<?php echo $ingreso_fechafactura; ?>" required>																
															</div>
															<!-- /.input group -->
														</div>
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="ingreso_fechapago">Fecha de pago</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control" id="ingreso_fechapago" name="ingreso_fechapago" value="<?php echo $ingreso_fechapago; ?>" required>
															</div>
															<!-- /.input group -->
														</div>								
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="ingreso_periodo">Periodo(mes/año)</label>															
															<input type="text" class="form-control" id="ingreso_periodo" name="ingreso_periodo" value="<?php echo $nombreMes; ?>" required>															
														</div>								
													</div>
													<div class="container-fluid">
														<div class="row mb-2">
															<div class="col-md-2">
																<div class="mb-3">
																	<label for="ingreso_factura">Factura</label>		
																	<div class="input-group">											
																		<div class="fileinput fileinput-new" data-provides="fileinput">
																			<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput"><img src="<?php echo $factura ?>"></div>
																			<div class="fileinput-preview fileinput-exists thumbnail" style="width: 130px; height: 158px"></div>
																			<div>
																				<span class="bton bton-white bton-file">
																					<span class="fileinput-new">Subir factura</span>
																					<span class="fileinput-exists">Cambiar</span>
																					<input type="file" name="ingreso_factura" id="ingreso_factura">
																				</span>
																				<a href="<?php echo $factura ?>" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
																			</div>
																		</div>
																	</div>		
																</div>
															</div><!-- /.mb-3 -->		
															<div class="col-md-2">
																<div class="mb-3">
																	<label for="ingreso_comprobante">Comprobante</label>		
																	<div class="input-group">											
																		<div class="fileinput fileinput-new" data-provides="fileinput">
																			<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput"><img src="<?php echo $comprobante ?>"></div>
																			<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
																			<div>
																				<span class="bton bton-white bton-file">
																					<span class="fileinput-new">Subir Pago</span>
																					<span class="fileinput-exists">Cambiar</span>
																					<input type="file" name="ingreso_comprobante" id="ingreso_comprobante">
																				</span>
																				<a href="<?php echo $comprobante ?>" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
																			</div>
																		</div>
																	</div>		
																</div>
															</div><!-- /.mb-3 -->		
															<div class="col-md-5">
																<div class="mb-3">
																	<label for="ingreso_valor">Valor</label>
																	<input type="text" class="form-control text-end" id="ingreso_valor" name="ingreso_valor" placeholder="0.00" pattern="^\d+(\.\d{1,2})?$" value="<?php echo $ingreso_valor; ?>" required>
																</div>														
																<div class="col-md-14">	
																	<div class="mb-3">
																		<label for="ingreso_concepto">Detalle</label>
																		<input type="text" class="form-control text-end" id="ingreso_concepto" name="ingreso_concepto" value="<?php echo $ingreso_concepto; ?>" required>
																	</div>	
																</div>
															</div>
															<div class="col-md-3">
																<div class="mb-3">
																	<label for="ingreso_formapagoid">Forma de pago</label>
																	<select class="form-select" id="ingreso_formapagoid" name="ingreso_formapagoid">																									
																		<?php echo $insEmpleado->listarOptionPago($ingreso_formapagoid); ?>
																	</select>	
																</div>												
																<div class="mb-3">
																	<label for="ingreso_tipoingresoid">Tipo de ingreso</label>
																	<select class="form-select" id="ingreso_tipoingresoid" name="ingreso_tipoingresoid">																									
																		<?php echo $insEmpleado->listarTipoIngreso($ingreso_tipoingresoid); ?>
																	</select>	
																</div>
															</div>
														</div>		
													</div>											
												</div>
												<?php 
													if($alerta == "S"){
														echo '
															<div class="col-md-12">
																<div class="alert '.$alert.' alert-dismissible">
																	<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
																	<h5><i class="icon fas fa-info"></i> Aviso!</h5>
																	'.$textodetalle.'
																</div>
															</div>
														';
													}
												?>
												<?php echo ds_acciones_form(APP_URL . 'empleadoIE/' . $empleadoid . '/', ['limpiar' => true]); ?>
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
															<th>Mes/Año</th>
															<th>Valor</th>
															<th>F. Pago</th>
															<th>T. Pago</th>
															<th>Estado</th>															
															<th style="width:200px;">Opciones</th>																
														</tr>
													</thead>
													<tbody>
														<?php 
															echo $insEmpleado->listarPagosIngreso($empleadoid); 
														?>								
													</tbody>
												</table>
												</div>
											</div>	
										</div>

										<div class="tab-pane" id="egreso"> 
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/empleadoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
												<input type="hidden" name="modulo_egreso" value="<?php echo $modulo_egreso; ?>">									
												<input type="hidden" name="egreso_id" value="<?php echo $egreso_id; ?>">
												<input type="hidden" name="egreso_empleadoid" value="<?php echo $datos['empleado_id']; ?>">
												<!-- Post -->
												<div class="row">
													<div class="col-md-4">
														<div class="mb-3">
															<label for="egreso_fechaegreso">Fecha de egreso</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
																												
																<input type="date" class="form-control" id="egreso_fechaegreso" name="egreso_fechaegreso" value="<?php echo $egreso_fechaegreso; ?>" required>																
															</div>
															<!-- /.input group -->
														</div>
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="egreso_fecharegistro">Fecha de registro</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control" id="egreso_fecharegistro" name="egreso_fecharegistro" value="<?php echo $egreso_fecharegistro; ?>" required>
															</div>
															<!-- /.input group -->
														</div>								
													</div>
													<div class="col-md-2">
														<div class="mb-3">
															<label for="egreso_periodo">Periodo(mes/año)</label>															
															<input type="text" class="form-control" id="egreso_periodo" name="egreso_periodo" value="<?php echo $nombreMes; ?>" required>															
														</div>								
													</div>
													<div class="col-md-2">
														<div class="mb-3">
															<label for="egreso_tipoid">Tipo de egreso</label>
															<select class="form-select" id="egreso_tipoid" name="egreso_tipoid">																									
																<?php echo $insEmpleado->listarTipoEgreso($egreso_tipoid); ?>
															</select>	
														</div>
													</div>
													<div class="col-md-2">
														<div class="mb-3">
															<label for="egreso_valor">Valor</label>
															<input type="text" class="form-control text-end" id="egreso_valor" name="egreso_valor" placeholder="0.00" pattern="^\d+(\.\d{1,2})?$" value="<?php echo $egreso_valor; ?>" required>
														</div>
													</div>
													<div class="col-md-3">
														<div class="mb-3">
															<label for="egreso_formaegresoid">Periodicidad de descuento</label>
															<select class="form-select" id="egreso_formaegresoid" name="egreso_formaegresoid">																									
																<?php echo $insEmpleado->listarPeriodicidadDescuento($egreso_formaegresoid); ?>
															</select>	
														</div>
													</div>
													<div class="col-md-7">	
														<div class="mb-3">
															<label for="egreso_concepto">Detalle</label>
															<input type="text" class="form-control" id="egreso_concepto" name="egreso_concepto" value="<?php echo $egreso_concepto; ?>" required>
														</div>	
													</div>												
												</div>
												<?php echo ds_acciones_form(APP_URL . 'empleadoIE/' . $empleadoid . '/', ['limpiar' => true]); ?>
											</form>
											<div class="tab-custom-content">
												<p class="lead mb-0">Egresos registrados</p>
											</div>
											<div class="tab-content" id="custom-content-above-tabContent">
												<div class="table-responsive">
												<table id="example1" class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>
															<th>Mes/Año</th>
															<th>Valor Egreso</th>
															<th>Pendiente</th>
															<th>Tipo Egreso</th>
															<th>Forma Egreso</th>
															<th>Estado</th>															
															<th style="width:250px;">Opciones</th>																
														</tr>
													</thead>
													<tbody>
														<?php 
															echo $insEmpleado->listarEgresos($empleadoid); 
														?>								
													</tbody>
												</table>
												</div>
											</div>	
										</div>
									</div>
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
		
	<script>
			$(document).ready(function () {
				$("#egreso_fechaegreso").keyup(function () {
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
						$("#egreso_periodo").val(mesNombre + " / " + año);
					}
				});
			});
		</script>

		<script>
			$(document).ready(function () {
				$("#ingreso_fechapago").keyup(function () {
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
						$("#ingreso_periodo").val(mesNombre + " / " + año);
					}
				});
			});
		</script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>