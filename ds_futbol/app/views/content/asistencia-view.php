<?php
	use app\controllers\asistenciaController;
	$insHorario = new asistenciaController();

	$_SESSION['menupadre']="Asistencia";
	$_SESSION['menuhijo']="asistencia";

	$profesorid	= $_SESSION['usuario_id'];

	$filas   = $insHorario->listarHorariosProfesor($profesorid);
	$total   = substr_count($filas, '<tr>');
	$resumen = $insHorario->resumenToma;

	$DIAS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
	$hoyTexto = $DIAS[(int)date('N')] . ' ' . date('d/m/Y');
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Asistencia';
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
						<h4 class="m-0">Registrar asistencia</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Registrar asistencia</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Toma de asistencia de hoy =================== -->
				<div class="ds-toma__resumen mb-3" role="status" aria-live="polite">
					<span class="ds-toma__hoy"><i class="fas fa-calendar-day me-1" aria-hidden="true"></i>Hoy, <?php echo $hoyTexto; ?></span>
					<span class="badge text-bg-warning" title="Horarios con parte de la lista marcada"><?php echo $resumen['curso']; ?> en curso</span>
					<span class="badge text-bg-danger" title="Horarios con clase hoy y sin ninguna marca"><?php echo $resumen['pendiente'] . ($resumen['pendiente'] === 1 ? ' pendiente' : ' pendientes'); ?></span>
					<span class="badge text-bg-success" title="Horarios con toda la lista marcada"><?php echo $resumen['tomada'] . ($resumen['tomada'] === 1 ? ' tomada' : ' tomadas'); ?></span>
					<a href="<?php echo APP_URL; ?>asistencia/" class="btn btn-sm ms-auto ds-toma__actualizar" title="Volver a consultar el estado (actualizado a las <?php echo date('H:i'); ?>)"><i class="fas fa-rotate me-1" aria-hidden="true"></i>Actualizar <span class="small"><?php echo date('H:i'); ?></span></a>
				</div>

				<!-- =================== Horarios =================== -->
				<div class="card ds-crud__lista" id="asistencia-horarios">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-clipboard-check me-2" aria-hidden="true"></i>Horarios registrados
							<span class="badge text-bg-secondary ms-1"><?php echo $total; ?></span>
						</h3>
						<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
					</div>
					<div class="card-body">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Horario</th>
									<th>Días y hora</th>
									<th class="ds-col-opcional">Profesor</th>
									<th>Alumnos en lista</th>
									<th>Toma de asistencia</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filas; ?>
							</tbody>
						</table>
						<p class="small text-body-secondary mb-0 mt-2">
							<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Toma de asistencia: alumnos activos de la lista con marca hoy.
							La marca es del alumno, no del horario: si está en dos horarios, cuenta en ambos.
						</p>
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
		| Sin la extensión Responsive: en el teléfono cada horario es una
		| ficha (futbol.css, .ds-crud). Por defecto, primero lo que hay que
		| atender hoy: en curso, pendientes, tomadas y, al final, sin clase.
		*/
		var CLAVE = 'ds-asistencia-horarios-por-pagina', LARGOS = [10, 25, 50, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable("#example1", {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 'Todos']],
			order: [[4, 'asc']],
			columnDefs: [
				{ targets: [0, 1, 4], type: 'string' },  /* data-order: prioridad + hora */
				{ targets: 3, type: 'num' },
				{ targets: 5, orderable: false, searchable: false }
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "No tiene horarios asignados",
				info: "_START_ a _END_ de _TOTAL_ horarios",
				infoEmpty: "Sin horarios",
				infoFiltered: "(de _MAX_)",
				lengthMenu: "Mostrar _MENU_",
				search: "",
				searchPlaceholder: "Filtrar por horario, sede o profesor…",
				zeroRecords: "Ningún horario coincide con el filtro",
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

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
  </body>
</html>
