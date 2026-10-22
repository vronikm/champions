<?php
	use app\controllers\alumnoController;
	$insAlumno = new alumnoController();

	/* alumnoNew/{representante}/ desde Representantes; alumnoNew/ a secas
	   desde «Nuevo alumno» del menú superior, sin representante. */
	$repre = $insAlumno->representanteParaAlta($url[1] ?? 0);

	$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

	/* La cabecera es común a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Nuevo alumno';
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
						<h4 class="m-0">Nuevo alumno</h4>
						<?php if ($repre): ?><p class="small text-body-secondary mb-0">Representante: <?php echo $h($repre['nombre']); ?></p><?php endif; ?>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>representanteList/">Representantes</a></li>
							<li class="breadcrumb-item active" aria-current="page">Nuevo alumno</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
			<form id="formAlumno" class="FormularioAjax ds-editar" action="<?php echo APP_URL; ?>app/ajax/alumnoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_alumno" value="registrar">
				<input type="hidden" name="alumno_repreid" value="<?php echo $repre ? $repre['id'] : ''; ?>">

				<!-- ============ De quién es hijo ============ -->
				<?php if ($repre): ?>
					<div class="card ds-alta__repre mb-3">
						<div class="card-body">
							<span class="ds-alta__repre-icono" aria-hidden="true"><i class="fas fa-user-group"></i></span>
							<div class="ds-alta__repre-datos">
								<span class="ds-alta__repre-rotulo">Representante</span>
								<strong><?php echo $h($repre['nombre']); ?></strong>
								<span class="text-body-secondary">
									<?php echo $h($repre['identificacion']); ?>
									<?php if ($repre['celular'] !== ''): ?> · <?php echo $h($repre['celular']); ?><?php endif; ?>
								</span>
							</div>
							<?php if ($repre['alumnos'] > 0): ?>
								<span class="badge text-bg-secondary ds-alta__repre-hijos"><?php echo $repre['alumnos'] . ($repre['alumnos'] === 1 ? ' alumno inscrito' : ' alumnos inscritos'); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php else: ?>
					<div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="note">
						<i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
						<span class="me-auto">El alumno se registrará <strong>sin representante</strong>. Para vincularlo, empiece desde el representante.</span>
						<a href="<?php echo APP_URL; ?>representanteList/" class="btn btn-sm btn-secondary">Elegir representante</a>
					</div>
				<?php endif; ?>

				<div class="row g-3">

					<!-- ============ Foto ============ -->
					<div class="col-12 col-lg-4 col-xl-3">
						<div class="card ds-editar__lateral">
							<div class="card-header"><h3 class="card-title"><i class="fas fa-camera me-2" aria-hidden="true"></i>Foto</h3></div>
							<div class="card-body">
								<div class="fileinput fileinput-new ds-crud__foto ds-editar__foto" data-provides="fileinput">
									<div class="fileinput-new thumbnail" data-trigger="fileinput">
										<span class="ds-editar__sin-imagen"><i class="fas fa-user" aria-hidden="true"></i>Sin foto</span>
									</div>
									<div class="fileinput-preview fileinput-exists thumbnail"></div>
									<div class="ds-crud__foto-botones">
										<span class="bton bton-white bton-file">
											<span class="fileinput-new"><i class="fas fa-upload me-1" aria-hidden="true"></i>Subir foto</span>
											<span class="fileinput-exists">Elegir otra</span>
											<input type="file" name="alumno_foto" id="alumno_foto" accept="image/jpeg,image/png" aria-label="Foto del alumno">
										</span>
										<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Quitar</a>
									</div>
								</div>
								<p class="form-text mb-0">JPG o PNG, hasta 4 MB. Desde el teléfono puede tomarla con la cámara.</p>
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
													<?php echo $insAlumno->listarCatalogoTipoDocumento(); ?>
												</select>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_identificacion">Identificación</label>
												<input type="text" class="form-control" id="alumno_identificacion" name="alumno_identificacion" placeholder="Número de cédula o pasaporte" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label" for="alumno_nacionalidadid">Nacionalidad</label>
												<select class="form-select" id="alumno_nacionalidadid" name="alumno_nacionalidadid">
													<?php /* Ecuatoriana de entrada: lo son los 936 alumnos
													         registrados, y el catálogo empieza por Colombiana. */
													      echo $insAlumno->listarOptionNacionalidad('ECU'); ?>
												</select>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label ds-req" for="alumno_nombre1">Primer nombre</label>
												<input type="text" class="form-control" id="alumno_nombre1" name="alumno_nombre1" autocapitalize="words" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="alumno_nombre2">Segundo nombre</label>
												<input type="text" class="form-control" id="alumno_nombre2" name="alumno_nombre2" autocapitalize="words">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label ds-req" for="alumno_apellido1">Apellido paterno</label>
												<input type="text" class="form-control" id="alumno_apellido1" name="alumno_apellido1" autocapitalize="words" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="alumno_apellido2">Apellido materno</label>
												<input type="text" class="form-control" id="alumno_apellido2" name="alumno_apellido2" autocapitalize="words">
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_fechanacimiento">Fecha de nacimiento</label>
												<div class="input-group">
													<input type="date" class="form-control" id="alumno_fechanacimiento" name="alumno_fechanacimiento" max="<?php echo date('Y-m-d'); ?>" title="no puede ser una fecha futura" required>
													<span id="edad_texto" class="input-group-text ds-editar__calculo" aria-live="polite">--</span>
												</div>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<span class="form-label d-block ds-req" id="rotulo_genero">Sexo</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_genero">
													<label class="ds-opcion"><input type="radio" id="alumno_generoM" name="alumno_genero" value="M" required> Masculino</label>
													<label class="ds-opcion"><input type="radio" id="alumno_generoF" name="alumno_genero" value="F"> Femenino</label>
												</div>
											</div>
											<div class="col-12 col-xl-4">
												<label class="form-label" for="alumno_direccion">Dirección</label>
												<input type="text" class="form-control" id="alumno_direccion" name="alumno_direccion" placeholder="Barrio, calle principal, número, calle secundaria" autocomplete="street-address">
												<?php if ($repre && $repre['direccion'] !== ''): ?>
													<button type="button" class="btn btn-link btn-sm px-0 ds-alta__copiar" data-ds-copiar="alumno_direccion" data-valor="<?php echo $h($repre['direccion']); ?>">
														<i class="fas fa-copy me-1" aria-hidden="true"></i>Usar la del representante
													</button>
												<?php endif; ?>
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
													<?php echo $insAlumno->listarOptionSede($_SESSION['rol'], $_SESSION['usuario']); ?>
												</select>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_fechaingreso">Fecha de ingreso</label>
												<div class="input-group">
													<input type="date" class="form-control" id="alumno_fechaingreso" name="alumno_fechaingreso" value="<?php echo date('Y-m-d'); ?>" required>
													<span id="ingreso_texto" class="input-group-text ds-editar__calculo" aria-live="polite">--</span>
												</div>
											</div>
											<div class="col-12 col-sm-6 col-xl-2">
												<label class="form-label" for="alumno_numcamiseta">Camiseta</label>
												<input type="text" class="form-control" id="alumno_numcamiseta" name="alumno_numcamiseta" inputmode="numeric" pattern="[0-9]*" maxlength="3" placeholder="Sin asignar">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<span class="form-label d-block ds-req" id="rotulo_hermanos">¿Hermanos en la escuela?</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_hermanos">
													<label class="ds-opcion"><input type="radio" id="alumno_hermanosSi" name="alumno_hermanos" value="S" required> Sí</label>
													<label class="ds-opcion"><input type="radio" id="alumno_hermanosNo" name="alumno_hermanos" value="N"> No</label>
												</div>
												<?php if ($repre && $repre['alumnos'] > 0): ?>
													<p class="form-text mb-0">Su representante ya tiene <?php echo $repre['alumnos'] . ($repre['alumnos'] === 1 ? ' alumno inscrito' : ' alumnos inscritos'); ?>.</p>
												<?php endif; ?>
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
										<div class="row g-3">
											<div class="col-12 col-md-8 col-xl-6">
												<label class="form-label" for="horarioid">Horario</label>
												<select id="horarioid" class="form-select js-buscador" name="horarioid">
													<option value="">Sin horario</option>
													<?php echo $insAlumno->listarhorarios(); ?>
												</select>
												<p class="form-text mb-0">Opcional: se puede asignar después desde Horarios.</p>
											</div>
											<div class="col-12">
												<ul class="ds-perfil__horario" id="horario_dias" aria-live="polite">
													<li class="ds-editar__vacio">Sin horario asignado.</li>
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
												<input type="text" class="form-control" id="cemer_nombre" name="cemer_nombre" autocapitalize="words">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="cemer_celular">Celular</label>
												<input type="tel" class="form-control" id="cemer_celular" name="cemer_celular" inputmode="tel" placeholder="09…">
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label" for="cemer_parentesco">Parentesco</label>
												<select class="form-select" id="cemer_parentesco" name="cemer_parentesco">
													<?php echo $insAlumno->listarCatalogoParentesco(); ?>
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
												<input type="text" class="form-control" id="infomedic_tiposangre" name="infomedic_tiposangre" placeholder="ORH+">
											</div>
											<div class="col-6 col-sm-4 col-xl-2">
												<label class="form-label" for="infomedic_peso">Peso (kg)</label>
												<input type="text" class="form-control" id="infomedic_peso" name="infomedic_peso" inputmode="decimal">
											</div>
											<div class="col-6 col-sm-4 col-xl-2">
												<label class="form-label" for="infomedic_talla">Talla (cm)</label>
												<input type="text" class="form-control" id="infomedic_talla" name="infomedic_talla" inputmode="decimal">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<span class="form-label d-block" id="rotulo_covid">Vacuna COVID</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_covid">
													<label class="ds-opcion"><input type="radio" id="infomedic_covidSi" name="infomedic_covid" value="S"> Sí</label>
													<label class="ds-opcion"><input type="radio" id="infomedic_covidNo" name="infomedic_covid" value="N"> No</label>
												</div>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<span class="form-label d-block" id="rotulo_vacunas">Vacunas habituales al día</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_vacunas">
													<label class="ds-opcion"><input type="radio" id="infomedic_vacunasSi" name="infomedic_vacunas" value="S"> Sí</label>
													<label class="ds-opcion"><input type="radio" id="infomedic_vacunasNo" name="infomedic_vacunas" value="N"> No</label>
												</div>
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_enfermedad">Enfermedad diagnosticada</label>
												<input type="text" class="form-control" id="infomedic_enfermedad" name="infomedic_enfermedad" placeholder="Ninguna">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_medicamentos">Medicamentos</label>
												<input type="text" class="form-control" id="infomedic_medicamentos" name="infomedic_medicamentos" placeholder="Ninguno">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_alergia1">Alergia a medicamentos</label>
												<input type="text" class="form-control" id="infomedic_alergia1" name="infomedic_alergia1" placeholder="Ninguna">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_alergia2">Alergia a objetos</label>
												<input type="text" class="form-control" id="infomedic_alergia2" name="infomedic_alergia2" placeholder="Ninguna">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_cirugias">Cirugías</label>
												<input type="text" class="form-control" id="infomedic_cirugias" name="infomedic_cirugias" placeholder="Ninguna">
											</div>
											<div class="col-12 col-md-6">
												<label class="form-label" for="infomedic_observacion">Observación</label>
												<input type="text" class="form-control" id="infomedic_observacion" name="infomedic_observacion">
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
											<?php foreach (['alumno_cedulaA' => 'Anverso', 'alumno_cedulaR' => 'Reverso'] as $campo => $lado): ?>
												<div class="col-12 col-sm-6">
													<span class="form-label d-block"><?php echo $lado; ?></span>
													<div class="fileinput fileinput-new ds-crud__foto ds-editar__cedula" data-provides="fileinput">
														<div class="fileinput-new thumbnail" data-trigger="fileinput">
															<span class="ds-editar__sin-imagen"><i class="fas fa-id-card" aria-hidden="true"></i>Sin registrar</span>
														</div>
														<div class="fileinput-preview fileinput-exists thumbnail"></div>
														<div class="ds-crud__foto-botones">
															<span class="bton bton-white bton-file">
																<span class="fileinput-new"><i class="fas fa-upload me-1" aria-hidden="true"></i>Subir</span>
																<span class="fileinput-exists">Elegir otra</span>
																<input type="file" name="<?php echo $campo; ?>" id="<?php echo $campo; ?>" accept="image/jpeg,image/png" aria-label="Cédula, <?php echo mb_strtolower($lado); ?>">
															</span>
															<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Quitar</a>
														</div>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
										<p class="form-text mb-0 mt-2">Opcional. JPG o PNG, hasta 4 MB cada lado.</p>
									</div>
								</div>
							</div>

						</div>
					</div>
				</div>

				<!-- Guardar, siempre a la vista. Antes era un asistente de cinco
				     pasos y «Guardar» sólo aparecía en el último. No hay botón de
				     limpiar: pegado a «Registrar», en el teléfono se pulsaba sin
				     querer y se perdía todo lo escrito. -->
				<div class="ds-editar__barra">
					<a href="<?php echo APP_URL . ($repre ? 'representanteList/' : 'alumnoList/'); ?>" class="btn btn-secondary"><i class="fas fa-xmark me-1" aria-hidden="true"></i>Cancelar</a>
					<button type="submit" class="btn btn-success"><i class="fas fa-floppy-disk me-1" aria-hidden="true"></i>Registrar alumno</button>
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
