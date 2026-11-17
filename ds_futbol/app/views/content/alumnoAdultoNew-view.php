<?php
	use app\controllers\alumnoAdultoController;
	use app\controllers\alumnoController;

	/* Alta manual de un alumno ADULTO (migración 086): él es su propio
	   titular, sin representante. El alta de siempre (alumnoNew) no cambia. */
	$insAdulto = new alumnoAdultoController();
	$insAlumno = new alumnoController();
	$sedes     = $insAdulto->sedesAdultos();
	$puedeCrear = puede('crear', 'alumnoAdultoNew');

	$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
	$maxNacimiento = (new DateTime('today'))->modify('-18 years')->format('Y-m-d');

	$tituloVista = 'Nuevo alumno adulto';
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
						<h4 class="m-0">Nuevo alumno adulto</h4>
						<p class="small text-body-secondary mb-0">Mayor de edad, sin representante: es su propio titular.</p>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>alumnoList/">Alumnos</a></li>
							<li class="breadcrumb-item active" aria-current="page">Nuevo alumno adulto</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">

			<?php if (!$puedeCrear): ?>
				<div class="alert alert-danger" role="alert">Su rol no puede registrar alumnos adultos.</div>
			<?php elseif (!$sedes): ?>
				<div class="alert alert-warning d-flex align-items-start gap-2" role="note">
					<i class="fas fa-triangle-exclamation mt-1" aria-hidden="true"></i>
					<div>Ninguna de sus sedes admite alumnos adultos. Se habilita en <strong>Core › Sedes</strong>,
					     editando la sede y marcando «Admite alumnos adultos».</div>
				</div>
			<?php else: ?>

			<form id="formAlumnoAdulto" class="FormularioAjax ds-editar" action="<?php echo APP_URL; ?>app/ajax/alumnoAdultoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_alumno_adulto" value="registrar">

				<div class="alert alert-info d-flex align-items-start gap-2" role="note">
					<i class="fas fa-file-invoice-dollar mt-1" aria-hidden="true"></i>
					<div>Con estos datos se crea también su <strong>ficha de titular</strong>: los recibos y las facturas
					     salen a su nombre, con su cédula o pasaporte, y llegan a su correo y su WhatsApp.</div>
				</div>

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
								<p class="form-text mb-0">Opcional. JPG o PNG, hasta 4 MB.</p>
							</div>
						</div>
					</div>

					<div class="col-12 col-lg-8 col-xl-9">
						<div class="row g-3">

							<!-- Datos personales -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-user me-2" aria-hidden="true"></i>Datos personales</h3></div>
									<div class="card-body">
										<div class="row g-3">
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_tipoidentificacion">Tipo de identificación</label>
												<select id="alumno_tipoidentificacion" class="form-select" name="alumno_tipoidentificacion">
													<option value="CED" selected>Cédula</option>
													<option value="PAS">Pasaporte</option>
												</select>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_identificacion">Identificación</label>
												<input type="text" class="form-control" id="alumno_identificacion" name="alumno_identificacion" maxlength="13" placeholder="Cédula o pasaporte" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label" for="alumno_nacionalidadid">Nacionalidad</label>
												<select class="form-select" id="alumno_nacionalidadid" name="alumno_nacionalidadid">
													<?php echo $insAlumno->listarOptionNacionalidad('ECU'); ?>
												</select>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label ds-req" for="alumno_primernombre">Primer nombre</label>
												<input type="text" class="form-control" id="alumno_primernombre" name="alumno_primernombre" maxlength="50" autocapitalize="words" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="alumno_segundonombre">Segundo nombre</label>
												<input type="text" class="form-control" id="alumno_segundonombre" name="alumno_segundonombre" maxlength="50" autocapitalize="words">
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label ds-req" for="alumno_apellidopaterno">Apellido paterno</label>
												<input type="text" class="form-control" id="alumno_apellidopaterno" name="alumno_apellidopaterno" maxlength="50" autocapitalize="words" required>
											</div>
											<div class="col-12 col-sm-6 col-xl-3">
												<label class="form-label" for="alumno_apellidomaterno">Apellido materno</label>
												<input type="text" class="form-control" id="alumno_apellidomaterno" name="alumno_apellidomaterno" maxlength="50" autocapitalize="words">
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_fechanacimiento">Fecha de nacimiento</label>
												<div class="input-group">
													<input type="date" class="form-control" id="alumno_fechanacimiento" name="alumno_fechanacimiento" max="<?php echo $maxNacimiento; ?>" title="Debe tener 18 años o más" required>
													<span id="edad_texto" class="input-group-text ds-editar__calculo" aria-live="polite">--</span>
												</div>
											</div>
											<div class="col-12 col-sm-6 col-xl-4">
												<span class="form-label d-block ds-req" id="rotulo_genero">Sexo</span>
												<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_genero">
													<label class="ds-opcion"><input type="radio" name="alumno_genero" value="M" required> Masculino</label>
													<label class="ds-opcion"><input type="radio" name="alumno_genero" value="F"> Femenino</label>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Contacto y facturación -->
							<div class="col-12">
								<div class="card">
									<div class="card-header"><h3 class="card-title"><i class="fas fa-address-book me-2" aria-hidden="true"></i>Contacto y facturación</h3></div>
									<div class="card-body">
										<div class="row g-3">
											<div class="col-12 col-sm-6">
												<label class="form-label ds-req" for="alumno_correo">Correo electrónico</label>
												<input type="email" class="form-control" id="alumno_correo" name="alumno_correo" maxlength="50" placeholder="correo@ejemplo.com" autocomplete="email" required>
												<p class="form-text mb-0">Aquí llegan sus recibos y facturas.</p>
											</div>
											<div class="col-12 col-sm-6">
												<label class="form-label ds-req" for="alumno_celular">Celular</label>
												<input type="tel" class="form-control" id="alumno_celular" name="alumno_celular" maxlength="10" inputmode="numeric" placeholder="09XXXXXXXX" required>
											</div>
											<div class="col-12">
												<label class="form-label ds-req" for="alumno_direccion">Dirección</label>
												<input type="text" class="form-control" id="alumno_direccion" name="alumno_direccion" maxlength="400" placeholder="Barrio, calle principal, número, calle secundaria" autocomplete="street-address" required>
												<p class="form-text mb-0">Es la dirección que lleva la factura.</p>
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
											<div class="col-12 col-sm-6 col-xl-4">
												<label class="form-label ds-req" for="alumno_sedeid">Sede</label>
												<select class="form-select" id="alumno_sedeid" name="alumno_sedeid">
													<?php foreach ($sedes as $s): ?>
														<option value="<?php echo (int)$s['sede_id']; ?>"><?php echo $h($s['sede_nombre']); ?></option>
													<?php endforeach; ?>
												</select>
												<p class="form-text mb-0">Sólo las sedes que admiten alumnos adultos.</p>
											</div>
											<div class="col-12 col-xl-8">
												<p class="form-text mb-0 mt-xl-4">
													Horario, contacto de emergencia, información médica y cédula se completan
													en la ficha del alumno, que se abre al registrar.
												</p>
											</div>
										</div>
									</div>
								</div>
							</div>

						</div>
					</div>
				</div>

				<div class="ds-editar__barra">
					<a href="<?php echo APP_URL; ?>alumnoList/" class="btn btn-secondary"><i class="fas fa-xmark me-1" aria-hidden="true"></i>Cancelar</a>
					<button type="submit" class="btn btn-success"><i class="fas fa-floppy-disk me-1" aria-hidden="true"></i>Registrar alumno adulto</button>
				</div>
			</form>
			<?php endif; ?>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
	<script>
	/* La edad junto a la fecha, y aviso si no llega a 18 (el servidor lo vuelve a comprobar). */
	(function () {
		var fecha = document.getElementById('alumno_fechanacimiento'),
		    salida = document.getElementById('edad_texto');
		if (!fecha || !salida) { return; }
		function poner() {
			var p = (fecha.value || '').split('-');
			if (p.length !== 3) { salida.textContent = '--'; fecha.setCustomValidity(''); return; }
			var n = new Date(+p[0], +p[1] - 1, +p[2]), hoy = new Date();
			var edad = hoy.getFullYear() - n.getFullYear()
			         - ((hoy.getMonth() < n.getMonth() || (hoy.getMonth() === n.getMonth() && hoy.getDate() < n.getDate())) ? 1 : 0);
			salida.textContent = isNaN(edad) ? '--' : edad + (edad === 1 ? ' año' : ' años');
			fecha.setCustomValidity(edad < 18 ? 'Debe tener 18 años o más. Un menor se inscribe con su representante.' : '');
		}
		fecha.addEventListener('input', poner);
		poner();
	})();
	</script>
  </body>
</html>
