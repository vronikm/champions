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

	/* Redes sociales (migración 073): sólo con autorización de uso de imagen
	   vigente (LOPDP) se puede publicar o compartir la tarjeta; lo vuelve a
	   comprobar el servidor al publicar. */
	$idsCumple   = array_column($alumnos, 'alumno_id');
	$autImagen   = $insCumple->autorizacionesImagen($idsCumple);
	$publicadas  = $insCumple->publicacionesCumple($idsCumple, (int)date('Y'));
	$cfgRedes    = redes_config();
	$redesActivas = array_keys(array_filter(['facebook' => $cfgRedes['fb_activo'] === 'S', 'instagram' => $cfgRedes['ig_activo'] === 'S']));
	$puedePublicar = $redesActivas && puede_crear('cumpleaniosList');

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
										<div class="cumple-acciones">
										<a href="<?php echo APP_URL . 'cumpleaniosTarjeta/' . $a['alumno_id'] . '/'; ?>"
										   class="btn-tarjeta"
										   target="_blank">
											<i class="fas fa-gift"></i> Ver tarjeta
										</a>
										<?php $idA = (int)$a['alumno_id']; $pubA = $publicadas[$idA] ?? []; ?>
										<?php if (($autImagen[$idA] ?? null) === 'S'): ?>
											<button type="button" class="btn-tarjeta btn-publicar" data-publicar
											        data-id="<?php echo $idA; ?>"
											        data-nombre="<?php echo htmlspecialchars($nombreCompleto); ?>"
											        data-mensaje="<?php echo htmlspecialchars(redes_mensaje_cumple($a, $cfgRedes)); ?>"
											        data-publicadas="<?php echo htmlspecialchars(json_encode((object)$pubA, JSON_UNESCAPED_UNICODE)); ?>">
												<i class="fas fa-share-nodes"></i> Publicar
											</button>
										<?php else: ?>
											<span class="cumple-sin-imagen" title="El representante no autorizó el uso de imagen en redes (LOPDP)">
												<i class="fas fa-user-lock" aria-hidden="true"></i> Sin autorización de imagen
											</span>
										<?php endif; ?>
										</div>
										<?php if ($pubA): ?>
											<div class="cumple-publicada">
												<?php foreach ($pubA as $red => $cuando): ?>
													<span title="<?php echo htmlspecialchars($cuando); ?>"><i class="fab fa-<?php echo $red; ?>" aria-hidden="true"></i> Publicada</span>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			</div>
		</section>
	</div>

	<!-- ============ Publicar la felicitación (redes sociales, migración 073) ============
	     Sólo se abre para alumnos con autorización de uso de imagen vigente.
	     «Publicar» va a las redes activas en Core › Redes sociales (página de
	     Facebook, Instagram profesional). «Compartir» abre la hoja del teléfono
	     (WhatsApp, Instagram, Facebook… a mano) y «Descargar» guarda la imagen. -->
	<div class="modal fade cumple-publicar" id="modalPublicar" tabindex="-1" aria-labelledby="modalPublicarTitulo" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="modalPublicarTitulo"><i class="fas fa-share-nodes me-2" aria-hidden="true"></i>Felicitar a <span data-p="nombre"></span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<img class="cumple-publicar__tarjeta" data-p="tarjeta" alt="Tarjeta de cumpleaños" src="">
					<?php if (!$esHoy): ?>
						<p class="small text-warning-emphasis mt-2 mb-0"><i class="fas fa-clock me-1" aria-hidden="true"></i>Se publica ahora, no el día del cumpleaños.</p>
					<?php endif; ?>

					<label for="publicarMensaje" class="form-label mt-3">Texto</label>
					<textarea class="form-control" id="publicarMensaje" rows="4" maxlength="2200"></textarea>

					<?php if ($puedePublicar): ?>
						<fieldset class="mt-3">
							<legend class="form-label mb-1">Publicar en</legend>
							<?php foreach ($redesActivas as $red): ?>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" value="<?php echo $red; ?>" id="publicar_<?php echo $red; ?>" data-red checked>
									<label class="form-check-label" for="publicar_<?php echo $red; ?>">
										<i class="fab fa-<?php echo $red; ?> me-1" aria-hidden="true"></i><?php echo $red === 'facebook' ? 'Facebook' : 'Instagram'; ?>
										<span class="small text-body-secondary" data-ya="<?php echo $red; ?>"></span>
									</label>
								</div>
							<?php endforeach; ?>
						</fieldset>
					<?php elseif ($redesActivas): ?>
						<p class="small text-body-secondary mt-3 mb-0">Su rol no puede publicar en las redes de la escuela; puede compartir o descargar la tarjeta.</p>
					<?php else: ?>
						<p class="small text-body-secondary mt-3 mb-0">Las redes de la escuela aún no están conectadas (Core › Redes sociales). Puede compartir o descargar la tarjeta.</p>
					<?php endif; ?>

					<div class="cumple-publicar__resultado mt-3" data-p="resultado" role="status" hidden></div>
				</div>
				<div class="modal-footer cumple-publicar__acciones">
					<button type="button" class="btn btn-secondary" data-accion="copiar"><i class="fas fa-copy me-1" aria-hidden="true"></i>Copiar texto</button>
					<a class="btn btn-secondary" data-accion="descargar" download><i class="fas fa-download me-1" aria-hidden="true"></i>Descargar</a>
					<button type="button" class="btn btn-secondary" data-accion="compartir" hidden><i class="fas fa-share me-1" aria-hidden="true"></i>Compartir</button>
					<?php if ($puedePublicar): ?>
						<button type="button" class="btn btn-primary" data-accion="publicar"><i class="fas fa-paper-plane me-1" aria-hidden="true"></i>Publicar</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
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

