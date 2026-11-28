<?php
	use app\controllers\alumnoController;
	$insAlumno = new alumnoController();

	/* Rol de la sesión: decide si el filtro ofrece "Todas las sedes". */
	$rolid = rol_actual();

	$campo = static fn(string $n) => isset($_GET[$n]) && is_string($_GET[$n]) ? $insAlumno->limpiarCadena($_GET[$n]) : '';
	$alumno_identificacion  = '';   /* el campo salió del formulario (la cédula se busca con «Filtrar la lista…»): sin él, nada filtra a escondidas */
	$alumno_primernombre    = $campo('alumno_nombre1');
	$alumno_apellidopaterno = $campo('alumno_apellido1');
	$alumno_anio            = preg_match('/^\d{4}$/', $campo('alumno_anio')) ? $campo('alumno_anio') : '';

	/* Filtro inicial por la ruta: alumnoList/{sede}/{estado}/. Lo usan las
	   tarjetas del Dashboard («Alumnos activos» de una sede) para abrir la
	   lista ya filtrada. Sólo vale al entrar: con el formulario enviado
	   mandan sus campos. La sede no amplía nada: condicionSede() la cruza
	   con las sedes de la sesión. */
	$sedeDeRuta   = ctype_digit((string)($url[1] ?? '')) ? (int)$url[1] : 0;
	$estadoDeRuta = in_array(strtoupper((string)($url[2] ?? '')), ['A', 'I', 'T'], true) ? strtoupper($url[2]) : '';
	$conFormulario = isset($_GET['alumno_estado']);   /* la búsqueda va por GET: «Volver» desde una ficha la recupera sin «Confirmar reenvío» */

	/* Estado: el listado muestra los ACTIVOS salvo que se pida otra cosa. */
	$alumno_estado = in_array($campo('alumno_estado'), ['A', 'I', 'T'], true) ? $campo('alumno_estado')
	               : (!$conFormulario && $estadoDeRuta !== '' ? $estadoDeRuta : 'A');

	/* Sede: «Todas» se ofrece a administración y a quien tiene más de una;
	   para el resto, «todas» son las suyas (el servidor lo acota en
	   condicionSede). Al abrir la pantalla, todas: antes no salía ningún
	   alumno hasta pulsar Buscar. */
	$alumno_sedeid = isset($_GET['alumno_sedeid']) ? (int)$campo('alumno_sedeid') : (!$conFormulario ? $sedeDeRuta : 0);
	$opcionesSede = $insAlumno->listarSedebusqueda($alumno_sedeid, $_SESSION['rol'], $_SESSION['usuario']);
	$ofrecerTodas = sedes_de_la_sesion() === null || substr_count($opcionesSede, '<option') > 1;

	$categoria = $alumno_anio === '' ? 0 : (int)$alumno_anio;

	/* Posición de juego: ya no se filtra por ella (2026-11-27: salió del
	   formulario y del resumen del resultado). La columna sigue en la tabla. */
	$alumno_posicion = '';

	/* Horario asignado: '' todos, 'S' con horario, 'N' sin horario. */
	$HORARIOS = ['' => 'Todos', 'S' => 'Con horario', 'N' => 'Sin horario'];
	$alumno_horario = isset($HORARIOS[$campo('alumno_horario')]) ? $campo('alumno_horario') : '';

	$porPosicion = [];
	$porHorario  = [];
	$filas = $insAlumno->listarAlumnos($alumno_identificacion, $alumno_apellidopaterno, $alumno_primernombre, $alumno_anio, $alumno_sedeid, $alumno_estado, $alumno_posicion, $porPosicion, $alumno_horario, $porHorario);

	$total = substr_count($filas, '<tr>');

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$ESTADOS = ['A' => 'Activos', 'I' => 'Inactivos', 'T' => 'Todos'];

	/* En el celular los criterios van plegados y su cabecera dice lo que se
	   está buscando: «JIPIRO · Activos · 2014». $filtrosExtra cuenta los que
	   se apartan de lo de siempre (todas las sedes, activos). */
	$nombreSede = 'Todas las sedes';
	if ($alumno_sedeid !== 0 && preg_match('/<option[^>]*value=["\']?' . $alumno_sedeid . '["\'\s>][^>]*>([^<]*)</', $opcionesSede, $mSede)) {
		$nombreSede = trim(html_entity_decode($mSede[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}
	$resumenFiltros = [$nombreSede, $ESTADOS[$alumno_estado]];
	if ($alumno_anio !== '')            { $resumenFiltros[] = $alumno_anio; }
	if ($alumno_apellidopaterno !== '') { $resumenFiltros[] = $alumno_apellidopaterno; }
	if ($alumno_primernombre !== '')    { $resumenFiltros[] = $alumno_primernombre; }
	if ($alumno_horario !== '')         { $resumenFiltros[] = $HORARIOS[$alumno_horario]; }
	$filtrosExtra = ($alumno_sedeid !== 0) + ($alumno_estado !== 'A') + ($alumno_anio !== '') + ($alumno_apellidopaterno !== '')
	              + ($alumno_primernombre !== '') + ($alumno_horario !== '');
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Alumnos';
	$extras      = array (0 => 'datatables',1 => 'swal',);
	require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">
      <?php require_once "app/views/inc/navbar.php"; ?>
      <?php require_once "app/views/inc/main-sidebar.php"; ?>

      <div class="app-main">

		<div class="app-content-header">
			<div class="container-fluid">
				<div class="row mb-2 g-2 align-items-center">
					<div class="col-sm-7">
						<h4 class="m-0">Alumnos</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Alumnos</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Criterios de búsqueda =================== -->
				<form action="<?php echo APP_URL."alumnoList/" ?>" method="GET" autocomplete="off" class="card ds-alumnos__filtros" id="alumnosFiltros">
					<div class="card-header">
						<?php /* Celular y tableta: la cabecera es un botón que despliega los
						         criterios y, plegada, dice qué se está buscando. */ ?>
						<button type="button" class="ds-alumnos__filtros-toggle d-lg-none" id="alumnosFiltrosToggle" aria-expanded="false" aria-controls="alumnosFiltrosCuerpo">
							<span class="ds-alumnos__filtros-titulo">
								<i class="fas fa-filter" aria-hidden="true"></i>Criterios de búsqueda
								<?php if ($filtrosExtra > 0): ?><span class="badge text-bg-primary"><?php echo $filtrosExtra; ?></span><?php endif; ?>
							</span>
							<span class="ds-alumnos__filtros-resumen"><?php echo $h(implode(' · ', $resumenFiltros)); ?></span>
							<i class="fas fa-chevron-down ds-alumnos__filtros-flecha" aria-hidden="true"></i>
						</button>
						<h3 class="card-title d-none d-lg-block"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<div class="card-tools d-none d-lg-block">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body" id="alumnosFiltrosCuerpo">
						<?php /* Orden pedido (2026-11-27): Sede, Estado, Año, Apellido,
						         Nombre y Horario. Identificación sale del formulario: la
						         cédula se busca con «Filtrar la lista…». */ ?>
						<div class="row g-3">
							<div class="col-12 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_sedeid" class="form-label">Sede</label>
								<select class="form-select" id="alumno_sedeid" name="alumno_sedeid">
									<?php if ($ofrecerTodas): ?>
										<option value="0" <?php echo $alumno_sedeid === 0 ? 'selected' : ''; ?>>Todas</option>
									<?php endif; ?>
									<?php echo $opcionesSede; ?>
								</select>
							</div>
							<div class="col-6 col-sm-3 col-lg-2 col-xl">
								<label for="alumno_estado" class="form-label">Estado</label>
								<select class="form-select" id="alumno_estado" name="alumno_estado">
									<?php foreach ($ESTADOS as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>" <?php echo $alumno_estado === $valor ? 'selected' : ''; ?>><?php echo $texto; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-sm-3 col-lg-2 col-xl-1">
								<label for="alumno_anio" class="form-label">Año</label>
								<input type="text" class="form-control" id="alumno_anio" name="alumno_anio" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" placeholder="2014" value="<?php echo $h($alumno_anio); ?>">
							</div>
							<div class="col-6 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_apellido1" class="form-label">Apellido paterno</label>
								<input type="text" class="form-control" id="alumno_apellido1" name="alumno_apellido1" placeholder="Primer apellido" value="<?php echo $h($alumno_apellidopaterno); ?>">
							</div>
							<div class="col-6 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_nombre1" class="form-label">Primer nombre</label>
								<input type="text" class="form-control" id="alumno_nombre1" name="alumno_nombre1" placeholder="Primer nombre" value="<?php echo $h($alumno_primernombre); ?>">
							</div>
							<div class="col-6 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_horario" class="form-label">Horario</label>
								<select class="form-select" id="alumno_horario" name="alumno_horario">
									<?php foreach ($HORARIOS as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>" <?php echo $alumno_horario === $valor ? 'selected' : ''; ?>><?php echo $texto; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-sm-6 col-lg-4 col-xl-auto d-flex align-items-end">
								<div class="w-100">
									<?php echo ds_boton_buscar(); ?>
								</div>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resultado de la búsqueda =================== -->
				<div class="card ds-crud__lista" id="alumnos-resultado">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-user-graduate me-2" aria-hidden="true"></i>Resultado de la búsqueda
							<span class="badge text-bg-secondary ms-1" title="<?php echo $ESTADOS[$alumno_estado]; ?>"><?php echo $total; ?></span>
						</h3>
						<div class="ds-alumnos__herramientas">
							<a href="<?php echo APP_URL . 'alumnoListaPDF/' . $categoria . '/' . $alumno_sedeid . '/' . $alumno_estado . '/'; ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener">
								<i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir
							</a>
							<a href="<?php echo APP_URL; ?>representanteList/" class="btn btn-primary btn-sm" title="Se registra desde su representante">
								<i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo alumno
							</a>
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<?php
						/*
						| Barra del resultado: sólo «Sin horario» (2026-11-27; antes
						| también «Con horario» y «Por posición»), en la MISMA fila que
						| «Mostrar N» (DataTables la coloca ahí: layout.topStart, abajo).
						| Es lo que hay que resolver: filtra con el formulario de
						| búsqueda y, pulsado otra vez, vuelve a «Todos».
						*/
						$sinHorario = (int)$porHorario['N'];
						$elegido    = $alumno_horario === 'N';
						?>
						<?php if ($sinHorario > 0 || $elegido): ?>
						<div class="ds-alumnos__barra" id="alumnosBarra">
							<div class="ds-alumnos__grupo">
								<button type="button" class="btn btn-sm <?php echo $elegido ? 'btn-primary' : 'btn-outline-warning ds-alumnos__chip-alerta'; ?> js-horario"
								        data-horario="<?php echo $elegido ? '' : 'N'; ?>" aria-pressed="<?php echo $elegido ? 'true' : 'false'; ?>"
								        title="<?php echo $elegido ? 'Ver todos' : 'Ver solo los alumnos sin horario'; ?>">
									<i class="fas fa-exclamation-triangle me-1" aria-hidden="true"></i>Sin horario<span class="badge <?php echo $elegido ? 'text-bg-light' : 'text-bg-secondary'; ?> ms-1"><?php echo $sinHorario; ?></span>
								</button>
							</div>
						</div>
						<?php endif; ?>
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Alumno</th>
									<th>Nacimiento</th>
									<th class="ds-col-opcional">Sede</th>
									<th>Posición</th>
									<th>Horario</th>
									<th>Estado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filas; ?>
							</tbody>
						</table>
					</div>
				</div>

			</div><!-- /.container-fluid -->
		</section>

		<?php /* Un solo formulario para las acciones de todas las filas
		         (activar/inactivar y eliminar): el botón pone la acción y el
		         id y lo envía por el mismo camino de ajax.js —confirmación,
		         envío y respuesta—, como en representanteList. */ ?>
		<form class="FormularioAjax d-none" id="formAccionAlumno" action="<?php echo APP_URL; ?>app/ajax/alumnoAjax.php" method="POST" autocomplete="off">
			<input type="hidden" name="modulo_alumno" id="accionAlumno" value="">
			<input type="hidden" name="alumno_id" id="idAlumno" value="">
		</form>

      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive ni los botones de copiar/exportar -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/*
		| Sin la extensión Responsive (escondía columnas y botones tras un ▶)
		| y sin la barra de copiar/CSV/Excel/PDF: el listado se imprime con el
		| botón «Imprimir» (PDF con cabecera de la escuela). En el teléfono
		| cada fila es una ficha (futbol.css, .ds-crud).
		|
		| «Mostrar N» elige cuántos alumnos por página y se recuerda en este
		| navegador. Si el almacenamiento no está disponible (modo privado),
		| simplemente vuelve a 25.
		*/
		var CLAVE = 'ds-alumnos-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
		try { var guardado = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(guardado) !== -1) { largo = guardado; } } catch (e) {}

		/* «Mostrar N» y «Sin horario» en la misma fila: la barra de
		   resumen se entrega a DataTables como un elemento más de la celda
		   superior izquierda (layout.topStart), detrás de «Mostrar». */
		var barra = document.getElementById('alumnosBarra');

		var tabla = new DataTable("#example1", {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [[0, 'asc']],               /* por apellido (data-order) */
			columnDefs: [
				{ targets: 1, type: 'string' },  /* data-order: fecha ISO */
				{ targets: 6, orderable: false, searchable: false }   /* acciones */
			],
			layout: {
				topStart: barra ? ['pageLength', barra] : 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "No hay alumnos con estos criterios",
				info: "_START_ a _END_ de _TOTAL_ alumnos",
				infoEmpty: "Sin alumnos",
				infoFiltered: "(de _MAX_)",
				lengthMenu: "Mostrar _MENU_",
				search: "",
				searchPlaceholder: "Filtrar la lista…",   /* nombre, cédula, sede, posición u horario */
				zeroRecords: "Ningún alumno coincide con el filtro",
				paginate: { next: "Siguiente", previous: "Anterior" },
				aria: {
					sortAscending: ": ordenar de forma ascendente",
					sortDescending: ": ordenar de forma descendente"
				}
			}
		});
		tabla.on('length', function (e, s, nuevo) {
			try { localStorage.setItem(CLAVE, String(nuevo)); } catch (err) {}
		});

		/* Criterios plegados en el celular: la cabecera los despliega. */
		var filtros = document.getElementById('alumnosFiltros');
		var toggle  = document.getElementById('alumnosFiltrosToggle');
		toggle.addEventListener('click', function () {
			var abierto = filtros.classList.toggle('is-abierto');
			toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
		});

		/* «Sin horario»: filtra con el mismo formulario de búsqueda, así se
		   conservan sede, año y estado. */
		document.querySelectorAll('.js-horario').forEach(function (b) {
			b.addEventListener('click', function () {
				var sel = document.getElementById('alumno_horario');
				sel.value = b.dataset.horario || '';
				sel.form.requestSubmit();
			});
		});

		/* Delegado en el documento: DataTables rehace las filas al paginar. */
		document.addEventListener('click', function (e) {
			var b = e.target.closest('.js-alumno');
			if (!b) { return; }
			document.getElementById('accionAlumno').value = b.dataset.accion || '';
			document.getElementById('idAlumno').value     = b.dataset.id     || '';
			document.getElementById('formAccionAlumno').requestSubmit();
		});
	});
	</script>
  </body>
</html>
