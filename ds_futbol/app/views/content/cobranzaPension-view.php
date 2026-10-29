<?php
	use app\controllers\cobranzaController;
	$insMora = new cobranzaController();

	$filas = $insMora->pensionesValormora();
	$total = substr_count($filas, '<tr>');
	$mora  = $insMora->totalMora;
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php
	   Estilos de la tabla y del modal: futbol.css (COBRANZA). */
	$tituloVista = 'Cobranza pensiones';
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
				<h1 class="m-0">Cobranza de pensiones</h1>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
				<div class="card ds-crud__lista ds-cobranza__lista">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-hand-holding-dollar me-2" aria-hidden="true"></i>Alumnos con pensiones en mora
							<span class="badge text-bg-secondary ms-1"><?php echo $total; ?></span>
						</h3>
						<div class="ds-pagos__resumen" title="Suma de la columna «Total mora»">
							<span class="text-body-secondary">Total en mora</span>
							<strong class="<?php echo $mora > 0 ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format($mora, 2, '.', ','); ?></strong>
						</div>
						<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
					</div>
					<div class="card-body">
						<table id="tablaCobranza" class="table table-hover align-middle ds-crud__tabla ds-cobranza__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Representante</th>
									<th>Alumno</th>
									<th class="ds-col-opcional">Fecha de ingreso</th>
									<th>Último pago de pensión</th>
									<th class="ds-col-opcional">Asistencia</th>
									<th class="text-end">Total mora</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filas; ?>
							</tbody>
						</table>
						<p class="small text-body-secondary mb-0 mt-2">
							<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Total mora: saldos pendientes de pensión y las pensiones no pagadas desde la última. Sólo alumnos activos.
							Asistencia: presentes y atrasos sobre los días registrados.
						</p>
					</div>
				</div>
			</div>
		</section>
      </div>
      <!-- /.vista -->

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive (en el teléfono cada fila es una ficha) ni
	     botones de exportar -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		/* «Mostrar N» elige cuántos alumnos por página y se recuerda. */
		var CLAVE = 'ds-cobranza-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable('#tablaCobranza', {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [[0, 'asc']],
			columnDefs: [
				{ targets: [0, 1, 2, 3], type: 'string' },   /* data-order: nombres y fechas ISO */
				{ targets: [4, 5], type: 'num' },            /* data-order: porcentaje e importe */
				{ targets: 6, orderable: false, searchable: false }
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "No hay alumnos con pensiones en mora",
				info: "_START_ a _END_ de _TOTAL_ alumnos",
				infoEmpty: "Sin alumnos",
				infoFiltered: "(de _MAX_)",
				lengthMenu: "Mostrar _MENU_",
				search: "",
				searchPlaceholder: "Filtrar por nombre, cédula o sede…",
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
	</script>
	<?php
		$cobroVista    = 'cobranzaPension';
		$cobroConcepto = 'pensiones';
		require_once "app/views/inc/cobranzaNotificar.php";
	?>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
  </body>
</html>
