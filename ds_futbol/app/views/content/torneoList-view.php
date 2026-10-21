<?php
	use app\controllers\torneoController;
	$insTorneo = new torneoController();
	
	$torneoid = (($url[1] ?? "") != "") ? $url[1] : 0;	

	$foto = APP_URL.'app/views/imagenes/fotos/torneos/torneo_default.jpg';

	if($torneoid != 0){
		$datosTorneo=$insTorneo->BuscarTorneo($torneoid);		
		if($datosTorneo->rowCount()==1){
			$datosTorneo=$datosTorneo->fetch(); 
			if ($datosTorneo['torneo_foto']!=""){
				$foto = APP_URL.'app/views/imagenes/fotos/torneos/'.$datosTorneo['torneo_foto'];
			}else{
				$foto = ds_recurso('ds_futbol/app/views/dist/img/torneo_default.jpg');
			}
			$modulo_torneo = 'actualizar';			

			$torneo_nombre = $datosTorneo['torneo_nombre'];
			$torneo_ciudad = $datosTorneo['torneo_ciudad'];
			$torneo_lugar = $datosTorneo['torneo_lugar'];
			$torneo_fechainicio = $datosTorneo['torneo_fechainicio'];
			$torneo_fechafin = $datosTorneo['torneo_fechafin'];
			$torneo_organizador = $datosTorneo['torneo_organizador'];
			$torneo_descripcion = $datosTorneo['torneo_descripcion'];
			$torneo_estado = $datosTorneo['ESTADO'];
			
		}
	}else{
		$modulo_torneo = 'registrar';
		$torneo_nombre = '';
		$torneo_ciudad = '';		
		$torneo_lugar = '';
		$torneo_fechainicio = '';
		$torneo_fechafin = '';
		$torneo_organizador = '';
		$torneo_descripcion = '';
		$torneo_estado = 'A';
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Torneos';
	$extras      = array (0 => 'datatables',);
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
					<h4 class="m-0">Torneos</h4>
				</div><!-- /.col -->
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-end">
						<li class="breadcrumb-item"><a href="#">Inicio</a></li>
						<li class="breadcrumb-item active"><a href="<?php echo APP_URL."dashboard/" ?>">Dashboard</a></li>
					</ol>
				</div><!-- /.col -->
			</div><!-- /.row -->
			</div><!-- /.container-fluid -->
		</div>
		<!-- /.content-header -->

		<!-- Main content -->
		<section class="app-content">
			<div class="container-fluid">
			<!-- Small boxes (Stat box) -->
				<div class="card card-default">
					<div class="card-header" style='min-height: 40px;'>
						<h4 class="card-title">Ingreso de nuevo torneo</h4>
						<div class="card-tools">							
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<div class="row">
							<div class="col-md-12">	
								<form class="FormularioAjax" id="quickForm" action="<?php echo APP_URL; ?>app/ajax/torneoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
									<input type="hidden" name="modulo_torneo" value="<?php echo $modulo_torneo; ?>">
									<input type="hidden" name="torneo_id" value="<?php echo $torneoid; ?>">
									<div class="row" style="font-size: 13px; min-height: 187px;">
										<div class="col-md-2">
											<div class="mb-3">
												<label for="torneo_foto">Foto</label>		
												<div class="input-group">											
													<div class="fileinput fileinput-new" data-provides="fileinput">
														<div class="fileinput-new thumbnail" style="width: 110px; min-height: 130px;" data-trigger="fileinput"><img src="<?php echo $foto; ?>"> </div>
														<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 116px; max-height: 144px"></div>
														<div>
															<span class="bton bton-white bton-file" style="font-size: 13px;">
																<span class="fileinput-new">Seleccionar Foto</span>
																<span class="fileinput-exists">Cambiar</span>
																<input type="file" name="torneo_foto" id="foto" accept="image/*">
															</span>
															<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
														</div>
													</div>
												</div>		
											</div>
										<!-- /.mb-3 -->								
										</div>
										<div class="col-sm-10">
											<div class="row" style="font-size: 13px;">
												<div class="col-md-3">
													<div class="mb-3">
														<label for="torneo_nombre">Nombre torneo</label>
														<input type="text"  class="form-control" id="torneo_nombre" name="torneo_nombre" value="<?php echo $torneo_nombre; ?>">
													</div> 
												</div>
												<div class="col-md-3">										
													<div class="mb-3">
														<label for="torneo_ciudad">Ciudad</label>
														<input type="text" class="form-control" id="torneo_ciudad" name="torneo_ciudad" value="<?php echo $torneo_ciudad; ?>">
													</div>
												</div>
												<div class="col-md-3">										
													<div class="mb-3">
														<label for="torneo_lugar">Lugar</label>
														<input type="text" class="form-control" id="torneo_lugar" name="torneo_lugar"value="<?php echo $torneo_lugar; ?>">
													</div>
												</div>
												<div class="col-md-3">
													<div class="mb-3">
														<label for="torneo_organizador">Organizador</label>
														<input type="text" class="form-control" id="torneo_organizador" name="torneo_organizador" value="<?php echo $torneo_organizador; ?>">	
													</div>
												</div>
												<div class="col-md-3">
													<div class="mb-3">
														<label for="torneo_fechainicio">Fecha de inicio</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" name="torneo_fechainicio" id="torneo_fechainicio" value="<?php echo $torneo_fechainicio; ?>">
														</div>
													</div>
												</div>
												<div class="col-md-3">
													<div class="mb-3">
														<label for="torneo_fechafin">Fecha de finalización</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" name="torneo_fechafin" id="torneo_fechafin" value="<?php echo $torneo_fechafin; ?>">
														</div>
													</div>
												</div>
												<div class="col-md-6">
													<div class="mb-3">
														<label for="torneo_descripcion">Descripción</label>
														<input type="text" class="form-control" id="torneo_descripcion" name="torneo_descripcion" value="<?php echo $torneo_descripcion; ?>">
													</div>	
												</div>	
												<?php echo ds_acciones_form(APP_URL . 'torneoList/', ['limpiar' => true]); ?>	
											</div>
										</div>
									</div>									
								</form>		
								
								<div class="tab-custom-content">
									<h4 class="card-title">Torneos ingresados</h4>
								</div>										
								<div class="tab-content" id="custom-content-above-tabContent">	
									<div class="table-responsive">
									<table id="example1" class="table table-bordered table-striped table-sm nowrap" style="width:100%">
										<thead>
											<tr>
												<th>Nombre</th>
												<th>Ciudad</th>
												<th>Lugar</th>
												<th>F. inicio</th>
												<th>Fecha fin</th>
												<th>Organizador</th>
												<th>Descripción</th>
												<th>Estado</th>
												<th style="width: 200px;">Opciones</th>
											</tr>
										</thead>
										<tbody>
											<?php 
												echo $insTorneo->listarTorneos(); 
											?>							
										</tbody>	
									</table>
									</div>
								</div>
							</div>	
						</div>
					</div>
				</div>
			<!-- /.row -->
			</div><!-- /.container-fluid -->
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
	<!-- fileinput -->
    	
	<!-- Page specific script -->
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			new DataTable("#example1", {
			"responsive": true, "lengthChange": false, "autoWidth": false,
			/* Menor numero = se conserva mas tiempo. Descripcion cae primero;
			   Nombre y Opciones son los ultimos en irse. */
			"columnDefs": [
				{ "responsivePriority": 1,  "targets": 0 },   /* Nombre      */
				{ "responsivePriority": 2,  "targets": 8 },   /* Opciones    */
				{ "responsivePriority": 3,  "targets": 7 },   /* Estado      */
				{ "responsivePriority": 4,  "targets": 3 },   /* F. inicio   */
				{ "responsivePriority": 5,  "targets": 1 },   /* Ciudad      */
				{ "responsivePriority": 6,  "targets": 4 },   /* Fecha fin   */
				{ "responsivePriority": 8,  "targets": 5 },   /* Organizador */
				{ "responsivePriority": 9,  "targets": 2 },   /* Lugar       */
				{ "responsivePriority": 10, "targets": 6, "orderable": false } /* Descripcion */
			],
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
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>








