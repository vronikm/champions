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

	/*
	| Sede (2026-11-27): sólo las activas que la sesión puede ver, y «Todas»
	| (valor 0), que es lo que se elige al entrar. ANTES salían también las
	| sedes cerradas y la pantalla abría vacía hasta elegir una sede y pulsar
	| Buscar. Una sede que ya no está en la lista vuelve a «Todas».
	*/
	$sedes = $insHorario->sedesParaHorarios();
	$horario_sedeid = (int)$horario_sedeid;
	if ($horario_sedeid !== 0 && !isset($sedes[$horario_sedeid])) { $horario_sedeid = 0; }
	$sedeNombre = $horario_sedeid !== 0 ? $sedes[$horario_sedeid] : 'Todas las sedes';

	/* La búsqueda se hace antes de pintar para poder contar los horarios en
	   la cabecera del resultado. */
	$filas = $insHorario->listarHorarios($horario_nombre, $horario_detalle, $horario_sedeid);
	$total = substr_count($filas, '<tr');
	$hayFiltroTexto = $horario_nombre !== '' || $horario_detalle !== '';
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
				| Criterios de búsqueda. La Sede va primero: es el criterio de casi
				| siempre, y al cambiarla se busca sola (el botón queda para el
				| nombre y el detalle). En el teléfono, la sede a todo lo ancho y
				| nombre y detalle en dos columnas.
				*/
				?>
				<form action="<?php echo APP_URL."asistenciaListHorario/" ?>" method="POST" autocomplete="off" class="card ds-horarios__filtros" id="horarios-filtros">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-12 col-lg-4">
								<label for="horario_sedeid" class="form-label">Sede</label>
								<select class="form-select" id="horario_sedeid" name="horario_sedeid">
									<option value="0" <?php echo $horario_sedeid === 0 ? 'selected' : ''; ?>>Todas las sedes</option>
									<?php foreach ($sedes as $idSede => $nombreSede): ?>
										<option value="<?php echo (int)$idSede; ?>" <?php echo $horario_sedeid === (int)$idSede ? 'selected' : ''; ?>><?php echo $h($nombreSede); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-lg-3">
								<label for="horario_nombre" class="form-label">Nombre</label>
								<input type="search" class="form-control" id="horario_nombre" name="horario_nombre" placeholder="Ej.: Grupo 2" value="<?php echo $h($horario_nombre); ?>" enterkeyhint="search">
							</div>
							<div class="col-6 col-lg-3">
								<label for="horario_detalle" class="form-label">Detalle</label>
								<input type="search" class="form-control" id="horario_detalle" name="horario_detalle" placeholder="Ej.: Sub 12" value="<?php echo $h($horario_detalle); ?>" enterkeyhint="search">
							</div>
							<div class="col-12 col-lg-2 d-flex align-items-end">
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
							<i class="far fa-clock me-2" aria-hidden="true"></i>Horarios
							<span class="badge text-bg-secondary ms-1"><?php echo $total; ?></span>
							<span class="ds-horarios__sede"><i class="fas fa-map-marker-alt me-1" aria-hidden="true"></i><?php echo $h($sedeNombre); ?></span>
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
						<?php if ($total === 0): ?>
							<p class="ds-horarios__ayuda mb-0">
								<i class="fas fa-circle-info me-1" aria-hidden="true"></i>
								<?php if (!$sedes): ?>
									No tiene sedes activas asignadas. Se asignan en Core › Usuarios.
								<?php elseif ($hayFiltroTexto): ?>
									Ningún horario coincide con el nombre o el detalle escritos en <?php echo $h($sedeNombre); ?>.
								<?php else: ?>
									<?php echo $h($sedeNombre); ?> no tiene horarios todavía.
								<?php endif; ?>
							</p>
						<?php else: ?>
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Horario</th>
									<th class="ds-col-opcional">Días y hora</th>
									<th>Profesor</th>
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
			/* Cambiar la sede busca sola: es el criterio de casi siempre. */
			var sede = document.getElementById('horario_sedeid');
			if (sede) {
				sede.addEventListener('change', function () { document.getElementById('horarios-filtros').submit(); });
			}

			if (!document.getElementById('example1')) { return; }
			new DataTable("#example1", {
				autoWidth: false,
				lengthChange: false,
				pageLength: 25,
				order: [[0, 'asc']],
				columnDefs: [
					{ targets: 3, type: 'num' },
					{ targets: 5, orderable: false, searchable: false }   /* acciones */
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