<?php
	use app\controllers\carnetController;
	$insCarnet = new carnetController();
	$cobrarReimpresion = $insCarnet->cobrarReimpresionCarnet();

	$valorReimpresion = $insCarnet->valorReimpresionCarnet();

	/* Alumnos con pago del mes y sin horario. Mientras haya alguno, la lista
	   de carnets no se muestra: el aviso va encima de la tabla, nunca dentro
	   (DataTables no admite filas con colspan). */
	$sinHorario = $insCarnet->verificarAlumnosSinHorarioAsignado();
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Carnets';
	$extras      = array (0 => 'datatables',);
	require_once "app/views/inc/cabecera.php";
?>
  
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">
      <!-- Navbar -->
      <?php require_once "app/views/inc/navbar.php"; ?>
      
      <!-- Main Sidebar Container -->
      <?php require_once "app/views/inc/main-sidebar.php"; ?>
      
      <!-- Content Wrapper -->
      <div class="app-main">
		<!-- Content Header -->
		<div class="app-content-header">
			<div class="container-fluid">
				<div class="row mb-2">
					<div class="col-sm-6">
						<h3 class="m-0">Carnets del Mes</h3>
					</div>
					<div class="col-sm-6">
						<ol class="breadcrumb float-sm-end">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active">Carnets</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<!-- Main content -->
		<section class="app-content">
			<div class="container-fluid">
				<!-- Card principal -->
				<div class="card card-default">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-id-card"></i> 
							Alumnos con pago de pensión - <?php $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'America/Guayaquil', IntlDateFormatter::GREGORIAN, 'MMMM yyyy');
        													echo ucfirst($formatter->format(new DateTime()));?>
						</h3>
						<div class="card-tools">							
							<!-- Botón imprimir todos con confirmación -->
							<button type="button" 
									id="btnImprimirTodos" 
									class="btn btn-success btn-sm" 
									style="margin-right: 10px;">

								<i class="fas fa-print"></i> Imprimir Todos
								<span class="badge text-bg-light" id="contadorCarnets">
									<i class="fas fa-spinner fa-spin"></i>
								</span>
							</button>

							<button type="button" 
									id="btn-impresion-atrasada"
									class="btn btn-info btn-sm"
									style="margin-right: 10px;">
								<i class="fas fa-calendar-alt"></i> Impresion atrasada
							</button>

							<button type="button" 
									id="btn-reimprimir-carnets"
									class="btn btn-warning btn-sm">
								<i class="fas fa-redo"></i> Reimprimir Carnets
							</button>

							<span id="contador-seleccion" 
								class="badge text-bg-warning" 
								style="display: none; font-size: 14px; padding: 8px 12px;">
								0 carnets seleccionados
							</span>

							<button type="button" 
									id="btn-limpiar-seleccion"
									class="btn btn-secondary btn-sm" 
									style="display: none;">
								<i class="fas fa-times"></i> Limpiar Selección
							</button>
							
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					
					<div class="card-body">						
						<div class="alert alert-info">
							<i class="fas fa-info-circle"></i>
							<strong>Información:</strong> 
							Los carnets se generan automáticamente para todos los alumnos con pago de pensión del mes actual.
							Use los checkboxes para reimprimir carnets extraviados. <?php echo $cobrarReimpresion ? 'Se generara un cobro de $' . number_format($valorReimpresion, 2, '.', '') . ' por reimpresion.' : 'No se generara cobro por reimpresion.'; ?>
						</div>

						<?php if (!empty($sinHorario)): ?>
						<div class="alert alert-danger" role="alert">
							<div class="d-flex align-items-start gap-2">
								<i class="fas fa-exclamation-triangle mt-1" aria-hidden="true"></i>
								<div class="flex-grow-1">
									<strong>Horario pendiente:</strong>
									<?php $n = count($sinHorario); echo $n === 1
										? 'un alumno con pago de este mes no tiene horario de entrenamiento asignado.'
										: $n . ' alumnos con pago de este mes no tienen horario de entrenamiento asignado.'; ?>
									Asigne el horario desde la ficha de cada alumno y vuelva a esta vista para listar los carnets.
									<ul class="mb-0 mt-2 ps-3" style="columns: 18rem;">
										<?php foreach ($sinHorario as $al): ?>
										<li>
											<a class="alert-link" href="<?php echo APP_URL . 'alumnoUpdate/' . (int) $al['alumno_id'] . '/'; ?>" target="_blank" rel="noopener"
											   title="Abrir la ficha para asignar el horario">
												<?php echo htmlspecialchars(trim(preg_replace('/\s+/', ' ', $al['alumno_nombre'])), ENT_QUOTES, 'UTF-8'); ?></a>
											<small>(<?php echo htmlspecialchars($al['alumno_identificacion'], ENT_QUOTES, 'UTF-8'); ?>)</small>
										</li>
										<?php endforeach; ?>
									</ul>
								</div>
							</div>
						</div>
						<?php endif; ?>

						<form id="formReimpresion" class="FormularioAjax" data-form="save">
							<div class="table-responsive">
							<table id="example1" class="table table-bordered table-striped table-sm">
								<thead>
									<tr>
										<th>Identificación</th>
										<th>Nombres</th>
										<th>Apellidos</th>	
										<th>Fecha Últ Pensión</th>
										<th>Condición</th>
										<th>Ver Carnet</th>
										<th style="text-align: center;">
											<?php /* Bootstrap 5 sustituye custom-control/
													 custom-checkbox por form-check, y las clases
													 internas por form-check-input y
													 form-check-label. */ ?>
											<div class="form-check">
												<input class="form-check-input"
													   type="checkbox"
													   id="seleccionarTodos">
												<label for="seleccionarTodos" class="form-check-label">
													Reimprimir
												</label>
											</div>
										</th>
									</tr>
								</thead>
								<tbody>
									<?php echo $insCarnet->listarAlumnos($sinHorario); ?>								
								</tbody>
							</table>
							</div>
						</form>
					</div>
				</div>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>

      <!-- Control Sidebar -->
      <aside class="control-sidebar control-sidebar-dark"></aside>
    </div>

    <!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<!-- Bootstrap 4 -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.responsive.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/responsive.bootstrap5.min.js"></script>
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/carnet_seleccion.js?v=<?php echo filemtime(__DIR__ . '/../dist/js/carnet_seleccion.js'); ?>"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/carnet_list.js?v=<?php echo filemtime(__DIR__ . '/../dist/js/carnet_list.js'); ?>"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>"></script>
	<script>
		var APP_URL = '<?php echo APP_URL; ?>';
		var COBRAR_REIMPRESION = <?php echo $cobrarReimpresion ? 'true' : 'false'; ?>;
		var CARNETS_BLOQUEADOS = <?php echo empty($sinHorario) ? 'false' : 'true'; ?>;
		var MES_ATRASADO_DEFAULT = <?php echo (int)date('n', strtotime('first day of last month')); ?>;
		var ANIO_ATRASADO_DEFAULT = <?php echo (int)date('Y', strtotime('first day of last month')); ?>;
		var MES_ACTUAL = '<?php 
        $formatter = new IntlDateFormatter(
            "es_ES", IntlDateFormatter::NONE, IntlDateFormatter::NONE, 
            "America/Guayaquil", IntlDateFormatter::GREGORIAN, "MMMM yyyy"
        );
        echo ucfirst($formatter->format(new DateTime()));
    ?>';
	</script>
  </body>
</html>
