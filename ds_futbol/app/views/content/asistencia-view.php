<?php
	use app\controllers\asistenciaController;
	$insHorario = new asistenciaController();

	$_SESSION['menupadre']="Asistencia";
	$_SESSION['menuhijo']="asistencia";

	$profesorid	= $_SESSION['usuario_id'];

	$filas   = $insHorario->listarHorariosProfesor($profesorid);
	$total   = substr_count($filas, '<tr>');
	$resumen = $insHorario->resumenToma;

	$DIAS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
	$hoyTexto = $DIAS[(int)date('N')] . ' ' . date('d/m/Y');
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Asistencia';
	$extras      = array (0 => 'datatables',);
	$pwaAsistencia = true;   /* instalable y sin conexión: app/views/inc/pwa-asistencia.php */
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
						<h4 class="m-0">Registrar asistencia</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Registrar asistencia</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Sin conexión: estado y app =================== -->
				<div class="ds-offline" id="asis-offline" role="status" aria-live="polite" hidden></div>
				<div class="ds-offline ds-offline--pendiente" id="asis-instalar" hidden>
					<i class="fas fa-mobile-screen-button" aria-hidden="true"></i>
					<span id="asis-instalar-texto"><strong>Instale la app de asistencia</strong> en este celular para abrirla desde la pantalla de inicio, también sin Internet.</span>
					<button type="button" class="btn btn-sm btn-success" id="asis-instalar-boton" hidden><i class="fas fa-download me-1" aria-hidden="true"></i>Instalar</button>
				</div>

				<!-- =================== Toma de asistencia de hoy =================== -->
				<div class="ds-toma__resumen mb-3" role="status" aria-live="polite">
					<span class="ds-toma__hoy"><i class="fas fa-calendar-day me-1" aria-hidden="true"></i>Hoy, <?php echo $hoyTexto; ?></span>
					<span class="badge text-bg-warning" title="Horarios con parte de la lista marcada"><?php echo $resumen['curso']; ?> en curso</span>
					<span class="badge text-bg-danger" title="Horarios con clase hoy y sin ninguna marca"><?php echo $resumen['pendiente'] . ($resumen['pendiente'] === 1 ? ' pendiente' : ' pendientes'); ?></span>
					<span class="badge text-bg-success" title="Horarios con toda la lista marcada"><?php echo $resumen['tomada'] . ($resumen['tomada'] === 1 ? ' tomada' : ' tomadas'); ?></span>
					<a href="<?php echo APP_URL; ?>asistencia/" class="btn btn-sm ms-auto ds-toma__actualizar" title="Volver a consultar el estado (actualizado a las <?php echo date('H:i'); ?>)"><i class="fas fa-rotate me-1" aria-hidden="true"></i>Actualizar <span class="small"><?php echo date('H:i'); ?></span></a>
				</div>

				<!-- =================== Horarios =================== -->
				<div class="card ds-crud__lista" id="asistencia-horarios">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-clipboard-check me-2" aria-hidden="true"></i>Horarios registrados
							<span class="badge text-bg-secondary ms-1"><?php echo $total; ?></span>
						</h3>
						<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
					</div>
					<div class="card-body">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Horario</th>
									<th>Días y hora</th>
									<th class="ds-col-opcional">Profesor</th>
									<th>Alumnos en lista</th>
									<th>Toma de asistencia</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filas; ?>
							</tbody>
						</table>
						<p class="small text-body-secondary mb-0 mt-2">
							<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Toma de asistencia: alumnos activos de la lista con marca hoy.
							La marca es del alumno, no del horario: si está en dos horarios, cuenta en ambos.
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
		/*
		| Sin la extensión Responsive: en el teléfono cada horario es una
		| ficha (futbol.css, .ds-crud). Por defecto, primero lo que hay que
		| atender hoy: en curso, pendientes, tomadas y, al final, sin clase.
		*/
		var CLAVE = 'ds-asistencia-horarios-por-pagina', LARGOS = [10, 25, 50, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable("#example1", {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 'Todos']],
			order: [[4, 'asc']],
			columnDefs: [
				{ targets: [0, 1, 4], type: 'string' },  /* data-order: prioridad + hora */
				{ targets: 3, type: 'num' },
				{ targets: 5, orderable: false, searchable: false }
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "No tiene horarios asignados",
				info: "_START_ a _END_ de _TOTAL_ horarios",
				infoEmpty: "Sin horarios",
				infoFiltered: "(de _MAX_)",
				lengthMenu: "Mostrar _MENU_",
				search: "",
				searchPlaceholder: "Filtrar por horario, sede o profesor…",
				zeroRecords: "Ningún horario coincide con el filtro",
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
	});
	</script>

	<script id="asis-datos" type="application/json"><?php echo json_encode([
		'usuario'  => (int)($_SESSION['usuarioid'] ?? 0),
		'generada' => date('Y-m-d H:i'),
		'login'    => APP_URL . 'login/',
		/* TODAS las tomas de la lista, no sólo la página visible de la
		   tabla (DataTables quita del DOM las filas de otras páginas). */
		'tomas'    => array_values(array_unique(array_map(
			static fn($m) => APP_URL . 'asistenciaAlumno/' . (int)$m . '/',
			preg_match_all('~asistenciaAlumno/(\d+)/~', $filas, $mm) ? $mm[1] : []
		))),
	]); ?></script>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/*
		| ASISTENCIA SIN CONEXIÓN (PWA) en la lista de horarios.
		|
		| Con señal, al abrir: guarda en el celular esta lista y la toma de
		| cada horario (el service worker las descarga con sus recursos), y
		| envía lo que haya quedado pendiente. Sin señal, esta página llega
		| de la copia guardada y dice desde cuándo son esos datos.
		*/
		var D = JSON.parse(document.getElementById('asis-datos').textContent);
		var Q = window.DSAsistencia || null;
		var franja = document.getElementById('asis-offline');
		var red = navigator.onLine ? 'ok' : 'sin-red';

		function fechaCorta(t) { var p = String(t || '').split(/[- :T]/); return p.length >= 5 ? p[2] + '/' + p[1] + ' a las ' + p[3] + ':' + p[4] : ''; }
		function ahora() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }

		/* Las tomas de los horarios de esta lista (todas, vienen del servidor). */
		function enlaces() { return D.tomas || []; }

		/* Cuántas marcas tiene cada horario guardadas en el celular, junto a su botón. */
		function marcarFilas(pend) {
			var por = {};
			pend.forEach(function (m) { por[m.horario] = (por[m.horario] || 0) + 1; });
			document.querySelectorAll('#example1 a[href*="asistenciaAlumno/"]').forEach(function (a) {
				var m = a.href.match(/asistenciaAlumno\/(\d+)/), n = m ? por[m[1]] || 0 : 0;
				var b = a.parentNode.querySelector('.ds-offline-cuenta');
				if (!n) { if (b) { b.remove(); } return; }
				if (!b) { b = document.createElement('span'); b.className = 'badge text-bg-warning ds-offline-cuenta ms-1'; a.after(b); }
				b.innerHTML = '<i class="fas fa-mobile-screen me-1" aria-hidden="true"></i>' + n + ' en el celular';
				b.title = 'Marcas tomadas sin conexión que aún no llegan al servidor';
			});
		}

		function pintar() {
			if (!Q) { return; }
			Promise.all([Q.pendientes({ usuario: D.usuario }), Q.leerMeta('descarga')]).then(function (r) {
				var pend = r[0], descarga = r[1], cola = pend.length, html = '', clase = '';
				marcarFilas(pend);
				if (red === 'sin-sesion' && cola) {
					clase = 'ds-offline--sesion';
					html = '<i class="fas fa-user-lock" aria-hidden="true"></i><span><strong>Su sesión venció.</strong> Tiene ' + cola +
						(cola === 1 ? ' marca guardada' : ' marcas guardadas') + ' en el celular. <a href="' + D.login + '">Entre de nuevo</a> para enviarlas.</span>';
				} else if (red === 'sin-red' || !navigator.onLine) {
					clase = 'ds-offline--sin-red';
					html = '<i class="fas fa-wifi" aria-hidden="true"></i><span><strong>Sin conexión.</strong> Puede tomar asistencia: las marcas quedan en el celular y se envían solas al volver la señal.' +
						(cola ? ' Ahora hay <strong>' + cola + '</strong> por enviar.' : '') +
						'<span class="ds-offline__nota">Horarios y alumnos descargados el ' + fechaCorta(descarga ? descarga.fecha : D.generada) + '; los contadores son de ese momento.</span></span>';
				} else if (cola) {
					clase = 'ds-offline--pendiente';
					html = '<i class="fas fa-cloud-arrow-up" aria-hidden="true"></i><span><strong>' + cola + (cola === 1 ? ' marca' : ' marcas') + '</strong> del celular por enviar.</span>' +
						'<button type="button" class="btn btn-sm btn-primary" id="asis-enviar"><i class="fas fa-paper-plane me-1" aria-hidden="true"></i>Enviar ahora</button>';
				} else if (descarga && descarga.horarios > 0) {
					clase = 'ds-offline--listo';
					html = '<i class="fas fa-circle-check" aria-hidden="true"></i><span><strong>Lista para usar sin conexión:</strong> ' + descarga.horarios +
						(descarga.horarios === 1 ? ' horario descargado' : ' horarios descargados') + ' en este celular (' + fechaCorta(descarga.fecha) + ').</span>';
				}
				franja.hidden = html === '';
				franja.className = 'ds-offline ' + clase;
				franja.innerHTML = html;
				var b = document.getElementById('asis-enviar');
				if (b) { b.addEventListener('click', enviar); }
			});
		}

		function enviar() {
			if (!Q) { return; }
			if (!navigator.onLine) { red = 'sin-red'; pintar(); return; }
			Q.sincronizar().then(function (r) {
				red = r.estado === 'sin-red' ? 'sin-red' : (r.estado === 'sin-sesion' ? 'sin-sesion' : 'ok');
				if (r.conflictos || r.rechazadas) {
					Swal.fire({ icon: 'info', title: 'Marcas enviadas con avisos',
						text: (r.enviadas ? r.enviadas + ' se guardaron. ' : '') + (r.conflictos + r.rechazadas) +
							' no se aplicaron (ya había otra marca o pasó el plazo). Ábralas en su horario para ver el detalle.',
						confirmButtonText: 'Entendido' });
				}
				pintar();
			});
		}

		/* Descargar al celular la lista y la toma de cada horario. */
		function descargar() {
			if (!navigator.onLine || !('serviceWorker' in navigator) || !window.dsAsistenciaSW) { return; }
			var paginas = [location.href.split('?')[0]].concat(enlaces());
			var recursos = (performance.getEntriesByType ? performance.getEntriesByType('resource') : []).map(function (e) { return e.name; });
			navigator.serviceWorker.ready.then(function (reg) {
				if (!reg.active) { return; }
				var canal = new MessageChannel();
				canal.port1.onmessage = function (e) {
					if (e.data && e.data.ok && Q) {
						Q.ponerMeta('descarga', { fecha: ahora(), horarios: Math.max(0, (e.data.guardadas || 0) - 1) }).then(pintar);
					}
				};
				reg.active.postMessage({ tipo: 'precargar', paginas: paginas, recursos: recursos }, [canal.port2]);
			});
		}

		if (Q) {
			Q.listo.then(function () { pintar(); if (navigator.onLine) { enviar(); descargar(); } });
			Q.escuchar(function (m) { if (m && m.tipo === 'sincronizado') { pintar(); } });
		}
		window.addEventListener('online', function () { red = 'ok'; enviar(); descargar(); });
		window.addEventListener('offline', function () { red = 'sin-red'; pintar(); });
		document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { pintar(); if (navigator.onLine) { enviar(); } } });

		/*
		| Instalar la app. Android/Chrome ofrecen el aviso propio
		| (beforeinstallprompt); en iPhone se instala desde Compartir. Ya
		| instalada (pantalla completa), no se muestra.
		*/
		var caja = document.getElementById('asis-instalar'), boton = document.getElementById('asis-instalar-boton'), aviso = null;
		var instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
		var cerrada = false; try { cerrada = localStorage.getItem('ds-asistencia-instalar-oculto') === '1'; } catch (e) {}
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault(); aviso = e;
			if (!instalada && !cerrada) { caja.hidden = false; boton.hidden = false; }
		});
		boton.addEventListener('click', function () {
			if (!aviso) { return; }
			aviso.prompt();
			aviso.userChoice.then(function () { aviso = null; caja.hidden = true; });
		});
		window.addEventListener('appinstalled', function () { caja.hidden = true; });
		if (!instalada && !cerrada && /iphone|ipad|ipod/i.test(navigator.userAgent)) {
			document.getElementById('asis-instalar-texto').innerHTML = '<strong>Instale la app de asistencia:</strong> toque Compartir <i class="fas fa-arrow-up-from-bracket"></i> y luego «Añadir a pantalla de inicio». Funciona también sin Internet.';
			caja.hidden = false;
		}
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
  </body>
</html>
