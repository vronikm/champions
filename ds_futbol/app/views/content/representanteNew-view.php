<?php
	use app\controllers\representanteController;
	$insRepre = new representanteController();
	
	$repreid=$insRepre->limpiarCadena($url[1]);
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Registro nuevo representante';
	$extras      = array (0 => 'dropzone',1 => 'swal',);
	$cabeceraExtra = <<<'CSS'
	<style>
		input:invalid {
		  box-shadow: 0 0 2px 1px red;
		}
		input:focus:invalid {
		  box-shadow: none;
		}
		textarea:invalid {
		  box-shadow: 0 0 2px 1px red;
		}
		textarea:focus:invalid {
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
							<h1 class="m-0">Nuevo Representante</h1>
						</div><!-- /.col -->
						<div class="col-sm-6">
							<ol class="breadcrumb float-sm-end">
								<li class="breadcrumb-item"><a href="#">Inicio</a></li>
								<li class="breadcrumb-item active">Ficha Representante</li>
							</ol>
						</div><!-- /.col -->
					</div><!-- /.row -->
				</div><!-- /.container-fluid -->
			</div>
			<!-- /.content-header -->

			<!-- Main content -->
			<section class="app-content">				
				<!-- /.container-fluid información representante -->
				<div class="container-fluid">						
					<div class="card">
						<div class="card-header p-2">
							<ul class="nav nav-pills">
								<li class="nav-item"><a class="nav-link active" href="#informacionp" data-bs-toggle="tab">Información Personal</a></li>
								<li class="nav-item"><a class="nav-link" href="#conyuge" data-bs-toggle="tab">Cónyuge</a></li>								
							</ul>
						</div><!-- /.card-header -->
					
						<div class="card-body">
							<div class="tab-content">
								<!-- Tab información del representante -->
								<div class="active tab-pane" id="informacionp">
									<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/representanteAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
									<input type="hidden" name="modulo_repre" value="registrar">																						
									<div class="row">
										<div class="col-sm-3">
											<div class="mb-3">
												<label for="repre_tipoidentificacion">Tipo identificación</label>
												<select id="repre_tipoidentificacion" class="form-control custom-select2" name="repre_tipoidentificacion" >
													<?php echo $insRepre->listarCatalogoTipoDocumento(); ?>
												</select>
											</div>          
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_identificacion">Identificación</label>                        
												<input type="text" class="form-control" id="repre_identificacion" name="repre_identificacion" placeholder="Identificación" required>
											</div>
										</div>                   
										<div class="col-md-3">                        
											<div class="mb-3">
												<label for="repre_apellidopaterno">Apellido paterno</label>
												<input type="text" class="form-control" id="repre_apellidopaterno" name="repre_apellidopaterno" placeholder="Primer apellido" required>
											</div>
										</div>
										<div class="col-md-3">
											<label for="repre_apellidomaterno">Apellido materno</label>
											<input type="text" class="form-control" id="repre_apellidomaterno" name="repre_apellidomaterno" placeholder="Segundo apellido" >
										</div>
										<div class="col-md-3">                        
											<div class="mb-3">
												<label for="repre_primernombre">Primer nombre</label>
												<input type="text" class="form-control" id="repre_primernombre" name="repre_primernombre" placeholder="Primer nombre" required>
											</div>
										</div>
										<div class="col-md-3">
											<label for="repre_segundonombre">Segundo nombre</label>
											<input type="text" class="form-control" id="repre_segundonombre" name="repre_segundonombre" placeholder="Segundo nombre" >
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_parentesco">Parentesco</label>
												<select class="form-select" style="width: 100%;" id="repre_parentesco" name="repre_parentesco" >													
													<?php echo $insRepre->listarCatalogoParentesco(); ?>
												</select>
											</div> 
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_sexo">Género</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="repre_sexoM" value="M" name="repre_sexo" required>
												        <label class="form-check-label" for="repre_sexoM" style="font-size: 14px;">Masculino</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="repre_sexoF" value="F" name="repre_sexo">
												        <label class="form-check-label" for="repre_sexoF" style="font-size: 14px;">Femenino</label>
												    </div>
												</div> 
											</div>
										</div>
										<div class="col-md-4">
											<div class="mb-3">
												<label for="repre_direccion">Dirección</label>
												<input type="text" class="form-control" id="repre_direccion" name="repre_direccion"  required>
											</div>
										</div>              
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_correo">Correo</label>
												<input type="text" class="form-control" id="repre_correo" name="repre_correo" placeholder="Correo" required>
											</div> 
										</div>
										<div class="col-md-2">
											<div class="mb-3">
												<label for="repre_celular">Celular</label>
												<input type="text" class="form-control" id="repre_celular" name="repre_celular" data-inputmask='"mask": "0999999999"' placeholder="Celular" required data-mask>
											</div> 
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_factura">Requiere factura</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="repre_facturaS" value="S" name="repre_factura" required>
												        <label class="form-check-label" for="repre_facturaS">Si</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="repre_facturaN" value="N" name="repre_factura">
												        <label class="form-check-label" for="repre_facturaN">No</label>
												    </div>
												</div> 
											</div>
										</div>
									</div>									
								</div>

								<!-- Tab información del conyuge representante --> 
								<div class="tab-pane" id="conyuge">
									<div class="row">
										<div class="col-md-3">											
											<div class="mb-3">
												<label for="TidentificacionCRep">Tipo identificación</label>
												<select id="conyuge_tipoidentificacion" class="form-control custom-select2" name="conyuge_tipoidentificacion" >
													<?php echo $insRepre->listarCatalogoTipoDocumento(); ?>
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="conyuge_identificacion">Identificación</label>                        
												<input type="text" class="form-control" id="conyuge_identificacion" name="conyuge_identificacion" placeholder="Identificación" >
											</div>
										</div>                   
										<div class="col-md-3">                        
											<div class="mb-3">
												<label for="conyuge_apellidopaterno">Apellido paterno</label>
												<input type="text" class="form-control" id="conyuge_apellidopaterno" name="conyuge_apellidopaterno" placeholder="Primer apellido" >
											</div>
										</div>
										<div class="col-md-3">
											<label for="conyuge_apellidomaterno">Apellido materno</label>
											<input type="text" class="form-control" id="conyuge_apellidomaterno" name="conyuge_apellidomaterno" placeholder="Segundo apellido" >
										</div>
										<div class="col-md-3">                        
											<div class="mb-3">
												<label for="conyuge_primernombre">Primer nombre</label>
												<input type="text" class="form-control" id="conyuge_primernombre" name="conyuge_primernombre" placeholder="Primer nombre" >
											</div>
										</div>
										<div class="col-md-3">
											<label for="conyuge_segundonombre">Segundo nombre</label>
											<input type="text" class="form-control" id="conyuge_segundonombre" name="conyuge_segundonombre" placeholder="Segundo nombre" >
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="conyuge_celular">Celular</label>
												<input type="text" class="form-control" id="conyuge_celular" name="conyuge_celular" data-inputmask='"mask": "0999999999"' placeholder="Celular" data-mask>
											</div> 
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="conyuge_correo">Correo</label>
												<input type="text" class="form-control" id="conyuge_correo" name="conyuge_correo" placeholder="Correo" >
											</div> 
										</div>
										<div class="col-md-4">
											<div class="mb-3">
												<label for="conyuge_direccion">Dirección</label>
												<input type="text" class="form-control" id="conyuge_direccion" name="conyuge_direccion" placeholder="Barrio, Calle principal, #casa, calle secundaria" >	
											</div>
										</div>              
										<div class="col-md-4">
											<div class="mb-3">
												<label for="conyuge_sexo">Género</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="conyuge_sexoM" value="M" name="conyuge_sexo">
												        <label class="form-check-label" for="conyuge_sexoM">Masculino</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="conyuge_sexoF" value="F" name="conyuge_sexo">
												        <label class="form-check-label" for="conyuge_sexoF">Femenino</label>
												    </div>
												</div> 
											</div>
										</div>               
									</div>										
								</div>										
								<?php echo ds_acciones_form('', ['limpiar' => true]); ?>
									
								</form>	
							</div>
							<!-- /.tab-pane -->
						</div><!-- /.card-body -->
						<!-- /.tab-content -->
					</div><!-- /.card -->
				</div><!-- /.container fluid -->
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
	<!-- dropzonejs -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/dropzone/min/dropzone.min.js"></script>

	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
		
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>

	<!--script src="app/views/dist/js/main.js" ></script-->
	
	<!-- fileinput -->
    
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
  </body>
</html>