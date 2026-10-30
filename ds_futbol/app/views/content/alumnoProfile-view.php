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
	$pendientes = (int)$al['pagos_pendientes'];

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
											<span class="badge <?php echo $pendientes ? 'text-bg-danger' : 'text-bg-success'; ?>">
												<?php echo $pendientes ? $pendientes . ($pendientes === 1 ? ' pago pendiente' : ' pagos pendientes') : 'Pagos al día'; ?>
											</span>
										</div>
									</div>
								</div>

								<dl class="ds-perfil__datos">
									<div><dt>Sede</dt><dd><?php echo $ver($al['sede_nombre']); ?></dd></div>
									<div><dt>Categoría</dt><dd><?php echo $nacimiento ? $nacimiento->format('Y') : $vacio; ?></dd></div>
									<div><dt>Edad</dt><dd><?php echo $nacimiento ? $h($tiempo($nacimiento)) : $vacio; ?></dd></div>
									<div><dt>Camiseta</dt><dd><?php echo (int)$al['alumno_numcamiseta'] > 0 ? $h($al['alumno_numcamiseta']) : $vacio; ?></dd></div>
									<div><dt>Ingreso</dt><dd><?php echo $ingreso ? $ingreso->format('d/m/Y') : $vacio; ?></dd></div>
									<div><dt>En la escuela</dt><dd><?php echo $ingreso && $tiempo($ingreso) !== '' ? $h($tiempo($ingreso)) : $vacio; ?></dd></div>
								</dl>

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
	</script>
  </body>
</html>
