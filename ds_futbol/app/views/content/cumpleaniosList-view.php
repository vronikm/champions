<?php
	use app\controllers\cumpleaniosController;
	$insCumple = new cumpleaniosController();

	// Fecha seleccionada (parámetro URL o hoy)
	$fechaFiltro = isset($url[1]) && $url[1] !== '' ? $insCumple->limpiarCadena($url[1]) : date('Y-m-d');

	// Validar formato de fecha
	$fechaObj = DateTime::createFromFormat('Y-m-d', $fechaFiltro);
	if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaFiltro) {
		$fechaFiltro = date('Y-m-d');
		$fechaObj    = new DateTime();
	}

	$datos = $insCumple->listarCumpleanios($fechaFiltro);
	$alumnos = $datos->rowCount() > 0 ? $datos->fetchAll() : [];

	// Formatear fecha en español
	$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
	$dias  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
	$fechaLabel = $dias[(int)$fechaObj->format('w')] . ', ' . (int)$fechaObj->format('d') . ' de ' . $meses[(int)$fechaObj->format('n') - 1] . ' de ' . $fechaObj->format('Y');
	$esHoy = ($fechaFiltro === date('Y-m-d'));

	/* Para el calendario: cuántos cumplen cada día del año ('MM-DD' => n). */
	$cumplesPorDia = $insCumple->cumpleaniosPorDia();

	/* Próximos cumpleaños: los días con alguno en las dos semanas que vienen
	   (desde mañana), para preparar las tarjetas con tiempo. */
	$proximos = [];
	$cursor = new DateTime('tomorrow');
	for ($i = 0; $i < 14; $i++, $cursor->modify('+1 day')) {
		$n = $cumplesPorDia[$cursor->format('m-d')] ?? 0;
		if ($n > 0) {
			$proximos[] = ['fecha' => $cursor->format('Y-m-d'), 'n' => $n,
			               'dia' => $dias[(int)$cursor->format('w')], 'num' => (int)$cursor->format('d'),
			               'mes' => $meses[(int)$cursor->format('n') - 1]];
		}
	}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo APP_NAME; ?> | Cumpleaños</title>
	<?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
	<link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
	<!-- Google Font -->
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
	<!-- Font Awesome -->
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
	<!-- AdminLTE -->
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/css/overlayscrollbars.min.css">
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/css/adminlte.min.css">
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/core.css'); ?>">
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_futbol/app/views/dist/css/futbol.css'); ?>">
	<!-- SweetAlert2 -->
	<link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/dist/css/sweetalert2.min.css">
	<?php /* Con ds_recurso() y no con APP_URL a secas: anade ?v=filemtime, asi
	     que al editar la hoja el navegador se trae la nueva. Sin eso, un cambio
	     de colores no se ve hasta vaciar la cache a mano —y quien lo mira en
	     una ventana nueva SI lo ve, que es como se descarta el problema por
	     equivocacion—. */ ?>
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_futbol/app/views/dist/css/cumples.css'); ?>">
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper ds-core">

	<!-- Navbar -->
	<?php require_once "app/views/inc/navbar.php"; ?>
	<!-- Sidebar -->
	<?php require_once "app/views/inc/main-sidebar.php"; ?>

	<!-- Content Wrapper -->
	<div class="app-main">

		<!-- Content Header -->
		<div class="app-content-header">
			<div class="container-fluid">
				<div class="row mb-2">
					<div class="col-sm-6">
						<h3 class="m-0" style="color:var(--cumple-acento);">
							<i class="fas fa-birthday-cake" style="color:var(--cumple-amarillo);" aria-hidden="true"></i>
							Cumpleaños
						</h3>
					</div>
					<div class="col-sm-6">
						<ol class="breadcrumb float-sm-end">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active">Cumpleaños</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<!-- Main content -->
		<section class="app-content">
			<div class="container-fluid">
				<!-- Barra unificada: filtro + fecha -->
				<div class="cumple-filtro mb-3">
					<i class="fas fa-<?php echo $esHoy ? 'star' : 'birthday-cake'; ?>"></i>
					<span class="fecha-info">
						<?php if ($esHoy): ?>
							Cumpleaños de <strong>hoy</strong> — <?php echo ucfirst($fechaLabel); ?>
						<?php else: ?>
							Cumpleaños del <?php echo ucfirst($fechaLabel); ?>
						<?php endif; ?>
					</span>
					<?php
					/*
					| EL CALENDARIO MARCA LOS DÍAS CON CUMPLEAÑOS
					|
					| El <input type="date"> del navegador no deja marcar días: había
					| que ir probando fecha a fecha para encontrar quién cumplía.
					| Este calendario pinta en amarillo los días con cumpleaños y
					| dice cuántos son, así que se ve de un vistazo qué tarjetas
					| hay que generar en el mes.
					*/
					?>
					<span class="cumple-filtro__etiqueta" id="etiquetaFecha">Fecha:</span>
					<div class="cumple-picker">
						<button type="button" class="cumple-picker__boton" id="btnFecha"
						        aria-haspopup="dialog" aria-expanded="false" aria-controls="calendarioCumples"
						        aria-labelledby="etiquetaFecha btnFechaTexto">
							<i class="far fa-calendar-alt" aria-hidden="true"></i>
							<span id="btnFechaTexto"><?php echo $fechaObj->format('d/m/Y'); ?></span>
							<i class="fas fa-chevron-down cumple-picker__flecha" aria-hidden="true"></i>
						</button>
						<div class="cumple-cal" id="calendarioCumples" role="dialog" aria-modal="false"
						     aria-label="Elegir fecha: los días marcados tienen cumpleaños" hidden>
							<div class="cumple-cal__cabecera">
								<button type="button" class="cumple-cal__nav" id="calPrev" aria-label="Mes anterior"><i class="fas fa-chevron-left"></i></button>
								<div class="cumple-cal__titulo">
									<div class="cumple-cal__mes" id="calMes" aria-live="polite"></div>
									<div class="cumple-cal__total" id="calTotal"></div>
								</div>
								<button type="button" class="cumple-cal__nav" id="calNext" aria-label="Mes siguiente"><i class="fas fa-chevron-right"></i></button>
							</div>
							<div class="cumple-cal__semana" aria-hidden="true">
								<span>L</span><span>M</span><span>X</span><span>J</span><span>V</span><span>S</span><span>D</span>
							</div>
							<div class="cumple-cal__dias" id="calDias" role="grid"></div>
							<div class="cumple-cal__pie">
								<span class="cumple-cal__leyenda"><i class="cumple-cal__muestra" aria-hidden="true"></i> Con cumpleaños</span>
								<button type="button" class="cumple-cal__hoy" id="calHoy">Ir a hoy</button>
							</div>
						</div>
					</div>
					<button class="btn-hoy" id="btnHoy">
						<i class="fas fa-home"></i> Hoy
					</button>
					<span class="cumple-badge">
						<i class="fas fa-birthday-cake"></i>
						<?php echo count($alumnos); ?> cumpleaño<?php echo count($alumnos) !== 1 ? 's' : ''; ?>
					</span>
				</div>

				<!-- Próximos cumpleaños: atajo para preparar las tarjetas con tiempo -->
				<div class="cumple-proximos mb-4" aria-label="Próximos cumpleaños">
					<span class="cumple-proximos__titulo"><i class="fas fa-calendar-day me-1" aria-hidden="true"></i>Próximos 14 días:</span>
					<?php if (!$proximos): ?>
						<span class="cumple-proximos__vacio">sin cumpleaños</span>
					<?php endif; ?>
					<?php foreach ($proximos as $p): ?>
						<a class="cumple-proximo<?php echo $p['fecha'] === $fechaFiltro ? ' activo' : ''; ?>"
						   href="<?php echo APP_URL . 'cumpleaniosList/' . $p['fecha'] . '/'; ?>"
						   title="<?php echo ucfirst($p['dia']) . ' ' . $p['num'] . ' de ' . $p['mes'] . ': ' . $p['n'] . ' cumpleaño' . ($p['n'] !== 1 ? 's' : ''); ?>"
						   <?php echo $p['fecha'] === $fechaFiltro ? 'aria-current="date"' : ''; ?>>
							<span class="cumple-proximo__dia"><?php echo mb_substr($p['dia'], 0, 3, 'UTF-8') . ' ' . $p['num'] . ' ' . mb_substr($p['mes'], 0, 3, 'UTF-8'); ?></span>
							<span class="cumple-proximo__n"><i class="fas fa-birthday-cake" aria-hidden="true"></i> <?php echo $p['n']; ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<!-- Grid de alumnos -->
				<?php if (empty($alumnos)): ?>
					<div class="cumple-empty">
						<i class="fas fa-birthday-cake"></i>
						<?php /* El color sale de un token de Bootstrap, no del azul de marca: ese
						   es fijo y sobre el tema oscuro da contraste 1,05, texto casi
						   invisible. Solo se vio el dia que no cumplia anios nadie y el
						   estado vacio se dibujo por primera vez. */ ?>
						<p style="font-size:1.1rem; font-weight:600; color:var(--bs-emphasis-color);">
							No hay alumnos que cumplan años <?php echo $esHoy ? 'hoy' : 'en esta fecha'; ?>.
						</p>
						<p>Prueba seleccionando otra fecha.</p>
					</div>
				<?php else: ?>
					<div class="row">
						<?php foreach ($alumnos as $a): ?>
							<?php
								/* Sin foto en disco, el escudo de alumno.png, como en
								   champions_cf. media_url() devuelve la genérica del
								   sistema (dist/img/foto.jpg) cuando el archivo falta, y
								   esa es vertical: en el círculo cortaba el «2013». */
								$fotoSrc = ($a['alumno_imagen'] !== '')
									? media_url('alumno', $a['alumno_imagen'])
									: '';
								if (strpos($fotoSrc, 'media.php') === false) {
									$fotoSrc = media_url('alumno', 'alumno.png');
								}
								$nombreCompleto = trim($a['alumno_primernombre'] . ' ' . $a['alumno_segundonombre'])
												. ' ' . trim($a['alumno_apellidopaterno'] . ' ' . $a['alumno_apellidomaterno']);
								$edad = (int)$a['edad'];
								$fechaNac = DateTime::createFromFormat('Y-m-d', $a['alumno_fechanacimiento']);
								$fechaNacLabel = $fechaNac ? $fechaNac->format('d/m/Y') : $a['alumno_fechanacimiento'];
							?>
							<div class="col-xl-3 col-lg-3 col-md-4 col-sm-6 mb-4"> <!-- Ajusta el número de columnas según el tamaño de pantalla-->
								<div class="alumno-card card">
									<div class="card-header-azul">
										<i class="fas fa-birthday-cake"
										   style="position:absolute;top:14px;right:14px;color:rgba(255,224,1,.45);font-size:1.1rem;"
							   aria-hidden="true"></i>
									</div>
									<div class="card-body-custom">
										<div class="foto-wrapper">
											<img src="<?php echo $fotoSrc; ?>"
											     alt="<?php echo htmlspecialchars($nombreCompleto); ?>"
											     onerror="this.src='<?php echo media_url('alumno', 'alumno.png'); ?>'">
										</div>
										<div class="mt-3 alumno-nombre"><?php echo htmlspecialchars($nombreCompleto); ?></div>
										<div class="alumno-id"><?php echo htmlspecialchars($a['alumno_identificacion']); ?></div>
										<span class="edad-badge">
											<i class="fas fa-birthday-cake"></i>
											<?php echo $edad; ?> años
										</span>
										<div class="fecha-nacimiento">
											<i class="fas fa-calendar"></i> <?php echo $fechaNacLabel; ?>
										</div>
										<a href="<?php echo APP_URL . 'cumpleaniosTarjeta/' . $a['alumno_id'] . '/'; ?>"
										   class="btn-tarjeta"
										   target="_blank">
											<i class="fas fa-gift"></i> Ver tarjeta
										</a>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			</div>
		</section>
	</div>

	<!-- Footer -->
	<?php require_once "app/views/inc/footer.php"; ?>

