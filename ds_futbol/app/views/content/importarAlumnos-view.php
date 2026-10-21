<?php
	use app\controllers\alumnoController;
	$insAlumnoImp = new alumnoController();
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Importar Alumnos';
	$extras      = array (0 => 'swal',);
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
						<h1 class="m-0">Importar Alumnos desde CSV</h1>
					</div>
					<div class="col-sm-6">
						<ol class="breadcrumb float-sm-end">
							<li class="breadcrumb-item"><a href="#">Configuración</a></li>
							<li class="breadcrumb-item active">Importar Alumnos</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">

				<!-- Paso 1: subir archivo y analizar -->
				<div class="card card-info">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-file-csv"></i> Paso 1 — Cargar y analizar CSV</h3>
					</div>
					<form id="formAnalizar" action="<?php echo APP_URL; ?>app/ajax/importarAlumnosAjax.php" method="POST" enctype="multipart/form-data" autocomplete="off">
						<input type="hidden" name="modulo_import" value="analizar">
						<div class="card-body">
							<div class="row">
								<div class="col-md-4">
									<div class="mb-3">
										<label>Sede destino</label>
										<select class="form-select" name="alumno_sedeid" required>
											<option value="">— Seleccione —</option>
											<?php echo $insAlumnoImp->listarOptionSede($_SESSION['rol'], $_SESSION['usuario']); ?>
										</select>
									</div>
								</div>
								<div class="col-md-6">
									<div class="mb-3">
										<label>Archivo CSV</label>
										<input type="file" name="archivo_csv" class="form-control" accept=".csv,text/csv" required>
										<small class="form-text text-muted">
											Separador: punto y coma (;). Columnas esperadas: Nombre alumno; Cédula; F.Nacimiento; Edad; F.Ingreso; Representante; Cédula; Correo; Teléfono; Dirección.
										</small>
									</div>
								</div>
								<div class="col-md-2 d-flex align-items-end">
									<button type="submit" class="btn btn-info w-100">
										<i class="fas fa-search"></i> Analizar
									</button>
								</div>
							</div>
						</div>
					</form>
				</div>

				<!-- Paso 2: reporte y confirmación -->
				<div class="card card-success" id="cardReporte" style="display:none;">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-clipboard-check"></i> Paso 2 — Informe de validación</h3>
					</div>
					<div class="card-body">
						<div class="alert alert-warning">
							<i class="fas fa-exclamation-triangle"></i>
							Revise el informe a continuación. <b>Los alumnos marcados como BLOQUEADO no se cargarán.</b>
							Las advertencias se cargan, pero deben revisarse luego.
						</div>
						<div id="reporteContenido"></div>
					</div>
					<div class="card-footer text-end">
						<button type="button" class="btn btn-secondary" onclick="document.getElementById('cardReporte').style.display='none';">
							<i class="fas fa-undo"></i> Cancelar
						</button>
						<form id="formCargar" action="<?php echo APP_URL; ?>app/ajax/importarAlumnosAjax.php" method="POST" style="display:inline;">
							<input type="hidden" name="modulo_import" value="cargar">
							<button type="submit" class="btn btn-success">
								<i class="fas fa-database"></i> Confirmar e insertar en BD
							</button>
						</form>
					</div>
				</div>

			</div>
		</section>
	  </div>

	  <?php require_once "app/views/inc/footer.php"; ?>
	</div>

	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
		$(function(){
		});

		// Paso 1: enviar análisis vía fetch (no usamos FormularioAjax porque queremos pintar el reporte en la página)
		document.getElementById('formAnalizar').addEventListener('submit', function(e){
			e.preventDefault();
			let fd = new FormData(this);

			Swal.fire({
				title: 'Procesando archivo...',
				allowOutsideClick: false,
				didOpen: () => Swal.showLoading()
			});

			fetch(this.action, { method: 'POST', body: fd })
				.then(r => r.json())
				.then(resp => {
					Swal.close();
					if (resp.tipo === 'preview') {
						document.getElementById('reporteContenido').innerHTML = resp.html;
						document.getElementById('cardReporte').style.display = 'block';
						document.getElementById('cardReporte').scrollIntoView({ behavior: 'smooth' });
					} else {
						Swal.fire({
							icon: resp.icono || 'error',
							title: resp.titulo || 'Error',
							text:  resp.texto  || 'Error al analizar el archivo'
						});
					}
				})
				.catch(err => {
					Swal.close();
					Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo procesar el archivo: ' + err.message });
				});
		});

		// Paso 2: confirmar carga
		document.getElementById('formCargar').addEventListener('submit', function(e){
			e.preventDefault();
			Swal.fire({
				title: '¿Confirmar carga?',
				text: 'Se insertarán los alumnos marcados como OK. Esta acción no se puede deshacer.',
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Sí, cargar',
				cancelButtonText: 'No'
			}).then(result => {
				if (!result.isConfirmed) return;

				Swal.fire({
					title: 'Insertando registros...',
					allowOutsideClick: false,
					didOpen: () => Swal.showLoading()
				});

				let fd = new FormData(this);
				fetch(this.action, { method: 'POST', body: fd })
					.then(r => r.json())
					.then(resp => {
						Swal.fire({
							icon: resp.icono || 'success',
							title: resp.titulo || 'Listo',
							text:  resp.texto  || ''
						}).then(() => location.reload());
					})
					.catch(err => {
						Swal.fire({ icon: 'error', title: 'Error', text: err.message });
					});
			});
		});
	</script>
  </body>
</html>
