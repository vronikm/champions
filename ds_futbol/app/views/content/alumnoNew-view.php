<?php
	use app\controllers\representanteController;
	$insRepre = new representanteController();

	use app\controllers\alumnoController;
	$insAlumno = new alumnoController();
	
	$repreid=$insAlumno->limpiarCadena($url[1]);
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Registro nuevo alumno';
	$extras      = array (0 => 'select2',1 => 'dropzone',2 => 'swal',);
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
							<h1 class="m-0">Nuevo Alumno</h1>
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
					<?php /* data-ds-wizard convierte estas pestañas en un asistente por
							 pasos: sin el, se puede llegar a la ultima y pulsar Guardar con
							 los obligatorios de la primera vacios, y no ocurre nada. */ ?>
					<div class="card" data-ds-wizard>
						<div class="card-header p-2">
							<ul class="nav nav-pills">
								<li class="nav-item"><a class="nav-link active" href="#informacionp" data-bs-toggle="tab">Información Personal</a></li>
								<li class="nav-item"><a class="nav-link" href="#cedula" data-bs-toggle="tab">Cédula</a></li>
								<li class="nav-item"><a class="nav-link" href="#contactoem" data-bs-toggle="tab">Contacto emergencia</a></li>											
								<li class="nav-item"><a class="nav-link" href="#informacionm" data-bs-toggle="tab">Información Médica</a></li>
								<li class="nav-item"><a class="nav-link" href="#horario" data-bs-toggle="tab">Horario</a></li>
							</ul>
						</div><!-- /.card-header -->
					
						<div class="card-body">
							<div class="tab-content">
								<!-- Tab de información personal del alumno -->
								<div class="active tab-pane" id="informacionp"> 
									<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/alumnoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">
										<input type="hidden" name="modulo_alumno" value="registrar">	
										<input type="hidden" name="alumno_repreid" value="<?php echo $repreid; ?>">																					

										<!-- Primera sección foto-->
										<div class="row">
											<div class="col-md-2">
												<div class="mb-3">
													<label for="alumno_foto">Foto (250KB)</label>		
													<div class="input-group">											
														<div class="fileinput fileinput-new" data-provides="fileinput">
															<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput">
																<img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/alumno.jpg'); ?>" alt=""></div>
															<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
															<div>
																<span class="bton bton-white bton-file">
																	<span class="fileinput-new">Seleccionar Foto</span>
																	<span class="fileinput-exists">Cambiar</span>
																	<input type="file" name="alumno_foto" id="alumno_foto" accept="image/*">
																</span>
																<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
															</div>
														</div>
													</div>		
												</div>
												<!-- /.mb-3 -->	
											</div>
											<div class="col-md-10"> 
												<div class="row">
													<div class="col-md-4">
														<div class="mb-3">
															<label for="alumno_identificacion">Identificación</label>                        
															<input type="text" class="form-control" id="alumno_identificacion" name="alumno_identificacion" placeholder="Identificación" required="required">
														</div>
													</div>											
													<div class="col-md-4">                        
														<div class="mb-3">
															<label for="alumno_apellido1">Apellido paterno</label>
															<input type="text" class="form-control" id="alumno_apellido1" name="alumno_apellido1" placeholder="Primer apellido" required="required">
														</div>
													</div>
													<div class="col-md-4">
														<label for="alumno_apellido2">Apellido materno</label>
														<input type="text" class="form-control" id="alumno_apellido2" name="alumno_apellido2" placeholder="Segundo apellido">
													</div>
													<div class="col-sm-4">
														<div class="mb-3">
															<label for="alumno_tipoidentificacion">Tipo identificación</label>
															<select id="alumno_tipoidentificacion" class="form-select" name="alumno_tipoidentificacion">																					
																<?php echo $insAlumno->listarCatalogoTipoDocumento(); ?>
															</select>
														</div>          
													</div>
													<div class="col-md-4">                        
														<div class="mb-3">
															<label for="alumno_nombre1">Primer nombre</label>
															<input type="text" class="form-control" id="alumno_nombre1" name="alumno_nombre1" placeholder="Primer nombre" required="required">
														</div>
													</div>
													<div class="col-md-4">
														<label for="alumno_nombre2">Segundo nombre</label>
														<input type="text" class="form-control" id="alumno_nombre2" name="alumno_nombre2" placeholder="Segundo nombre">
													</div>    
													<div class="col-md-4">
														<div class="mb-3">
															<label for="alumno_nacionalidadid">Nacionalidad</label>
															<select class="form-select" style="width: 100%;" id="alumno_nacionalidadid" name="alumno_nacionalidadid">
																<?php echo $insAlumno->listarCatalogoNacionalidad(); ?>
															</select>
														</div> 
													</div>
													<div class="col-md-4">									
														<div class="mb-3">
															<label for="alumno_fechanacimiento">Fecha nacimiento</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control" name="alumno_fechanacimiento" required="required">
															</div>
														<!-- /.input group -->
														</div>												
													</div>
													<div class="col-md-4">
														<div class="mb-3">
															<label for="alumno_fechaingreso">Fecha ingreso</label>
															<div class="input-group">
																													<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
												
																<input type="date" class="form-control" name="alumno_fechaingreso" required="required">
															</div>
														<!-- /.input group -->
														</div>								
													</div>
												</div>
												<!-- Fin primera sección foto-->
											</div>
										</div> <!--fin col md 10-->

										<!-- Segunda sección foto-->
										<div class="row">
											<div class="col-md-2">
												<div class="mb-3">
													<label for="Numcamiseta">Número de camiseta</label>
													<input type="text" class="form-control" id="alumno_numcamiseta" name="alumno_numcamiseta" placeholder="Número de camiseta"> 
												</div>
											</div>
											<div class="col-md-2">
												<div class="mb-3">
													<label for="alumno_sedeid">Sede</label>
													<select class="form-select" id="alumno_sedeid" name="alumno_sedeid">									
														<?php echo $insAlumno->listarOptionSede($_SESSION['rol'], $_SESSION['usuario']); ?>
													</select>	
												</div>
											</div> 
											<div class="col-md-3">
												<div class="mb-3">
													<label for="alumno_direccion">Dirección</label>
													<textarea class="form-control" id="alumno_direccion" name="alumno_direccion" placeholder="Barrio, Calle principal, #casa, calle secundaria"></textarea>
												</div>	
											</div>  
											<div class="col-md-2">
												<div class="mb-3">
													<label for="alumno_hermanos">Tiene hermanos?</label>
													<!-- radio -->
													<div class="d-flex flex-wrap gap-4">
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="alumno_hermanosSi" name="alumno_hermanos" value="S" required="required">
													        <label class="form-check-label" for="alumno_hermanosSi">Si</label>
													    </div>
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="alumno_hermanosNo" name="alumno_hermanos" value="N">
													        <label class="form-check-label" for="alumno_hermanosNo">No</label>
													    </div>
													</div>
												</div>
											</div>	 
											<div class="col-md-3">
												<div class="mb-3">
													<label for="alumno_genero">Género</label>
													<div class="d-flex flex-wrap gap-4">
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="alumno_generoM" name="alumno_genero" value="M" required="required">
													        <label class="form-check-label" for="alumno_generoM">Masculino</label>
													    </div>
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="alumno_generoF" name="alumno_genero" value="F">
													        <label class="form-check-label" for="alumno_generoF">Femenino</label>
													    </div>
													</div> 
												</div>
											</div>       
										</div>  <!--./row line 874--> 
										<!-- Fin segunda sección foto-->			
								</div>

									<!-- Tab de información médica del alumno -->
									<div class="tab-pane" id="informacionm">
										<div class="row">
											<div class="col-md-3">
												<div class="mb-3">
													<label for="infomedic_tiposangre">Tipo de sangre</label>
													<input type="text" class="form-control" id="infomedic_tiposangre" name="infomedic_tiposangre" placeholder="Tipo de sangre">                          
												</div>
											</div> 
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Peso">Peso (Kg)</label>
													<input type="text" class="form-control" id="infomedic_peso" name="infomedic_peso" placeholder="Peso en kilogramos">                          
												</div>
											</div>   
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Talla">Talla (cm)</label>
													<input type="text" class="form-control" id="infomedic_talla" name="infomedic_talla" placeholder="Talla en centímetros">                          
												</div>
											</div>
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Enfermedad">Enfermedad diagnosticada</label>
													<input type="text" class="form-control" id="infomedic_enfermedad" name="infomedic_enfermedad" placeholder="Breve descripción de enfermedad">                          
												</div>
											</div>
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Medicamentos">Medicamentos</label>
													<input type="text" class="form-control" id="infomedic_medicamentos" name="infomedic_medicamentos" placeholder="Breve descripción de dósis de medicamentos">                          
												</div>
											</div> 
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Alergia1">Alergia a medicamentos</label>
													<input type="text" class="form-control" id="infomedic_alergia1" name="infomedic_alergia1" placeholder="Alergia a medicamentos">                          
												</div>
											</div> 
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Alergia2">Alergia a objetos</label>
													<input type="text" class="form-control" id="infomedic_alergia2" name="infomedic_alergia2" placeholder="Alergia a objetos">                          
												</div>
											</div>  
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Cirugias">Cirugías</label>
													<input type="text" class="form-control" id="infomedic_cirugias" name="infomedic_cirugias" placeholder="Breve descripción de cirugías">                          
												</div>
											</div>  
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Observacion">Observación</label>
													<input type="text" class="form-control" id="infomedic_observacion" name="infomedic_observacion" placeholder="Observacion">                          
												</div>
											</div>  
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Covid">Carnet vacunación Covid</label>
													<div class="d-flex flex-wrap gap-4">
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="infomedic_covidSi" name="infomedic_covid" value="S">
													        <label class="form-check-label" for="infomedic_covidSi">Si</label>
													    </div>
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="infomedic_covidNo" name="infomedic_covid" value="N">
													        <label class="form-check-label" for="infomedic_covidNo">No</label>
													    </div>
													</div>
												</div>
											</div>
											<div class="col-md-4">
												<div class="mb-3">
													<label for="Vacunas">Carnet vacunación habitual</label>
													<div class="d-flex flex-wrap gap-4">
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="infomedic_vacunasSi" name="infomedic_vacunas" value="S">
													        <label class="form-check-label" for="infomedic_vacunasSi">Si</label>
													    </div>
													    <div class="form-check">
													        <input class="form-check-input" type="radio" id="infomedic_vacunasNo" name="infomedic_vacunas" value="N">
													        <label class="form-check-label" for="infomedic_vacunasNo">No</label>
													    </div>
													</div>                         
												</div>
											</div>  
										</div>
									</div>

									<!-- Tab información contacto de emergencia -->
									<div class="tab-pane" id="contactoem">
										<div class="row">
											<div class="col-md-3">
												<div class="mb-3">
													<label for="CEmergencia">Celular</label>
													<input type="text" class="form-control" id="cemer_celular" name="cemer_celular" placeholder="+593">                          
												</div>
											</div>
											<div class="col-md-3">
												<div class="mb-3">
													<label for="Nomcontactoemer">Nombre contacto</label>
													<input type="text" class="form-control" id="cemer_nombre" name="cemer_nombre" placeholder="Nombre contacto">                          
												</div>
											</div>
											<div class="col-md-4">
												<div class="mb-3">
													<label for="cemer_parentesco">Parentesco</label>
													<select class="form-select" style="width: 100%;" id="cemer_parentesco" name="cemer_parentesco">
														<?php echo $insAlumno->listarCatalogoParentesco(); ?>
													</select>
												</div> 
											</div>
										</div>		
									</div>

									<!-- Tab cedula del alumno -->
									<div class="tab-pane" id="cedula">
										<div class="row">
											<div class="col-md-4">
												<div class="mb-3">
													<label for="alumno_cedulaA">Anverso</label>		
													<div class="input-group">											
														<div class="fileinput fileinput-new" data-provides="fileinput">
															<div class="fileinput-new thumbnail" style="width: 100%; max-width: 330px; height: 210px;" data-trigger="fileinput">
																<img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/alumno.jpg'); ?>" alt=""></div>
															<div class="fileinput-preview fileinput-exists thumbnail" style="width: 100%; max-width: 330px; height: 210px"></div>
															<div>
																<span class="bton bton-white bton-file">
																	<span class="fileinput-new">Seleccionar Foto</span>
																	<span class="fileinput-exists">Cambiar</span>
																	<input type="file" name="alumno_cedulaA" id="alumno_cedulaA" accept="image/*">
																</span>
																<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
															</div>
														</div>
													</div>		
												</div>
												<!-- /.mb-3 -->	
											</div>
											<div class="col-md-2">
												<div class="mb-3">
													<label for="alumno_cedulaR">Reverso</label>		
													<div class="input-group">											
														<div class="fileinput fileinput-new" data-provides="fileinput">
															<div class="fileinput-new thumbnail" style="width: 100%; max-width: 330px; height: 210px;" data-trigger="fileinput">
																<img src="<?php echo ds_recurso('ds_futbol/app/views/dist/img/alumno.jpg'); ?>" alt=""></div>
															<div class="fileinput-preview fileinput-exists thumbnail" style="width: 100%; max-width: 330px; height: 210px"></div>
															<div>
																<span class="bton bton-white bton-file">
																	<span class="fileinput-new">Seleccionar Foto</span>
																	<span class="fileinput-exists">Cambiar</span>
																	<input type="file" name="alumno_cedulaR" id="alumno_cedulaR" accept="image/*">
																</span>
																<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
															</div>
														</div>
													</div>		
												</div>
												<!-- /.mb-3 -->	
											</div>
										</div>
									</div>

									<!-- Tab horario del alumno -->
									<div class="tab-pane" id="horario">
										<div class="container-fluid">													
											<!-- Table row -->
											<div class="row">
												<div class="col-md-4">
													<div class="mb-3">
														<label for="horarioid">Horarios</label>
														<select id="horarioid" class="form-control js-buscador" name="horarioid">
															<option value="">Seleccione un horario</option>																					
															<?php echo $insAlumno->listarhorarios(); ?>
														</select>
													</div>          
												</div>

												<div class="col-md-12 table-responsive">
													<table class="table table-striped table-bordered table-sm">											
														<thead>																
															<tr>		
																<th></th>												
																<th>LUNES</th>	
																<th>MARTES</th>
																<th>MIERCOLES</th>
																<th>JUEVES</th>
																<th>VIERNES</th>																																		
															</tr>
														</thead>	
														<tbody id="tabla_horario">				
														</tbody>
													</table>
												</div>
												<!-- /.col -->
											</div>
											
										</div><!-- /.container-fluid -->
									</div>
									<!-- /.tab-pane -->		
									
									<?php echo ds_acciones_form('', ['limpiar' => true]); ?>	
								</form>	

								<!-- /.tab-pane -->
							</div>
							<!-- /.tab-content -->
						</div><!-- /.card-body -->
					</div>
					<!-- /.card -->
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
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/select2/js/select2.full.min.js"></script>
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
			$('.js-buscador').select2({ width: '100%' })
			$('[data-mask]').inputmask()
		})
	</script>

	<!-- horarioid-->
	<script>
		$(document).ready(function() {
			$('#horarioid').change(function() {
				var horario_id = $(this).val();

				if (horario_id) {
					$.ajax({
						type: 'POST',
						url: '<?php echo APP_URL; ?>app/ajax/alumnoAjax.php',
						data: {
							modulo_alumno: 'cargarHorario',
							horarioid: horario_id							
						},
						success: function(response) {
							$('#tabla_horario').html(response);
						}
					});
				} else {
					$('#horarioid').html('<option value="">Seleccione un horario</option>');
				}
			});
		});
	</script>	

	<script type="text/javascript">
		function cerrarPestana() {
			window.close();
		}
    </script>	

	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/js/wizard.js"></script>
  </body>
</html>