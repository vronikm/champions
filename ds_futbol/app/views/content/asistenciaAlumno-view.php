<?php
	use app\controllers\asistenciaController;
	$insAlumno = new asistenciaController();

	$horario_id = ds_id_de_url($url, 1, APP_URL . 'asistenciaListHorario/');

	if($horario_id != 0){
		$horarioSede=$insAlumno->BuscarHorarioSede($horario_id);		
		if($horarioSede->rowCount()==1){
			$horarioSede	=	$horarioSede->fetch();				
		} else {
			/* Sin registro, $horarioSede seguiria siendo el statement y la
			   vista lo usaria como array: error fatal en pantalla, con la
			   ruta del servidor dentro. Se vuelve a donde ya vuelve esta
			   misma vista cuando el identificador no sirve. */
			header("Location: " . APP_URL . 'asistenciaListHorario/');
			exit();
		}
	}
	
	if(isset($_POST['fecha'])){
		$fechahoy =  $insAlumno->limpiarCadena($_POST['fecha']);		
	} ELSE{
		$fechahoy = date('Y-m-d');		
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Alumnos';
	$extras      = array (0 => 'datatables',1 => 'swal',);
	require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">

		<!-- Preloader -->
		<!--?php require_once "app/views/inc/preloader.php"; ?-->
		<!-- /.Preloader -->

      	<!-- Navbar -->	
		<nav class="app-header navbar navbar-expand bg-body">
			<!-- Left navbar links -->
			<ul class="navbar-nav">
				<li class="nav-item">
					<a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="fas fa-bars"></i></a>
				</li>
				<form action="<?php echo APP_URL."asistenciaAlumno/$horario_id" ?>" method="POST" autocomplete="off" enctype="multipart/form-data" >
				<div class="container-fluid">					
					<div class="col-xm-6">		
						<li class="nav-item d-sm-inline-block">
							<div class="card-comment">											
								<div class="input-group">
															<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
					
									<?php 
										if($_SESSION['rol']!= 1 && $_SESSION['rol']!= 2){
											echo '<input class="form-control" value="'.$fechahoy.'" disabled>';
											echo '<input type="hidden" name="fecha" value="'.$fechahoy.'">';
										}else{
											echo '<input type="date" class="form-control" id="fecha" name="fecha" value="'.$fechahoy.'" required>
											  <button type="submit" class="btn btn-info">Generar lista</button>
											';
										}
									?>							
								</div>								
							</div>				
						</li>
					</div>
					
					
				</div>
					
				</form>	
				
			</ul>

			 <!-- Right navbar links -->
		</nav>
	
    	<!--?php require_once "app/views/inc/navbar.php"; ?-->
      	<!-- /.navbar -->

		<!-- Main Sidebar Container -->
		<?php require_once "app/views/inc/main-sidebar.php"; ?>
		<!-- /.Main Sidebar Container -->  

      <!-- vista -->
      <div class="app-main">
		
		<!-- Main content -->
		<section class="app-content">
			<div class="container-fluid">
			<!-- Small boxes (Stat box) -->
				<div class="card card-default">
					

					<div class="card-body card-comments">
						
					
		                  
						<div class="table-responsive">
						<table id="example1" class="table table-bordered table-striped table-sm ">
							<thead>
								<tr>
									<th>Categoría</th>
									<th>Nombres</th>									
									<th>Asistencia</th>	
								</tr>
							</thead>
							<tbody>
								<?php 
									if(isset($_POST['fecha'])){												
										echo $insAlumno->ListadoAlumnos($horarioSede["horario_id"],$fechahoy);		
									}else{
										echo $insAlumno->ListadoAlumnos($horarioSede["horario_id"], date('Y-m-d'));		
									}								
								?>								
							</tbody>	
						</table>
						</div>	
						<div class="card-footer">
							<!--a href="<?php echo APP_URL.'equipoList/'.$horarioSede["horario_id"].'/'; ?>" class="btn btn-dark btn-sm">Regresar</a-->
							<button class="btn btn-dark btn-back btn-sm" onclick="cerrarPestana()">Regresar</button>
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
				"responsive": true,
				"lengthChange": false, // Deshabilitar el cambio de longitud
				"autoWidth": false,
				"paging": false, // Deshabilitar la paginación
				"searching": false, // Habilitar la búsqueda
				"ordering": false, // Desactiva la opción de ordenar
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

	<script>
		// NUEVO: Evento para Toma de Asistencia
		$(document).on('click', '.btn-asistencia', function() {
			var estado = $(this).data('estado');
			var alumno_id = $(this).data('alumnoid');
			var fecha = $(this).data('fecha');
			var boton = $(this);

			$.ajax({
				type: 'POST',
				url: '<?php echo APP_URL; ?>app/ajax/asistenciaAjax.php',
				data: {
					modulo_asistencia: 'asistencia',
					estado: estado,
					fecha: fecha,
					alumno_id: alumno_id
				},
				beforeSend: function() {
					boton.prop('disabled', true);
				},
				success: function(response) {
					/* El color dice cual esta elegido, pero el color solo no basta:
					   aria-pressed lo anuncia para quien no lo distinga o use lector
					   de pantalla. Sin actualizarlo aqui, quedaria mintiendo en cuanto
					   alguien pulsara. */
					// Puedes mostrar un toast, cambiar color, etc.
					$('.btn-asistencia[data-alumnoid="' + alumno_id + '"]').removeClass('btn-info').addClass('btn-dark').attr('aria-pressed','false'); // Reset buttons
					boton.removeClass('btn-dark').addClass('btn-info').attr('aria-pressed','true'); // Highlight selected

					// Si quieres mostrar un mensaje de éxito:
					// alert("Asistencia guardada!");
				},
				error: function() {
					alert('Error al registrar asistencia.');
				},
				complete: function() {
					boton.prop('disabled', false);
				}
			});
		});
		
	</script>


	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
    
  </body>
</html>








