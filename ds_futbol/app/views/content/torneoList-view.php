<?php
/*
| Fútbol › Torneos (rediseño 2026-11-25)
|
| · Criterios de búsqueda: año, estado, en curso/próximos/finalizados,
|   ciudad y texto (nombre, lugar u organizador).
| · Resumen: torneos, en curso, próximos y equipos inscritos.
| · Lista en fichas (.ds-crud, futbol.css) con DataTables para ordenar,
|   paginar y filtrar lo cargado.
| · Registrar y editar en un modal (a pantalla completa en el celular). Se
|   guarda con torneoAjax.php como antes. «Editar» sigue siendo un enlace a
|   /torneoList/{id}/: con JavaScript abre el modal, y esa dirección abre el
|   modal de ese torneo.
*/
use app\controllers\torneoController;
$insTorneo = new torneoController();

/* limpiarHtml() guarda escapado; lo heredado está en crudo: se decodifica y
   se escapa una sola vez. */
$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

$puedeCrear  = puede_crear('torneoList');
$puedeEditar = puede_editar('torneoList');

/* Enlace /torneoList/{id}/: abre ese torneo en el modal. */
$abrirId = (int)($url[1] ?? 0);

/* Criterios */
$anios    = $insTorneo->aniosTorneo();
$ciudades = $insTorneo->ciudadesTorneo();
$anioF    = isset($anios[(int)($_GET['anio'] ?? 0)]) ? (string)(int)$_GET['anio'] : '';
$estadoF  = in_array($_GET['estado'] ?? '', ['A', 'I'], true) ? $_GET['estado'] : '';
$CUANDO   = ['curso' => 'En curso', 'proximo' => 'Próximos', 'fin' => 'Finalizados'];
$cuandoF  = isset($CUANDO[$_GET['cuando'] ?? '']) ? $_GET['cuando'] : '';
$ciudadF  = in_array($_GET['ciudad'] ?? '', $ciudades, true) ? $_GET['ciudad'] : '';
$q        = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$filtrosExtra = ($anioF !== '') + ($estadoF !== '') + ($cuandoF !== '') + ($ciudadF !== '');
$filtros  = $filtrosExtra + ($q !== '');

$resumen = [];
$filasTorneos = $insTorneo->listarTorneos(['anio' => $anioF, 'estado' => $estadoF, 'cuando' => $cuandoF, 'ciudad' => $ciudadF, 'q' => $q], $resumen);

$enlace = static function (array $cambios) use ($anioF, $estadoF, $cuandoF, $ciudadF, $q): string {
	$p = array_filter(array_merge(['anio' => $anioF, 'estado' => $estadoF, 'cuando' => $cuandoF, 'ciudad' => $ciudadF, 'q' => $q], $cambios),
	                  static fn ($v) => $v !== '' && $v !== null);
	return APP_URL . 'torneoList/' . ($p ? '?' . http_build_query($p) : '');
};

