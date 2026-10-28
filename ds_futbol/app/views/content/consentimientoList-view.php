<?php
	use app\controllers\consentimientoController;

	$insConsent = new consentimientoController();

	/*----------  Búsqueda de representantes  ----------
	   Por GET, para que el filtro sobreviva a recargar y se pueda compartir.
	   Al abrir: alumnos ACTIVOS de todas las sedes de la sesión. Una sede
	   ajena en la dirección se ignora (y la regla de sedes va siempre). */
	$sedes  = $insConsent->sedesDeLaBusqueda();
	$sedeId = (int)($_GET['sede'] ?? ($url[1] ?? 0));
	$sedeId = isset($sedes[$sedeId]) ? $sedeId : 0;
	/* «Todas» sólo tiene sentido con más de una sede; con una, es esa. */
	if (count($sedes) === 1) { $sedeId = (int)array_key_first($sedes); }
	$estado = array_key_exists($_GET['estado'] ?? '', consentimientoController::ESTADOS) ? $_GET['estado'] : 'A';
	$autorizacion = array_key_exists($_GET['autorizacion'] ?? '', consentimientoController::AUTORIZACION) ? $_GET['autorizacion'] : 'T';

	$filas      = $insConsent->listarConsentimientos($sedeId, $estado);
	$repres     = $insConsent->representantesListado($filas, $autorizacion);
	$filtrada   = $sedeId !== 0 && count($sedes) > 1 || $estado !== 'A' || $autorizacion !== 'T';

	$puedeEnviar = puede('crear', 'consentimientoList');
	$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
	$fecha = static fn($v) => $v ? date('d/m/Y', strtotime($v)) : '';

	$totalAlumnos   = count($filas);
	$alumnosPend    = count(array_filter($filas, [consentimientoController::class, 'alumnoPendiente']));
	$porAutorizar   = count(array_filter($repres, static fn($p) => $p['pendiente']));
	$conCorreo      = count(array_filter($repres, static fn($p) => $p['pendiente'] && $p['activos'] > 0 && $p['correo'] !== ''));
	$etiquetaAlumnos = ['A' => 'Alumnos activos', 'I' => 'Alumnos inactivos', 'T' => 'Alumnos (activos e inactivos)'][$estado];

	/* Devuelve el badge del estado de un consentimiento */
	function badgeConsent($otorgado, $origen, $fecha, $usuario) {
		if ($otorgado === null) {
			return '<span class="badge text-bg-secondary">Sin registro</span>';
		}
		if ($otorgado === 'N') {
			/* Quien respondió «no» en línea no revocó nada: decidió. */
			return in_array($origen, ['ENLACE', 'FORMULARIO'], true)
				? '<span class="badge text-bg-dark" title="Respondió el '.htmlspecialchars($fecha).'">No autoriza</span>'
				: '<span class="badge text-bg-dark" title="Revocado el '.htmlspecialchars($fecha).'">Revocado</span>';
		}
		$fuente = match ($origen) {
			'FORMULARIO' => '<span class="badge text-bg-info" title="Aceptado en línea el '.htmlspecialchars($fecha).'">Formulario</span>',
			'ENLACE'     => '<span class="badge text-bg-primary" title="Respondido por enlace el '.htmlspecialchars($fecha).'">Enlace</span>',
			default      => '<span class="badge text-bg-warning" title="Registrado por '.htmlspecialchars($usuario ?? '').' el '.htmlspecialchars($fecha).'">Administrador</span>',
		};

		return '<span class="badge text-bg-success">Otorgado</span> '.$fuente;
	}

	/* Chip corto del estado de un alumno en la lista de pendientes. */
	function chipConsent($etiqueta, $otorgado) {
		[$clase, $icono, $texto] = match ($otorgado) {
			'S'     => ['text-bg-success', 'fa-check', 'sí'],
			'N'     => ['text-bg-dark', 'fa-xmark', 'no'],
			default => ['text-bg-secondary', 'fa-minus', 'falta'],
		};
		return '<span class="badge '.$clase.'" title="'.$etiqueta.': '.$texto.'"><i class="fas '.$icono.' me-1" aria-hidden="true"></i>'.$etiqueta.'</span>';
	}

	/* El último enlace de un representante, en palabras. */
	function ultimoEnvio($e) {
		if (!$e) {
			return '<span class="badge text-bg-light border">Sin enviar</span>';
		}
		$canal = ['WHATSAPP' => 'WhatsApp', 'CORREO' => 'correo', 'COPIA' => 'enlace copiado'][$e['enlace_canal']] ?? $e['enlace_canal'];
		$cuando = date('d/m/Y', strtotime($e['enlace_fecha']));
		if ($e['enlace_usado'] === null && strtotime($e['enlace_expira']) < time()) {
			return '<span class="badge text-bg-warning" title="Enviado por '.htmlspecialchars($e['enlace_usuario']).'">Venció</span>'
				 . '<div class="ds-ficha__meta">'.$cuando.' · '.$canal.'</div>';
		}
		return '<span class="badge text-bg-info" title="Enviado por '.htmlspecialchars($e['enlace_usuario']).'">Enviado</span>'
			 . '<div class="ds-ficha__meta">'.$cuando.' · '.$canal.'</div>';
	}
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Consentimientos LOPDP';
	$extras      = array (0 => 'datatables',);
	ob_start();
