<?php
	use app\controllers\equipoController;
	$insEquipo = new equipoController();
	
	$equipo_torneoid 	= (($url[1] ?? "") != "") ? $url[1] : 0;
	$equipo_id 		 	= (($url[2] ?? "") != "") ? $url[2] : 0;
	$modulo_equipo		= '';
	$equipo_nombre 		= '';
	$equipo_categoria	= '';	
	
	$foto = APP_URL.'app/views/imagenes/fotos/equipos/equipo_default.jpg';

	if($equipo_torneoid != 0){
		$nombreTorneo=$insEquipo->BuscarTorneoEquipo($equipo_torneoid);		
		if($nombreTorneo->rowCount()==1){
			$nombreTorneo	=	$nombreTorneo->fetch();				
			$torneo_nombre	= 	$nombreTorneo['torneo_nombre'];		
		}
	}else{
		$torneo_nombre = '';
	}

	if($equipo_id != 0){
		$datosEquipo=$insEquipo->BuscarEquipo($equipo_id);		
		if($datosEquipo->rowCount()==1){
			$datosEquipo=$datosEquipo->fetch(); 
			if ($datosEquipo['equipo_foto']!=""){
				$foto = APP_URL.'app/views/imagenes/fotos/equipos/'.$datosEquipo['equipo_foto'];
			}else{
				$foto = APP_URL.'app/views/imagenes/fotos/equipos/equipo_default.jpg';
			}
			$modulo_equipo 	= 'actualizar';
			$equipo_profesorid	= $datosEquipo['equipo_profesorid'];
			$equipo_nombre		= $datosEquipo['equipo_nombre'];
			$equipo_categoria	= $datosEquipo['equipo_categoria'];
			$equipo_sedeid		= $datosEquipo['sede_id'];
			$equipo_sede		= $datosEquipo['sede_nombre'];
			$equipo_estado		= $datosEquipo['ESTADO'];			
		}
	}else{
		$modulo_equipo 		= 'registrar';
		$equipo_profesorid	= '';
		$equipo_nombre 		= '';
		$equipo_categoria	= '';
		$equipo_sedeid		= '';
		$equipo_estado 		= 'A';
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Equipos';
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
					<h4 class="m-0">Equipos para el torneo <?php echo $torneo_nombre; ?></h4>
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
						<h4 class="card-title">Ingreso de nuevo equipo</h4>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<div class="row">
							<div class="col-md-12">	
								<form class="FormularioAjax" id="quickForm" action="<?php echo APP_URL; ?>app/ajax/equipoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
									<input type="hidden" name="modulo_equipo" value="<?php echo $modulo_equipo; ?>">
									<input type="hidden" name="equipo_torneoid" value="<?php echo $equipo_torneoid; ?>">
									<input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
									<div class="row" style="font-size: 13px; min-height: 187px;">
										<div class="col-md-2">
											<div class="mb-3">
												<label for="equipo_foto">Foto</label>		
												<div class="input-group">											
													<div class="fileinput fileinput-new" data-provides="fileinput">
														<div class="fileinput-new thumbnail" style="width: 110px; min-height: 130px;" data-trigger="fileinput"><img src="<?php echo $foto; ?>"> </div>
														<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 116px; max-height: 144px"></div>
														<div>
															<span class="bton bton-white bton-file" style="font-size: 13px;">
																<span class="fileinput-new">Seleccionar Foto</span>
																<span class="fileinput-exists">Cambiar</span>
																<input type="file" name="equipo_foto" id="foto" accept="image/*">
															</span>
															<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
														</div>
													</div>
												</div>		
											</div>
										<!-- /.mb-3 -->								
										</div>
										<div class="col-sm-10">
											<div class="row">
												<div class="col-md-2">
													<div class="mb-3">
														<label for="equipo_sedeid">Sede</label>
														<select class="form-select" id="equipo_sedeid" name="equipo_sedeid">
															<?php
																if($equipo_sedeid == 0){	
																	echo "<option value='0' selected='selected'>-Seleccionar sede-</option>";
																}else{
																	echo "<option value='0'>- Seleccionar sede -</option>";	
																}
															?>
															<?php echo $insEquipo->listarOptionSede($equipo_sedeid); ?>
														</select>	
													</div>
												</div>
												<div class="col-md-3">
													<div class="mb-3">
														<label for="equipo_nombre">Nombre equipo</label>
														<input type="text"  class="form-control" id="equipo_nombre" name="equipo_nombre" value="<?php echo $equipo_nombre; ?>">
													</div> 
												</div>
												<div class="col-md-3">										
													<div class="mb-3">
														<label for="equipo_categoria">Categoría</label>
														<input type="text" class="form-control" id="equipo_categoria" name="equipo_categoria" value="<?php echo $equipo_categoria; ?>">
													</div>
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="equipo_profesorid">Profesor a cargo</label>
														<select class="form-select" id="equipo_profesorid" name="equipo_profesorid">									
															<?php
																if($equipo_profesorid == 0){	
																	echo "<option value='0' selected='selected'>Seleccionar un profesor</option>";
																}else{
																	echo "<option value='0'>- Seleccionar un profesor -</option>";	
																}
															?>
															<?php echo $insEquipo->listarResponsable($equipo_profesorid); ?>
														</select>	
													</div>
												</div> 
												<?php echo ds_acciones_form(APP_URL . 'equipoList/' . $equipo_torneoid . '/', ['limpiar' => true]); ?>
											</div>
										</div>
									</div>									
								</form>		
								
								<div class="tab-custom-content">
									<h4 class="card-title">Equipos ingresados para el torneo <?php echo $torneo_nombre; ?></h4>
								</div>										
								<div class="tab-content" id="custom-content-above-tabContent" style="font-size: 13px;">	
									<div class="table-responsive">
									<table id="example1" class="table table-bordered table-striped table-sm" style="font-size: 13px;">
										<thead>
											<tr>
												<th>Sede</th>
												<th>Torneo</th>
												<th>Equipo</th>
												<th>Categoría</th>
												<th>Profesor a cargo</th>
												<th>Estado</th>
												<th>Jugadores</th>
												<th style="width: 255px;">Operaciones</th>
											</tr>
										</thead>
										<tbody>
											<?php 
												echo $insEquipo->listarEquiposTorneo($equipo_torneoid); 
											?>							
										</tbody>	
									</table>
									</div>
								</div>
							</div>	
						</div>
					</div>
					<div class="card-footer">						
						<?php include "./app/views/inc/btn_back.php";?>					
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
        	}
			});			    
		});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
    
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>








