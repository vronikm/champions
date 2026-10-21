<?php
	use app\controllers\balanceController;
	$insIngreso = new balanceController();
	
	$ingresoid = (($url[1] ?? "") != "") ? $url[1] : 0;	

	$foto = media_url('fingreso', 'ingreso_default.jpg');

	if($ingresoid != 0){
		$datosIngreso=$insIngreso->BuscarIngreso($ingresoid);		
		if($datosIngreso->rowCount()==1){
			$datosIngreso=$datosIngreso->fetch(); 
			if ($datosIngreso['ingreso_imagenpago']!=""){
				$foto = media_url('fingreso', $datosIngreso['ingreso_imagenpago']);
			}else{
				$foto = ds_recurso('ds_futbol/app/views/dist/img/ingreso_default.jpg');
			}
			$modulo_ingreso = 'actualizar';			

			$ingreso_sedeid			= $datosIngreso['ingreso_sedeid'];
			$ingreso_fecharecepcion	= $datosIngreso['ingreso_fecharecepcion'];
			$ingreso_empresa		= $datosIngreso['ingreso_empresa'];
			$ingreso_monto		 	= $datosIngreso['ingreso_monto'];
			$ingreso_formaentrega 	= $datosIngreso['ingreso_formaentrega'];
			$ingreso_concepto 		= $datosIngreso['ingreso_concepto'];
			$ingreso_descripcion 	= $datosIngreso['ingreso_descripcion'];
			
		}
	}else{
		$modulo_ingreso 		= 'registrar';
		$ingreso_sedeid			= '';
		$ingreso_fecharecepcion = '';
		$ingreso_empresa		= '';
		$ingreso_monto		 	= '';
		$ingreso_formaentrega 	= '';
		$ingreso_concepto		= '';
		$ingreso_descripcion 	= '';
			
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Ingresos';
	$extras      = array (0 => 'swal',);
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
					<h4 class="m-0">Ingresos</h4>
				</div><!-- /.col -->
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-end">
						<li class="breadcrumb-item"><a href="#">Inicio</a></li>
						<li class="breadcrumb-item active"><a href="<?php echo APP_URL."dashboard/" ?>">Dashboard</a></li>
					</ol>
				</div><!-- /.col -->
			</div><!-- /.row -->
			</div><!-- /.container-fluid -->
		</div>
		<!-- /.content-header -->

		<!-- Main content -->
		<section class="app-content">
			<div class="container-fluid">
			<!-- Small boxes (Stat box) -->
				<div class="card card-default">
					<div class="card-header" style='min-height: 40px;'>
						<h4 class="card-title">Registro de nuevo ingreso</h4>
						<div class="card-tools">							
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<div class="row">
							<div class="col-md-12">	
								<form class="FormularioAjax" id="quickForm" action="<?php echo APP_URL; ?>app/ajax/balanceAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
									<input type="hidden" name="modulo_ingreso" value="<?php echo $modulo_ingreso; ?>">
									<input type="hidden" name="ingreso_id" value="<?php echo $ingresoid; ?>">
									<div class="row" style="font-size: 13px; min-height: 187px;">
										<div class="col-md-2">
											<div class="mb-3">
												<label for="ingreso_imagenpago">Foto</label>		
												<div class="input-group">											
													<div class="fileinput fileinput-new" data-provides="fileinput">
														<div class="fileinput-new thumbnail" style="width: 110px; min-height: 130px;" data-trigger="fileinput"><img src="<?php echo $foto; ?>"> </div>
														<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 116px; max-height: 144px"></div>
														<div>
															<span class="bton bton-white bton-file" style="font-size: 13px;">
																<span class="fileinput-new">Seleccionar Foto</span>
																<span class="fileinput-exists">Cambiar</span>
																<input type="file" name="ingreso_imagenpago" id="foto" accept="image/*">
															</span>
															<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
														</div>
													</div>
												</div>		
											</div>
										<!-- /.mb-3 -->								
										</div>
										<div class="col-sm-10">
											<div class="row" style="font-size: 13px;">
												<div class="col-md-3">
													<div class="mb-3">
														<label for="ingreso_sedeid">Sede</label>
														<select class="form-select" id="ingreso_sedeid" name="ingreso_sedeid" onchange="ocultarDiv()" >																									
															<?php echo $insIngreso->listarOptionSede($ingreso_sedeid); ?>
														</select>	
													</div>
												</div>
												<div class="col-md-3">
													<div class="mb-3">
														<label for="ingreso_fecharecepcion">Fecha de recepción</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" name="ingreso_fecharecepcion" id="ingreso_fecharecepcion" value="<?php echo $ingreso_fecharecepcion; ?>">
														</div>
													</div>
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="ingreso_empresa">Nombre empresa</label>
														<input type="text"  class="form-control" id="ingreso_empresa" name="ingreso_empresa" value="<?php echo $ingreso_empresa; ?>">
													</div> 
												</div>
												<div class="col-md-2">
													<div class="mb-3">
														<label for="ingreso_monto">Monto USD</label>
														<input type="text" class="form-control" id="ingreso_monto" name="ingreso_monto" value="<?php echo $ingreso_monto; ?>" required>
													</div> 
												</div>																									
												<div class="col-md-3">
													<div class="mb-3">
														<label for="ingreso_formaentrega">Forma de recepción</label>
														<select class="form-select" id="ingreso_formaentrega" name="ingreso_formaentrega" onchange="ocultarDiv()" >																									
															<?php echo $insIngreso->listarFormaEntregaIngreso($ingreso_formaentrega); ?>
														</select>	
													</div>
												</div>
												<div class="col-md-3">
													<div class="mb-3">
														<label for="ingreso_concepto">Concepto</label>
														<select class="form-select" id="ingreso_concepto" name="ingreso_concepto" onchange="ocultarDiv()" >																									
															<?php echo $insIngreso->listarTipoIngreso($ingreso_concepto); ?>
														</select>	
													</div>
												</div>													
												<div class="col-md-6">
													<div class="mb-3">
														<label for="ingreso_descripcion">Descripción</label>
														<input type="text" class="form-control" id="ingreso_descripcion" name="ingreso_descripcion" value="<?php echo $ingreso_descripcion; ?>">
													</div>	
												</div>	
												<?php echo ds_acciones_form(APP_URL . 'ingresoList/', ['limpiar' => true]); ?>	
											</div>
										</div>
									</div>									
								</form><br>
								
								<div class="tab-custom-content">
									<h4 class="card-title">Ingresos registrados</h4>
								</div>										
								<div class="tab-content" id="custom-content-above-tabContent" style="font-size: 13px;">	
									<div class="table-responsive">
									<table id="example1" class="table table-bordered table-striped table-sm" style="font-size: 13px;">
										<thead>
											<tr>
												<th>Sede</th>
												<th>Empresa</th>
												<th>Monto</th>
												<th>Fecha de recepción</th>
												<th>Opciones</th>
											</tr>
										</thead>
										<tbody>
											<?php 
												echo $insIngreso->listarIngresos(); 
											?>							
										</tbody>	
									</table>
									</div>
								</div>
							</div>	
						</div>
					</div>
				</div>
			<!-- /.row -->
			</div><!-- /.container-fluid -->
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
	<!-- DataTables  & Plugins -->
	<?php /* pdfmake y jszip pesan 2,2 MB y sirven a dos botones: se traen
			 al pulsarlos, no en cada carga. Va DESPUES de buttons.html5, que es
			 quien define esos botones. */ ?>
	<!-- InputMask -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/inputmask/jquery.inputmask.min.js"></script>
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<!-- fileinput -->
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>

	<!-- Aplicar la máscara de entrada para el campo ingreso_monto-->
	<script>
        $(document).ready(function(){
            Inputmask({
                alias: "currency",
                prefix: "$ ",  // Prefijo de la moneda
                groupSeparator: ",",
                autoGroup: true,
                digits: 2,
                digitsOptional: false,
                placeholder: "0"
            }).mask("#ingreso_monto");
        });
    </script>    
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>








