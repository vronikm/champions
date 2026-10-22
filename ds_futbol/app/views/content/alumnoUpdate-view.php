<?php
	use app\controllers\alumnoController;
	$insAlumno = new alumnoController();

	$alumnoid = ds_id_de_url($url, 1, APP_URL . 'alumnoList/');

	/* Los mismos datos que la ficha (fichaAlumno): el alumno con sus
	   catálogos, la información médica, el contacto de emergencia y los
	   horarios asignados. */
	$ficha = $insAlumno->fichaAlumno($alumnoid);
	if ($ficha === null) {
		header("Location: " . APP_URL . "alumnoList/");
		exit();
	}
	$al    = $ficha['alumno'];
	$med   = $ficha['medica'] ?: [];
	$cemer = $ficha['emergencia'] ?: [];

	/* Valores de los campos: los textos se guardan escapados (limpiarHtml);
	   se deshace y se escapa una sola vez. Antes iban sin escapar dentro de
	   value="…" y una comilla en la dirección cortaba el campo. */
	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	/* $medico y no $m: main-sidebar.php recorre el menú con
	   foreach ($GenerarMenu as $m) y la pisaba. */
	$medico = static fn(string $campo) => $h($med[$campo] ?? '');
	$marcado = static fn($actual, $valor) => (string)$actual === $valor ? 'checked' : '';

	$nombreAlumno = trim($al['alumno_primernombre'] . ' ' . $al['alumno_apellidopaterno']);

	$foto = $al['alumno_imagen'] != '' ? media_url('alumno', $al['alumno_imagen']) : '';
	if (str_contains($foto, '/dist/img/foto.jpg')) { $foto = ''; }
	$cedulas = [];
	foreach (['alumno_cedulaA' => 'Anverso', 'alumno_cedulaR' => 'Reverso'] as $campo => $lado) {
		$img = $al[$campo] != '' ? media_url('cedula', $al[$campo]) : '';
		$cedulas[$campo] = ['lado' => $lado, 'img' => str_contains($img, '/dist/img/foto.jpg') ? '' : $img];
	}

	/* El horario: el primero que tenga asignado. Si tiene más de uno se
	   avisa, porque al guardar queda sólo el elegido. */
	$horarios  = $ficha['horarios'];
	$horarioid = $horarios ? $horarios[0]['id'] : 0;
	$DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

	/* La cabecera es común a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Editar a ' . $h($nombreAlumno);
	$extras      = ['select2', 'swal'];
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
						<h4 class="m-0">Editar alumno</h4>
						<p class="small text-body-secondary mb-0"><?php echo $h($nombreAlumno); ?></p>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>alumnoList/">Alumnos</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>alumnoProfile/<?php echo (int)$alumnoid; ?>/">Ficha</a></li>
							<li class="breadcrumb-item active" aria-current="page">Editar</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
			<form id="formAlumno" class="FormularioAjax ds-editar" action="<?php echo APP_URL; ?>app/ajax/alumnoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_alumno" value="actualizar">
				<input type="hidden" name="alumno_id" value="<?php echo (int)$alumnoid; ?>">

				<div class="row g-3">

					<!-- ============ Foto ============ -->
					<div class="col-12 col-lg-4 col-xl-3">
						<div class="card ds-editar__lateral">
							<div class="card-header"><h3 class="card-title"><i class="fas fa-camera me-2" aria-hidden="true"></i>Foto</h3></div>
							<div class="card-body">
								<div class="fileinput fileinput-new ds-crud__foto ds-editar__foto" data-provides="fileinput">
									<div class="fileinput-new thumbnail" data-trigger="fileinput">
										<?php if ($foto !== ''): ?>
											<img src="<?php echo $h($foto); ?>" alt="Foto actual de <?php echo $h($nombreAlumno); ?>">
										<?php else: ?>
											<span class="ds-editar__sin-imagen"><i class="fas fa-user" aria-hidden="true"></i>Sin foto</span>
										<?php endif; ?>
									</div>
									<div class="fileinput-preview fileinput-exists thumbnail"></div>
									<div class="ds-crud__foto-botones">
										<span class="bton bton-white bton-file">
											<span class="fileinput-new"><i class="fas fa-upload me-1" aria-hidden="true"></i><?php echo $foto !== '' ? 'Cambiar foto' : 'Subir foto'; ?></span>
											<span class="fileinput-exists">Elegir otra</span>
											<input type="file" name="alumno_foto" id="alumno_foto" accept="image/jpeg,image/png" aria-label="Foto del alumno">
										</span>
										<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Quitar</a>
									</div>
								</div>
								<p class="form-text mb-0">JPG o PNG, hasta 4 MB. La foto nueva se guarda al pulsar «Guardar cambios».</p>
							</div>
						</div>
					</div>

					<!-- ============ Los datos, por secciones ============ -->
					<div class="col-12 col-lg-8 col-xl-9">
						<div class="row g-3">

							<!-- Datos personales -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-user me-2" aria-hidden="true"></i>Datos personales</h3></div>
									<div class="card-body">
										<div class="row g-3">
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label" for="alumno_tipoidentificacion">Tipo de identificación</label>
												<select id="alumno_tipoidentificacion" class="form-select" name="alumno_tipoidentificacion">
													<?php echo $insAlumno->listarOptionTipoIdentificacion($al['alumno_tipoidentificacion']); ?>
												</select>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_identificacion">Identificación</label>
												<input type="text" class="form-control" id="alumno_identificacion" name="alumno_identificacion" value="<?php echo $h($al['alumno_identificacion']); ?>" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label" for="alumno_nacionalidadid">Nacionalidad</label>
												<select class="form-select" id="alumno_nacionalidadid" name="alumno_nacionalidadid">
													<?php echo $insAlumno->listarOptionNacionalidad($al['alumno_nacionalidadid']); ?>
												</select>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label ds-req" for="alumno_nombre1">Primer nombre</label>
												<input type="text" class="form-control" id="alumno_nombre1" name="alumno_nombre1" value="<?php echo $h($al['alumno_primernombre']); ?>" autocapitalize="words" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="alumno_nombre2">Segundo nombre</label>
												<input type="text" class="form-control" id="alumno_nombre2" name="alumno_nombre2" value="<?php echo $h($al['alumno_segundonombre']); ?>" autocapitalize="words">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label ds-req" for="alumno_apellido1">Apellido paterno</label>
												<input type="text" class="form-control" id="alumno_apellido1" name="alumno_apellido1" value="<?php echo $h($al['alumno_apellidopaterno']); ?>" autocapitalize="words" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="alumno_apellido2">Apellido materno</label>
												<input type="text" class="form-control" id="alumno_apellido2" name="alumno_apellido2" value="<?php echo $h($al['alumno_apellidomaterno']); ?>" autocapitalize="words">
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_fechanacimiento">Fecha de nacimiento</label>
												<div class="input-group">
													<input type="date" class="form-control" id="alumno_fechanacimiento" name="alumno_fechanacimiento" value="<?php echo $h($al['alumno_fechanacimiento']); ?>" required>
													<span id="edad_texto" class="input-group-text ds-editar__calculo" aria-live="polite">--</span>
												</div>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<span class="form-label d-block ds-req" id="rotulo_genero">Sexo</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_genero">
													<label class="ds-opcion"><input type="radio" id="alumno_generoM" name="alumno_genero" value="M" <?php echo $marcado($al['alumno_genero'], 'M'); ?> required> Masculino</label>
													<label class="ds-opcion"><input type="radio" id="alumno_generoF" name="alumno_genero" value="F" <?php echo $marcado($al['alumno_genero'], 'F'); ?>> Femenino</label>
												</div>
											</div>
											<div class="col-12 col-xl-4">
												<label class="form-label" for="alumno_direccion">Dirección</label>
												<input type="text" class="form-control" id="alumno_direccion" name="alumno_direccion" value="<?php echo $h($al['alumno_direccion']); ?>" autocomplete="street-address">
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- En la escuela -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-futbol me-2" aria-hidden="true"></i>En la escuela</h3></div>
									<div class="card-body">
										<div class="row g-3">
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="alumno_sedeid">Sede</label>
												<select class="form-select" id="alumno_sedeid" name="alumno_sedeid">
													<?php echo $insAlumno->listarSedeAlumno($al['alumno_sedeid']); ?>
												</select>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_fechaingreso">Fecha de ingreso</label>
												<div class="input-group">
													<input type="date" class="form-control" id="alumno_fechaingreso" name="alumno_fechaingreso" value="<?php echo $h($al['alumno_fechaingreso']); ?>" required>
													<span id="ingreso_texto" class="input-group-text ds-editar__calculo" aria-live="polite">--</span>
												</div>
											</div>
											<div class="col-12 col-sm-6 col-xl-2">
												<label class="form-label" for="alumno_numcamiseta">Camiseta</label>
												<input type="text" class="form-control" id="alumno_numcamiseta" name="alumno_numcamiseta" value="<?php echo (int)$al['alumno_numcamiseta'] > 0 ? (int)$al['alumno_numcamiseta'] : ''; ?>" inputmode="numeric" pattern="[0-9]*" maxlength="3" placeholder="Sin asignar">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<span class="form-label d-block ds-req" id="rotulo_hermanos">¿Hermanos en la escuela?</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_hermanos">
													<label class="ds-opcion"><input type="radio" id="alumno_hermanosSi" name="alumno_hermanos" value="S" <?php echo $marcado($al['alumno_hermanos'], 'S'); ?> required> Sí</label>
													<label class="ds-opcion"><input type="radio" id="alumno_hermanosNo" name="alumno_hermanos" value="N" <?php echo $marcado($al['alumno_hermanos'], 'N'); ?>> No</label>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Horario -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-clock me-2" aria-hidden="true"></i>Horario de entrenamiento</h3></div>
									<div class="card-body">
										<?php if (count($horarios) > 1): ?>
											<div class="alert alert-warning py-2" role="note">
												Tiene <?php echo count($horarios); ?> horarios asignados. Al guardar quedará sólo el que esté elegido aquí.
											</div>
										<?php endif; ?>
										<div class="row g-3">
											<div class="col-12 col-md-8 col-xl-6">
												<label class="form-label" for="horarioid">Horario</label>
												<select id="horarioid" class="form-select js-buscador" name="horarioid">
													<option value="">Sin horario</option>
													<?php echo $insAlumno->listarhorariosProfile($horarioid, $al['alumno_sedeid']); ?>
												</select>
												<p class="form-text mb-0">Se muestran los horarios de la sede guardada. Si cambia de sede, guarde primero para ver los de la nueva.</p>
											</div>
											<div class="col-12">
												<ul class="ds-perfil__horario" id="horario_dias" aria-live="polite">
													<?php if ($horarios && $horarios[0]['dias']): ?>
														<?php foreach ($horarios[0]['dias'] as $d): ?>
															<li>
																<span class="ds-perfil__dia"><?php echo $h($DIAS[$d['dia']] ?? ('Día ' . $d['dia'])); ?></span>
																<span class="ds-perfil__hora"><?php echo $h(trim($d['inicio'] . ' – ' . $d['fin'], ' –')); ?></span>
																<span class="ds-perfil__lugar">
																	<?php if ($d['lugar'] !== ''): ?><span><i class="fas fa-location-dot me-1" aria-hidden="true"></i><?php echo $h($d['lugar']); ?></span><?php endif; ?>
																	<?php if ($d['profesor'] !== ''): ?><span><i class="fas fa-user me-1" aria-hidden="true"></i><?php echo $h($d['profesor']); ?></span><?php endif; ?>
																</span>
															</li>
														<?php endforeach; ?>
													<?php else: ?>
														<li class="ds-editar__vacio"><?php echo $horarioid ? 'El horario no tiene días cargados.' : 'Sin horario asignado.'; ?></li>
													<?php endif; ?>
												</ul>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Contacto de emergencia -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-phone me-2" aria-hidden="true"></i>Contacto de emergencia</h3></div>
									<div class="card-body">
										<div class="row g-3">
											<div class="col-12 col-sm-6 col-xl-5">
												<label class="form-label" for="cemer_nombre">Nombre</label>
												<input type="text" class="form-control" id="cemer_nombre" name="cemer_nombre" value="<?php echo $h($cemer['cemer_nombre'] ?? ''); ?>" autocapitalize="words">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="cemer_celular">Celular</label>
												<input type="tel" class="form-control" id="cemer_celular" name="cemer_celular" value="<?php echo $h($cemer['cemer_celular'] ?? ''); ?>" inputmode="tel" placeholder="09…">
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label" for="cemer_parentesco">Parentesco</label>
												<select class="form-select" id="cemer_parentesco" name="cemer_parentesco">
													<?php echo $insAlumno->listarCatalogoParentesco($cemer['cemer_parentesco'] ?? ''); ?>
												</select>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Información médica -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-notes-medical me-2" aria-hidden="true"></i>Información médica</h3></div>
									<div class="card-body">
										<div class="row g-3">
											<div class="col-12 col-sm-4 col-xl-2">
												<label class="form-label" for="infomedic_tiposangre">Tipo de sangre</label>
												<input type="text" class="form-control" id="infomedic_tiposangre" name="infomedic_tiposangre" value="<?php echo $medico('infomedic_tiposangre'); ?>" placeholder="ORH+">
											</div>
											<div class="col-6 col-sm-4 col-xl-2">
												<label class="form-label" for="infomedic_peso">Peso (kg)</label>
												<input type="text" class="form-control" id="infomedic_peso" name="infomedic_peso" value="<?php echo $medico('infomedic_peso'); ?>" inputmode="decimal">
											</div>
											<div class="col-6 col-sm-4 col-xl-2">
												<label class="form-label" for="infomedic_talla">Talla (cm)</label>
												<input type="text" class="form-control" id="infomedic_talla" name="infomedic_talla" value="<?php echo $medico('infomedic_talla'); ?>" inputmode="decimal">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<span class="form-label d-block" id="rotulo_covid">Vacuna COVID</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_covid">
													<label class="ds-opcion"><input type="radio" id="infomedic_covidSi" name="infomedic_covid" value="S" <?php echo $marcado($med['infomedic_covid'] ?? '', 'S'); ?>> Sí</label>
													<label class="ds-opcion"><input type="radio" id="infomedic_covidNo" name="infomedic_covid" value="N" <?php echo $marcado($med['infomedic_covid'] ?? '', 'N'); ?>> No</label>
												</div>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<span class="form-label d-block" id="rotulo_vacunas">Vacunas habituales al día</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_vacunas">
													<label class="ds-opcion"><input type="radio" id="infomedic_vacunasSi" name="infomedic_vacunas" value="S" <?php echo $marcado($med['infomedic_vacunas'] ?? '', 'S'); ?>> Sí</label>
													<label class="ds-opcion"><input type="radio" id="infomedic_vacunasNo" name="infomedic_vacunas" value="N" <?php echo $marcado($med['infomedic_vacunas'] ?? '', 'N'); ?>> No</label>
												</div>
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_enfermedad">Enfermedad diagnosticada</label>
												<input type="text" class="form-control" id="infomedic_enfermedad" name="infomedic_enfermedad" value="<?php echo $medico('infomedic_enfermedad'); ?>" placeholder="Ninguna">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_medicamentos">Medicamentos</label>
												<input type="text" class="form-control" id="infomedic_medicamentos" name="infomedic_medicamentos" value="<?php echo $medico('infomedic_medicamentos'); ?>" placeholder="Ninguno">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_alergia1">Alergia a medicamentos</label>
												<input type="text" class="form-control" id="infomedic_alergia1" name="infomedic_alergia1" value="<?php echo $medico('infomedic_alergia1'); ?>" placeholder="Ninguna">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_alergia2">Alergia a objetos</label>
												<input type="text" class="form-control" id="infomedic_alergia2" name="infomedic_alergia2" value="<?php echo $medico('infomedic_alergia2'); ?>" placeholder="Ninguna">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_cirugias">Cirugías</label>
												<input type="text" class="form-control" id="infomedic_cirugias" name="infomedic_cirugias" value="<?php echo $medico('infomedic_cirugias'); ?>" placeholder="Ninguna">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_observacion">Observación</label>
												<input type="text" class="form-control" id="infomedic_observacion" name="infomedic_observacion" value="<?php echo $medico('infomedic_observacion'); ?>">
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Cédula -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-id-card me-2" aria-hidden="true"></i>Cédula</h3></div>
									<div class="card-body">
										<div class="row g-3">
											<?php foreach ($cedulas as $campo => $c): ?>
												<div class="col-12 col-sm-6">
													<span class="form-label d-block"><?php echo $c['lado']; ?></span>
													<div class="fileinput fileinput-new ds-crud__foto ds-editar__cedula" data-provides="fileinput">
														<div class="fileinput-new thumbnail" data-trigger="fileinput">
															<?php if ($c['img'] !== ''): ?>
																<img src="<?php echo $h($c['img']); ?>" alt="Cédula, <?php echo mb_strtolower($c['lado']); ?>" loading="lazy">
															<?php else: ?>
																<span class="ds-editar__sin-imagen"><i class="fas fa-id-card" aria-hidden="true"></i>Sin registrar</span>
															<?php endif; ?>
														</div>
														<div class="fileinput-preview fileinput-exists thumbnail"></div>
														<div class="ds-crud__foto-botones">
															<span class="bton bton-white bton-file">
																<span class="fileinput-new"><i class="fas fa-upload me-1" aria-hidden="true"></i><?php echo $c['img'] !== '' ? 'Cambiar' : 'Subir'; ?></span>
																<span class="fileinput-exists">Elegir otra</span>
																<input type="file" name="<?php echo $campo; ?>" id="<?php echo $campo; ?>" accept="image/jpeg,image/png" aria-label="Cédula, <?php echo mb_strtolower($c['lado']); ?>">
															</span>
															<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Quitar</a>
														</div>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
										<p class="form-text mb-0 mt-2">JPG o PNG, hasta 4 MB cada lado.</p>
									</div>
								</div>
							</div>

						</div>
					</div>
				</div>

				<!-- Guardar, siempre a la vista: antes el botón quedaba al final de
				     la pestaña y en el teléfono había que bajar hasta el fondo. -->
				<div class="ds-editar__barra">
					<a href="<?php echo APP_URL; ?>alumnoProfile/<?php echo (int)$alumnoid; ?>/" class="btn btn-secondary"><i class="fas fa-xmark me-1" aria-hidden="true"></i>Cancelar</a>
					<button type="submit" class="btn btn-success"><i class="fas fa-floppy-disk me-1" aria-hidden="true"></i>Guardar cambios</button>
				</div>
			</form>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<!-- jQuery sólo por select2, el buscador de horarios -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/select2/js/select2.full.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/form-secciones.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/alumno-form.js'); ?>"></script>
  </body>
</html>
