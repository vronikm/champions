<?php
	use app\controllers\alumnoController;
	$insAlumno = new alumnoController();

	$alumnoid = ds_id_de_url($url, 1, APP_URL . 'alumnoList/');

	$ficha = $insAlumno->fichaAlumno($alumnoid);
	if ($ficha === null) {
		/* El alumno no existe: al listado. Antes se pintaba el aviso de
		   error dentro de la página a medio montar. */
		header("Location: " . APP_URL . "alumnoList/");
		exit();
	}
	$al    = $ficha['alumno'];
	$repre = $ficha['representante'];
	$cemer = $ficha['emergencia'];
	$med   = $ficha['medica'];

	/* Los textos se guardan escapados (limpiarHtml): se deshace y se vuelve
	   a escapar una sola vez para no ver «&amp;» ni dejar pasar marcado. */
	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$vacio = '<span class="text-body-secondary">—</span>';
	$ver   = static fn($v) => trim(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '' ? $h($v) : $vacio;

	$fecha = static function ($v): ?DateTime {
		if (!$v || str_starts_with((string)$v, '0000')) { return null; }
		try { return new DateTime((string)$v); } catch (Exception $e) { return null; }
	};
	/* «12 años», o en meses si no llega al año: para la antigüedad de quien
	   acaba de entrar, «0 años» no dice nada. */
	$tiempo = static function (?DateTime $d): string {
		if (!$d) { return ''; }
		$dif = $d->diff(new DateTime('today'));
		if ($dif->invert) { return ''; }
		if ($dif->y > 0) { return $dif->y . ($dif->y === 1 ? ' año' : ' años'); }
		return $dif->m . ($dif->m === 1 ? ' mes' : ' meses');
	};

	/* $nombreAlumno y no $nombre: main-sidebar.php pisa $nombre con el de la sede. */
	$nombreAlumno   = trim($al['alumno_primernombre'] . ' ' . $al['alumno_apellidopaterno']);
	$nombreCompleto = preg_replace('/\s+/', ' ', trim($al['alumno_primernombre'] . ' ' . $al['alumno_segundonombre'] . ' '
	                . $al['alumno_apellidopaterno'] . ' ' . $al['alumno_apellidomaterno']));

	/* La foto, sólo si existe de verdad: media_url() devuelve la genérica
	   —el escudo— cuando falta, y parecía la foto del alumno. */
	$foto = $al['alumno_imagen'] != '' ? media_url('alumno', $al['alumno_imagen']) : '';
	if (str_contains($foto, '/dist/img/foto.jpg')) { $foto = ''; }
	$iniciales = mb_strtoupper(mb_substr((string)$al['alumno_primernombre'], 0, 1) . mb_substr((string)$al['alumno_apellidopaterno'], 0, 1));

	$nacimiento = $fecha($al['alumno_fechanacimiento']);
	$ingreso    = $fecha($al['alumno_fechaingreso']);
	$activo     = $al['alumno_estado'] === 'A';
	/* Lo que debe (mainModel::resumenCuentaAlumnos, la misma cuenta que Pagos
	   y Cobranza). Antes sólo se contaban los pagos con saldo y quien debía
	   pensiones sin generar salía «Pagos al día». */
	$deudaAl  = (float)($al['deuda'] ?? 0);

	/* Teléfonos: «Llamar» y «WhatsApp» los arma ds_botones_contacto()
	   (ds_core/inc/ui.php), común a las fichas. */
	$telefono = static fn($v): string => ds_botones_contacto((string)$v);

	/* Lo que conviene saber antes de que entrene: alergias, enfermedad y
	   medicación. «Ninguna», «No», «N/A»… se escriben mucho y no son un aviso. */
	$sinDato = static fn($v) => (bool)preg_match('/^(no|ningun[oa]?s?|nada|n\/?a|s\/?n|-+|\.+|0|sin\s.*)?$/iu',
		trim(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
	$avisos = [];
	if ($med) {
		foreach (['infomedic_alergia1' => 'Alergia a medicamentos', 'infomedic_alergia2' => 'Alergia a objetos',
		          'infomedic_enfermedad' => 'Enfermedad', 'infomedic_medicamentos' => 'Medicamentos'] as $campo => $rotulo) {
			if (!$sinDato($med[$campo])) { $avisos[$rotulo] = $med[$campo]; }
		}
	}
	$siNo = static fn($v) => $v === 'S' ? 'Sí' : ($v === 'N' ? 'No' : '');

	$DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
	$hora = static fn($v) => $v ? substr((string)$v, 0, 5) : '';

	$puedeEditar = function_exists('puede_editar') && puede_editar('alumnoList');

	/* Sede con código interno (PERSONALIZADO, migración 096): el mismo alumno
	   inscrito allí (PA000001…) o el original del que salió, y si se le puede
	   inscribir ahora. alumnoController::sedeParaInscribir decide. */
	$vinculos  = $ficha['vinculos'] ?? [];
	$inscribir = ($activo && function_exists('puede_crear') && puede_crear('alumnoList')) ? $insAlumno->sedeParaInscribir($al) : null;
	if ($inscribir && $inscribir['existente']) { $inscribir = null; }   /* ya inscrito: lo dice el vínculo */
	$nombreRepre = $repre ? preg_replace('/\s+/', ' ', trim($repre['repre_primernombre'] . ' ' . $repre['repre_segundonombre'] . ' '
	             . $repre['repre_apellidopaterno'] . ' ' . $repre['repre_apellidomaterno'])) : '';
	$verPermiso  = static fn(string $vista) => !function_exists('usuario_tiene_permiso') || usuario_tiene_permiso($vista);

	/* La cabecera es común a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Ficha de ' . $h($nombreAlumno);
	$extras      = ['swal'];
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
						<h4 class="m-0">Ficha del alumno</h4>
						<p class="small text-body-secondary mb-0"><?php echo $h($nombreAlumno); ?></p>
					</div>
					<div class="col-sm-5">
						<div class="d-flex flex-wrap align-items-center justify-content-between justify-content-sm-end gap-2">
							<ol class="breadcrumb mb-0">
								<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
								<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>alumnoList/">Alumnos</a></li>
								<li class="breadcrumb-item active" aria-current="page">Ficha</li>
							</ol>
							<?php /* Regresar: a la pantalla de la que se llegó (Alumnos,
							         Representantes, Inscripciones…). Si la ficha se abrió
							         sola —enlace directo, pestaña nueva— no hay a dónde
							         volver y el enlace lleva al listado de alumnos. */ ?>
							<?php echo ds_boton('volver', 'Regresar', ['href' => APP_URL . 'alumnoList/', 'estilo' => 'secondary', 'class' => 'btn-sm ds-perfil__regresar', 'id' => 'btnRegresar']); ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
				<div class="row g-3 ds-perfil">

					<!-- ============ Quién es ============ -->
					<div class="col-12 col-lg-4 col-xl-3">
						<div class="card ds-perfil__alumno">
							<div class="card-body">
								<div class="ds-perfil__identidad">
									<?php if ($foto !== ''): ?>
										<img class="ds-perfil__foto" src="<?php echo $h($foto); ?>" alt="Foto de <?php echo $h($nombreAlumno); ?>">
									<?php else: ?>
										<span class="ds-perfil__foto ds-perfil__foto--iniciales" aria-hidden="true"><?php echo $h($iniciales); ?></span>
									<?php endif; ?>
									<div class="ds-perfil__nombre">
										<strong><?php echo $h($nombreCompleto); ?></strong>
										<span class="text-body-secondary"><?php echo $h($al['alumno_identificacion']); ?></span>
										<div class="ds-perfil__insignias">
											<span class="badge <?php echo $activo ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $activo ? 'Activo' : 'Inactivo'; ?></span>
											<span class="badge <?php echo $deudaAl > 0 ? 'text-bg-danger' : 'text-bg-success'; ?>">
												<?php echo $deudaAl > 0 ? 'Debe $' . number_format($deudaAl, 2) : 'Pagos al día'; ?>
											</span>
										</div>
									</div>
								</div>

								<?php if ($vinculos): ?>
									<?php /* La misma persona en otra sede (096): el original o su registro con código. */ ?>
									<ul class="ds-perfil__vinculos">
										<?php foreach ($vinculos as $vn): $esOrigen = (int)$vn['alumno_id'] === (int)($al['alumno_origenid'] ?? 0); ?>
											<li>
												<a href="<?php echo APP_URL; ?>alumnoProfile/<?php echo (int)$vn['alumno_id']; ?>/" class="ds-perfil__vinculo">
													<i class="fas <?php echo $esOrigen ? 'fa-user' : 'fa-dumbbell'; ?>" aria-hidden="true"></i>
													<span>
														<?php echo $esOrigen ? 'Alumno de' : 'También en'; ?> <strong><?php echo $h($vn['sede_nombre']); ?></strong>
														<span class="d-block small"><?php echo $h($vn['alumno_identificacion']); ?><?php echo $vn['alumno_estado'] === 'A' ? '' : ' · inactivo'; ?></span>
													</span>
													<i class="fas fa-chevron-right ms-auto" aria-hidden="true"></i>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>

								<dl class="ds-perfil__datos">
									<div><dt>Sede</dt><dd><?php echo $ver($al['sede_nombre']); ?></dd></div>
									<div><dt>Categoría</dt><dd><?php echo $nacimiento ? $nacimiento->format('Y') : $vacio; ?></dd></div>
									<div><dt>Edad</dt><dd><?php echo $nacimiento ? $h($tiempo($nacimiento)) : $vacio; ?></dd></div>
									<div><dt>Posición</dt><dd><?php echo $ver($al['posicion_juego'] ?: 'No definido'); ?></dd></div>
									<div><dt>Camiseta</dt><dd><?php echo (int)$al['alumno_numcamiseta'] > 0 ? $h($al['alumno_numcamiseta']) : $vacio; ?></dd></div>
									<div><dt>Ingreso</dt><dd><?php echo $ingreso ? $ingreso->format('d/m/Y') : $vacio; ?></dd></div>
									<div><dt>En la escuela</dt><dd><?php echo $ingreso && $tiempo($ingreso) !== '' ? $h($tiempo($ingreso)) : $vacio; ?></dd></div>
								</dl>

								<!-- Pagos y asistencia: el mismo resumen que «Representa a» -->
								<section class="ds-perfil__cuenta" aria-labelledby="perfil-cuenta">
									<h4 class="ds-perfil__subtitulo" id="perfil-cuenta">Pagos y asistencia</h4>
									<?php $rcAlumno = $al; $rcIngreso = false; include "app/views/inc/resumenCuenta.php"; ?>
								</section>

								<div class="ds-perfil__acciones">
									<?php if ($puedeEditar): ?>
										<a href="<?php echo APP_URL; ?>alumnoUpdate/<?php echo (int)$alumnoid; ?>/" class="btn btn-sm btn-primary"><i class="fas fa-pen me-1" aria-hidden="true"></i>Editar</a>
									<?php endif; ?>
									<?php if ($verPermiso('reporteAsistencia')): ?>
										<a href="<?php echo APP_URL; ?>buscarAsistencia/<?php echo (int)$alumnoid; ?>/" class="btn btn-sm btn-secondary"><i class="fas fa-calendar-check me-1" aria-hidden="true"></i>Asistencia</a>
									<?php endif; ?>
									<?php if ($verPermiso('pagosList')): ?>
										<a href="<?php echo APP_URL; ?>pagosNew/<?php echo (int)$alumnoid; ?>/" class="btn btn-sm btn-secondary"><i class="fas fa-dollar-sign me-1" aria-hidden="true"></i>Pagos</a>
									<?php endif; ?>
									<?php if ($inscribir): ?>
										<button type="button" class="btn btn-sm btn-outline-primary ds-perfil__inscribir" data-bs-toggle="modal" data-bs-target="#modalInscribirSede">
											<i class="fas fa-dumbbell me-1" aria-hidden="true"></i>Inscribir en <?php echo $h($inscribir['sede_nombre']); ?>
										</button>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>

					<!-- ============ El resto, por secciones ============ -->
					<div class="col-12 col-lg-8 col-xl-9">
						<div class="row g-3">

							<!-- Contactos: lo primero que se busca a pie de cancha -->
							<div class="col-12 col-xl-6">
								<div class="card h-100">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-address-book me-2" aria-hidden="true"></i>Contactos</h3></div>
									<div class="card-body">
										<h4 class="ds-perfil__subtitulo">Representante</h4>
										<?php if ($repre): ?>
											<p class="ds-perfil__persona">
												<strong><?php echo $h(preg_replace('/\s+/', ' ', trim($repre['repre_primernombre'] . ' ' . $repre['repre_segundonombre'] . ' ' . $repre['repre_apellidopaterno'] . ' ' . $repre['repre_apellidomaterno']))); ?></strong>
												<?php if (trim((string)$repre['parentesco']) !== ''): ?><span class="text-body-secondary"><?php echo $h($repre['parentesco']); ?></span><?php endif; ?>
											</p>
											<dl class="ds-perfil__datos">
												<div><dt>Identificación</dt><dd><?php echo $ver($repre['repre_identificacion']); ?></dd></div>
												<div><dt>Celular</dt><dd><?php echo $ver($repre['repre_celular']); ?></dd></div>
												<div class="ds-perfil__ancho"><dt>Correo</dt><dd>
													<?php echo trim((string)$repre['repre_correo']) !== ''
														? '<a href="mailto:' . $h($repre['repre_correo']) . '">' . $h($repre['repre_correo']) . '</a>' : $vacio; ?>
												</dd></div>
												<div class="ds-perfil__ancho"><dt>Dirección</dt><dd><?php echo $ver($repre['repre_direccion']); ?></dd></div>
												<div><dt>Requiere factura</dt><dd><?php echo $siNo($repre['repre_factura']) ?: $vacio; ?></dd></div>
											</dl>
											<?php echo $telefono($repre['repre_celular']); ?>
										<?php else: ?>
											<p class="text-body-secondary mb-0">Sin representante registrado.</p>
										<?php endif; ?>

										<hr>
										<h4 class="ds-perfil__subtitulo">En caso de emergencia</h4>
										<?php if ($cemer && trim($cemer['cemer_nombre'] . $cemer['cemer_celular']) !== ''): ?>
											<p class="ds-perfil__persona">
												<strong><?php echo $ver($cemer['cemer_nombre']); ?></strong>
												<?php if (trim((string)$cemer['parentesco']) !== ''): ?><span class="text-body-secondary"><?php echo $h($cemer['parentesco']); ?></span><?php endif; ?>
											</p>
											<dl class="ds-perfil__datos">
												<div><dt>Celular</dt><dd><?php echo $ver($cemer['cemer_celular']); ?></dd></div>
											</dl>
											<?php echo $telefono($cemer['cemer_celular']); ?>
										<?php else: ?>
											<p class="text-body-secondary mb-0">Sin contacto de emergencia registrado.</p>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<!-- Salud -->
							<div class="col-12 col-xl-6">
								<div class="card h-100">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-notes-medical me-2" aria-hidden="true"></i>Información médica</h3></div>
									<div class="card-body">
										<?php if (!$med): ?>
											<p class="text-body-secondary mb-0">Sin información médica registrada.</p>
										<?php else: ?>
											<?php if ($avisos): ?>
												<div class="alert alert-warning ds-perfil__aviso" role="note">
													<strong><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Tener en cuenta</strong>
													<ul class="mb-0">
														<?php foreach ($avisos as $rotulo => $valor): ?>
															<li><?php echo $h($rotulo); ?>: <strong><?php echo $h($valor); ?></strong></li>
														<?php endforeach; ?>
													</ul>
												</div>
											<?php endif; ?>
											<dl class="ds-perfil__datos ds-perfil__datos--tres">
												<div><dt>Tipo de sangre</dt><dd><?php echo $ver($med['infomedic_tiposangre']); ?></dd></div>
												<div><dt>Peso</dt><dd><?php echo trim((string)$med['infomedic_peso']) !== '' ? $h($med['infomedic_peso']) . ' kg' : $vacio; ?></dd></div>
												<div><dt>Talla</dt><dd><?php echo trim((string)$med['infomedic_talla']) !== '' ? $h($med['infomedic_talla']) . ' cm' : $vacio; ?></dd></div>
												<div><dt>Enfermedad</dt><dd><?php echo $ver($med['infomedic_enfermedad']); ?></dd></div>
												<div><dt>Medicamentos</dt><dd><?php echo $ver($med['infomedic_medicamentos']); ?></dd></div>
												<div><dt>Cirugías</dt><dd><?php echo $ver($med['infomedic_cirugias']); ?></dd></div>
												<div><dt>Alergia a medicamentos</dt><dd><?php echo $ver($med['infomedic_alergia1']); ?></dd></div>
												<div><dt>Alergia a objetos</dt><dd><?php echo $ver($med['infomedic_alergia2']); ?></dd></div>
												<div><dt>Vacuna COVID</dt><dd><?php echo $siNo($med['infomedic_covid']) ?: $vacio; ?></dd></div>
												<div><dt>Vacunas habituales</dt><dd><?php echo $siNo($med['infomedic_vacunas']) ?: $vacio; ?></dd></div>
												<div class="ds-perfil__ancho"><dt>Observación</dt><dd><?php echo $ver($med['infomedic_observacion']); ?></dd></div>
											</dl>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<!-- Horario: un renglón por día, que se lee igual en un teléfono -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-clock me-2" aria-hidden="true"></i>Horario</h3></div>
									<div class="card-body">
										<?php if (!$ficha['horarios']): ?>
											<p class="text-body-secondary mb-0">Sin horario asignado.</p>
										<?php endif; ?>
										<?php foreach ($ficha['horarios'] as $i => $hor): ?>
											<?php if ($i > 0): ?><hr><?php endif; ?>
											<h4 class="ds-perfil__subtitulo">
												<?php echo $h($hor['nombre']); ?>
												<?php if (trim((string)$hor['detalle']) !== ''): ?><span class="text-body-secondary fw-normal"> · <?php echo $h($hor['detalle']); ?></span><?php endif; ?>
											</h4>
											<?php if (!$hor['dias']): ?>
												<p class="text-body-secondary mb-0">El horario no tiene días cargados.</p>
											<?php else: ?>
												<ul class="ds-perfil__horario">
													<?php foreach ($hor['dias'] as $d): ?>
														<li>
															<span class="ds-perfil__dia"><?php echo $h($DIAS[$d['dia']] ?? ('Día ' . $d['dia'])); ?></span>
															<span class="ds-perfil__hora"><?php echo $d['inicio'] ? $h($hora($d['inicio']) . ' – ' . $hora($d['fin'])) : $vacio; ?></span>
															<span class="ds-perfil__lugar">
																<?php if (trim((string)$d['lugar']) !== ''): ?><span><i class="fas fa-location-dot me-1" aria-hidden="true"></i><?php echo $h($d['lugar']); ?></span><?php endif; ?>
																<?php if (trim((string)$d['profesor']) !== ''): ?><span><i class="fas fa-user me-1" aria-hidden="true"></i><?php echo $h($d['profesor']); ?></span><?php endif; ?>
															</span>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
								</div>
							</div>

							<!-- Hermanos: los demás alumnos de su representante -->
							<?php if ($hermanos = $ficha['hermanos'] ?? []):
								$herActivos = count(array_filter($hermanos, static fn($x) => $x['alumno_estado'] === 'A')); ?>
							<div class="col-12">
								<div class="card">
									<div class="card-header d-flex align-items-center gap-2">
										<h3 class="card-title me-auto"><i class="fas fa-children me-2" aria-hidden="true"></i>Hermanos</h3>
										<span class="small text-body-secondary"><?php echo count($hermanos) . (count($hermanos) === 1 ? ' hermano' : ' hermanos') . ' · ' . $herActivos . ($herActivos === 1 ? ' activo' : ' activos'); ?></span>
									</div>
									<div class="card-body">
										<ul class="ds-repre__alumnos">
											<?php foreach ($hermanos as $hn):
												$hnNombre = preg_replace('/\s+/', ' ', trim(html_entity_decode($hn['alumno_primernombre'] . ' ' . $hn['alumno_segundonombre'] . ' ' . $hn['alumno_apellidopaterno'] . ' ' . $hn['alumno_apellidomaterno'], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
												$hnActivo = $hn['alumno_estado'] === 'A';
												$hnFoto   = $hn['alumno_imagen'] != '' ? media_url('alumno', $hn['alumno_imagen']) : '';
												if (str_contains($hnFoto, '/dist/img/foto.jpg')) { $hnFoto = ''; }
												$hnInic   = mb_strtoupper(mb_substr(html_entity_decode((string)$hn['alumno_primernombre']), 0, 1) . mb_substr(html_entity_decode((string)$hn['alumno_apellidopaterno']), 0, 1));
												$hnNac    = $fecha($hn['alumno_fechanacimiento']);
												$hnMeta   = array_filter([$hnNac ? $tiempo($hnNac) : '', $hnNac ? 'Cat. ' . $hnNac->format('Y') : '', html_entity_decode((string)$hn['sede_nombre'])]);
												/* Beca o descuento: sólo del hermano activo (al inactivo no se le cobra).
												   descuento_valor es la pensión que PAGA. */
												$hnCod = $hnActivo ? (string)$hn['descuento_rubroid'] : '';
												if ($hnCod !== '') {
													$hnSede  = (float)$hn['sede_pension'];
													$hnPaga  = $hnCod === 'DBC' ? 0.0 : min(max((float)$hn['descuento_valor'], 0), $hnSede);
													$hnBenef = ['DBC' => 'Beca 100 %', 'DBP' => 'Beca 50 %'][$hnCod] ?? (trim((string)$hn['descuento_nombre']) !== '' ? $hn['descuento_nombre'] : $hnCod);
													$hnDet   = $hnCod === 'DBC' ? 'no paga pensión' : 'paga $' . number_format($hnPaga, 2) . ($hnSede > 0 ? ' de $' . number_format($hnSede, 2) : '');
												} ?>
												<li>
													<a class="ds-repre__alumno<?php echo $hnActivo ? '' : ' ds-repre__alumno--inactivo'; ?>" href="<?php echo APP_URL; ?>alumnoProfile/<?php echo (int)$hn['alumno_id']; ?>/">
														<?php if ($hnFoto !== ''): ?>
															<img class="ds-repre__alumno-foto" src="<?php echo $h($hnFoto); ?>" alt="" loading="lazy">
														<?php else: ?>
															<span class="ds-repre__alumno-foto" aria-hidden="true"><?php echo $h($hnInic); ?></span>
														<?php endif; ?>
														<span class="ds-repre__alumno-datos">
															<strong><?php echo $h($hnNombre); ?></strong>
															<span><?php echo $h(implode(' · ', $hnMeta)); ?></span>
															<?php if ($hnActivo): ?>
																<span class="ds-perfil__hermano-benef">
																	<?php if ($hnCod !== ''): ?>
																		<span class="badge ds-repre__beneficio"><?php echo $h($hnBenef); ?></span> <?php echo $h($hnDet); ?>
																	<?php else: ?>
																		Sin beca ni descuento
																	<?php endif; ?>
																</span>
															<?php endif; ?>
															<?php
																/* Lo que debe (mainModel::resumenCuentaAlumnos): rojo con el
																   importe y de qué se compone, o «Al día» en verde. */
																$hnDeuda = (float)$hn['deuda'];
																$hnPartes = [];
																if ($hn['meses_deuda'] > 0) { $hnPartes[] = $hn['meses_deuda'] . ($hn['meses_deuda'] === 1 ? ' pensión' : ' pensiones') . ($hnActivo ? '' : ($hn['meses_deuda'] === 1 ? ' asistida' : ' asistidas') . ' sin pagar'); }
																if ($hn['saldo'] > 0) { $hnPartes[] = 'saldo $' . number_format($hn['saldo'], 2); }
															?>
															<span class="ds-perfil__hermano-deuda <?php echo $hnDeuda > 0 ? 'ds-perfil__hermano-deuda--mora' : 'ds-perfil__hermano-deuda--aldia'; ?>">
																<i class="fas <?php echo $hnDeuda > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?>" aria-hidden="true"></i>
																<?php if ($hnDeuda > 0): ?>
																	<strong>Debe $<?php echo number_format($hnDeuda, 2); ?></strong><?php if ($hnPartes): ?><span>(<?php echo $h(implode(' + ', $hnPartes)); ?>)</span><?php endif; ?>
																<?php else: ?>
																	<strong>Al día</strong>
																<?php endif; ?>
															</span>
														</span>
														<span class="badge <?php echo $hnActivo ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $hnActivo ? 'Activo' : 'Inactivo'; ?></span>
														<i class="fas fa-chevron-right ds-repre__alumno-ir" aria-hidden="true"></i>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								</div>
							</div>
							<?php endif; ?>

							<!-- Torneos: en los que participó y si pagó la inscripción -->
							<?php
								$torneos = $ficha['torneos'] ?? [];
								$TORNEOS_VISIBLES = 4;
								$hoyT = date('Y-m-d');
								/* Estado de la inscripción de cada torneo: [clase, icono, texto, detalle]. */
								$inscripcion = static function (array $t): array {
									$pagado = (float)$t['pagado'];
									$saldo  = (float)$t['saldo'];
									$valor  = (float)$t['valor_inscripcion'];
									$cuando = $t['ultimo_pago'] ? ' el ' . date('d/m/Y', strtotime($t['ultimo_pago'])) : '';
									if ($saldo > 0) {
										return ['mora', 'fa-triangle-exclamation', 'Saldo pendiente $' . number_format($saldo, 2), 'Abonó $' . number_format($pagado, 2) . $cuando];
									}
									if ((int)$t['pagos'] > 0 && ($valor <= 0 || $pagado >= $valor)) {
										return ['aldia', 'fa-circle-check', 'Inscripción pagada', '$' . number_format($pagado, 2) . $cuando];
									}
									if ((int)$t['pagos'] > 0) {
										return ['mora', 'fa-triangle-exclamation', 'Falta $' . number_format($valor - $pagado, 2), 'Pagó $' . number_format($pagado, 2) . ' de $' . number_format($valor, 2) . $cuando];
									}
									return $valor > 0
										? ['mora', 'fa-triangle-exclamation', 'Inscripción sin pagar', 'Debe $' . number_format($valor, 2)]
										: ['pendiente', 'fa-circle-question', 'Sin pago registrado', 'El equipo no tiene valor de inscripción'];
								};
								$estados = array_map($inscripcion, $torneos);
								$tPagados = count(array_filter($estados, static fn($e) => $e[0] === 'aldia'));
							?>
							<div class="col-12">
								<div class="card">
									<div class="card-header d-flex flex-wrap align-items-center gap-2">
										<h3 class="card-title me-auto"><i class="fas fa-trophy me-2" aria-hidden="true"></i>Torneos</h3>
										<?php if ($torneos): ?>
											<span class="small text-body-secondary"><?php echo count($torneos) . (count($torneos) === 1 ? ' torneo' : ' torneos') . ' · ' . $tPagados . ($tPagados === 1 ? ' pagado' : ' pagados'); ?></span>
										<?php endif; ?>
									</div>
									<div class="card-body">
										<?php if (!$torneos): ?>
											<p class="text-body-secondary mb-0">No ha participado en torneos.</p>
										<?php else: ?>
											<ul class="ds-torneos">
												<?php foreach ($torneos as $i => $t):
													[$tClase, $tIcono, $tTexto, $tDetalle] = $estados[$i];
													$tIni = $fecha($t['torneo_fechainicio']);
													$tFin = $fecha($t['torneo_fechafin']);
													/* Próximo, en curso o jugado, según sus fechas. */
													$tCuando = !$tIni ? '' : ($tIni->format('Y-m-d') > $hoyT ? 'Próximo'
														: (($tFin ? $tFin->format('Y-m-d') : $tIni->format('Y-m-d')) >= $hoyT ? 'En curso' : 'Jugado'));
													$tLugar = implode(', ', array_filter([trim(html_entity_decode((string)$t['torneo_ciudad'])), trim(html_entity_decode((string)$t['torneo_lugar']))],
														static fn($x) => $x !== ''));
													if (strcasecmp(trim((string)$t['torneo_ciudad']), trim((string)$t['torneo_lugar'])) === 0) { $tLugar = trim(html_entity_decode((string)$t['torneo_ciudad'])); }
													$tJuego = array_filter([trim((string)$t['posicion']), trim((string)$t['tipo'])]);
													/* Los 4 más recientes a la vista; el resto, plegado (en el
													   teléfono, 16 torneos empujaban el resto de la ficha). */
													if ($i === $TORNEOS_VISIBLES): ?>
													</ul>
													<details class="ds-torneos__mas">
														<summary>Ver <?php echo count($torneos) - $TORNEOS_VISIBLES === 1 ? 'el otro torneo' : 'los otros ' . (count($torneos) - $TORNEOS_VISIBLES) . ' torneos'; ?></summary>
														<ul class="ds-torneos">
													<?php endif; ?>
													<li class="ds-torneo">
														<div class="ds-torneo__cab">
															<strong><?php echo $h($t['torneo_nombre']); ?></strong>
															<?php if ($tCuando !== ''): ?>
																<span class="badge <?php echo ['Próximo' => 'text-bg-primary', 'En curso' => 'text-bg-info', 'Jugado' => 'text-bg-light border'][$tCuando]; ?>"><?php echo $tCuando; ?></span>
															<?php endif; ?>
														</div>
														<ul class="ds-torneo__meta">
															<?php if ($tIni): ?>
																<li><i class="fas fa-calendar-days" aria-hidden="true"></i><span><?php echo $tIni->format('d/m/Y') . ($tFin && $tFin != $tIni ? ' – ' . $tFin->format('d/m/Y') : ''); ?></span></li>
															<?php endif; ?>
															<?php if ($tLugar !== ''): ?>
																<li><i class="fas fa-location-dot" aria-hidden="true"></i><span><?php echo $h($tLugar); ?></span></li>
															<?php endif; ?>
															<li><i class="fas fa-people-group" aria-hidden="true"></i>
																<?php if ((int)$t['inscrito']): ?>
																	<span><?php echo $h(implode(' · ', array_filter([trim((string)$t['equipo']), trim((string)$t['categoria']) !== '' ? 'Cat. ' . trim((string)$t['categoria']) : '']))); ?>
																	<?php if ($tJuego): ?><span class="ds-torneo__juego"><?php echo $h(implode(' · ', $tJuego)); ?></span><?php endif; ?></span>
																<?php else: ?>
																	<span class="text-body-secondary">Sin equipo asignado</span>
																<?php endif; ?>
															</li>
														</ul>
														<div class="ds-torneo__pago ds-torneo__pago--<?php echo $tClase; ?>">
															<i class="fas <?php echo $tIcono; ?>" aria-hidden="true"></i>
															<strong><?php echo $h($tTexto); ?></strong>
															<span><?php echo $h($tDetalle); ?></span>
														</div>
													</li>
												<?php endforeach; ?>
											</ul>
											<?php if (count($torneos) > $TORNEOS_VISIBLES): ?></details><?php endif; ?>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<!-- Datos personales -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-user me-2" aria-hidden="true"></i>Datos personales</h3></div>
									<div class="card-body">
										<dl class="ds-perfil__datos ds-perfil__datos--cuatro">
											<div><dt>Tipo de identificación</dt><dd><?php echo $ver($al['tipo_identificacion']); ?></dd></div>
											<div><dt>Identificación</dt><dd><?php echo $ver($al['alumno_identificacion']); ?></dd></div>
											<div><dt>Nacionalidad</dt><dd><?php echo $ver($al['nacionalidad']); ?></dd></div>
											<div><dt>Fecha de nacimiento</dt><dd><?php echo $nacimiento ? $nacimiento->format('d/m/Y') : $vacio; ?></dd></div>
											<div><dt>Nombres</dt><dd><?php echo $ver(trim($al['alumno_primernombre'] . ' ' . $al['alumno_segundonombre'])); ?></dd></div>
											<div><dt>Apellidos</dt><dd><?php echo $ver(trim($al['alumno_apellidopaterno'] . ' ' . $al['alumno_apellidomaterno'])); ?></dd></div>
											<div><dt>Sexo</dt><dd><?php echo $al['alumno_genero'] === 'M' ? 'Masculino' : ($al['alumno_genero'] === 'F' ? 'Femenino' : $vacio); ?></dd></div>
											<div><dt>Hermanos en la escuela</dt><dd><?php echo $siNo($al['alumno_hermanos']) ?: $vacio; ?></dd></div>
											<div class="ds-perfil__ancho"><dt>Dirección</dt><dd><?php echo $ver($al['alumno_direccion']); ?></dd></div>
										</dl>
									</div>
								</div>
							</div>

							<!-- Cédula -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-id-card me-2" aria-hidden="true"></i>Cédula</h3></div>
									<div class="card-body">
										<?php
											$cedulas = [];
											foreach (['alumno_cedulaA' => 'Anverso', 'alumno_cedulaR' => 'Reverso'] as $campo => $lado) {
												$img = $al[$campo] != '' ? media_url('cedula', $al[$campo]) : '';
												$cedulas[$lado] = str_contains($img, '/dist/img/foto.jpg') ? '' : $img;
											}
										?>
										<?php if (implode('', $cedulas) === ''): ?>
											<p class="text-body-secondary mb-0">Sin cédula registrada.</p>
										<?php else: ?>
										<div class="row g-3">
											<?php foreach ($cedulas as $lado => $img): ?>
												<div class="col-12 col-sm-6">
													<figure class="ds-perfil__cedula mb-0">
														<?php if ($img !== ''): ?>
															<a href="<?php echo $h($img); ?>" target="_blank" rel="noopener" title="Abrir en tamaño completo">
																<img src="<?php echo $h($img); ?>" alt="Cédula, <?php echo mb_strtolower($lado); ?>" loading="lazy">
															</a>
														<?php else: ?>
															<div class="ds-perfil__cedula-vacia"><i class="fas fa-image me-2" aria-hidden="true"></i>Sin registrar</div>
														<?php endif; ?>
														<figcaption><?php echo $lado; ?></figcaption>
													</figure>
												</div>
											<?php endforeach; ?>
										</div>
										<?php endif; ?>
									</div>
								</div>
							</div>

						</div>
					</div>

				</div>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<?php if ($inscribir): ?>
	<!-- =================== Inscribir en la sede con código (096) =================== -->
	<div class="modal fade" id="modalInscribirSede" tabindex="-1" aria-labelledby="modalInscribirSedeTitulo" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
			<form class="modal-content FormularioAjax" id="formInscribirSede" action="<?php echo APP_URL; ?>app/ajax/alumnoAjax.php" method="POST" autocomplete="off" novalidate>
				<input type="hidden" name="modulo_alumno" value="registrarSedeCodigo">
				<input type="hidden" name="alumno_id" value="<?php echo (int)$alumnoid; ?>">
				<div class="modal-header">
					<h5 class="modal-title" id="modalInscribirSedeTitulo"><i class="fas fa-dumbbell me-2" aria-hidden="true"></i>Inscribir en <?php echo $h($inscribir['sede_nombre']); ?></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="ds-insc__codigo">
						<span>Código que se le asignará</span>
						<strong id="inscCodigo"><?php echo $h($inscribir['codigo']); ?></strong>
						<small>Va como identificación en <?php echo $h($inscribir['sede_nombre']); ?>. Se confirma al guardar.</small>
					</div>

					<dl class="ds-insc__resumen">
						<div><dt>Alumno</dt><dd><?php echo $h($nombreCompleto); ?> <span class="text-body-secondary">· <?php echo $h($al['alumno_identificacion']); ?></span></dd></div>
						<div><dt>Representante</dt><dd><?php echo $nombreRepre !== '' ? $h($nombreRepre) . ' <span class="text-body-secondary">(el mismo)</span>' : $vacio; ?></dd></div>
						<div><dt>Sede</dt><dd><?php echo $h($inscribir['sede_nombre']); ?></dd></div>
						<div><dt>Pensión</dt><dd>$<?php echo number_format((float)$inscribir['pension'], 2); ?> al mes · inscripción $<?php echo number_format((float)$inscribir['inscripcion'], 2); ?></dd></div>
					</dl>
					<p class="small text-body-secondary mb-3">
						<i class="fas fa-copy me-1" aria-hidden="true"></i>Se copian sus datos personales, la foto, la información médica y el contacto de emergencia.
						Su registro en <?php echo $h($al['sede_nombre']); ?> no cambia: cada sede lleva su propia pensión.
					</p>

					<div class="row g-3">
						<div class="col-12 col-sm-6">
							<label for="inscFecha" class="form-label">Fecha de ingreso <span class="text-danger">*</span></label>
							<input type="date" class="form-control" id="inscFecha" name="alumno_fechaingreso" value="<?php echo date('Y-m-d'); ?>" required>
							<div class="invalid-feedback">Elija la fecha de ingreso.</div>
						</div>
						<div class="col-12 col-sm-6">
							<label for="inscHorario" class="form-label">Horario</label>
							<select class="form-select" id="inscHorario" name="horarioid">
								<option value="">Sin horario por ahora</option>
								<?php foreach ($inscribir['horarios'] as $hid => $htexto): ?>
									<option value="<?php echo (int)$hid; ?>"><?php echo $h($htexto); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>
				<div class="modal-footer ds-insc__pie">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Inscribir</button>
				</div>
			</form>
		</div>
	</div>
	<?php endif; ?>

	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<?php /* Esta pantalla no envía formularios, pero la barra superior sí:
	         «Cambiar contraseña» va por ajax.js y avisa con SweetAlert. */ ?>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script>
	/* «Regresar»: si se llegó desde otra pantalla del sistema, se vuelve a
	   ella (con su búsqueda y su página); si no, el enlace lleva al listado
	   de alumnos. Ctrl+clic o clic central siguen abriendo el listado. */
	(function () {
		var b = document.getElementById('btnRegresar');
		var desdeAqui = false;
		try { desdeAqui = !!document.referrer && new URL(document.referrer).origin === location.origin && document.referrer !== location.href; } catch (e) {}
		if (!b || !desdeAqui || history.length < 2) { return; }
		b.addEventListener('click', function (e) {
			if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
			e.preventDefault();
			history.back();
		});
	})();

	/* Inscribir en la sede con código: si falta la fecha, se marca y no se
	   envía (antes de la confirmación de ajax.js, que escucha el mismo submit). */
	(function () {
		var form = document.getElementById('formInscribirSede');
		if (!form) { return; }
		form.addEventListener('submit', function (e) {
			if (!form.checkValidity()) {
				e.preventDefault(); e.stopImmediatePropagation();
				form.classList.add('was-validated');
				var malo = form.querySelector(':invalid'); if (malo) { malo.focus(); }
			}
		}, true);
	})();
	</script>

	<style>
		/* La misma persona en otra sede (096) */
		.ds-perfil__vinculos { list-style: none; padding: 0; margin: 0 0 1rem; display: grid; gap: .4rem; }
		.ds-perfil__vinculo { display: flex; align-items: center; gap: .6rem; padding: .55rem .75rem; border-radius: var(--ds-radius-sm, 10px);
		                      background: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis); border: 1px solid var(--bs-info-border-subtle);
		                      text-decoration: none; line-height: 1.25; }
		.ds-perfil__vinculo:hover { filter: brightness(.97); }
		/* El botón, en su propia fila: en la tarjeta angosta el nombre de la sede se cortaba. */
		.ds-perfil__acciones .ds-perfil__inscribir { flex: 1 1 100%; }
		/* Modal de inscripción */
		.ds-insc__codigo { text-align: center; padding: .9rem; margin-bottom: 1rem; border-radius: var(--ds-radius-sm, 10px);
		                   background: var(--bs-tertiary-bg); border: 1px dashed var(--bs-border-color); }
		.ds-insc__codigo span { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary-color); font-weight: 600; }
		.ds-insc__codigo strong { display: block; font-size: 1.8rem; letter-spacing: .08em; font-variant-numeric: tabular-nums; }
		.ds-insc__codigo small { color: var(--bs-secondary-color); }
		.ds-insc__resumen { display: grid; grid-template-columns: 8.5rem 1fr; gap: .35rem .75rem; margin-bottom: .75rem; }
		.ds-insc__resumen > div { display: contents; }
		.ds-insc__resumen dt { color: var(--bs-secondary-color); font-weight: 600; }
		.ds-insc__resumen dd { margin: 0; overflow-wrap: anywhere; }
		@media (max-width: 575.98px) {
			.ds-insc__resumen { grid-template-columns: 1fr; gap: 0; }
			.ds-insc__resumen dd { margin-bottom: .5rem; }
			.ds-insc__pie { flex-direction: column-reverse; align-items: stretch; }
			.ds-insc__pie .btn { width: 100%; margin: 0; min-height: 2.9rem; }
		}
	</style>
  </body>
</html>
