<?php
	use app\controllers\empleadoController;
	$insDescEgreso = new empleadoController();	

	$egresoid = ds_id_de_url($url, 1, APP_URL . 'egresoList/');

	$datos=$insDescEgreso->BuscarRubroEgreso($egresoid);
	
	if($datos->rowCount()==1){
		$datos=$datos->fetch(); 
		
		if ($datos['egreso_valor'] == $datos['egreso_pendiente']){
			$valor_descargado=0.00;
		}
		else{
			$valor_descargado=$datos['egreso_descargado'];
		}

	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "egresoList/");
		exit();
	}
	$fechahoy = date('Y-m-d');
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Saldo pendiente egresos';
	$extras      = array (0 => 'lightbox',1 => 'swal',);
	$cabeceraExtra = <<<'CSS'
	<style>
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
							<h1 class="m-0">Saldo pendiente: <?php echo $datos['RUBRO']; ?></h1>
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
				<!-- /.container-fluid informacion alumno -->
				<div class="container-fluid">
					<div class="row">
						<div class="col-md-3">
							<div class="card card-secondary">		
								<div class="card-header">
									<h3 class="card-title">Saldo pendiente egresos</h3>
								</div>						
								<div class="card-body">																			
									<div class="row">
										<div class="col-md-12">
											<div class="mb-3 campo">
												<label for="egreso_fechaegreso">Fecha de egreso</label>
												<div class="input-group">
																							<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
									
													<input type="date" class="form-control" value="<?php echo $datos['egreso_fechaegreso']; ?>" disabled>	
												</div>
												<!-- /.input group -->
											</div>
										</div>
										<div class="col-md-12">
											<div class="mb-3">
												<label for="egreso_fecharegistro">Fecha de registro</label>
												<div class="input-group">
																							<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
									
													<input type="date" class="form-control" value="<?php echo $datos['egreso_fecharegistro']; ?>" disabled>
												</div>
												<!-- /.input group -->
											</div>								
										</div>
										<div class="col-md-12">
											<div class="mb-3">
												<label for="egreso_periodo">Periodo(mes/año)</label>															
												<input type="text" class="form-control" value="<?php echo $datos['egreso_periodo']; ?>" disabled>															
											</div>								
										</div>										
										<div class="col-md-6">
											<div class="mb-3">
												<label for="egreso_descargado">Descargado</label>
												<input type="text" class="form-control text-end" value="<?php echo $valor_descargado; ?>" disabled>
											</div>
										</div>
										<div class="col-md-6">
											<div class="mb-3">
												<label for="egreso_pendiente">Pendiente</label>
												<input type="text" class="form-control text-end" value="<?php echo $datos['egreso_pendiente']; ?>" disabled>
											</div>
										</div>										
										<div class="col-md-12">
											<div class="mb-3">
											<label for="egreso_formaegresoid">Forma de egreso</label>
											<select class="form-select" id="egreso_formaegresoid" name="egreso_formaegresoid" disabled>																									
												<?php echo $insDescEgreso->listarOptionDescuento($datos['egreso_formaegresoid']); ?>
											</select>	
											</div>
										</div>
										<div class="col-md-12">
											<div class="mb-3">
												<label for="egreso_concepto">Detalle</label>
												<textarea class="form-control" placeholder="Detalle del egreso" rows="3" disabled><?php echo $datos['egreso_concepto']; ?></textarea>
											</div>
										</div>											
									</div>									
								</div><!-- /.card-body -->
							</div>
							<!-- /.card -->
						</div>
						<div class="col-md-9">
							<div class="card">								
								<div class="card-body">									
									<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/empleadoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
										<input type="hidden" name="modulo_egreso" value="descargoegreso">											
										<input type="hidden" name="trxegreso_egresoid" value="<?php echo $egresoid; ?>">		
										<input type="hidden" name="trxegreso_pendiente" value="<?php echo $datos['egreso_pendiente']; ?>">
										<input type="hidden" name="trxegreso_descargado" value="<?php echo $datos['egreso_descargado']; ?>">
										<input type="hidden" name="trxegreso_total" value="<?php echo $datos['egreso_valor']; ?>">
										<input type="hidden" name="trxegreso_formaegresoid" value="<?php echo $datos['RUBRO']; ?>">
										<!-- Post -->
											<div class="row">
												<div class="col-md-3">
													<div class="mb-3 campo">
														<label for="trxegreso_fecha">Fecha de descargo</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" id="trxegreso_fecha" name="trxegreso_fecha" value="<?php echo $fechahoy; ?>" >														
														</div>
														<!-- /.input group -->
													</div>
												</div>
												<div class="col-md-3">
													<div class="mb-3">
														<label for="trxegreso_fecharegistro">Fecha de registro</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" id="trxegreso_fecharegistro" name="trxegreso_fecharegistro" value="<?php echo $fechahoy; ?>" >
														</div>
														<!-- /.input group -->
													</div>								
												</div>
												<div class="col-md-3">
													<div class="mb-3">
														<label for="trxegreso_periodo">Periodo(mes/año)</label>															
														<input type="text" class="form-control" id="trxegreso_periodo" name="trxegreso_periodo" value="<?php echo $datos['egreso_periodo']; ?>">															
													</div>								
												</div>	
												<div class="col-md-3">
													<div class="mb-3">
														<label for="trxegreso_descargo">Valor descargo</label>
														<input type="text" class="form-control text-end" id="trxegreso_descargo" name="trxegreso_descargo" placeholder="0.00" value="<?php echo $datos['egreso_pendiente']; ?>" >
													</div>
												</div>	
												<div class="col-md-3">
													<div class="mb-3">
														<label for="trxegreso_formaegresoid">Periodicidad de descuento</label>
														<select class="form-select" id="trxegreso_formaegresoid" name="trxegreso_formaegresoid">																									
															<?php echo $insDescEgreso->listarPeriodicidadDescuento($trxegreso_formaegresoid); ?>
														</select>	
													</div>
												</div>
												<div class="col-md-9">
													<div class="mb-3">
														<label for="trxegreso_concepto">Detalle</label>
														<textarea class="form-control" id="trxegreso_concepto" name="trxegreso_concepto" placeholder="Detalle del egreso" rows="3" ><?php echo "Descargo pendiente del egreso ".$datos['RUBRO']." del periodo ".$datos['egreso_periodo']." por el valor de $".$datos['egreso_pendiente'] ." dólares"; ?></textarea>
													</div>
												</div>											
											</div>
											<button type="submit" class="btn btn-info btn-sm">Descargar</button>
											<?php include "./app/views/inc/btn_back.php";?>		
									</form>	
									<div class="tab-custom-content">
										<p class="lead mb-0">Descargos realizados</p>
									</div>
									<div class="tab-content" id="custom-content-above-tabContent">
										<div class="table-responsive">
										<table id="example1" class="table table-bordered table-striped table-sm">
											<thead>
												<tr>
													<th>No</th>
													<th>Fecha</th>
													<th>Periodo</th>
													<th>Egreso inicial</th>
													<th>Descargo</th>
													<th style="width:250px;">Opciones</th>																
												</tr>
											</thead>
											<tbody>
												<?php 
													echo $insDescEgreso->listarDescargosPendientes($egresoid); 
												?>								
											</tbody>
										</table>
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

	<!-- Ekko Lightbox -->
	<script src="<?php echo ds_recurso('ds_core/assets/js/visor.js'); ?>"></script>
	
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
		
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>

	<!--script src="app/views/dist/js/main.js" ></script-->
	
	<!-- fileinput -->
    
	<!-- Page specific script -->
	<script>
	$(function () {
		

		$('.btn[data-filter]').on('click', function() {
		$('.btn[data-filter]').removeClass('active');
		$(this).addClass('active');
		});
	})
	</script>

  </body>
</html>