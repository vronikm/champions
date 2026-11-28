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
	/* Van por GET (antes POST): así «Volver» desde la ficha de pagos regresa
	   a esta búsqueda sin el aviso «Confirmar reenvío del formulario», y la
	   búsqueda se puede recargar o guardar como enlace. */
	$criterio = static fn(string $clave) => is_string($_GET[$clave] ?? null) ? trim($_GET[$clave]) : '';

	$alumno_identificacion  = $criterio('alumno_identificacion');
	$alumno_primernombre    = $criterio('alumno_nombre1');
	$alumno_apellidopaterno = $criterio('alumno_apellido1');

	/* Estado: el listado muestra los ACTIVOS salvo que se pida otra cosa. */
	$alumno_estado = in_array($criterio('alumno_estado'), ['A', 'I', 'T'], true) ? $criterio('alumno_estado') : 'A';

	/* Sede: «Todas» para administración y para quien tiene más de una; el
	   servidor acota siempre a las del usuario (condicionSede). Al abrir,
	   todas: antes no salía ningún alumno hasta pulsar Buscar. */
	$opcionesSede  = $insPago->listarOptionSede($criterio('alumno_sedeid'), $_SESSION['rol'], $_SESSION['usuario']);
	$ofrecerTodas  = sedes_de_la_sesion() === null || substr_count($opcionesSede, '<option') > 1;
	$alumno_sedeid = isset($_GET['alumno_sedeid']) ? (int)$criterio('alumno_sedeid') : 0;

	$filas = $insPago->listarAlumnosPagos($alumno_identificacion, $alumno_apellidopaterno, $alumno_primernombre, $alumno_estado, $alumno_sedeid);
	$total = substr_count($filas, '<tr>');
	$deuda = $insPago->totalAdeudado;
	$resumen = $insPago->resumen;

	/* En el celular, los criterios que no son la identificación van plegados
	   salvo que alguno esté en uso. */
	$filtrosExtra = ($alumno_apellidopaterno !== '') + ($alumno_primernombre !== '') + ($alumno_estado !== 'A') + ($alumno_sedeid > 0);
	$hayCriterio  = $filtrosExtra + ($alumno_identificacion !== '');

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
						<div class="small text-body-secondary">Busque al alumno para registrar su pago o ver lo que debe</div>
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
				<form action="<?php echo APP_URL."pagosList/" ?>" method="GET" autocomplete="off" class="card ds-alumnos__filtros pg-filtros">
					<div class="card-header pg-filtros__cab">
						<h3 class="card-title mb-0"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<button type="button" class="btn btn-sm btn-outline-secondary d-md-none pg-filtros__plegar" data-bs-toggle="collapse" data-bs-target="#pgFiltrosCuerpo"
						        aria-expanded="<?php echo $filtrosExtra ? 'true' : 'false'; ?>" aria-controls="pgFiltrosCuerpo">
							<i class="fas fa-sliders me-1" aria-hidden="true"></i><span><?php echo $filtrosExtra ? 'Filtros (' . $filtrosExtra . ')' : 'Filtros'; ?></span>
							<i class="fas fa-chevron-down ms-1" aria-hidden="true"></i>
						</button>
					</div>
					<div class="card-body pb-2">
						<div class="row g-2 g-md-3">
							<?php /* Celular: la cédula y «Buscar» en la misma línea; los demás
							         criterios, plegados debajo (order-last). */ ?>
							<div class="col col-md-4 col-xl">
								<label for="alumno_identificacion" class="form-label">Identificación</label>
								<input type="search" class="form-control" id="alumno_identificacion" name="alumno_identificacion" inputmode="numeric" placeholder="Cédula o código" value="<?php echo $h($alumno_identificacion); ?>">
							</div>
							<div class="collapse pg-filtros__extra order-last order-md-0<?php echo $filtrosExtra ? ' show' : ''; ?>" id="pgFiltrosCuerpo">
							<div class="row g-2 g-md-3 mt-0 pg-filtros__extra">
								<div class="col-6 col-md-4 col-xl">
									<label for="alumno_apellido1" class="form-label">Apellido paterno</label>
									<input type="text" class="form-control" id="alumno_apellido1" name="alumno_apellido1" placeholder="Primer apellido" value="<?php echo $h($alumno_apellidopaterno); ?>">
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="alumno_nombre1" class="form-label">Primer nombre</label>
									<input type="text" class="form-control" id="alumno_nombre1" name="alumno_nombre1" placeholder="Primer nombre" value="<?php echo $h($alumno_primernombre); ?>">
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="alumno_estado" class="form-label">Estado</label>
									<select class="form-select" id="alumno_estado" name="alumno_estado">
										<?php foreach ($ESTADOS as $valor => $texto): ?>
											<option value="<?php echo $valor; ?>" <?php echo $alumno_estado === $valor ? 'selected' : ''; ?>><?php echo $texto; ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl">
									<label for="alumno_sedeid" class="form-label">Sede</label>
									<select class="form-select" id="alumno_sedeid" name="alumno_sedeid">
										<?php if ($ofrecerTodas): ?>
											<option value="0" <?php echo $alumno_sedeid === 0 ? 'selected' : ''; ?>>Todas</option>
										<?php endif; ?>
										<?php echo $opcionesSede; ?>
									</select>
								</div>
							</div>
							</div>
							<div class="col-auto col-md-4 col-xl-auto d-flex align-items-end gap-2 pg-filtros__acciones">
								<div class="flex-grow-1"><?php echo ds_boton_buscar(); ?></div>
								<?php if ($hayCriterio): ?>
									<a href="<?php echo APP_URL; ?>pagosList/" class="btn btn-outline-secondary" title="Quitar los criterios">
										<i class="fas fa-eraser" aria-hidden="true"></i><span class="visually-hidden">Limpiar</span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resumen =================== -->
				<div class="pg-kpis mb-3">
					<div class="pg-kpi pg-kpi--total">
						<span class="pg-kpi__et">Alumnos</span>
						<strong class="pg-kpi__val"><?php echo (int)$total; ?></strong>
						<span class="pg-kpi__nota"><?php echo $h(mb_strtolower($ESTADOS[$alumno_estado])); ?></span>
					</div>
					<div class="pg-kpi">
						<span class="pg-kpi__et">Al día</span>
						<strong class="pg-kpi__val text-success"><?php echo (int)$resumen['alDia']; ?></strong>
						<span class="pg-kpi__nota"><?php echo $total ? round($resumen['alDia'] * 100 / $total) . ' %' : '&nbsp;'; ?></span>
					</div>
					<div class="pg-kpi">
						<span class="pg-kpi__et">Con deuda</span>
						<strong class="pg-kpi__val <?php echo $resumen['conDeuda'] ? 'text-danger' : ''; ?>"><?php echo (int)$resumen['conDeuda']; ?></strong>
						<span class="pg-kpi__nota"><?php echo $total ? round($resumen['conDeuda'] * 100 / $total) . ' %' : '&nbsp;'; ?></span>
					</div>
					<div class="pg-kpi pg-kpi--deuda">
						<span class="pg-kpi__et">Total adeudado</span>
						<strong class="pg-kpi__val <?php echo $deuda > 0 ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format($deuda, 2, '.', ','); ?></strong>
						<span class="pg-kpi__nota">de esta búsqueda</span>
					</div>
				</div>

				<!-- =================== Resultado de la búsqueda =================== -->
				<div class="card ds-crud__lista" id="pagos-resultado">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-file-invoice-dollar me-2" aria-hidden="true"></i>Resultado de la búsqueda
							<span class="badge text-bg-secondary ms-1" title="<?php echo $ESTADOS[$alumno_estado]; ?>"><?php echo $total; ?></span>
						</h3>
						<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
					</div>
					<div class="card-body">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Alumno</th>
									<th>Sede</th>
									<th class="ds-col-opcional">Fecha de ingreso</th>
									<th>Último pago de pensión</th>
									<th>Asistencia</th>
									<th>Valor adeudado</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
								<?php echo $filas; ?>
							</tbody>
						</table>
						<p class="small text-body-secondary mb-0 mt-2">
							<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Valor adeudado: saldos pendientes y, para alumnos activos, las pensiones no pagadas desde la última; para inactivos, las de los meses que asistieron después de su último pago. Quien nunca pagó una pensión las debe desde su ingreso. Un pago adelantado cubre el mes que indica y los pagos eliminados no cuentan.
							Asistencia: el último día que vino (presente o atraso, según lo que tomaron los profesores) y, debajo, el porcentaje sobre los días registrados.
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
				{ targets: [1, 2, 3, 4], type: 'string' },  /* data-order: sede y fechas ISO (ingreso, último pago, última asistencia) */
				{ targets: 5, type: 'num' },                /* data-order: importe */
				{ targets: 6, orderable: false, searchable: false }
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

	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>

	<style>
		/* Criterios: en el celular se ve la identificación y «Filtros» despliega el resto;
		   en el escritorio, todo en una fila. */
		.pg-filtros__cab { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.pg-filtros .form-label { font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: .25rem; }
		.pg-filtros__plegar { white-space: nowrap; flex: 0 0 auto; }
		.pg-filtros__plegar[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
		@media (min-width: 768px) { .pg-filtros__extra { display: contents !important; } }

		/* Resumen */
		.pg-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
		.pg-kpi { display: flex; flex-direction: column; gap: .1rem; padding: .8rem 1rem; border-radius: var(--ds-radius-sm, 10px);
		          background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); min-width: 0; }
		.pg-kpi__et { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); font-weight: 600; }
		.pg-kpi__val { font-size: 1.45rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
		.pg-kpi__nota { font-size: .8rem; color: var(--bs-secondary-color); }
		.pg-kpi--total { border-top: 3px solid var(--bs-primary); }
		.pg-kpi--deuda { border-top: 3px solid var(--bs-danger); }
		.ds-pagos__sede { font-weight: 600; }
		/* Tabla (escritorio): con la columna Sede no cabía todo en una
		   portátil; el nombre se partía en cuatro líneas y los botones se
		   salían. Entre 992 y 1399 px se oculta «Fecha de ingreso» (opcional;
		   la ficha del celular la sigue mostrando). */
		@media (min-width: 992px) {
			#pagos-resultado td.ds-ficha__principal { min-width: 13.5rem; }
			#pagos-resultado td[data-etiqueta="Sede"] .ds-pagos__sede { white-space: nowrap; }
			#pagos-resultado td.ds-ficha__acciones { width: 1%; }
		}
		@media (min-width: 992px) and (max-width: 1399.98px) {
			#pagos-resultado .ds-col-opcional { display: none; }
		}

		/*
		| Celular (2026-11-28). Antes el primer alumno aparecía a ~800 px:
		| el resumen ocupaba cuatro cajas en dos filas y cada ficha tenía
		| seis renglones con rótulo delante.
		|  · Criterios: cédula y «Buscar» en una línea; «Filtros» despliega el resto.
		|  · Resumen: tres cifras en una fila y el total adeudado en otra.
		|  · Ficha: nombre y lo que debe arriba; sede, ingreso, último pago y
		|    asistencia en dos columnas con el rótulo encima; los botones.
		| Respaldo: etiqueta respaldo/pagosList-antes-ux-2026-11-28.
		*/
		@media (max-width: 767.98px) {
			.pg-filtros > .card-header { padding: .7rem 1rem; }
			.pg-filtros > .card-body { padding: .75rem 1rem; }
			.pg-filtros__acciones .btn { min-height: 2.75rem; }
			.pg-filtros input.form-control { min-height: 2.75rem; }

			.pg-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; margin-bottom: .75rem !important; }
			.pg-kpi { padding: .5rem .65rem; }
			.pg-kpi__et { font-size: .68rem; }
			.pg-kpi__val { font-size: 1.15rem; }
			.pg-kpi__nota { font-size: .72rem; }
			.pg-kpi--deuda { grid-column: 1 / -1; flex-direction: row; flex-wrap: wrap; align-items: baseline; column-gap: .6rem; }
			.pg-kpi--deuda .pg-kpi__val { margin-left: auto; order: 2; }
			.pg-kpi--deuda .pg-kpi__nota { order: 1; }
		}
		@media (max-width: 991.98px) {
			#pagos-resultado #example1 > tbody > tr { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem .75rem; }
			#pagos-resultado #example1 > tbody > tr > td.ds-ficha__principal { grid-column: 1 / -1; grid-row: 1; }
			/* Lo que debe, en su línea bajo el nombre: «$140.00 · 4 pensiones». */
			#pagos-resultado #example1 > tbody > tr > td[data-etiqueta="Valor adeudado"] {
				grid-column: 1 / -1; grid-row: 2;
				display: flex; flex-wrap: wrap; align-items: center; gap: .2rem .6rem;
				padding: .45rem .65rem;
				border-radius: var(--bs-border-radius);
				background: var(--bs-tertiary-bg) !important;
			}
			#pagos-resultado #example1 > tbody > tr > td[data-etiqueta="Valor adeudado"]::before {
				content: "Debe"; font-size: .68rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase;
			}
			#pagos-resultado #example1 > tbody > tr > td[data-etiqueta="Valor adeudado"]:has(.text-bg-success)::before { content: "Pagos"; }
			#pagos-resultado #example1 td[data-etiqueta="Valor adeudado"] .badge { font-size: .95rem; }
			#pagos-resultado #example1 td[data-etiqueta="Valor adeudado"] .small { display: inline !important; font-size: .8rem; }
			#pagos-resultado #example1 > tbody > tr > td.ds-ficha__dato:not([data-etiqueta="Valor adeudado"]) {
				grid-column: auto; font-size: .85rem;
			}
			#pagos-resultado #example1 > tbody > tr > td.ds-ficha__dato[data-etiqueta]:not([data-etiqueta="Valor adeudado"])::before {
				content: attr(data-etiqueta); display: block; margin-bottom: .1rem;
				font-size: .68rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase;
			}
			#pagos-resultado #example1 > tbody > tr > td.ds-ficha__acciones { grid-column: 1 / -1; }
		}
	</style>
  </body>
</html>
