<?php
	use app\controllers\representanteController;
	$insRepre = new representanteController();

	$repreid = ds_id_de_url($url, 1, APP_URL . 'representanteList/');

	$ficha = $insRepre->fichaRepresentante($repreid);
	if ($ficha === null) {
		/* No existe: al listado. Antes se pintaba el aviso de error dentro
		   de una página a medio montar. */
		header("Location: " . APP_URL . "representanteList/");
		exit();
	}
	$r   = $ficha['representante'];
	$c   = $ficha['conyuge'];
	$hijos = $ficha['alumnos'];

	/* Los textos llegan ya sin escapar (fichaRepresentante): aquí se escapan una vez. */
	$h     = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
	$vacio = '<span class="text-body-secondary">—</span>';
	$ver   = static fn($v) => trim((string)$v) !== '' ? $h($v) : $vacio;
	$junta = static fn(...$partes) => preg_replace('/\s+/', ' ', trim(implode(' ', $partes)));

	$nombreRepre = $junta($r['repre_primernombre'], $r['repre_segundonombre'], $r['repre_apellidopaterno'], $r['repre_apellidomaterno']);
	$corto       = $junta($r['repre_primernombre'], $r['repre_apellidopaterno']);
	$iniciales   = mb_strtoupper(mb_substr($r['repre_primernombre'], 0, 1) . mb_substr($r['repre_apellidopaterno'], 0, 1));
	$firmado     = $r['repre_firmado'] === 'S';
	$activos     = count(array_filter($hijos, fn($a) => $a['alumno_estado'] === 'A'));

	/* Autorizaciones LOPDP de sus alumnos (Consentimientos LOPDP): el resumen
	   y las de cada uno. «Completas»: datos autorizados y respuesta sobre la
	   imagen (un «no autorizo» también es respuesta). */
	$auts     = $insRepre->autorizacionesFicha((int)$repreid);
	$resumen  = $auts['resumen'];
	$completa = $resumen && $resumen['DATOS']['estado'] === 'S' && $resumen['IMAGEN']['estado'] !== null;
	$NOMBRES_ORIGEN = ['PAPEL' => 'el formulario firmado en papel', 'ENLACE' => 'el enlace de autorización', 'FORMULARIO' => 'el formulario de inscripción en línea', 'ADMIN' => 'registro en el sistema'];
	$origenes = $resumen ? array_values(array_unique(array_merge($resumen['DATOS']['origenes'], $resumen['IMAGEN']['origenes']))) : [];
	$enLinea  = array_values(array_intersect(['PAPEL', 'ENLACE', 'FORMULARIO'], $origenes));
	$lista    = static fn(array $p) => count($p) > 1 ? implode(', ', array_slice($p, 0, -1)) . ' y ' . end($p) : ($p[0] ?? '');

	$edad = static function ($fecha): string {
		if (!$fecha || str_starts_with((string)$fecha, '0000')) { return ''; }
		try { $a = (new DateTime($fecha))->diff(new DateTime('today'))->y; } catch (Exception $e) { return ''; }
		return $a . ($a === 1 ? ' año' : ' años');
	};

	/* «Representa a»: ingreso, beneficio, último pago y última asistencia. */
	$fechaOk = static fn($f) => ($f && !str_starts_with((string)$f, '0000')) ? substr((string)$f, 0, 10) : '';
	$antiguedad = static function (string $f): string {
		$d = (new DateTime($f))->diff(new DateTime('today'));
		if ($d->invert) { return 'ingresa más adelante'; }
		if ($d->y > 0)  { return 'hace ' . $d->y . ($d->y === 1 ? ' año' : ' años') . ($d->m > 0 ? ' y ' . $d->m . ($d->m === 1 ? ' mes' : ' meses') : ''); }
		if ($d->m > 0)  { return 'hace ' . $d->m . ($d->m === 1 ? ' mes' : ' meses'); }
		return 'este mes';
	};
	/* descuento_valor es la pensión que PAGA con el beneficio (como lo cobra pagosNew). */
	$beneficio = static function (array $a) use ($h): string {
		$cod = $a['descuento_rubroid'] ?? '';
		if ($cod === '') { return '<span class="text-body-secondary">No tiene</span>'; }
		$sede  = (float)$a['sede_pension'];
		$paga  = $cod === 'DBC' ? 0.0 : min(max((float)$a['descuento_valor'], 0), $sede);
		$nombre = ['DBC' => 'Beca 100 %', 'DBP' => 'Beca 50 %'][$cod] ?? (trim($a['descuento_nombre']) !== '' ? $a['descuento_nombre'] : $cod);
		$detalle = $cod === 'DBC' ? 'no paga pensión' : 'paga $' . number_format($paga, 2) . ($sede > 0 ? ' de $' . number_format($sede, 2) : '');
		return '<span class="badge ds-repre__beneficio">' . $h($nombre) . '</span><small>' . $h($detalle) . '</small>';
	};

	$puedePagos  = function_exists('puede') ? puede('ver', 'pagosList') : true;
	$puedeEditar = function_exists('puede') ? puede('editar', 'representanteList') : true;
	$puedeCrear  = function_exists('puede') ? puede('crear', 'representanteList') : true;

	/* La cabecera es común a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Representante ' . $h($corto);
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
						<h4 class="m-0">Ficha del representante</h4>
						<p class="small text-body-secondary mb-0"><?php echo $h($corto); ?></p>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>representanteList/">Representantes</a></li>
							<li class="breadcrumb-item active" aria-current="page">Ficha</li>
						</ol>
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
									<span class="ds-perfil__foto ds-perfil__foto--iniciales ds-repre__avatar" aria-hidden="true"><?php echo $h($iniciales); ?></span>
									<div class="ds-perfil__nombre">
										<strong><?php echo $h($nombreRepre); ?></strong>
										<span class="text-body-secondary"><?php echo $h($r['repre_identificacion']); ?></span>
										<div class="ds-perfil__insignias">
											<?php if (trim($r['parentesco']) !== ''): ?><span class="badge text-bg-secondary"><?php echo $h($r['parentesco']); ?></span><?php endif; ?>
											<?php if ($r['repre_factura'] === 'S'): ?><span class="badge text-bg-info">Factura</span><?php endif; ?>
										</div>
									</div>
								</div>

								<?php /* Las autorizaciones LOPDP de sus alumnos, a la vista: es lo
								         que hay que pedirle si falta. Salen de Consentimientos LOPDP
								         (por el enlace, el formulario de inscripción o el sistema);
								         el formulario en papel va aparte, en los botones. */ ?>
								<div class="ds-repre__lopdp <?php echo !$resumen ? 'ds-repre__lopdp--neutro' : ($completa ? 'ds-repre__lopdp--ok' : 'ds-repre__lopdp--pendiente'); ?>" id="autorizacionesLopdp">
									<div class="ds-repre__lopdp-titulo">
										<i class="fas <?php echo $completa ? 'fa-shield-halved' : 'fa-hourglass-half'; ?>" aria-hidden="true"></i>
										<strong>Autorizaciones LOPDP</strong>
										<span class="ms-auto"><?php echo !$resumen ? 'sin alumnos' : ($completa ? 'completas' : 'pendientes'); ?></span>
									</div>
									<?php if ($resumen): ?>
										<?php if ($completa): ?>
											<p class="ds-repre__lopdp-texto">
												Cuenta con la autorización de los <strong>Consentimientos LOPDP</strong>
												<?php echo $origenes ? 'por ' . $h($lista(array_map(fn($o) => $NOMBRES_ORIGEN[$o] ?? $o, $origenes))) : ''; ?>.
											</p>
										<?php elseif ($enLinea): ?>
											<p class="ds-repre__lopdp-texto">
												Ya respondió en parte por <?php echo $h($lista(array_map(fn($o) => $NOMBRES_ORIGEN[$o], $enLinea))); ?>; falta lo que se indica.
											</p>
										<?php endif; ?>
										<div class="ds-repres__auts mb-0">
											<?php echo app\controllers\representanteController::chipAutorizacion('Datos', $resumen['DATOS']); ?>
											<?php echo app\controllers\representanteController::chipAutorizacion('Imagen', $resumen['IMAGEN']); ?>
										</div>
										<?php if (!$completa && function_exists('puede') && puede('crear', 'consentimientoList')): ?>
											<a href="<?php echo APP_URL; ?>consentimientoList/?autorizacion=P" class="ds-repre__lopdp-enlace">
												<i class="fas fa-paper-plane me-1" aria-hidden="true"></i>Enviar la autorización
											</a>
										<?php endif; ?>
									<?php endif; ?>
									<?php if ($firmado): ?>
										<p class="ds-repre__lopdp-texto mb-0"><i class="fas fa-file-signature me-1" aria-hidden="true"></i>Formulario en papel firmado.</p>
									<?php endif; ?>
								</div>

								<div class="ds-perfil__acciones">
									<?php if ($puedeEditar): ?>
										<a href="<?php echo APP_URL; ?>representanteUpdate/<?php echo (int)$repreid; ?>/" class="btn btn-sm btn-primary"><i class="fas fa-pen me-1" aria-hidden="true"></i>Editar</a>
									<?php endif; ?>
									<?php if ($puedeCrear): ?>
										<a href="<?php echo APP_URL; ?>alumnoNew/<?php echo (int)$repreid; ?>/" class="btn btn-sm btn-secondary"><i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo alumno</a>
										<a href="<?php echo APP_URL; ?>representanteVinc/<?php echo (int)$repreid; ?>/" class="btn btn-sm btn-secondary"><i class="fas fa-link me-1" aria-hidden="true"></i>Vincular alumno</a>
									<?php endif; ?>
									<a href="<?php echo APP_URL; ?>representanteFLPD/<?php echo (int)$repreid; ?>/" target="_blank" rel="noopener" class="btn btn-sm btn-light border"
									   title="Formulario de protección de datos en papel<?php echo $firmado ? ' (firmado)' : ''; ?>">
										<i class="fas fa-file-signature me-1" aria-hidden="true"></i>Formulario LPD
									</a>
								</div>
							</div>
						</div>
					</div>

					<!-- ============ El resto ============ -->
					<div class="col-12 col-lg-8 col-xl-9">
						<div class="row g-3">

							<!-- Representados: lo que más se viene a mirar -->
							<div class="col-12">
								<div class="card">
									<div class="card-header d-flex align-items-center gap-2">
										<h3 class="card-title me-auto"><i class="fas fa-children me-2" aria-hidden="true"></i>Representa a</h3>
										<?php if ($hijos): ?>
											<span class="small text-body-secondary"><?php echo count($hijos) . (count($hijos) === 1 ? ' alumno' : ' alumnos') . ($activos < count($hijos) ? ' · ' . $activos . ($activos === 1 ? ' activo' : ' activos') : ''); ?></span>
										<?php endif; ?>
									</div>
									<div class="card-body">
										<?php if (!$hijos): ?>
											<p class="text-body-secondary mb-2">Todavía no tiene alumnos.</p>
											<?php if ($puedeCrear): ?>
												<a href="<?php echo APP_URL; ?>alumnoNew/<?php echo (int)$repreid; ?>/" class="btn btn-sm btn-success"><i class="fas fa-plus me-1" aria-hidden="true"></i>Registrar su primer alumno</a>
											<?php endif; ?>
										<?php else: ?>
											<ul class="ds-repre__alumnos">
												<?php foreach ($hijos as $a):
													$nombreA = $junta($a['alumno_primernombre'], $a['alumno_segundonombre'], $a['alumno_apellidopaterno'], $a['alumno_apellidomaterno']);
													$foto = $a['alumno_imagen'] !== '' ? media_url('alumno', $a['alumno_imagen']) : '';
													if (str_contains($foto, '/dist/img/foto.jpg')) { $foto = ''; }
													$inic = mb_strtoupper(mb_substr($a['alumno_primernombre'], 0, 1) . mb_substr($a['alumno_apellidopaterno'], 0, 1));
													$activo = $a['alumno_estado'] === 'A';
													$anio = $a['alumno_fechanacimiento'] && !str_starts_with($a['alumno_fechanacimiento'], '0000') ? substr($a['alumno_fechanacimiento'], 0, 4) : '';
													$meta = array_filter([$edad($a['alumno_fechanacimiento']), $anio !== '' ? 'Cat. ' . $anio : '', $a['sede_nombre']]);
													$ingreso = $fechaOk($a['alumno_fechaingreso']);
													$asis    = $fechaOk($a['ultima_asis']);
													$diasAsis = $asis !== '' ? (int)(new DateTime($asis))->diff(new DateTime('today'))->days : null;
													$penFecha = $fechaOk($a['pension_fecha']);
													$pagoFecha = $fechaOk($a['pago_fecha']);
													/* Un pago de otro concepto posterior al de pensión: también se muestra. */
													$otroPago = $pagoFecha !== '' && $a['pago_rubroid'] !== 'RPE' && $pagoFecha >= $penFecha; ?>
													<li class="ds-repre__ficha<?php echo $activo ? '' : ' ds-repre__ficha--inactivo'; ?>">
														<a class="ds-repre__alumno<?php echo $activo ? '' : ' ds-repre__alumno--inactivo'; ?>" href="<?php echo APP_URL; ?>alumnoProfile/<?php echo (int)$a['alumno_id']; ?>/">
															<?php if ($foto !== ''): ?>
																<img class="ds-repre__alumno-foto" src="<?php echo $h($foto); ?>" alt="" loading="lazy">
															<?php else: ?>
																<span class="ds-repre__alumno-foto" aria-hidden="true"><?php echo $h($inic); ?></span>
															<?php endif; ?>
															<span class="ds-repre__alumno-datos">
																<strong><?php echo $h($nombreA); ?></strong>
																<span><?php echo $h(implode(' · ', $meta)); ?></span>
																<?php if ($autA = $auts['alumnos'][(int)$a['alumno_id']] ?? null): ?>
																	<span class="ds-repre__alumno-aut">
																		<?php echo app\controllers\representanteController::chipAutorizacion('Datos', $autA['DATOS']); ?>
																		<?php echo app\controllers\representanteController::chipAutorizacion('Imagen', $autA['IMAGEN']); ?>
																	</span>
																<?php endif; ?>
															</span>
															<span class="badge <?php echo $activo ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $activo ? 'Activo' : 'Inactivo'; ?></span>
															<i class="fas fa-chevron-right ds-repre__alumno-ir" aria-hidden="true"></i>
														</a>
														<dl class="ds-repre__resumen">
															<div>
																<dt><i class="fas fa-right-to-bracket" aria-hidden="true"></i>Ingreso</dt>
																<dd>
																	<?php if ($ingreso !== ''): ?>
																		<?php echo date('d/m/Y', strtotime($ingreso)); ?>
																		<?php if ($ant = $antiguedad($ingreso)): ?><small><?php echo $h($ant); ?></small><?php endif; ?>
																	<?php else: ?>
																		<span class="text-body-secondary">Sin fecha</span>
																	<?php endif; ?>
																</dd>
															</div>
															<div>
																<dt><i class="fas fa-award" aria-hidden="true"></i>Beca o descuento</dt>
																<dd><?php echo $beneficio($a); ?></dd>
															</div>
															<div>
																<dt><i class="fas fa-money-bill-wave" aria-hidden="true"></i>Último pago de pensión</dt>
																<dd>
																	<?php if ($penFecha !== ''): ?>
																		<?php echo date('d/m/Y', strtotime($penFecha)); ?>
																		<small><?php echo $h(implode(' · ', array_filter([trim($a['pension_periodo']), '$' . number_format((float)$a['pension_valor'], 2)]))); ?></small>
																	<?php else: ?>
																		<span class="text-body-secondary">Sin pagos de pensión</span>
																	<?php endif; ?>
																	<?php if ($otroPago): ?>
																		<small class="ds-repre__otro-pago">Después: <?php echo $h(trim($a['pago_concepto']) !== '' ? $a['pago_concepto'] : 'otro concepto'); ?> $<?php echo number_format((float)$a['pago_valor'], 2); ?> el <?php echo date('d/m/Y', strtotime($pagoFecha)); ?></small>
																	<?php endif; ?>
																</dd>
															</div>
															<div>
																<dt><i class="fas fa-clipboard-check" aria-hidden="true"></i>Última asistencia</dt>
																<dd>
																	<?php if ($asis !== ''): ?>
																		<?php echo date('d/m/Y', strtotime($asis)); ?>
																		<small class="<?php echo $diasAsis > 30 ? 'text-danger' : ''; ?>"><?php echo $diasAsis === 0 ? 'hoy' : ($diasAsis === 1 ? 'ayer' : ($diasAsis <= 60 ? 'hace ' . $diasAsis . ' días' : $antiguedad($asis))); ?></small>
																	<?php else: ?>
																		<span class="text-body-secondary">Sin asistencias</span>
																	<?php endif; ?>
																</dd>
															</div>
														</dl>
														<?php if ($puedePagos): ?>
															<div class="ds-repre__ficha-acciones">
																<a class="btn btn-sm btn-outline-secondary" href="<?php echo APP_URL; ?>pagosNew/<?php echo (int)$a['alumno_id']; ?>/"
																   title="Pagos de <?php echo $h($nombreA); ?>"><i class="fas fa-list me-1" aria-hidden="true"></i>Ver pagos</a>
															</div>
														<?php endif; ?>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<!-- Contacto -->
							<div class="col-12 col-xl-6">
								<div class="card h-100">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-address-book me-2" aria-hidden="true"></i>Contacto</h3></div>
									<div class="card-body">
										<dl class="ds-perfil__datos">
											<div><dt>Celular</dt><dd><?php echo $ver($r['repre_celular']); ?></dd></div>
											<div><dt>Sexo</dt><dd><?php echo $r['repre_sexo'] === 'M' ? 'Masculino' : ($r['repre_sexo'] === 'F' ? 'Femenino' : $vacio); ?></dd></div>
											<div class="ds-perfil__ancho"><dt>Correo</dt><dd>
												<?php echo trim($r['repre_correo']) !== '' ? '<a href="mailto:' . $h($r['repre_correo']) . '">' . $h($r['repre_correo']) . '</a>' : $vacio; ?>
											</dd></div>
											<div class="ds-perfil__ancho"><dt>Dirección</dt><dd><?php echo $ver($r['repre_direccion']); ?></dd></div>
											<div><dt>Tipo de identificación</dt><dd><?php echo $ver($r['tipo_identificacion']); ?></dd></div>
											<div><dt>Requiere factura</dt><dd><?php echo $r['repre_factura'] === 'S' ? 'Sí' : ($r['repre_factura'] === 'N' ? 'No' : $vacio); ?></dd></div>
										</dl>
										<?php echo ds_botones_contacto($r['repre_celular']); ?>
									</div>
								</div>
							</div>

							<!-- Cónyuge -->
							<div class="col-12 col-xl-6">
								<div class="card h-100">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-user-group me-2" aria-hidden="true"></i>Cónyuge</h3></div>
									<div class="card-body">
										<?php if (!$c || $junta($c['conyuge_primernombre'], $c['conyuge_apellidopaterno']) === ''): ?>
											<p class="text-body-secondary mb-0">Sin cónyuge registrado.</p>
										<?php else: ?>
											<p class="ds-perfil__persona">
												<strong><?php echo $h($junta($c['conyuge_primernombre'], $c['conyuge_segundonombre'], $c['conyuge_apellidopaterno'], $c['conyuge_apellidomaterno'])); ?></strong>
												<?php if (trim($c['conyuge_identificacion']) !== ''): ?><span class="text-body-secondary"><?php echo $h($c['conyuge_identificacion']); ?></span><?php endif; ?>
											</p>
											<dl class="ds-perfil__datos">
												<div><dt>Celular</dt><dd><?php echo $ver($c['conyuge_celular']); ?></dd></div>
												<div><dt>Sexo</dt><dd><?php echo $c['conyuge_sexo'] === 'M' ? 'Masculino' : ($c['conyuge_sexo'] === 'F' ? 'Femenino' : $vacio); ?></dd></div>
												<div class="ds-perfil__ancho"><dt>Correo</dt><dd>
													<?php echo trim($c['conyuge_correo']) !== '' ? '<a href="mailto:' . $h($c['conyuge_correo']) . '">' . $h($c['conyuge_correo']) . '</a>' : $vacio; ?>
												</dd></div>
												<div class="ds-perfil__ancho"><dt>Dirección</dt><dd><?php echo $ver($c['conyuge_direccion']); ?></dd></div>
											</dl>
											<?php echo ds_botones_contacto($c['conyuge_celular']); ?>
										<?php endif; ?>
									</div>
								</div>
							</div>

						</div>
					</div>

				</div>

				<?php /* Al final, de vuelta al listado (no «atrás»: se puede haber
				         llegado desde un alumno, un pago o un enlace). */ ?>
				<div class="ds-perfil__pie">
					<a href="<?php echo APP_URL; ?>representanteList/" class="btn btn-secondary" id="btnRegresar">
						<i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Regresar
					</a>
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
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
  </body>
</html>