/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
$tituloVista = 'Torneos';
$extras      = array (0 => 'datatables',);
require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">
      <?php require_once "app/views/inc/navbar.php"; ?>
      <?php require_once "app/views/inc/main-sidebar.php"; ?>

      <div class="app-main">
		<div class="app-content-header">
			<div class="container-fluid">
				<div class="tor-cabecera">
					<div>
						<h4 class="m-0">Torneos</h4>
						<div class="small text-body-secondary">Campeonatos en los que participa la escuela y sus equipos</div>
					</div>
					<?php if ($puedeCrear): ?>
						<button type="button" class="btn btn-primary tor-nuevo" data-tor-nuevo>
							<i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo torneo
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Criterios de búsqueda =================== -->
				<form method="GET" action="<?php echo APP_URL; ?>torneoList/" autocomplete="off" class="card mb-3 tor-filtros" id="torFiltros">
					<div class="card-header tor-filtros__cab">
						<h3 class="card-title mb-0"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<button type="button" class="btn btn-sm btn-outline-secondary d-md-none tor-filtros__plegar" data-bs-toggle="collapse" data-bs-target="#torFiltrosCuerpo"
						        aria-expanded="<?php echo $filtrosExtra ? 'true' : 'false'; ?>" aria-controls="torFiltrosCuerpo">
							<i class="fas fa-sliders me-1" aria-hidden="true"></i><span><?php echo $filtrosExtra ? 'Filtros (' . $filtrosExtra . ')' : 'Filtros'; ?></span>
							<i class="fas fa-chevron-down ms-1" aria-hidden="true"></i>
						</button>
					</div>
					<div class="card-body pb-2">
						<div class="row g-2 g-md-3">
							<div class="col-12 col-md-8 col-xl-3">
								<label for="f_q" class="form-label">Buscar torneo</label>
								<input type="search" class="form-control" id="f_q" name="q" maxlength="80" placeholder="Nombre, lugar u organizador" value="<?php echo $h($q); ?>">
							</div>
							<div class="collapse tor-filtros__extra<?php echo $filtrosExtra ? ' show' : ''; ?>" id="torFiltrosCuerpo">
							<div class="row g-2 g-md-3 mt-0 tor-filtros__extra">
								<div class="col-6 col-md-4 col-xl">
									<label for="f_anio" class="form-label">Año</label>
									<select class="form-select" id="f_anio" name="anio">
										<option value="">Todos</option>
										<?php foreach ($anios as $a => $n): ?>
											<option value="<?php echo (int)$a; ?>" <?php echo (string)$a === $anioF ? 'selected' : ''; ?>><?php echo (int)$a; ?> (<?php echo (int)$n; ?>)</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="f_cuando" class="form-label">Fechas</label>
									<select class="form-select" id="f_cuando" name="cuando">
										<option value="">Todas</option>
										<?php foreach ($CUANDO as $v => $t): ?>
											<option value="<?php echo $v; ?>" <?php echo $v === $cuandoF ? 'selected' : ''; ?>><?php echo $t; ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="f_ciudad" class="form-label">Ciudad</label>
									<select class="form-select" id="f_ciudad" name="ciudad">
										<option value="">Todas</option>
										<?php foreach ($ciudades as $c): ?>
											<option value="<?php echo $h($c); ?>" <?php echo $c === $ciudadF ? 'selected' : ''; ?>><?php echo $h($c); ?></option>
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
									<a href="<?php echo APP_URL; ?>torneoList/" class="btn btn-outline-secondary" title="Quitar los filtros">
										<i class="fas fa-eraser" aria-hidden="true"></i><span class="visually-hidden">Limpiar</span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resumen =================== -->
				<div class="tor-kpis mb-3">
					<div class="tor-kpi tor-kpi--total">
						<span class="tor-kpi__et">Torneos</span>
						<strong class="tor-kpi__val"><?php echo (int)$resumen['total']; ?></strong>
						<span class="tor-kpi__nota"><?php echo $filtros ? 'con estos criterios' : 'registrados'; ?></span>
					</div>
					<a class="tor-kpi" href="<?php echo $h($enlace(['cuando' => $cuandoF === 'curso' ? '' : 'curso'])); ?>" title="Ver sólo los que están en curso">
						<span class="tor-kpi__et">En curso</span>
						<strong class="tor-kpi__val text-warning-emphasis"><?php echo (int)$resumen['curso']; ?></strong>
						<span class="tor-kpi__nota">hoy</span>
					</a>
					<a class="tor-kpi" href="<?php echo $h($enlace(['cuando' => $cuandoF === 'proximo' ? '' : 'proximo'])); ?>" title="Ver sólo los próximos">
						<span class="tor-kpi__et">Próximos</span>
						<strong class="tor-kpi__val text-info-emphasis"><?php echo (int)$resumen['proximos']; ?></strong>
						<span class="tor-kpi__nota">aún no empiezan</span>
					</a>
					<div class="tor-kpi">
						<span class="tor-kpi__et">Equipos inscritos</span>
						<strong class="tor-kpi__val"><?php echo (int)$resumen['equipos']; ?></strong>
						<span class="tor-kpi__nota">en estos torneos</span>
					</div>
				</div>

				<!-- =================== Torneos =================== -->
				<div class="card ds-crud__lista" id="torneos-ingresados">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-trophy me-2" aria-hidden="true"></i>Torneos
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
									<th>Torneo</th>
									<th>Fechas</th>
									<th class="text-center">Equipos</th>
									<th class="ds-col-opcional">Organizador</th>
									<th>Estado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filasTorneos; ?>
							</tbody>
						</table>
					</div>
				</div>

				<?php if ($puedeCrear): ?>
					<button type="button" class="btn btn-primary tor-fab d-md-none" data-tor-nuevo aria-label="Nuevo torneo"><i class="fas fa-plus" aria-hidden="true"></i></button>
				<?php endif; ?>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<?php if ($puedeCrear || $puedeEditar): ?>
	<!-- =================== Registrar / editar =================== -->
	<div class="modal fade" id="modalTorneo" tabindex="-1" aria-labelledby="modalTorneoTitulo" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
			<form class="modal-content FormularioAjax" id="torneo-form" action="<?php echo APP_URL; ?>app/ajax/torneoAjax.php" method="POST"
			      autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_torneo" id="tor_modulo" value="registrar">
				<input type="hidden" name="torneo_id" id="tor_id" value="0">
				<div class="modal-header">
					<h5 class="modal-title" id="modalTorneoTitulo"><i class="fas fa-trophy me-2" aria-hidden="true"></i><span>Nuevo torneo</span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="row g-3">
						<div class="col-12">
							<label for="torneo_nombre" class="form-label">Nombre del torneo <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="torneo_nombre" name="torneo_nombre" maxlength="100" required placeholder="Por ejemplo: Copa Ciudad de Loja Sub-12">
							<div class="invalid-feedback">Escriba el nombre del torneo.</div>
						</div>
						<div class="col-12 col-md-5">
							<label for="torneo_ciudad" class="form-label">Ciudad <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="torneo_ciudad" name="torneo_ciudad" maxlength="50" required list="torCiudades">
							<datalist id="torCiudades">
								<?php foreach ($ciudades as $c): ?><option value="<?php echo $h($c); ?>"></option><?php endforeach; ?>
							</datalist>
							<div class="invalid-feedback">Escriba la ciudad.</div>
						</div>
						<div class="col-12 col-md-7">
							<label for="torneo_lugar" class="form-label">Lugar <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="torneo_lugar" name="torneo_lugar" maxlength="150" required placeholder="Estadio, complejo o cancha">
							<div class="invalid-feedback">Escriba el lugar.</div>
						</div>
						<div class="col-6 col-md-4">
							<label for="torneo_fechainicio" class="form-label">Inicio <span class="text-danger">*</span></label>
							<input type="date" class="form-control" name="torneo_fechainicio" id="torneo_fechainicio" required>
							<div class="invalid-feedback">Elija la fecha de inicio.</div>
						</div>
						<div class="col-6 col-md-4">
							<label for="torneo_fechafin" class="form-label">Finalización <span class="text-danger">*</span></label>
							<input type="date" class="form-control" name="torneo_fechafin" id="torneo_fechafin" required>
							<div class="invalid-feedback">No puede ser antes del inicio.</div>
						</div>
						<div class="col-12 col-md-4">
							<label for="torneo_organizador" class="form-label">Organizador</label>
							<input type="text" class="form-control" id="torneo_organizador" name="torneo_organizador" maxlength="100">
						</div>
						<div class="col-12">
							<label for="torneo_descripcion" class="form-label">Descripción</label>
							<textarea class="form-control" id="torneo_descripcion" name="torneo_descripcion" rows="2" maxlength="300" placeholder="Categorías, costo de inscripción, notas…"></textarea>
						</div>
						<div class="col-12">
							<label for="torneo_foto" class="form-label">Afiche o logo <span class="text-body-secondary small">(JPG o PNG, hasta 4 MB)</span></label>
							<div class="tor-foto">
								<img src="" alt="" class="tor-foto__img" id="torFotoVista" hidden>
								<div class="tor-foto__vacio" id="torFotoVacio" aria-hidden="true"><i class="fas fa-trophy"></i></div>
								<div class="flex-grow-1 min-w-0">
									<input type="file" class="form-control" name="torneo_foto" id="torneo_foto" accept="image/jpeg,image/png">
									<div class="form-text" id="torFotoAyuda">Opcional.</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer tor-modal__pie">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1" aria-hidden="true"></i><span id="torGuardarTxt">Registrar torneo</span></button>
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
			order: [[1, 'desc']],          /* los más recientes primero */
			columnDefs: [
				{ targets: [1, 4], type: 'string' },   /* data-order: fecha ISO y estado */
				{ targets: 2, type: 'num', className: 'text-center', searchable: false },
				{ targets: 5, orderable: false, searchable: false }
			],
			layout: {
				topStart: null,
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: <?php echo json_encode($filtros ? 'Ningún torneo coincide con los criterios de búsqueda' : 'Aún no hay torneos ingresados'); ?>,
				info: "_START_ a _END_ de _TOTAL_ torneos",
				infoEmpty: "Sin torneos",
				infoFiltered: "(filtrados de _MAX_)",
				search: "",
				searchPlaceholder: "Filtrar en la tabla…",
				zeroRecords: "Ningún torneo coincide con el filtro",
				paginate: { next: "Siguiente", previous: "Anterior" },
				aria: { sortAscending: ": ordenar de forma ascendente", sortDescending: ": ordenar de forma descendente" }
			}
		});

		/* Los selectores de los criterios filtran al elegirlos. */
		['f_anio', 'f_cuando', 'f_ciudad', 'f_estado'].forEach(function (id) {
			var s = document.getElementById(id);
			if (s) { s.addEventListener('change', function () { s.form.submit(); }); }
		});

		var modalEl = document.getElementById('modalTorneo');
		if (!modalEl) { return; }
		var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
		var form  = document.getElementById('torneo-form');
		var $ = function (id) { return document.getElementById(id); };
		var inicio = $('torneo_fechainicio'), fin = $('torneo_fechafin');

		/* La finalización no puede ser anterior al inicio (el servidor lo
		   rechaza igual: validarFechasTorneo). */
		function limitarFin() {
			fin.min = inicio.value || '';
			fin.setCustomValidity(inicio.value && fin.value && fin.value < inicio.value ? 'No puede ser antes del inicio' : '');
		}
		inicio.addEventListener('change', function () { if (!fin.value || fin.value < inicio.value) { fin.value = inicio.value; } limitarFin(); });
		fin.addEventListener('change', limitarFin);

		var foto = $('torneo_foto'), vista = $('torFotoVista'), vacio = $('torFotoVacio'), ayuda = $('torFotoAyuda');
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

		/* Abrir: vacío para registrar, o con los datos de la fila para editar. */
		function abrir(d) {
			form.reset();
			form.classList.remove('was-validated');
			var editar = !!(d && d.id);
			d = d || {};
			$('tor_modulo').value = editar ? 'actualizar' : 'registrar';
			$('tor_id').value = editar ? d.id : 0;
			modalEl.querySelector('.modal-title span').textContent = editar ? 'Editar torneo' : 'Nuevo torneo';
			$('torGuardarTxt').textContent = editar ? 'Guardar cambios' : 'Registrar torneo';
			$('torneo_nombre').value = d.nombre || '';
			$('torneo_ciudad').value = d.ciudad || '';
			$('torneo_lugar').value = d.lugar || '';
			inicio.value = d.inicio || '';
			fin.value = d.fin || '';
			$('torneo_organizador').value = d.organizador || '';
			$('torneo_descripcion').value = d.descripcion || '';
			limitarFin();
			mostrarFoto(d.foto || '');
			ayuda.textContent = editar && d.foto ? 'Elija otra sólo si quiere reemplazar la actual.' : 'Opcional.';
			modal.show();
		}
		modalEl.addEventListener('shown.bs.modal', function () {
			/* En el teléfono no se enfoca: el teclado taparía el formulario. */
			if (window.matchMedia('(min-width: 768px)').matches) { $('torneo_nombre').focus(); }
		});

		document.querySelectorAll('[data-tor-nuevo]').forEach(function (b) { b.addEventListener('click', function () { abrir(null); }); });
		/* «Editar» es un enlace a /torneoList/{id}/; con JavaScript abre el
		   modal. Delegado: DataTables vuelve a pintar las filas al paginar. */
		document.getElementById('example1').addEventListener('click', function (e) {
			var a = e.target.closest('.tor-editar');
			if (!a || !a.getAttribute('data-torneo') || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
			e.preventDefault();
			abrir(JSON.parse(a.getAttribute('data-torneo')));
		});

		/* Validación antes de la confirmación de ajax.js (que escucha el
		   mismo submit): si falta algo, se marca y no se envía. */
		form.addEventListener('submit', function (e) {
			limitarFin();
			if (!form.checkValidity()) {
				e.preventDefault(); e.stopImmediatePropagation();
				form.classList.add('was-validated');
				var malo = form.querySelector(':invalid'); if (malo) { malo.focus(); }
			}
		}, true);

		/* /torneoList/{id}/: abre ese torneo (aunque esté en otra página de la tabla). */
		var abrirId = <?php echo (int)$abrirId; ?>;
		if (abrirId) {
			var b = tabla.rows().nodes().toArray().map(function (tr) { return tr.querySelector('.tor-editar'); })
				.filter(function (x) { return x && x.getAttribute('data-torneo') && JSON.parse(x.getAttribute('data-torneo')).id === abrirId; })[0];
			if (b) { abrir(JSON.parse(b.getAttribute('data-torneo'))); }
		}
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>

	<style>
		.tor-cabecera { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
		.min-w-0 { min-width: 0; }

		.tor-filtros__cab { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.tor-filtros .form-label { font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: .25rem; }
		.tor-filtros__plegar { white-space: nowrap; flex: 0 0 auto; }
		.tor-filtros__plegar[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
		@media (min-width: 768px) { .tor-filtros__extra { display: contents !important; } }

		.tor-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
		.tor-kpi { display: flex; flex-direction: column; gap: .1rem; padding: .8rem 1rem; border-radius: var(--ds-radius-sm, 10px);
		           background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); color: inherit; text-decoration: none; min-width: 0; }
		a.tor-kpi:hover { background: var(--bs-tertiary-bg); }
		.tor-kpi__et { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); font-weight: 600; }
		.tor-kpi__val { font-size: 1.45rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
		.tor-kpi__nota { font-size: .8rem; color: var(--bs-secondary-color); }
		.tor-kpi--total { border-top: 3px solid var(--bs-primary); }
		.tor-momento { margin-left: .35rem; font-weight: 600; }

		.tor-foto { display: flex; gap: .75rem; align-items: center; }
		.tor-foto__img, .tor-foto__vacio { width: 64px; height: 64px; flex: 0 0 auto; border-radius: 8px; object-fit: cover; border: 1px solid var(--bs-border-color); }
		.tor-foto__vacio { display: grid; place-items: center; background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); font-size: 1.3rem; }
		.tor-foto__vacio[hidden], .tor-foto__img[hidden] { display: none; }

		.tor-fab { position: fixed; right: 1rem; bottom: 1.25rem; z-index: 1030; width: 3.5rem; height: 3.5rem; border-radius: 50%;
		           font-size: 1.3rem; box-shadow: 0 .4rem 1rem rgba(0,0,0,.25); display: grid; place-items: center; padding: 0; }

		@media (max-width: 991.98px) { .tor-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
		@media (max-width: 767.98px) {
			.tor-nuevo { display: none; }
			.tor-kpi { padding: .65rem .8rem; }
			.tor-kpi__val { font-size: 1.2rem; }
			.app-content { padding-bottom: 5rem; }
			.tor-modal__pie { flex-direction: column-reverse; align-items: stretch; }
			.tor-modal__pie .btn { width: 100%; margin: 0; min-height: 2.9rem; }
		}
	</style>
  </body>
</html>
