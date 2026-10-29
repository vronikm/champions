<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\asistenciaController;
	$insLugar = new asistenciaController();	

	$lugarid = (($url[1] ?? "") != "") ? $url[1] : 0;	

	if($lugarid != 0){
		$datos=$insLugar->BuscarLugar($lugarid);		
		if($datos->rowCount()==1){
			$datos=$datos->fetch(); 
			$modulo_lugar = 'actualizar_lugar';
			$lugar_nombre = $datos['lugar_nombre'];
			$lugar_direccion = $datos['lugar_direccion'];
			$lugar_detalle = $datos['lugar_detalle'];
			$lugar_sedeid = $datos['lugar_sedeid'];
			$lugar_estado= $datos['lugar_estado'];
		}
	}else{
		$modulo_lugar = 'registrar_lugar';
		$lugar_nombre = '';
		$lugar_direccion = '';
		$lugar_detalle = '';
		$lugar_sedeid  = 0;
		$lugar_estado = 'A';
	}	
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Lugar entrenamiento';
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
							<h4 class="m-0">Lugar de entrenamiento</h4>
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

					<div class="card card-default">						
						<div class="card-header" style="font-size: 13px; min-height: 40px;">
							<h3 class="card-title">Ingreso de nuevo lugar de entrenamiento</h3>

							<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
							<button type="button" class="btn btn-tool" data-lte-toggle="card-remove" title="Quitar" aria-label="Quitar">
								<i class="fas fa-times"></i>
							</button>
							</div>
						</div>

						<div class="card-body">
							<form class="FormularioAjax" id="quickForm" action="<?php echo APP_URL; ?>app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
								<input type="hidden" name="modulo_asistencia" value="<?php echo $modulo_lugar; ?>">
								<input type="hidden" name="lugar_id" value="<?php echo $lugarid; ?>">											
								
								<div class="row" style='font-size: 14px;'>
									<div class="col-md-2">
										<div class="mb-3" style='font-size: 13px;'>
										<label for="lugar_sedeid">Sede</label>
										<select class="form-select" style='font-size: 13px; height: 30px;' id="lugar_sedeid" name="lugar_sedeid">																									
											<?php echo $insLugar->listarOptionSedebusqueda($lugar_sedeid, true); ?>
										</select>	
										</div>
									</div>

									<div class="col-md-5">
										<div class="mb-3" style='font-size: 13px; min-height: 15px;'>
											<label for="lugar_nombre">Lugar de entrenamiento</label>
											<input type="text" class="form-control" style='font-size: 15px; height: 30px;' id="lugar_nombre" name="lugar_nombre" value="<?php echo $lugar_nombre; ?>">
										</div>	
									</div>

									<div class="col-md-5">
										<div class="mb-3">
											<label for="lugar_direccion">Dirección</label>
											<input type="text" class="form-control" style='font-size: 15px; height: 30px;' id="lugar_direccion" name="lugar_direccion" value="<?php echo $lugar_direccion; ?>">
										</div>	
									</div>

									<div class="col-md-10">
										<div class="mb-3">
											<label for="lugar_detalle">Ubicación</label>
											<input type="text" class="form-control" style='font-size: 15px; height: 30px;' id="lugar_detalle" name="lugar_detalle" value="<?php echo $lugar_detalle; ?>">
										</div>
									</div>

									<div class="col-md-2" style='font-size: 13px; min-height: 15px;'>
										<div class="mb-3">
											<label for="estado">Estado</label>
											<select class="form-select" style='font-size: 13px; height: 30px;' id="estado" name="estado">		
												<?php 
													if($lugar_estado == 'A'){
														echo '<option value="A" selected>Activo</option>
															<option value="I" >Inactivo</option>';
													}else{
														echo '<option value="A" >Activo</option>
															<option value="I" selected>Inactivo</option>';	
													}
												?>																				
												
											</select>	
										</div>
									</div>
									
									<?php echo ds_acciones_form(APP_URL . 'asistenciaLugar/', ['limpiar' => true]); ?>
								</div>	
							</form>

							<div class="tab-custom-content">
								<p class="lead mb-0" style="font-size:15px; height: 23px;">Lugares de entrenamiento</p>
							</div>
							<div class="tab-content" id="custom-content-above-tabContent">
								<div class="table-responsive">
								<table id="example1" class="table table-bordered table-striped table-sm" style="font-size: 13px;">
									<thead>
										<tr>
											<th>N.</th>
											<th>Sede</th>
											<th>Nombre</th>
											<th>Dirección</th>
											<th>Ubicación</th>	
											<th>Estado</th>														
											<th style="width:150px;">Opciones</th>																
										</tr>
									</thead>
									<tbody>
										<?php 
											echo $insLugar->listarLugar(); 
										?>								
									</tbody>
								</table>
								</div>
							</div>						
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

	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>

	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery-validation/jquery.validate.min.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery-validation/additional-methods.min.js"></script>
	
	<script>
		$(function () {

			//Datemask dd/mm/yyyy
			$('#datemask').inputmask('dd/mm/yyyy', { 'placeholder': 'dd/mm/yyyy' })
			//Datemask2 mm/dd/yyyy
			$('#datemask2').inputmask('mm/dd/yyyy', { 'placeholder': 'mm/dd/yyyy' })
			//Money Euro
			$('[data-mask]').inputmask()

		})  
	</script>	

	<script>
		$(function () {
			
			$('#quickForm').validate({
				rules: {
				hora_inicio: {
					required: true       
				},
				hora_fin: {
					required: true
				},
				},
				messages: {
				hora_inicio: {
					required: "Por favor ingrese una hora"
				},
				hora_fin: {
					required: "Por favor ingrese una hora",
					minlength: "Your password must be at least 5 characters long"
				},
				},
				errorElement: 'span',
				errorPlacement: function (error, element) {
				error.addClass('invalid-feedback');
				element.closest('.mb-3').append(error);
				},
				highlight: function (element, errorClass, validClass) {
				$(element).addClass('is-invalid');
				},
				unhighlight: function (element, errorClass, validClass) {
				$(element).removeClass('is-invalid');
				}
			});
		});
	</script>
	


  </body>
</html>