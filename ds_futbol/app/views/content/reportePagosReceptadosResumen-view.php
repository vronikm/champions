<?php
	use app\controllers\reporteController;
	$insResumen = new reporteController();
	$sede_id 	  = (($url[1] ?? "") != "") ? $url[1] : 0;

	if(isset($_POST['alumno_sedeid'])){
		$alumno_sedeid = $insResumen->limpiarCadena($_POST['alumno_sedeid']);
	} ELSE{
		$alumno_sedeid = 0;
	}

	if(isset($_POST['pago_fecha_inicio'])){
		$fecha_inicio = $insResumen->limpiarCadena($_POST['pago_fecha_inicio']);
	} ELSE{
		$fecha_inicio = "";
	}

	if(isset($_POST['pago_fecha_fin'])){
		$fecha_fin = $insResumen->limpiarCadena($_POST['pago_fecha_fin']);
	} ELSE{
		$fecha_fin = "";
	}	
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Reporte de pagos';
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
				<h1 class="m-0">Resumen de pagos receptados </h1>
				</div><!-- /.col -->
				<div class="col-sm-6">
				<ol class="breadcrumb float-sm-end">
					<li class="breadcrumb-item"><a href="#">Nuevo</a></li>
					<li class="breadcrumb-item active">Dashboard</li>
				</ol>
				</div><!-- /.col -->
			</div><!-- /.row -->
			</div><!-- /.container-fluid -->
		</div>
		<!-- /.content-header -->

		<!-- Section listado de alumnos -->
		<section class="app-content">
			<form action="<?php echo APP_URL."reportePagosReceptadosResumen/" ?>" method="POST" autocomplete="off" enctype="multipart/form-data" >			
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
								<div class="mb-3 campo">
									<label for="pago_fecha">Fecha inicio</label>
									<div class="input-group">
																	<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
						
										<input type="date" class="form-control" id="pago_fecha_inicio" name="pago_fecha_inicio" value="<?php echo htmlspecialchars((string)$fecha_inicio, ENT_QUOTES, 'UTF-8'); ?>" required>										
									</div>
									<!-- /.input group -->
								</div>
							</div>
							<div class="col-md-3">
								<div class="mb-3 campo">
									<label for="pago_fecha">Fecha fin</label>
									<div class="input-group">
																	<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
						
										<input type="date" class="form-control" id="pago_fecha_fin" name="pago_fecha_fin" value="<?php echo htmlspecialchars((string)$fecha_fin, ENT_QUOTES, 'UTF-8'); ?>" required>										
									</div>
									<!-- /.input group -->
								</div>
							</div>	
							<div class="col-md-3">
								<div class="mb-3">
									<label for="alumno_sedeid">Sede</label>
									<select class="form-select" id="alumno_sedeid" name="alumno_sedeid" required>																									
										<?php echo $insResumen->listarSedebusqueda($alumno_sedeid); ?>
									</select>	
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
						<h3 class="card-title">Resumen de pagos por forma de pago</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="table-responsive">
						<table id="example1" class="table table-bordered table-striped table-sm">
							<thead>
								<tr>
									<th>Sede</th>					
									<th>F. registro pago</th>
									<th>Forma de Pago</th>
									<th>Pagos</th>		
									<th>V. Pagado</th>
								</tr>
							</thead>
							<tbody>
								<?php
									if($alumno_sedeid!=0){
										echo $insResumen->resumenPagosForma($fecha_inicio, $fecha_fin,$alumno_sedeid);
									}else{
										echo $insResumen->resumenPagosFormaConsolidado($fecha_inicio, $fecha_fin);
									}	
								?>								
							</tbody>
						</table>
						</div>	
					</div>
					
					<div class="card-body">
					<div class="tab-custom-content">
						<h3 class="card-title">Detalle de pagos por forma de pago</h3><br><br>
					</div>
						<div class="table-responsive">
						<table id="example2" class="table table-bordered table-striped table-sm">
							<thead>
								<tr>
									<th>Sede</th>					
									<th>F. registro pago</th>
									<th>Rubro</th>
									<th>Forma de Pago</th>
									<th>Pagos</th>		
									<th>V. Pagado</th>
								</tr>
							</thead>
							<tbody>
								<?php
									if($alumno_sedeid!=0){
										echo $insResumen->resumenPagos($fecha_inicio, $fecha_fin, $alumno_sedeid); 
									}else{
										echo $insResumen->resumenPagosConsolidado($fecha_inicio, $fecha_fin);
									}	
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
    <!-- Page specific script -->
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		new DataTable("#example1, #example2", {
			"paging": true,
			"lengthChange": false,
			"searching": false,
			"ordering": false,
			"info": true,
			"autoWidth": false,
			"responsive": true, 
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
					"colvis": "Visibilidad columnas"
				}
			},
			layout: { topStart: 'buttons' },
			"buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
			});
	});
	</script>
  </body>
</html>