?>
<style>
	.ds-consent__resumen { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1.25rem; }
	.ds-consent__cifra {
		background: var(--bs-body-bg); border: 1px solid var(--bs-border-color);
		border-radius: var(--bs-border-radius-lg); padding: .75rem 1rem;
	}
	.ds-consent__cifra strong { display: block; font-size: 1.5rem; line-height: 1.2; }
	.ds-consent__cifra span { font-size: .825rem; color: var(--bs-secondary-color); }
	.ds-consent__hijos { list-style: none; margin: 0; padding: 0; display: grid; gap: .3rem; }
	.ds-consent__hijos li { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
	.ds-consent__hijos .ds-consent__hijo { font-weight: 500; margin-right: .15rem; }
	/* Texto oscuro sobre el verde de WhatsApp: con blanco no llega a 4,5. */
	.btn-whatsapp { background: #25D366; border-color: #25D366; color: #212529; font-weight: 600; }
	.btn-whatsapp:hover, .btn-whatsapp:focus { background: #1ebe5c; border-color: #1ebe5c; color: #212529; }
	/* Modal «Enviar autorización» */
	.ds-aut-destino {
		display: flex; align-items: center; gap: 12px; padding: 10px 12px;
		border: 1px solid var(--bs-border-color); border-radius: var(--ds-radius-sm, 10px);
		background: var(--bs-tertiary-bg);
	}
	.ds-aut-avatar {
		width: 40px; height: 40px; flex: none; display: grid; place-items: center;
		border-radius: 50%; background: var(--bs-primary); color: var(--bs-white);
	}
	.ds-aut-canales { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
	.ds-aut-canal {
		display: flex; align-items: center; gap: .6rem; text-align: left;
		min-height: 3.5rem; padding: .55rem .75rem; font-weight: 600;
		border: 2px solid var(--bs-border-color); background: var(--bs-body-bg); color: var(--bs-body-color);
	}
	.ds-aut-canal small { display: block; font-weight: 400; font-size: .78rem; color: var(--bs-secondary-color);
		overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
	.ds-aut-canal > span { min-width: 0; }
	.ds-aut-canal__icono { font-size: 1.35rem; flex: none; }
	.ds-aut-canal .fa-whatsapp { color: #1a9e4b; }
	.btn-check:checked + .ds-aut-canal { border-color: var(--bs-primary); background: var(--bs-primary-bg-subtle); color: var(--bs-emphasis-color); }
	.btn-check:focus-visible + .ds-aut-canal { outline: 3px solid var(--bs-primary); outline-offset: 2px; }
	.btn-check:disabled + .ds-aut-canal { opacity: .55; }
	#autCuerpo { font-size: .92rem; line-height: 1.45; }
	.ds-aut-enlace {
		padding: .65rem .8rem; border-radius: var(--ds-radius-sm, 10px);
		border: 1px dashed var(--bs-border-color); background: var(--bs-tertiary-bg);
	}
	.ds-aut-enlace__texto { white-space: pre-wrap; font-size: .85rem; color: var(--bs-secondary-color); }
	/* El enlace firmado es largo: una línea recortada; completo en «Ver lo que recibe». */
	.ds-aut-enlace__url { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: .8rem; margin: .1rem 0; }
	.btn-whatsapp:disabled { background: #25D366; border-color: #25D366; color: #212529; opacity: .5; }
	@media (max-width: 575.98px) {
		#modalAutorizacion .modal-footer > .btn { flex: 1 1 auto; min-height: 2.75rem; }
		.ds-consent__resumen { gap: .5rem; }
		.ds-consent__cifra { padding: .6rem .7rem; }
		.ds-consent__cifra strong { font-size: 1.25rem; }
		.ds-consent__cifra span { font-size: .75rem; }
		.ds-consent__masivo { width: 100%; }
	}
</style>
<?php
	$cabeceraExtra = ob_get_clean();
	require_once "app/views/inc/cabecera.php";
?>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper ds-core">

	<?php require_once "app/views/inc/navbar.php"; ?>
	<?php require_once "app/views/inc/main-sidebar.php"; ?>

	<div class="app-main">

		<div class="app-content-header">
			<div class="container-fluid">
				<div class="row mb-2">
					<div class="col-sm-6">
						<h4 class="m-0">Consentimientos LOPDP</h4>
					</div>
					<div class="col-sm-6 d-none d-sm-block">
						<ol class="breadcrumb float-sm-end">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL.'dashboard/'; ?>">Dashboard</a></li>
							<li class="breadcrumb-item active">Consentimientos</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Búsqueda de representantes =================== -->
				<form method="GET" action="<?php echo APP_URL; ?>consentimientoList/" class="card ds-crud__form ds-consent__busqueda mb-3" id="busqueda" role="search" aria-label="Búsqueda de representantes">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-magnifying-glass me-2" aria-hidden="true"></i>Búsqueda de representantes</h3>
						<?php if ($filtrada): ?>
							<div class="card-tools">
								<a href="<?php echo APP_URL; ?>consentimientoList/" class="btn btn-sm btn-link px-0" id="busquedaLimpiar">
									<i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Quitar filtros
								</a>
							</div>
						<?php endif; ?>
					</div>
					<div class="card-body">
						<div class="row g-3 align-items-end">
							<div class="col-12 col-md-4">
								<label for="busquedaSede" class="form-label">Sede</label>
								<?php /* Sólo las sedes de la sesión. Con una sola no hay nada que elegir. */ ?>
								<select class="form-select" id="busquedaSede" name="sede" <?php echo count($sedes) <= 1 ? 'disabled' : ''; ?>>
									<?php if (count($sedes) > 1): ?>
										<option value="0" <?php echo $sedeId === 0 ? 'selected' : ''; ?>><?php echo sedes_de_la_sesion() === null ? 'Todas las sedes' : 'Todas mis sedes'; ?></option>
									<?php endif; ?>
									<?php foreach ($sedes as $id => $nombre): ?>
										<option value="<?php echo (int)$id; ?>" <?php echo $sedeId === (int)$id ? 'selected' : ''; ?>><?php echo $h($nombre); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-md-3">
								<label for="busquedaEstado" class="form-label">Estado del alumno</label>
								<select class="form-select" id="busquedaEstado" name="estado">
									<?php foreach (consentimientoController::ESTADOS as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>" <?php echo $estado === $valor ? 'selected' : ''; ?>><?php echo $h($texto); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-md-3">
								<label for="busquedaAutorizacion" class="form-label">Autorización</label>
								<select class="form-select" id="busquedaAutorizacion" name="autorizacion">
									<?php foreach (consentimientoController::AUTORIZACION as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>" <?php echo $autorizacion === $valor ? 'selected' : ''; ?>><?php echo $h($texto); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-12 col-md-2">
								<button type="submit" class="btn btn-primary w-100" id="busquedaBuscar">
									<i class="fas fa-magnifying-glass me-1" aria-hidden="true"></i>Buscar
								</button>
							</div>
						</div>
					</div>
				</form>

				<div class="ds-consent__resumen" role="list">
					<div class="ds-consent__cifra" role="listitem"><strong><?php echo $totalAlumnos; ?></strong><span><?php echo $etiquetaAlumnos; ?></span></div>
					<div class="ds-consent__cifra" role="listitem"><strong><?php echo $totalAlumnos - $alumnosPend; ?></strong><span>Con respuesta completa</span></div>
					<div class="ds-consent__cifra" role="listitem"><strong><?php echo $porAutorizar; ?></strong><span>Representantes por autorizar</span></div>
				</div>

				<!-- =================== Representantes =================== -->
				<div class="card ds-crud__lista" id="pendientes">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-users me-2" aria-hidden="true"></i>Representantes
							<span class="badge text-bg-secondary ms-1" id="totalRepres"><?php echo count($repres); ?></span>
						</h3>
						<?php if ($puedeEnviar && $conCorreo > 0): ?>
							<div class="card-tools">
								<button type="button" class="btn btn-sm btn-primary ds-consent__masivo" id="btnCorreoTodos" data-total="<?php echo $conCorreo; ?>">
									<i class="fas fa-envelope me-1" aria-hidden="true"></i>Enviar por correo a <?php echo $conCorreo; ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
					<div class="card-body">
						<?php if (!$repres): ?>
							<p class="mb-0 text-body-secondary" id="sinRepres"><i class="fas fa-circle-info me-1" aria-hidden="true"></i>
								<?php echo $autorizacion === 'P' && $totalAlumnos > 0
									? 'Todos los representantes de esta búsqueda respondieron las dos autorizaciones.'
									: 'No hay representantes con alumnos que cumplan esta búsqueda.'; ?></p>
						<?php else: ?>
							<p class="small text-body-secondary">
								Representantes con al menos un alumno <?php echo ['A' => 'activo', 'I' => 'inactivo', 'T' => 'activo o inactivo'][$estado]; ?>
								<?php echo $sedeId ? 'en ' . $h($sedes[$sedeId]) : (count($sedes) > 1 ? 'en sus sedes' : ''); ?>.
								A quien le falta la autorización de <strong>tratamiento de datos</strong> o la respuesta sobre el <strong>uso de imagen</strong>
								se le envía un enlace que muestra sólo las dos autorizaciones; es personal, vence en 7 días y sirve una sola vez.
							</p>
							<table id="tablaPendientes" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
								<thead>
									<tr>
										<th>Representante</th>
										<th>Alumnos</th>
										<th>Estado</th>
										<th class="text-end"><span class="visually-hidden">Acciones</span></th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ($repres as $p): ?>
									<tr data-repre="<?php echo (int)$p['repre_id']; ?>">
										<td class="ds-ficha__principal" data-search="<?php echo $h($p['representante'] . ' ' . $p['identificacion'] . ' ' . implode(' ', array_unique(array_column($p['alumnos'], 'sede')))); ?>">
											<div class="ds-ficha__nombre"><?php echo $h($p['representante']); ?></div>
											<div class="ds-ficha__meta">
												<?php echo $h($p['identificacion']); ?>
												<?php if ($p['celular'] !== ''): ?> · <?php echo $h($p['celular']); ?><?php endif; ?>
											</div>
										</td>
										<td class="ds-ficha__dato">
											<ul class="ds-consent__hijos">
												<?php foreach ($p['alumnos'] as $a): ?>
													<li><span class="ds-consent__hijo"><?php echo $h($a['nombre']); ?></span>
														<?php if ($a['estado'] !== 'A'): ?><span class="badge text-bg-light border">Inactivo</span><?php endif; ?>
														<?php if ($sedeId === 0 && count($sedes) > 1): ?><span class="ds-ficha__meta"><?php echo $h($a['sede']); ?></span><?php endif; ?>
														<?php echo chipConsent('Datos', $a['datos']); ?>
														<?php echo chipConsent('Imagen', $a['imagen']); ?></li>
												<?php endforeach; ?>
											</ul>
										</td>
										<td class="ds-ficha__estado" data-p="envio">
											<?php echo $p['pendiente'] ? ultimoEnvio($p['ultimo']) : '<span class="badge text-bg-success"><i class="fas fa-check me-1" aria-hidden="true"></i>Completa</span>'; ?>
										</td>
										<td class="ds-ficha__acciones">
											<?php if ($puedeEnviar && $p['pendiente']): ?>
											<div class="ds-ficha__botones">
												<?php if ($p['activos'] > 0): ?>
													<?php /* Un solo botón: el canal se elige en el modal, viendo el mensaje. */ ?>
													<button type="button" class="btn btn-sm btn-primary ds-ficha__primario" data-autorizacion
															data-nombre="<?php echo $h($p['representante']); ?>"
															data-con-correo="<?php echo $p['correo'] !== '' ? '1' : '0'; ?>">
														<i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?php echo $p['ultimo'] ? 'Reenviar autorización' : 'Enviar autorización'; ?>
													</button>
												<?php else: ?>
													<?php /* El enlace pide la autorización por los alumnos activos: sin ninguno, no hay qué enviar. */ ?>
													<span class="d-inline-block w-100" tabindex="0" title="Sin alumnos activos: el enlace pide la autorización sólo por alumnos activos">
														<button type="button" class="btn btn-sm btn-outline-secondary ds-ficha__primario w-100" disabled>
															<i class="fas fa-ban me-1" aria-hidden="true"></i>Sin alumnos activos
														</button>
													</span>
												<?php endif; ?>
											</div>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<!-- =================== Por alumno =================== -->
				<div class="card ds-crud__lista" id="por-alumno">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-user-shield me-2" aria-hidden="true"></i>Autorizaciones por alumno
							<span class="badge text-bg-secondary ms-1"><?php echo $totalAlumnos; ?></span></h3>
					</div>
					<div class="card-body">
						<p class="small text-body-secondary">
							Cada autorización indica de dónde proviene:
							<span class="badge text-bg-info">Formulario</span> la aceptó al inscribirse en línea,
							<span class="badge text-bg-primary">Enlace</span> la respondió desde el enlace enviado, y
							<span class="badge text-bg-warning">Administrador</span> se registró desde el sistema (por ejemplo, con el formulario firmado en papel).
						</p>
						<table id="tablaConsentimientos" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Alumno</th>
									<th>Representante</th>
									<th>Tratamiento de datos</th>
									<th>Uso de imagen</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($filas as $f): ?>
								<tr>
									<td class="ds-ficha__principal" data-search="<?php echo $h($f['alumno'] . ' ' . $f['alumno_identificacion'] . ' ' . $f['sede_nombre']); ?>">
										<div class="ds-ficha__nombre"><?php echo $h($f['alumno']); ?></div>
										<div class="ds-ficha__meta"><?php echo $h($f['alumno_identificacion']); ?> · <?php echo $h($f['sede_nombre']); ?>
											<?php if ($f['alumno_estado'] !== 'A'): ?><span class="badge text-bg-light border ms-1">Inactivo</span><?php endif; ?></div>
									</td>
									<td class="ds-ficha__dato" data-etiqueta="Representante"><?php echo $h($f['representante']); ?></td>
									<td class="ds-ficha__dato" data-etiqueta="Datos"><?php echo badgeConsent($f['datos_otorgado'], $f['datos_origen'], $f['datos_fecha'], $f['datos_usuario']); ?></td>
									<td class="ds-ficha__dato" data-etiqueta="Imagen"><?php echo badgeConsent($f['imagen_otorgado'], $f['imagen_origen'], $f['imagen_fecha'], $f['imagen_usuario']); ?></td>
									<td class="ds-ficha__acciones">
										<div class="ds-ficha__botones">
											<button type="button" class="btn btn-sm btn-info ds-ficha__primario btn-historial" data-id="<?php echo $f['alumno_id']; ?>"
													data-alumno="<?php echo $h($f['alumno']); ?>" title="Ver historial">
												<i class="fas fa-history me-1" aria-hidden="true"></i>Historial
											</button>
											<?php if ($puedeEnviar): ?>
											<button type="button" class="btn btn-sm btn-primary btn-registrar" data-id="<?php echo $f['alumno_id']; ?>"
													data-alumno="<?php echo $h($f['alumno']); ?>" title="Registrar o revocar" aria-label="Registrar o revocar">
												<i class="fas fa-pen" aria-hidden="true"></i>
											</button>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

			</div>
		</section>
	</div>

	<?php require_once "app/views/inc/footer.php"; ?>
</div>

<!-- ==================== Enviar autorización ====================
     El mismo esquema que «Enviar recibo por WhatsApp» (ds_core/inc/whatsappRecibo.php):
     a quién, por dónde, el mensaje que se puede corregir y lo que no se toca
     (el enlace personal). El enlace se prepara al abrir y sólo cuenta como
     enviado cuando de verdad sale. -->
<div class="modal fade" id="modalAutorizacion" tabindex="-1" aria-labelledby="autTitulo" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="autTitulo"><i class="fas fa-paper-plane me-2 text-primary" aria-hidden="true"></i>Enviar autorización</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div class="ds-aut-destino mb-3">
					<div class="ds-aut-avatar" aria-hidden="true"><i class="fas fa-user-shield"></i></div>
					<div class="flex-grow-1" style="min-width:0">
						<div class="fw-semibold text-truncate" id="autNombre"></div>
						<div class="small text-body-secondary" id="autHijos">&nbsp;</div>
					</div>
				</div>

				<div id="autCargando" class="text-center text-body-secondary py-4" role="status">
					<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Preparando el enlace…
				</div>

				<div id="autContenido" hidden>
					<fieldset class="mb-3 border-0 p-0 m-0">
						<legend class="form-label fs-6 mb-2">Enviar por</legend>
						<div class="ds-aut-canales">
							<input type="radio" class="btn-check" name="autCanal" id="autCanalWa" value="WHATSAPP" autocomplete="off">
							<label class="btn ds-aut-canal" for="autCanalWa">
								<i class="fab fa-whatsapp ds-aut-canal__icono" aria-hidden="true"></i>
								<span>WhatsApp<small id="autResumenWa"></small></span>
							</label>
							<input type="radio" class="btn-check" name="autCanal" id="autCanalCorreo" value="CORREO" autocomplete="off">
							<label class="btn ds-aut-canal" for="autCanalCorreo">
								<i class="fas fa-envelope ds-aut-canal__icono" aria-hidden="true"></i>
								<span>Correo<small id="autResumenCorreo"></small></span>
							</label>
						</div>
					</fieldset>

					<div id="autGrupoWa">
						<label for="autCelular" class="form-label">Número de WhatsApp <span class="text-danger">*</span></label>
						<div class="input-group">
							<span class="input-group-text"><i class="fas fa-mobile-alt" aria-hidden="true"></i></span>
							<input type="tel" class="form-control" id="autCelular" inputmode="tel" autocomplete="off" placeholder="09XXXXXXXX">
						</div>
						<div class="form-text" id="autCelularAyuda"></div>
					</div>

					<div id="autGrupoCorreo" hidden>
						<label for="autCorreo" class="form-label">Correo del representante <span class="text-danger">*</span></label>
						<div class="input-group">
							<span class="input-group-text"><i class="fas fa-at" aria-hidden="true"></i></span>
							<input type="email" class="form-control" id="autCorreo" inputmode="email" autocomplete="off" placeholder="correo@ejemplo.com">
						</div>
						<div class="form-text" id="autCorreoAyuda"></div>
					</div>

					<label for="autCuerpo" class="form-label mt-3">Mensaje</label>
					<textarea class="form-control" id="autCuerpo" rows="5" maxlength="1500"></textarea>

					<div class="ds-aut-enlace mt-3">
						<div class="d-flex align-items-center gap-2 mb-1">
							<i class="fas fa-link" aria-hidden="true"></i>
							<strong class="small">Se agrega al final, con el enlace personal</strong>
						</div>
						<div class="ds-aut-enlace__texto" id="autPie"></div>
						<div class="d-flex flex-wrap align-items-center gap-2 mt-2 small">
							<a href="#" id="autVer" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square me-1" aria-hidden="true"></i>Ver lo que recibe</a>
						</div>
					</div>

					<div class="alert alert-danger mt-3 mb-0 py-2" id="autAviso" role="alert" hidden></div>
				</div>
			</div>
			<div class="modal-footer flex-wrap gap-2">
				<button type="button" class="btn btn-outline-secondary me-auto" id="autCopiar" disabled>
					<i class="far fa-copy me-1" aria-hidden="true"></i><span>Copiar mensaje</span>
				</button>
				<button type="button" class="btn btn-whatsapp" id="autEnviar" disabled>
					<i class="fab fa-whatsapp me-1" aria-hidden="true"></i><span>Abrir WhatsApp</span>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Modal historial -->
<div class="modal fade" id="modalHistorial" tabindex="-1" aria-labelledby="histTitulo" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-fullscreen-md-down modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="histTitulo"><i class="fas fa-history"></i> Historial &mdash; <span id="histAlumno"></span></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body" id="histContenido">
				<p class="text-muted mb-0">Cargando...</p>
			</div>
		</div>
	</div>
</div>

<!-- Modal registrar -->
<div class="modal fade" id="modalRegistrar" tabindex="-1" aria-labelledby="regTitulo" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen-sm-down">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="regTitulo"><i class="fas fa-pen"></i> Registrar consentimiento</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<p class="text-muted">
					Alumno: <strong id="regAlumno"></strong>
				</p>
				<input type="hidden" id="reg_alumno_id">

				<div class="mb-3">
					<label class="form-label" for="reg_tipo">Consentimiento</label>
					<select class="form-select" id="reg_tipo">
						<option value="DATOS">Tratamiento de datos personales</option>
						<option value="IMAGEN">Uso de imagen del menor</option>
					</select>
				</div>

				<div class="mb-3">
					<label class="form-label" for="reg_otorgado">Acción</label>
					<select class="form-select" id="reg_otorgado">
						<option value="S">Otorgar</option>
						<option value="N">Revocar</option>
					</select>
				</div>

				<div class="mb-3">
					<label class="form-label" for="reg_observacion">Observación</label>
					<textarea class="form-control" id="reg_observacion" rows="2" maxlength="200"
							  placeholder="Ej: formulario firmado recibido en la sede el 12/08/2026"></textarea>
				</div>

				<div class="alert alert-warning mb-0" style="font-size:.85rem;">
					<i class="fas fa-exclamation-triangle"></i>
					Quedará registrado con origen <strong>Administrador</strong> y su usuario
					(<strong><?php echo $h($_SESSION['usuario'] ?? ''); ?></strong>).
					Regístrelo solo si tiene respaldo del consentimiento del representante.
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="btnGuardarConsent">Guardar</button>
			</div>
		</div>
	</div>
</div>

<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var AJAX = '<?php echo APP_URL; ?>app/ajax/consentimientoAjax.php';

	/* Búsqueda de representantes: cada cambio busca, sin tener que pulsar
	   «Buscar» (que queda para quien navega con teclado o sin JavaScript). */
	var busqueda = document.getElementById('busqueda');
	busqueda.querySelectorAll('select').forEach(function (s) {
		s.addEventListener('change', function () {
			var b = document.getElementById('busquedaBuscar');
			b.disabled = true;
			b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Buscando…';
			busqueda.submit();
		});
	});

	/* Tabla en escritorio, fichas en el teléfono (futbol.css, .ds-crud).
	   Una sola caja de filtrar por tabla: la búsqueda por ?q= ya no existe. */
	function idioma(cosa) {
		return {
			emptyTable: 'No hay ' + cosa + ' que mostrar',
			info: '_START_ a _END_ de _TOTAL_ ' + cosa,
			infoEmpty: 'Sin ' + cosa,
			infoFiltered: '(de _MAX_)',
			lengthMenu: 'Mostrar _MENU_',
			search: '',
			searchPlaceholder: 'Filtrar por nombre, cédula o sede…',
			zeroRecords: 'Ninguno coincide',
			paginate: { next: 'Siguiente', previous: 'Anterior' }
		};
	}
	var layout = { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: { paging: { firstLast: false } } };
	var tablaPend = null;
	if (document.getElementById('tablaPendientes')) {
		tablaPend = new DataTable('#tablaPendientes', {
			autoWidth: false, pageLength: 10, order: [[0, 'asc']], searchDelay: 250, layout: layout,
			columnDefs: [{ targets: 3, orderable: false, searchable: false }],
			language: idioma('representantes')
		});
	}
	new DataTable('#tablaConsentimientos', {
		autoWidth: false, pageLength: 25, order: [[0, 'asc']], searchDelay: 250, layout: layout,
		columnDefs: [{ targets: 4, orderable: false, searchable: false }],
		language: idioma('alumnos')
	});

	/*----------  Pedir la autorización  ----------*/

	function pedir(datos) {
		var d = new FormData();
		Object.keys(datos).forEach(function (k) { d.append(k, datos[k]); });
		return fetch(AJAX, { method: 'POST', body: d, credentials: 'same-origin' })
			.then(function (r) { return r.json(); });
	}

	function marcarEnviado(fila, canal) {
		var celda = fila.querySelector('[data-p="envio"]');
		var hoy = new Date();
		var f = ('0' + hoy.getDate()).slice(-2) + '/' + ('0' + (hoy.getMonth() + 1)).slice(-2) + '/' + hoy.getFullYear();
		celda.innerHTML = '<span class="badge text-bg-info">Enviado</span><div class="ds-ficha__meta">' + f + ' · ' + canal + '</div>';
		var b = fila.querySelector('[data-autorizacion]');
		if (b) { b.lastChild.textContent = 'Reenviar autorización'; }
	}

	function aviso(icono, titulo, texto) {
		Swal.fire({ toast: true, position: 'top-end', timer: 3500, showConfirmButton: false, icon: icono, title: titulo, text: texto || '' });
	}

	/*----------  Modal «Enviar autorización»  ----------*/

	var modalEl  = document.getElementById('modalAutorizacion');
	var modalAut = bootstrap.Modal.getOrCreateInstance(modalEl);
	var $a = function (id) { return document.getElementById(id); };
	var celular = $a('autCelular'), correo = $a('autCorreo'), cuerpo = $a('autCuerpo');
	var btnEnviar = $a('autEnviar'), btnCopiar = $a('autCopiar'), avisoModal = $a('autAviso');
	var actual = null;   /* { fila, r: respuesta de generar_enlace } */
	var ESPERA_ENVIO = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Enviando…';

	function canal() { return $a('autCanalCorreo').checked ? 'CORREO' : 'WHATSAPP'; }
	function textoFinal() { return cuerpo.value.trim() + '\n\n' + actual.r.pie; }
	function avisar(texto) { avisoModal.textContent = texto; avisoModal.hidden = !texto; }

	/* El número en formato internacional, como el recibo: 0991234567 →
	   593991234567; con «+» y prefijo de otro país, se respeta. */
	function numero() {
		var crudo = celular.value.trim(), n = crudo.replace(/\D/g, '');
		if (/^\+/.test(crudo))     { return n.length >= 8 && n.length <= 15 ? n : ''; }
		if (/^09\d{8}$/.test(n))   { return '593' + n.slice(1); }
		if (/^9\d{8}$/.test(n))    { return '593' + n; }
		if (/^5939\d{8}$/.test(n)) { return n; }
		return '';
	}
	function correoValido() { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(correo.value.trim()); }

	/* Destino, ayuda y botón principal según el canal elegido. */
	function refrescar() {
		var wa = canal() === 'WHATSAPP', ok;
		$a('autGrupoWa').hidden = !wa;
		$a('autGrupoCorreo').hidden = wa;
		if (wa) {
			var n = numero();
			ok = !!n;
			$a('autCelularAyuda').innerHTML = !celular.value.trim()
				? '<span class="text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>No hay celular registrado. Escríbalo para continuar.</span>'
				: (n ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Se abrirá WhatsApp con +' + n.replace(/^(593)(\d{2})(\d{3})(\d{4})$/, '$1 $2 $3 $4') + '</span>'
				     : '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Use un celular de 10 dígitos (09…) o el formato internacional (+57…).</span>');
			celular.classList.toggle('is-invalid', !!celular.value.trim() && !n);
			btnEnviar.className = 'btn btn-whatsapp';
			btnEnviar.innerHTML = '<i class="fab fa-whatsapp me-1" aria-hidden="true"></i><span>Abrir WhatsApp</span>';
		} else {
			ok = correoValido();
			$a('autCorreoAyuda').innerHTML = !correo.value.trim()
				? '<span class="text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>No hay correo registrado. Escríbalo para continuar.</span>'
				: (ok ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Se enviará desde el sistema a ' + $('<i>').text(correo.value.trim()).html() + '</span>'
				      : '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Correo no válido.</span>');
			correo.classList.toggle('is-invalid', !!correo.value.trim() && !ok);
			btnEnviar.className = 'btn btn-primary';
			btnEnviar.innerHTML = '<i class="fas fa-envelope me-1" aria-hidden="true"></i><span>Enviar correo</span>';
		}
		$a('autResumenWa').textContent = celular.value.trim() || 'sin celular';
		$a('autResumenCorreo').textContent = correo.value.trim() || 'sin correo';
		btnEnviar.disabled = !ok;
		return ok;
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-autorizacion]');
		if (!b) { return; }
		var fila = b.closest('tr');
		actual = null;
		$a('autNombre').textContent = b.dataset.nombre;
		$a('autHijos').innerHTML = '&nbsp;';
		$a('autCargando').hidden = false;
		$a('autContenido').hidden = true;
		btnEnviar.disabled = btnCopiar.disabled = true;
		avisar('');
		modalAut.show();

		pedir({ modulo_consentimiento: 'generar_enlace', repre_id: fila.dataset.repre }).then(function (r) {
			if (r.tipo !== 'enlace') {
				$a('autCargando').innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>' + $('<i>').text(r.texto || 'No se pudo preparar el enlace.').html() + '</span>';
				return;
			}
			actual = { fila: fila, r: r };
			$a('autHijos').textContent = 'Representante de ' + r.hijos;
			celular.value = r.celular || '';
			correo.value = r.correo || '';
			cuerpo.value = r.cuerpo;
			/* El pie con el enlace en su propia línea, recortada. */
			var partes = r.pie.split(r.url), pie = $a('autPie'), url = document.createElement('code');
			url.className = 'ds-aut-enlace__url'; url.textContent = r.url; url.title = r.url;
			pie.textContent = '';
			pie.append(partes[0].trim(), url, (partes[1] || '').trim());
			$a('autVer').href = r.url;
			/* Por dónde, de entrada: WhatsApp si hay celular; si no, correo si hay. */
			var porCorreo = !numero() && correoValido();
			$a('autCanalWa').checked = !porCorreo;
			$a('autCanalCorreo').checked = porCorreo;
			$a('autCargando').hidden = true;
			$a('autContenido').hidden = false;
			btnCopiar.disabled = false;
			refrescar();
		}).catch(function () {
			$a('autCargando').innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>No fue posible comunicarse con el servidor.</span>';
		});
	});

	/* Al cerrar, el cargador vuelve a su estado (pudo quedar con un error). */
	modalEl.addEventListener('hidden.bs.modal', function () {
		$a('autCargando').innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Preparando el enlace…';
	});
	modalEl.addEventListener('shown.bs.modal', function () { btnEnviar.focus(); });
	[$a('autCanalWa'), $a('autCanalCorreo')].forEach(function (r) { r.addEventListener('change', function () { avisar(''); refrescar(); }); });
	celular.addEventListener('input', refrescar);
	correo.addEventListener('input', refrescar);

	function anotar(c) {
		return pedir({ modulo_consentimiento: 'generar_envio', enlace_id: actual.r.enlace_id, canal: c });
	}

	btnEnviar.addEventListener('click', function () {
		if (!actual || !refrescar()) { return; }
		avisar('');

		if (canal() === 'WHATSAPP') {
			/* Se abre dentro del toque (el enlace ya está listo): el teléfono no
			   lo bloquea. Después se anota el envío. */
			window.open('https://wa.me/' + numero() + '?text=' + encodeURIComponent(textoFinal()), '_blank', 'noopener');
			var fila = actual.fila;
			anotar('WHATSAPP').then(function (r) {
				if (r.icono === 'success') { marcarEnviado(fila, 'WhatsApp'); }
			}).catch(function () {});
			modalAut.hide();
			return;
		}

		var antes = btnEnviar.innerHTML;
		btnEnviar.disabled = true;
		btnEnviar.innerHTML = ESPERA_ENVIO;
		pedir({ modulo_consentimiento: 'generar_envio', enlace_id: actual.r.enlace_id, canal: 'CORREO',
		        correo: correo.value.trim(), cuerpo: cuerpo.value }).then(function (r) {
			if (r.icono === 'success') {
				marcarEnviado(actual.fila, 'correo');
				modalAut.hide();
				aviso('success', r.titulo, r.texto);
			} else {
				avisar(r.texto || r.titulo);
			}
		}).catch(function () {
			avisar('No fue posible comunicarse con el servidor.');
		}).finally(function () {
			btnEnviar.innerHTML = antes;
			refrescar();
		});
	});

	btnCopiar.addEventListener('click', function () {
		if (!actual) { return; }
		var etiqueta = btnCopiar.querySelector('span'), texto = textoFinal(), fila = actual.fila;
		var listo = function () {
			etiqueta.textContent = '¡Copiado!';
			setTimeout(function () { etiqueta.textContent = 'Copiar mensaje'; }, 1800);
			anotar('COPIA').then(function (r) { if (r.icono === 'success') { marcarEnviado(fila, 'enlace copiado'); } }).catch(function () {});
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(texto).then(listo);
		} else {
			var t = document.createElement('textarea');
			t.value = texto; document.body.appendChild(t); t.select();
			try { document.execCommand('copy'); listo(); } catch (err) {}
			t.remove();
		}
	});

	/* A todos los que tienen correo, uno por uno: una sola petición con
	   decenas de correos superaría el tiempo máximo del servidor. */
	var todos = document.getElementById('btnCorreoTodos');
	if (todos) {
		todos.addEventListener('click', function () {
			/* DataTables saca del documento las filas de otras páginas: se
			   toman de la tabla, todas, no sólo las visibles. */
			var botones = tablaPend.rows().nodes().toArray()
				.map(function (tr) { return tr.querySelector('[data-autorizacion][data-con-correo="1"]'); })
				.filter(Boolean);
			Swal.fire({
				title: '¿Enviar a ' + botones.length + ' representantes?', icon: 'question', showCancelButton: true,
				text: 'Cada uno recibe en su correo su propio enlace. Los que no tienen correo quedan para WhatsApp.',
				confirmButtonText: 'Enviar a todos', cancelButtonText: 'Cancelar'
			}).then(function (c) {
				if (!c.isConfirmed) { return; }
				var ok = 0, fallos = [], i = 0;
				Swal.fire({ title: 'Enviando…', html: '<span id="progresoCorreo">0 de ' + botones.length + '</span>', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
				(function siguiente() {
					if (i >= botones.length) {
						Swal.fire({
							title: fallos.length ? 'Envío con avisos' : 'Correos enviados',
							icon: fallos.length ? 'warning' : 'success', confirmButtonText: 'Aceptar',
							html: ok + ' enviados.' + (fallos.length ? '<br><br>No se enviaron:<br>' + fallos.join('<br>') : '')
						});
						return;
					}
					var fila = botones[i].closest('tr'); i++;
					var nombre = fila.querySelector('.ds-ficha__nombre').textContent.trim();
					var p = document.getElementById('progresoCorreo'); if (p) { p.textContent = i + ' de ' + botones.length; }
					pedir({ modulo_consentimiento: 'generar_correo', repre_id: fila.dataset.repre }).then(function (r) {
						if (r.icono === 'success') { ok++; marcarEnviado(fila, 'correo'); }
						else { fallos.push($('<i>').text(nombre + ': ' + (r.texto || r.titulo)).html()); }
					}).catch(function () { fallos.push($('<i>').text(nombre).html()); }).finally(siguiente);
				})();
			});
		});
	}

	/*----------  Historial y registro manual  ----------*/

	var modalHist = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHistorial'));
	var modalReg  = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRegistrar'));

	$(document).on('click', '.btn-historial', function () {
		var id = $(this).data('id');
		$('#histAlumno').text($(this).data('alumno'));
		$('#histContenido').html('<p class="text-muted mb-0">Cargando...</p>');
		modalHist.show();

		$.post(AJAX, { modulo_consentimiento: 'historial', alumno_id: id },
			function (html) { $('#histContenido').html(html); }
		);
	});

	$(document).on('click', '.btn-registrar', function () {
		$('#reg_alumno_id').val($(this).data('id'));
		$('#regAlumno').text($(this).data('alumno'));
		$('#reg_observacion').val('');
		modalReg.show();
	});

	$('#btnGuardarConsent').click(function () {
		var btn = $(this);
		btn.prop('disabled', true);

		$.post(AJAX, {
			modulo_consentimiento: 'registrar',
			alumno_id:   $('#reg_alumno_id').val(),
			tipo:        $('#reg_tipo').val(),
			otorgado:    $('#reg_otorgado').val(),
			observacion: $('#reg_observacion').val()
		}, function (data) {
			btn.prop('disabled', false);
			var r = (typeof data === 'string') ? JSON.parse(data) : data;

			modalReg.hide();
			Swal.fire({ title: r.titulo, text: r.texto, icon: r.icono })
				.then(function () { if (r.icono === 'success') location.reload(); });
		}).fail(function () {
			btn.prop('disabled', false);
			Swal.fire('Error', 'No se pudo guardar el consentimiento.', 'error');
		});
	});

});
</script>

</body>
</html>
