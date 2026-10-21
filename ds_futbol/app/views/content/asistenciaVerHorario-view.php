<?php	
	use app\controllers\asistenciaController;

	include 'app/lib/barcode.php';
	
	$generator = new barcode_generator();
	$symbology="qr";
	$optionsQR=array('sx'=>4,'sy'=>4,'p'=>-10);		

	$insHorario = new asistenciaController();	
	/* El respaldo a 0 no evitaba nada: la consulta no encontraba horario y
	   la vista seguía con $sede aún como PDOStatement. */
	$horario_id = ds_id_de_url($url, 1, APP_URL . 'asistenciaListHorario/');

	$datoshorario=$insHorario->seleccionarDatos("Unico","asistencia_horario","horario_id",$horario_id);
	if($datoshorario->rowCount()==1){
		$datoshorario=$datoshorario->fetch();
		$lugar_sedeid 		= $datoshorario['horario_sedeid'];
		$horario_nombre 	= $datoshorario['horario_nombre'];
		$horario_detalle	= $datoshorario['horario_detalle'];
		$horario_estado		= $datoshorario['horario_estado'];
	}else{
		$lugar_sedeid = isset($_POST['horario_sedeid']) ? $insHorario->limpiarCadena($_POST['horario_sedeid']) : 0;
		$horario_nombre 	= "";
		$horario_detalle	= "";
		$horario_estado		= "";
	}

	$sede=$insHorario->informacionSede($lugar_sedeid);
	if($sede->rowCount()==1){
		$sede=$sede->fetch(); 
    } else {
			/* Sin registro, $sede seguiria siendo el statement y la
			   vista lo usaria como array: error fatal en pantalla, con la
			   ruta del servidor dentro. Se vuelve a donde ya vuelve esta
			   misma vista cuando el identificador no sirve. */
			header("Location: " . APP_URL . 'asistenciaListHorario/');
			exit();
		}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Horario';
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
							<h4 class="m-0">Horario <?php echo $horario_nombre.' '.$horario_detalle." Sede ". $sede['sede_nombre']; ?></h4>
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
								<!-- info row -->
								<div class="row invoice-info">
									<div class="col-sm-6 invoice-col">										
										<address class="text-center">												
											<img src="<?php echo ds_logo_url((int)$sede["sede_id"]) ?>" style="width: 100px; height: 100px;"/>
											<br>Dirección: <?php echo $sede["sede_direccion"]; ?><br>
											Celular: <?php echo $sede["sede_telefono"]." - ".$sede["sede_nombre"]; ?> - ECUADOR								
										</address>
									</div>
									<!-- /.col -->
									<div class="col-sm-6 invoice-col">									
										<address class="text-center">	
											<strong class="profile-username"><?php echo ds_nombre_organizacion_may($sede["sede_id"]); ?></strong><br>
											<div class="row">
												<div class="col-12 table-responsive">
													<div class="row">
														<div class="col-4"></div>														
														<div class="col-4">
															<div class="table-responsive">
															<table class="table table-striped table-sm">
																<thead>
																	<tr style="font-size: 14px">
																		<th>DIA</th>
																		<th>MES</th>
																		<th>AÑO</th>																
																	</tr>
																</thead>
																<tbody>
																	<tr style="font-size: 14px">
																		<td><?php echo  date('d', strtotime(date('Y-m-d'))); ?></td>
																		<td><?php echo date('m', strtotime(date('Y-m-d'))); ?></td>
																		<td><?php echo date('Y', strtotime(date('Y-m-d'))); ?></td>												
																	</tr>														
																</tbody>
															</table>
															</div>
														</div>
														<div class="col-4"></div>
													</div>	
												</div>
												<!-- /.col -->
											</div>
										</address>
									</div>
									<!-- /.col -->								
								</div>
								<!-- Table row -->
								<div class="row">
									<div class="col-12 table-responsive">
										<table class="table table-striped table-bordered  table-sm">											
											<tbody>
												<tr>													
													<th colspan="8">Horario <?php echo $horario_nombre.". ".$horario_detalle; ?></th>																							
												</tr>
												<tr>		
													<th></th>												
													<th>LUNES</th>	
													<th>MARTES</th>
													<th>MIERCOLES</th>
													<th>JUEVES</th>
													<th>VIERNES</th>																																		
												</tr>													
													<?php echo $datos=$insHorario->generarHorario($horario_id);	?>																		
											</tbody>
										</table>
									</div>
									<!-- /.col -->
								</div>
								<!-- /.row -->
								<!-- this row will not appear when printing -->
								<div class="row no-print">
									<div class="col-12">
										<?php /* Flex en vez de float: el orden del código es el
												 orden en pantalla, y el ms-auto separa sin
												 depender de un margen puesto a ojo. */ ?>
										<div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
											<button class="btn btn-dark btn-back btn-sm" onclick="cerrarPestana()">Regresar</button>

											<a href="<?php echo APP_URL.'asistenciaHorarioPDF/'.$horario_id.'/'; ?>"
											   class="btn btn-dark btn-sm ms-auto" target="_blank">
												<i class="fas fa-print"></i> Imprimir
											</a>
										</div>
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