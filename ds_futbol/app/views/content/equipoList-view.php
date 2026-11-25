<?php
/*
| Fútbol › Torneos › Equipos de un torneo (rediseño 2026-11-25)
|
| · Criterios de búsqueda: texto (equipo, categoría o profesor), categoría,
|   sede, profesor y estado. Las opciones son las de los equipos del torneo.
| · Resumen: equipos, activos, jugadores inscritos y equipos sin valor de
|   inscripción.
| · Lista en fichas (.ds-crud, futbol.css) con DataTables; columna Jugadores.
| · Registrar y editar en un modal (a pantalla completa en el celular). Se
|   guarda con equipoAjax.php como antes. «Editar» sigue siendo un enlace a
|   /equipoList/{torneo}/{equipo}/: con JavaScript abre el modal, y esa
|   dirección abre el modal de ese equipo.
*/
use app\controllers\equipoController;
$insEquipo = new equipoController();

$equipo_torneoid = (int)(($url[1] ?? "") != "" ? $url[1] : 0);
$abrirId         = (int)(($url[2] ?? "") != "" ? $url[2] : 0);

/* Sin un torneo válido no hay a qué inscribir equipos: a la lista de torneos. */
$torneo_nombre = '';
if ($equipo_torneoid > 0) {
	$nombreTorneo = $insEquipo->BuscarTorneoEquipo($equipo_torneoid);
	if ($nombreTorneo->rowCount() == 1) {
		$torneo_nombre = $nombreTorneo->fetch()['torneo_nombre'];
	}
}
if ($torneo_nombre === '') {
	header("Location: " . APP_URL . "torneoList/");
	exit();
}

/* limpiarHtml() guarda escapado; lo heredado está en crudo. */
$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

$puedeEditar = puede_editar('torneoList');
$puedeCrear  = puede_crear('torneoList');   /* la guardia de equipoAjax exige crear/editar en torneoList */
$urlLista    = APP_URL . 'equipoList/' . $equipo_torneoid . '/';

/* Criterios */
$opciones   = $insEquipo->opcionesFiltroEquipos($equipo_torneoid);
$q          = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$categoriaF = in_array($_GET['categoria'] ?? '', $opciones['categorias'], true) ? $_GET['categoria'] : '';
$sedeF      = isset($opciones['sedes'][$_GET['sede'] ?? '']) ? (string)$_GET['sede'] : '';
$profesorF  = isset($opciones['profesores'][(int)($_GET['profesor'] ?? 0)]) ? (int)$_GET['profesor'] : 0;
$estadoF    = in_array($_GET['estado'] ?? '', ['A', 'I'], true) ? $_GET['estado'] : '';
$filtrosExtra = ($categoriaF !== '') + ($sedeF !== '') + ($profesorF > 0) + ($estadoF !== '');
$filtros    = $filtrosExtra + ($q !== '');

$resumen = [];
$filasEquipos = $insEquipo->listarEquiposTorneo($equipo_torneoid, ['q' => $q, 'categoria' => $categoriaF, 'sede' => $sedeF, 'profesor' => $profesorF, 'estado' => $estadoF], $resumen);

