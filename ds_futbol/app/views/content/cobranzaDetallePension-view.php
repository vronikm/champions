<?php	
	use app\controllers\cobranzaController;

	include 'app/lib/barcode.php';
	
	$generator = new barcode_generator();
	$symbology="qr";
	$optionsQR=array('sx'=>4,'sy'=>4,'p'=>-10);		

	$insDetallePendiente = new cobranzaController();	
	$repre_id = (($url[1] ?? "") != "") ? $insDetallePendiente->limpiarCadena($url[1]) : 0;
	$lugar_sedeid = isset($_POST['horario_sedeid']) ? $insHorario->limpiarCadena($_POST['horario_sedeid']) : 0;

	$datosPendiente=$insDetallePendiente->seleccionarDatos("Unico","alumno_representante","repre_id",$repre_id);
	if($datosPendiente->rowCount()==1){
		$datosPendiente		= $datosPendiente->fetch();
		$repre_nombre 		= $datosPendiente['repre_primernombre'].' '.$datosPendiente['repre_segundonombre'].' '.$datosPendiente['repre_apellidopaterno'].' '.$datosPendiente['repre_apellidomaterno'];
	
	}else{
		$repre_nombre 	= "";
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Cobranza pensiones';
	$extras      = array (0 => 'dropzone',1 => 'swal',);
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
							<h5 class="m-0">Detalle de pensiones pendientes <?php echo $repre_nombre; ?></h5>
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
								<div class="col-sm-11 invoice-col">									
									<address class="text-center"><br>
										<strong class="profile-username"><?php echo ds_nombre_organizacion_may((int)$lugar_sedeid); ?></strong><br><br>											
										<div class="row">
											<div class="row">
												<div class="col-4"></div>														
												<div class="col-12">
													Representante: <?php echo $repre_nombre;?>
												</div>
												<div class="col-11">
													Fecha de notificación: <?php echo date('d-m-Y');?>
												</div>
											</div>
											<!-- /.col -->
										</div>
									</address>
								</div>							
								<!-- Table row -->
								<div class="row">
									<div class="col-12 table-responsive">
										<table class="table table-striped table-bordered table-sm">											
											<tbody>
												<tr>	
													<th>Sede</th>												
													<th>Identificación</th>	
													<th>Nombre</th>
													<th>Cant. Pendientes</th>
													<th>Saldos Pendientes</th>
													<th>Cant. Pensiones</th>																																		
													<th>Valor Completo</th>	
												</tr>													
													<?php echo $datos=$insDetallePendiente->pensionesPendientes($repre_id);	?>																		
											</tbody>
										</table>
									</div>
									<!-- /.col -->
								</div>
								<!-- /.row -->
								<!-- this row will not appear when printing -->
								<div class="row no-print">
									<div class="col-12">
										<?php include "./app/views/inc/btn_back.php";?>	
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
    
	<script type="text/javascript">
        function cerrarPestana() {
            window.close();
        }
    </script>
  </body>
</html>