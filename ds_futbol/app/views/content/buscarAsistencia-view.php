<?php
	use app\controllers\asistenciaController;
	$insAsistencia = new asistenciaController();

	$alumno = ds_id_de_url($url, 1, APP_URL . 'reporteAsistencia/');

	$datos=$insAsistencia->BuscarAlumno($alumno);
	if($datos->rowCount()==1){
		$datos=$datos->fetch(); 
		
		if ($datos['alumno_imagen']!=""){
			$foto = media_url('alumno', $datos['alumno_imagen']);
		}else{
			$foto=ds_recurso('ds_futbol/app/views/dist/img/alumno.jpg');
		}

		if($datos['pendiente']==1){
			$pendiente = 'Pendiente';
			$clase = '<a class="float-end text-danger">';
		}else{
			$pendiente = 'Al día';
			$clase = '<a class="float-end">';
		}
	
		$sede=$insAsistencia->informacionSede($datos['alumno_sedeid']);
		if($sede->rowCount()==1){
			$sede=$sede->fetch(); 
		}

		$mesesEnEspanol = [
			'January' => 'Enero',
			'February' => 'Febrero',
			'March' => 'Marzo',
			'April' => 'Abril',
			'May' => 'Mayo',
			'June' => 'Junio',
			'July' => 'Julio',
			'August' => 'Agosto',
			'September' => 'Septiembre',
			'October' => 'Octubre',
			'November' => 'Noviembre',
			'December' => 'Diciembre'
		];

		$fechahoy = date('Y-m-d');
		// Crear un objeto DateTime
		$dateTime = new DateTime($fechahoy);
		// Obtener el nombre completo del mes
		$nombreMes = $dateTime->format('F');
		// Obtener el año
		$nombreMesEspanol = $mesesEnEspanol[$nombreMes];

		$anio = $dateTime->format('Y');
		$nombreMes = $nombreMesEspanol." / ".$anio;

	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "reporteAsistencia/");
		exit();
	}

	if(isset($_POST['alumno_anioasist'])){
		$alumno_anioasist = $insAsistencia->limpiarCadena($_POST['alumno_anioasist']);
	
	} ELSE{
		$alumno_anioasist = "2024";		
	}

	if(isset($_POST['alumno_mesasist'])){
		$alumno_mesasist = $insAsistencia->limpiarCadena($_POST['alumno_mesasist']);	
	} ELSE{
		$alumno_mesasist = "Enero";		
	}

	if(isset($_POST['alumno_sedeid'])){
		$alumno_sedeid = $insAsistencia->limpiarCadena($_POST['alumno_sedeid']);		
	} ELSE{
		$alumno_sedeid = "";		
	}

	if(isset($_POST['alumno_identificacion'])){
		$alumno_identificacion = $insAsistencia->limpiarCadena($_POST['alumno_identificacion']);
	} ELSE{
		$alumno_identificacion = "";
	}

	if(isset($_POST['alumno_nombre1'])){
		$alumno_primernombre = $insAsistencia->limpiarCadena($_POST['alumno_nombre1']);
	} ELSE{
		$alumno_primernombre = "";
	}

	if(isset($_POST['alumno_apellido1'])){
		$alumno_apellidopaterno = $insAsistencia->limpiarCadena($_POST['alumno_apellido1']);
	} ELSE{
		$alumno_apellidopaterno = "";
	}
	
	if(isset($_POST['alumno_anio'])){
		$alumno_anio = $insAsistencia->limpiarCadena($_POST['alumno_anio']);
	
	} ELSE{
		$alumno_anio = "";		
	}

	if($alumno_anio == ""){
		$categoria = 0;
	}else{
		$categoria = $alumno_anio;
	}
?>


<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo APP_NAME; ?>| Ver asistencia</title>
	<?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
	<link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
	<!-- Google Font: Source Sans Pro -->
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
	<!-- Font Awesome -->
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
	  <!-- fullCalendar -->
	  <link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/main.css">
	<!-- DataTables -->
	<!-- Theme style -->
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/css/overlayscrollbars.min.css">
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/css/adminlte.min.css">
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/core.css'); ?>">
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_futbol/app/views/dist/css/futbol.css'); ?>">
	<link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/dist/css/sweetalert2.min.css">
    
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
  </head>
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
				<h1 class="m-0">Registro de asistencia</h1>
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

		<!-- Section listado de alumnos -->
		<section class="app-content">
			<form action="<?php echo APP_URL."buscarAsistencia/" ?>" method="POST" autocomplete="off" enctype="multipart/form-data" >			
			<!-- card-body -->                
			<div class="card-body">
				<div class="row">
					<div class="col-md-3">	
						<!-- Profile Image -->
						<div class="card card-primary card-outline">
							<div class="card-body box-profile">
								<div class="text-center">
									<img class="profile-user-img img-fluid rounded-circle"
										src="<?php echo $foto; ?>"
										alt="User profile picture">
								</div>

								<h3 class="profile-username text-center"><?php echo $datos['alumno_primernombre']." ".$datos['alumno_apellidopaterno'] ; ?></h3>

								<p class="text-muted text-center"><?php echo $datos['alumno_identificacion']; ?></p>

								<ul class="list-group list-group-unbordered mb-3">
									<li class="list-group-item">
										<b>Sede</b> <a class="float-end"><?php echo $datos['sede_nombre']; ?></a>
									</li>
									<li class="list-group-item">
										<b>Categoría</b> <a class="float-end"><?php echo $datos['anio']; ?></a>
									</li>
									<li class="list-group-item">
										<b>Estado alumno</b> <a class="float-end"><?php echo $datos['estado']; ?></a>
									</li>
									<li class="list-group-item">
										<b>Fecha de ingreso</b> <a class="float-end"><?php echo $datos['alumno_fechaingreso']; ?></a>
									</li>
									<li class="list-group-item">
										<b>Estado pagos</b> 												
										<?php												
											echo $clase.$pendiente.'</a>'; 												
										?>
									</li>
									<li class="list-group-item">
										<b>ID</b> <a class="float-end"><?php echo $alumno; ?></a>
									</li>
								</ul>
							</div>
							<!-- /.card-body -->
						</div>
						<!-- /.card -->
					</div>
					<!-- /.col -->
					<div class="col-md-9">
						<div class="card card-primary card-outline">
							<div class="card-body p-0">		
								<!-- THE CALENDAR -->
								<div id="calendar">
									<?php 
										if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'CalendarioEventos') {
											header('Content-Type: application/json');
											echo $insAsistencia->CalendarioEventos($alumno); 
											exit;
										}
									?>
								</div>
							</div>
							<!-- /.card-body -->
						</div>
					<!-- /.card -->
					</div>
					<!-- /.col -->
				</div>	
			</div>            
			</form>
		</section>
		<!-- /.section -->
      
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
	<!-- Bootstrap 4 -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- jQuery UI -->
	<!-- DataTables  & Plugins -->
	<!-- AdminLTE App -->
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>	
	<!-- fullCalendar 2.2.5 -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/main.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/locales/es.js"></script>

     <!-- Page specific script -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var calendarEl = document.getElementById('calendar');
			var calendar = new FullCalendar.Calendar(calendarEl, {
				initialView: 'dayGridMonth',
				locale: 'es', 
				aspectRatio: 2.14, // Cambia la relación de aspecto para hacerlo más pequeño
				events: <?php echo $insAsistencia->CalendarioEventos($alumno); ?>			
			});
			calendar.render();
		});
	</script>
  </body>
</html>








