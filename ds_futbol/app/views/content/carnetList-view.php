<?php
	use app\controllers\carnetController;
	$insCarnet = new carnetController();

	/*
	| Carnets del mes.
	|
	| La sede elegida (GET ?sede=, 0 = todas las del usuario) acota la lista Y
	| la emisión: «Imprimir pendientes» imprime los de esa sede, o los de todas.
	| El servidor acota siempre a las sedes del usuario (condicionSede).
	|
	| La lista trae sólo a los alumnos con horario asignado: son los que se
	| imprimen. Los que tienen pago y no horario se avisan encima de la tabla,
	| nunca dentro (DataTables no admite filas con colspan). Antes uno solo sin
	| horario vaciaba la lista entera.
	*/
	$sedeId       = (int)($_GET['sede'] ?? 0);
	$opcionesSede = $insCarnet->opcionesSedeCarnet($sedeId);
	$ofrecerTodas = sedes_de_la_sesion() === null || substr_count($opcionesSede, '<option') > 1;
	if (!$ofrecerTodas && $sedeId === 0 && preg_match('/value="(\d+)"/', $opcionesSede, $m)) { $sedeId = (int)$m[1]; }
	$nombreSede   = $sedeId > 0 && preg_match('/value="' . $sedeId . '"[^>]*>([^<]*)</', $opcionesSede, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : 'Todas las sedes';

	$cobrarReimpresion = $insCarnet->cobrarReimpresionCarnet();
	$valorReimpresion  = $insCarnet->valorReimpresionCarnet();
	$sinHorario = $insCarnet->verificarAlumnosSinHorarioAsignado($sedeId);
	/* «Por imprimir» es la cuenta del servidor, la misma que usa la impresión. */
	$alumnos     = $insCarnet->alumnosCarnetMes($sedeId);
	$impresos    = count(array_filter($alumnos, static fn($a) => !empty($a['fecha_impresion'])));
	$porImprimir = (int)($insCarnet->carnetPendientesImpresion($sedeId)[0]['total'] ?? 0);

	$formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'America/Guayaquil', IntlDateFormatter::GREGORIAN, 'MMMM yyyy');
	$mesActual = ucfirst($formatter->format(new DateTime()));

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$fecha = static fn($v) => $v ? date('d/m/Y', strtotime((string)$v)) : '';
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Carnets';
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
						<h4 class="m-0">Carnets del mes</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Carnets</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud ds-carnet">

				<!-- =================== Emisión =================== -->
				<div class="card ds-carnet__emision">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-id-card me-2" aria-hidden="true"></i>Emisión de <?php echo $h($mesActual); ?></h3>
					</div>
					<div class="card-body">
						<form method="GET" action="<?php echo APP_URL; ?>carnetList/" class="row g-2 align-items-end mb-3" id="formSede">
							<div class="col-12 col-sm-6 col-lg-4">
								<label for="sede" class="form-label">Sede</label>
								<select class="form-select" id="sede" name="sede" data-autoenvio>
									<?php if ($ofrecerTodas): ?>
										<option value="0" <?php echo $sedeId === 0 ? 'selected' : ''; ?>>Todas las sedes</option>
									<?php endif; ?>
									<?php echo $opcionesSede; ?>
								</select>
							</div>
							<noscript><div class="col-auto"><button type="submit" class="btn btn-primary">Ver</button></div></noscript>
						</form>

						<div class="ds-carnet__cifras" aria-label="Resumen del mes">
							<div><span class="ds-carnet__cifra"><?php echo count($alumnos); ?></span><span class="ds-carnet__rotulo">con carnet este mes</span></div>
							<div><span class="ds-carnet__cifra text-success-emphasis"><?php echo $impresos; ?></span><span class="ds-carnet__rotulo">impresos</span></div>
							<div><span class="ds-carnet__cifra text-warning-emphasis" id="contadorCarnets"><?php echo $porImprimir; ?></span><span class="ds-carnet__rotulo">por imprimir</span></div>
						</div>

						<div class="ds-carnet__acciones">
							<button type="button" id="btnImprimirTodos" class="btn btn-success">
								<i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir pendientes
								<span class="ds-carnet__alcance"><?php echo $sedeId > 0 ? '· ' . $h($nombreSede) : '· todas las sedes'; ?></span>
							</button>
							<button type="button" id="btn-impresion-atrasada" class="btn btn-secondary">
								<i class="fas fa-calendar-alt me-1" aria-hidden="true"></i>Impresión atrasada
							</button>
						</div>

						<p class="small text-body-secondary mb-0 mt-3">
							<i class="fas fa-info-circle me-1" aria-hidden="true"></i>
							Llevan carnet los alumnos con la pensión del mes pagada o beca completa. Para reponer uno extraviado,
							márquelo con «Reimprimir».
							<?php echo $cobrarReimpresion ? 'La reimpresión genera un cobro de $' . number_format($valorReimpresion, 2, '.', '') . '.' : 'La reimpresión no genera cobro.'; ?>
						</p>
					</div>
				</div>

				<?php if (!empty($sinHorario)): ?>
				<div class="alert alert-warning" role="alert" id="avisoSinHorario">
					<div class="d-flex align-items-start gap-2">
						<i class="fas fa-exclamation-triangle mt-1" aria-hidden="true"></i>
						<div class="flex-grow-1 min-w-0">
							<strong>Horario pendiente:</strong>
							<?php $n = count($sinHorario); echo $n === 1
								? 'un alumno con pago de este mes no tiene horario de entrenamiento asignado.'
								: $n . ' alumnos con pago de este mes no tienen horario de entrenamiento asignado.'; ?>
							No aparecen en la lista ni se imprimen hasta tenerlo: asígnelo desde la ficha de cada alumno.
							<ul class="mb-0 mt-2 ps-3 ds-carnet__sin-horario">
								<?php foreach ($sinHorario as $al): ?>
								<li>
									<a class="alert-link" href="<?php echo APP_URL . 'alumnoUpdate/' . (int) $al['alumno_id'] . '/'; ?>" title="Abrir la ficha para asignar el horario">
										<?php echo $h(trim(preg_replace('/\s+/', ' ', $al['alumno_nombre']))); ?></a>
									<small>(<?php echo $h($al['alumno_identificacion']); ?>)</small>
								</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<!-- =================== Alumnos =================== -->
				<div class="card ds-crud__lista" id="carnets-mes">
					<div class="card-header">
						<h3 class="card-title">
							<i class="fas fa-users me-2" aria-hidden="true"></i>Alumnos
							<span class="badge text-bg-secondary ms-1"><?php echo count($alumnos); ?></span>
						</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<!-- Reimpresión: la selección se recuerda entre páginas (carnet_seleccion.js) -->
						<div class="ds-carnet__seleccion">
							<div class="form-check mb-0">
								<input class="form-check-input" type="checkbox" id="seleccionarTodos">
								<label class="form-check-label" for="seleccionarTodos">Marcar los impresos de esta página</label>
							</div>
							<span id="contador-seleccion" class="badge text-bg-warning" style="display: none;">0 carnets seleccionados</span>
							<div class="ds-carnet__seleccion-botones">
								<button type="button" id="btn-limpiar-seleccion" class="btn btn-sm btn-secondary" style="display: none;">
									<i class="fas fa-times me-1" aria-hidden="true"></i>Limpiar
								</button>
								<button type="button" id="btn-reimprimir-carnets" class="btn btn-sm btn-warning" disabled>
									<i class="fas fa-redo me-1" aria-hidden="true"></i>Reimprimir seleccionados
								</button>
							</div>
						</div>

						<form id="formReimpresion">
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Alumno</th>
									<th class="ds-col-opcional">Sede</th>
									<th>Pensión</th>
									<th>Carnet</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($alumnos as $a):
								$id = (int)$a['alumno_id'];
								$impreso = !empty($a['fecha_impresion']);
							?>
								<tr>
									<td class="ds-ficha__principal" data-order="<?php echo $h($a['apellidos'] . ' ' . $a['nombres']); ?>">
										<div class="ds-ficha__nombre"><?php echo $h($a['apellidos'] . ' ' . $a['nombres']); ?></div>
										<div class="ds-ficha__meta"><?php echo $h($a['alumno_identificacion']); ?></div>
									</td>
									<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Sede"><?php echo $h($a['sede_nombre'] ?? ''); ?></td>
									<td class="ds-ficha__dato" data-etiqueta="Pensión" data-order="<?php echo $h($a['ultima_pension']); ?>">
										<?php echo $a['ultima_pension'] ? 'Pagada el ' . $fecha($a['ultima_pension']) : 'Beca completa'; ?>
									</td>
									<td class="ds-ficha__estado" data-order="<?php echo $impreso ? 1 : 0; ?>">
										<?php if ($impreso): ?>
											<span class="badge text-bg-success"><i class="fas fa-check me-1" aria-hidden="true"></i>Impreso</span>
											<div class="small text-body-secondary"><?php echo $fecha($a['fecha_impresion']); ?></div>
										<?php else: ?>
											<span class="badge text-bg-warning"><i class="fas fa-clock me-1" aria-hidden="true"></i>Por imprimir</span>
										<?php endif; ?>
									</td>
									<td class="ds-ficha__acciones">
										<div class="ds-ficha__botones">
											<a href="<?php echo APP_URL . 'carnetFotoPDF/' . $id . '/'; ?>" class="btn btn-sm btn-primary ds-ficha__primario" target="_blank" rel="noopener">
												<i class="fas fa-id-card me-1" aria-hidden="true"></i>Ver carnet
											</a>
											<?php if ($impreso): ?>
												<input type="checkbox" class="btn-check chk-reimpresion" id="alumno_<?php echo $id; ?>" name="pagos_seleccionados[]" value="<?php echo $id; ?>" autocomplete="off">
												<label class="btn btn-sm ds-carnet__reimprimir" for="alumno_<?php echo $id; ?>" title="Marcar para reimprimir">
													<i class="fas fa-redo me-1" aria-hidden="true"></i>Reimprimir
												</label>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						</form>
					</div>
				</div>

			</div>
		</section>
      </div>

      <!-- =================== Impresión atrasada ===================
           Carnets de un mes anterior que no se imprimieron. En el teléfono
           ocupa la pantalla completa; las cédulas se cuentan al escribirlas
           y los avisos del servidor salen como lista, no en una línea. -->
      <div class="modal fade ds-atrasada" id="modalAtrasada" tabindex="-1" aria-labelledby="modalAtrasadaTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
          <form class="modal-content" id="formAtrasada" novalidate autocomplete="off">
            <div class="modal-header">
              <h5 class="modal-title" id="modalAtrasadaTitulo"><i class="fas fa-calendar-alt me-2" aria-hidden="true"></i>Impresión atrasada</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
              <p class="small text-body-secondary">
                Para carnets de un mes anterior que no se imprimieron. Se imprimen con el color de ese mes y
                <strong>no generan cobro</strong>.
              </p>

              <div class="row g-2 mb-3">
                <div class="col-7">
                  <label for="atrasadaMes" class="form-label">Mes</label>
                  <select class="form-select" id="atrasadaMes" required>
                    <?php foreach (['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $i => $mes): ?>
                      <option value="<?php echo $i + 1; ?>" <?php echo (int)date('n', strtotime('first day of last month')) === $i + 1 ? 'selected' : ''; ?>><?php echo $mes; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-5">
                  <label for="atrasadaAnio" class="form-label">Año</label>
                  <select class="form-select" id="atrasadaAnio" required>
                    <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 2; $a--): ?>
                      <option value="<?php echo $a; ?>" <?php echo (int)date('Y', strtotime('first day of last month')) === $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
              </div>

              <label for="atrasadaCedulas" class="form-label">Cédulas de los alumnos</label>
              <textarea class="form-control" id="atrasadaCedulas" rows="4" inputmode="numeric" spellcheck="false"
                        placeholder="Una por línea, o separadas por comas o espacios" aria-describedby="atrasadaAyuda"></textarea>
              <div id="atrasadaAyuda" class="form-text">Puede pegarlas desde una hoja de cálculo.</div>
              <div class="ds-atrasada__cedulas mt-2" id="atrasadaDetectadas" aria-live="polite" hidden></div>
              <div class="invalid-feedback d-block" id="atrasadaError" hidden></div>

              <div class="ds-atrasada__resultado mt-3" id="atrasadaResultado" role="status" hidden></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary" id="atrasadaEnviar">
                <i class="fas fa-print me-1" aria-hidden="true"></i>Preparar PDF
              </button>
            </div>
          </form>
        </div>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive; en el teléfono, fichas -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script>
		var APP_URL = '<?php echo APP_URL; ?>';
		var COBRAR_REIMPRESION = <?php echo $cobrarReimpresion ? 'true' : 'false'; ?>;
		var MES_ATRASADO_DEFAULT = <?php echo (int)date('n', strtotime('first day of last month')); ?>;
		var ANIO_ATRASADO_DEFAULT = <?php echo (int)date('Y', strtotime('first day of last month')); ?>;
		var MES_ACTUAL = <?php echo json_encode($mesActual, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
		/* La emisión va por la sede elegida (0 = todas las del usuario). */
		var SEDE_CARNET = <?php echo $sedeId; ?>;
		var SEDE_NOMBRE = <?php echo json_encode($nombreSede, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
	</script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/carnet_seleccion.js?v=<?php echo filemtime(__DIR__ . '/../dist/js/carnet_seleccion.js'); ?>"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/carnet_list.js?v=<?php echo filemtime(__DIR__ . '/../dist/js/carnet_list.js'); ?>"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>"></script>
  </body>
</html>
