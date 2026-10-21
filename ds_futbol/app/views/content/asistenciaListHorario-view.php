<?php
	use app\controllers\asistenciaController;
	$insHorario = new asistenciaController();	

	if(isset($_POST['horario_sedeid'])){
		$horario_sedeid = $insHorario->limpiarCadena($_POST['horario_sedeid']);
	} ELSE{
		$horario_sedeid = 0;
	}

	if(isset($_POST['horario_nombre'])){
		$horario_nombre = $insHorario->limpiarCadena($_POST['horario_nombre']);
	} ELSE{
		$horario_nombre = "";
	}

	if(isset($_POST['horario_detalle'])){
		$horario_detalle = $insHorario->limpiarCadena($_POST['horario_detalle']);
	} ELSE{
		$horario_detalle = "";
	}		
?>


<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Horarios';
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
					<h4 class="m-0">Horarios</h4>
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

		<!-- Section listado de alumnos -->
		<section class="app-content">			
			<div class="container-fluid">
				<div class="card card-default" style='height: 140px;'>
					<div class="card-header" style='min-height: 40px;'>
						<h3 class="card-title">Búsqueda de horarios</h3>
</div>  

					<form action="<?php echo APP_URL."asistenciaListHorario/" ?>" method="POST" autocomplete="off" enctype="multipart/form-data" >
					<!-- card-body -->                
						<div class="card-body">
							<div class="row" style='font-size: 14px; height: 60px;'>
								<div class="col-sm-3">
									<div class="mb-3">
										<label for="horario_nombre">Horario nombre</label>                        
										<input type="text" class="form-control" style='font-size: 13px; height: 31px;' id="horario_nombre" name="horario_nombre" placeholder="Nombre" value="<?php echo $horario_nombre; ?>">
									</div>        
								</div>
								<div class="col-sm-3">
									<div class="mb-3">
										<label for="horario_detalle">Horario detalle</label>
										<input type="text" class="form-control" style='font-size: 13px; height: 31px;' id="horario_detalle" name="horario_detalle" placeholder="Detalle" value="<?php echo $horario_detalle; ?>">
									</div>         
								</div>											
								<div class="col-md-3">
									<div class="mb-3">
										<label for="horario_sedeid">Sede</label>
										<select class="form-select" style='font-size: 13px; height: 31px;' id="horario_sedeid" name="horario_sedeid">
											<?php
												if($horario_sedeid == 0){	
													echo "<option value='0' selected='selected'>Seleccionar sede</option>";
												}else{
													echo "<option value='0'>Seleccionar sede</option>";	
												}
											?>																		
											<?php echo $insHorario->listarOptionSedebusqueda($horario_sedeid); ?>
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
					</form>
				</div>
            </div> 
			<div class="container-fluid">
			<!-- Small boxes (Stat box) -->
				<div class="card card-default">
					<div class="card-header" style='min-height: 40px;'>
						<h3 class="card-title">Resultado de la búsqueda</h3>
						<div class="card-tools">
							<?php
							/* El alta va junto a su tabla, no en el formulario de filtros. */
							if ($horario_sedeid != 0) {
								echo '<form class="d-inline-flex me-2" action="'.APP_URL.'asistenciaHorario/" method="POST" autocomplete="off" target="_blank">
										<input type="hidden" name="horario_sedeid" value="'.$horario_sedeid.'">
										<button type="submit" class="btn btn-primary btn-sm">Nuevo</button>
									</form>';
							}
							?>
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<div class="table-responsive">
						<table id="example1" class="table table-bordered table-striped table-sm" style="font-size: 14px;">
							<thead>
								<tr>
									<th>Horario</th>
									<th>Detalle</th>
									<th>Estado</th>																
									<th>Alumnos</th>
									<th>Opciones horario</th>
									<th>Operaciones</th>
								</tr>
							</thead>
							<tbody>
								<?php 
									if($horario_nombre !='' || $horario_detalle !='' || $horario_sedeid != 0){
										echo $insHorario->listarHorarios($horario_nombre, $horario_detalle, $horario_sedeid); 
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

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
    
  </body>
</html>