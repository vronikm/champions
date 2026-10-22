<?php
	use app\controllers\torneoController;
	$insTorneo = new torneoController();

	$torneoid = (($url[1] ?? "") != "") ? $url[1] : 0;

	$foto = APP_URL.'app/views/imagenes/fotos/torneos/torneo_default.jpg';

	/* Valores del formulario. Por omisión, alta de un torneo nuevo; con un id
	   válido en la URL, edición de ese torneo. */
	$modulo_torneo      = 'registrar';
	$torneo_nombre      = '';
	$torneo_ciudad      = '';
	$torneo_lugar       = '';
	$torneo_fechainicio = '';
	$torneo_fechafin    = '';
	$torneo_organizador = '';
	$torneo_descripcion = '';
	$torneo_estado      = 'A';

	if($torneoid != 0){
		$datosTorneo=$insTorneo->BuscarTorneo($torneoid);
		if($datosTorneo->rowCount()==1){
			$datosTorneo=$datosTorneo->fetch();
			if ($datosTorneo['torneo_foto']!=""){
				$foto = APP_URL.'app/views/imagenes/fotos/torneos/'.$datosTorneo['torneo_foto'];
			}else{
				$foto = ds_recurso('ds_futbol/app/views/dist/img/torneo_default.jpg');
			}
			$modulo_torneo = 'actualizar';

			$torneo_nombre = $datosTorneo['torneo_nombre'];
			$torneo_ciudad = $datosTorneo['torneo_ciudad'];
			$torneo_lugar = $datosTorneo['torneo_lugar'];
			$torneo_fechainicio = $datosTorneo['torneo_fechainicio'];
			$torneo_fechafin = $datosTorneo['torneo_fechafin'];
			$torneo_organizador = $datosTorneo['torneo_organizador'];
			$torneo_descripcion = $datosTorneo['torneo_descripcion'];
			$torneo_estado = $datosTorneo['ESTADO'];
		}
	}

	$editando = $modulo_torneo === 'actualizar';

	/* Todo valor que viene de la base se escapa al imprimirlo: el nombre o la
	   descripción de un torneo los escribe un usuario. Se decodifica antes
	   porque limpiarHtml() ya los guarda escapados («WOMEN&quot;S»): sin
	   esto el campo mostraría «&quot;» y, al guardar, lo escaparía otra vez. */
	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

	/* El listado se genera antes para poder contar los torneos en la cabecera
	   de su sección. */
	$filasTorneos = $insTorneo->listarTorneos();
	$totalTorneos = substr_count($filasTorneos, '<tr>');
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Torneos';
	$extras      = array (0 => 'datatables',);
	require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">

      <!-- Navbar -->
      <?php require_once "app/views/inc/navbar.php"; ?>
      <!-- /.navbar -->

      <!-- Main Sidebar Container -->
      <?php require_once "app/views/inc/main-sidebar.php"; ?>
      <!-- /.Main Sidebar Container -->

      <!-- vista -->
      <div class="app-main">
		<!-- Content Header (Page header) -->
		<div class="app-content-header">
			<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h4 class="m-0">Torneos</h4>
				</div><!-- /.col -->
				<div class="col-sm-6">
					<ol class="breadcrumb float-sm-end">
						<li class="breadcrumb-item"><a href="<?php echo APP_URL."dashboard/" ?>">Inicio</a></li>
						<li class="breadcrumb-item active" aria-current="page">Torneos</li>
					</ol>
				</div><!-- /.col -->
			</div><!-- /.row -->

			<?php /* En el teléfono las dos secciones quedan una debajo de otra y
			         el formulario es largo: estos atajos llevan a cada una sin
			         recorrer la página. En pantallas anchas sobran. */ ?>
			<nav class="ds-crud__atajos d-md-none" aria-label="Secciones de la página">
				<a href="#torneo-form" class="btn btn-sm btn-outline-secondary">
					<i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus'; ?> me-1" aria-hidden="true"></i><?php echo $editando ? 'Editar torneo' : 'Nuevo torneo'; ?>
				</a>
				<a href="#torneos-ingresados" class="btn btn-sm btn-outline-secondary">
					<i class="fas fa-list me-1" aria-hidden="true"></i>Torneos ingresados <span class="badge text-bg-secondary ms-1"><?php echo $totalTorneos; ?></span>
				</a>
			</nav>
			</div><!-- /.container-fluid -->
		</div>
		<!-- /.content-header -->

		<!-- Main content -->
		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== 1. Ingreso / edición =================== -->
				<?php /* El formulario ES la tarjeta: cabecera, cuerpo y pie quedan
				         como hijos directos, que es lo que AdminLTE pliega, y el
				         botón Guardar sigue dentro del formulario. */ ?>
				<form class="card ds-crud__form FormularioAjax<?php echo $editando ? ' ds-crud__form--edicion' : ''; ?>"
				      id="torneo-form" action="<?php echo APP_URL; ?>app/ajax/torneoAjax.php" method="POST"
				      autocomplete="off" enctype="multipart/form-data">
					<input type="hidden" name="modulo_torneo" value="<?php echo $modulo_torneo; ?>">
					<input type="hidden" name="torneo_id" value="<?php echo (int)$torneoid; ?>">

					<div class="card-header">
						<h3 class="card-title">
							<i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus-circle'; ?> me-2" aria-hidden="true"></i><?php
							echo $editando ? 'Editar torneo' : 'Ingreso de nuevo torneo'; ?>
						</h3>
						<?php if ($editando): ?>
							<span class="badge text-bg-warning ms-2 ds-crud__editando"><?php echo $h($torneo_nombre); ?></span>
						<?php endif; ?>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<div class="row g-3">
							<!-- Foto -->
							<div class="col-12 col-lg-3 col-xl-2">
								<span class="form-label d-block">Foto</span>
								<div class="fileinput fileinput-new ds-crud__foto" data-provides="fileinput">
									<div class="fileinput-new thumbnail" data-trigger="fileinput"><img src="<?php echo $h($foto); ?>" alt="Foto del torneo"></div>
									<div class="fileinput-preview fileinput-exists thumbnail"></div>
									<div class="ds-crud__foto-botones">
										<span class="bton bton-white bton-file">
											<span class="fileinput-new">Seleccionar foto</span>
											<span class="fileinput-exists">Cambiar</span>
											<input type="file" name="torneo_foto" id="foto" accept="image/jpeg,image/png" aria-label="Foto del torneo">
										</span>
										<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Quitar</a>
										<small class="text-body-secondary d-block mt-1">JPG o PNG, hasta 4 MB.</small>
									</div>
								</div>
							</div>

							<!-- Datos -->
							<div class="col-12 col-lg-9 col-xl-10">
								<div class="row g-3">
									<div class="col-12 col-md-6 col-xl-4">
										<label for="torneo_nombre" class="form-label">Nombre del torneo <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="text" class="form-control" id="torneo_nombre" name="torneo_nombre" maxlength="100" required value="<?php echo $h($torneo_nombre); ?>">
									</div>
									<div class="col-12 col-sm-6 col-md-3 col-xl-4">
										<label for="torneo_ciudad" class="form-label">Ciudad <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="text" class="form-control" id="torneo_ciudad" name="torneo_ciudad" maxlength="50" required value="<?php echo $h($torneo_ciudad); ?>">
									</div>
									<div class="col-12 col-sm-6 col-md-3 col-xl-4">
										<label for="torneo_lugar" class="form-label">Lugar <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="text" class="form-control" id="torneo_lugar" name="torneo_lugar" maxlength="150" required value="<?php echo $h($torneo_lugar); ?>">
									</div>
									<div class="col-6 col-md-3">
										<label for="torneo_fechainicio" class="form-label">Inicio <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="date" class="form-control" name="torneo_fechainicio" id="torneo_fechainicio" required value="<?php echo $h($torneo_fechainicio); ?>">
									</div>
									<div class="col-6 col-md-3">
										<label for="torneo_fechafin" class="form-label">Finalización <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="date" class="form-control" name="torneo_fechafin" id="torneo_fechafin" required value="<?php echo $h($torneo_fechafin); ?>">
									</div>
									<div class="col-12 col-md-6">
										<label for="torneo_organizador" class="form-label">Organizador</label>
										<input type="text" class="form-control" id="torneo_organizador" name="torneo_organizador" maxlength="100" value="<?php echo $h($torneo_organizador); ?>">
									</div>
									<div class="col-12">
										<label for="torneo_descripcion" class="form-label">Descripción</label>
										<textarea class="form-control" id="torneo_descripcion" name="torneo_descripcion" rows="2" maxlength="300"><?php echo $h($torneo_descripcion); ?></textarea>
									</div>
								</div>
								<p class="small text-body-secondary mt-2 mb-0"><span class="text-danger">*</span> Campos obligatorios.</p>
							</div>
						</div>
					</div>

					<?php echo ds_acciones_form(APP_URL . 'torneoList/', ['limpiar' => true, 'volver' => $editando ? 'Cancelar' : 'Volver']); ?>
				</form>

				<!-- =================== 2. Torneos ingresados =================== -->
				<div class="card ds-crud__lista" id="torneos-ingresados">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-trophy me-2" aria-hidden="true"></i>Torneos ingresados
							<span class="badge text-bg-secondary ms-1"><?php echo $totalTorneos; ?></span>
						</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Torneo</th>
									<th>Fechas</th>
									<th class="text-center">Equipos</th>
									<th class="ds-col-opcional">Organizador</th>
									<th>Estado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filasTorneos; ?>
							</tbody>
						</table>
					</div>
				</div>

			</div><!-- /.container-fluid -->
		</section>
		<!-- /.content -->

      </div>
      <!-- /.vista -->

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>
    <!-- ./wrapper -->
	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			/* Sin el plugin responsive: escondía columnas tras un ▶ y, en el
			   teléfono, también los botones de cada torneo. Ahora la tabla tiene
			   cinco columnas que caben en escritorio y, en el teléfono, cada fila
			   se presenta como ficha (futbol.css, .ds-crud). */
			new DataTable("#example1", {
				autoWidth: false,
				lengthChange: false,
				pageLength: 10,
				order: [[1, 'desc']],          /* los más recientes primero */
				columnDefs: [
					/* Como texto: data-order lleva la fecha ISO, que se ordena bien
					   como cadena, y así DataTables no las alinea a la derecha. */
					{ targets: [1, 4], type: 'string' },
					/* Equipos: se ordena por el número (data-order), no por el texto. */
					{ targets: 2, type: 'num', className: 'text-center', searchable: false },
					{ targets: 5, orderable: false, searchable: false }
				],
				layout: {
					topStart: null,
					topEnd: 'search',
					bottomStart: 'info',
					bottomEnd: { paging: { firstLast: false } }
				},
				language: {
					emptyTable: "Aún no hay torneos ingresados",
					info: "_START_ a _END_ de _TOTAL_ torneos",
					infoEmpty: "Sin torneos",
					infoFiltered: "(de _MAX_)",
					search: "",
					searchPlaceholder: "Buscar torneo, ciudad, lugar…",
					zeroRecords: "Ningún torneo coincide con la búsqueda",
					paginate: { next: "Siguiente", previous: "Anterior" },
					aria: {
						sortAscending: ": ordenar de forma ascendente",
						sortDescending: ": ordenar de forma descendente"
					}
				}
			});

			/* La finalización no puede ser anterior al inicio: el servidor lo
			   rechaza igual (validarFechasTorneo), pero así el selector de
			   fecha ni siquiera lo ofrece. */
			var inicio = document.getElementById('torneo_fechainicio');
			var fin = document.getElementById('torneo_fechafin');
			function limitarFin() { fin.min = inicio.value || ''; }
			inicio.addEventListener('change', limitarFin);
			limitarFin();
		});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>
