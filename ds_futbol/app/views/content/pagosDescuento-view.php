<?php
	setlocale(LC_TIME, 'es_EC.UTF-8');

	use app\controllers\pagosController;
	$insAlumno = new pagosController();	

	$alumno = ds_id_de_url($url, 1, APP_URL . 'pagosList/');

	$datos=$insAlumno->BuscarAlumnoDescuento($alumno);
	
	if($datos->rowCount()==1){
		$datos=$datos->fetch(); 
		
		if ($datos['alumno_imagen']!=""){
			$foto = media_url('alumno', $datos['alumno_imagen']);
		}else{
			$foto=ds_recurso('ds_futbol/app/views/dist/img/alumno.jpg');
		}		
		
		$descuento=$insAlumno->BuscarDescuento($alumno);
		if($descuento->rowCount()==1){
			$descuento=$descuento->fetch(); 
			
			$modulo_pagos			= "descuentoUP";
			$descuento_id 			= $descuento["descuento_id"];
			$descuento_rubroid 		= $descuento["descuento_rubroid"];
			$descuento_alumnoid 	= $descuento["descuento_alumnoid"];
			$descuento_valor 		= $descuento["descuento_valor"];
			$descuento_detalle 		= $descuento["descuento_detalle"];
			$descuento_fecha 		= $descuento["descuento_fecha"];
			$descuento_estado		= $descuento["descuento_estado"];
			
		}else{
			
			$modulo_pagos			= "descuento";
			$descuento_id 			= "";
			$descuento_rubroid 		= "";	 
			$descuento_alumnoid 	= ""; 
			$descuento_valor 		= ""; 		
			$descuento_detalle 		= "";
			$descuento_fecha 		= date('Y-m-d');
			$descuento_estado		= "";
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
	$tituloVista = 'Descuentos';
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
							<h1 class="m-0">Descuentos alumno</h1>
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
											<b>Estado del alumno</b> <a class="float-end"><?php echo $datos['estado']; ?></a>
										</li>
										<li class="list-group-item">
											<b>Fecha de ingreso</b> <a class="float-end"><?php echo $datos['alumno_fechaingreso']; ?></a>
										</li>
									</ul>
								</div>
								<!-- /.card-body -->
							</div>
							<!-- /.card -->
						</div>

						<div class="col-md-9">
							<div class="card">						
								<div class="card-body">
									<div class="tab-content">
										<div class="active tab-pane" id="pension"> 
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
											<input type="hidden" name="modulo_pagos" value="<?php echo $modulo_pagos; ?>">											
											<input type="hidden" name="descuento_alumnoid" value="<?php echo $datos['alumno_id']; ?>">											
																
											<div class="row">
												<div class="col-md-3">
													<div class="mb-3">
													<label for="descuento_rubroid">Tipo de descuento</label>
													<select class="form-select" id="descuento_rubroid" name="descuento_rubroid" >																									
														<?php echo $insAlumno->listarOptionDescuento($descuento_rubroid); ?>
													</select>	
													</div>
												</div>	
												<div class="col-md-3">
													<div class="mb-3">
														<label for="descuento_valor">Valor</label>
														<input type="text" class="form-control text-end" id="descuento_valor" name="descuento_valor" placeholder="0.00" pattern="^\d+(\.\d{1,2})?$" value="<?php echo $descuento_valor; ?>" required>
													</div>
												</div>											
												<div class="col-md-3">
													<div class="mb-3">
														<label for="descuento_fecha">Fecha de registro</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" id="descuento_fecha" name="descuento_fecha" value="<?php echo $descuento_fecha; ?>" required>
														</div>
														<!-- /.input group -->
													</div>								
												</div>
												<div class="col-md-3">
													<div class="mb-3">
													<label for="descuento_estado">Estado</label>
													<select class="form-select" id="descuento_estado" name="descuento_estado" >	
														<?php
															if ($descuento_estado == "S"){
																echo "<option value='S' selected>Activo</option>
																	 <option value='N'>Inactivo</option>";
															}else{
																echo "<option value='S'>Activo</option>
																	 <option value='N' selected>Inactivo</option>";
															}														
														?>
													</select>	
													</div>
												</div>																							
											
												<div class="col-md-12">
													<div class="mb-3">
													<label for="descuento_detalle">Detalle</label>
													<textarea class="form-control" id="descuento_detalle" name="descuento_detalle" placeholder="Detalle del descuento" rows="3"><?php echo $descuento_detalle; ?></textarea>
													</div>
												</div>											
											</div>	
											
											<?php echo ds_acciones_form('', ['limpiar' => true]); ?>

											</form>							
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
	<!-- Bootstrap 4 -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	
	<!-- Select2 -->
	<!-- Bootstrap4 Duallistbox -->
	<!-- InputMask -->
	<!-- date-range-picker -->
	<!-- bootstrap color picker -->
	<!-- Tempusdominus Bootstrap 4 -->
	<!-- Bootstrap Switch -->
	<!-- BS-Stepper -->

	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
		
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>



  </body>
</html>