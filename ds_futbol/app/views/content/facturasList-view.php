<?php
	use app\controllers\facturasController;
	$insFactura = new facturasController();

	/* Servicio bajado por mantenimiento (Core › Facturación electrónica). */
	if (!$insFactura->servicioFacturacionActivo()) { require "app/views/inc/facturacion-mantenimiento.php"; }

	/*
	| Emitir facturas: elegir al alumno cuyo representante se factura.
	|
	| Los criterios van por GET (recargar o volver atrás conserva la búsqueda)
	| y viajan a la consulta como parámetros ligados; el escape va en la
	| salida. Al abrir ya se listan los alumnos de las sedes del usuario:
	| antes no salía nadie hasta elegir una sede, y «Todas» no respetaba las
	| sedes asignadas. Primero, quien tiene pagos por facturar.
	|
	| Lo ya emitido está en «Facturas emitidas»; la configuración del SRI, en
	| Core › Facturación electrónica.
	*/
	$criterio = static fn(string $clave) => trim((string)($_GET[$clave] ?? ''));

	$busqueda  = mb_substr($criterio('q'), 0, 80);
	$anio      = preg_match('/^\d{4}$/', $criterio('anio')) ? $criterio('anio') : '';
	$sedeId    = (int)$criterio('sede');
	$situacion = array_key_exists($criterio('situacion'), $insFactura->situacionesFacturacion()) ? $criterio('situacion') : '';

	$opcionesSede = $insFactura->opcionesSedeFacturas($sedeId);
	$ofrecerTodas = sedes_de_la_sesion() === null || substr_count($opcionesSede, '<option') > 1;

	$alumnos = $insFactura->alumnosParaFacturar(['busqueda' => $busqueda, 'anio' => $anio, 'sede' => $sedeId, 'situacion' => $situacion]);
	$porFacturar = count(array_filter($alumnos, static fn($a) => (int)$a['pendientes'] > 0));
	$sri = sri_disponible();
	$puedeEmitir = puede('crear', 'facturasList');

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$hayFiltro = $busqueda !== '' || $anio !== '' || $sedeId > 0 || $situacion !== '';
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Emitir facturas';
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
						<h4 class="m-0">Emitir facturas</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Facturación</li>
						</ol>
					</div>
				</div>
				<nav class="ds-crud__atajos" aria-label="Facturación">
					<?php if (puede('ver', 'facturasEmitidas')): ?>
						<a href="<?php echo APP_URL; ?>facturasEmitidas/" class="btn btn-sm btn-outline-secondary">
							<i class="fas fa-receipt me-1" aria-hidden="true"></i>Facturas emitidas
						</a>
					<?php endif; ?>
					<?php if (es_superadministrador()): ?>
						<a href="<?php echo DS_HUB_URL; ?>ds_core/admin/facturacionConfigSri/" class="btn btn-sm btn-outline-secondary">
							<i class="fas fa-cog me-1" aria-hidden="true"></i>Configuración SRI
						</a>
					<?php endif; ?>
				</nav>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud ds-fact">

				<?php if (!$sri['ok']): ?>
					<div class="alert alert-warning d-flex gap-2" role="alert">
						<i class="fas fa-exclamation-triangle mt-1" aria-hidden="true"></i>
						<div>
							<strong>La facturación electrónica no está lista:</strong> <?php echo $h(implode('; ', $sri['faltan'])); ?>.
							<?php if (es_superadministrador()): ?>
								<a href="<?php echo DS_HUB_URL; ?>ds_core/admin/facturacionConfigSri/" class="alert-link">Completar la configuración</a>.
							<?php else: ?>
								Avise al administrador del sistema.
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- =================== Criterios de búsqueda =================== -->
				<form method="GET" action="<?php echo APP_URL; ?>facturasList/" autocomplete="off" class="card ds-alumnos__filtros">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-12 col-md-6 col-xl">
								<label for="q" class="form-label">Alumno</label>
								<input type="search" class="form-control" id="q" name="q" maxlength="80" placeholder="Nombre, apellido o cédula" value="<?php echo $h($busqueda); ?>">
							</div>
							<div class="col-6 col-md-3 col-xl-2">
								<label for="anio" class="form-label">Categoría</label>
								<input type="text" class="form-control" id="anio" name="anio" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="Año" title="Año de nacimiento, por ejemplo 2015" value="<?php echo $h($anio); ?>">
							</div>
							<div class="col-6 col-md-3 col-xl-2">
								<label for="sede" class="form-label">Sede</label>
								<select class="form-select" id="sede" name="sede">
									<?php if ($ofrecerTodas): ?>
										<option value="0" <?php echo $sedeId === 0 ? 'selected' : ''; ?>>Todas</option>
									<?php endif; ?>
									<?php echo $opcionesSede; ?>
								</select>
							</div>
							<div class="col-12 col-md-6 col-xl-3">
								<label for="situacion" class="form-label">Situación</label>
								<select class="form-select" id="situacion" name="situacion">
									<?php foreach ($insFactura->situacionesFacturacion() as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>" <?php echo $situacion === $valor ? 'selected' : ''; ?>><?php echo $texto; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-12 col-md-6 col-xl-auto d-flex align-items-end gap-2">
								<div class="flex-grow-1"><?php echo ds_boton_buscar(); ?></div>
								<?php if ($hayFiltro): ?>
									<a href="<?php echo APP_URL; ?>facturasList/" class="btn btn-secondary ds-fact__limpiar" title="Quitar los filtros">
										<i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Quitar los filtros</span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Alumnos =================== -->
				<div class="card ds-crud__lista" id="alumnos-facturar">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-user-graduate me-2" aria-hidden="true"></i>Alumnos
							<span class="badge text-bg-secondary ms-1"><?php echo count($alumnos); ?></span>
							<?php if ($porFacturar > 0): ?>
								<span class="badge text-bg-info ms-1" title="Alumnos con pagos cobrados que aún no están en una factura"><?php echo $porFacturar; ?> por facturar</span>
							<?php endif; ?>
						</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<table id="tablaAlumnos" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Alumno</th>
									<th class="ds-col-opcional">Sede</th>
									<th>Representante</th>
									<th>Pagos</th>
									<th>Situación</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($alumnos as $a):
								$pend = (int)$a['pendientes'];
								$fact = (int)$a['facturados'];
								if ($pend > 0)      { [$sit, $clase, $orden] = ['Por facturar', 'text-bg-info', 0]; }
								elseif ($fact > 0)  { [$sit, $clase, $orden] = ['Facturado', 'text-bg-success', 1]; }
								else                { [$sit, $clase, $orden] = ['Sin pagos', 'text-bg-secondary', 2]; }
								$nac = $a['alumno_fechanacimiento'] ? date('Y', strtotime((string)$a['alumno_fechanacimiento'])) : '';
							?>
								<tr>
									<td class="ds-ficha__principal">
										<div class="ds-ficha__nombre"><?php echo $h($a['apellidos'] . ' ' . $a['nombres']); ?></div>
										<div class="ds-ficha__meta"><?php echo $h($a['alumno_identificacion']); ?><?php echo $nac ? ' · Cat. ' . $nac : ''; ?></div>
									</td>
									<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Sede"><?php echo $h($a['sede_nombre'] ?? ''); ?></td>
									<td class="ds-ficha__dato" data-etiqueta="Representante"><?php echo $h($a['representante']); ?></td>
									<td class="ds-ficha__dato" data-etiqueta="Pagos" data-order="<?php echo $pend; ?>">
										<span title="Pagos cobrados sin factura"><?php echo $pend; ?> por facturar</span>
										<span class="text-body-secondary"> · <?php echo $fact; ?> facturado<?php echo $fact === 1 ? '' : 's'; ?></span>
									</td>
									<td class="ds-ficha__estado" data-order="<?php echo $orden; ?>">
										<span class="badge <?php echo $clase; ?> ds-fact__situacion"><?php echo $sit; ?></span>
									</td>
									<td class="ds-ficha__acciones">
										<div class="ds-ficha__botones">
											<?php if ($puedeEmitir): ?>
												<a href="<?php echo APP_URL; ?>facturasNew/<?php echo (int)$a['alumno_id']; ?>/"
												   class="btn btn-sm <?php echo $pend > 0 ? 'btn-primary' : 'btn-secondary'; ?> ds-ficha__primario">
													<i class="fas <?php echo $pend > 0 ? 'fa-file-invoice-dollar' : 'fa-folder-open'; ?> me-1" aria-hidden="true"></i><?php echo $pend > 0 ? 'Facturar' : 'Ver pagos y facturas'; ?>
												</a>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<p class="small text-body-secondary mt-3 mb-0">
							Se listan los alumnos activos cuyo representante pidió factura. «Por facturar» son pagos cobrados que aún no están en una factura.
						</p>
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
	<!-- DataTables: sin Responsive ni botones de exportar; en el teléfono, fichas -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/* «Mostrar N» se recuerda. El orden del servidor (primero quien tiene
		   pagos por facturar) se respeta al abrir. */
		var CLAVE = 'ds-facturar-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable('#tablaAlumnos', {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [],
			columnDefs: [{ targets: 5, orderable: false, searchable: false }],
			layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: { paging: { firstLast: false } } },
			language: {
				emptyTable: 'Ningún alumno con estos criterios',
				info: '_START_ a _END_ de _TOTAL_ alumnos',
				infoEmpty: 'Sin alumnos',
				infoFiltered: '(de _MAX_)',
				lengthMenu: 'Mostrar _MENU_',
				search: '',
				searchPlaceholder: 'Filtrar esta lista…',
				zeroRecords: 'Ningún alumno coincide',
				paginate: { next: 'Siguiente', previous: 'Anterior' }
			}
		});
		tabla.on('length', function (e, s, nuevo) {
			try { localStorage.setItem(CLAVE, String(nuevo)); } catch (err) {}
		});
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
  </body>
</html>
