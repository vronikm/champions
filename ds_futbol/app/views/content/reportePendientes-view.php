<?php
	use app\controllers\reporteController;
	$insPendientes = new reporteController();	
	
	$sede_id 	= (($url[1] ?? "") != "") ? $url[1] : 0;
?>


<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Pagos Pendientes';
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
					<h1 class="m-0">Pagos pendientes</h1>
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

			<!-- Main content -->
			<section class="app-content">
				<div class="container-fluid">
					
					<div class="row">
						<div class="col-12">
							<div class="card">
							<div class="card-header">
								<h3 class="card-title">Alumnos</h3>
							</div>
							<!-- ./card-header -->
							<div class="card-body">
								<div class="table-responsive">
								<table id="example1" class="table table-bordered table-striped table-sm">
									<thead>
										<tr>
											<th>Identificación</th>
											<th>Nombres</th>
											<th>T.Pendientes</th>
											<th>Saldos Pendientes</th>
											<th>T.Pensiones</th>
											<th>Valor Pensiones</th>									
										</tr>
									</thead>
									<tbody>
										<?php
										/* Se pinta primero el cuerpo: valoresPendientes() es
										   quien calcula los totales del pie mientras recorre
										   las filas, así que tiene que ir antes. */
										echo $insPendientes->valoresPendientes($sede_id);
										?>
									</tbody>
									<?php $pie = $insPendientes->pieValoresPendientes(); ?>
									<?php if ($pie !== ''): ?>
									<?php
									/* Los totales van en el PIE y no en el cuerpo.
									   En el cuerpo llevaban colspan, y DataTables lee las
									   celdas por posición: pedía la columna 5 de una fila
									   que sólo tenía tres y saltaba «Requested unknown
									   parameter 5 for row 0». En el pie no lo recorre, el
									   colspan funciona, y el total se queda fijo aunque se
									   pagine o se filtre. */
									?>
									<tfoot class="table-group-divider fw-semibold">
										<?php echo $pie; ?>
									</tfoot>
									<?php endif; ?>
								</table>
								</div>
							</div>
							<!-- /.card-body -->
							</div>
							<!-- /.card -->
						</div>
					</div>
				
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








