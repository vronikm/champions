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

	/*
	| Al día o en mora (mismo criterio que la lista de pagos). Va aparte de
	| la lista porque no depende de la fecha: al cambiarla, administración
	| recibe otra lista de marcas, pero el estado de pagos es el de hoy.
	| El importe sólo viaja a quien puede ver Pagos; el profesor ve el
	| estado y cuántas pensiones, que es lo que necesita para avisar.
	*/
	$verMontos = puede('ver', 'pagosList');
	$pagos = [];
	foreach ($insAlumno->estadoPagosAlumnos(array_column($alumnos, 'alumno_id')) as $id => $p) {
		$pagos[$id] = ['estado' => $p['estado'], 'meses' => $p['meses']] + ($verMontos ? ['deuda' => $p['deuda']] : []);
	}

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
		/* Sin conexión (asistencia-offline.js): de quién son las marcas que
		   se guardan en el celular, cuándo se descargó esta lista y a dónde
		   ir a entrar si la sesión venció. */
		'usuario' => (int)($_SESSION['usuarioid'] ?? 0),
		'generada'=> date('Y-m-d H:i'),
		'login'   => APP_URL . 'login/',
		'pagos'   => (object)$pagos,
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

					<!-- Sin conexión / sesión vencida / lista descargada: lo pone el script. -->
					<div class="ds-offline" id="toma-offline" role="status" aria-live="polite" hidden></div>

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

					<div class="d-flex flex-wrap gap-2 mb-2">
						<input type="search" class="form-control ds-toma__buscar" id="toma-buscar" placeholder="Buscar alumno…" aria-label="Buscar alumno">
						<button type="button" class="btn btn-success" id="toma-todos" title="Marca como presentes a los alumnos que aún no tienen asistencia"><i class="fas fa-check-double me-1" aria-hidden="true"></i>Todos presentes</button>
					</div>

					<!-- Filtro por estado de pagos: los botones y sus cifras los pone el script. -->
					<div class="ds-toma__filtros" id="toma-filtros" role="group" aria-label="Filtrar por estado de pagos"></div>

					<!-- =================== La lista =================== -->
					<ol class="ds-toma__lista list-unstyled" id="toma-lista" aria-label="Alumnos del horario"></ol>
					<p class="text-body-secondary text-center d-none" id="toma-vacia">Este horario no tiene alumnos activos asignados.</p>
					<p class="text-body-secondary text-center d-none" id="toma-sin-resultados">Ningún alumno coincide con el filtro.</p>

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
		| Tomar asistencia SIN recargar la página y SIN conexión.
		|
		| Cada toque pinta la marca al instante y la GUARDA EN EL CELULAR
		| (IndexedDB, asistencia-offline.js) antes de enviarla. Luego se
		| envía en lote; si no hay señal, queda «En el celular» y se envía
		| sola al volver la conexión, al volver a la app, cada 30 s y, en
		| Android, aunque la app esté cerrada (Background Sync del service
		| worker). Cerrar el navegador no pierde nada.
		|
		| Sin señal, la página llega de la copia guardada (sw-asistencia.js).
		| Si esa copia es de otro día, la toma es la de hoy según el celular.
		|
		| Conflictos: si mientras tanto alguien registró otra marca en el
		| servidor, se conserva la del servidor y la ficha lo dice.
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

		/*
		| Estado de pagos de cada alumno (al día / en mora / sin pagos), con
		| el mismo criterio que la lista de pagos. No cambia con la fecha.
		| El importe sólo llega si el rol puede ver Pagos.
		*/
		var PAGOS = D.pagos || {};
		var PAGO = {
			mora:     { texto: 'En mora',   icono: 'fa-circle-exclamation' },
			aldia:    { texto: 'Al día',    icono: 'fa-circle-check' },
			beca:     { texto: 'Beca',      icono: 'fa-graduation-cap' },
			sinpagos: { texto: 'Sin pagos', icono: 'fa-circle-question' }
		};
		var filtroPago = '';
		function etiquetaPago(p) {
			var partes = [PAGO[p.estado].texto];
			if (p.estado === 'mora') {
				partes.push(p.meses > 0 ? p.meses + (p.meses === 1 ? ' pensión' : ' pensiones') : 'saldo pendiente');
				if (typeof p.deuda === 'number') { partes.push('$' + p.deuda.toFixed(2)); }
			}
			return partes.join(' · ');
		}

		/*
		| confirmada: lo que dice el servidor (según la página o el último envío).
		| pend: lo marcado en este celular que aún no llegó al servidor; vive
		|       en IndexedDB (asistencia-offline.js), no en memoria.
		| avisos: conflictos o rechazos del último envío, por alumno.
		*/
		var alumnos = [], confirmada = {}, pend = {}, avisos = {}, fecha = D.fecha;
		var Q = window.DSAsistencia || null;
		var red = navigator.onLine ? 'ok' : 'sin-red';   /* ok | enviando | sin-red | sin-sesion */
		if (!Q) {
			/* Sin la librería (no debería pasar: va en la cabecera) no hay dónde guardar. */
			document.getElementById('toma-lista').insertAdjacentHTML('beforebegin',
				'<div class="alert alert-danger">No se pudo cargar el módulo de asistencia. Recargue la página.</div>');
		}

		/*
		| ¿La página viene de la copia guardada en el celular de otro día?
		| Entonces la toma es la de HOY según el teléfono y las marcas que
		| traía la página eran de ese otro día: se descartan.
		*/
		var hoyCel = Q ? Q.hoy() : D.hoy;
		var deOtroDia = Q && fecha === D.hoy && D.hoy !== hoyCel;
		if (deOtroDia) { fecha = hoyCel; D.alumnos.forEach(function (a) { a.marca = ''; }); }

		function el(tag, clase, texto) { var e = document.createElement(tag); if (clase) e.className = clase; if (texto != null) e.textContent = texto; return e; }

		function pintar() {
			lista.innerHTML = '';
			document.getElementById('toma-vacia').classList.toggle('d-none', alumnos.length > 0);
			alumnos.forEach(function (a) {
				var li = el('li', 'ds-toma__alumno'); li.dataset.id = a.id;
				var p = PAGOS[a.id];
				li.dataset.pago = p ? p.estado : '';
				var quien = el('div', 'ds-toma__quien');
				quien.appendChild(el('div', 'ds-toma__nombre', a.apellidos + ' ' + a.nombres));
				var meta = el('div', 'ds-toma__meta');
				if (p && PAGO[p.estado]) {
					var chip = el('span', 'ds-toma__pago ds-toma__pago--' + p.estado);
					chip.innerHTML = '<i class="fas ' + PAGO[p.estado].icono + '" aria-hidden="true"></i>';
					chip.appendChild(document.createTextNode(etiquetaPago(p)));
					meta.appendChild(chip);
				}
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
			filtros(); filtrar(); resumen();
		}

		/* Botones «Todos · En mora · Al día · Beca · Sin pagos» con su cifra. Sólo
		   los estados que tienen alumnos; sin datos de pagos, no se muestran. */
		function filtros() {
			var caja = document.getElementById('toma-filtros');
			var n = { mora: 0, aldia: 0, beca: 0, sinpagos: 0 }, con = 0;
			alumnos.forEach(function (a) { var p = PAGOS[a.id]; if (p && n.hasOwnProperty(p.estado)) { n[p.estado]++; con++; } });
			caja.innerHTML = '';
			caja.hidden = con === 0;
			if (!con) { filtroPago = ''; return; }
			if (filtroPago && !n[filtroPago]) { filtroPago = ''; }
			[['', 'Todos', alumnos.length]].concat(['mora', 'aldia', 'beca', 'sinpagos'].filter(function (k) { return n[k] > 0; })
				.map(function (k) { return [k, PAGO[k].texto, n[k]]; }))
				.forEach(function (f) {
					var b = el('button', 'ds-toma__filtro' + (f[0] ? ' ds-toma__filtro--' + f[0] : ''));
					b.type = 'button'; b.dataset.filtro = f[0];
					b.setAttribute('aria-pressed', filtroPago === f[0] ? 'true' : 'false');
					if (f[0]) { b.innerHTML = '<i class="fas ' + PAGO[f[0]].icono + '" aria-hidden="true"></i>'; }
					b.appendChild(el('span', 'ds-toma__rotulo', f[1]));
					b.appendChild(el('span', 'ds-toma__cifra', String(f[2])));
					caja.appendChild(b);
				});
		}
		document.getElementById('toma-filtros').addEventListener('click', function (ev) {
			var b = ev.target.closest('.ds-toma__filtro'); if (!b) return;
			filtroPago = b.dataset.filtro;
			this.querySelectorAll('.ds-toma__filtro').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
			filtrar();
		});

		/* El aspecto de una fila: la marca a la vista y dónde está guardada. */
		function fila(id) {
			var li = lista.querySelector('li[data-id="' + id + '"]'); if (!li) return;
			var actual = pend[id] || confirmada[id] || '';
			li.querySelectorAll('.ds-toma__opcion').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.estado === actual ? 'true' : 'false'); });
			li.classList.toggle('ds-toma__alumno--marcado', actual !== '');
			li.classList.toggle('ds-toma__alumno--celular', !!pend[id]);
			var g = li.querySelector('.ds-toma__guardado');
			g.className = 'ds-toma__guardado';
			if (avisos[id]) {
				g.innerHTML = '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i> ';
				g.appendChild(document.createTextNode(avisos[id].texto));
				g.classList.add(avisos[id].resultado === 'conflicto' ? 'text-warning-emphasis' : 'text-danger');
			} else if (pend[id]) {
				g.innerHTML = red === 'enviando'
					? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Enviando…'
					: '<i class="fas fa-mobile-screen" aria-hidden="true"></i> En el celular';
				g.classList.add('text-body-secondary');
			} else if (confirmada[id]) {
				g.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Guardado'; g.classList.add('text-success-emphasis');
			} else { g.textContent = ''; }
		}
		function filas() { alumnos.forEach(function (a) { fila(a.id); }); }

		function resumen() {
			var n = { P: 0, A: 0, F: 0, J: 0 }, marcados = 0;
			alumnos.forEach(function (a) { var e = pend[a.id] || confirmada[a.id]; if (e) { n[e]++; marcados++; } });
			document.getElementById('toma-cuenta').textContent = marcados + ' de ' + alumnos.length + ' marcados';
			document.getElementById('toma-progreso').style.width = (alumnos.length ? marcados / alumnos.length * 100 : 0) + '%';
			document.getElementById('toma-totales').textContent = ESTADOS.map(function (e) { return n[e.v] + ' ' + NOMBRE[e.v][n[e.v] === 1 ? 0 : 1]; }).join(' · ');
			var cola = Object.keys(pend).length, estado = document.getElementById('toma-estado');
			estado.className = 'small';
			if (!cola) {
				estado.innerHTML = alumnos.length ? '<i class="fas fa-check me-1" aria-hidden="true"></i>Todo enviado' : '';
				estado.classList.add('text-success-emphasis');
			} else if (red === 'enviando') {
				estado.textContent = 'Enviando ' + cola + '…'; estado.classList.add('text-body-secondary');
			} else {
				estado.innerHTML = '<i class="fas fa-mobile-screen me-1" aria-hidden="true"></i>' + cola + ' en el celular';
				estado.classList.add('text-warning-emphasis');
			}
			document.getElementById('toma-todos').disabled = marcados === alumnos.length;
			franja();
		}

		/* La franja de arriba: sin señal, sesión vencida o lista descargada. */
		function franja() {
			var f = document.getElementById('toma-offline'), cola = Object.keys(pend).length, html = '', clase = '';
			if (red === 'sin-sesion' && cola) {
				clase = 'ds-offline--sesion';
				html = '<i class="fas fa-user-lock" aria-hidden="true"></i><span><strong>Su sesión venció.</strong> ' + cola +
					(cola === 1 ? ' marca está guardada' : ' marcas están guardadas') + ' en el celular. ' +
					'<a href="' + D.login + '">Entre de nuevo</a> y vuelva a esta lista para enviarlas.</span>';
			} else if (red === 'sin-red' || !navigator.onLine) {
				clase = 'ds-offline--sin-red';
				html = '<i class="fas fa-wifi" aria-hidden="true"></i><span><strong>Sin conexión.</strong> Siga marcando: ' +
					(cola ? (cola === 1 ? 'la marca queda' : 'las ' + cola + ' marcas quedan') + ' en el celular y se envía' + (cola === 1 ? '' : 'n') + ' solas al volver la señal.'
					      : 'lo que marque queda en el celular y se envía solo al volver la señal.') +
					(deOtroDia || !navigator.onLine ? ' <span class="ds-offline__nota">Lista descargada el ' + fechaCorta(D.generada) + '.</span>' : '') + '</span>';
			}
			if (Q && !Q.persistente && cola) {
				html += (html ? '<br>' : '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i>') +
					'<span>Este navegador no guarda datos (modo privado): no cierre la página hasta que todo se envíe.</span>';
				clase = clase || 'ds-offline--sin-red';
			}
			f.hidden = html === '';
			f.className = 'ds-offline ' + clase;
			f.innerHTML = html;
		}
		function fechaCorta(t) { var p = String(t || '').split(/[- :]/); return p.length >= 5 ? p[2] + '/' + p[1] + ' a las ' + p[3] + ':' + p[4] : ''; }

		/*
		| Lo del celular para este horario y día: marcas pendientes y avisos
		| del último envío. Un conflicto deja a la vista lo que tiene el
		| servidor (se conservó esa marca).
		*/
		function leerLocal() {
			if (!Q) { return Promise.resolve(); }
			var f = { usuario: D.usuario, horario: D.horario, fecha: fecha };
			return Promise.all([Q.pendientes(f), Q.avisos(f)]).then(function (r) {
				var antes = pend;
				pend = {}; avisos = {};
				r[0].forEach(function (m) { pend[m.alumno] = m.estado; });
				/* Lo que estaba pendiente y ya no está, sin aviso, lo envió alguien
				   (esta página, otra pestaña o el service worker en segundo plano):
				   ahora es lo del servidor. */
				var avisados = {};
				r[1].forEach(function (a) { avisados[a.alumno] = true; });
				Object.keys(antes).forEach(function (id) { if (!pend[id] && !avisados[id]) { confirmada[id] = antes[id]; } });
				r[1].forEach(function (a) {
					avisos[a.alumno] = a;
					if (a.resultado === 'conflicto' && a.actual) { confirmada[a.alumno] = a.actual; }
				});
			});
		}

		/*
		| Enviar lo pendiente. Lo que estaba pendiente antes del envío y ya no
		| lo está (sin aviso) llegó al servidor: pasa a «Guardado».
		*/
		var programado = null;
		function enviar() {
			if (!Q) { return; }
			clearTimeout(programado);
			programado = setTimeout(function () {
				if (!Object.keys(pend).length) { return; }
				/* Sin red ni se intenta: la marca ya está a salvo en el celular. */
				if (!navigator.onLine) { red = 'sin-red'; filas(); resumen(); Q.pedirEnvioEnSegundoPlano(); return; }
				red = 'enviando'; filas(); resumen();
				Q.sincronizar().then(function (r) {
					red = r.estado === 'sin-red' ? 'sin-red' : (r.estado === 'sin-sesion' ? 'sin-sesion' : 'ok');
					return leerLocal();
				}).then(function () {
					filas(); resumen();
					if (Object.keys(pend).length && red !== 'sin-sesion') { Q.pedirEnvioEnSegundoPlano(); }
				});
			}, 250);
		}

		function marcar(id, estado) {
			delete avisos[id];
			var anterior = confirmada[id] || '';
			if (!Q) { return; }
			Q.guardar({ usuario: D.usuario, horario: D.horario, alumno: Number(id), fecha: fecha, estado: estado, anterior: anterior })
				.then(function (reg) {
					if (reg.pendiente) { pend[id] = estado; } else { delete pend[id]; }
					fila(id); resumen(); enviar();
				})
				.catch(function () {
					Swal.fire({ icon: 'error', title: 'No se guardó', text: 'El celular no pudo guardar la marca. Vuelva a intentarlo.', confirmButtonText: 'Aceptar' });
				});
		}

		lista.addEventListener('click', function (ev) {
			var b = ev.target.closest('.ds-toma__opcion'); if (!b) return;
			var id = b.closest('li').dataset.id;
			if ((pend[id] || confirmada[id]) === b.dataset.estado && !avisos[id]) return;   /* ya es esa */
			marcar(id, b.dataset.estado);
		});

		document.getElementById('toma-todos').addEventListener('click', function () {
			var faltan = alumnos.filter(function (a) { return !(pend[a.id] || confirmada[a.id]); });
			if (!faltan.length) return;
			Swal.fire({ icon: 'question', title: 'Marcar presentes', text: 'Se marcarán como presentes los ' + faltan.length + ' alumnos sin marcar.',
				showCancelButton: true, confirmButtonText: 'Sí, marcar', cancelButtonText: 'Cancelar' })
				.then(function (r) { if (r.isConfirmed) { faltan.forEach(function (a) { marcar(a.id, 'P'); }); } });
		});

		function filtrar() {
			var q = document.getElementById('toma-buscar').value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
			var visibles = 0;
			lista.querySelectorAll('li').forEach(function (li) {
				var t = li.querySelector('.ds-toma__nombre').textContent.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
				li.hidden = (q !== '' && t.indexOf(q) === -1) || (filtroPago !== '' && li.dataset.pago !== filtroPago);
				if (!li.hidden) { visibles++; }
			});
			document.getElementById('toma-sin-resultados').classList.toggle('d-none', !alumnos.length || visibles > 0);
		}
		document.getElementById('toma-buscar').addEventListener('input', filtrar);

		function cargar(lista_) {
			alumnos = lista_; confirmada = {};
			alumnos.forEach(function (a) { if (a.marca) confirmada[a.id] = a.marca; });
			return leerLocal().then(pintar, pintar).then(enviar);
		}

		/* La fecha del encabezado, si la página guardada era de otro día. */
		if (deOtroDia) {
			var etiqueta = document.querySelector('.ds-toma__hoy');
			if (etiqueta) {
				var d = new Date(fecha + 'T12:00:00');
				etiqueta.innerHTML = '<i class="far fa-calendar me-1" aria-hidden="true"></i>Hoy, ' +
					d.toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long' });
			}
		}

		(Q ? Q.listo : Promise.resolve()).then(function () { cargar(D.alumnos); });

		/* Administración: otra fecha, sin recargar (sólo con conexión). */
		var campoFecha = document.getElementById('toma-fecha');
		if (campoFecha) {
			var actualizarCampo = function () { campoFecha.disabled = !navigator.onLine; document.getElementById('toma-hoy').disabled = !navigator.onLine; };
			actualizarCampo();
			window.addEventListener('online', actualizarCampo);
			window.addEventListener('offline', actualizarCampo);
			function cambiarFecha(nueva) {
				if (Object.keys(pend).length) { Swal.fire({ icon: 'info', title: 'Espere', text: 'Aún hay marcas por enviar.', confirmButtonText: 'Aceptar' }); campoFecha.value = fecha; return; }
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

		/* Reintentos: al volver la señal, al volver a la app y cada 30 s con pendientes. */
		window.addEventListener('online', function () { red = 'ok'; enviar(); resumen(); });
		window.addEventListener('offline', function () { red = 'sin-red'; filas(); resumen(); });
		document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { leerLocal().then(function () { filas(); resumen(); enviar(); }); } });
		setInterval(function () { if (Object.keys(pend).length && navigator.onLine && red !== 'enviando') { enviar(); } }, 30000);
		/* El service worker envió en segundo plano: releer lo del celular. */
		if (Q) { Q.escuchar(function (m) { if (m && m.tipo === 'sincronizado' && red !== 'enviando') { leerLocal().then(function () { filas(); resumen(); }); } }); }

		/* Sólo sin almacenamiento persistente se puede perder algo al salir. */
		window.addEventListener('beforeunload', function (e) {
			if (Q && !Q.persistente && Object.keys(pend).length) { e.preventDefault(); e.returnValue = ''; }
		});
	});
	</script>
	<?php endif; ?>
  </body>
</html>
