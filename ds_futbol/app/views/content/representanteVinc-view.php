<?php
	use app\controllers\representanteController;
	$insRepre = new representanteController();	

	$repreid = ds_id_de_url($url, 1, APP_URL . 'representanteList/');

	$datos=$insRepre->datosRepresentante($repreid);
	
	if($datos->rowCount()>0){
		$datos=$datos->fetch(); 
	} else {
			/* Sin registro, $datos seguiria siendo el statement y la
			   vista lo usaria como array: error fatal en pantalla, con la
			   ruta del servidor dentro. Se vuelve a donde ya vuelve esta
			   misma vista cuando el identificador no sirve. */
			header("Location: " . APP_URL . 'representanteList/');
			exit();
		}


	if(isset($_POST['alumno_identificacion'])){
		$alumno_identificacion = $insRepre->limpiarCadena($_POST['alumno_identificacion']);
	} ELSE{
		$alumno_identificacion = "";
	}

	if(isset($_POST['alumno_nombre1'])){
		$alumno_primernombre = $insRepre->limpiarCadena($_POST['alumno_nombre1']);
	} ELSE{
		$alumno_primernombre = "";
	}

	if(isset($_POST['alumno_apellido1'])){
		$alumno_apellidopaterno = $insRepre->limpiarCadena($_POST['alumno_apellido1']);
	} ELSE{
		$alumno_apellidopaterno = "";
	}
	
?>


<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Representados';
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
				<h4 class="m-0">Búsqueda de representado para <?php echo $datos['REPRESENTANTE']; ?></h4>
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

		<!-- Section búsqueda de representados -->
		<section class="app-content">
			<form action="<?php echo APP_URL."representanteVinc/".$repreid."/" ?>" method="POST" autocomplete="off" enctype="multipart/form-data" >
			
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
							<div class="col-sm-3">
								<div class="mb-3">
									<label for="alumno_identificacion">Identificación</label>                        
									<input type="text" class="form-control" id="alumno_identificacion" name="alumno_identificacion" placeholder="Identificación" value="<?php echo $alumno_identificacion; ?>">
								</div>        
							</div>
							<div class="col-sm-3">
								<div class="mb-3">
									<label for="alumno_apellido1">Apellido paterno</label>
									<input type="text" class="form-control" id="alumno_apellido1" name="alumno_apellido1" placeholder="Primer apellido" value="<?php echo $alumno_apellidopaterno; ?>">
								</div>         
							</div>
							<div class="col-md-3">
								<div class="mb-3">
									<label for="alumno_nombre1">Primer nombre</label>
									<input type="text" class="form-control" id="alumno_nombre1" name="alumno_nombre1" placeholder="Primer nombre" value="<?php echo $alumno_primernombre; ?>">
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
									<th>Apellidos</th>
									<th style="width: 220px;">Opciones</th>
								</tr>
							</thead>
							<tbody>
								<?php 
									echo $insRepre->buscarRepresentado($alumno_identificacion,$alumno_apellidopaterno, $alumno_primernombre, $repreid); 
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
			});			    
		});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
    
  </body>
</html>








