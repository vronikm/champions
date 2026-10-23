<?php
	use app\controllers\pagosController;
	$insPago = new pagosController();

	/* Rol de la sesión: decide si el filtro ofrece "Todas las sedes". */
	$rolid = rol_actual();

	/*
	| Los criterios de búsqueda se toman TAL CUAL los escribió el usuario.
	|
	| Antes pasaban por limpiarCadena(), que aplica htmlspecialchars: eso
	| convertía «O'Brien» en «O&#039;Brien» y la búsqueda no encontraba al
	| alumno aunque existiera. El escape corresponde a la SALIDA, no a la
	| entrada: abajo se escapan al pintarlos en el formulario, y la consulta
	| los recibe como parámetros ligados.
	*/
	$criterio = static fn(string $clave) => trim((string)($_POST[$clave] ?? ''));

	$alumno_identificacion  = $criterio('alumno_identificacion');
	$alumno_primernombre    = $criterio('alumno_nombre1');
	$alumno_apellidopaterno = $criterio('alumno_apellido1');

	/* Estado: el listado muestra los ACTIVOS salvo que se pida otra cosa. */
	$alumno_estado = in_array($criterio('alumno_estado'), ['A', 'I', 'T'], true) ? $criterio('alumno_estado') : 'A';

	/* Sede: «Todas» para administración y para quien tiene más de una; el
	   servidor acota siempre a las del usuario (condicionSede). Al abrir,
	   todas: antes no salía ningún alumno hasta pulsar Buscar. */
	$opcionesSede  = $insPago->listarOptionSede($criterio('alumno_sedeid'), $_SESSION['rol'], $_SESSION['usuario']);
	$ofrecerTodas  = $rolid == 1 || $rolid == 2 || substr_count($opcionesSede, '<option') > 1;
	$alumno_sedeid = isset($_POST['alumno_sedeid']) ? (int)$criterio('alumno_sedeid') : 0;

	$filas = $insPago->listarAlumnosPagos($alumno_identificacion, $alumno_apellidopaterno, $alumno_primernombre, $alumno_estado, $alumno_sedeid);
	$total = substr_count($filas, '<tr>');
	$deuda = $insPago->totalAdeudado;

	/* Para imprimirlos dentro de un atributo HTML sin abrirlo. */
	$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
	$ESTADOS = ['A' => 'Activos', 'I' => 'Inactivos', 'T' => 'Todos'];
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Pagos';
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
						<h4 class="m-0">Registro de pagos</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Pagos</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Criterios de búsqueda =================== -->
				<form action="<?php echo APP_URL."pagosList/" ?>" method="POST" autocomplete="off" class="card ds-alumnos__filtros">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-12 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_identificacion" class="form-label">Identificación</label>
								<input type="text" class="form-control" id="alumno_identificacion" name="alumno_identificacion" inputmode="numeric" placeholder="Cédula" value="<?php echo $h($alumno_identificacion); ?>">
							</div>
							<div class="col-6 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_apellido1" class="form-label">Apellido paterno</label>
								<input type="text" class="form-control" id="alumno_apellido1" name="alumno_apellido1" placeholder="Primer apellido" value="<?php echo $h($alumno_apellidopaterno); ?>">
							</div>
							<div class="col-6 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_nombre1" class="form-label">Primer nombre</label>
								<input type="text" class="form-control" id="alumno_nombre1" name="alumno_nombre1" placeholder="Primer nombre" value="<?php echo $h($alumno_primernombre); ?>">
							</div>
							<div class="col-6 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_estado" class="form-label">Estado</label>
								<select class="form-select" id="alumno_estado" name="alumno_estado">
									<?php foreach ($ESTADOS as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>" <?php echo $alumno_estado === $valor ? 'selected' : ''; ?>><?php echo $texto; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-sm-6 col-lg-4 col-xl">
								<label for="alumno_sedeid" class="form-label">Sede</label>
								<select class="form-select" id="alumno_sedeid" name="alumno_sedeid">
									<?php if ($ofrecerTodas): ?>
										<option value="0" <?php echo $alumno_sedeid === 0 ? 'selected' : ''; ?>>Todas</option>
									<?php endif; ?>
									<?php echo $opcionesSede; ?>
								</select>
							</div>
							<div class="col-12 col-lg-4 col-xl-auto d-flex align-items-end">
								<div class="w-100">
									<?php echo ds_boton_buscar(); ?>
								</div>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resultado de la búsqueda =================== -->
				<div class="card ds-crud__lista" id="pagos-resultado">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-file-invoice-dollar me-2" aria-hidden="true"></i>Resultado de la búsqueda
							<span class="badge text-bg-secondary ms-1" title="<?php echo $ESTADOS[$alumno_estado]; ?>"><?php echo $total; ?></span>
						</h3>
						<div class="ds-pagos__resumen" title="Suma de la columna «Valor adeudado» de esta búsqueda">
							<span class="text-body-secondary">Adeudado</span>
							<strong class="<?php echo $deuda > 0 ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format($deuda, 2, '.', ','); ?></strong>
						</div>
						<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
					</div>
					<div class="card-body">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Alumno</th>
									<th class="ds-col-opcional">Fecha de ingreso</th>
									<th>Último pago de pensión</th>
									<th>Valor adeudado</th>
									<th class="ds-col-opcional">Asistencia</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filas; ?>
							</tbody>
						</table>
						<p class="small text-body-secondary mb-0 mt-2">
							<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Valor adeudado: saldos pendientes y, para alumnos activos, las pensiones no pagadas desde la última.
							Asistencia: presentes y atrasos sobre los días registrados.
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
		| Sin la extensión Responsive (escondía columnas y botones tras un ▶).
		| En el teléfono cada fila es una ficha (futbol.css, .ds-crud).
		| «Mostrar N» elige cuántos alumnos por página y se recuerda.
		*/
		var CLAVE = 'ds-pagos-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		var tabla = new DataTable("#example1", {
			autoWidth: false,
			pageLength: largo,
			lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
			order: [[0, 'asc']],
			columnDefs: [
				{ targets: [1, 2], type: 'string' },     /* data-order: fechas ISO (ingreso, último pago) */
				{ targets: [3, 4], type: 'num' },        /* data-order: importe y porcentaje */
				{ targets: 5, orderable: false, searchable: false }
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "No hay alumnos con estos criterios",
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
	</script>

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
  </body>
</html>
