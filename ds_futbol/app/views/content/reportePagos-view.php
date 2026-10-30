<?php
	use app\controllers\reporteController;
	$insRecibidos = new reporteController();
	$sede_id 	  = (($url[1] ?? "") != "") ? $url[1] : "";

	/* Fechas del filtro: las del formulario o el mes actual. Sólo se acepta
	   aaaa-mm-dd: van también en la dirección del PDF. */
	$fechaValida = static fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && checkdate((int)substr($f, 5, 2), (int)substr($f, 8, 2), (int)substr($f, 0, 4));
	$rp_fecha_inicio = $insRecibidos->limpiarCadena($_POST['pago_fecha_inicio'] ?? '');
	$rp_fecha_fin    = $insRecibidos->limpiarCadena($_POST['pago_fecha_fin'] ?? '');
	if (!$fechaValida($rp_fecha_inicio)) { $rp_fecha_inicio = date('Y-m-01'); }   // Primer día del mes actual
	if (!$fechaValida($rp_fecha_fin))    { $rp_fecha_fin    = date('Y-m-t'); }    // Último día del mes actual

	$datos=$insRecibidos->seleccionarDatos("Unico","general_sede","sede_id",$sede_id);
	if($datos->rowCount()==1){
		$datos=$datos->fetch();
		$sede_nombre		= $datos['sede_nombre'];
	}else{
		$sede_nombre = "";
	}

	/* Se pinta primero el cuerpo: es quien calcula los totales del pie. */
	$filas   = $sede_id != "" ? $insRecibidos->listarPagos($rp_fecha_inicio, $rp_fecha_fin, $sede_id)
	                          : $insRecibidos->listarPagosConsolidado($rp_fecha_inicio, $rp_fecha_fin);
	$pie     = $insRecibidos->pieValoresPagos();
	$totales = $insRecibidos->totalesValoresPagos();

	$h   = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$pdf = APP_URL . 'reportePagosPDF/' . ((int)$sede_id) . '/' . $rp_fecha_inicio . '/' . $rp_fecha_fin . '/';
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php
	   Estilos: futbol.css (REPORTES CON TOTALES). */
	$tituloVista = 'Reporte de pagos';
	$extras      = array (0 => 'datatables',1 => 'swal',);
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

		<div class="app-content-header">
			<div class="container-fluid">
				<div class="d-flex flex-wrap align-items-center gap-2 ds-reporte__cabecera">
					<div class="me-auto">
						<h1 class="m-0">Pagos realizados</h1>
						<p class="small text-body-secondary mb-0">
							<i class="fas fa-location-dot me-1" aria-hidden="true"></i><?php echo $sede_nombre !== '' ? 'Sede ' . $h($sede_nombre) : 'Todas las sedes'; ?>
							· del <?php echo reporteController::fechaCorta($rp_fecha_inicio); ?> al <?php echo reporteController::fechaCorta($rp_fecha_fin); ?>
						</p>
					</div>
					<?php /* Regresar: a la pantalla de la que se llegó (panel,
							 reportes…). Abierta sola, al panel. */ ?>
					<?php echo ds_boton('volver', 'Regresar', ['href' => APP_URL . 'dashboard/', 'estilo' => 'secondary', 'class' => 'btn-sm', 'id' => 'btnRegresar']); ?>
					<?php echo ds_boton('imprimir', 'Imprimir', ['href' => $pdf, 'estilo' => 'success', 'class' => 'btn-sm', 'id' => 'btnImprimir', 'target' => '_blank', 'title' => 'Reporte en PDF con las fechas elegidas']); ?>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
				<form action="<?php echo APP_URL."reportePagos/".$h($sede_id) ?>" method="POST" autocomplete="off" class="card card-default ds-reporte__filtro">
					<div class="card-body">
						<div class="row g-2 align-items-end">
							<div class="col-6 col-md-4">
								<label class="form-label mb-1" for="pago_fecha_inicio">Desde</label>
								<input type="date" class="form-control" id="pago_fecha_inicio" name="pago_fecha_inicio" value="<?php echo $h($rp_fecha_inicio); ?>" required>
							</div>
							<div class="col-6 col-md-4">
								<label class="form-label mb-1" for="pago_fecha_fin">Hasta</label>
								<input type="date" class="form-control" id="pago_fecha_fin" name="pago_fecha_fin" value="<?php echo $h($rp_fecha_fin); ?>" required>
							</div>
							<div class="col-12 col-md-4 col-lg-3">
								<?php echo ds_boton_buscar(); ?>
							</div>
						</div>
					</div>
				</form>

				<div class="card ds-crud__lista ds-reporte__lista">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-money-bill-wave me-2" aria-hidden="true"></i>Pagos
							<span class="badge text-bg-secondary ms-1"><?php echo (int)($totales['pagos'] ?? 0); ?></span>
						</h3>
						<div class="ds-pagos__resumen" title="Suma de la columna «Pagado»">
							<span class="text-body-secondary">Total pagado</span>
							<strong class="text-success"><?php echo reporteController::dinero($totales['pagado'] ?? 0); ?></strong>
						</div>
					</div>
					<div class="card-body">
						<table id="tablaPagos" class="table table-hover align-middle ds-crud__tabla ds-reporte__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Alumno</th>
									<th>Fecha de pago</th>
									<th class="ds-col-opcional">Periodo</th>
									<th>Rubro y forma de pago</th>
									<th class="text-end">Pagado</th>
									<th class="text-end">Pendiente</th>
									<th>Estado</th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filas; ?>
							</tbody>
							<?php if ($pie !== ''): ?>
							<?php /* El TOTAL va en el pie: es de TODO el informe y se ve
									 siempre, se elija ver 10 filas o todas, y aunque se
									 filtre; futbol.css lo deja fijo al pie de la pantalla. */ ?>
							<tfoot class="ds-reporte__pie">
								<?php echo $pie; ?>
							</tfoot>
							<?php endif; ?>
						</table>
						<p class="small text-body-secondary mb-0 mt-2">
							<i class="fas fa-circle-info me-1" aria-hidden="true"></i><?php echo $sede_id != ""
								? 'Pagos y abonos registrados en el sistema entre las dos fechas.'
								: 'Pagos y abonos con fecha de pago entre las dos fechas, de todas las sedes.'; ?>
						</p>
					</div>
				</div>
			</div><!-- /.container-fluid -->
		</section>
		<!-- /.section -->
      </div>
      <!-- /.vista -->
      <?php require_once "app/views/inc/footer.php"; ?>
    </div>
    <!-- ./wrapper -->
	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive (en el teléfono cada fila es una ficha) ni
	     botones de exportar: el PDF lo da «Imprimir» -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/* «Mostrar N» elige cuántos pagos por página y se recuerda. */
		var CLAVE = 'ds-pagos-reporte-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}
		var tabla = new DataTable('#tablaPagos', {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [[1, 'desc']],
			columnDefs: [
				{ targets: [0, 1], type: 'string' },   /* data-order: nombre y fecha ISO */
				{ targets: [4, 5], type: 'num' }       /* data-order: el número sin formato */
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "No hay pagos entre esas fechas",
				info: "_START_ a _END_ de _TOTAL_ pagos",
				infoEmpty: "Sin pagos",
				infoFiltered: "(de _MAX_)",
				lengthMenu: "Mostrar _MENU_",
				search: "",
				searchPlaceholder: "Filtrar por nombre, cédula, rubro…",
				zeroRecords: "Ningún pago coincide con el filtro",
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
	});

	/* «Regresar» vuelve a la pantalla anterior cuando se llegó desde el
	   sistema; abierta sola, el enlace lleva al panel. Tras «Buscar» la
	   anterior es esta misma pantalla, así que se salta: se cuenta cuántas
	   búsquedas se hicieron aquí y se retrocede todas. */
	(function () {
		var b = document.getElementById('btnRegresar');
		var CLAVE = 'ds-pagos-reporte-busquedas';
		var busquedas = 0;
		try {
			if (performance.getEntriesByType('navigation')[0].type === 'navigate' && document.referrer.indexOf('/reportePagos/') === -1) { sessionStorage.removeItem(CLAVE); }
			busquedas = parseInt(sessionStorage.getItem(CLAVE), 10) || 0;
		} catch (e) {}
		document.querySelector('.ds-reporte__filtro').addEventListener('submit', function () {
			try { sessionStorage.setItem(CLAVE, String(busquedas + 1)); } catch (e) {}
		});
		var desdeAqui = false;
		try { desdeAqui = !!document.referrer && new URL(document.referrer).origin === location.origin; } catch (e) {}
		if (!b || !desdeAqui || history.length < 2 + busquedas) { return; }
		b.addEventListener('click', function (e) {
			if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
			e.preventDefault();
			try { sessionStorage.removeItem(CLAVE); } catch (err) {}
			history.go(-(1 + busquedas));
		});
	})();
	</script>
  </body>
</html>
