<?php
	use app\controllers\reporteController;
	$insAsistencia = new reporteController();

	/*
	| Sin filtro, el rango arranca en la última marcación registrada.
	|
	| Cuando todavía no hay ninguna —una escuela recién puesta en marcha—
	| MAX() devuelve NULL, esa cadena vacía viajaba al SQL como fecha y
	| MySQL rechazaba la consulta con "Incorrect DATETIME value: ''". La
	| pantalla moría por no tener datos, que es justo cuando más se abre.
	*/
	$ultimaMarcacion = static function($insAsistencia) {
		$fila = $insAsistencia->fechaMarcacion()->fetch();
		$fecha = is_array($fila) ? trim((string)($fila['FECHA_MAXIMA'] ?? '')) : '';
		return $fecha !== '' ? $fecha : date('Y-m-d');
	};

	if(isset($_POST['asistencia_fecha_inicio']) && $_POST['asistencia_fecha_inicio'] !== ''){
		$fecha_inicio = $insAsistencia->limpiarCadena($_POST['asistencia_fecha_inicio']);
	} ELSE{
		$fecha_inicio = $ultimaMarcacion($insAsistencia);
	}

	if(isset($_POST['asistencia_fecha_fin']) && $_POST['asistencia_fecha_fin'] !== ''){
		$fecha_fin = $insAsistencia->limpiarCadena($_POST['asistencia_fecha_fin']);
	} ELSE{
		$fecha_fin = $ultimaMarcacion($insAsistencia);
	}

	if(isset($_POST['empleado_nombre'])){
		$empleado_nombre = $insAsistencia->limpiarCadena($_POST['empleado_nombre']);
	} ELSE{
		$empleado_nombre = "";
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Asistencia de empleados';
	$extras      = array (0 => 'datatables',);
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
				<h1 class="m-0">Reporte de asistencia de empleados</h1>
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
			<form action="<?php echo APP_URL."empleadoAsistencias/" ?>" method="POST" autocomplete="off" enctype="multipart/form-data" >			
				<div class="container-fluid">
					<div class="card card-default">
						<div class="card-header">
							<h3 class="card-title">Criterios de búsqueda</h3>
							<div class="card-tools">
								<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
							</div>
						</div>
						<!-- card-body -->                
						<div class="card-body">
							<div class="row">
								<div class="col-md-3">
									<div class="mb-3">
										<label for="empleado_nombre">Nombre empleado</label>
										<input type="text" class="form-control" id="empleado_nombre" name="empleado_nombre" placeholder="Nombre del empleado" value="<?php echo $empleado_nombre; ?>">
									</div>
								</div>
								<div class="col-md-3">
									<div class="mb-3 campo">
										<label for="asistencia_fecha_inicio">Fecha inicio</label>
										<div class="input-group">
																			<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
							
											<input type="date" class="form-control" id="asistencia_fecha_inicio" name="asistencia_fecha_inicio" value="<?php echo htmlspecialchars((string)$fecha_inicio, ENT_QUOTES, 'UTF-8'); ?>" required>										
										</div>
										<!-- /.input group -->
									</div>
								</div>
								<div class="col-md-3">
									<div class="mb-3 campo">
										<label for="asistencia_fecha_fin">Fecha fin</label>
										<div class="input-group">
																			<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
							
											<input type="date" class="form-control" id="asistencia_fecha_fin" name="asistencia_fecha_fin" value="<?php echo htmlspecialchars((string)$fecha_fin, ENT_QUOTES, 'UTF-8'); ?>" required>										
										</div>
										<!-- /.input group -->
									</div>
								</div>	
								<div class="col-md-3 d-flex align-items-end">
									<div class="mb-3 w-100">
																				<?php echo ds_boton_buscar(); ?>
									</div>
								</div>
							</div>					
						</div>
					</div>
				</div>  
			</form>

			<div class="container-fluid">
			<!-- Small boxes (Stat box) -->
				<div class="card card-default">
					<div class="card-header">
						<h3 class="card-title">Resultado de la búsqueda</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<div class="table-responsive">
						<table id="example1" class="table table-bordered table-striped table-sm">
							<thead>
								<tr>
									<th>Identificación</th>
									<th>Nombres</th>									
									<th style="width: 220px;">Reporte de asistencia</<th>
								</tr>
							</thead>
							<tbody>
								<?php 
									echo $insAsistencia->listarEmpleados($empleado_nombre, $fecha_inicio, $fecha_fin); 
								?>								
							</tbody>
						</table>
						</div>	
					</div>
				</div>
			<!-- /.row -->
			</div><!-- /.container-fluid -->

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
		"buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
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
