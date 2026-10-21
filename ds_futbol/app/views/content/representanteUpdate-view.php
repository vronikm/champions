<?php
	use app\controllers\representanteController;
	$insRepre = new representanteController();	

	$repreid=$insRepre->limpiarCadena($url[1]);

	$repre_sexoM 		= "";
	$repre_sexoF 		= "";
	$repre_facturaS		= "";
	$repre_facturaN		= "";
	$repre_hermanosSi	= "";
	$repre_hermanosNo	= "";
	$conyuge_sexoM		= "";
	$conyuge_sexoF		= "";

	$datosrepresentante=$insRepre->seleccionarDatos("Unico","alumno_representante","repre_id",$repreid);
	if($datosrepresentante->rowCount()==1){
		$datosrepresentante=$datosrepresentante->fetch();
		if ($datosrepresentante['repre_sexo']=='M'){
			$repre_sexoM = "checked";
		}else{
			$repre_sexoF = "checked";
		}

		if ($datosrepresentante['repre_factura']=='S'){
			$repre_facturaS = "checked";
		}else{
			$repre_facturaN = "checked";
		}
	
		$repre_id					= $datosrepresentante['repre_id'];
		$repre_tipoidentificacion 	= $datosrepresentante['repre_tipoidentificacion'];
		$repre_identificacion 	  	= $datosrepresentante['repre_identificacion'];
		$repre_primernombre		  	= $datosrepresentante['repre_primernombre'];
		$repre_segundonombre 	 	= $datosrepresentante['repre_segundonombre'];
		$repre_apellidopaterno 	  	= $datosrepresentante['repre_apellidopaterno'];
		$repre_apellidomaterno 	 	= $datosrepresentante['repre_apellidomaterno'];
		$repre_direccion 		  	= $datosrepresentante['repre_direccion'];
		$repre_correo 			  	= $datosrepresentante['repre_correo'];
		$repre_celular 			  	= $datosrepresentante['repre_celular'];
		$repre_parentesco 		  	= $datosrepresentante['repre_parentesco'];
		
		$datosconyugerep=$insRepre->seleccionarDatos("Unico","alumno_representanteconyuge","conyuge_repid",$repreid);
		if($datosconyugerep->rowCount()==1){
			$datosconyugerep=$datosconyugerep->fetch();
			$conyuge_tipoidentificacion		=$datosconyugerep['conyuge_tipoidentificacion'];
			$conyuge_identificacion			=$datosconyugerep['conyuge_identificacion'];
			$conyuge_primernombre			=$datosconyugerep['conyuge_primernombre'];
			$conyuge_segundonombre			=$datosconyugerep['conyuge_segundonombre'];
			$conyuge_apellidopaterno		=$datosconyugerep['conyuge_apellidopaterno'];
			$conyuge_apellidomaterno		=$datosconyugerep['conyuge_apellidomaterno'];
			$conyuge_direccion				=$datosconyugerep['conyuge_direccion'];
			$conyuge_correo					=$datosconyugerep['conyuge_correo'];
			$conyuge_celular				=$datosconyugerep['conyuge_celular'];
			
			if ($datosconyugerep['conyuge_sexo']=='M'){
				$conyuge_sexoM = "checked";
			}else{
				$conyuge_sexoF = "checked";
			}

		}else{
			$conyuge_tipoidentificacion	="";
			$conyuge_identificacion		="";
			$conyuge_primernombre		="";
			$conyuge_segundonombre		="";
			$conyuge_apellidopaterno	="";
			$conyuge_apellidomaterno	="";
			$conyuge_direccion			="";
			$conyuge_correo				="";
			$conyuge_celular			="";
			$conyuge_sexoM 				="";
			$conyuge_sexoF 				="";
		}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Ficha representante';
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
							<h1 class="m-0">Actualizar Representante</h1>
						</div><!-- /.col -->
						<div class="col-sm-6">
							<ol class="breadcrumb float-sm-end">
								<li class="breadcrumb-item"><a href="#">Inicio</a></li>
								<li class="breadcrumb-item active">Actualizar Representante</li>
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
								<input type="hidden" name="alumno_repreid" value="<?php echo $repreid; ?>">	
							</ul>
						</div><!-- /.card-header -->
						<div class="card-body">
							<div class="tab-content">
								<!-- Tab información del representante -->
								<div class="active tab-pane" id="informacionp">
									<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/representanteAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
									<input type="hidden" name="modulo_repre" value="actualizar">	
									<input type="hidden" name="repre_id" value="<?php echo $datosrepresentante['repre_id']; ?>">																					
									<div class="row">
										<div class="col-sm-3">
											<div class="mb-3">
												<label for="repre_tipoidentificacion">Tipo identificación</label>
												<select class="form-select" id="repre_tipoidentificacion" name="repre_tipoidentificacion" >
													<?php echo $insRepre->listarOptionTipoIdentificacion($repre_tipoidentificacion); ?>
												</select>
											</div>          
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_identificacion">Identificación</label>                        
												<input type="text" class="form-control" id="repre_identificacion" name="repre_identificacion" value="<?php echo $repre_identificacion; ?>" >
											</div>
										</div>                   
										<div class="col-md-3">                        
											<div class="mb-3">
												<label for="repre_apellidopaterno">Apellido paterno</label>
												<input type="text" class="form-control" id="repre_apellidopaterno" name="repre_apellidopaterno" value="<?php echo $repre_apellidopaterno; ?>" >
											</div>
										</div>
										<div class="col-md-3">
											<label for="repre_apellidomaterno">Apellido materno</label>
											<input type="text" class="form-control" id="repre_apellidomaterno" name="repre_apellidomaterno" value="<?php echo $repre_apellidomaterno; ?>" >
										</div>
										<div class="col-md-3">                        
											<div class="mb-3">
												<label for="repre_primernombre">Primer nombre</label>
												<input type="text" class="form-control" id="repre_primernombre" name="repre_primernombre" value="<?php echo $repre_primernombre; ?>" >
											</div>
										</div>
										<div class="col-md-3">
											<label for="repre_segundonombre">Segundo nombre</label>
											<input type="text" class="form-control" id="repre_segundonombre" name="repre_segundonombre" value="<?php echo $repre_segundonombre; ?>" >
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_parentesco">Parentesco</label>
												<select class="form-select" style="width: 100%;" id="repre_parentesco" name="repre_parentesco" >													
													<?php echo $insRepre->listarCatalogoParentesco($repre_parentesco); ?>
												</select>
											</div> 
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_sexo">Género</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="repre_sexoM" name="repre_sexo" value="M" <?php echo $repre_sexoM;?>>
												        <label class="form-check-label" for="repre_sexoM" style="font-size: 14px;">Masculino</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="repre_sexoF" name="repre_sexo" value="F" <?php echo $repre_sexoF;?>>
												        <label class="form-check-label" for="repre_sexoF" style="font-size: 14px;">Femenino</label>
												    </div>
												</div> 
											</div>
										</div>
										<div class="col-md-4">
											<div class="mb-3">
												<label for="repre_direccion">Dirección</label>
												<input type="text" class="form-control" id="repre_direccion" name="repre_direccion" value="<?php echo $repre_direccion; ?>" >
											</div>
										</div>              
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_correo">Correo</label>
												<input type="text" class="form-control" id="repre_correo" name="repre_correo" value="<?php echo $repre_correo; ?>" >
											</div> 
										</div>
										<div class="col-md-2">
											<div class="mb-3">
												<label for="repre_celular">Celular</label>
												<input type="text" class="form-control" id="repre_celular" name="repre_celular" value="<?php echo $repre_celular; ?>" >
											</div> 
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="repre_factura">Requiere factura</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="repre_facturaS" value="S" name="repre_factura" <?php echo $repre_facturaS;?>>
												        <label class="form-check-label" for="repre_facturaS">Si</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="repre_facturaN" value="N" name="repre_factura" <?php echo $repre_facturaN;?>>
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
												<select class="form-select" id="conyuge_tipoidentificacion" name="conyuge_tipoidentificacion" >
													<?php echo $insRepre->listarOptionTipoIdentificacion($conyuge_tipoidentificacion); ?> 
												</select>
											</div>
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="conyuge_identificacion">Identificación</label>                        
												<input type="text" class="form-control" id="conyuge_identificacion" name="conyuge_identificacion" value="<?php echo $conyuge_identificacion;?>" >
											</div>
										</div>                   
										<div class="col-md-3">                        
											<div class="mb-3">
												<label for="conyuge_apellidopaterno">Apellido paterno</label>
												<input type="text" class="form-control" id="conyuge_apellidopaterno" name="conyuge_apellidopaterno" value="<?php echo $conyuge_apellidopaterno;?>" >
											</div>
										</div>
										<div class="col-md-3">
											<label for="conyuge_apellidomaterno">Apellido materno</label>
											<input type="text" class="form-control" id="conyuge_apellidomaterno" name="conyuge_apellidomaterno" value="<?php echo $conyuge_apellidomaterno;?>" >
										</div>
										<div class="col-md-3">                        
											<div class="mb-3">
												<label for="conyuge_primernombre">Primer nombre</label>
												<input type="text" class="form-control" id="conyuge_primernombre" name="conyuge_primernombre" value="<?php echo $conyuge_primernombre;?>" >
											</div>
										</div>
										<div class="col-md-3">
											<label for="conyuge_segundonombre">Segundo nombre</label>
											<input type="text" class="form-control" id="conyuge_segundonombre" name="conyuge_segundonombre" value="<?php echo $conyuge_segundonombre;?>" >
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="conyuge_celular">Celular</label>
												<input type="text" class="form-control" id="conyuge_celular" name="conyuge_celular"value="<?php echo $conyuge_celular;?>" >
											</div> 
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="conyuge_correo">Correo</label>
												<input type="text" class="form-control" id="conyuge_correo" name="conyuge_correo" value="<?php echo $conyuge_correo;?>" >
											</div> 
										</div>
										<div class="col-md-4">
											<div class="mb-3">
												<label for="conyuge_direccion">Dirección</label>
												<input type="text" class="form-control" id="conyuge_direccion" name="conyuge_direccion" value="<?php echo $conyuge_direccion;?>" >	
											</div>
										</div>              
										<div class="col-md-4">
											<div class="mb-3">
												<label for="conyuge_sexo">Género</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="conyuge_sexoM" name="conyuge_sexo" value="M" <?php echo $conyuge_sexoM;?>>
												        <label class="form-check-label" for="conyuge_sexoM">Masculino</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="conyuge_sexoF" name="conyuge_sexo" value="F" <?php echo $conyuge_sexoF;?>>
												        <label class="form-check-label" for="conyuge_sexoF">Femenino</label>
												    </div>
												</div> 
											</div>
										</div>               
									</div>										
								</div>								
								<div class="card-footer">	
									<button type="submit" class="btn btn-success btn-sm">Actualizar</button>						
									<?php include "./app/views/inc/btn_back.php";	?>					
								</div>	
									
								</form>	
							</div>
							<!-- /.tab-pane -->
						</div><!-- /.card-body -->
					</div><!-- /.card -->
				</div>
			</section>
			<!-- /.content -->    
			
			<?php
				}else{
					include "./app/views/inc/error_alert.php";
				}
			?>
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
    
	<!-- Page specific script -->
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			/* Aqui habia una tabla de DataTables sobre #representados, pero la
			   libreria no se carga en esta vista: cada visita lanzaba
			   «ReferenceError: DataTable is not defined». La tabla se ve bien
			   sin ella; traer doce archivos para paginar tres filas no sale a
			   cuenta. */			    
		});
	</script>
  </body>
</html>