<?php
/*
| Fútbol › Empleados (rediseño 2026-11-25)
|
| · Criterios de búsqueda: sede, tipo de empleado, estado, acceso al sistema
|   y texto (nombre, identificación, correo o celular).
| · Resumen: empleados, activos, inactivos y sin usuario del sistema.
| · Lista en fichas (.ds-crud, futbol.css) con DataTables para ordenar y
|   «Mostrar N» (se recuerda). La búsqueda la hacen los criterios.
| · Registrar y editar en un modal (a pantalla completa en el celular). Se
|   guarda con empleadoAjax.php como antes. /empleadoList/{id}/ (enlace
|   antiguo) abre el modal de ese empleado.
*/
use app\controllers\empleadoController;
$insempleado = new empleadoController();

$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

$puedeCrear  = puede_crear('empleadoList');
$puedeEditar = puede_editar('empleadoList');

/* Enlace antiguo /empleadoList/{id}/: abre ese empleado en el modal. */
$abrirId = (int)($url[1] ?? 0);

/* Criterios */
$sedeF   = (int)($_GET['sede'] ?? 0);
$tipoF   = (string)($_GET['tipo'] ?? '');
$estadoF = in_array($_GET['estado'] ?? '', ['A', 'I'], true) ? $_GET['estado'] : '';
$accesoF = in_array($_GET['acceso'] ?? '', ['con', 'sin', 'inactivo'], true) ? $_GET['acceso'] : '';
$q       = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$filtros = ($sedeF > 0) + ($tipoF !== '') + ($estadoF !== '') + ($accesoF !== '') + ($q !== '');
$filtrosExtra = $filtros - ($q !== '');

$sedes = $insempleado->sedesFiltroEmpleado();
$tipos = $insempleado->tiposFiltroEmpleado();
$resumen = [];
$filasEmpleados = $insempleado->listarEmpleados(['sede' => $sedeF, 'tipo' => $tipoF, 'estado' => $estadoF, 'acceso' => $accesoF, 'q' => $q], $resumen);

$enlace = static function (array $cambios) use ($sedeF, $tipoF, $estadoF, $accesoF, $q): string {
	$p = array_filter(array_merge(['sede' => $sedeF ?: '', 'tipo' => $tipoF, 'estado' => $estadoF, 'acceso' => $accesoF, 'q' => $q], $cambios),
	                  static fn ($v) => $v !== '' && $v !== null);
	return APP_URL . 'empleadoList/' . ($p ? '?' . http_build_query($p) : '');
};

