<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\asistenciaController;
	$insHora = new asistenciaController();

	$horaid = (($url[1] ?? "") != "") ? (int)$url[1] : 0;

	/* Valores del formulario. Por omisión, alta de una hora nueva; con un id
	   válido en la URL, edición de esa hora. Antes, con un id que no existía,
	   $modulo_hora quedaba sin definir y el formulario no sabía qué hacer. */
	$modulo_hora = 'registrar';
	$hora_inicio = '';
	$hora_fin    = '';
	$detalle     = '';
	$estado      = 'A';

	if($horaid != 0){
		$datos=$insHora->BuscarHora($horaid);
		if($datos->rowCount()==1){
			$datos=$datos->fetch();
			if ($datos['hora_estado'] !== 'E') {
				$modulo_hora = 'actualizar';
				$hora_inicio = substr((string)$datos['hora_inicio'], 0, 5);
				$hora_fin    = substr((string)$datos['hora_fin'], 0, 5);
				$detalle     = $datos['hora_detalle'];
				$estado      = $datos['hora_estado'];
			}
		}
	}
	if ($modulo_hora === 'registrar') { $horaid = 0; }

	$editando = $modulo_hora === 'actualizar';

	/* limpiarHtml() guarda el detalle ya escapado: se decodifica antes de
	   escaparlo para no mostrar «&quot;» ni escaparlo dos veces al guardar. */
	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

	/* El listado se genera antes para contar las horas en la cabecera. */
	$filasHoras = $insHora->listarHoras();
	$totalHoras = substr_count($filasHoras, '<tr>');
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Horas de entrenamiento';
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
					<div class="row mb-2 g-2 align-items-center">
						<div class="col-sm-7">
							<h4 class="m-0">Horas de entrenamiento</h4>
						</div>
						<div class="col-sm-5">
							<ol class="breadcrumb float-sm-end mb-0">
								<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
								<li class="breadcrumb-item active" aria-current="page">Horas</li>
							</ol>
						</div>
					</div>

					<?php /* En el teléfono las dos secciones quedan una debajo de otra:
					         estos atajos llevan a cada una sin recorrer la página. */ ?>
					<nav class="ds-crud__atajos d-md-none" aria-label="Secciones de la página">
						<a href="#hora-form" class="btn btn-sm btn-outline-secondary">
							<i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus'; ?> me-1" aria-hidden="true"></i><?php echo $editando ? 'Editar hora' : 'Nueva hora'; ?>
						</a>
						<a href="#horas-ingresadas" class="btn btn-sm btn-outline-secondary">
							<i class="fas fa-list me-1" aria-hidden="true"></i>Horas ingresadas <span class="badge text-bg-secondary ms-1"><?php echo $totalHoras; ?></span>
						</a>
					</nav>
				</div>
			</div>

			<section class="app-content">
				<div class="container-fluid ds-crud">

					<!-- =================== 1. Ingreso / edición =================== -->
					<form class="card ds-crud__form FormularioAjax<?php echo $editando ? ' ds-crud__form--edicion' : ''; ?>"
					      id="hora-form" action="<?php echo APP_URL; ?>app/ajax/asistenciaAjax.php" method="POST" autocomplete="off">
						<input type="hidden" name="modulo_asistencia" value="<?php echo $modulo_hora; ?>">
						<input type="hidden" name="hora_id" value="<?php echo (int)$horaid; ?>">

						<div class="card-header">
							<h3 class="card-title">
								<i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus-circle'; ?> me-2" aria-hidden="true"></i><?php
								echo $editando ? 'Editar hora' : 'Ingreso de nueva hora'; ?>
							</h3>
							<?php if ($editando): ?>
								<span class="badge text-bg-warning ms-2 ds-crud__editando"><?php echo $h($hora_inicio . ' – ' . $hora_fin); ?></span>
							<?php endif; ?>
							<div class="card-tools">
								<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
							</div>
						</div>
						<div class="card-body">
							<div class="row g-3">
								<?php /* type="time": en el teléfono abre el selector de hora
								         del sistema. Antes era un texto con máscara 99:99:99 que
								         obligaba a escribir los segundos. */ ?>
								<div class="col-6 col-md-3 col-xl-2">
									<label for="hora_inicio" class="form-label">Hora inicio <span class="text-danger" aria-hidden="true">*</span></label>
									<input type="time" class="form-control" id="hora_inicio" name="hora_inicio" step="300" required value="<?php echo $h($hora_inicio); ?>">
								</div>
								<div class="col-6 col-md-3 col-xl-2">
									<label for="hora_fin" class="form-label">Hora fin <span class="text-danger" aria-hidden="true">*</span></label>
									<input type="time" class="form-control" id="hora_fin" name="hora_fin" step="300" required value="<?php echo $h($hora_fin); ?>" aria-describedby="hora_duracion">
								</div>
								<div class="col-12 <?php echo $editando ? 'col-md-6 col-xl-6' : 'col-md-6 col-xl-8'; ?>">
									<label for="detalle" class="form-label">Detalle</label>
									<input type="text" class="form-control" id="detalle" name="detalle" maxlength="300" placeholder="Por ejemplo, Horario grupo 1" value="<?php echo $h($detalle); ?>">
								</div>
								<?php if ($editando): ?>
									<div class="col-12 col-md-3 col-xl-2">
										<label for="estado" class="form-label">Estado</label>
										<select class="form-select" id="estado" name="estado">
											<option value="A" <?php echo $estado === 'A' ? 'selected' : ''; ?>>Activo</option>
											<option value="I" <?php echo $estado === 'I' ? 'selected' : ''; ?>>Inactivo</option>
										</select>
									</div>
								<?php else: ?>
									<?php /* Una hora nueva nace activa (registrarHoraControlador). */ ?>
									<input type="hidden" name="estado" value="A">
								<?php endif; ?>
								<div class="col-12">
									<p class="form-text mb-0" id="hora_duracion" aria-live="polite"></p>
								</div>
							</div>
						</div>

						<?php echo ds_acciones_form(APP_URL . 'asistenciaHora/', ['limpiar' => true, 'volver' => $editando ? 'Cancelar' : 'Volver']); ?>
					</form>

					<!-- =================== 2. Horas ingresadas =================== -->
					<div class="card ds-crud__lista" id="horas-ingresadas">
						<div class="card-header">
							<h3 class="card-title">
								<i class="far fa-clock me-2" aria-hidden="true"></i>Horas ingresadas
								<span class="badge text-bg-secondary ms-1"><?php echo $totalHoras; ?></span>
							</h3>
							<div class="card-tools">
								<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
							</div>
						</div>
						<div class="card-body">
							<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
								<thead>
									<tr>
										<th>Hora</th>
										<th>Detalle</th>
										<th class="text-center">Horarios</th>
										<th>Estado</th>
										<th class="text-end"><span class="visually-hidden">Acciones</span></th>
									</tr>
								</thead>
								<tbody>
									<?php echo $filasHoras; ?>
								</tbody>
							</table>
							<p class="small text-body-secondary mb-0 mt-2">
								<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Horarios: cuántos horarios usan la hora. Una hora en uso no se puede eliminar; se puede inactivar.
							</p>
						</div>
					</div>

				</div>
			</section>
		</div>

		<?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive ni botones de exportar -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/* En el teléfono cada hora es una ficha (futbol.css, .ds-crud). */
		new DataTable("#example1", {
			autoWidth: false,
			lengthChange: false,
			pageLength: 25,
			order: [[0, 'asc']],
			columnDefs: [
				{ targets: [0, 3], type: 'string' },   /* data-order: HH:MM:SS y el estado */
				{ targets: 2, type: 'num', className: 'text-center', searchable: false },
				{ targets: 4, orderable: false, searchable: false }
			],
			layout: {
				topStart: null,
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "Aún no hay horas ingresadas",
				info: "_START_ a _END_ de _TOTAL_ horas",
				infoEmpty: "Sin horas",
				infoFiltered: "(de _MAX_)",
				search: "",
				searchPlaceholder: "Buscar hora o detalle…",
				zeroRecords: "Ninguna hora coincide con la búsqueda",
				paginate: { next: "Siguiente", previous: "Anterior" },
				aria: {
					sortAscending: ": ordenar de forma ascendente",
					sortDescending: ": ordenar de forma descendente"
				}
			}
		});

		/*
		| La hora fin, después de la de inicio. El servidor lo comprueba igual
		| (validarRangoHora); aquí se avisa antes de enviar y se muestra la
		| duración mientras se escribe.
		*/
		var inicio = document.getElementById('hora_inicio');
		var fin = document.getElementById('hora_fin');
		var nota = document.getElementById('hora_duracion');
		function revisar() {
			fin.min = inicio.value || '';
			if (!inicio.value || !fin.value) { fin.setCustomValidity(''); nota.textContent = ''; return; }
			var a = inicio.value.split(':'), b = fin.value.split(':');
			var minutos = (b[0] * 60 + +b[1]) - (a[0] * 60 + +a[1]);
			if (minutos <= 0) {
				fin.setCustomValidity('La hora fin debe ser posterior a la hora de inicio');
				nota.textContent = 'La hora fin debe ser posterior a la hora de inicio.';
				nota.classList.add('text-danger');
			} else {
				fin.setCustomValidity('');
				var h = Math.floor(minutos / 60), m = minutos % 60;
				nota.textContent = 'Duración: ' + (h ? h + ' h' : '') + (h && m ? ' ' : '') + (m ? m + ' min' : '');
				nota.classList.remove('text-danger');
			}
		}
		inicio.addEventListener('input', revisar);
		fin.addEventListener('input', revisar);
		revisar();
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
  </body>
</html>
