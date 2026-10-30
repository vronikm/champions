<?php
	use app\controllers\reporteController;
	use app\controllers\alumnoController;
	$insPendientes = new reporteController();

	$sede_id 	= (($url[1] ?? "") != "") ? (int)$url[1] : 0;

	/* Se pinta primero el cuerpo: valoresPendientes() es quien calcula los
	   totales del pie y del resumen mientras recorre las filas. */
	$filas   = $insPendientes->valoresPendientes($sede_id);
	$pie     = $insPendientes->pieValoresPendientes();
	$totales = $insPendientes->totalesValoresPendientes();

	$sedeNombre = '';
	if ($sede_id > 0) {
		$s = (new alumnoController())->informacionSede($sede_id);
		$sedeNombre = $s->rowCount() === 1 ? (string)$s->fetch()['sede_nombre'] : '';
	}
	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php
	   Estilos: futbol.css (PAGOS PENDIENTES). */
	$tituloVista = 'Pagos Pendientes';
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
					<div class="d-flex flex-wrap align-items-center gap-2 ds-pend__cabecera">
						<div class="me-auto">
							<h1 class="m-0">Pagos pendientes</h1>
							<p class="small text-body-secondary mb-0">
								<i class="fas fa-location-dot me-1" aria-hidden="true"></i><?php echo $sedeNombre !== '' ? 'Sede ' . $h($sedeNombre) : 'Todas las sedes'; ?>
								· alumnos activos
							</p>
						</div>
						<?php /* Regresar: a la pantalla de la que se llegó (panel,
								 cobranza, balance…). Abierta sola, al panel. */ ?>
						<?php echo ds_boton('volver', 'Regresar', ['href' => APP_URL . 'dashboard/', 'estilo' => 'secondary', 'class' => 'btn-sm', 'id' => 'btnRegresar']); ?>
						<?php echo ds_boton('imprimir', 'Imprimir', ['href' => APP_URL . 'reportePendientesPDF/' . $sede_id . '/', 'estilo' => 'success', 'class' => 'btn-sm', 'id' => 'btnImprimir', 'target' => '_blank', 'title' => 'Reporte en PDF']); ?>
					</div>
				</div>
			</div>

			<!-- Main content -->
			<section class="app-content">
				<div class="container-fluid">
					<div class="card ds-crud__lista ds-pend__lista">
						<div class="card-header d-flex flex-wrap align-items-center gap-2">
							<h3 class="card-title me-auto">
								<i class="fas fa-file-invoice-dollar me-2" aria-hidden="true"></i>Alumnos con pagos pendientes
								<span class="badge text-bg-secondary ms-1"><?php echo (int)($totales['alumnos'] ?? 0); ?></span>
							</h3>
							<div class="ds-pagos__resumen" title="Saldos pendientes más pensiones no pagadas">
								<span class="text-body-secondary">Total pendiente</span>
								<strong class="<?php echo ($totales['total'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo reporteController::dinero($totales['total'] ?? 0); ?></strong>
							</div>
						</div>
						<div class="card-body">
							<table id="tablaPendientes" class="table table-hover align-middle ds-crud__tabla ds-pend__tabla" style="width:100%">
								<thead>
									<tr>
										<th>Alumno</th>
										<th class="text-end">Partidas con saldo</th>
										<th class="text-end">Saldos</th>
										<th class="text-end">Pensiones no pagadas</th>
										<th class="text-end">Valor pensiones</th>
										<th class="text-end">Total</th>
									</tr>
								</thead>
								<tbody>
									<?php echo $filas; ?>
								</tbody>
								<?php if ($pie !== ''): ?>
								<?php
								/* Los totales van en el PIE: son de TODO el informe y se
								   ven siempre, se elija ver 10 filas o todas, y aunque
								   se filtre. futbol.css lo deja fijo al pie de la
								   pantalla mientras se recorre la lista. */
								?>
								<tfoot class="ds-pend__pie">
									<?php echo $pie; ?>
								</tfoot>
								<?php endif; ?>
							</table>
							<p class="small text-body-secondary mb-0 mt-2">
								<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Partidas con saldo: pagos de cualquier rubro con saldo pendiente.
								Pensiones no pagadas: las que faltan desde la última pensión registrada, con el descuento del alumno si lo tiene.
							</p>
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
	<!-- DataTables: sin Responsive (en el teléfono cada fila es una ficha) -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/* «Mostrar N» elige cuántos alumnos por página y se recuerda. */
		var CLAVE = 'ds-pendientes-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}
		var tabla = new DataTable('#tablaPendientes', {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [[5, 'desc']],
			columnDefs: [
				{ targets: 0, type: 'string' },
				{ targets: [1, 2, 3, 4, 5], type: 'num' }   /* data-order: el número sin formato */
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "No hay pagos pendientes",
				info: "_START_ a _END_ de _TOTAL_ alumnos",
				infoEmpty: "Sin alumnos",
				infoFiltered: "(de _MAX_)",
				lengthMenu: "Mostrar _MENU_",
				search: "",
				searchPlaceholder: "Filtrar por nombre o cédula…",
				zeroRecords: "Ningún alumno coincide con el filtro",
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
	   sistema; abierta sola, el enlace lleva al panel. Ctrl+clic o clic
	   central siguen abriendo el panel. */
	(function () {
		var b = document.getElementById('btnRegresar');
		var desdeAqui = false;
		try { desdeAqui = !!document.referrer && new URL(document.referrer).origin === location.origin && document.referrer !== location.href; } catch (e) {}
		if (!b || !desdeAqui || history.length < 2) { return; }
		b.addEventListener('click', function (e) {
			if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
			e.preventDefault();
			history.back();
		});
	})();
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>

  </body>
</html>
