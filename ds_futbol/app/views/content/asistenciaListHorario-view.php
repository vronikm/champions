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

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

	/* La búsqueda se hace antes de pintar para poder contar los horarios en
	   la cabecera del resultado. */
	$hayCriterio = $horario_nombre != '' || $horario_detalle != '' || $horario_sedeid != 0;
	$filas       = $hayCriterio ? $insHorario->listarHorarios($horario_nombre, $horario_detalle, $horario_sedeid) : '';
	$total       = substr_count($filas, '<tr');

	$opcionesSede = $insHorario->listarOptionSedebusqueda($horario_sedeid);
	/* Nombre de la sede elegida, para rotular el resultado. */
	$sedeNombre = ($horario_sedeid != 0 && preg_match('/<option value=' . preg_quote((string)$horario_sedeid, '/') . ' selected>([^<]*)</', $opcionesSede, $m))
		? trim($m[1]) : '';
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
						<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
						<li class="breadcrumb-item active">Horarios</li>
					</ol>					
				</div><!-- /.col -->
			</div><!-- /.row -->
			</div><!-- /.container-fluid -->
		</div>
		<!-- /.content-header -->

		<!-- Section listado de alumnos -->
		<section class="app-content">
			<div class="container-fluid ds-crud">

				<?php
				/*
				| Criterios de búsqueda. ANTES la tarjeta tenía 140 px de alto fijo
				| y la fila 60: en el teléfono los campos se apilaban, la Sede y el
				| botón Buscar quedaban debajo de la tarjeta del resultado y no se
				| podían pulsar.
				|
				| La Sede va PRIMERO en el marcado: es el criterio con el que se busca
				| casi siempre, y en el teléfono se ve y se recorre antes que nada.
				| En escritorio (lg) las clases order-lg-* conservan el orden de
				| siempre: nombre, detalle, sede y el botón.
				*/
				?>
				<form action="<?php echo APP_URL."asistenciaListHorario/" ?>" method="POST" autocomplete="off" class="card ds-horarios__filtros">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-12 col-sm-6 col-lg-3 order-lg-3">
								<label for="horario_sedeid" class="form-label">Sede</label>
								<select class="form-select" id="horario_sedeid" name="horario_sedeid">
									<option value="0" <?php echo $horario_sedeid == 0 ? 'selected' : ''; ?>>Seleccionar sede</option>
									<?php echo $opcionesSede; ?>
								</select>
							</div>
							<div class="col-6 col-sm-6 col-lg-3 order-lg-1">
								<label for="horario_nombre" class="form-label">Horario nombre</label>
								<input type="text" class="form-control" id="horario_nombre" name="horario_nombre" placeholder="Nombre" value="<?php echo $h($horario_nombre); ?>">
							</div>
							<div class="col-6 col-sm-6 col-lg-3 order-lg-2">
								<label for="horario_detalle" class="form-label">Horario detalle</label>
								<input type="text" class="form-control" id="horario_detalle" name="horario_detalle" placeholder="Detalle" value="<?php echo $h($horario_detalle); ?>">
							</div>
							<div class="col-12 col-sm-6 col-lg-3 order-lg-4 d-flex align-items-end">
								<div class="w-100">
									<?php echo ds_boton_buscar(); ?>
								</div>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resultado de la búsqueda =================== -->
				<div class="card ds-crud__lista" id="horarios-resultado">
					<div class="card-header">
						<h3 class="card-title me-auto">
							<i class="far fa-clock me-2" aria-hidden="true"></i>Resultado de la búsqueda
							<?php if ($hayCriterio): ?>
								<span class="badge text-bg-secondary ms-1"><?php echo $total; ?></span>
							<?php endif; ?>
							<?php if ($sedeNombre !== ''): ?>
								<span class="ds-horarios__sede"><i class="fas fa-map-marker-alt me-1" aria-hidden="true"></i><?php echo $h($sedeNombre); ?></span>
							<?php endif; ?>
						</h3>
						<div class="card-tools d-flex align-items-center gap-1">
							<?php
							/* El alta va junto a su tabla, no en el formulario de filtros. */
							if ($horario_sedeid != 0) {
								echo '<form class="d-inline-flex m-0" action="'.APP_URL.'asistenciaHorario/" method="POST" autocomplete="off" target="_blank">
										<input type="hidden" name="horario_sedeid" value="'.(int)$horario_sedeid.'">
										<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo horario</button>
									</form>';
							}
							?>
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<?php if (!$hayCriterio): ?>
							<p class="ds-horarios__ayuda mb-0">
								<i class="fas fa-hand-point-up me-1" aria-hidden="true"></i>Elija una sede y pulse <strong>Buscar</strong> para ver sus horarios.
							</p>
						<?php else: ?>
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Horario</th>
									<th>Alumnos</th>
									<th>Estado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filas; ?>
							</tbody>
						</table>
						<?php endif; ?>
					</div>
				</div>

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
	<!-- DataTables: sin Responsive ni botones de exportar -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
		/*
		| Sin la extensión Responsive (en el teléfono escondía «Ver lista» y
		| «Asignar alumnos» tras un ▶) ni los botones de exportar, que no se
		| configuraban: en el teléfono cada horario es una ficha (futbol.css,
		| .ds-crud). Sin búsqueda no hay tabla y no se inicia nada.
		*/
		document.addEventListener('DOMContentLoaded', function () {
			if (!document.getElementById('example1')) { return; }
			new DataTable("#example1", {
				autoWidth: false,
				lengthChange: false,
				pageLength: 25,
				order: [[0, 'asc']],
				columnDefs: [
					{ targets: 1, type: 'num' },
					{ targets: 3, orderable: false, searchable: false }   /* acciones */
				],
				layout: {
					topStart: null,
					topEnd: 'search',
					bottomStart: 'info',
					bottomEnd: { paging: { firstLast: false } }
				},
				language: {
					emptyTable: "No hay horarios con estos criterios",
					info: "_START_ a _END_ de _TOTAL_ horarios",
					infoEmpty: "Sin horarios",
					infoFiltered: "(de _MAX_)",
					search: "",
					searchPlaceholder: "Filtrar la lista…",
					zeroRecords: "Ningún horario coincide con el filtro",
					paginate: { next: "Siguiente", previous: "Anterior" },
					aria: {
						sortAscending: ": ordenar de forma ascendente",
						sortDescending: ": ordenar de forma descendente"
					}
				}
			});
		});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
    
  </body>
</html>