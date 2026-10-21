<?php
	use app\controllers\empleadoController;
	$insempleado = new empleadoController();	

	$empleadoid		= $insempleado->limpiarCadena($url[1]);
	$foto 			= ds_recurso('ds_futbol/app/views/dist/img/default.png');
	$empleado_sexoM = "";
	$empleado_sexoF	= "";

	$datosempleado=$insempleado->seleccionarDatos("Unico","sujeto_empleado","empleado_id",$empleadoid);
	if($datosempleado->rowCount()==1){
		$datosempleado=$datosempleado->fetch();
		if ($datosempleado['empleado_foto']!=""){
			$foto = media_url('empleado', $datosempleado['empleado_foto']);
		}else{
			$foto = ds_recurso('ds_futbol/app/views/dist/img/default.png');
		}
		if ($datosempleado['empleado_genero']=='M'){
			$empleado_sexoM = "checked";
		}else{
			$empleado_sexoF = "checked";
		}

		$modulo_empleado 				= 'actualizar';		
		$empleado_sedeid 				= $datosempleado['empleado_sedeid'];
		$empleado_tipoidentificacion 	= $datosempleado['empleado_tipoidentificacion'];
		$empleado_identificacion 	  	= $datosempleado['empleado_identificacion'];
		$empleado_nombre		  		= $datosempleado['empleado_nombre'];
		$empleado_correo 			  	= $datosempleado['empleado_correo'];
		$empleado_celular 			  	= $datosempleado['empleado_celular'];
		$empleado_direccion 		  	= $datosempleado['empleado_direccion'];
		$empleado_tipopersonalid 		= $datosempleado['empleado_tipopersonalid'];
		$empleado_especialidadid 		= $datosempleado['empleado_especialidadid'];
		$empleado_fechaingreso			= $datosempleado['empleado_fechaingreso'];
		$empleado_sueldo				= $datosempleado['empleado_sueldo'];
	}else{
		$modulo_empleado 				= 'registrar';	
		$empleado_sedeid		  		= '';
		$empleado_tipoidentificacion	= '';
		$empleado_identificacion		= '';
		$empleado_nombre				= '';		
		$empleado_correo				= '';
		$empleado_celular				= '';
		$empleado_direccion				= '';
		$empleado_tipopersonalid		= '';
		$empleado_especialidadid 		= '';
		$empleado_fechaingreso			= '';
		$empleado_sueldo				= '';
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'empleados';
	$extras      = array (0 => 'datatables',1 => 'swal',);
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
				<h4 class="m-0">Empleados</h4>
				</div><!-- /.col -->
				<div class="col-sm-6">
				<ol class="breadcrumb float-sm-end">
					<li class="breadcrumb-item"><a href="#">Nuevo</a></li>
					<li class="breadcrumb-item active">Dashboard v1</li>
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
					<div class="card-header" class="centered" >
						<h4 class="card-title">Ingreso de nuevo empleado</h4>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<div class="row">
							<div class="col-md-12">	
								<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/empleadoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
								<input type="hidden" name="modulo_empleado" value="<?php echo $modulo_empleado; ?>">
								<input type="hidden" name="empleado_id" value="<?php echo $empleadoid; ?>">

								<div class="row" style="font-size: 13px;">						
									<div class="col-md-2">
										<div class="mb-3">
											<label for="empleado_foto">Foto</label>		
											<div class="input-group">											
												<div class="fileinput fileinput-new" data-provides="fileinput">
													<div class="fileinput-new thumbnail" style="width: 116px; height: 144px;" data-trigger="fileinput"><img src="<?php echo $foto; ?>"></div>
													<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 116px; max-height: 144px"></div>
													<div>
														<span class="bton bton-white bton-file" style="font-size: 13px;">
															<span class="fileinput-new">Seleccionar Foto</span>
															<span class="fileinput-exists">Cambiar</span>
															<input type="file" name="empleado_foto" id="foto" accept="image/*">
														</span>
														<a href="#" class="bton bton-orange fileinput-exists" style="font-size: 13px;" data-bs-dismiss="fileinput">Remover</a>
													</div>
												</div>
											</div>		
										</div>
										<!-- /.mb-3 -->								
									</div>
									<!-- /.col -->
									<div class="col-sm-10">
										<div class="row" style="font-size: 13px;">
											<div class="col-md-2">
												<div class="mb-3">
													<label for="empleado_sedeid">Sede</label>
													<select class="form-select" id="empleado_sedeid" name="empleado_sedeid">									
														<?php echo $insempleado->listarOptionSede($empleado_sedeid); ?>
													</select>	
												</div>
											</div> 
											<div class="col-sm-2">
												<div class="mb-3">
													<label for="empleado_tipoidentificacion">Tipo identificación</label>
													<select id="empleado_tipoidentificacion" class="form-control custom-select2" name="empleado_tipoidentificacion" value="<?php echo $empleado_tipoidentificacion; ?>">
														<?php echo $insempleado->OptionTipoIdentificacion($empleado_tipoidentificacion); ?>
													</select>
												</div>          
											</div>
											<div class="col-md-2">
												<div class="mb-3">
													<label for="empleado_identificacion">Identificación</label>                        
													<input type="text" class="form-control" id="empleado_identificacion" name="empleado_identificacion" value="<?php echo $empleado_identificacion; ?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="mb-3">
													<label for="empleado_nombre">Nombre y Apellido</label>
													<input type="text" class="form-control" id="empleado_nombre" name="empleado_nombre" value="<?php echo $empleado_nombre; ?>" required>
												</div>
											</div>
											<div class="col-md-2">
												<div class="mb-3">
													<label for="empleado_correo">Correo</label>
													<input type="email" class="form-control" id="empleado_correo" name="empleado_correo" value="<?php echo $empleado_correo; ?>" required>	
												</div>
											</div>
											<div class="col-md-2">
												<div class="mb-3">
													<label for="empleado_celular">Celular</label>
													<input type="text" class="form-control" id="empleado_celular" name="empleado_celular" data-inputmask='"mask": "0999999999"' value="<?php echo $empleado_celular; ?>" data-mask required>
												</div> 
											</div>
											<div class="col-md-3">
												<div class="mb-3">
													<label for="empleado_fechaingreso">Fecha de ingreso</label>
													<div class="input-group">
																									<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
										
														<input type="date" class="form-control" name="empleado_fechaingreso" id="empleado_fechaingreso" value="<?php echo $empleado_fechaingreso; ?>">
													</div>
												</div>
											</div>
											<div class="col-md-2">
												<div class="mb-3">
													<label for="empleado_sueldo">Honorarios USD</label>
													<input type="text" class="form-control" id="empleado_sueldo" name="empleado_sueldo" value="<?php echo $empleado_sueldo; ?>" required>
												</div> 
											</div>
											<div class="col-md-3">
												<div class="mb-3">
													<label for="empleado_tipopersonalid">Tipo empleado</label>
													<select class="form-select" id="empleado_tipopersonalid" name="empleado_tipopersonalid" onchange="toggleEspecialidad()">									
														<?php echo $insempleado->listarTipoPersonal($empleado_tipopersonalid); ?>
													</select>	
												</div>
											</div>
											<div class="col-md-4">
												<div class="mb-3">
													<label for="empleado_especialidadid">Especialidad</label>
													<select class="form-select" id="empleado_especialidadid" name="empleado_especialidadid" disabled>									
														<?php echo $insempleado->OptionEspecialidad($empleado_especialidadid); ?>
													</select>	
												</div>
											</div> 
											<div class="col-md-8">
												<div class="mb-3">
													<label for="empleado_direccion">Dirección</label>
													<input type="text" class="form-control" id="empleado_direccion" name="empleado_direccion" value="<?php echo $empleado_direccion; ?>" required>
												</div>	
											</div>
											<div class="col-md-3">
												<div class="mb-3">
													<label for="empleado_genero">Género</label>
													<div class="d-flex flex-wrap gap-4">
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="empleado_generoM" name="empleado_genero" value="M" <?php echo $empleado_sexoM; ?> required>
													        <label class="form-check-label" for="empleado_generoM">Masculino</label>
													    </div>
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="empleado_generoF" name="empleado_genero" value="F" <?php echo $empleado_sexoF; ?>>
													        <label class="form-check-label" for="empleado_generoF">Femenino</label>
													    </div>
													</div> 
												</div>
											</div>									
											<?php echo ds_acciones_form(APP_URL . 'empleadoList/', ['limpiar' => true]); ?>
										</div>								
									</div>
									<!-- /.col -->
								</div>
								</form>

								<div class="tab-custom-content">
									<h4 class="card-title">Empleados ingresados</h4>
								</div>
								
								<div class="tab-content" id="custom-content-above-tabContent" style="font-size: 13px;">
									<div class="table-responsive">
									<table id="example1" class="table table-bordered table-striped table-sm" style="font-size: 13px;">
										<thead>
											<tr>
												<th>Sede</th>
												<th>Identificación</th>
												<th>Nombre y apellido</th>
												<th>Correo</th>
												<th>Celular</th>												
												<th>Honorarios</th>
												<th>Acceso sistema</th>
												<th style="width: 180px;">Operaciones</th>
											</tr>
										</thead>
										<tbody>
											<?php 
												echo $insempleado->listarEmpleados(); 
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
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.responsive.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/responsive.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.buttons.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/buttons.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/buttons.html5.min.js"></script>
	<?php /* pdfmake y jszip pesan 2,2 MB y sirven a dos botones: se traen
			 al pulsarlos, no en cada carga. Va DESPUES de buttons.html5, que es
			 quien define esos botones. */ ?>
	<script src="<?php echo ds_recurso('ds_core/assets/js/exportar.js'); ?>"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/buttons.print.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/buttons.colVis.min.js"></script>
		<!-- InputMask -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/inputmask/jquery.inputmask.min.js"></script>
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<!-- fileinput -->
	
	<!-- Page specific script -->
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			new DataTable("#example1", {
			"responsive": true, "lengthChange": false, "autoWidth": false,
			"language": {
				"decimal": "",
				"emptyTable": "No hay datos disponibles en la tabla",
				"info": "Mostrando _START_ a _END_ de _TOTAL_ entradas",
				"infoEmpty": "Mostrando 0 a 0 de 0 entradas",
				"infoFiltered": "(filtrado de _MAX_ entradas totales)",
				"infoPostFix": "",
				"thousands": ",",
				"lengthMenu": "Mostrar _MENU_ entradas",
				"loadingRecords": "Cargando...",
				"processing": "Procesando...",
				"search": "Buscar:",
				"zeroRecords": "No se encontraron registros coincidentes",
				"paginate": {
					"first": "Primero",
					"last": "Último",
					"next": "Siguiente",
					"previous": "Anterior"
				},
				"aria": {
					"sortAscending": ": activar para ordenar la columna ascendente",
					"sortDescending": ": activar para ordenar la columna descendente"
				}
			},
			});			    
		});
	</script>

	<!-- Aplicar la máscara de entrada para el campo sueldo-->
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
            }).mask("#empleado_sueldo");
        });

		function toggleEspecialidad() {
			var tipoEmpleado = document.getElementById("empleado_tipopersonalid").value;
			var especialidad = document.getElementById("empleado_especialidadid");

			if (tipoEmpleado === "TPP") {
				especialidad.disabled = false; // Habilita el campo
			} else {
				especialidad.disabled = true; // Deshabilita el campo
			}
		}
	</script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>








