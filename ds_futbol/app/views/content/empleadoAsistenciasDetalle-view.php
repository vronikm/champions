<?php	
	use app\controllers\reporteController;

	include 'app/lib/barcode.php';
	
	$generator = new barcode_generator();
	$symbology="qr";
	$optionsQR=array('sx'=>4,'sy'=>4,'p'=>-10);		

	$insDetalle   = new reporteController();

	/* Esta pantalla es el detalle de un empleado y sólo se llega a ella
	   enviando el formulario del reporte. Al abrirla por URL faltaban las
	   tres claves de POST, PHP avisaba por cada una y el id vacío acababa
	   en la consulta. Sin empleado no hay detalle: se vuelve al reporte. */
	$empleado_id  = trim((string)($_POST['empleado_id'] ?? ''));

	if ($empleado_id === '' || !ctype_digit($empleado_id)) {
		header("Location: " . APP_URL . "empleadoAsistencias/");
		exit();
	}

	$fecha_inicio = (string)($_POST['fecha_inicio'] ?? date('Y-m-01'));
	$fecha_fin    = (string)($_POST['fecha_fin']    ?? date('Y-m-d'));

	$datosAsistencia=$insDetalle->seleccionarDatos("Unico","sujeto_empleado","empleado_id",$empleado_id);
	if($datosAsistencia->rowCount()==1){
		$datosAsistencia		= $datosAsistencia->fetch();
		$empleado_nombre 		= $datosAsistencia['empleado_nombre'];
	}else{
		$empleado_nombre 	= "";
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = '';
	$extras      = array (0 => 'dropzone',);
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
							<h5 class="m-0">Detalle de asistencias empleado <?php echo $empleado_nombre; ?></h5>
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
								<div class="col-sm-11 invoice-col">									
									<address class="text-center"><br>
										<strong class="profile-username"><?php echo ds_nombre_organizacion_may(0); ?></strong><br><br>											
										<div class="row">
											<div class="row">
												<div class="col-4"></div>														
												<div class="col-12">
													Empleado: <?php echo $empleado_nombre;?>
												</div>
												<div class="col-11">
													Fecha de generación: <?php echo date('d-m-Y');?>
												</div>
											</div>
											<!-- /.col -->
										</div>
									</address>
								</div>							
								
								<div class="card-body">									
									<div class="table-responsive">
									<table id="example1" class="table table-bordered table-striped table-sm" style="font-size: 13px;">
										<thead>
											<tr>
												<th>Nombre empleado</th>
												<th>Fecha</th>
												<th>Hora</th>
												<th>Tipo</th>
												<th>Ubicación</th>														
											</tr>
										</thead>
										<tbody>
											<?php 
												echo $insDetalle->listarMarcacionesEmpleado($empleado_id, $fecha_inicio, $fecha_fin); 
											?>							
										</tbody>	
									</table>
									</div>
								</div>	

								<div class="row no-print">
									<div class="col-12">
										<button class="btn btn-dark btn-back btn-sm" onclick="cerrarPagina()">Regresar</button>
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
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>

	<script>
        function cerrarPagina() {
            window.close();
        }
    </script>

     <!-- Page specific script -->
	 <script>
		document.addEventListener('DOMContentLoaded', function () {
			new DataTable("#example1", {
			"responsive": true, 
			"lengthChange": false, 
			"autoWidth": false,
			"paging": false, // Deshabilitar la paginación
			"searching": false, // Deshabilitar la búsqueda
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
				},
			"buttons": {
					"copy": "Copiar",
					"print": "Imprimir",
					"text": 'Imprimir Tabla',
					"title": 'Datos de Alumnos',
					"messageTop": 'Generado por el sistema de gestión de alumnos.',
					"messageBottom": 'Página generada automáticamente.',
					customize: function(win) {
						$(win.document.body)
							.css('font-family', 'Arial')
							.css('background-color', '#f3f3f3');

						// Cambiar el estilo de la tabla impresa
						$(win.document.body).find('table')
							.addClass('display')  // Añadir una clase CSS a la tabla impresa
							.css('font-size', '12pt')
							.css('border', '1px solid black');

						// Agregar logotipo al principio
						$(win.document.body).prepend(
							'<img src="https://example.com/logo.png" style="position:absolute; top:0; left:0; width:100px;" />'
						);

						// Modificar título y agregar estilos CSS adicionales
						$(win.document.body).find('h1')
							.css('text-align', 'center')
							.css('color', '#4CAF50');
					}
				}
			},
			layout: { topStart: 'buttons' },
			"buttons": ["copy", "csv", "excel", "pdf", "print"]
			});
			new DataTable('#example2', {
				"paging": true,
				"lengthChange": false,
				"searching": false,
				"ordering": true,
				"info": true,
				"autoWidth": false,
				"responsive": true,
			});
		});
	</script>
  </body>
</html>