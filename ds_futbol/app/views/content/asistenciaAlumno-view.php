<?php
	use app\controllers\asistenciaController;
	$insAlumno = new asistenciaController();

	$horario_id = (int)ds_id_de_url($url, 1, APP_URL . 'asistencia/');

	$horario = $insAlumno->resumenHorario($horario_id);
	if (!$horario) {
		/* Horario inexistente o eliminado: a la lista de horarios. */
		header("Location: " . APP_URL . 'asistencia/');
		exit();
	}
	$puede = $insAlumno->puedeTomarAsistencia($horario_id);

	/*
	| La fecha. El profesor marca HOY; administración (roles 1 y 2) puede
	| corregir otros días, nunca futuros. El servidor lo vuelve a comprobar
	| en cada marca (fechaDeMarca). ANTES la fecha viajaba en cada botón y
	| el servidor aceptaba cualquiera.
	*/
	$esAdmin = in_array((int)($_SESSION['rol'] ?? 0), [1, 2], true);
	$hoy     = date('Y-m-d');
	$fecha   = $hoy;
	if ($esAdmin && isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha']) && $_GET['fecha'] <= $hoy) {
		$fecha = $_GET['fecha'];
	}

	$alumnos = $puede ? $insAlumno->alumnosParaAsistencia($horario_id, $fecha) : [];

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$DIAS = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
	$LARGOS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
	$MESES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	$fechaLarga = static fn(string $f) => $LARGOS[(int)date('N', strtotime($f))] . ' ' . (int)date('j', strtotime($f)) . ' de ' . $MESES[(int)date('n', strtotime($f))];

	/* Lo que necesita el script, en JSON dentro de la página. */
	$datosJs = [
		'horario' => $horario_id,
		'fecha'   => $fecha,
		'hoy'     => $hoy,
		'url'     => APP_URL . 'app/ajax/asistenciaAjax.php',
		'alumnos' => array_map(static fn($a) => [
			'id' => (int)$a['alumno_id'],
			'apellidos' => html_entity_decode((string)$a['apellidos'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			'nombres'   => html_entity_decode((string)$a['nombres'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			'categoria' => (string)$a['categoria'],
			'marca'     => (string)$a['marca'],
		], $alumnos),
	];
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Tomar asistencia';
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
						<h4 class="m-0">Tomar asistencia</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>asistencia/">Registrar asistencia</a></li>
							<li class="breadcrumb-item active" aria-current="page">Lista</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
				<div class="ds-toma">

					<!-- =================== El horario y la fecha =================== -->
					<div class="card mb-3">
						<div class="card-body ds-toma__cabecera">
							<div class="min-w-0">
								<div class="ds-toma__horario"><?php echo $h($horario['horario_nombre']); ?>
									<?php if ($horario['horario_detalle']): ?><span class="fw-normal text-body-secondary">· <?php echo $h($horario['horario_detalle']); ?></span><?php endif; ?>
								</div>
								<div class="small text-body-secondary">
									<i class="fas fa-location-dot me-1" aria-hidden="true"></i><?php echo implode(' · ', array_filter([$h($horario['sede_nombre']), $h($horario['lugares'])], 'strlen')); ?>
									<?php if ($horario['inicio']): ?> · <?php echo substr($horario['inicio'], 0, 5) . ' – ' . substr($horario['fin'], 0, 5); ?><?php endif; ?>
									<?php if ($horario['dias']): ?> · <?php echo implode(' ', array_map(static fn($d) => $DIAS[(int)$d] ?? '', explode(',', $horario['dias']))); ?><?php endif; ?>
								</div>
							</div>
							<div class="ds-toma__fecha">
								<?php if ($esAdmin): ?>
									<label for="toma-fecha" class="form-label small mb-1">Fecha</label>
									<div class="input-group">
										<input type="date" class="form-control" id="toma-fecha" value="<?php echo $fecha; ?>" max="<?php echo $hoy; ?>">
										<button type="button" class="btn btn-secondary" id="toma-hoy">Hoy</button>
									</div>
								<?php else: ?>
									<span class="badge text-bg-secondary ds-toma__hoy"><i class="far fa-calendar me-1" aria-hidden="true"></i>Hoy, <?php echo $fechaLarga($hoy); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>

				<?php if (!$puede): ?>

					<div class="alert alert-warning">
						<i class="fas fa-lock me-1" aria-hidden="true"></i>Este horario no es suyo ni de una de sus sedes: no puede tomar su asistencia.
					</div>

				<?php else: ?>

					<!-- =================== Avance y guardado (fijo arriba) =================== -->
					<div class="ds-toma__barra" id="toma-barra">
						<div class="ds-toma__avance">
							<div class="d-flex justify-content-between align-items-baseline gap-2">
								<strong id="toma-cuenta">0 de 0</strong>
								<span class="small" id="toma-estado" role="status" aria-live="polite"></span>
							</div>
							<div class="progress mt-1" style="height:.4rem;" role="presentation"><div class="progress-bar bg-success" id="toma-progreso" style="width:0%"></div></div>
							<div class="ds-toma__totales small mt-1" id="toma-totales"></div>
						</div>
					</div>

					<div class="d-flex flex-wrap gap-2 mb-3">
						<input type="search" class="form-control ds-toma__buscar" id="toma-buscar" placeholder="Buscar alumno…" aria-label="Buscar alumno">
						<button type="button" class="btn btn-success" id="toma-todos"><i class="fas fa-check-double me-1" aria-hidden="true"></i>Presentes los que faltan</button>
					</div>

					<!-- =================== La lista =================== -->
					<ol class="ds-toma__lista list-unstyled" id="toma-lista" aria-label="Alumnos del horario"></ol>
					<p class="text-body-secondary text-center d-none" id="toma-vacia">Este horario no tiene alumnos activos asignados.</p>

				<?php endif; ?>

					<div class="mt-3">
						<a href="<?php echo APP_URL; ?>asistencia/" class="btn btn-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Volver a mis horarios</a>
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
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>

	<?php if ($puede): ?>
	<script id="toma-datos" type="application/json"><?php echo json_encode($datosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/*
		| Tomar asistencia SIN recargar la página.
		|
		| Cada toque pinta la marca al instante y la manda al servidor con
		| fetch. La respuesta se LEE: si el servidor la rechaza, la marca
		| vuelve a la anterior y se dice por qué. Si no hay señal, queda
		| PENDIENTE y se reintenta sola (cada pocos segundos y al volver la
		| conexión); salir de la página con marcas pendientes avisa.
		|
		| ANTES: jQuery $.ajax que no miraba la respuesta (pintaba «guardado»
		| aunque la sesión hubiese caducado) y un formulario de fecha que
		| recargaba la página entera.
		*/
		var D = JSON.parse(document.getElementById('toma-datos').textContent);
		var ESTADOS = [
			{ v: 'P', texto: 'Presente', corto: 'Presente' },
			{ v: 'A', texto: 'Atraso', corto: 'Atraso' },
			{ v: 'F', texto: 'Falta', corto: 'Falta' },
			{ v: 'J', texto: 'Justificado', corto: 'Justif.' }
		];
		var NOMBRE = { P: ['presente', 'presentes'], A: ['atraso', 'atrasos'], F: ['falta', 'faltas'], J: ['justificado', 'justificados'] };
		var lista = document.getElementById('toma-lista');
		var csrf = (document.querySelector('meta[name="ds-csrf"]') || {}).content || '';

		/* confirmada: lo que dice el servidor. pendientes: alumno → estado por enviar. */
		var alumnos = [], confirmada = {}, pendientes = {}, enviando = false, reintento = null, fecha = D.fecha;

		function el(tag, clase, texto) { var e = document.createElement(tag); if (clase) e.className = clase; if (texto != null) e.textContent = texto; return e; }

		function pintar() {
			lista.innerHTML = '';
			document.getElementById('toma-vacia').classList.toggle('d-none', alumnos.length > 0);
			alumnos.forEach(function (a) {
				var li = el('li', 'ds-toma__alumno'); li.dataset.id = a.id;
				var quien = el('div', 'ds-toma__quien');
				quien.appendChild(el('div', 'ds-toma__nombre', a.apellidos + ' ' + a.nombres));
				var meta = el('div', 'ds-toma__meta');
				meta.appendChild(el('span', '', a.categoria ? 'Categoría ' + a.categoria : ''));
				meta.appendChild(el('span', 'ds-toma__guardado'));
				quien.appendChild(meta);
				li.appendChild(quien);
				var grupo = el('div', 'ds-toma__opciones'); grupo.setAttribute('role', 'group');
				grupo.setAttribute('aria-label', 'Asistencia de ' + a.nombres + ' ' + a.apellidos);
				ESTADOS.forEach(function (e) {
					var b = el('button', 'ds-toma__opcion'); b.type = 'button'; b.dataset.estado = e.v;
					b.innerHTML = '<span class="ds-toma__largo"></span><span class="ds-toma__corto"></span>';
					b.firstChild.textContent = e.texto; b.lastChild.textContent = e.corto;
					grupo.appendChild(b);
				});
				li.appendChild(grupo);
				lista.appendChild(li);
				fila(a.id);
			});
			filtrar(); resumen();
		}

		/* El aspecto de una fila: el estado a la vista (pendiente o confirmado). */
		function fila(id, aviso) {
			var li = lista.querySelector('li[data-id="' + id + '"]'); if (!li) return;
			var actual = pendientes[id] || confirmada[id] || '';
			li.querySelectorAll('.ds-toma__opcion').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.estado === actual ? 'true' : 'false'); });
			li.classList.toggle('ds-toma__alumno--marcado', actual !== '');
			var g = li.querySelector('.ds-toma__guardado');
			g.className = 'ds-toma__guardado';
			if (aviso) { g.textContent = aviso; g.classList.add('text-danger'); }
			else if (pendientes[id]) { g.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Guardando…'; g.classList.add('text-body-secondary'); }
			else if (confirmada[id]) { g.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Guardado'; g.classList.add('text-success-emphasis'); }
			else { g.textContent = ''; }
		}

		function resumen() {
			var n = { P: 0, A: 0, F: 0, J: 0 }, marcados = 0;
			alumnos.forEach(function (a) { var e = pendientes[a.id] || confirmada[a.id]; if (e) { n[e]++; marcados++; } });
			document.getElementById('toma-cuenta').textContent = marcados + ' de ' + alumnos.length + ' marcados';
			document.getElementById('toma-progreso').style.width = (alumnos.length ? marcados / alumnos.length * 100 : 0) + '%';
			document.getElementById('toma-totales').textContent = ESTADOS.map(function (e) { return n[e.v] + ' ' + NOMBRE[e.v][n[e.v] === 1 ? 0 : 1]; }).join(' · ');
			var cola = Object.keys(pendientes).length, estado = document.getElementById('toma-estado');
			estado.className = 'small';
			if (!cola) { estado.innerHTML = alumnos.length ? '<i class="fas fa-check me-1" aria-hidden="true"></i>Todo guardado' : ''; estado.classList.add('text-success-emphasis'); }
			else if (!navigator.onLine || reintento) { estado.innerHTML = '<i class="fas fa-wifi me-1" aria-hidden="true"></i>Sin conexión: ' + cola + ' por enviar'; estado.classList.add('text-danger'); }
			else { estado.textContent = 'Guardando ' + cola + '…'; estado.classList.add('text-body-secondary'); }
			document.getElementById('toma-todos').disabled = marcados === alumnos.length;
		}

		/* Envía la cola de una en una, con la marca más reciente de cada alumno. */
		function enviar() {
			if (enviando) return;
			var ids = Object.keys(pendientes); if (!ids.length) { resumen(); return; }
			var id = ids[0], estado = pendientes[id];
			var datos = new FormData();
			datos.append('modulo_asistencia', 'asistencia'); datos.append('horario_id', D.horario);
			datos.append('alumno_id', id); datos.append('estado', estado); datos.append('fecha', fecha); datos.append('ds_csrf', csrf);
			enviando = true;
			var corte = new AbortController(), reloj = setTimeout(function () { corte.abort(); }, 15000);
			fetch(D.url, { method: 'POST', body: datos, credentials: 'same-origin', signal: corte.signal })
				.then(function (r) { return r.text(); })
				.then(function (texto) {
					var j = null; try { j = JSON.parse(texto); } catch (e) {}
					if (j && j.ok) {
						confirmada[id] = j.estado;
						if (pendientes[id] === estado) { delete pendientes[id]; }   /* si volvió a tocar, va la nueva */
						fila(id);
					} else {
						/* Rechazo del servidor (o la sesión caducó: llega HTML, no JSON). No se reintenta. */
						delete pendientes[id];
						fila(id, j && j.texto ? j.texto : 'No se guardó: su sesión pudo caducar. Vuelva a entrar.');
						if (!j) { Swal.fire({ icon: 'warning', title: 'Sesión caducada', text: 'Vuelva a entrar para seguir tomando asistencia.', confirmButtonText: 'Aceptar' }); }
					}
					reintento = null;
				})
				.catch(function () {
					/* Sin señal o tiempo agotado: queda pendiente y se reintenta. */
					if (!reintento) { reintento = setTimeout(function () { reintento = null; enviar(); }, 5000); }
				})
				.finally(function () {
					clearTimeout(reloj); enviando = false; resumen();
					if (!reintento && Object.keys(pendientes).length) { enviar(); }
				});
		}

		function marcar(id, estado) {
			pendientes[id] = estado; fila(id); resumen(); enviar();
		}

		lista.addEventListener('click', function (ev) {
			var b = ev.target.closest('.ds-toma__opcion'); if (!b) return;
			var id = b.closest('li').dataset.id;
			if ((pendientes[id] || confirmada[id]) === b.dataset.estado) return;   /* ya es esa */
			marcar(id, b.dataset.estado);
		});

		document.getElementById('toma-todos').addEventListener('click', function () {
			var faltan = alumnos.filter(function (a) { return !(pendientes[a.id] || confirmada[a.id]); });
			if (!faltan.length) return;
			Swal.fire({ icon: 'question', title: 'Marcar presentes', text: 'Se marcarán como presentes los ' + faltan.length + ' alumnos sin marcar.',
				showCancelButton: true, confirmButtonText: 'Sí, marcar', cancelButtonText: 'Cancelar' })
				.then(function (r) { if (r.isConfirmed) { faltan.forEach(function (a) { pendientes[a.id] = 'P'; fila(a.id); }); resumen(); enviar(); } });
		});

		function filtrar() {
			var q = document.getElementById('toma-buscar').value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
			lista.querySelectorAll('li').forEach(function (li) {
				var t = li.querySelector('.ds-toma__nombre').textContent.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
				li.hidden = q !== '' && t.indexOf(q) === -1;
			});
		}
		document.getElementById('toma-buscar').addEventListener('input', filtrar);

		function cargar(lista_) {
			alumnos = lista_; confirmada = {}; pendientes = {};
			alumnos.forEach(function (a) { if (a.marca) confirmada[a.id] = a.marca; });
			pintar();
		}
		cargar(D.alumnos);

		/* Administración: otra fecha, sin recargar. */
		var campoFecha = document.getElementById('toma-fecha');
		if (campoFecha) {
			function cambiarFecha(nueva) {
				if (Object.keys(pendientes).length) { Swal.fire({ icon: 'info', title: 'Espere', text: 'Aún hay marcas por guardar.', confirmButtonText: 'Aceptar' }); campoFecha.value = fecha; return; }
				var datos = new FormData();
				datos.append('modulo_asistencia', 'cargar_lista'); datos.append('horario_id', D.horario); datos.append('fecha', nueva); datos.append('ds_csrf', csrf);
				fetch(D.url, { method: 'POST', body: datos, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
					if (!j.ok) { Swal.fire({ icon: 'error', title: 'No se pudo cargar', text: j.texto, confirmButtonText: 'Aceptar' }); campoFecha.value = fecha; return; }
					fecha = j.fecha; cargar(j.alumnos);
					history.replaceState(null, '', location.pathname + (fecha === D.hoy ? '' : '?fecha=' + fecha));
				}).catch(function () { Swal.fire({ icon: 'error', title: 'Sin conexión', text: 'No se pudo cargar la lista de esa fecha.', confirmButtonText: 'Aceptar' }); campoFecha.value = fecha; });
			}
			campoFecha.addEventListener('change', function () { if (campoFecha.value) cambiarFecha(campoFecha.value); });
			document.getElementById('toma-hoy').addEventListener('click', function () { campoFecha.value = D.hoy; cambiarFecha(D.hoy); });
		}

		window.addEventListener('online', function () { if (reintento) { clearTimeout(reintento); reintento = null; } enviar(); });
		window.addEventListener('offline', resumen);
		window.addEventListener('beforeunload', function (e) {
			if (Object.keys(pendientes).length) { e.preventDefault(); e.returnValue = ''; }
		});
	});
	</script>
	<?php endif; ?>
  </body>
</html>
