<?php
	use app\controllers\asistenciaController;
	$insAsignar = new asistenciaController();	

	$horario_id = (($url[1] ?? "") != "") ? $url[1] : 0;
	$sede_id = (($url[2] ?? "") != "") ? $url[2] : 0;
	$vista = (($url[3] ?? "") != "") ? $url[3]."/" : "";

	$url = ($vista != "") ? 'asistenciaHorarioLista/'.$horario_id : 'asistenciaListHorario';

	if($sede_id != 0){
		$sede=$insAsignar->BuscarSede($sede_id);		
		if($sede->rowCount()==1){
			$sede				=	$sede->fetch();				
			$sede_nombre		= 	$sede['sede_nombre'];	
		}
	}else{
		$sede_nombre 	= '';
	}
	
	$modulo_asistencia	= '';

	if($horario_id != 0){
		$nombreHorario=$insAsignar->buscarHorario($horario_id);		
		if($nombreHorario->rowCount()==1){
			$nombreHorario		=	$nombreHorario->fetch();				
			$horario_nombre		= 	$nombreHorario['HORARIO'];					
		}
	}else{
		$horario_nombre 	= '';
	}	
?>


<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Asignación horario';
	$extras      = array (0 => 'datatables',1 => 'swal',);
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
								<h5 class="m-0"><?php echo "$sede_nombre | $horario_nombre"; ?></h5>

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
					<div class="container-fluid">						
							<div class="card card-default">
								<div class="card-header" style='min-height: 40px;'>
									<h3 class="card-title">Alumnos sin horario</h3>
									<div class="card-tools">
										<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
									</div>
								</div>               
								
						
								<div class="card-body">
									<div class="table-responsive">
									<table id="example1" class="table table-bordered table-striped table-sm" style="font-size: 14px;">
										<thead>
											<tr>
												<th>Sede</th>	
												<th>Identificación</th>
												<th>Nombres y Apellidos</th>
												<th>Año</th>
												<th></th>	
											</tr>
										</thead>
										<tbody>
											<?php 												
												echo $insAsignar->listarAlumnos($horario_id, $sede_id); 												
											?>								
										</tbody>
									</table>
									</div>	
								</div>
							<div class="card-footer">		
								<!--a href="<?php echo APP_URL.'asistenciaListHorario/'; ?>" class="btn btn-dark btn-sm">Regresar</a>	
								<button class="btn btn-dark btn-back btn-sm" onclick="cerrarPestana()">Regresar</button-->	
								<a href="#" class="btn btn-dark btn-sm" onclick="document.getElementById('form-regresar').submit(); return false;">Regresar</a>								
								<form id="form-regresar" action="<?php echo APP_URL.$url."/" ?>" method="POST" autocomplete="off" enctype="multipart/form-data">	
									<input type="hidden" name="horario_sedeid" value="<?php echo $sede_id; ?>">
								</form>												
							</div>	
						</div>

					</div>				
				</section>				
			</div><!-- /.container-fluid -->
		
			<?php require_once "app/views/inc/footer.php"; ?>

			<!-- Control Sidebar -->
			<aside class="control-sidebar control-sidebar-dark">
			<!-- Control sidebar content goes here -->
			</aside>
			<!-- /.control-sidebar -->

		</div>
			
		<!-- fin -->

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

	<script type="text/javascript">
		function cerrarPestana() {
			window.close();
		}
    </script>
		<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
		<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>

	</body>
</html>








