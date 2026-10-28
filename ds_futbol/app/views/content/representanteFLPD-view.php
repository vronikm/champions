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
	/* Las secciones 2 a 6 y el pie, editables en Core › Textos de autorizaciones. */
	$form = consent_formulario(ds_nombre_organizacion() ?: DS_HUB_NAME, (string)($sede_nombre ?? ''), (string)($sede_email ?? ds_email_organizacion()));
	$cabeceraExtra = '<style>.ds-flpd { font-size: .95rem; } .ds-flpd ul { padding-left: 1.4rem; } .ds-flpd__texto p:has(> strong:only-child) { font-weight: 600; margin: 1.1rem 0 .4rem; } .ds-flpd__pie { border-top: 1px solid var(--bs-border-color); border-bottom: 1px solid var(--bs-border-color); padding: .75rem 0; margin: 1rem 0; }</style>';
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
						<div class="col-12 col-xl-10 offset-xl-1">
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
								<div class="row mx-0 mx-md-4">	
									<strong class="profile-username text-center"><?php echo htmlspecialchars($form['titulo']); ?></strong><br><br>																	
								</div>
								<!-- /.row -->
								<div class="row">
									<div class="col-12 ds-flpd">
										<?php /* Sección 1: la pone el sistema. De la 2 en adelante y las
										         autorizaciones del pie: el texto vigente de Core › Textos de
										         autorizaciones (FORMULARIO), el mismo que el PDF. */ ?>
										<p class="ds-flpd__seccion"><strong>1. Datos del Titular y Representante del alumno</strong></p>
										<ul>
											<li>Nombre del representante: <?php echo htmlspecialchars($datos['REPRESENTANTE']); ?></li>
											<li>Número de identificación: <?php echo htmlspecialchars($datos['repre_identificacion']); ?></li>
										</ul>
										<div class="ds-flpd__texto"><?php echo $form['texto']; ?></div>
										<div class="ds-flpd__pie">
											<?php foreach ($form['pie'] as $linea): ?>
												<p class="mb-1"><?php echo htmlspecialchars($linea); ?></p>
											<?php endforeach; ?>
										</div>
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