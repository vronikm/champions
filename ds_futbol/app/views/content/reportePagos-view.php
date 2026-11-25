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
	$excel   = $insRecibidos->filasExcelPagos();
	$formas  = $insRecibidos->formasDePagos();

	$h   = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$pdf = APP_URL . 'reportePagosPDF/' . ((int)$sede_id) . '/' . $rp_fecha_inicio . '/' . $rp_fecha_fin . '/';

	/*
	| ATAJOS DE PERIODO (2026-11-24)
	|
	| En el teléfono escribir dos fechas es lo más lento de la pantalla. Los
	| atajos ponen las dos de un toque y buscan. El activo se marca.
	*/
	$hoy = date('Y-m-d');
	$atajos = [
		'Hoy'          => [$hoy, $hoy],
		'Esta semana'  => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
		'Este mes'     => [date('Y-m-01'), date('Y-m-t')],
		'Mes anterior' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
	];
	$nombreSede   = $sede_nombre !== '' ? 'Sede ' . $sede_nombre : 'Todas las sedes';
	$archivoExcel = 'Pagos ' . ($sede_nombre !== '' ? $sede_nombre . ' ' : '') . $rp_fecha_inicio . ' a ' . $rp_fecha_fin . '.xlsx';
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

		<style>
			/* ---- Pagos realizados (2026-11-24): teléfono primero ---- */
			.rp-acciones { display: flex; gap: .5rem; }
			.rp-atajos { display: flex; gap: .4rem; overflow-x: auto; padding-bottom: .15rem; margin-bottom: .6rem; scrollbar-width: none; }
			.rp-atajos::-webkit-scrollbar { display: none; }
			.rp-atajos button { flex: 0 0 auto; border-radius: 2rem; min-height: 2.25rem;
			                    --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }
			.rp-atajos button.activo { background: var(--bs-success); border-color: var(--bs-success); color: #fff; }
			.rp-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .75rem; margin-bottom: 1rem; }
			.rp-kpi { border: 1px solid var(--bs-border-color); border-radius: .85rem; padding: .7rem .9rem; background: var(--bs-body-bg); min-width: 0; }
			.rp-kpi__etq { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: var(--bs-secondary-color); }
			.rp-kpi__cifra { display: block; font-size: 1.35rem; font-weight: 700; line-height: 1.2; margin-top: .15rem; font-variant-numeric: tabular-nums; }
			.rp-kpi--total { background: var(--bs-success); border-color: var(--bs-success); color: #fff; }
			.rp-kpi--total .rp-kpi__etq { color: rgba(255,255,255,.85); }
			.rp-kpi--pend .rp-kpi__cifra { color: var(--bs-warning-text-emphasis); }
			.rp-formas { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .4rem; }
			.rp-forma { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .7rem; border-radius: 2rem; border: 1px solid var(--bs-border-color);
			            background: var(--bs-tertiary-bg); font-size: .85rem; }
			.rp-forma strong { font-variant-numeric: tabular-nums; }

			@media (max-width: 991.98px) {
				/* Regresar, PDF y Excel: tres botones iguales en una fila. */
				.rp-acciones { width: 100%; }
				.rp-acciones .btn { flex: 1 1 0; min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; }
				.rp-atajos button { min-height: 2.5rem; }

				/*
				| La ficha de cada pago, compacta: nombre e importe arriba, la
				| fecha, el rubro y la forma en una línea gris, y el estado. El
				| pendiente sólo aparece si hay. Antes eran seis filas
				| «Rótulo: valor» por pago y entraban dos por pantalla.
				*/
				#tablaPagos > tbody > tr { grid-template-columns: minmax(0, 1fr) auto; gap: .1rem .75rem; }
				#tablaPagos td.ds-ficha__principal { grid-column: 1; grid-row: 1; }
				#tablaPagos td[data-etiqueta="Pagado"] { grid-column: 2; grid-row: 1; display: block; font-size: 1.1rem; }
				#tablaPagos td[data-etiqueta="Pagado"]::before { display: none; }
				#tablaPagos td.ds-ficha__estado { grid-column: 2; grid-row: 2; justify-self: end; }
				#tablaPagos td[data-etiqueta="Fecha de pago"],
				#tablaPagos td[data-etiqueta="Rubro"] { grid-column: 1; display: block; text-align: left !important; font-size: .82rem; color: var(--bs-secondary-color); }
				#tablaPagos td[data-etiqueta="Fecha de pago"] { grid-row: 2; }
				#tablaPagos td[data-etiqueta="Rubro"] { grid-row: 3; }
				#tablaPagos td[data-etiqueta="Fecha de pago"]::before,
				#tablaPagos td[data-etiqueta="Rubro"]::before { display: none; }
				#tablaPagos td[data-etiqueta="Fecha de pago"] .ds-reporte__valor,
				#tablaPagos td[data-etiqueta="Rubro"] .ds-reporte__valor { text-align: left; }
				#tablaPagos td[data-etiqueta="Fecha de pago"] .ds-reporte__sub,
				#tablaPagos td[data-etiqueta="Rubro"] .ds-reporte__sub { display: inline; font-size: .82rem; }
				#tablaPagos td[data-etiqueta="Fecha de pago"] .ds-reporte__sub::before,
				#tablaPagos td[data-etiqueta="Rubro"] .ds-reporte__sub::before { content: " · "; }
				#tablaPagos td[data-etiqueta="Rubro"] .ds-reporte__valor { color: var(--bs-body-color); }
				#tablaPagos td.rp-sin-pendiente { display: none !important; }
				#tablaPagos td.rp-con-pendiente { grid-column: 1 / -1; grid-row: 4; font-size: .85rem; color: var(--bs-warning-text-emphasis); }
				#tablaPagos td[data-etiqueta="Periodo"] { display: none !important; }
			}
			@media (max-width: 575.98px) {
				.rp-kpis { grid-template-columns: 1fr 1fr; gap: .5rem; }
				.rp-kpi--total { grid-column: 1 / -1; }
				.rp-kpi__cifra { font-size: 1.15rem; }
			}
		</style>

		<div class="app-content-header">
			<div class="container-fluid">
				<div class="d-flex flex-wrap align-items-center gap-2 ds-reporte__cabecera">
					<div class="me-auto">
						<h1 class="m-0">Pagos realizados</h1>
						<p class="small text-body-secondary mb-0">
							<i class="fas fa-location-dot me-1" aria-hidden="true"></i><?php echo $h($nombreSede); ?>
							· del <?php echo reporteController::fechaCorta($rp_fecha_inicio); ?> al <?php echo reporteController::fechaCorta($rp_fecha_fin); ?>
						</p>
					</div>
					<div class="rp-acciones">
						<?php /* Regresar: a la pantalla de la que se llegó (panel,
								 reportes…). Abierta sola, al panel. */ ?>
						<?php echo ds_boton('volver', 'Regresar', ['href' => APP_URL . 'dashboard/', 'estilo' => 'secondary', 'class' => 'btn-sm', 'id' => 'btnRegresar']); ?>
						<a href="<?php echo $h($pdf); ?>" class="btn btn-danger btn-sm" id="btnImprimir" target="_blank" rel="noopener" title="Reporte en PDF con las fechas elegidas">
							<i class="fas fa-file-pdf me-1" aria-hidden="true"></i> PDF</a>
						<button type="button" class="btn btn-success btn-sm" id="btnExcel" title="Descargar en Excel (.xlsx)"<?php echo $excel ? '' : ' disabled'; ?>>
							<i class="fas fa-file-excel me-1" aria-hidden="true"></i> Excel</button>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
				<form action="<?php echo APP_URL."reportePagos/".$h($sede_id) ?>" method="POST" autocomplete="off" class="card card-default ds-reporte__filtro" id="rpFiltro">
					<div class="card-body">
						<div class="rp-atajos" role="group" aria-label="Periodos rápidos">
							<?php foreach ($atajos as $nombre => [$d, $a]): ?>
								<button type="button" class="btn btn-sm btn-outline-secondary<?php echo $d === $rp_fecha_inicio && $a === $rp_fecha_fin ? ' activo' : ''; ?>"
								        data-desde="<?php echo $d; ?>" data-hasta="<?php echo $a; ?>"
								        aria-pressed="<?php echo $d === $rp_fecha_inicio && $a === $rp_fecha_fin ? 'true' : 'false'; ?>"><?php echo $nombre; ?></button>
							<?php endforeach; ?>
						</div>
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

				<?php /* Las cifras del periodo, antes de la lista. */ ?>
				<div class="rp-kpis">
					<div class="rp-kpi rp-kpi--total">
						<span class="rp-kpi__etq"><i class="fas fa-sack-dollar" aria-hidden="true"></i>Total pagado</span>
						<strong class="rp-kpi__cifra" id="rpTotal"><?php echo reporteController::dinero($totales['pagado'] ?? 0); ?></strong>
					</div>
					<div class="rp-kpi">
						<span class="rp-kpi__etq"><i class="fas fa-receipt" aria-hidden="true"></i>Pagos</span>
						<strong class="rp-kpi__cifra"><?php echo (int)($totales['pagos'] ?? 0); ?></strong>
					</div>
					<div class="rp-kpi rp-kpi--pend">
						<span class="rp-kpi__etq"><i class="fas fa-hourglass-half" aria-hidden="true"></i>Pendiente</span>
						<strong class="rp-kpi__cifra"><?php echo reporteController::dinero($totales['pendiente'] ?? 0); ?></strong>
					</div>
					<?php if ($formas): ?>
						<div class="rp-formas" aria-label="Lo pagado por forma de pago">
							<?php foreach ($formas as $nombre => $valor): ?>
								<span class="rp-forma"><?php echo $h($nombre); ?> <strong><?php echo reporteController::dinero($valor); ?></strong></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

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
							El Excel lleva lo que se ve: si escribe en «Filtrar», sólo lo filtrado.
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
	     botones de exportar: el PDF lo da «PDF» y el Excel, excel.js -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_core/assets/js/excel.js'); ?>"></script>
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

		/*
		| EXCEL (2026-11-24)
		|
		| Las filas llegan ya separadas campo a campo (filasExcelPagos), en el
		| mismo orden en que se pintaron las de la tabla: el índice de cada
		| fila de DataTables es su posición aquí. Se exporta lo que se ve: si
		| hay algo escrito en «Filtrar», sólo esas, en el orden de la tabla.
		*/
		var FILAS = <?php echo json_encode($excel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
		var btn = document.getElementById('btnExcel');
		if (btn) {
			btn.addEventListener('click', function () {
				var filtro = tabla.search();
				var indices = tabla.rows({ search: 'applied', order: 'applied' }).indexes().toArray();
				var filas = indices.map(function (i) { return FILAS[i]; }).filter(Boolean);
				var columnas = [
					{ t: 'Sede', ancho: 14 }, { t: 'Identificación', ancho: 14 }, { t: 'Alumno', ancho: 34 },
					{ t: 'Fecha de pago', tipo: 'fecha' }, { t: 'Fecha de registro', tipo: 'fecha', ancho: 14 },
					{ t: 'Periodo', ancho: 16 }, { t: 'Rubro', ancho: 18 }, { t: 'Forma de pago', ancho: 26 },
					{ t: 'Concepto', ancho: 30 },
					{ t: 'Pagado', tipo: 'dinero', total: true }, { t: 'Pendiente', tipo: 'dinero', total: true }, { t: 'Estado', ancho: 11 }
				];
				/* Sin concepto (el informe por sede no lo trae): fuera esa columna. */
				if (!filas.some(function (f) { return f[8]; })) {
					columnas.splice(8, 1);
					filas = filas.map(function (f) { return f.slice(0, 8).concat(f.slice(9)); });
				}
				var titulo = ['Pagos realizados', <?php echo json_encode($nombreSede . ' · del ' . reporteController::fechaCorta($rp_fecha_inicio) . ' al ' . reporteController::fechaCorta($rp_fecha_fin), JSON_UNESCAPED_UNICODE); ?>
				              + (filtro ? ' · filtro: «' + filtro + '»' : ''),
				              'Generado el ' + new Date().toLocaleString('es-EC') + ' · ' + filas.length + (filas.length === 1 ? ' pago' : ' pagos')];
				btn.disabled = true;
				dsExcel.descargar(<?php echo json_encode($archivoExcel, JSON_UNESCAPED_UNICODE); ?>, { hoja: 'Pagos', titulo: titulo, columnas: columnas, filas: filas })
					.catch(function () {}).then(function () { btn.disabled = false; });
			});
		}
	});

	/* Atajos de periodo: ponen las dos fechas y buscan. */
	document.querySelectorAll('.rp-atajos button').forEach(function (b) {
		b.addEventListener('click', function () {
			document.getElementById('pago_fecha_inicio').value = b.dataset.desde;
			document.getElementById('pago_fecha_fin').value = b.dataset.hasta;
			var f = document.getElementById('rpFiltro');
			f.requestSubmit ? f.requestSubmit() : f.submit();
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
