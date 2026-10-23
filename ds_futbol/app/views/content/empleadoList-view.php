<?php
	use app\controllers\empleadoController;
	$insempleado = new empleadoController();

	$empleadoid		= (int)($url[1] ?? 0);
	$foto 			= ds_recurso('ds_futbol/app/views/dist/img/default.png');
	$empleado_sexoM = "";
	$empleado_sexoF	= "";

	/* Valores del formulario. Por omisión, alta de un empleado nuevo; con un
	   id válido en la URL, edición de ese empleado. */
	$modulo_empleado 				= 'registrar';
	$empleado_sedeid		  		= '';
	$empleado_tipoidentificacion	= '';
	$empleado_identificacion		= '';
	$empleado_nombre				= '';
	$empleado_correo				= '';
	$empleado_celular				= '';
	$empleado_direccion				= '';
	$empleado_tipopersonalid		= '';
	$empleado_especialidadid 		= '';
	$empleado_fechaingreso			= '';
	$empleado_sueldo				= '';

	$datosempleado = $empleadoid > 0 ? $insempleado->seleccionarDatos("Unico","sujeto_empleado","empleado_id",$empleadoid) : null;
	if($datosempleado && $datosempleado->rowCount()==1){
		$datosempleado=$datosempleado->fetch();
		if ($datosempleado['empleado_foto']!=""){
			$foto = media_url('empleado', $datosempleado['empleado_foto']);
		}
		if ($datosempleado['empleado_genero']=='M'){
			$empleado_sexoM = "checked";
		}else{
			$empleado_sexoF = "checked";
		}

		$modulo_empleado 				= 'actualizar';
		$empleado_sedeid 				= $datosempleado['empleado_sedeid'];
		$empleado_tipoidentificacion 	= $datosempleado['empleado_tipoidentificacion'];
		$empleado_identificacion 	  	= $datosempleado['empleado_identificacion'];
		$empleado_nombre		  		= $datosempleado['empleado_nombre'];
		$empleado_correo 			  	= $datosempleado['empleado_correo'];
		$empleado_celular 			  	= $datosempleado['empleado_celular'];
		$empleado_direccion 		  	= $datosempleado['empleado_direccion'];
		$empleado_tipopersonalid 		= $datosempleado['empleado_tipopersonalid'];
		$empleado_especialidadid 		= $datosempleado['empleado_especialidadid'];
		$empleado_fechaingreso			= $datosempleado['empleado_fechaingreso'];
		$empleado_sueldo				= $datosempleado['empleado_sueldo'];
	}else{
		$empleadoid = 0;
	}

	$editando = $modulo_empleado === 'actualizar';

	/* limpiarHtml() guarda lo escrito ya escapado: se decodifica antes de
	   escaparlo para no mostrar «&quot;» ni escaparlo dos veces al guardar. */
	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

	/* El listado se genera antes para contar los empleados en la cabecera. */
	$filasEmpleados = $insempleado->listarEmpleados();
	$totalEmpleados = substr_count($filasEmpleados, '<tr>');
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Empleados';
	$extras      = array (0 => 'datatables',);
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
						<h4 class="m-0">Empleados</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Empleados</li>
						</ol>
					</div>
				</div>

				<?php /* En el teléfono las dos secciones quedan una debajo de otra y
				         el formulario es largo: estos atajos llevan a cada una. */ ?>
				<nav class="ds-crud__atajos d-md-none" aria-label="Secciones de la página">
					<a href="#empleado-form" class="btn btn-sm btn-outline-secondary">
						<i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus'; ?> me-1" aria-hidden="true"></i><?php echo $editando ? 'Editar empleado' : 'Nuevo empleado'; ?>
					</a>
					<a href="#empleados-ingresados" class="btn btn-sm btn-outline-secondary">
						<i class="fas fa-list me-1" aria-hidden="true"></i>Empleados ingresados <span class="badge text-bg-secondary ms-1"><?php echo $totalEmpleados; ?></span>
					</a>
				</nav>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== 1. Ingreso / edición =================== -->
				<form class="card ds-crud__form FormularioAjax<?php echo $editando ? ' ds-crud__form--edicion' : ''; ?>"
				      id="empleado-form" action="<?php echo APP_URL; ?>app/ajax/empleadoAjax.php" method="POST"
				      autocomplete="off" enctype="multipart/form-data">
					<input type="hidden" name="modulo_empleado" value="<?php echo $modulo_empleado; ?>">
					<input type="hidden" name="empleado_id" value="<?php echo (int)$empleadoid; ?>">

					<div class="card-header">
						<h3 class="card-title">
							<i class="fas <?php echo $editando ? 'fa-pen' : 'fa-user-plus'; ?> me-2" aria-hidden="true"></i><?php
							echo $editando ? 'Editar empleado' : 'Ingreso de nuevo empleado'; ?>
						</h3>
						<?php if ($editando): ?>
							<span class="badge text-bg-warning ms-2 ds-crud__editando"><?php echo $h($empleado_nombre); ?></span>
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
									<div class="fileinput-new thumbnail" data-trigger="fileinput"><img src="<?php echo $h($foto); ?>" alt="Foto del empleado"></div>
									<div class="fileinput-preview fileinput-exists thumbnail"></div>
									<div class="ds-crud__foto-botones">
										<span class="bton bton-white bton-file">
											<span class="fileinput-new">Seleccionar foto</span>
											<span class="fileinput-exists">Cambiar</span>
											<input type="file" name="empleado_foto" id="foto" accept="image/*" aria-label="Foto del empleado">
										</span>
										<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Quitar</a>
									</div>
								</div>
							</div>

							<!-- Datos -->
							<div class="col-12 col-lg-9 col-xl-10">
								<div class="row g-3">
									<div class="col-12 col-sm-6 col-xl-4">
										<label for="empleado_nombre" class="form-label">Nombre y apellido <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="text" class="form-control" id="empleado_nombre" name="empleado_nombre" required value="<?php echo $h($empleado_nombre); ?>">
									</div>
									<div class="col-6 col-sm-6 col-xl-2">
										<label for="empleado_tipoidentificacion" class="form-label">Tipo de identificación</label>
										<select id="empleado_tipoidentificacion" class="form-select" name="empleado_tipoidentificacion">
											<?php echo $insempleado->OptionTipoIdentificacion($empleado_tipoidentificacion); ?>
										</select>
									</div>
									<div class="col-6 col-sm-6 col-xl-3">
										<label for="empleado_identificacion" class="form-label">Identificación <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="text" class="form-control" id="empleado_identificacion" name="empleado_identificacion" inputmode="numeric" required value="<?php echo $h($empleado_identificacion); ?>">
									</div>
									<div class="col-12 col-sm-6 col-xl-3">
										<label for="empleado_sedeid" class="form-label">Sede</label>
										<select class="form-select" id="empleado_sedeid" name="empleado_sedeid">
											<?php echo $insempleado->listarOptionSede($empleado_sedeid); ?>
										</select>
									</div>
									<div class="col-12 col-sm-6 col-xl-4">
										<label for="empleado_correo" class="form-label">Correo <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="email" class="form-control" id="empleado_correo" name="empleado_correo" inputmode="email" required value="<?php echo $h($empleado_correo); ?>">
									</div>
									<?php /* Antes, una máscara 0999999999 (jQuery Inputmask): el
									         teclado numérico y el patrón dicen lo mismo sin librería. */ ?>
									<div class="col-6 col-sm-6 col-xl-2">
										<label for="empleado_celular" class="form-label">Celular <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="tel" class="form-control" id="empleado_celular" name="empleado_celular" inputmode="numeric" maxlength="10" pattern="0\d{9}" title="10 dígitos, empieza por 0" placeholder="09…" required value="<?php echo $h($empleado_celular); ?>">
									</div>
									<div class="col-6 col-sm-6 col-xl-3">
										<label for="empleado_fechaingreso" class="form-label">Fecha de ingreso</label>
										<input type="date" class="form-control" name="empleado_fechaingreso" id="empleado_fechaingreso" value="<?php echo $h($empleado_fechaingreso); ?>">
									</div>
									<?php /* El servidor quita «$», comas y espacios: basta un
									         número con decimales. */ ?>
									<div class="col-6 col-sm-6 col-xl-3">
										<label for="empleado_sueldo" class="form-label">Honorarios <span class="text-danger" aria-hidden="true">*</span></label>
										<div class="input-group">
											<span class="input-group-text">$</span>
											<input type="text" class="form-control" id="empleado_sueldo" name="empleado_sueldo" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" title="Un valor como 450 o 450.50" required value="<?php echo $h($empleado_sueldo); ?>">
										</div>
									</div>
									<div class="col-6 col-sm-6 col-xl-4">
										<label for="empleado_tipopersonalid" class="form-label">Tipo de empleado</label>
										<select class="form-select" id="empleado_tipopersonalid" name="empleado_tipopersonalid">
											<?php echo $insempleado->listarTipoPersonal($empleado_tipopersonalid); ?>
										</select>
									</div>
									<div class="col-12 col-sm-6 col-xl-5">
										<label for="empleado_especialidadid" class="form-label">Especialidad <span class="small text-body-secondary">(profesores)</span></label>
										<select class="form-select" id="empleado_especialidadid" name="empleado_especialidadid">
											<?php echo $insempleado->OptionEspecialidad($empleado_especialidadid); ?>
										</select>
									</div>
									<div class="col-12 col-xl-9">
										<label for="empleado_direccion" class="form-label">Dirección <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="text" class="form-control" id="empleado_direccion" name="empleado_direccion" required value="<?php echo $h($empleado_direccion); ?>">
									</div>
									<div class="col-12 col-xl-3">
										<span class="form-label d-block" id="empleado_genero_etq">Género <span class="text-danger" aria-hidden="true">*</span></span>
										<div class="d-flex flex-wrap gap-4" role="radiogroup" aria-labelledby="empleado_genero_etq">
											<div class="form-check">
												<input class="form-check-input" type="radio" id="empleado_generoM" name="empleado_genero" value="M" <?php echo $empleado_sexoM; ?> required>
												<label class="form-check-label" for="empleado_generoM">Masculino</label>
											</div>
											<div class="form-check">
												<input class="form-check-input" type="radio" id="empleado_generoF" name="empleado_genero" value="F" <?php echo $empleado_sexoF; ?>>
												<label class="form-check-label" for="empleado_generoF">Femenino</label>
											</div>
										</div>
									</div>
								</div>
								<p class="small text-body-secondary mt-2 mb-0"><span class="text-danger">*</span> Campos obligatorios.</p>
							</div>
						</div>
					</div>

					<?php echo ds_acciones_form(APP_URL . 'empleadoList/', ['limpiar' => true, 'volver' => $editando ? 'Cancelar' : 'Volver']); ?>
				</form>

				<!-- =================== 2. Empleados ingresados =================== -->
				<div class="card ds-crud__lista" id="empleados-ingresados">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-id-badge me-2" aria-hidden="true"></i>Empleados ingresados
							<span class="badge text-bg-secondary ms-1"><?php echo $totalEmpleados; ?></span>
						</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Empleado</th>
									<th class="ds-col-opcional">Sede</th>
									<th>Contacto</th>
									<th>Acceso al sistema</th>
									<th>Estado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filasEmpleados; ?>
							</tbody>
						</table>
					</div>
				</div>

			</div>
		</section>

      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive ni botones de exportar -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/*
		| Sin la extensión Responsive: en el teléfono cada empleado es una
		| ficha (futbol.css, .ds-crud). «Mostrar N» elige cuántos por página y
		| se recuerda. Primero los activos.
		*/
		var CLAVE = 'ds-empleados-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 10;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable("#example1", {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [[4, 'asc'], [0, 'asc']],
			columnDefs: [
				{ targets: 4, type: 'string' },        /* data-order: 0 activo, 1 inactivo */
				{ targets: 5, orderable: false, searchable: false }
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "Aún no hay empleados ingresados",
				info: "_START_ a _END_ de _TOTAL_ empleados",
				infoEmpty: "Sin empleados",
				infoFiltered: "(de _MAX_)",
				lengthMenu: "Mostrar _MENU_",
				search: "",
				searchPlaceholder: "Buscar nombre, cédula, sede…",
				zeroRecords: "Ningún empleado coincide con la búsqueda",
				paginate: { next: "Siguiente", previous: "Anterior" },
				aria: {
					sortAscending: ": ordenar de forma ascendente",
					sortDescending: ": ordenar de forma descendente"
				}
			}
		});
		tabla.on('length', function (e, s, nuevo) {
			try { localStorage.setItem(CLAVE, String(nuevo)); } catch (err) {}
		});

		/*
		| La especialidad sólo aplica a los profesores (TPP).
		|
		| ANTES el campo nacía deshabilitado y sólo se habilitaba al CAMBIAR el
		| tipo. Al editar a un profesor seguía deshabilitado, un select
		| deshabilitado no se envía, y el servidor guardaba la especialidad
		| vacía: se perdía al guardar cualquier otro cambio.
		*/
		var tipo = document.getElementById('empleado_tipopersonalid');
		var especialidad = document.getElementById('empleado_especialidadid');
		function alternarEspecialidad() { especialidad.disabled = tipo.value !== 'TPP'; }
		tipo.addEventListener('change', alternarEspecialidad);
		alternarEspecialidad();
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>
