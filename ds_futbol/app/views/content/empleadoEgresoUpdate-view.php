<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\empleadoController;
	$insEgreso = new empleadoController();	

	$empleadoid	= (($url[1] ?? "") != "") ? $url[1] : 0;
	$egreso_id 	= (($url[2] ?? "") != "") ? $url[2] : 0;

	$datosEgreso=$insEgreso->BuscarEgreso($egreso_id);

	if($datosEgreso->rowCount()==1){
		$datosEgreso=$datosEgreso->fetch();
		
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

	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "egresoList/");
		exit();
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Modificación egresos';
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
							<h1 class="m-0">Modificación de egreso <?php echo $egreso_fechaegreso?> </h1>
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
						<div class="col-md-12">
							<div class="card">

								<div class="card-body">
									<div class="tab-content">
											<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/empleadoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
												<input type="hidden" name="modulo_egreso" value="actualizar">									
												<input type="hidden" name="egreso_empleadoid" value="<?php echo $empleadoid; ?>">
												<input type="hidden" name="egreso_id" value="<?php echo $egreso_id; ?>">
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
															<input type="text" class="form-control" id="egreso_periodo" name="egreso_periodo" placeholder="Mes/año" value="<?php echo $egreso_periodo; ?>" required>															
														</div>								
													</div>
													<div class="col-md-2">
														<div class="mb-3">
															<label for="egreso_tipoid">Tipo de egreso</label>
															<select class="form-select" id="egreso_tipoid" name="egreso_tipoid">																									
																<?php echo $insEgreso->listarTipoEgreso($egreso_tipoid); ?>
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
																<?php echo $insEgreso->listarPeriodicidadDescuento($egreso_formaegresoid); ?>
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
				var fecha = new Date(value);				
				// Array con los nombres de los meses
				var nombresMeses = [
				"Enero","Febrero", "Marzo", "Abril", "Mayo", "Junio",
				"Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
				];
				// Obtener el mes (los meses van de 0 a 11 en JavaScript)
				var mesNumero = fecha.getMonth();
				var mesNombre = nombresMeses[mesNumero];
				var año = fecha.getFullYear();

				$("#egreso_periodo").val(mesNombre + " / " + año );
			});
		});		
	</script>
  </body>
</html>