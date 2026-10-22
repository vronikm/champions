<?php
	use app\controllers\representanteController;
	$insRepre = new representanteController();	

	$repreid=$insRepre->limpiarCadena($url[1]);

	if(isset($_POST['repre_identificacion'])){
		$repre_identificacion = $insRepre->limpiarCadena($_POST['repre_identificacion']);
	} ELSE{
		$repre_identificacion = "";
	}

	if(isset($_POST['repre_nombre1'])){
		$repre_primernombre = $insRepre->limpiarCadena($_POST['repre_nombre1']);
	} ELSE{
		$repre_primernombre = "";
	}

	if(isset($_POST['repre_apellido1'])){
		$repre_apellidopaterno = $insRepre->limpiarCadena($_POST['repre_apellido1']);
	} ELSE{
		$repre_apellidopaterno = "";
	}

?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Representantes';
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
				<h1 class="m-0">Representantes</h1>
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

		<!-- Section listado de representados -->
		<section class="app-content">
			<form action="<?php echo APP_URL."representanteList/" ?>" method="POST" autocomplete="off" enctype="multipart/form-data" >
			
				<div class="container-fluid">
					<div class="card card-default">
						<div class="card-header">
							<h3 class="card-title">Búsqueda de representantes</h3>
							<div class="card-tools">
								<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
							</div>
						</div>  

						<!-- card-body -->                
						<div class="card-body">
							<div class="row">
								<div class="col-sm-3">
									<div class="mb-3">
										<label for="repre_identificacion">Identificación</label>                        
										<input type="text" class="form-control" id="repre_identificacion" name="repre_identificacion" placeholder="Identificación" value="<?php echo $repre_identificacion; ?>">
									</div>        
								</div>
								<div class="col-sm-3">
									<div class="mb-3">
										<label for="repre_apellido1">Apellido paterno</label>
										<input type="text" class="form-control" id="repre_apellido1" name="repre_apellido1" placeholder="Primer apellido" value="<?php echo $repre_apellidopaterno; ?>">
									</div>         
								</div>
								<div class="col-md-3">
									<div class="mb-3">
										<label for="repre_nombre1">Primer nombre</label>
										<input type="text" class="form-control" id="repre_nombre1" name="repre_nombre1" placeholder="Primer nombre" value="<?php echo $repre_primernombre; ?>">
									</div>
								</div>								
								<div class="col-md-2 d-flex align-items-end">
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
							<a href="<?php echo APP_URL; ?>representanteNew/" class="btn btn-primary btn-sm" >Nuevo Representante</a>
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
									<th class="text-center">Alumnos</th>
									<th>Representado</th>
									<th style width="140px">Protección de datos</th>								
									<th style width="160px">Operaciones</th>
								</tr>
							</thead>
							<?php
							/*
							| EL CUERPO LLEGA DEL SERVIDOR, PAGINA A PAGINA
							|
							| Antes se pintaban aqui las 265 filas y la pantalla pesaba
							| 410 KB, de los cuales 377 eran este <tbody>. DataTables
							| paginaba despues, en el navegador: se pagaba el peso entero
							| para enseñar veinticinco.
							|
							| Ahora lo sirve representanteTablaAjax.php. La busqueda y el
							| orden siguen alcanzando a TODAS las filas, porque los
							| resuelve la base; no se pierde nada y la pagina baja a
							| decenas de KB.
							|
							| El <tbody> se deja vacio a proposito: DataTables lo rellena.
							*/
							?>
							<tbody></tbody>
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
			/*
			| serverSide: la base pagina, busca y ordena. El navegador sólo
			| pinta lo que le llega.
			|
			| processing muestra «Procesando…» mientras viaja la petición: sin
			| eso, en una conexión lenta la tabla parece congelada.
			|
			| columnDefs: las tres últimas columnas son botones, no datos. No
			| se ordenan porque ordenar por «Operaciones» no significa nada, y
			| el servidor sólo acepta las cuatro primeras (lista blanca).
			| «Alumnos» (la 3) sí se ordena; el primer clic, de más a menos
			| representados.
			*/
			"serverSide": true,
			"processing": true,
			"ajax": {
				"url": "<?php echo APP_URL; ?>app/ajax/representanteTablaAjax.php",
				"type": "GET"
			},
			"order": [[2, "asc"]],
			"columnDefs": [
				{ "targets": 3, "className": "text-center", "orderSequence": ["desc", "asc"], "responsivePriority": 2 },
				{ "orderable": false, "targets": [4, 5, 6] }
			],
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

	<?php
	/*
	| UN SOLO FORMULARIO PARA TODAS LAS FILAS
	|
	| Antes cada representante traia DOS formularios completos —marcar firmado
	| y eliminar—, cada uno con sus campos ocultos. Con 264 registros eso son
	| 528 formularios y 1.056 campos: 228 KB de los 580 que pesaba la pagina,
	| el 39 %. Y crece con cada representante que se da de alta.
	|
	| Ahora las filas llevan un boton con data-accion y data-id, y este
	| formulario unico se rellena al pulsarlo. No es una reimplementacion: al
	| llamar a requestSubmit() pasa por el MISMO manejador de ajax.js que
	| antes, asi que la confirmacion, el envio y el tratamiento de la
	| respuesta siguen siendo exactamente los de siempre.
	*/
	?>
	<form class="FormularioAjax d-none" id="formAccionRepre"
	      action="<?php echo APP_URL; ?>app/ajax/representanteAjax.php"
	      method="POST" autocomplete="off">
		<input type="hidden" name="modulo_repre" id="accionRepre" value="">
		<input type="hidden" name="repre_id"     id="idRepre"     value="">
	</form>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>

	<script>
	/* Delegado en el documento y no en cada boton: DataTables rehace las filas
	   al paginar y ordenar, y los oyentes puestos fila a fila se pierden ahi. */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('.js-repre');
		if (!b) { return; }
		document.getElementById('accionRepre').value = b.dataset.accion || '';
		document.getElementById('idRepre').value     = b.dataset.id     || '';
		document.getElementById('formAccionRepre').requestSubmit();
	});
	</script>

  </body>
</html>








