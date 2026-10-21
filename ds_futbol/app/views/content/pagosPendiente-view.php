<?php
	use app\controllers\pagosController;
	$insAlumno = new pagosController();	

	/* Esta pantalla se abre desde el listado con el id del pago en la URL.
	   Sin id la consulta quedaba truncada y la pagina moria mostrando el
	   SQL; con un id inexistente seguia pintando con $datos todavia como
	   PDOStatement y reventaba mas abajo. En ambos casos se vuelve al
	   listado, que es de donde se venia. */
	$pagoid = isset($url[1]) ? trim((string)$url[1]) : '';

	if ($pagoid === '' || !ctype_digit($pagoid)) {
		header("Location: " . APP_URL . "pagosList/");
		exit();
	}

	$datos=$insAlumno->BuscarPago($pagoid);

	if($datos->rowCount()==1){
		$datos=$datos->fetch();

		if ($datos['pago_archivo']!=""){
			$imagen = media_url('pago', $datos['pago_archivo']);
		}else{
			$imagen = ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg');
		}
	}else{
		header("Location: " . APP_URL . "pagosList/");
		exit();
	}

	$fechahoy = date('Y-m-d');
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Pago pendiente';
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
							<h1 class="m-0">Pago pendiente: <?php echo $datos['RUBRO']; ?></h1>
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
									<h3 class="card-title">Pago pendiente</h3>
								</div>						
								<div class="card-body">																			
									<div class="row">
										<div class="col-md-12">
											<div class="mb-3 campo">
												<label for="pago_fecha">Fecha de pago</label>
												<div class="input-group">
																							<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
									
													<input type="date" class="form-control" value="<?php echo $datos['pago_fecha']; ?>" disabled>
													
												</div>
												<!-- /.input group -->
											</div>
										</div>
										<div class="col-md-12">
											<div class="mb-3">
												<label for="pago_fecharegistro">Fecha de registro</label>
												<div class="input-group">
																							<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
									
													<input type="date" class="form-control" value="<?php echo $datos['pago_fecharegistro']; ?>" disabled>
												</div>
												<!-- /.input group -->
											</div>								
										</div>
										<div class="col-md-12">
											<div class="mb-3">
												<label for="pago_periodo">Periodo(mes/año)</label>															
												<input type="text" class="form-control" value="<?php echo $datos['pago_periodo']; ?>" disabled>															
											</div>								
										</div>
										
										<div class="col-md-6">
											<div class="mb-3">
												<label for="pago_valor">Valor</label>
												<input type="text" class="form-control text-end" value="<?php echo $datos['pago_valor']; ?>" disabled>
											</div>
										</div>

										<div class="col-md-6">
											<div class="mb-3">
												<label for="pago_saldo">Saldo</label>
												<input type="text" class="form-control text-end" value="<?php echo $datos['pago_saldo']; ?>" disabled>
											</div>
										</div>
										
										<div class="col-md-12">
											<div class="mb-3">
											<label for="pago_formapagoid">Forma de pago</label>
											<select class="form-select" id="pago_formapagoid" name="pago_formapagoid" disabled>																									
												<?php echo $insAlumno->listarOptionPagoid($datos['pago_formapagoid']); ?>
											</select>	
											</div>
										</div>
										<div class="col-md-12">
											<div class="mb-3">
											<label for="pago_concepto">Detalle</label>
											<textarea class="form-control" placeholder="Detalle del pago" rows="3" disabled><?php echo $datos['pago_concepto']; ?></textarea>
											</div>
										</div>
									
										<div class="col-md-12 ">
											<div class="mb-3">
												<label for="pago_archivo">Imagen pago</label>
												<div class="text-center">	
													<div class="row">
														<div class="col-sm-6">							
															<a href="<?php echo $imagen ?>" data-bs-toggle="lightbox" data-title="Pago" data-gallery="gallery">
																<img src="<?php echo $imagen ?>" class="profile-user-img img-fluid mb-2" alt="white sample"/>
															</a>	
														</div>
													</div>
												</div>													
											</div>
										<!-- /.mb-3 -->	
										</div>												
									</div>									
								</div><!-- /.card-body -->
							</div>
							<!-- /.card -->
						</div>
						<div class="col-md-9">
							<div class="card">								
								<div class="card-body">
									
									<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/pagosAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
									<input type="hidden" name="modulo_pagos" value="pagopendiente">											
									<input type="hidden" name="pago_id" value="<?php echo $pagoid; ?>">		
									<input type="hidden" name="pago_saldo" value="<?php echo $datos['pago_saldo']; ?>">
									<input type="hidden" name="pago_total" value="<?php echo $datos['pago_valor']; ?>">
									<input type="hidden" name="pago_rubro" value="<?php echo $datos['RUBRO']; ?>">
									<!-- Post -->
										<div class="row">
											<div class="col-md-4">
												<div class="mb-3 campo">
													<label for="pago_fecha">Fecha de pago</label>
													<div class="input-group">
																									<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
										
														<input type="date" class="form-control" id="pago_fecha" name="pago_fecha" value="<?php echo $fechahoy; ?>" >
														
													</div>
													<!-- /.input group -->
												</div>
											</div>
											<div class="col-md-4">
												<div class="mb-3">
													<label for="pago_fecharegistro">Fecha de registro</label>
													<div class="input-group">
																									<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
										
														<input type="date" class="form-control" id="pago_fecharegistro" name="pago_fecharegistro" value="<?php echo $fechahoy; ?>" >
													</div>
													<!-- /.input group -->
												</div>								
											</div>
											<?php
														if($datos['pago_rubroid'] == 'RPC'){
															echo '
																<div class="col-md-4">
																	<div class="mb-3">
																	<label for="pago_campeonatoid">Campeonato</label>
																	<select id="pago_campeonatoid" class="form-select" name="pago_campeonatoid" disabled>																									
																		'.$insAlumno->listarCampeonatos($datos['pago_campeonatoid']).'
																	</select>	
																	</div>
																</div>';
														}else{
															echo '
																<div class="col-md-4">
																	<div class="mb-3">
																		<label for="pago_periodo">Periodo(mes/año)</label>															
																		<input type="text" class="form-control" id="pago_periodo" name="pago_periodo" value="'.$datos['pago_periodo'].'" required>															
																	</div>								
																</div>';
														}
													?>
											
											<div class="col-md-4">
												<div class="mb-3">
													<label for="pago_valor">Valor</label>
													<input type="text" class="form-control text-end" id="pago_valor" name="pago_valor" placeholder="0.00" value="<?php echo $datos['pago_saldo']; ?>" >
												</div>
											</div>

											<div class="col-md-4">
												<div class="mb-3">
													<label for="pago_saldo">Saldo</label>
													<input type="text" class="form-control" disabled>
												</div>
											</div>
											
											<div class="col-md-4">
												<div class="mb-3">
												<label for="pago_formapagoid">Forma de pago</label>
												<select class="form-select" id="pago_formapagoid" name="pago_formapagoid" >																									
													<?php echo $insAlumno->listarOptionPagoid($datos['pago_formapagoid']); ?>
												</select>	
												</div>
											</div>
											
											<div class="col-md-2">
												<div class="mb-3">
													<label for="pago_archivo">Imagen pago</label>		
													<div class="input-group">											
														<div class="fileinput fileinput-new" data-provides="fileinput">
															<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput">
																<img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg'); ?>" alt="" id="miImagen">
															</div>
															<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
															<div>
																<span class="bton bton-white bton-file">
																	<span class="fileinput-new">Subir Pago</span>
																	<span class="fileinput-exists">Cambiar</span>
																	<input type="file" name="pago_archivo" id="pago_archivo">
																</span>
																<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">X</a>
															</div>
														</div>
													</div>		
												</div>
											<!-- /.mb-3 -->	
											</div>

											<div class="col-md-10">
												<div class="mb-3">
												<label for="pago_concepto">Detalle</label>
												<textarea class="form-control" id="pago_concepto" name="pago_concepto" placeholder="Detalle del pago" rows="5" ><?php echo "Pago pendiente por el valor de $".$datos['pago_saldo'] ." Dólares"; ?></textarea>
												</div>
											</div>

											
										</div>

										<button type="submit" class="btn btn-info btn-sm">Pagar</button>
										<?php include "./app/views/inc/btn_back.php";	?>						
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
													<th>Fecha</th>
													<th>Periodo</th>													
													<th>Pago</th>	
													<th>Saldo</th>										
													<th>Recibo</th>
													<th style="width:250px;">Opciones</th>																
												</tr>
											</thead>
											<tbody>
												<?php 
													echo $insAlumno->listarPagosPendientes($pagoid); 
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

	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>