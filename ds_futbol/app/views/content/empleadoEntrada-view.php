<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\empleadoController;
	$insEmpleado = new empleadoController();

	/*
	| El empleado sale de la sesión (empleado_actual(), el usuario_empleadoid
	| del login). ANTES se buscaba por $_SESSION['identificacion'], que nadie
	| guarda: la pantalla nunca encontraba al empleado y no se podía marcar.
	*/
	$empleado = $insEmpleado->empleadoDeLaSesion();
	$hoy      = $empleado ? $insEmpleado->marcacionesDeHoy((int)$empleado['empleado_id']) : [];
	$ultima   = $hoy ? end($hoy) : null;

	/* Lo que toca ahora. El servidor vuelve a decidirlo al registrar. */
	$toca      = ($ultima && $ultima['asistencia_tipo'] === 'E') ? 'S' : 'E';
	$dentro    = $toca === 'S';

	/* Tiempo trabajado hoy: cada entrada con su salida y, si sigue dentro,
	   hasta ahora. */
	$segundos = 0; $desde = null;
	foreach ($hoy as $m) {
		$t = strtotime($m['asistencia_hora']);
		if ($m['asistencia_tipo'] === 'E') { $desde = $t; }
		elseif ($desde !== null) { $segundos += $t - $desde; $desde = null; }
	}
	if ($desde !== null) { $segundos += time() - $desde; }
	$trabajado = $segundos > 0 ? (intdiv($segundos, 3600) ? intdiv($segundos, 3600) . ' h ' : '') . intdiv($segundos % 3600, 60) . ' min' : '';

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$foto = ($empleado && trim((string)$empleado['empleado_foto']) !== '')
		? media_url('empleado', $empleado['empleado_foto'])
		: ds_recurso('ds_futbol/app/views/dist/img/default.png');

	$DIAS  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
	$MESES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	$fechaLarga = $DIAS[(int)date('w')] . ' ' . (int)date('j') . ' de ' . $MESES[(int)date('n')] . ' de ' . date('Y');
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Marcar entrada y salida';
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
							<h4 class="m-0">Marcar entrada y salida</h4>
						</div>
						<div class="col-sm-5">
							<ol class="breadcrumb float-sm-end mb-0">
								<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
								<li class="breadcrumb-item active" aria-current="page">Marcar</li>
							</ol>
						</div>
					</div>
				</div>
			</div>

			<section class="app-content">
				<div class="container-fluid">
				<?php if (!$empleado): ?>

					<div class="card ds-marca">
						<div class="card-body text-center py-5">
							<i class="fas fa-user-clock fa-3x text-body-secondary mb-3" aria-hidden="true"></i>
							<h5>Su usuario no tiene una ficha de empleado activa</h5>
							<p class="text-body-secondary mb-0">Para marcar entrada y salida, administración debe vincular su usuario a su ficha de empleado en DigiSports Core.</p>
						</div>
					</div>

				<?php else: ?>

					<div class="row g-3 justify-content-center">
						<div class="col-12 col-lg-7 col-xl-6">

							<!-- =================== Marcar =================== -->
							<div class="card ds-marca">
								<div class="card-body">
									<div class="ds-marca__quien">
										<img src="<?php echo $h($foto); ?>" alt="" class="ds-marca__foto" width="56" height="56">
										<div class="min-w-0">
											<div class="ds-marca__nombre"><?php echo $h($empleado['empleado_nombre']); ?></div>
											<div class="small text-body-secondary"><?php echo implode(' · ', array_filter([$h($empleado['especialidad']), $h($empleado['sede_nombre'])], 'strlen')); ?></div>
										</div>
										<span class="badge ms-auto <?php echo $dentro ? 'text-bg-success' : 'text-bg-secondary'; ?>" id="marca-estado">
											<?php echo $dentro ? 'Dentro desde ' . date('H:i', strtotime($ultima['asistencia_hora'])) : ($ultima ? 'Salió a las ' . date('H:i', strtotime($ultima['asistencia_hora'])) : 'Sin marcar hoy'); ?>
										</span>
									</div>

									<div class="ds-marca__reloj" aria-live="off">
										<?php /* data-servidor: la hora del servidor, en milisegundos; el
										         reloj avanza desde ella y no desde la del dispositivo. */ ?>
										<div class="ds-marca__hora" id="reloj" data-servidor="<?php echo (int)round(microtime(true) * 1000); ?>" data-dia="<?php echo date('Y-m-d'); ?>"
										     data-sincro="<?php echo $h(ds_recurso('ds_futbol/app/views/dist/img/default.png')); ?>" title="Hora del servidor (Ecuador)"><?php echo date('H:i:s'); ?></div>
										<div class="ds-marca__fecha"><?php echo $fechaLarga; ?></div>
									</div>

									<?php /* El empleado y el tipo los decide el servidor
									         (AsistenciaCoordenadas): aquí sólo viaja la ubicación. */ ?>
									<form class="FormularioAjax" id="form-marca" action="<?php echo APP_URL; ?>app/ajax/empleadoAjax.php" method="POST" autocomplete="off">
										<input type="hidden" name="modulo_asistencia" value="coordenadas">
										<input type="hidden" id="latitude" name="latitude">
										<input type="hidden" id="longitude" name="longitude">

										<button type="submit" class="btn btn-lg w-100 ds-marca__boton <?php echo $dentro ? 'btn-danger' : 'btn-success'; ?>" id="marca-boton" disabled>
											<i class="fas <?php echo $dentro ? 'fa-right-from-bracket' : 'fa-right-to-bracket'; ?> me-2" aria-hidden="true"></i><?php echo $dentro ? 'Registrar salida' : 'Registrar entrada'; ?>
										</button>
									</form>

									<div class="ds-marca__ubicacion" id="ubicacion" role="status" aria-live="polite">
										<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Obteniendo su ubicación…
									</div>
								</div>
							</div>

							<!-- =================== Hoy =================== -->
							<div class="card ds-marca mt-3">
								<div class="card-header d-flex align-items-center">
									<h3 class="card-title mb-0"><i class="far fa-clock me-2" aria-hidden="true"></i>Marcaciones de hoy</h3>
									<?php if ($trabajado !== ''): ?>
										<span class="ms-auto small text-body-secondary" title="Entre cada entrada y su salida<?php echo $dentro ? ', y hasta ahora' : ''; ?>">Trabajado: <strong class="text-body-emphasis"><?php echo $trabajado; ?></strong></span>
									<?php endif; ?>
								</div>
								<div class="card-body p-0">
									<?php if (!$hoy): ?>
										<p class="text-body-secondary text-center my-4 px-3">Aún no hay marcaciones hoy.</p>
									<?php else: ?>
										<ol class="list-group list-group-flush ds-marca__lista">
											<?php foreach ($hoy as $m): $entrada = $m['asistencia_tipo'] === 'E'; ?>
												<li class="list-group-item d-flex align-items-center gap-3">
													<span class="ds-marca__icono <?php echo $entrada ? 'text-bg-success' : 'text-bg-danger'; ?>" aria-hidden="true"><i class="fas <?php echo $entrada ? 'fa-right-to-bracket' : 'fa-right-from-bracket'; ?>"></i></span>
													<div class="me-auto">
														<strong><?php echo date('H:i', strtotime($m['asistencia_hora'])); ?></strong>
														<span class="text-body-secondary ms-1"><?php echo $entrada ? 'Entrada' : 'Salida'; ?></span>
													</div>
													<a href="https://www.google.com/maps?q=<?php echo (float)$m['asistencia_latitud'] . ',' . (float)$m['asistencia_longitud']; ?>" target="_blank" rel="noopener" class="small text-nowrap"><i class="fas fa-location-dot me-1" aria-hidden="true"></i>Ver lugar</a>
												</li>
											<?php endforeach; ?>
										</ol>
									<?php endif; ?>
								</div>
							</div>

						</div>
					</div>

				<?php endif; ?>
				</div>
			</section>
		</div>

		<?php require_once "app/views/inc/footer.php"; ?>
	</div>

	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>

	<?php if ($empleado): ?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/*
		| El reloj muestra la hora del SERVIDOR, no la del dispositivo.
		|
		| La que se guarda al marcar ya era la del servidor; la pantalla, en
		| cambio, enseñaba la del teléfono, que se puede adelantar o atrasar
		| a mano y no coincidía con lo registrado. Ahora parte de la hora del
		| servidor (data-servidor) y avanza con performance.now(), que es
		| monótono: cambiar la hora del teléfono no lo mueve. Se pinta en la
		| zona de Ecuador, aunque el teléfono esté en otra.
		|
		| Algunos teléfonos detienen performance.now() en reposo: al volver a
		| la pantalla se resincroniza con la cabecera Date de una petición
		| ligera al propio servidor. Si cambió el día, se recarga (las
		| marcaciones de hoy son otras).
		*/
		var reloj = document.getElementById('reloj');
		var base = +reloj.dataset.servidor, inicio = performance.now();
		var ZONA = 'America/Guayaquil';
		var formatoHora = new Intl.DateTimeFormat('es-EC', { timeZone: ZONA, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' });
		var formatoDia = new Intl.DateTimeFormat('en-CA', { timeZone: ZONA });   /* AAAA-MM-DD */
		function ahora() { return new Date(base + (performance.now() - inicio)); }
		function pintar() {
			var t = ahora();
			reloj.textContent = formatoHora.format(t);
			if (formatoDia.format(t) !== reloj.dataset.dia) { location.reload(); }
		}
		function sincronizar() {
			fetch(reloj.dataset.sincro, { method: 'HEAD', cache: 'no-store' }).then(function (r) {
				var fecha = Date.parse(r.headers.get('Date') || '');
				/* La cabecera Date tiene segundos enteros: +500 ms centra el error. */
				if (!isNaN(fecha)) { base = fecha + 500; inicio = performance.now(); pintar(); }
			}).catch(function () {});
		}
		pintar();
		setInterval(pintar, 1000);
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible') { sincronizar(); }
		});

		/*
		| La ubicación. El botón de marcar espera a tenerla: sin ella el
		| servidor rechaza la marcación.
		|
		| TRES ESTADOS DEL PERMISO (Permissions API)
		|   granted   se pide sola al abrir.
		|   prompt    NO se dispara la ventana del navegador al abrir: se
		|             muestra «Activar mi ubicación», que dice para qué es, y
		|             la ventana sale al pulsarlo. Pedirla sin contexto es lo
		|             que hace que la gente pulse «Bloquear».
		|   denied    el navegador NO deja volver a preguntar: sólo la
		|             persona puede desbloquearla en los ajustes. Se le dan
		|             los pasos de SU dispositivo y, si el navegador avisa del
		|             cambio, se continúa solo.
		| ANTES: alert() y, después, un mensaje sin salida.
		*/
		var boton = document.getElementById('marca-boton');
		var aviso = document.getElementById('ubicacion');
		var ua = navigator.userAgent;
		var dispositivo = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && 'ontouchend' in document) ? 'ios'
			: /Android/.test(ua) ? 'android' : 'computador';

		var PASOS = {
			android: ['Toque el icono <i class="fas fa-sliders" aria-hidden="true"></i> o el candado, a la izquierda de la dirección de la página.',
			          'Entre en <strong>Permisos</strong> y ponga <strong>Ubicación</strong> en <strong>Permitir</strong>.',
			          'Revise también que la <strong>ubicación del teléfono</strong> esté encendida (deslice la barra de arriba).'],
			ios:     ['En Safari, toque <strong>aA</strong> (o el icono a la izquierda de la dirección) y entre en <strong>Configuración del sitio web</strong>.',
			          'En <strong>Ubicación</strong>, elija <strong>Permitir</strong>.',
			          'Si no aparece: <strong>Ajustes › Privacidad y seguridad › Localización</strong>, actívela y en <strong>Safari</strong> elija <strong>Al usar la app</strong>.'],
			computador: ['Haga clic en el icono a la izquierda de la dirección de la página (candado o ajustes).',
			          'En <strong>Ubicación</strong>, elija <strong>Permitir</strong>.',
			          'Si el navegador lo pide, recargue la página.']
		};
		var NOMBRES = { android: 'Android', ios: 'iPhone o iPad', computador: 'computador' };
		var TITULOS = { android: 'Android', ios: 'iPhone o iPad', computador: 'Computador' };

		function lista(clave) {
			return '<ol class="ds-marca__pasos">' + PASOS[clave].map(function (p) { return '<li>' + p + '</li>'; }).join('') + '</ol>';
		}
		function mostrar(html, clase) {
			aviso.className = 'ds-marca__ubicacion ' + (clase || '');
			aviso.innerHTML = html;
			aviso.querySelectorAll('[data-ubicacion="pedir"]').forEach(function (b) { b.addEventListener('click', obtener); });
		}

		/* Sin decidir: se explica y se pide con un toque. */
		function invitar() {
			boton.disabled = true;
			mostrar('<div class="ds-marca__permiso">'
				+ '<p class="mb-2"><i class="fas fa-location-crosshairs me-1" aria-hidden="true"></i>Para marcar se registra <strong>dónde</strong> está. Su ubicación sólo se guarda al marcar.</p>'
				+ '<button type="button" class="btn btn-primary w-100" data-ubicacion="pedir" id="ubicacion-activar"><i class="fas fa-location-dot me-2" aria-hidden="true"></i>Activar mi ubicación</button>'
				+ '</div>');
		}

		/* Bloqueada: los pasos de este dispositivo, y los de los otros a mano. */
		function bloqueada() {
			boton.disabled = true;
			var otros = Object.keys(PASOS).filter(function (k) { return k !== dispositivo; });
			mostrar('<div class="ds-marca__permiso ds-marca__permiso--bloqueado text-start">'
				+ '<p class="fw-semibold mb-2"><i class="fas fa-location-dot me-1" aria-hidden="true"></i>La ubicación está bloqueada para esta página</p>'
				+ '<p class="small mb-2">El navegador no deja volver a preguntar: hay que permitirla una vez desde sus ajustes. En su ' + NOMBRES[dispositivo] + ':</p>'
				+ lista(dispositivo)
				+ '<button type="button" class="btn btn-primary w-100 mt-1" data-ubicacion="pedir" id="ubicacion-reintentar"><i class="fas fa-rotate me-2" aria-hidden="true"></i>Ya la activé, reintentar</button>'
				+ '<details class="small mt-2"><summary>¿Usa otro dispositivo?</summary>'
				+ otros.map(function (k) { return '<p class="fw-semibold mt-2 mb-1">' + TITULOS[k] + '</p>' + lista(k); }).join('')
				+ '</details></div>');
		}

		function obtener() {
			boton.disabled = true;
			mostrar('<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Obteniendo su ubicación…');
			if (!window.isSecureContext || !navigator.geolocation) {
				mostrar('<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Este navegador no puede dar la ubicación en esta dirección. Abra la página con <strong>https://</strong> en el navegador del teléfono.', 'text-danger');
				return;
			}
			navigator.geolocation.getCurrentPosition(function (pos) {
				var lat = pos.coords.latitude, lon = pos.coords.longitude, precision = Math.round(pos.coords.accuracy);
				document.getElementById('latitude').value = lat;
				document.getElementById('longitude').value = lon;
				boton.disabled = false;
				mostrar('<i class="fas fa-location-dot me-1 text-success" aria-hidden="true"></i>Ubicación lista'
					+ (precision ? ' <span class="text-body-secondary">(± ' + precision + ' m)</span>' : '')
					+ ' · <a href="https://www.google.com/maps?q=' + lat + ',' + lon + '" target="_blank" rel="noopener">Ver en el mapa</a>'
					+ ' · <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-ubicacion="pedir" id="ubicacion-actualizar">Actualizar</button>');
			}, function (error) {
				if (error.code === 1) { bloqueada(); return; }
				mostrar('<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>'
					+ (error.code === 3 ? 'La ubicación tardó demasiado.' : 'No se pudo obtener su ubicación.')
					+ ' Revise que la ubicación (GPS) del dispositivo esté encendida.'
					+ ' <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-ubicacion="pedir" id="ubicacion-reintentar">Reintentar</button>', 'text-danger');
			}, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
		}

		/* Según el estado del permiso; sin Permissions API (navegadores
		   antiguos), se invita a activarla con el botón. */
		if (navigator.permissions && navigator.permissions.query) {
			navigator.permissions.query({ name: 'geolocation' }).then(function (estado) {
				function segun() {
					if (estado.state === 'granted') { obtener(); }
					else if (estado.state === 'denied') { bloqueada(); }
					else { invitar(); }
				}
				segun();
				/* Si la desbloquea en los ajustes sin salir de la página. */
				estado.onchange = segun;
			}).catch(invitar);
		} else {
			invitar();
		}
	});
	</script>
	<?php endif; ?>
  </body>
</html>