<script>
/* Publicar / compartir la felicitación (modal #modalPublicar). */
(function () {
	var modalEl = document.getElementById('modalPublicar');
	if (!modalEl) { return; }
	var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
	var AJAX = '<?php echo APP_URL; ?>app/ajax/cumpleaniosAjax.php';
	var TARJETA = '<?php echo APP_URL; ?>cumpleaniosTarjeta/';
	var $ = function (s) { return modalEl.querySelector(s); };
	var texto = document.getElementById('publicarMensaje');
	var resultado = $('[data-p="resultado"]');
	var actual = null;   /* el botón de la tarjeta que abrió el modal */

	function aviso(clase, html) { resultado.className = 'cumple-publicar__resultado mt-3 alert ' + clase + ' mb-0'; resultado.innerHTML = html; resultado.hidden = false; }
	function esc(t) { var d = document.createElement('span'); d.textContent = t; return d.innerHTML; }

	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-publicar]');
		if (!b) { return; }
		actual = b;
		$('[data-p="nombre"]').textContent = b.dataset.nombre;
		var url = TARJETA + b.dataset.id + '/?redes=1';   /* la versión que se publica: nombre corto */
		$('[data-p="tarjeta"]').src = url;
		$('[data-accion="descargar"]').href = url;
		$('[data-accion="descargar"]').setAttribute('download', 'cumpleanos_' + b.dataset.id + '.jpg');
		texto.value = b.dataset.mensaje;
		resultado.hidden = true;
		var ya = JSON.parse(b.dataset.publicadas || '{}');
		modalEl.querySelectorAll('[data-red]').forEach(function (c) {
			var hecho = !!ya[c.value];
			c.checked = !hecho; c.disabled = hecho;
			$('[data-ya="' + c.value + '"]').textContent = hecho ? '· ya publicada (' + ya[c.value] + ')' : '';
		});
		modal.show();
	});

	/* Compartir: la hoja del teléfono con la imagen y el texto, si el navegador la tiene. */
	var compartir = $('[data-accion="compartir"]');
	var puedeCompartir = !!(navigator.canShare && window.File && navigator.canShare({ files: [new File([''], 'x.jpg', { type: 'image/jpeg' })] }));
	compartir.hidden = !puedeCompartir;
	compartir.addEventListener('click', function () {
		fetch(TARJETA + actual.dataset.id + '/?redes=1', { credentials: 'same-origin' })
			.then(function (r) { return r.blob(); })
			.then(function (blob) {
				var f = new File([blob], 'cumpleanos.jpg', { type: 'image/jpeg' });
				return navigator.share({ files: [f], text: texto.value });
			})
			.catch(function (err) { if (err && err.name !== 'AbortError') { aviso('alert-warning', 'No se pudo compartir desde este navegador. Use «Descargar».'); } });
	});

	$('[data-accion="copiar"]').addEventListener('click', function () {
		navigator.clipboard.writeText(texto.value).then(function () { aviso('alert-success', 'Texto copiado.'); });
	});

	var publicar = $('[data-accion="publicar"]');
	if (publicar) {
		publicar.addEventListener('click', function () {
			var redes = [].slice.call(modalEl.querySelectorAll('[data-red]:checked:not(:disabled)')).map(function (c) { return c.value; });
			if (!redes.length) { aviso('alert-warning', 'Elija al menos una red.'); return; }
			var d = new FormData();
			d.append('modulo_cumple', 'crear_publicacion_cumple');
			d.append('alumno_id', actual.dataset.id);
			d.append('mensaje', texto.value);
			redes.forEach(function (r) { d.append('redes[]', r); });
			var antes = publicar.innerHTML;
			publicar.disabled = true;
			publicar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Publicando…';
			fetch(AJAX, { method: 'POST', body: d, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					var clase = r.icono === 'success' ? 'alert-success' : (r.icono === 'warning' ? 'alert-warning' : 'alert-danger');
					aviso(clase, '<strong>' + esc(r.titulo || '') + '</strong><br>' + esc(r.texto || '').replace(/\n/g, '<br>'));
					/* Las publicadas quedan marcadas en la tarjeta y en el modal. */
					var ya = JSON.parse(actual.dataset.publicadas || '{}');
					(r.publicadas || []).forEach(function (red) {
						ya[red] = 'ahora';
						var c = modalEl.querySelector('[data-red][value="' + red + '"]');
						if (c) { c.checked = false; c.disabled = true; $('[data-ya="' + red + '"]').textContent = '· ya publicada'; }
					});
					actual.dataset.publicadas = JSON.stringify(ya);
				})
				.catch(function () { aviso('alert-danger', 'No se pudo comunicar con el servidor.'); })
				.finally(function () { publicar.disabled = false; publicar.innerHTML = antes; });
		});
	}
})();
</script>

</body>
</html>