</div>

<!-- Scripts -->
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>

<script>
(function () {
	/*
	| Calendario de cumpleaños.
	|   CUMPLES  'MM-DD' => cuántos alumnos activos cumplen ese día (el mismo
	|            criterio que la lista: cumpleaniosController::cumpleaniosPorDia)
	|   FECHA    el día que se está mostrando; HOY, la fecha del servidor.
	| Elegir un día lleva a cumpleaniosList/AAAA-MM-DD/, como antes.
	*/
	var CUMPLES = <?php echo json_encode((object) $cumplesPorDia); ?>;
	var FECHA   = '<?php echo $fechaFiltro; ?>';
	var HOY     = '<?php echo date('Y-m-d'); ?>';
	var BASE    = '<?php echo APP_URL; ?>cumpleaniosList/';
	var MESES   = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
	var DIAS    = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];

	var boton = document.getElementById('btnFecha');
	var cal   = document.getElementById('calendarioCumples');
	var rej   = document.getElementById('calDias');
	var tMes  = document.getElementById('calMes');
	var tTot  = document.getElementById('calTotal');

	var p = FECHA.split('-');
	var vista = { anio: +p[0], mes: +p[1] - 1 };   /* mes que se está mirando */
	var foco  = FECHA;                              /* día con el foco del teclado */

	function dos(n) { return (n < 10 ? '0' : '') + n; }
	function iso(a, m, d) { return a + '-' + dos(m + 1) + '-' + dos(d); }
	function cuantos(a, m, d) { return CUMPLES[dos(m + 1) + '-' + dos(d)] || 0; }
	function diasDelMes(a, m) { return new Date(Date.UTC(a, m + 1, 0)).getUTCDate(); }
	function ir(fecha) { window.location.href = BASE + fecha + '/'; }

	function pintar() {
		var a = vista.anio, m = vista.mes, total = 0, dias = 0;
		var n = diasDelMes(a, m);
		/* Lunes primero: getUTCDay() da 0 al domingo. */
		var hueco = (new Date(Date.UTC(a, m, 1)).getUTCDay() + 6) % 7;
		var html = '';
		for (var h = 0; h < hueco; h++) { html += '<span class="cumple-cal__vacio" aria-hidden="true"></span>'; }
		for (var d = 1; d <= n; d++) {
			var f = iso(a, m, d), c = cuantos(a, m, d);
			total += c; if (c) { dias++; }
			var dSem = DIAS[new Date(Date.UTC(a, m, d)).getUTCDay()];
			var etiqueta = dSem + ' ' + d + ' de ' + MESES[m] + ' de ' + a + (c ? ', ' + c + ' cumpleaño' + (c > 1 ? 's' : '') : ', sin cumpleaños');
			html += '<button type="button" role="gridcell" class="cumple-cal__dia' + (c ? ' con-cumple' : '') +
			        (f === FECHA ? ' elegido' : '') + (f === HOY ? ' hoy' : '') + '"' +
			        ' data-fecha="' + f + '" tabindex="' + (f === foco ? '0' : '-1') + '"' +
			        ' aria-label="' + etiqueta + '"' + (f === FECHA ? ' aria-selected="true"' : '') +
			        (f === HOY ? ' aria-current="date"' : '') + ' title="' + etiqueta + '">' +
			        '<span class="cumple-cal__num">' + d + '</span>' +
			        (c ? '<span class="cumple-cal__n">' + c + '</span>' : '') + '</button>';
		}
		rej.innerHTML = html;
		tMes.textContent = MESES[m].charAt(0).toUpperCase() + MESES[m].slice(1) + ' ' + a;
		tTot.textContent = total
			? total + ' cumpleaño' + (total > 1 ? 's' : '') + ' en ' + dias + ' día' + (dias > 1 ? 's' : '')
			: 'Sin cumpleaños este mes';
	}

	function abrir() {
		var q = FECHA.split('-'); vista = { anio: +q[0], mes: +q[1] - 1 }; foco = FECHA;
		pintar();
		cal.hidden = false;
		boton.setAttribute('aria-expanded', 'true');
		var el = rej.querySelector('[tabindex="0"]'); if (el) { el.focus(); }
	}
	function cerrar(devolverFoco) {
		cal.hidden = true;
		boton.setAttribute('aria-expanded', 'false');
		if (devolverFoco) { boton.focus(); }
	}
	function cambiarMes(delta) {
		var fecha = new Date(Date.UTC(vista.anio, vista.mes + delta, 1));
		vista = { anio: fecha.getUTCFullYear(), mes: fecha.getUTCMonth() };
		/* El foco va al primer día con cumpleaños del mes, o al día 1. */
		var primero = 1;
		for (var d = 1; d <= diasDelMes(vista.anio, vista.mes); d++) { if (cuantos(vista.anio, vista.mes, d)) { primero = d; break; } }
		foco = iso(vista.anio, vista.mes, primero);
		pintar();
	}

	boton.addEventListener('click', function () { cal.hidden ? abrir() : cerrar(false); });
	document.getElementById('calPrev').addEventListener('click', function () { cambiarMes(-1); });
	document.getElementById('calNext').addEventListener('click', function () { cambiarMes(1); });
	document.getElementById('calHoy').addEventListener('click', function () { ir(HOY); });
	rej.addEventListener('click', function (e) {
		var b = e.target.closest('.cumple-cal__dia');
		if (b) { ir(b.dataset.fecha); }
	});

	/* Teclado: flechas por días y semanas, RePág/AvPág por meses, Esc cierra. */
	cal.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { e.preventDefault(); cerrar(true); return; }
		var actual = e.target.closest('.cumple-cal__dia');
		if (!actual) { return; }
		var salto = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
		if (e.key === 'PageUp' || e.key === 'PageDown') {
			e.preventDefault(); cambiarMes(e.key === 'PageUp' ? -1 : 1);
			var x = rej.querySelector('[tabindex="0"]'); if (x) { x.focus(); }
			return;
		}
		if (salto === undefined) { return; }
		e.preventDefault();
		var q = actual.dataset.fecha.split('-');
		var nueva = new Date(Date.UTC(+q[0], +q[1] - 1, +q[2] + salto));
		foco = nueva.toISOString().slice(0, 10);
		vista = { anio: nueva.getUTCFullYear(), mes: nueva.getUTCMonth() };
		pintar();
		var el = rej.querySelector('[data-fecha="' + foco + '"]'); if (el) { el.focus(); }
	});

	/* Clic fuera cierra. */
	document.addEventListener('click', function (e) {
		if (!cal.hidden && !e.target.closest('.cumple-picker')) { cerrar(false); }
	});

	// Botón "Hoy"
	document.getElementById('btnHoy').addEventListener('click', function () {
		window.location.href = BASE;
	});
})();
</script>

</body>
</html>
