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

	$edad = static function ($fecha): string {
		if (!$fecha || str_starts_with((string)$fecha, '0000')) { return ''; }
		try { $a = (new DateTime($fecha))->diff(new DateTime('today'))->y; } catch (Exception $e) { return ''; }
		return $a . ($a === 1 ? ' año' : ' años');
	};

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

								<?php /* La autorización de datos de los menores (LOPDP): a la
								         vista, porque es lo que hay que pedirle si falta. */ ?>
								<div class="ds-repre__lopdp <?php echo $firmado ? 'ds-repre__lopdp--ok' : 'ds-repre__lopdp--pendiente'; ?>">
									<i class="fas <?php echo $firmado ? 'fa-circle-check' : 'fa-file-signature'; ?>" aria-hidden="true"></i>
									<span><strong>Protección de datos:</strong> <?php echo $firmado ? 'formulario firmado' : 'pendiente de firma'; ?></span>
									<a href="<?php echo APP_URL; ?>representanteFLPD/<?php echo (int)$repreid; ?>/" target="_blank" rel="noopener" class="ds-repre__lopdp-enlace">Formulario</a>
								</div>

								<div class="ds-perfil__acciones">
									<?php if ($puedeEditar): ?>
										<a href="<?php echo APP_URL; ?>representanteUpdate/<?php echo (int)$repreid; ?>/" class="btn btn-sm btn-primary"><i class="fas fa-pen me-1" aria-hidden="true"></i>Editar</a>
									<?php endif; ?>
									<?php if ($puedeCrear): ?>
										<a href="<?php echo APP_URL; ?>alumnoNew/<?php echo (int)$repreid; ?>/" class="btn btn-sm btn-secondary"><i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo alumno</a>
										<a href="<?php echo APP_URL; ?>representanteVinc/<?php echo (int)$repreid; ?>/" class="btn btn-sm btn-secondary"><i class="fas fa-link me-1" aria-hidden="true"></i>Vincular alumno</a>
									<?php endif; ?>
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
													$meta = array_filter([$edad($a['alumno_fechanacimiento']), $anio !== '' ? 'Cat. ' . $anio : '', $a['sede_nombre']]); ?>
													<li>
														<a class="ds-repre__alumno<?php echo $activo ? '' : ' ds-repre__alumno--inactivo'; ?>" href="<?php echo APP_URL; ?>alumnoProfile/<?php echo (int)$a['alumno_id']; ?>/">
															<?php if ($foto !== ''): ?>
																<img class="ds-repre__alumno-foto" src="<?php echo $h($foto); ?>" alt="" loading="lazy">
															<?php else: ?>
																<span class="ds-repre__alumno-foto" aria-hidden="true"><?php echo $h($inic); ?></span>
															<?php endif; ?>
															<span class="ds-repre__alumno-datos">
																<strong><?php echo $h($nombreA); ?></strong>
																<span><?php echo $h(implode(' · ', $meta)); ?></span>
															</span>
															<span class="badge <?php echo $activo ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $activo ? 'Activo' : 'Inactivo'; ?></span>
															<i class="fas fa-chevron-right ds-repre__alumno-ir" aria-hidden="true"></i>
														</a>
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
