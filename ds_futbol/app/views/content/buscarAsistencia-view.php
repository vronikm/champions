<?php
	use app\controllers\asistenciaController;
	$insAsistencia = new asistenciaController();

	$alumno = ds_id_de_url($url, 1, APP_URL . 'reporteAsistencia/');

	$datos = $insAsistencia->BuscarAlumno($alumno);
	if ($datos->rowCount() != 1) {
		/* El registro no existe: se vuelve al listado. Antes se incluía el
		   aviso pero la vista seguía ejecutando con $datos aún como
		   PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "reporteAsistencia/");
		exit();
	}
	$datos = $datos->fetch();

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

	/* $nombreAlumno y no $nombre: main-sidebar.php, que se incluye después,
	   pisa $nombre con el de la sede y la ficha salía «DigiSports - Fútbol». */
	$nombreAlumno = trim($datos['alumno_primernombre'] . ' ' . $datos['alumno_apellidopaterno']);

	/* La foto, sólo si existe de verdad. media_url() devuelve la imagen
	   genérica —el escudo de la escuela— cuando el archivo falta, y en esta
	   pantalla parecía la foto del alumno. Sin foto, sus iniciales. */
	$foto = $datos['alumno_imagen'] != '' ? media_url('alumno', $datos['alumno_imagen']) : '';
	if (str_contains($foto, '/dist/img/foto.jpg')) { $foto = ''; }
	$iniciales = mb_strtoupper(mb_substr((string)$datos['alumno_primernombre'], 0, 1) . mb_substr((string)$datos['alumno_apellidopaterno'], 0, 1));

	$alDia   = (int)$datos['pendiente'] !== 1;
	$activo  = $datos['estado'] === 'Activo';
	$ingreso = ($datos['alumno_fechaingreso'] && $datos['alumno_fechaingreso'] !== '0000-00-00')
		? date('d/m/Y', strtotime($datos['alumno_fechaingreso'])) : '—';

	/* Las marcas de asistencia, una por día, como eventos de FullCalendar. */
	$eventosJson = $insAsistencia->CalendarioEventos($alumno);
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo APP_NAME; ?> | Asistencia de <?php echo $h($nombreAlumno); ?></title>
	<?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
	<link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
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
				<div class="row mb-2 g-2 align-items-center">
					<div class="col-sm-7">
						<h4 class="m-0">Asistencia</h4>
						<p class="small text-body-secondary mb-0"><?php echo $h($nombreAlumno); ?></p>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>reporteAsistencia/">Reporte de asistencia</a></li>
							<li class="breadcrumb-item active" aria-current="page">Alumno</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
				<div class="row g-3 ds-asist ds-calendario">

					<!-- ============ El alumno ============ -->
					<div class="col-12 col-lg-4 col-xl-3">
						<div class="card ds-asist__alumno">
							<div class="card-body">
								<div class="ds-asist__identidad">
									<?php if ($foto !== ''): ?>
										<img class="ds-asist__avatar" src="<?php echo $h($foto); ?>" alt="Foto de <?php echo $h($nombreAlumno); ?>">
									<?php else: ?>
										<span class="ds-asist__avatar ds-asist__avatar--iniciales" aria-hidden="true"><?php echo $h($iniciales); ?></span>
									<?php endif; ?>
									<div class="ds-asist__nombre">
										<strong><?php echo $h($nombreAlumno); ?></strong>
										<span class="text-body-secondary"><?php echo $h($datos['alumno_identificacion']); ?></span>
									</div>
								</div>

								<dl class="ds-asist__datos">
									<div><dt>Sede</dt><dd><?php echo $h($datos['sede_nombre'] ?? '—'); ?></dd></div>
									<div><dt>Categoría</dt><dd><?php echo $h($datos['anio']); ?></dd></div>
									<div><dt>Estado</dt><dd><span class="badge <?php echo $activo ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $h($datos['estado']); ?></span></dd></div>
									<div><dt>Pagos</dt><dd><span class="badge <?php echo $alDia ? 'text-bg-success' : 'text-bg-danger'; ?>"><?php echo $alDia ? 'Al día' : 'Pendiente'; ?></span></dd></div>
									<div><dt>Ingreso</dt><dd><?php echo $h($ingreso); ?></dd></div>
									<div><dt>ID</dt><dd><?php echo (int)$alumno; ?></dd></div>
								</dl>

								<div class="ds-asist__acciones">
									<?php if (function_exists('usuario_tiene_permiso') && usuario_tiene_permiso('alumnoProfile')): ?>
										<a href="<?php echo APP_URL; ?>alumnoProfile/<?php echo (int)$alumno; ?>/" class="btn btn-sm btn-primary"><i class="fas fa-id-card me-1" aria-hidden="true"></i>Ver ficha</a>
									<?php endif; ?>
									<a href="<?php echo APP_URL; ?>reporteAsistencia/" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Volver</a>
								</div>
							</div>
						</div>
					</div>

					<!-- ============ Resumen y calendario ============ -->
					<div class="col-12 col-lg-8 col-xl-9">
						<?php /* El resumen se calcula en el navegador con las marcas del
						         mes que muestra el calendario, y se recalcula al cambiar
						         de mes. Misma fórmula que Insights: (presentes + atrasos)
						         entre todos los días marcados. */ ?>
						<div class="ds-asist__resumen" aria-live="polite">
							<div class="ds-asist__kpi ds-asist__kpi--principal">
								<span class="ds-asist__kpi-valor" id="kpi-porcentaje">—</span>
								<span class="ds-asist__kpi-rotulo">Asistencia del mes</span>
							</div>
							<div class="ds-asist__kpi" data-marca="PRESENTE"><span class="ds-asist__kpi-valor" id="kpi-PRESENTE">0</span><span class="ds-asist__kpi-rotulo"><i class="ds-asist__punto ds-asist__punto--presente"></i>Presente</span></div>
							<div class="ds-asist__kpi" data-marca="ATRASO"><span class="ds-asist__kpi-valor" id="kpi-ATRASO">0</span><span class="ds-asist__kpi-rotulo"><i class="ds-asist__punto ds-asist__punto--atraso"></i>Atraso</span></div>
							<div class="ds-asist__kpi" data-marca="JUSTIFICADO"><span class="ds-asist__kpi-valor" id="kpi-JUSTIFICADO">0</span><span class="ds-asist__kpi-rotulo"><i class="ds-asist__punto ds-asist__punto--justificado"></i>Justificado</span></div>
							<div class="ds-asist__kpi" data-marca="FALTA"><span class="ds-asist__kpi-valor" id="kpi-FALTA">0</span><span class="ds-asist__kpi-rotulo"><i class="ds-asist__punto ds-asist__punto--falta"></i>Falta</span></div>
						</div>

						<div class="card mt-3">
							<div class="card-body ds-asist__calendario">
								<div id="calendar"></div>
								<p class="small text-body-secondary mb-0 mt-2" id="asist-nota"></p>
							</div>
						</div>
					</div>

				</div>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<?php /* Esta pantalla no envía formularios, pero la barra superior sí:
	         «Cambiar contraseña» va por ajax.js y avisa con SweetAlert. */ ?>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<!-- FullCalendar 5.10.1 -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/main.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/locales/es.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var marcas = <?php echo $eventosJson; ?>;
		var TIPOS = ['PRESENTE', 'ATRASO', 'JUSTIFICADO', 'FALTA'];

		/* Cada día marcado tiñe la celda entera (evento de fondo): se lee de
		   un vistazo y cabe en un teléfono, donde «PRESENTE» escrito en una
		   columna de 50 px salía cortado. El rótulo queda en el title. */
		var eventos = marcas.map(function (m) {
			return { start: m.start, allDay: true, display: 'background', color: m.color,
			         title: m.title, classNames: ['ds-asist__marca', 'ds-asist__marca--' + String(m.title).toLowerCase()] };
		});

		/* Abre en el mes actual si tiene marcas; si no, en el último mes que
		   las tenga. Antes abría siempre en el actual y, si el alumno no había
		   venido aún, la pantalla parecía vacía. */
		var hoy = new Date();
		var mesActual = hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0');
		var ultima = marcas.reduce(function (max, m) { return m.start > max ? m.start : max; }, '');
		var inicio = (ultima && !marcas.some(function (m) { return m.start.slice(0, 7) === mesActual; })) ? ultima : undefined;

		function resumir(info) {
			var desde = info.view.currentStart, hasta = info.view.currentEnd;
			var cuenta = { PRESENTE: 0, ATRASO: 0, JUSTIFICADO: 0, FALTA: 0 };
			marcas.forEach(function (m) {
				var d = new Date(m.start + 'T00:00:00');
				if (d >= desde && d < hasta && cuenta.hasOwnProperty(m.title)) { cuenta[m.title]++; }
			});
			var total = TIPOS.reduce(function (s, t) { return s + cuenta[t]; }, 0);
			TIPOS.forEach(function (t) { document.getElementById('kpi-' + t).textContent = cuenta[t]; });
			document.getElementById('kpi-porcentaje').textContent =
				total ? ((cuenta.PRESENTE + cuenta.ATRASO) / total * 100).toFixed(1).replace('.', ',') + ' %' : '—';
			document.getElementById('asist-nota').textContent = total
				? total + (total === 1 ? ' día marcado' : ' días marcados') + ' en el mes. Los atrasos cuentan como asistencia.'
				: 'No hay asistencia registrada en este mes.' + (ultima ? ' Use las flechas para ver otros meses.' : '');
		}

		var calendario = new FullCalendar.Calendar(document.getElementById('calendar'), {
			locale: 'es',
			initialView: 'dayGridMonth',
			initialDate: inicio,
			height: 'auto',
			fixedWeekCount: false,
			headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
			buttonText: { today: 'Hoy' },
			events: eventos,
			eventDidMount: function (info) { info.el.title = info.event.title; },
			datesSet: resumir
		});
		calendario.render();
	});
	</script>
  </body>
</html>
