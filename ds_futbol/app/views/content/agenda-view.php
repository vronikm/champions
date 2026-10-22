<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\agendaController;
	$insAgenda = new agendaController();

	/* Los eventos se leen una vez: el calendario los recibe en JSON y la
	   columna de próximos usa los mismos datos. */
	$eventosJson = $insAgenda->obtenerEventos();
	$proximos    = $insAgenda->proximosEventos(6);

	$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

	/* «mié 22 oct · 10:00 – 11:30», o con las dos fechas si acaba otro día. */
	$cuando = static function (string $inicio, string $fin): string {
		$dias  = ['dom','lun','mar','mié','jue','vie','sáb'];
		$meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
		$i = strtotime($inicio); $f = strtotime($fin ?: $inicio);
		$fecha = static fn($t) => $dias[(int)date('w', $t)] . ' ' . date('j', $t) . ' ' . $meses[(int)date('n', $t) - 1];
		return date('Y-m-d', $i) === date('Y-m-d', $f)
			? $fecha($i) . ' · ' . date('H:i', $i) . ' – ' . date('H:i', $f)
			: $fecha($i) . ' ' . date('H:i', $i) . ' → ' . $fecha($f) . ' ' . date('H:i', $f);
	};

	/* Colores para elegir con un toque. El selector libre sigue disponible. */
	$colores = [
		'#0d6efd' => 'Azul',     '#198754' => 'Verde',   '#ffc107' => 'Amarillo',
		'#dc3545' => 'Rojo',     '#6f42c1' => 'Morado',  '#fd7e14' => 'Naranja',
		'#20c997' => 'Turquesa', '#6c757d' => 'Gris',
	];
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo APP_NAME; ?> | Agenda</title>
	<?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
	<link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/main.css">
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/css/overlayscrollbars.min.css">
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/css/adminlte.min.css">
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/core.css'); ?>">
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_futbol/app/views/dist/css/futbol.css'); ?>">
	<link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/dist/css/sweetalert2.min.css">
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">
      <?php require_once "app/views/inc/navbar.php"; ?>
      <?php require_once "app/views/inc/main-sidebar.php"; ?>

      <div class="app-main">

		<div class="app-content-header">
			<div class="container-fluid">
				<div class="ds-agenda__cabecera">
					<div>
						<h4 class="m-0">Agenda</h4>
						<p class="text-body-secondary small mb-0">Eventos y actividades de la escuela</p>
					</div>
					<button type="button" class="btn btn-primary ds-agenda__nuevo" id="btn-nuevo-evento">
						<i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo evento
					</button>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
				<div class="row g-3 ds-agenda ds-calendario">

					<!-- Calendario -->
					<div class="col-12 col-lg-8 col-xl-9">
						<div class="card">
							<div class="card-body ds-agenda__calendario">
								<div id="calendar"></div>
							</div>
						</div>
					</div>

					<!-- Próximos eventos -->
					<div class="col-12 col-lg-4 col-xl-3">
						<div class="card ds-agenda__proximos">
							<div class="card-header">
								<h3 class="card-title"><i class="far fa-clock me-2" aria-hidden="true"></i>Próximos eventos</h3>
							</div>
							<?php if ($proximos): ?>
								<ul class="list-group list-group-flush">
									<?php foreach ($proximos as $e): ?>
										<li class="list-group-item">
											<button type="button" class="ds-agenda__item" data-evento="<?php echo (int)$e['id']; ?>">
												<span class="ds-agenda__punto" style="background: <?php echo $h($e['color']); ?>"></span>
												<span class="ds-agenda__texto">
													<span class="ds-agenda__titulo"><?php echo $h($e['title']); ?></span>
													<span class="ds-agenda__cuando"><?php echo $h($cuando($e['start'], $e['end'])); ?></span>
												</span>
											</button>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else: ?>
								<div class="card-body text-center text-body-secondary">
									<i class="far fa-calendar-check fa-2x mb-2" aria-hidden="true"></i>
									<p class="mb-2">No hay eventos próximos.</p>
									<button type="button" class="btn btn-sm btn-primary" data-nuevo-evento>Crear un evento</button>
								</div>
							<?php endif; ?>
						</div>
					</div>

				</div>
			</div>
		</section>

		<!-- Alta y edición de un evento -->
		<div class="modal fade" id="modal-agenda" tabindex="-1" aria-labelledby="modal-title" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
				<form class="modal-content FormularioAjax" id="form-evento" data-recargar-directo
				      action="<?php echo APP_URL; ?>app/ajax/agendaAjax.php" method="POST" autocomplete="off">
					<input type="hidden" name="modulo_agenda" id="modulo_agenda" value="registrar">
					<input type="hidden" name="agenda_id" id="agenda_id" value="">

					<div class="modal-header">
						<h5 class="modal-title" id="modal-title">Nuevo evento</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="mb-3">
							<label for="agenda_title" class="form-label">Título <span class="text-danger" aria-hidden="true">*</span></label>
							<input type="text" id="agenda_title" name="agenda_title" class="form-control" maxlength="50" required>
						</div>
						<div class="row g-2 mb-3">
							<div class="col-12 col-sm-6">
								<label for="agenda_start" class="form-label">Inicio <span class="text-danger" aria-hidden="true">*</span></label>
								<input type="datetime-local" id="agenda_start" name="agenda_start" class="form-control" required>
							</div>
							<div class="col-12 col-sm-6">
								<label for="agenda_end" class="form-label">Fin <span class="text-danger" aria-hidden="true">*</span></label>
								<input type="datetime-local" id="agenda_end" name="agenda_end" class="form-control" required>
							</div>
						</div>
						<div class="mb-3">
							<label for="agenda_detail" class="form-label">Detalle</label>
							<textarea id="agenda_detail" name="agenda_detail" class="form-control" rows="3" maxlength="200"></textarea>
						</div>
						<fieldset>
							<legend class="form-label fs-6 mb-1">Color</legend>
							<div class="ds-agenda__colores">
								<?php foreach ($colores as $valor => $nombre): ?>
									<label class="ds-agenda__color" title="<?php echo $nombre; ?>">
										<input type="radio" name="color_rapido" value="<?php echo $valor; ?>" aria-label="<?php echo $nombre; ?>">
										<span style="background: <?php echo $valor; ?>"></span>
									</label>
								<?php endforeach; ?>
								<label class="ds-agenda__color ds-agenda__color--libre" title="Otro color">
									<input type="color" id="agenda_color" name="agenda_color" value="#0d6efd" aria-label="Otro color">
								</label>
							</div>
						</fieldset>
					</div>
					<div class="modal-footer">
						<?php /* Botones rellenos: los de contorno daban 3,3 de contraste en tema
						         oscuro (qa_identidad exige 4,5). */ ?>
						<button type="button" class="btn btn-danger me-auto d-none" id="btn-eliminar-evento">
							<i class="fas fa-trash me-1" aria-hidden="true"></i>Eliminar
						</button>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1" aria-hidden="true"></i>Guardar</button>
					</div>
				</form>
			</div>
		</div>

		<?php /* Eliminar va por su propio formulario: ajax.js le añade el
		         testigo CSRF y muestra la respuesta igual que al guardar. La
		         confirmación, con el nombre del evento, la hace el script. */ ?>
		<form class="FormularioAjax d-none" id="form-eliminar-evento" data-recargar-directo
		      action="<?php echo APP_URL; ?>app/ajax/agendaAjax.php" method="POST">
			<input type="hidden" name="modulo_agenda" value="eliminar">
			<input type="hidden" name="agenda_id" id="eliminar_id" value="">
		</form>

      </div>
      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>"></script>
	<!-- FullCalendar 5.10.1 -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/main.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/locales/es.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var eventos = <?php echo $eventosJson; ?>;
		var movil   = window.matchMedia('(max-width: 767.98px)').matches;

		var modalEl  = document.getElementById('modal-agenda');
		var modal    = bootstrap.Modal.getOrCreateInstance(modalEl);
		var campo    = function (id) { return document.getElementById(id); };
		var colorIn  = campo('agenda_color');

		/* datetime-local trabaja en hora LOCAL y sin zona: YYYY-MM-DDTHH:MM.
		   toISOString() daría la hora UTC, cinco horas desplazada. */
		function aInput(d) {
			var p = function (n) { return String(n).padStart(2, '0'); };
			return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
		}
		function ponerColor(valor) {
			colorIn.value = valor || '#0d6efd';
			document.querySelectorAll('input[name="color_rapido"]').forEach(function (r) { r.checked = (r.value.toLowerCase() === colorIn.value.toLowerCase()); });
		}
		function limitarFin() {
			campo('agenda_end').min = campo('agenda_start').value;
			if (campo('agenda_end').value && campo('agenda_end').value < campo('agenda_start').value) {
				var fin = new Date(campo('agenda_start').value); fin.setHours(fin.getHours() + 1);
				campo('agenda_end').value = aInput(fin);
			}
		}

		/* Abre el formulario para un evento nuevo. Antes, después de editar
		   uno, el siguiente «nuevo» se enviaba como edición sin id y fallaba:
		   aquí se reinicia todo, siempre. */
		function nuevoEvento(dia, conHora) {
			/* Desde la vista Semana se pulsa una franja: esa es la hora. Desde el
			   mes o el botón, la próxima hora en punto (no antes de las 8). */
			var inicio = dia ? new Date(dia) : new Date();
			if (!conHora) {
				var ahora = new Date();
				inicio.setHours(dia ? Math.max(ahora.getHours() + 1, 8) % 24 : ahora.getHours() + 1, 0, 0, 0);
			}
			var fin = new Date(inicio.getTime() + 60 * 60 * 1000);

			campo('form-evento').reset();
			campo('modulo_agenda').value = 'registrar';
			campo('agenda_id').value = '';
			campo('modal-title').textContent = 'Nuevo evento';
			campo('btn-eliminar-evento').classList.add('d-none');
			campo('agenda_start').value = aInput(inicio);
			campo('agenda_end').value = aInput(fin);
			ponerColor('#0d6efd');
			limitarFin();
			modal.show();
		}

		function editarEvento(ev) {
			var fin = ev.end || new Date(ev.start.getTime() + 60 * 60 * 1000);
			campo('form-evento').reset();
			campo('modulo_agenda').value = 'editar';
			campo('agenda_id').value = ev.id;
			campo('modal-title').textContent = 'Editar evento';
			campo('btn-eliminar-evento').classList.remove('d-none');
			campo('agenda_title').value = ev.title;
			campo('agenda_detail').value = ev.extendedProps.detail || '';
			campo('agenda_start').value = aInput(ev.start);
			campo('agenda_end').value = aInput(fin);
			ponerColor(ev.backgroundColor);
			limitarFin();
			modal.show();
		}

		var calendario = new FullCalendar.Calendar(document.getElementById('calendar'), {
			locale: 'es',
			/* En el teléfono, la lista: siete columnas de 50 px no dejan leer
			   un título. En pantallas anchas, el mes. */
			initialView: movil ? 'listMonth' : 'dayGridMonth',
			height: 'auto',                       /* nunca recortado */
			headerToolbar: movil
				? { left: 'prev,next', center: 'title', right: 'today' }
				: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
			footerToolbar: movil ? { center: 'listMonth,dayGridMonth' } : false,
			buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Lista' },
			dayMaxEvents: true,
			nowIndicator: true,
			eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
			noEventsContent: 'No hay eventos en este periodo',
			events: eventos,
			/* En el mes el título se corta: al pasar el ratón se lee entero. */
			eventDidMount: function (info) { info.el.title = info.event.title; },
			dateClick: function (info) { nuevoEvento(info.date, !info.allDay); },
			eventClick: function (info) { info.jsEvent.preventDefault(); editarEvento(info.event); }
		});
		calendario.render();

		campo('btn-nuevo-evento').addEventListener('click', function () { nuevoEvento(null); });
		document.querySelectorAll('[data-nuevo-evento]').forEach(function (b) { b.addEventListener('click', function () { nuevoEvento(null); }); });
		document.querySelectorAll('[data-evento]').forEach(function (b) {
			b.addEventListener('click', function () {
				var ev = calendario.getEventById(b.dataset.evento);
				if (ev) { editarEvento(ev); }
			});
		});
		document.querySelectorAll('input[name="color_rapido"]').forEach(function (r) {
			r.addEventListener('change', function () { colorIn.value = r.value; });
		});
		colorIn.addEventListener('input', function () { ponerColor(colorIn.value); });
		campo('agenda_start').addEventListener('change', limitarFin);

		campo('btn-eliminar-evento').addEventListener('click', function () {
			var titulo = campo('agenda_title').value;
			Swal.fire({
				title: '¿Eliminar este evento?',
				text: '«' + titulo + '» dejará de aparecer en la agenda.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Sí, eliminar',
				cancelButtonText: 'No, conservar',
				confirmButtonColor: '#dc3545'
			}).then(function (r) {
				if (!r.isConfirmed) { return; }
				campo('eliminar_id').value = campo('agenda_id').value;
				modal.hide();
				campo('form-eliminar-evento').requestSubmit();
			});
		});
	});
	</script>
  </body>
</html>
