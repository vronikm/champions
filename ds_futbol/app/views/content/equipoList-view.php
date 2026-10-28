<?php
	use app\controllers\equipoController;
	$insEquipo = new equipoController();

	$equipo_torneoid = (int)(($url[1] ?? "") != "" ? $url[1] : 0);
	$equipo_id       = (int)(($url[2] ?? "") != "" ? $url[2] : 0);

	/* Sin un torneo válido no hay a qué inscribir equipos: se vuelve a la
	   lista de torneos en vez de pintar un título y un formulario sin dueño
	   (antes $torneo_nombre quedaba sin definir). */
	$torneo_nombre = '';
	if ($equipo_torneoid > 0) {
		$nombreTorneo = $insEquipo->BuscarTorneoEquipo($equipo_torneoid);
		if ($nombreTorneo->rowCount() == 1) {
			$torneo_nombre = $nombreTorneo->fetch()['torneo_nombre'];
		}
	}
	if ($torneo_nombre === '') {
		header("Location: " . APP_URL . "torneoList/");
		exit();
	}

	$foto = APP_URL.'app/views/imagenes/fotos/equipos/equipo_default.jpg';

	/* Valores del formulario: alta por omisión; con id de equipo, edición. */
	$modulo_equipo     = 'registrar';
	$equipo_profesorid = 0;
	$equipo_nombre     = '';
	$equipo_categoria  = '';
	$equipo_sedeid     = null;   /* null: sin elegir; 0: todas las sedes (migración 076) */
	$equipo_valor      = '';

	if ($equipo_id > 0) {
		$datosEquipo = $insEquipo->BuscarEquipo($equipo_id);
		if ($datosEquipo->rowCount() == 1) {
			$datosEquipo = $datosEquipo->fetch();
			if ($datosEquipo['equipo_foto'] != "") {
				$foto = APP_URL.'app/views/imagenes/fotos/equipos/'.$datosEquipo['equipo_foto'];
			}
			$modulo_equipo     = 'actualizar';
			$equipo_profesorid = (int)$datosEquipo['equipo_profesorid'];
			$equipo_nombre     = $datosEquipo['equipo_nombre'];
			$equipo_categoria  = $datosEquipo['equipo_categoria'];
			$equipo_sedeid     = (int)$datosEquipo['sede_id'];
			$equipo_valor      = $datosEquipo['equipo_valorinscripcion'] !== null ? number_format((float)$datosEquipo['equipo_valorinscripcion'], 2, '.', '') : '';
		} else {
			$equipo_id = 0;
		}
	}

	$editando = $modulo_equipo === 'actualizar';

	/* Escapado de salida: decodificar antes porque limpiarHtml() guarda los
	   textos ya escapados (ver torneoList-view.php). */
	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

	$filasEquipos = $insEquipo->listarEquiposTorneo($equipo_torneoid);
	$totalEquipos = substr_count($filasEquipos, '<tr>');
	$urlLista     = APP_URL . 'equipoList/' . $equipo_torneoid . '/';
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Equipos';
	$extras      = array (0 => 'datatables',1 => 'swal',);
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
				<div class="col-sm-7">
					<h4 class="m-0">Equipos</h4>
					<p class="small text-body-secondary mb-0"><i class="fas fa-trophy me-1" aria-hidden="true"></i><?php echo $h($torneo_nombre); ?></p>
				</div>
				<div class="col-sm-5">
					<ol class="breadcrumb float-sm-end">
						<li class="breadcrumb-item"><a href="<?php echo APP_URL."dashboard/" ?>">Inicio</a></li>
						<li class="breadcrumb-item"><a href="<?php echo APP_URL."torneoList/" ?>">Torneos</a></li>
						<li class="breadcrumb-item active" aria-current="page">Equipos</li>
					</ol>
				</div>
			</div>

			<?php /* Atajos a cada sección en el teléfono (ver torneoList). */ ?>
			<nav class="ds-crud__atajos d-md-none" aria-label="Secciones de la página">
				<a href="#equipo-form" class="btn btn-sm">
					<i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus'; ?> me-1" aria-hidden="true"></i><?php echo $editando ? 'Editar equipo' : 'Nuevo equipo'; ?>
				</a>
				<a href="#equipos-inscritos" class="btn btn-sm">
					<i class="fas fa-list me-1" aria-hidden="true"></i>Equipos inscritos <span class="badge text-bg-secondary ms-1"><?php echo $totalEquipos; ?></span>
				</a>
			</nav>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== 1. Ingreso / edición =================== -->
				<form class="card ds-crud__form FormularioAjax<?php echo $editando ? ' ds-crud__form--edicion' : ''; ?>"
				      id="equipo-form" action="<?php echo APP_URL; ?>app/ajax/equipoAjax.php" method="POST"
				      autocomplete="off" enctype="multipart/form-data">
					<input type="hidden" name="modulo_equipo" value="<?php echo $modulo_equipo; ?>">
					<input type="hidden" name="equipo_torneoid" value="<?php echo $equipo_torneoid; ?>">
					<input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">

					<div class="card-header">
						<h3 class="card-title">
							<i class="fas <?php echo $editando ? 'fa-pen' : 'fa-plus-circle'; ?> me-2" aria-hidden="true"></i><?php
							echo $editando ? 'Editar equipo' : 'Ingreso de nuevo equipo'; ?>
						</h3>
						<?php if ($editando): ?>
							<span class="badge text-bg-warning ms-2 ds-crud__editando"><?php echo $h($equipo_nombre); ?></span>
						<?php endif; ?>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>

					<div class="card-body">
						<div class="row g-3">
							<!-- Escudo -->
							<div class="col-12 col-lg-3 col-xl-2">
								<span class="form-label d-block">Escudo</span>
								<div class="fileinput fileinput-new ds-crud__foto" data-provides="fileinput">
									<div class="fileinput-new thumbnail" data-trigger="fileinput"><img src="<?php echo $h($foto); ?>" alt="Escudo del equipo"></div>
									<div class="fileinput-preview fileinput-exists thumbnail"></div>
									<div class="ds-crud__foto-botones">
										<span class="bton bton-white bton-file">
											<span class="fileinput-new">Seleccionar imagen</span>
											<span class="fileinput-exists">Cambiar</span>
											<input type="file" name="equipo_foto" id="foto" accept="image/jpeg,image/png" aria-label="Escudo del equipo">
										</span>
										<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Quitar</a>
										<small class="text-body-secondary d-block mt-1">JPG o PNG.</small>
									</div>
								</div>
							</div>

							<!-- Datos -->
							<div class="col-12 col-lg-9 col-xl-10">
								<div class="row g-3">
									<div class="col-12 col-md-6">
										<label for="equipo_nombre" class="form-label">Nombre del equipo <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="text" class="form-control" id="equipo_nombre" name="equipo_nombre" maxlength="50" required value="<?php echo $h($equipo_nombre); ?>">
									</div>
									<div class="col-12 col-md-6">
										<label for="equipo_categoria" class="form-label">Categoría <span class="text-danger" aria-hidden="true">*</span></label>
										<input type="text" class="form-control" id="equipo_categoria" name="equipo_categoria" maxlength="50" required
										       placeholder="Ej.: Sub 12 o 2014-2015" value="<?php echo $h($equipo_categoria); ?>">
									</div>
									<div class="col-12 col-md-6">
										<label for="equipo_sedeid" class="form-label">Sede <span class="text-danger" aria-hidden="true">*</span></label>
										<?php /* El marcador va con value="" para que «required» lo
										         rechace en el navegador; el servidor lo vuelve a
										         comprobar ((int) <= 0). */ ?>
										<?php /* «Todas las sedes»: un equipo con niños de todas las sedes
										         (se guarda como sede 0). La ofrece listarOptionSede a quien
										         ve todas las sedes. */ ?>
										<select class="form-select" id="equipo_sedeid" name="equipo_sedeid" required aria-describedby="equipo_sede_ayuda">
											<option value=""<?php echo $equipo_sedeid === null ? ' selected' : ''; ?>>Seleccione una sede</option>
											<?php echo $insEquipo->listarOptionSede($equipo_sedeid); ?>
										</select>
										<div class="form-text" id="equipo_sede_ayuda">«Todas las sedes» si el equipo junta niños de varias sedes.</div>
									</div>
									<div class="col-12 col-md-6">
										<label for="equipo_profesorid" class="form-label">Profesor a cargo <span class="text-danger" aria-hidden="true">*</span></label>
										<select class="form-select" id="equipo_profesorid" name="equipo_profesorid" required>
											<option value=""<?php echo $equipo_profesorid > 0 ? '' : ' selected'; ?>>Seleccione un profesor</option>
											<?php echo $insEquipo->listarResponsable($equipo_profesorid); ?>
										</select>
									</div>
									<div class="col-12 col-md-6">
										<label for="equipo_valorinscripcion" class="form-label">Valor de la inscripción por alumno</label>
										<div class="input-group">
											<span class="input-group-text">$</span>
											<input type="text" class="form-control" id="equipo_valorinscripcion" name="equipo_valorinscripcion"
											       inputmode="decimal" maxlength="9" pattern="\d{1,6}([.,]\d{1,2})?" placeholder="0.00"
											       value="<?php echo $h($equipo_valor); ?>" aria-describedby="equipo_valor_ayuda">
										</div>
										<div class="form-text" id="equipo_valor_ayuda">Lo que se cobra a cada alumno que juega el torneo con este equipo. Déjelo vacío si aún no está definido.</div>
									</div>
								</div>
								<p class="small text-body-secondary mt-2 mb-0"><span class="text-danger">*</span> Campos obligatorios.</p>
							</div>
						</div>
					</div>

					<?php /* En edición, «Cancelar» vuelve al alta de este torneo; en
					         alta, «Volver» lleva a la lista de torneos. Antes Volver
					         recargaba esta misma página y había además un «Regresar»
					         al pie que hacía lo mismo. */ ?>
					<?php echo ds_acciones_form($editando ? $urlLista : APP_URL . 'torneoList/',
						['limpiar' => true, 'volver' => $editando ? 'Cancelar' : 'Torneos']); ?>
				</form>

				<!-- =================== 2. Equipos inscritos =================== -->
				<div class="card ds-crud__lista" id="equipos-inscritos">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-users me-2" aria-hidden="true"></i>Equipos inscritos
							<span class="badge text-bg-secondary ms-1"><?php echo $totalEquipos; ?></span>
						</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Equipo</th>
									<th>Profesor a cargo</th>
									<th class="ds-col-opcional">Inscripción</th>
									<th>Estado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filasEquipos; ?>
							</tbody>
						</table>
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
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			/* Sin el plugin responsive, como en Torneos: en el teléfono cada
			   fila es una ficha (futbol.css, .ds-crud) con los botones a la
			   vista, en vez de esconderlos tras un ▶. */
			new DataTable("#example1", {
				autoWidth: false,
				lengthChange: false,
				pageLength: 10,
				order: [],                      /* el del servidor: categoría y nombre */
				columnDefs: [
					{ targets: 2, type: 'num' },
					{ targets: 3, type: 'string' },
					{ targets: 4, orderable: false, searchable: false }
				],
				layout: {
					topStart: null,
					topEnd: 'search',
					bottomStart: 'info',
					bottomEnd: { paging: { firstLast: false } }
				},
				language: {
					emptyTable: "Este torneo aún no tiene equipos inscritos",
					info: "_START_ a _END_ de _TOTAL_ equipos",
					infoEmpty: "Sin equipos",
					infoFiltered: "(de _MAX_)",
					search: "",
					searchPlaceholder: "Buscar equipo, categoría, profesor…",
					zeroRecords: "Ningún equipo coincide con la búsqueda",
					paginate: { next: "Siguiente", previous: "Anterior" },
					aria: {
						sortAscending: ": ordenar de forma ascendente",
						sortDescending: ": ordenar de forma descendente"
					}
				}
			});
		});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>
