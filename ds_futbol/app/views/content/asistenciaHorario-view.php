<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\asistenciaController;
	$insHorario = new asistenciaController();			
	
	$horario_id = (($url[1] ?? "") != "") ? $url[1] : 0;	
	$modulo_horario = ($horario_id == 0) ? 'registrar_horario' : 'actualizar_horario';
	
	$datos=$insHorario->seleccionarDatos("Unico","asistencia_horario","horario_id",$horario_id);
	if($datos->rowCount()==1){
		$datos=$datos->fetch();
		$lugar_sedeid 		= $datos['horario_sedeid'];
		$horario_nombre 	= $datos['horario_nombre'];
		$horario_detalle	= $datos['horario_detalle'];
		$horario_estado		= $datos['horario_estado'];
	}else{
		$lugar_sedeid = isset($_POST['horario_sedeid']) ? $insHorario->limpiarCadena($_POST['horario_sedeid']) : 0;
		$horario_nombre 	= "";
		$horario_detalle	= "";
		$horario_estado		= "";
	}

	$encabezado_vista = ($modulo_horario == 'registrar_horario') ? 'Creación de horario Sede ' : 'Edición horario '.$horario_nombre.' '.$horario_detalle.' Sede ';

	$sede=$insHorario->seleccionarDatos("Unico","general_sede","sede_id",$lugar_sedeid);
	if($sede->rowCount()==1){
		$sede=$sede->fetch();
		$sede_nombre = $sede['sede_nombre'];		
	}else{		
		$sede_nombre = "";
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Horarios';
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
							<h4 class="m-0"> <?php echo $encabezado_vista. $sede_nombre; ?></h4>
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
						<div class="card-header" style='min-height: 40px;'>
							<h3 class="card-title">Datos del horario</h3>

							<div class="card-tools">
								<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
							</div>
						</div>

						<div class="card-body">						

							<form class="FormularioAjax" id="quickForm" action="<?php echo APP_URL; ?>app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
								<input type="hidden" name="modulo_asistencia" value="<?php echo $modulo_horario; ?>">
								<input type="hidden" name="lugar_sedeid" value="<?php echo $lugar_sedeid; ?>">	
								<input type="hidden" name="horario_id" value="<?php echo $horario_id; ?>">										
								
								<div class="row" style='font-size: 14px;'>
									<div class="col-md-3">
										<div class="mb-3">
											<label for="horario_nombre">Horario nombre</label>
											<input type="text" class="form-control" style='height: 31px;' id="horario_nombre" name="horario_nombre" placeholder="Nombre" value="<?php echo $horario_nombre; ?>" required >
										</div>
									</div>
								
									<div class="col-md-9">
										<div class="mb-3">
											<label for="horario_detalle">Horario descripción</label>	
											<input type="text" class="form-control" style='height: 31px;' id="horario_detalle" name="horario_detalle" placeholder="Descripción" value="<?php echo $horario_detalle; ?>">
										</div>
									</div>									
								</div>
								<div class="tab-custom-content">
									<p class="lead mb-0" style="font-size:15px; height: 23px;">Horario de entrenamiento</p>
								</div>
								<div class="tab-content" id="custom-content-above-tabContent">
									<div class="table-responsive">
									<table id="presupuesto" name="presupuesto" class="table table-bordered table-striped table-sm" style='font-size: 14px;'>
										<thead>
											<tr style="text-align: center;">
												<th>Día</th>
												<th>Lugar entrenamiento</th>
												<th>Hora</th>
												<th>Profesor</th>
												<th><button type="button" class="btn btn-info btn-sm float-end btn_add" id="agregar" name="agregar">Agregar</button></th>
											</tr>
											<?php echo $insHorario->listarDetalleHorario($horario_id); ?>
										</thead>
										<tbody>
											<?php 
												//echo $insLugar->listarLugar(); 
											?>								
										</tbody>
									</table>
									</div>
								</div>

								<?php echo ds_acciones_form('', ['limpiar' => true, 'salirJs' => 'cerrarPestana()', 'volver' => 'Cerrar']); ?>
							</form>	

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
		$(document).ready(function() {
			$(".btn_add").on("click", function() {
				// Columna 1: Días de la semana
				var column1 = "<select class='form-select' name='dia[]'>" +
							"<option value='1'>Lunes</option>" +
							"<option value='2'>Martes</option>" +
							"<option value='3'>Miércoles</option>" +
							"<option value='4'>Jueves</option>" +
							"<option value='5'>Viernes</option>" +
							"<option value='6'>Sábado</option>" +
							"<option value='7'>Domingo</option>" +
							"</select>";
				
				// Columna 2: Lugares de entrenamiento con PHP
				var column2 = "<select class='form-select' id='lugar' name='lugar[]'><?php echo addslashes($insHorario->listarOptionLugar($lugar_sedeid,0)); ?></select>";
				
				// Columna 3: Horarios con PHP
				var column3 = "<select class='form-select' id='hora' name='hora[]'><?php echo addslashes($insHorario->listarOptionHora(0)); ?></select>";
				
				// Columna 4: Profesores con PHP
				var column4 = "<select class='form-select' id='profesor' name='profesor[]'><?php echo addslashes($insHorario->listarOptionProfesor($lugar_sedeid,0)); ?></select>";
				
				// Agregar una nueva fila a la tabla
				$("#presupuesto").append(
					"<tr><td>" + column1 + "</td>" +
					"<td>" + column2 + "</td>" +
					"<td>" + column3 + "</td>" +
					"<td>" + column4 + "</td>" +                    
					"<td><button type='button' class='btn btn-danger btn-sm btn-icon icon-left btn_remove float-end'>Eliminar<i class='entypo-trash'></i></button></td></tr>"
				);			    
			});

			// Evento para eliminar fila
			$(document).on("click", ".btn_remove", function() {
				$(this).closest("tr").remove();
			});
		});
	</script>
	<script type="text/javascript">
        function cerrarPestana() {
            window.close();
        }
    </script>
  </body>
</html>