/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
$tituloVista = 'Equipos';
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
				<div class="eq-cabecera">
					<div class="min-w-0">
						<a href="<?php echo APP_URL; ?>torneoList/" class="small text-decoration-none eq-volver"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Torneos</a>
						<h4 class="m-0">Equipos</h4>
						<div class="small text-body-secondary text-truncate"><i class="fas fa-trophy me-1" aria-hidden="true"></i><?php echo $h($torneo_nombre); ?></div>
					</div>
					<?php if ($puedeCrear): ?>
						<button type="button" class="btn btn-primary eq-nuevo" data-eq-nuevo>
							<i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo equipo
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Criterios de búsqueda =================== -->
				<form method="GET" action="<?php echo $h($urlLista); ?>" autocomplete="off" class="card mb-3 eq-filtros" id="eqFiltros">
					<div class="card-header eq-filtros__cab">
						<h3 class="card-title mb-0"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<button type="button" class="btn btn-sm btn-outline-secondary d-md-none eq-filtros__plegar" data-bs-toggle="collapse" data-bs-target="#eqFiltrosCuerpo"
						        aria-expanded="<?php echo $filtrosExtra ? 'true' : 'false'; ?>" aria-controls="eqFiltrosCuerpo">
							<i class="fas fa-sliders me-1" aria-hidden="true"></i><span><?php echo $filtrosExtra ? 'Filtros (' . $filtrosExtra . ')' : 'Filtros'; ?></span>
							<i class="fas fa-chevron-down ms-1" aria-hidden="true"></i>
						</button>
					</div>
					<div class="card-body pb-2">
						<div class="row g-2 g-md-3">
							<div class="col-12 col-md-8 col-xl-3">
								<label for="f_q" class="form-label">Buscar equipo</label>
								<input type="search" class="form-control" id="f_q" name="q" maxlength="80" placeholder="Equipo, categoría o profesor" value="<?php echo $h($q); ?>">
							</div>
							<div class="collapse eq-filtros__extra<?php echo $filtrosExtra ? ' show' : ''; ?>" id="eqFiltrosCuerpo">
							<div class="row g-2 g-md-3 mt-0 eq-filtros__extra">
								<div class="col-6 col-md-4 col-xl">
									<label for="f_categoria" class="form-label">Categoría</label>
									<select class="form-select" id="f_categoria" name="categoria">
										<option value="">Todas</option>
										<?php foreach ($opciones['categorias'] as $c): ?>
											<option value="<?php echo $h($c); ?>" <?php echo $c === $categoriaF ? 'selected' : ''; ?>><?php echo $h($c); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="f_sede" class="form-label">Sede</label>
									<select class="form-select" id="f_sede" name="sede">
										<option value="">Todas</option>
										<?php foreach ($opciones['sedes'] as $v => $n): ?>
											<option value="<?php echo $h($v); ?>" <?php echo (string)$v === $sedeF ? 'selected' : ''; ?>><?php echo $h($n); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="f_profesor" class="form-label">Profesor</label>
									<select class="form-select" id="f_profesor" name="profesor">
										<option value="">Todos</option>
										<?php foreach ($opciones['profesores'] as $v => $n): ?>
											<option value="<?php echo (int)$v; ?>" <?php echo (int)$v === $profesorF ? 'selected' : ''; ?>><?php echo $h($n); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="f_estado" class="form-label">Estado</label>
									<select class="form-select" id="f_estado" name="estado">
										<option value="">Todos</option>
										<option value="A" <?php echo $estadoF === 'A' ? 'selected' : ''; ?>>Activos</option>
										<option value="I" <?php echo $estadoF === 'I' ? 'selected' : ''; ?>>Inactivos</option>
									</select>
								</div>
							</div>
							</div>
							<div class="col-12 col-md-4 col-xl-auto d-flex align-items-end gap-2">
								<div class="flex-grow-1"><?php echo ds_boton_buscar(); ?></div>
								<?php if ($filtros): ?>
									<a href="<?php echo $h($urlLista); ?>" class="btn btn-outline-secondary" title="Quitar los filtros">
										<i class="fas fa-eraser" aria-hidden="true"></i><span class="visually-hidden">Limpiar</span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resumen =================== -->
				<div class="eq-kpis mb-3">
					<div class="eq-kpi eq-kpi--total">
						<span class="eq-kpi__et">Equipos</span>
						<strong class="eq-kpi__val"><?php echo (int)$resumen['total']; ?></strong>
						<span class="eq-kpi__nota"><?php echo $filtros ? 'con estos criterios' : 'en el torneo'; ?></span>
					</div>
					<div class="eq-kpi">
						<span class="eq-kpi__et">Activos</span>
						<strong class="eq-kpi__val text-success"><?php echo (int)$resumen['activos']; ?></strong>
						<span class="eq-kpi__nota"><?php echo (int)$resumen['total'] - (int)$resumen['activos']; ?> inactivos</span>
					</div>
					<div class="eq-kpi">
						<span class="eq-kpi__et">Jugadores inscritos</span>
						<strong class="eq-kpi__val"><?php echo (int)$resumen['jugadores']; ?></strong>
						<span class="eq-kpi__nota">en estos equipos</span>
					</div>
					<div class="eq-kpi <?php echo $resumen['sinValor'] ? 'eq-kpi--aviso' : ''; ?>">
						<span class="eq-kpi__et">Sin valor de inscripción</span>
						<strong class="eq-kpi__val"><?php echo (int)$resumen['sinValor']; ?></strong>
						<span class="eq-kpi__nota"><?php echo $resumen['sinValor'] ? 'no se les puede cobrar aún' : 'todos definidos'; ?></span>
					</div>
				</div>

				<!-- =================== Equipos =================== -->
				<div class="card ds-crud__lista" id="equipos-inscritos">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-users me-2" aria-hidden="true"></i>Equipos inscritos
							<span class="badge text-bg-secondary ms-1"><?php echo (int)$resumen['total']; ?></span>
						</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Equipo</th>
									<th>Profesor a cargo</th>
									<th class="text-center">Jugadores</th>
									<th class="ds-col-opcional">Inscripción</th>
									<th>Estado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filasEquipos; ?>
							</tbody>
						</table>
					</div>
				</div>

				<?php if ($puedeCrear): ?>
					<button type="button" class="btn btn-primary eq-fab d-md-none" data-eq-nuevo aria-label="Nuevo equipo"><i class="fas fa-plus" aria-hidden="true"></i></button>
				<?php endif; ?>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<?php if ($puedeCrear || $puedeEditar): ?>
	<!-- =================== Registrar / editar =================== -->
	<div class="modal fade" id="modalEquipo" tabindex="-1" aria-labelledby="modalEquipoTitulo" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
			<form class="modal-content FormularioAjax" id="equipo-form" action="<?php echo APP_URL; ?>app/ajax/equipoAjax.php" method="POST"
			      autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_equipo" id="eq_modulo" value="registrar">
				<input type="hidden" name="equipo_torneoid" value="<?php echo $equipo_torneoid; ?>">
				<input type="hidden" name="equipo_id" id="eq_id" value="0">
				<div class="modal-header">
					<h5 class="modal-title" id="modalEquipoTitulo"><i class="fas fa-shield-halved me-2" aria-hidden="true"></i><span>Nuevo equipo</span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<p class="small text-body-secondary mb-3"><i class="fas fa-trophy me-1" aria-hidden="true"></i><?php echo $h($torneo_nombre); ?></p>
					<div class="row g-3">
						<div class="col-12 col-md-7">
							<label for="equipo_nombre" class="form-label">Nombre del equipo <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="equipo_nombre" name="equipo_nombre" maxlength="50" required>
							<div class="invalid-feedback">Escriba el nombre del equipo.</div>
						</div>
						<div class="col-12 col-md-5">
							<label for="equipo_categoria" class="form-label">Categoría <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="equipo_categoria" name="equipo_categoria" maxlength="50" required placeholder="Ej.: Sub 12 o 2014-2015" list="eqCategorias">
							<datalist id="eqCategorias">
								<?php foreach ($opciones['categorias'] as $c): ?><option value="<?php echo $h($c); ?>"></option><?php endforeach; ?>
							</datalist>
							<div class="invalid-feedback">Escriba la categoría.</div>
						</div>
						<div class="col-12 col-md-6">
							<?php /* «Todas las sedes» (T): un equipo con niños de todas las sedes
							         (se guarda como sede 0). La ofrece listarOptionSede a quien
							         ve todas las sedes. */ ?>
							<label for="equipo_sedeid" class="form-label">Sede <span class="text-danger">*</span></label>
							<select class="form-select" id="equipo_sedeid" name="equipo_sedeid" required aria-describedby="equipo_sede_ayuda">
								<option value="">Seleccione una sede</option>
								<?php echo $insEquipo->listarOptionSede(null); ?>
							</select>
							<div class="invalid-feedback">Elija la sede.</div>
							<div class="form-text" id="equipo_sede_ayuda">«Todas las sedes» si el equipo junta niños de varias sedes.</div>
						</div>
						<div class="col-12 col-md-6">
							<label for="equipo_profesorid" class="form-label">Profesor a cargo <span class="text-danger">*</span></label>
							<select class="form-select" id="equipo_profesorid" name="equipo_profesorid" required>
								<option value="">Seleccione un profesor</option>
								<?php echo $insEquipo->listarResponsable(0); ?>
							</select>
							<div class="invalid-feedback">Elija el profesor.</div>
						</div>
						<div class="col-12 col-md-6">
							<label for="equipo_valorinscripcion" class="form-label">Inscripción por alumno</label>
							<div class="input-group has-validation">
								<span class="input-group-text">$</span>
								<input type="text" class="form-control" id="equipo_valorinscripcion" name="equipo_valorinscripcion"
								       inputmode="decimal" maxlength="9" pattern="\d{1,6}([.,]\d{1,2})?" placeholder="0.00" aria-describedby="equipo_valor_ayuda">
								<div class="invalid-feedback">Un valor como 25 o 25.50.</div>
							</div>
							<div class="form-text" id="equipo_valor_ayuda">Lo que se cobra a cada alumno que juega con este equipo. Vacío si aún no está definido.</div>
						</div>
						<div class="col-12 col-md-6">
							<label for="equipo_foto" class="form-label">Escudo <span class="text-body-secondary small">(JPG o PNG, hasta 4 MB)</span></label>
							<div class="eq-foto">
								<img src="" alt="" class="eq-foto__img" id="eqFotoVista" hidden>
								<div class="eq-foto__vacio" id="eqFotoVacio" aria-hidden="true"><i class="fas fa-shield-halved"></i></div>
								<div class="flex-grow-1 min-w-0">
									<input type="file" class="form-control" name="equipo_foto" id="equipo_foto" accept="image/jpeg,image/png">
									<div class="form-text" id="eqFotoAyuda">Opcional.</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer eq-modal__pie">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1" aria-hidden="true"></i><span id="eqGuardarTxt">Registrar equipo</span></button>
				</div>
			</form>
		</div>
	</div>
	<?php endif; ?>

	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/* Sin el plugin responsive: en el teléfono cada fila es una ficha
		   (futbol.css, .ds-crud). El filtro de la tabla busca en lo cargado;
		   los criterios de búsqueda, en el servidor. */
		var tabla = new DataTable("#example1", {
			autoWidth: false,
			lengthChange: false,
			pageLength: 10,
			order: [],                      /* el del servidor: categoría y nombre */
			columnDefs: [
				{ targets: 2, type: 'num', className: 'text-center', searchable: false },
				{ targets: 3, type: 'num' },
				{ targets: 4, type: 'string' },
				{ targets: 5, orderable: false, searchable: false }
			],
			layout: {
				topStart: null,
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: <?php echo json_encode($filtros ? 'Ningún equipo coincide con los criterios de búsqueda' : 'Este torneo aún no tiene equipos inscritos'); ?>,
				info: "_START_ a _END_ de _TOTAL_ equipos",
				infoEmpty: "Sin equipos",
				infoFiltered: "(filtrados de _MAX_)",
				search: "",
				searchPlaceholder: "Filtrar en la tabla…",
				zeroRecords: "Ningún equipo coincide con el filtro",
				paginate: { next: "Siguiente", previous: "Anterior" },
				aria: { sortAscending: ": ordenar de forma ascendente", sortDescending: ": ordenar de forma descendente" }
			}
		});

		/* Los selectores de los criterios filtran al elegirlos. */
		['f_categoria', 'f_sede', 'f_profesor', 'f_estado'].forEach(function (id) {
			var s = document.getElementById(id);
			if (s) { s.addEventListener('change', function () { s.form.submit(); }); }
		});

		var modalEl = document.getElementById('modalEquipo');
		if (!modalEl) { return; }
		var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
		var form  = document.getElementById('equipo-form');
		var $ = function (id) { return document.getElementById(id); };

		var foto = $('equipo_foto'), vista = $('eqFotoVista'), vacio = $('eqFotoVacio'), ayuda = $('eqFotoAyuda');
		function mostrarFoto(url) {
			vista.hidden = !url; vacio.hidden = !!url;
			if (url) { vista.src = url; } else { vista.removeAttribute('src'); }
		}
		foto.addEventListener('change', function () {
			var f = foto.files && foto.files[0];
			if (!f) { return; }
			if (f.size > 4000 * 1024) { foto.value = ''; ayuda.innerHTML = '<span class="text-danger">La imagen supera los 4 MB: elija otra.</span>'; return; }
			mostrarFoto(URL.createObjectURL(f)); ayuda.textContent = f.name;
		});

		/* Elige el valor; si no está (un profesor ya inactivo, una sede que
		   no es de las suyas), lo añade para no perderlo al guardar. */
		function elegir(select, valor, texto) {
			if (!valor) { select.value = ''; return; }
			if (![].some.call(select.options, function (o) { return o.value === valor; })) {
				var o = document.createElement('option'); o.value = valor; o.textContent = texto || valor; select.appendChild(o);
			}
			select.value = valor;
		}

		/* Abrir: vacío para registrar, o con los datos de la fila para editar. */
		function abrir(d) {
			form.reset();
			form.classList.remove('was-validated');
			var editar = !!(d && d.id);
			d = d || {};
			$('eq_modulo').value = editar ? 'actualizar' : 'registrar';
			$('eq_id').value = editar ? d.id : 0;
			modalEl.querySelector('.modal-title span').textContent = editar ? 'Editar equipo' : 'Nuevo equipo';
			$('eqGuardarTxt').textContent = editar ? 'Guardar cambios' : 'Registrar equipo';
			$('equipo_nombre').value = d.nombre || '';
			$('equipo_categoria').value = d.categoria || '';
			elegir($('equipo_sedeid'), d.sede || '', d.sede === 'T' ? 'Todas las sedes' : '');
			elegir($('equipo_profesorid'), d.profesor && d.profesor !== '0' ? d.profesor : '', '');
			$('equipo_valorinscripcion').value = d.valor || '';
			mostrarFoto(d.foto || '');
			ayuda.textContent = editar && d.foto ? 'Elija otro sólo si quiere reemplazar el actual.' : 'Opcional.';
			modal.show();
		}
		modalEl.addEventListener('shown.bs.modal', function () {
			if (window.matchMedia('(min-width: 768px)').matches) { $('equipo_nombre').focus(); }
		});

		document.querySelectorAll('[data-eq-nuevo]').forEach(function (b) { b.addEventListener('click', function () { abrir(null); }); });
		/* «Editar» es un enlace a /equipoList/{torneo}/{equipo}/; con JavaScript
		   abre el modal. Delegado: DataTables vuelve a pintar al paginar. */
		document.getElementById('example1').addEventListener('click', function (e) {
			var a = e.target.closest('.eq-editar');
			if (!a || !a.getAttribute('data-equipo') || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
			e.preventDefault();
			abrir(JSON.parse(a.getAttribute('data-equipo')));
		});

		/* Validación antes de la confirmación de ajax.js (que escucha el
		   mismo submit): si falta algo, se marca y no se envía. */
		form.addEventListener('submit', function (e) {
			if (!form.checkValidity()) {
				e.preventDefault(); e.stopImmediatePropagation();
				form.classList.add('was-validated');
				var malo = form.querySelector(':invalid'); if (malo) { malo.focus(); }
			}
		}, true);

		/* /equipoList/{torneo}/{equipo}/: abre ese equipo (aunque esté en otra página). */
		var abrirId = <?php echo (int)$abrirId; ?>;
		if (abrirId) {
			var b = tabla.rows().nodes().toArray().map(function (tr) { return tr.querySelector('.eq-editar'); })
				.filter(function (x) { return x && x.getAttribute('data-equipo') && JSON.parse(x.getAttribute('data-equipo')).id === abrirId; })[0];
			if (b) { abrir(JSON.parse(b.getAttribute('data-equipo'))); }
		}
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>"></script>

	<style>
		.eq-cabecera { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
		.eq-volver { display: inline-block; margin-bottom: .15rem; }
		.min-w-0 { min-width: 0; }

		.eq-filtros__cab { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.eq-filtros .form-label { font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: .25rem; }
		.eq-filtros__plegar { white-space: nowrap; flex: 0 0 auto; }
		.eq-filtros__plegar[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
		@media (min-width: 768px) { .eq-filtros__extra { display: contents !important; } }

		.eq-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
		.eq-kpi { display: flex; flex-direction: column; gap: .1rem; padding: .8rem 1rem; border-radius: var(--ds-radius-sm, 10px);
		          background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); min-width: 0; }
		.eq-kpi__et { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); font-weight: 600; }
		.eq-kpi__val { font-size: 1.45rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
		.eq-kpi__nota { font-size: .8rem; color: var(--bs-secondary-color); }
		.eq-kpi--total { border-top: 3px solid var(--bs-primary); }
		.eq-kpi--aviso { border-top: 3px solid var(--bs-warning); }
		.eq-jugadores .badge { font-size: .85rem; min-width: 2rem; }
		@media (min-width: 992px) { #equipos-inscritos td.eq-jugadores { text-align: center; } }

		.eq-foto { display: flex; gap: .75rem; align-items: center; }
		.eq-foto__img, .eq-foto__vacio { width: 64px; height: 64px; flex: 0 0 auto; border-radius: 8px; object-fit: contain; border: 1px solid var(--bs-border-color); background: #fff; }
		.eq-foto__vacio { display: grid; place-items: center; background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); font-size: 1.3rem; }
		.eq-foto__vacio[hidden], .eq-foto__img[hidden] { display: none; }

		.eq-fab { position: fixed; right: 1rem; bottom: 1.25rem; z-index: 1030; width: 3.5rem; height: 3.5rem; border-radius: 50%;
		          font-size: 1.3rem; box-shadow: 0 .4rem 1rem rgba(0,0,0,.25); display: grid; place-items: center; padding: 0; }

		@media (max-width: 991.98px) { .eq-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
		@media (max-width: 767.98px) {
			.eq-nuevo { display: none; }
			.eq-kpi { padding: .65rem .8rem; }
			.eq-kpi__val { font-size: 1.2rem; }
			.app-content { padding-bottom: 5rem; }
			.eq-modal__pie { flex-direction: column-reverse; align-items: stretch; }
			.eq-modal__pie .btn { width: 100%; margin: 0; min-height: 2.9rem; }
		}
	</style>
  </body>
</html>
