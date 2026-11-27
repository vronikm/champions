<?php
	use app\controllers\asistenciaController;
	$insAlumno = new asistenciaController();

	$horario_id = ds_id_de_url($url, 1, APP_URL . 'asistenciaListHorario/');

	/* Nombre, sede, días, hora, lugares y profesores del horario activo.
	   Sin registro (o inactivo) se vuelve a la lista de horarios: seguir
	   con un horario vacío era un error fatal en pantalla. */
	$horario = $horario_id ? $insAlumno->resumenHorario((int)$horario_id) : null;
	if (!$horario) {
		header("Location: " . APP_URL . 'asistenciaListHorario/');
		exit();
	}

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$filas = $insAlumno->ListaAlumnosHorario($horario['horario_id']);
	$total = substr_count($filas, '<tr');

	$LETRAS = [1 => ['L', 'Lunes'], 2 => ['M', 'Martes'], 3 => ['X', 'Miércoles'], 4 => ['J', 'Jueves'], 5 => ['V', 'Viernes'], 6 => ['S', 'Sábado'], 7 => ['D', 'Domingo']];
	$hoyIso = (int)date('N');
	$chips = '';
	foreach (array_filter(explode(',', (string)$horario['dias']), 'strlen') as $d) {
		$d = (int)$d;
		if (!isset($LETRAS[$d])) { continue; }
		$chips .= '<abbr title="' . $LETRAS[$d][1] . '" class="ds-toma__dia' . ($d === $hoyIso ? ' ds-toma__dia--hoy' : '') . '">' . $LETRAS[$d][0] . '</abbr>';
	}
	$rango = $horario['inicio'] ? substr($horario['inicio'], 0, 5) . ' – ' . substr($horario['fin'], 0, 5) : '';
	$profesores = array_filter(explode('|', (string)$horario['profesores']), 'strlen');

	$urlAsignar  = APP_URL . 'asistenciaHorarioJugador/' . (int)$horario['horario_id'] . '/' . (int)$horario['horario_sedeid'] . '/';
	$urlImprimir = APP_URL . 'horarioListaPDF/' . (int)$horario['horario_id'] . '/';
	$urlHorarios = APP_URL . 'asistenciaListHorario/';
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Alumnos del horario';
	$extras      = array (0 => 'datatables',1 => 'swal',);
	$cabeceraExtra = <<<'CSS'
	<style>
		/* Resumen del horario: qué, cuándo, dónde y con quién. */
		.ds-hl__nombre { font-size: 1.2rem; font-weight: 700; margin: 0; line-height: 1.25; }
		.ds-hl__detalle { color: var(--bs-secondary-color); margin-top: .15rem; }
		.ds-hl__datos { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; margin-top: .75rem; font-size: .925rem; }
		.ds-hl__dato { display: inline-flex; align-items: center; gap: .4rem; min-width: 0; }
		.ds-hl__dato > .fas { color: var(--bs-secondary-color); width: 1rem; text-align: center; }
		.ds-hl__total { font-size: 1.6rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
		.ds-hl__total-rotulo { font-size: .8rem; color: var(--bs-secondary-color); }
		.ds-hl__acciones { display: flex; flex-wrap: wrap; gap: .5rem; }
		/* Ficha compacta del alumno (teléfono y tableta): nombre y categoría a
		   la izquierda, los dos botones a la derecha en la misma ficha. */
		@media (max-width: 991.98px) {
			#example1.ds-crud__tabla > tbody > tr { grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: .15rem .75rem; padding: .7rem .75rem; margin-bottom: .5rem; }
			#example1 td.ds-ficha__principal { grid-column: 1; grid-row: 1; }
			#example1 td.ds-ficha__dato { grid-column: 1; grid-row: 2; padding-left: 3.15rem !important; font-size: .8rem; }
			#example1 td.ds-ficha__dato[data-etiqueta]::before { content: "Cat. "; }
			#example1 td.ds-ficha__acciones { grid-column: 2; grid-row: 1 / span 2; margin: 0; padding: 0; border: 0; align-self: center; }
			#example1 td.ds-ficha__acciones .ds-ficha__botones { gap: .4rem; }
			#example1 td.ds-ficha__acciones .ds-ficha__primario { flex: none; }
			#example1 td.ds-ficha__acciones .ds-ficha__texto { display: none; }
			#example1 td.ds-ficha__acciones .ds-ficha__primario i { margin-right: 0 !important; }
		}
		@media (max-width: 575.98px) {
			/* Botones a todo lo ancho y del tamaño del dedo. */
			.ds-hl__acciones { display: grid; grid-template-columns: 1fr 1fr; }
			.ds-hl__acciones .btn { min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; }
			.ds-hl__acciones .ds-hl__principal { grid-column: 1 / -1; }
			.ds-hl__datos { flex-direction: column; gap: .4rem; }
		}
	</style>
CSS;
	require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">

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
						<h1 class="m-0">Alumnos del horario</h1>
					</div>
					<div class="col-sm-6">
						<ol class="breadcrumb float-sm-end">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo $urlHorarios; ?>">Horarios</a></li>
							<li class="breadcrumb-item active" aria-current="page"><?php echo $h($horario['horario_nombre']); ?></li>
						</ol>
					</div>
				</div>
			</div>
		</div>
		<!-- /.content-header -->

		<!-- Main content -->
		<section class="app-content">
			<div class="container-fluid">

				<!-- Resumen del horario -->
				<div class="card mb-3">
					<div class="card-body">
						<div class="d-flex align-items-start gap-3">
							<div class="flex-grow-1 min-w-0">
								<h2 class="ds-hl__nombre"><?php echo $h($horario['horario_nombre']); ?></h2>
								<?php if (trim((string)$horario['horario_detalle']) !== ''): ?>
									<div class="ds-hl__detalle"><?php echo $h($horario['horario_detalle']); ?></div>
								<?php endif; ?>
							</div>
							<div class="text-end flex-shrink-0">
								<div class="ds-hl__total"><?php echo $total; ?></div>
								<div class="ds-hl__total-rotulo"><?php echo $total === 1 ? 'alumno' : 'alumnos'; ?></div>
							</div>
						</div>

						<div class="ds-hl__datos">
							<?php if ($chips !== '' || $rango !== ''): ?>
								<span class="ds-hl__dato"><i class="fas fa-clock" aria-hidden="true"></i>
									<?php if ($chips !== ''): ?><span class="ds-toma__dias"><?php echo $chips; ?></span><?php endif; ?>
									<?php if ($rango !== ''): ?><span class="fw-semibold" style="font-variant-numeric: tabular-nums"><?php echo $rango; ?></span><?php endif; ?>
								</span>
							<?php endif; ?>
							<span class="ds-hl__dato"><i class="fas fa-location-dot" aria-hidden="true"></i>
								<span><?php echo implode(' · ', array_filter([$h($horario['sede_nombre']), $h($horario['lugares'])], 'strlen')); ?></span>
							</span>
							<?php if ($profesores): ?>
								<span class="ds-hl__dato"><i class="fas fa-chalkboard-user" aria-hidden="true"></i>
									<span><?php echo implode(', ', array_map($h, $profesores)); ?></span>
								</span>
							<?php endif; ?>
						</div>

						<div class="ds-hl__acciones mt-3">
							<a href="<?php echo $urlAsignar; ?>" class="btn btn-primary ds-hl__principal"><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Asignar alumnos</a>
							<a href="<?php echo $urlImprimir; ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener"><i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir</a>
							<button type="button" class="btn btn-outline-secondary" id="hlVolver"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Volver</button>
						</div>
					</div>
				</div>

				<!-- Lista de alumnos -->
				<div class="card ds-crud__lista">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-users me-2" aria-hidden="true"></i>Alumnos asignados
							<span class="badge text-bg-secondary ms-1"><?php echo $total; ?></span>
						</h3>
						<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
					</div>
					<div class="card-body">
						<?php if ($total === 0): ?>
							<div class="text-center text-body-secondary py-4">
								<i class="fas fa-user-group fa-2x mb-2 opacity-50" aria-hidden="true"></i>
								<p class="mb-3">Este horario aún no tiene alumnos asignados.</p>
								<a href="<?php echo $urlAsignar; ?>" class="btn btn-primary"><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Asignar alumnos</a>
							</div>
						<?php else: ?>
							<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
								<thead>
									<tr>
										<th>Alumno</th>
										<th>Categoría</th>
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

			</div>
		</section>
		<!-- /.content -->

      </div>
      <!-- /.vista -->

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>
    <!-- ./wrapper -->

	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive; en el teléfono cada fila es una ficha (.ds-crud) -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/* Volver: a la pantalla anterior si se llegó desde el sistema; si la
		   lista se abrió en otra pestaña (Horarios la abre así), se cierra;
		   si no, a la lista de horarios. */
		document.getElementById('hlVolver').addEventListener('click', function () {
			if (history.length > 1 && document.referrer.indexOf(location.origin + '/') === 0) { history.back(); return; }
			if (window.opener) { window.close(); return; }
			location.href = <?php echo json_encode($urlHorarios, JSON_UNESCAPED_SLASHES); ?>;
		});

		if (!document.getElementById('example1')) { return; }

		/* «Mostrar N» elige cuántos alumnos por página y se recuerda. */
		var CLAVE = 'ds-horario-lista-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable('#example1', {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [[0, 'asc']],
			columnDefs: [
				{ targets: 1, type: 'num' },
				{ targets: 2, orderable: false, searchable: false }
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: 'Este horario no tiene alumnos',
				info: '_START_ a _END_ de _TOTAL_ alumnos',
				infoEmpty: 'Sin alumnos',
				infoFiltered: '(de _MAX_)',
				lengthMenu: 'Mostrar _MENU_',
				search: '',
				searchPlaceholder: 'Buscar por nombre o cédula…',
				zeroRecords: 'Ningún alumno coincide',
				paginate: { next: 'Siguiente', previous: 'Anterior' }
			}
		});
		tabla.on('length', function (e, s, n) { try { localStorage.setItem(CLAVE, String(n)); } catch (err) {} });
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>

  </body>
</html>
