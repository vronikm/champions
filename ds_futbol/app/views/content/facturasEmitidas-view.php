<?php
	use app\controllers\facturasController;
	$insFactura = new facturasController();

	/* Servicio bajado por mantenimiento (Core › Facturación electrónica). */
	if (!$insFactura->servicioFacturacionActivo()) { require "app/views/inc/facturacion-mantenimiento.php"; }

	/*
	| Facturas emitidas: lo que ya salió hacia el SRI, con su estado, y las
	| acciones sobre cada comprobante (RIDE, XML, consultar en el SRI,
	| reenviar por correo, nuevo secuencial si fue devuelta). Réplica de la
	| pantalla de cdjg, con dos diferencias:
	|   · sólo se ven las facturas de alumnos de las sedes del usuario;
	|   · los filtros van por GET, así que recargar tras una acción —o
	|     compartir el enlace— conserva la búsqueda.
	| Emitir es otra pantalla (facturasList); la configuración del SRI vive
	| en Core › Facturación electrónica.
	*/
	$criterio = static fn(string $clave) => trim((string)($_GET[$clave] ?? ''));

	$estado = $criterio('estado');
	if (!in_array($estado, $insFactura->estadosFacturaSri(), true)) { $estado = ''; }
	$busqueda = mb_substr($criterio('q'), 0, 80);
	$sedeId   = (int)$criterio('sede');

	/* Por defecto, el año en curso: acota la consulta sin esconder nada reciente. */
	$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $criterio('desde')) ? $criterio('desde') : date('Y') . '-01-01';
	$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $criterio('hasta')) ? $criterio('hasta') : date('Y-m-d');
	if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

	$opcionesSede = $insFactura->opcionesSedeFacturas($sedeId);
	$ofrecerTodas = in_array((int)rol_actual(), [1, 2], true) || substr_count($opcionesSede, '<option') > 1;

	$facturas = $insFactura->listarFacturasEmitidas(['sede' => $sedeId, 'estado' => $estado, 'busqueda' => $busqueda, 'desde' => $desde, 'hasta' => $hasta]);
	$resumen  = $insFactura->resumenFacturasEmitidas($facturas);
	$sri      = sri_disponible();

	$urlAjax  = APP_URL . 'app/ajax/facturasAjax.php';
	$puedeEditar = puede('editar', 'facturasList');

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$dinero = static fn($v) => '$ ' . number_format((float)$v, 2, '.', ',');
	$fecha  = static fn($v) => $v ? date('d/m/Y', strtotime((string)$v)) : '';
	$hayFiltro = $estado !== '' || $busqueda !== '' || $sedeId > 0 || $criterio('desde') !== '' || $criterio('hasta') !== '';
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Facturas emitidas';
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
						<h4 class="m-0">Facturas emitidas</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>facturasList/">Facturación</a></li>
							<li class="breadcrumb-item active" aria-current="page">Emitidas</li>
						</ol>
					</div>
				</div>
				<nav class="ds-crud__atajos" aria-label="Facturación">
					<?php if (puede('ver', 'facturasList')): ?>
						<a href="<?php echo APP_URL; ?>facturasList/" class="btn btn-sm btn-outline-secondary">
							<i class="fas fa-file-invoice-dollar me-1" aria-hidden="true"></i>Emitir facturas
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
				<form method="GET" action="<?php echo APP_URL; ?>facturasEmitidas/" autocomplete="off" class="card ds-alumnos__filtros">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-6 col-md-4 col-xl-2">
								<label for="desde" class="form-label">Emitidas desde</label>
								<input type="date" class="form-control" id="desde" name="desde" value="<?php echo $h($desde); ?>">
							</div>
							<div class="col-6 col-md-4 col-xl-2">
								<label for="hasta" class="form-label">Hasta</label>
								<input type="date" class="form-control" id="hasta" name="hasta" value="<?php echo $h($hasta); ?>">
							</div>
							<div class="col-6 col-md-4 col-xl-2">
								<label for="estado" class="form-label">Estado</label>
								<select class="form-select" id="estado" name="estado">
									<option value="">Todos</option>
									<?php foreach ($insFactura->estadosFacturaSri() as $valor): ?>
										<option value="<?php echo $valor; ?>" <?php echo $estado === $valor ? 'selected' : ''; ?>><?php echo ucfirst(strtolower(str_replace('_', ' ', $valor))); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-md-4 col-xl-2">
								<label for="sede" class="form-label">Sede</label>
								<select class="form-select" id="sede" name="sede">
									<?php if ($ofrecerTodas): ?>
										<option value="0" <?php echo $sedeId === 0 ? 'selected' : ''; ?>>Todas</option>
									<?php endif; ?>
									<?php echo $opcionesSede; ?>
								</select>
							</div>
							<div class="col-12 col-md-8 col-xl">
								<label for="q" class="form-label">Cliente, alumno, número o clave</label>
								<input type="search" class="form-control" id="q" name="q" maxlength="80" placeholder="Nombre, cédula, 001-001-…" value="<?php echo $h($busqueda); ?>">
							</div>
							<div class="col-12 col-xl-auto d-flex align-items-end gap-2">
								<div class="flex-grow-1"><?php echo ds_boton_buscar(); ?></div>
								<?php if ($hayFiltro): ?>
									<a href="<?php echo APP_URL; ?>facturasEmitidas/" class="btn btn-secondary ds-fact__limpiar" title="Quitar los filtros">
										<i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Quitar los filtros</span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resumen =================== -->
				<div class="row g-2 g-md-3 ds-fact__resumen" aria-label="Resumen del periodo">
					<?php foreach ([
						['Emitidas', $resumen['total'], 'del ' . $fecha($desde) . ' al ' . $fecha($hasta), 'text-bg-primary', 'fa-file-invoice', ''],
						['Autorizadas', $resumen['autorizadas'], $dinero($resumen['monto_autorizado']), 'text-bg-success', 'fa-check-circle', 'AUTORIZADO'],
						['En proceso', $resumen['en_proceso'], 'por autorizar', $resumen['en_proceso'] > 0 ? 'text-bg-warning' : 'text-bg-secondary', 'fa-hourglass-half', ''],
						['Con problema', $resumen['con_problema'], $resumen['anuladas'] > 0 ? $resumen['anuladas'] . ' anulada(s)' : 'devueltas o con error', $resumen['con_problema'] > 0 ? 'text-bg-danger' : 'text-bg-secondary', 'fa-exclamation-triangle', ''],
					] as [$texto, $valor, $detalle, $clase, $icono]): ?>
						<div class="col-6 col-lg-3">
							<div class="info-box mb-0">
								<span class="info-box-icon <?php echo $clase; ?> shadow-sm"><i class="fas <?php echo $icono; ?>" aria-hidden="true"></i></span>
								<div class="info-box-content">
									<span class="info-box-text"><?php echo $texto; ?></span>
									<span class="info-box-number"><?php echo (int)$valor; ?></span>
									<span class="ds-fact__detalle"><?php echo $h($detalle); ?></span>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- =================== Comprobantes =================== -->
				<div class="card ds-crud__lista" id="facturas-emitidas">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-receipt me-2" aria-hidden="true"></i>Comprobantes
							<span class="badge text-bg-secondary ms-1"><?php echo count($facturas); ?></span>
						</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<table id="tablaFacturas" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Factura</th>
									<th>Cliente</th>
									<th class="ds-col-opcional">Alumno</th>
									<th class="text-end">Total</th>
									<th>Estado SRI</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($facturas as $f):
								$id  = (int)$f['id'];
								$est = (string)$f['estado_sri'];
								$correo = filter_var($f['cliente_email'] ?? '', FILTER_VALIDATE_EMAIL) ? (string)$f['cliente_email'] : '';
							?>
								<tr>
									<td class="ds-ficha__principal" data-order="<?php echo $h($f['fecha_emision'] . sprintf('%09d', $id)); ?>">
										<div class="ds-ficha__nombre"><?php echo $h($f['numero']); ?></div>
										<div class="ds-ficha__meta">
											<?php echo $fecha($f['fecha_emision']); ?>
											<?php if ((string)$f['ambiente'] !== '2'): ?>
												<span class="badge text-bg-warning ms-1" title="Emitida en el ambiente de pruebas: no tiene validez tributaria">Pruebas</span>
											<?php endif; ?>
										</div>
									</td>
									<td class="ds-ficha__dato" data-etiqueta="Cliente">
										<?php echo $h($f['cliente_razon_social']); ?>
										<span class="d-block small text-body-secondary"><?php echo $h($f['cliente_identificacion']); ?></span>
									</td>
									<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Alumno">
										<?php echo $h($f['alumno']); ?>
										<span class="d-block small text-body-secondary"><?php echo $h($f['sede_nombre'] ?? ''); ?></span>
									</td>
									<td class="ds-ficha__dato text-end ds-fact__total" data-etiqueta="Total" data-order="<?php echo (float)$f['total']; ?>">
										<?php echo $dinero($f['total']); ?>
									</td>
									<td class="ds-ficha__estado" data-search="<?php echo $h($est . ' ' . $f['clave_acceso'] . ' ' . $f['numero_autorizacion']); ?>">
										<span class="badge <?php echo $f['estado_clase']; ?>"><?php echo $h(ucfirst(strtolower(str_replace('_', ' ', $est)))); ?></span>
										<?php if ($f['mensaje_error'] !== '' && $f['grupo'] !== 'autorizada'): ?>
											<div class="small text-danger-emphasis ds-fact__error"><?php echo $h($f['mensaje_error']); ?></div>
										<?php elseif (!empty($f['fecha_autorizacion'])): ?>
											<div class="small text-body-secondary ds-fact__autorizada">Aut. <?php echo $fecha($f['fecha_autorizacion']); ?></div>
										<?php endif; ?>
									</td>
									<td class="ds-ficha__acciones">
										<div class="ds-ficha__botones">
											<a href="<?php echo $h($urlAjax . '?modulo_facturas=VER_RIDE&factura_id=' . $id); ?>" class="btn btn-sm btn-primary ds-ficha__primario" target="_blank" rel="noopener" title="Ver la factura (RIDE)">
												<i class="fas fa-file-invoice me-1" aria-hidden="true"></i>RIDE
											</a>
											<a href="<?php echo $h($urlAjax . '?modulo_facturas=DESCARGAR_XML&factura_id=' . $id); ?>" class="btn btn-sm btn-secondary ds-fact__icono" title="Descargar el XML" aria-label="Descargar el XML">
												<i class="fas fa-code" aria-hidden="true"></i>
											</a>
											<?php if ($est !== 'ANULADA'): ?>
												<button type="button" class="btn btn-sm btn-info ds-fact__icono" data-accion="CONSULTAR_FACTURA_SRI" data-id="<?php echo $id; ?>" data-numero="<?php echo $h($f['numero']); ?>" title="Consultar el estado en el SRI" aria-label="Consultar el estado en el SRI">
													<i class="fas fa-sync-alt" aria-hidden="true"></i>
												</button>
											<?php endif; ?>
											<?php if ($puedeEditar && $est === 'AUTORIZADO' && $correo !== ''): ?>
												<button type="button" class="btn btn-sm btn-success ds-fact__icono" data-accion="ENVIAR_FACTURA_CORREO" data-id="<?php echo $id; ?>" data-numero="<?php echo $h($f['numero']); ?>" data-email="<?php echo $h($correo); ?>" title="Enviar por correo a <?php echo $h($correo); ?>" aria-label="Enviar por correo">
													<i class="fas fa-envelope" aria-hidden="true"></i>
												</button>
											<?php endif; ?>
											<?php if ($puedeEditar && in_array($est, ['DEVUELTA', 'NO_AUTORIZADO'], true)): ?>
												<button type="button" class="btn btn-sm btn-warning ds-fact__icono" data-accion="REGENERAR_FACTURA_DEVUELTA" data-id="<?php echo $id; ?>" data-numero="<?php echo $h($f['numero']); ?>" title="Emitir de nuevo con otro secuencial" aria-label="Emitir de nuevo con otro secuencial">
													<i class="fas fa-redo" aria-hidden="true"></i>
												</button>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<p class="small text-body-secondary mt-3 mb-0">
							<i class="fas fa-sync-alt me-1" aria-hidden="true"></i>consulta el comprobante en el SRI y actualiza su estado aquí.
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
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/* Tabla en escritorio, fichas en el teléfono (futbol.css, .ds-crud).
		   «Mostrar N» se recuerda. La más reciente, primero. */
		var CLAVE = 'ds-facturas-emitidas-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable('#tablaFacturas', {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todas']],
			order: [[0, 'desc']],
			columnDefs: [{ targets: 5, orderable: false, searchable: false }],
			layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: { paging: { firstLast: false } } },
			language: {
				emptyTable: 'No hay facturas emitidas con estos criterios',
				info: '_START_ a _END_ de _TOTAL_ facturas',
				infoEmpty: 'Sin facturas',
				infoFiltered: '(de _MAX_)',
				lengthMenu: 'Mostrar _MENU_',
				search: '',
				searchPlaceholder: 'Filtrar esta lista…',
				zeroRecords: 'Ninguna factura coincide',
				paginate: { next: 'Siguiente', previous: 'Anterior' }
			}
		});
		tabla.on('length', function (e, s, nuevo) {
			try { localStorage.setItem(CLAVE, String(nuevo)); } catch (err) {}
		});

		/* Acciones sobre una factura. La respuesta se muestra y, al cerrarla,
		   se recarga: los filtros van en la dirección, así que se conservan. */
		var TEXTOS = {
			CONSULTAR_FACTURA_SRI:      { espera: 'Consultando en el SRI…' },
			ENVIAR_FACTURA_CORREO:      { espera: 'Enviando la factura…',
				confirmar: function (b) { return 'Se enviará el RIDE y el XML autorizado de la factura ' + b.dataset.numero + ' a ' + b.dataset.email + '.'; },
				boton: 'Enviar' },
			REGENERAR_FACTURA_DEVUELTA: { espera: 'Emitiendo con un nuevo secuencial…',
				confirmar: function (b) { return 'La factura ' + b.dataset.numero + ' se volverá a emitir con otro número. La devuelta queda como constancia.'; },
				boton: 'Emitir de nuevo' }
		};

		function ejecutar(boton) {
			var op = boton.dataset.accion, datos = new FormData();
			datos.append('modulo_facturas', op);
			datos.append('factura_id', boton.dataset.id);
			Swal.fire({ title: TEXTOS[op].espera, text: 'Puede tardar unos segundos.', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
			fetch('<?php echo $urlAjax; ?>', { method: 'POST', body: datos, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					Swal.fire({ title: r.titulo || 'Listo', text: r.texto || '', icon: r.icono || 'info', confirmButtonText: 'Aceptar' })
						.then(function () { location.reload(); });
				})
				.catch(function () {
					Swal.fire({ title: 'Sin conexión', text: 'No fue posible comunicarse con el servidor.', icon: 'error', confirmButtonText: 'Aceptar' });
				});
		}

		document.getElementById('tablaFacturas').addEventListener('click', function (e) {
			var boton = e.target.closest('button[data-accion]');
			if (!boton) { return; }
			var t = TEXTOS[boton.dataset.accion];
			if (!t.confirmar) { ejecutar(boton); return; }
			Swal.fire({ title: '¿Continuar?', text: t.confirmar(boton), icon: 'question', showCancelButton: true,
				confirmButtonText: t.boton, cancelButtonText: 'Cancelar' })
				.then(function (r) { if (r.isConfirmed) { ejecutar(boton); } });
		});
	});
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
  </body>
</html>