/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
$tituloVista = 'Empleados';
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
				<div class="emp-cabecera">
					<div>
						<h4 class="m-0">Empleados</h4>
						<div class="small text-body-secondary">Profesores, secretaría y demás personal de la escuela</div>
					</div>
					<?php if ($puedeCrear): ?>
						<button type="button" class="btn btn-primary emp-nuevo" data-emp-nuevo>
							<i class="fas fa-user-plus me-1" aria-hidden="true"></i>Nuevo empleado
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Criterios de búsqueda =================== -->
				<form method="GET" action="<?php echo APP_URL; ?>empleadoList/" autocomplete="off" class="card mb-3 emp-filtros" id="empFiltros">
					<div class="card-header emp-filtros__cab">
						<h3 class="card-title mb-0"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<button type="button" class="btn btn-sm btn-outline-secondary d-md-none emp-filtros__plegar" data-bs-toggle="collapse" data-bs-target="#empFiltrosCuerpo"
						        aria-expanded="<?php echo $filtrosExtra ? 'true' : 'false'; ?>" aria-controls="empFiltrosCuerpo">
							<i class="fas fa-sliders me-1" aria-hidden="true"></i><span><?php echo $filtrosExtra ? 'Filtros (' . $filtrosExtra . ')' : 'Filtros'; ?></span>
							<i class="fas fa-chevron-down ms-1" aria-hidden="true"></i>
						</button>
					</div>
					<div class="card-body pb-2">
						<div class="row g-2 g-md-3">
							<div class="col-12 col-md-8 col-xl-3">
								<label for="f_q" class="form-label">Buscar empleado</label>
								<input type="search" class="form-control" id="f_q" name="q" maxlength="80" placeholder="Nombre, cédula, correo o celular" value="<?php echo $h($q); ?>">
							</div>
							<div class="collapse emp-filtros__extra<?php echo $filtrosExtra ? ' show' : ''; ?>" id="empFiltrosCuerpo">
							<div class="row g-2 g-md-3 mt-0 emp-filtros__extra">
								<div class="col-6 col-md-4 col-xl">
									<label for="f_sede" class="form-label">Sede</label>
									<select class="form-select" id="f_sede" name="sede">
										<option value="">Todas</option>
										<?php foreach ($sedes as $id => $nombre): ?>
											<option value="<?php echo (int)$id; ?>" <?php echo $id === $sedeF ? 'selected' : ''; ?>><?php echo $h($nombre); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="f_tipo" class="form-label">Tipo de empleado</label>
									<select class="form-select" id="f_tipo" name="tipo">
										<option value="">Todos</option>
										<?php foreach ($tipos as $v => $nombre): ?>
											<option value="<?php echo $h($v); ?>" <?php echo $v === $tipoF ? 'selected' : ''; ?>><?php echo $h($nombre); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="f_estado" class="form-label">Estado</label>
									<select class="form-select" id="f_estado" name="estado">
										<option value="">Todos</option>
										<option value="A" <?php echo $estadoF === 'A' ? 'selected' : ''; ?>>Sólo activos</option>
										<option value="I" <?php echo $estadoF === 'I' ? 'selected' : ''; ?>>Sólo inactivos</option>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="f_acceso" class="form-label">Acceso al sistema</label>
									<select class="form-select" id="f_acceso" name="acceso">
										<option value="">Todos</option>
										<option value="con" <?php echo $accesoF === 'con' ? 'selected' : ''; ?>>Con acceso</option>
										<option value="inactivo" <?php echo $accesoF === 'inactivo' ? 'selected' : ''; ?>>Usuario inactivo</option>
										<option value="sin" <?php echo $accesoF === 'sin' ? 'selected' : ''; ?>>Sin usuario</option>
									</select>
								</div>
							</div>
							</div>
							<div class="col-12 col-md-4 col-xl-auto d-flex align-items-end gap-2">
								<div class="flex-grow-1"><?php echo ds_boton_buscar(); ?></div>
								<?php if ($filtros): ?>
									<a href="<?php echo APP_URL; ?>empleadoList/" class="btn btn-outline-secondary" title="Quitar los filtros">
										<i class="fas fa-eraser" aria-hidden="true"></i><span class="visually-hidden">Limpiar</span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resumen =================== -->
				<div class="emp-kpis mb-3">
					<div class="emp-kpi emp-kpi--total">
						<span class="emp-kpi__et">Empleados</span>
						<strong class="emp-kpi__val"><?php echo (int)$resumen['total']; ?></strong>
						<span class="emp-kpi__nota"><?php echo $filtros ? 'con estos criterios' : 'activos e inactivos'; ?></span>
					</div>
					<a class="emp-kpi" href="<?php echo $h($enlace(['estado' => $estadoF === 'A' ? '' : 'A'])); ?>" title="Ver sólo los activos">
						<span class="emp-kpi__et">Activos</span>
						<strong class="emp-kpi__val text-success"><?php echo (int)$resumen['activos']; ?></strong>
						<span class="emp-kpi__nota">trabajando</span>
					</a>
					<a class="emp-kpi" href="<?php echo $h($enlace(['estado' => $estadoF === 'I' ? '' : 'I'])); ?>" title="Ver sólo los inactivos">
						<span class="emp-kpi__et">Inactivos</span>
						<strong class="emp-kpi__val"><?php echo (int)$resumen['inactivos']; ?></strong>
						<span class="emp-kpi__nota">sin actividad</span>
					</a>
					<a class="emp-kpi emp-kpi--aviso" href="<?php echo $h($enlace(['acceso' => $accesoF === 'sin' ? '' : 'sin'])); ?>" title="Ver los que no tienen usuario del sistema">
						<span class="emp-kpi__et">Sin usuario del sistema</span>
						<strong class="emp-kpi__val"><?php echo (int)$resumen['sinUsuario']; ?></strong>
						<span class="emp-kpi__nota">no pueden ingresar</span>
					</a>
				</div>

				<!-- =================== Empleados =================== -->
				<div class="card ds-crud__lista" id="empleados-ingresados">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-id-badge me-2" aria-hidden="true"></i>Empleados
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
									<th>Empleado</th>
									<th class="ds-col-opcional">Sede</th>
									<th>Contacto</th>
									<th>Acceso al sistema</th>
									<th>Estado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filasEmpleados; ?>
							</tbody>
						</table>
					</div>
				</div>

				<?php if ($puedeCrear): ?>
					<button type="button" class="btn btn-primary emp-fab d-md-none" data-emp-nuevo aria-label="Nuevo empleado"><i class="fas fa-user-plus" aria-hidden="true"></i></button>
				<?php endif; ?>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<?php if ($puedeCrear || $puedeEditar): ?>
	<!-- =================== Registrar / editar =================== -->
	<div class="modal fade" id="modalEmpleado" tabindex="-1" aria-labelledby="modalEmpleadoTitulo" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down">
			<form class="modal-content FormularioAjax" id="empleado-form" action="<?php echo APP_URL; ?>app/ajax/empleadoAjax.php" method="POST"
			      autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_empleado" id="emp_modulo" value="registrar">
				<input type="hidden" name="empleado_id" id="emp_id" value="0">
				<div class="modal-header">
					<h5 class="modal-title" id="modalEmpleadoTitulo"><i class="fas fa-user-plus me-2" aria-hidden="true"></i><span>Nuevo empleado</span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="row g-3">
						<!-- Foto -->
						<div class="col-12 col-lg-3">
							<div class="emp-foto">
								<img src="" alt="" class="emp-foto__img" id="empFotoVista" hidden>
								<div class="emp-foto__vacio" id="empFotoVacio" aria-hidden="true"><i class="fas fa-user"></i></div>
								<label class="btn btn-outline-secondary btn-sm emp-foto__boton" for="empleado_foto">
									<i class="fas fa-camera me-1" aria-hidden="true"></i><span id="empFotoTxt">Elegir foto</span>
								</label>
								<input type="file" class="visually-hidden" name="empleado_foto" id="empleado_foto" accept="image/jpeg,image/png" aria-describedby="empFotoAyuda">
								<div class="form-text text-center" id="empFotoAyuda">JPG o PNG, hasta 4 MB. Opcional.</div>
							</div>
						</div>

						<!-- Datos -->
						<div class="col-12 col-lg-9">
							<div class="emp-seccion">Datos personales</div>
							<div class="row g-3">
								<div class="col-12 col-md-6">
									<label for="empleado_nombre" class="form-label">Nombre y apellido <span class="text-danger">*</span></label>
									<input type="text" class="form-control" id="empleado_nombre" name="empleado_nombre" maxlength="80" required autocomplete="off">
									<div class="invalid-feedback">Escriba el nombre.</div>
								</div>
								<div class="col-5 col-md-2">
									<label for="empleado_tipoidentificacion" class="form-label">Tipo</label>
									<select id="empleado_tipoidentificacion" class="form-select" name="empleado_tipoidentificacion">
										<?php echo $insempleado->OptionTipoIdentificacion(''); ?>
									</select>
								</div>
								<div class="col-7 col-md-4">
									<label for="empleado_identificacion" class="form-label">Identificación <span class="text-danger">*</span></label>
									<input type="text" class="form-control" id="empleado_identificacion" name="empleado_identificacion" inputmode="numeric" maxlength="20" required>
									<div class="invalid-feedback">Escriba la identificación.</div>
								</div>
								<div class="col-12 col-md-6">
									<span class="form-label d-block" id="empleado_genero_etq">Género <span class="text-danger">*</span></span>
									<div class="btn-group w-100 emp-genero" role="radiogroup" aria-labelledby="empleado_genero_etq">
										<input class="btn-check" type="radio" id="empleado_generoM" name="empleado_genero" value="M" required>
										<label class="btn btn-outline-secondary" for="empleado_generoM">Masculino</label>
										<input class="btn-check" type="radio" id="empleado_generoF" name="empleado_genero" value="F">
										<label class="btn btn-outline-secondary" for="empleado_generoF">Femenino</label>
									</div>
									<div class="invalid-feedback emp-genero__error">Elija el género.</div>
								</div>
								<div class="col-12 col-md-6">
									<label for="empleado_direccion" class="form-label">Dirección <span class="text-danger">*</span></label>
									<input type="text" class="form-control" id="empleado_direccion" name="empleado_direccion" maxlength="200" required>
									<div class="invalid-feedback">Escriba la dirección.</div>
								</div>
							</div>

							<div class="emp-seccion mt-4">Contacto</div>
							<div class="row g-3">
								<div class="col-12 col-md-7">
									<label for="empleado_correo" class="form-label">Correo <span class="text-danger">*</span></label>
									<input type="email" class="form-control" id="empleado_correo" name="empleado_correo" inputmode="email" maxlength="50" required>
									<div class="invalid-feedback">Escriba un correo válido.</div>
								</div>
								<div class="col-12 col-md-5">
									<label for="empleado_celular" class="form-label">Celular <span class="text-danger">*</span></label>
									<input type="tel" class="form-control" id="empleado_celular" name="empleado_celular" inputmode="numeric" maxlength="10" pattern="0\d{9}" placeholder="09…" required>
									<div class="invalid-feedback">10 dígitos, empieza por 0.</div>
								</div>
							</div>

							<div class="emp-seccion mt-4">Trabajo</div>
							<div class="row g-3">
								<div class="col-12 col-md-4">
									<label for="empleado_sedeid" class="form-label">Sede</label>
									<select class="form-select" id="empleado_sedeid" name="empleado_sedeid">
										<?php echo $insempleado->listarOptionSede(0); ?>
									</select>
								</div>
								<div class="col-6 col-md-4">
									<label for="empleado_tipopersonalid" class="form-label">Tipo de empleado</label>
									<select class="form-select" id="empleado_tipopersonalid" name="empleado_tipopersonalid">
										<?php echo $insempleado->listarTipoPersonal(''); ?>
									</select>
								</div>
								<div class="col-6 col-md-4" id="empEspecialidadCampo">
									<label for="empleado_especialidadid" class="form-label">Especialidad</label>
									<select class="form-select" id="empleado_especialidadid" name="empleado_especialidadid">
										<?php echo $insempleado->OptionEspecialidad(''); ?>
									</select>
								</div>
								<div class="col-6 col-md-4">
									<label for="empleado_fechaingreso" class="form-label">Fecha de ingreso <span class="text-danger">*</span></label>
									<input type="date" class="form-control" name="empleado_fechaingreso" id="empleado_fechaingreso" required>
									<div class="invalid-feedback">Elija la fecha.</div>
								</div>
								<?php /* El servidor quita «$», comas y espacios: basta un número con decimales. */ ?>
								<div class="col-6 col-md-4">
									<label for="empleado_sueldo" class="form-label">Honorarios <span class="text-danger">*</span></label>
									<div class="input-group has-validation">
										<span class="input-group-text">$</span>
										<input type="text" class="form-control" id="empleado_sueldo" name="empleado_sueldo" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" required>
										<div class="invalid-feedback">Un valor como 450 o 450.50.</div>
									</div>
								</div>
							</div>
							<p class="small text-body-secondary mt-3 mb-0"><span class="text-danger">*</span> Campos obligatorios.</p>
						</div>
					</div>
				</div>
				<div class="modal-footer emp-modal__pie">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1" aria-hidden="true"></i><span id="empGuardarTxt">Registrar empleado</span></button>
				</div>
			</form>
		</div>
	</div>
	<?php endif; ?>

	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive ni botones de exportar -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/*
		| Sin la extensión Responsive: en el teléfono cada empleado es una
		| ficha (futbol.css, .ds-crud). «Mostrar N» elige cuántos por página y
		| se recuerda. Primero los activos.
		|
		| Dos búsquedas que se complementan: los «Criterios de búsqueda» van al
		| servidor (sede, tipo, estado…); el filtro de la tabla (arriba a la
		| derecha) filtra al escribir lo que ya está cargado. «1 a N de N
		| empleados» va debajo de la tabla, a la izquierda.
		*/
		var CLAVE = 'ds-empleados-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 10;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable("#example1", {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [[4, 'asc'], [0, 'asc']],
			columnDefs: [
				{ targets: 4, type: 'string' },        /* data-order: 0 activo, 1 inactivo */
				{ targets: 5, orderable: false, searchable: false }
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: <?php echo json_encode($filtros ? 'Ningún empleado coincide con los criterios de búsqueda' : 'Aún no hay empleados ingresados'); ?>,
				info: "_START_ a _END_ de _TOTAL_ empleados",
				infoEmpty: "Sin empleados",
				infoFiltered: "(filtrados de _MAX_)",
				zeroRecords: "Ningún empleado coincide con el filtro",
				search: "",
				searchPlaceholder: "Filtrar en la tabla…",
				lengthMenu: "Mostrar _MENU_",
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

		/* Los selectores de los criterios filtran al elegirlos. */
		['f_sede', 'f_tipo', 'f_estado', 'f_acceso'].forEach(function (id) {
			var s = document.getElementById(id);
			if (s) { s.addEventListener('change', function () { s.form.submit(); }); }
		});

		var modalEl = document.getElementById('modalEmpleado');
		if (!modalEl) { return; }
		var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
		var form  = document.getElementById('empleado-form');
		var $ = function (id) { return document.getElementById(id); };
		var HOY = <?php echo json_encode(date('Y-m-d')); ?>;
		var SEDE_F = <?php echo json_encode($sedeF > 0 ? (string)$sedeF : ''); ?>;

		/*
		| La especialidad sólo aplica a los profesores (TPP). Deshabilitada no
		| se envía y el servidor la guarda vacía, que es lo correcto para los
		| demás; para un profesor tiene que quedar habilitada al abrir.
		*/
		var tipo = $('empleado_tipopersonalid'), especialidad = $('empleado_especialidadid');
		function alternarEspecialidad() {
			especialidad.disabled = tipo.value !== 'TPP';
			$('empEspecialidadCampo').classList.toggle('opacity-50', especialidad.disabled);
		}
		tipo.addEventListener('change', alternarEspecialidad);

		/* Foto: vista previa, o las iniciales del nombre. */
		var foto = $('empleado_foto'), vista = $('empFotoVista'), vacio = $('empFotoVacio'), ayuda = $('empFotoAyuda');
		function mostrarFoto(url) {
			vista.hidden = !url; vacio.hidden = !!url;
			if (url) { vista.src = url; } else { vista.removeAttribute('src'); }
			$('empFotoTxt').textContent = url ? 'Cambiar foto' : 'Elegir foto';
		}
		foto.addEventListener('change', function () {
			var f = foto.files && foto.files[0];
			if (!f) { return; }
			if (f.size > 4000 * 1024) { foto.value = ''; ayuda.innerHTML = '<span class="text-danger">La imagen supera los 4 MB: elija otra.</span>'; return; }
			mostrarFoto(URL.createObjectURL(f));
			ayuda.textContent = f.name;
		});

		function elegir(select, valor) {
			if (valor && [].some.call(select.options, function (o) { return o.value === valor; })) { select.value = valor; }
			else { select.selectedIndex = 0; }
		}

		/* Abrir: vacío para registrar, o con los datos de la fila para editar. */
		function abrir(d) {
			form.reset();
			form.classList.remove('was-validated');
			var editar = !!(d && d.id);
			$('emp_modulo').value = editar ? 'actualizar' : 'registrar';
			$('emp_id').value = editar ? d.id : 0;
			form.classList.toggle('ds-crud__form--edicion', editar);
			modalEl.querySelector('.modal-title span').textContent = editar ? 'Editar empleado' : 'Nuevo empleado';
			modalEl.querySelector('.modal-title i').className = 'fas ' + (editar ? 'fa-pen' : 'fa-user-plus') + ' me-2';
			$('empGuardarTxt').textContent = editar ? 'Guardar cambios' : 'Registrar empleado';
			d = d || {};
			$('empleado_nombre').value = d.nombre || '';
			elegir($('empleado_tipoidentificacion'), d.tipoid || '');
			$('empleado_identificacion').value = d.identificacion || '';
			$('empleado_generoM').checked = d.genero === 'M';
			$('empleado_generoF').checked = d.genero === 'F';
			$('empleado_direccion').value = d.direccion || '';
			$('empleado_correo').value = d.correo || '';
			$('empleado_celular').value = d.celular || '';
			elegir($('empleado_sedeid'), editar ? d.sede : SEDE_F);
			elegir(tipo, d.tipo || '');
			elegir(especialidad, d.especialidad || '');
			$('empleado_fechaingreso').value = editar ? (d.ingreso || '') : HOY;
			$('empleado_sueldo').value = d.sueldo || '';
			alternarEspecialidad();
			mostrarFoto(d.foto || '');
			ayuda.textContent = editar && d.foto ? 'Elija otra sólo si quiere reemplazar la actual.' : 'JPG o PNG, hasta 4 MB. Opcional.';
			modal.show();
		}
		modalEl.addEventListener('shown.bs.modal', function () {
			/* En el teléfono no se enfoca: el teclado taparía el formulario. */
			if (window.matchMedia('(min-width: 768px)').matches) { $('empleado_nombre').focus(); }
		});

		document.querySelectorAll('[data-emp-nuevo]').forEach(function (b) { b.addEventListener('click', function () { abrir(null); }); });
		/* Delegado: DataTables vuelve a pintar las filas al paginar u ordenar. */
		document.getElementById('example1').addEventListener('click', function (e) {
			var b = e.target.closest('.emp-editar');
			if (b) { abrir(JSON.parse(b.getAttribute('data-empleado'))); }
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

		/* Enlace antiguo /empleadoList/{id}/: abre ese empleado. */
		var abrirId = <?php echo (int)$abrirId; ?>;
		if (abrirId) {
			var fila = tabla.rows().nodes().toArray().map(function (tr) { return tr.querySelector('.emp-editar'); })
				.filter(function (b) { return b && JSON.parse(b.getAttribute('data-empleado')).id === abrirId; })[0];
			if (fila) { abrir(JSON.parse(fila.getAttribute('data-empleado'))); }
		}
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>

	<style>
		.emp-cabecera { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }

		.emp-filtros__cab { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.emp-filtros .form-label { font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: .25rem; }
		.emp-filtros__plegar { white-space: nowrap; flex: 0 0 auto; }
		.emp-filtros__plegar[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
		/* Escritorio: todos los criterios en la misma fila (el pliegue es
		   sólo para el celular). */
		@media (min-width: 768px) { .emp-filtros__extra { display: contents !important; } }

		.emp-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
		.emp-kpi { display: flex; flex-direction: column; gap: .1rem; padding: .8rem 1rem; border-radius: var(--ds-radius-sm, 10px);
		           background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); color: inherit; text-decoration: none; min-width: 0; }
		a.emp-kpi:hover { background: var(--bs-tertiary-bg); }
		.emp-kpi__et { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); font-weight: 600; }
		.emp-kpi__val { font-size: 1.45rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
		.emp-kpi__nota { font-size: .8rem; color: var(--bs-secondary-color); }
		.emp-kpi--total { border-top: 3px solid var(--bs-primary); }
		.emp-kpi--aviso { border-top: 3px solid var(--bs-warning); }

		/* Modal */
		.emp-seccion { font-size: .75rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--bs-secondary-color);
		               padding-bottom: .35rem; margin-bottom: .75rem; border-bottom: 1px solid var(--bs-border-color); }
		.emp-foto { display: flex; flex-direction: column; align-items: center; gap: .5rem; }
		.emp-foto__img, .emp-foto__vacio { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 1px solid var(--bs-border-color); }
		.emp-foto__vacio { display: grid; place-items: center; background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); font-size: 2.5rem; }
		.emp-foto__vacio[hidden], .emp-foto__img[hidden] { display: none; }
		.emp-genero .btn { min-height: 2.5rem; }
		.was-validated .emp-genero:has(:invalid) + .emp-genero__error { display: block; }
		.was-validated .emp-genero:has(:invalid) .btn { border-color: var(--bs-form-invalid-border-color); }

		/* Pie de la tabla: «1 a N de N empleados» a la izquierda, la paginación a la derecha. */
		#empleados-ingresados .dt-info { color: var(--bs-secondary-color); }
		@media (min-width: 768px) {
			#empleados-ingresados .dt-layout-start .dt-info { text-align: left; padding-top: .35rem; }
		}

		.emp-fab { position: fixed; right: 1rem; bottom: 1.25rem; z-index: 1030; width: 3.5rem; height: 3.5rem; border-radius: 50%;
		           font-size: 1.25rem; box-shadow: 0 .4rem 1rem rgba(0,0,0,.25); display: grid; place-items: center; padding: 0; }

		@media (max-width: 991.98px) {
			.emp-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
			.emp-foto { flex-direction: row; flex-wrap: wrap; justify-content: center; }
			.emp-foto__img, .emp-foto__vacio { width: 88px; height: 88px; font-size: 1.8rem; }
		}
		@media (max-width: 767.98px) {
			.emp-nuevo { display: none; }
			.emp-kpi { padding: .65rem .8rem; }
			.emp-kpi__val { font-size: 1.2rem; }
			.app-content { padding-bottom: 5rem; }
			.emp-modal__pie { flex-direction: column-reverse; align-items: stretch; }
			.emp-modal__pie .btn { width: 100%; margin: 0; min-height: 2.9rem; }
		}
	</style>
  </body>
</html>
