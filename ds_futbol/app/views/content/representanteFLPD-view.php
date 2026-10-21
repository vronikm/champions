<?php	

	use app\controllers\representanteController;

	include 'app/lib/barcode.php';
	
	$generator = new barcode_generator();
	$symbology="qr";
	$optionsQR=array('sx'=>4,'sy'=>4,'p'=>-10);	
	$fechahoy = date('Y-m-d');	

	$insFormulario = new representanteController();	

	$repreid = ds_id_de_url($url, 1, APP_URL . 'representanteList/');

	$datos=$insFormulario->datosRepresentante($repreid);
	
	if($datos->rowCount()==1){
		$datos=$datos->fetch();
		$repre_sedeid = $datos['SEDE'];	
	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "representanteList/");
		exit();
	}

	$sede=$insFormulario->informacionSede($repre_sedeid);
	if($sede->rowCount()==1){
		$sede=$sede->fetch(); 
		$sede_nombre  = $sede['sede_nombre'];
		$sede_email   = $sede['sede_email'];
	}
	
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Formulario PDP';
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
							<h1>PROTECCIÓN DE DATOS PERSONALES</h1>
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
											<img src="<?php echo ds_logo_url((int)$sede["sede_id"]) ?>" style="width: 120px; height: 120px;"/>									
										</address>
									</div>
									<!-- /.col -->
									<div class="col-sm-5 invoice-col">									
										<address class="text-center">	
											<strong class="profile-username">ESCUELA <br> <?php echo mb_strtoupper(APP_NAME, 'UTF-8'); ?> </strong><br><br>
												<p class="lead">Fecha: <?php echo  $fechahoy; ?></p>								
										</address>
									</div>
									<!-- /.col -->								
								</div>
								<!-- Table row -->
								<div class="row" style="margin-right: 25px; margin-left: 25px;">	
									<strong class="profile-username text-center">FORMULARIO DE CONSENTIMIENTO INFORMADO DE RECOLECCIÓN Y TRATAMIENTO DE DATOS PERSONALES</strong><br><br>																	
								</div>
								<!-- /.row -->
								<div class="row">
									<div class="col-12 table-responsive">
										<table class="table table-striped table-sm">											
											<tbody>
												<tr>													
													<th>1. Datos del Titular y Representante del alumno</th>																					
												</tr>	
												<tr>
													<td colspan="3">- Nombre del representante: <?php echo $datos['REPRESENTANTE']; ?><br>
																	- Número de identificación: <?php echo $datos['repre_identificacion']; ?>
													</td>
												</tr>
												<tr>													
													<th>2. Finalidad del tratamiento de datos</th>																					
												</tr>	
												<tr>													
													<td colspan="3">En Escuela <?php echo mb_strtoupper(APP_NAME, 'UTF-8'); ?>, recolectamos y tratamos los datos personales de nuestros alumnos y sus representantes legales con las siguientes finalidades:<br>
																	•	Gestión administrativa y operativa de la inscripción, pagos mensuales y participación del alumno en las actividades de la Escuela <?php echo mb_strtoupper(APP_NAME, 'UTF-8'); ?><br>
																	•	Comunicación de eventos, horarios de entrenamiento, fechas de torneos, actividades y todo tipo de información relevante.<br>
																	•	Atención de situaciones de emergencia, incluyendo acceso a información de contacto y datos médicos básicos proporcionados por el representante legal.<br>
																	•	Registro de contenido audiovisual (fotos y videos) para la promoción de actividades en redes sociales y material institucional, siempre con el consentimiento del representante del alumno.
													</td>												
												</tr>
												<tr>													
													<th>3. Datos a ser recolectados</th>																					
												</tr>	
												<tr>													
													<td colspan="3">Con el fin de llevar a cabo las finalidades descritas, recolectaremos los siguientes datos personales:<br>
																	•	Datos del alumno: Tipo de identificación, número de identificación, apellido paterno, apellido materno, primer nombre, segundo nombre, nacionalidad, fecha de nacimiento, dirección, tiene hermanos, sexo, fotografías del documento de identificación (anverso y reverso).<br>
																	•	Contacto de emergencia del alumno: Celular, nombre, parentesco.<br>
																	•	Datos del representante: Tipo de identificación, número de identificación, apellido paterno, apellido materno, primer nombre, segundo nombre, parentesco, sexo, dirección, correo, celular y si requiere factura.<br>
																	•	Información médica: Tipo de sangre, peso, talla, enfermedad diagnosticada, medicamentos, alergia a medicamentos, alergia a objetos, cirugías, dispone de carnet de vacunación COVID y vacunación habitual.<br>
																	•	Contenido audiovisual (fotos y videos): Se podrán tomar y usar imágenes de los alumnos en actividades propias de la escuela con fines promocionales o informativos
													</td>												
												</tr>
												<tr>													
													<th>4. Derechos del Titular de los Datos</th>																					
												</tr>	
												<tr>													
													<td colspan="3">El titular de los datos o su representante tiene derecho a acceder, rectificar, cancelar u oponerse al tratamiento de los datos personales en cualquier momento, de conformidad con la Ley de Protección de Datos Personales en Ecuador. Para ejercer estos derechos, puede contactarse a través del correo: <?php echo $sede_email ?></td>												
												</tr>
												<tr>													
													<th>5. Seguridad de los Datos</th>																					
												</tr>	
												<tr>													
													<td colspan="3">La escuela adopta medidas de seguridad razonables y adecuadas para proteger los datos personales contra el acceso no autorizado, pérdida, destrucción, alteración o uso indebido.</td>												
												</tr>
												<tr>													
													<th>6. Consentimiento</th>																					
												</tr>	
												<tr>													
													<td colspan="3">Declaro que he leído y comprendido los términos de este consentimiento y autorizo a la Escuela <?php echo mb_strtoupper(APP_NAME, 'UTF-8'); ?> a recolectar y tratar los datos personales mencionados, en los términos señalados en este documento.</td>												
												</tr>
												<tr>
													<td></td>
												</tr>
												<tr>													
													<td colspan="3">Sí, autorizo el uso de imágenes de mi(s) representado(s) (fotos y videos) en las redes sociales y material promocional de la Escuela <?php echo mb_strtoupper(APP_NAME, 'UTF-8'); ?> y en todas sus sedes.<br>
																	Sí, consiento el tratamiento de los datos personales de mi(s) representado(s) con las finalidades descritas.<br>
													</td>												
												</tr>
												<tr>
													<td></td>
												</tr>
											</tbody>
										</table>
									</div>
									<!-- accepted payments column -->
									<div class="col-4">										
									</div>
								</div>
								<!-- /.row -->								

								<!-- this row will not appear when printing -->
								<div class="row no-print">
									<div class="col-12">
										<?php /* Flex en vez de float, igual que el resto de
												 pies. El botón de volver sigue viniendo del
												 include compartido; sólo cambia el contenedor. */ ?>
										<div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
											<?php include "./app/views/inc/btn_back.php";?>

											<a href="<?php echo APP_URL.'formularioLPPDF/'.$repreid.'/'; ?>"
											   class="btn btn-dark btn-sm ms-auto" target="_blank">
												<i class="fas fa-print"></i> Ver formulario
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

	<!--script src="app/views/dist/js/main.js" ></script-->
	
	<!-- fileinput -->
    
	<script>
        // Esta función se llama cuando el botón es clickeado
        function printPage() {
            window.addEventListener("load", window.print());
        }
    </script>
	

  </body>
</html>