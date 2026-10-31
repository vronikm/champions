<?php
	use app\controllers\reporteController;
	$insLEIngresos = new reporteController();

	// Solo se aceptan fechas Y-m-d validas; si no, se usa el mes actual
	$fechaValida = function($fecha){
		$d = DateTime::createFromFormat('Y-m-d', (string)$fecha);
		return $d && $d->format('Y-m-d') === $fecha;
	};

	$le_fecha_inicio = isset($_POST['le_fecha_inicio']) && $fechaValida($_POST['le_fecha_inicio'])
		? $_POST['le_fecha_inicio']
		: date('Y-m-01');

	$le_fecha_fin = isset($_POST['le_fecha_fin']) && $fechaValida($_POST['le_fecha_fin'])
		? $_POST['le_fecha_fin']
		: date('Y-m-t');

	if($le_fecha_inicio > $le_fecha_fin){
		[$le_fecha_inicio, $le_fecha_fin] = [$le_fecha_fin, $le_fecha_inicio];
	}

	$insIngresos = $insLEIngresos->ingresosMoraLugarEntr($le_fecha_inicio, $le_fecha_fin);
	$datos = $insIngresos->fetchAll();

	$sede = $lugar = $alumno = $estadoalumno = $fechaultpago = [];
	$situacion = $periodo = $concepto = $valor = $saldo = $mora = $mesesmora = $estadopago = [];

	foreach($datos as $rows){
		$sede[]        = $rows['SEDE'];
		$lugar[]       = $rows['LUGARENTRENAMIENTO'];
		$alumno[]      = $rows['ALUMNO'];
		$estadoalumno[]= $rows['ESTADOALUMNO'];
		$fechaultpago[]= $rows['FECHA_ULTPAGO'] ?? 'No Registra';
		$situacion[]   = $rows['SITUACION'];
		$periodo[]     = $rows['PAGO_PERIODO'];
		$concepto[]    = $rows['PAGO_CONCEPTO'];
		$valor[]       = (float)$rows['PAGO_VALOR'];
		$saldo[]       = (float)$rows['PAGO_SALDO'];
		$mora[]        = (float)$rows['PAGO_MORA'];
		$mesesmora[]   = (int)$rows['MESES_MORA'];
		$estadopago[]  = $rows['ESTADOPAGO'];
	}

	// KPI totales (una fila por alumno activo)
	$totalRegistros  = count($lugar);
	$totalValor      = array_sum($valor);
	$totalSaldo      = array_sum($saldo);
	$totalMora       = array_sum($mora);
	$countMora       = count(array_filter($situacion, fn($s) => $s === 'EN MORA'));
	$countAlDia      = count(array_filter($situacion, fn($s) => $s === 'AL DÍA'));
	$countSinReg     = count(array_filter($situacion, fn($s) => $s === 'No Registra'));

	/* Subtotales por situación. Cada columna es una sola magnitud:
	   - Pagado -> pensiones cobradas dentro del período
	   - Saldo  -> saldos pendientes de pensiones registradas en el período
	   - Mora   -> deuda solo del período (meses del período sin pagar + saldos) */
	$subtotales = [];
	foreach(['AL DÍA', 'EN MORA', 'No Registra'] as $s){
		$subtotales[$s] = ['valor' => 0.0, 'saldo' => 0.0, 'mora' => 0.0];
	}
	for($i = 0; $i < $totalRegistros; $i++){
		$s = isset($subtotales[$situacion[$i]]) ? $situacion[$i] : 'No Registra';
		$subtotales[$s]['valor'] += $valor[$i];
		$subtotales[$s]['saldo'] += $saldo[$i];
		$subtotales[$s]['mora']  += $mora[$i];
	}

	// Etiqueta período
	$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
	$labelPeriodo = date('d', strtotime($le_fecha_inicio)) . ' ' . $meses[(int)date('n', strtotime($le_fecha_inicio))]
		. ' – ' . date('d', strtotime($le_fecha_fin))   . ' ' . $meses[(int)date('n', strtotime($le_fecha_fin))]
		. ' ' . date('Y', strtotime($le_fecha_fin));

	$nombreArchivo = ds_nombre_archivo('IngresosMora') . '-' . $le_fecha_inicio . '_' . $le_fecha_fin;
	// Argumentos JS seguros para los atributos onclick
	$jsArgsExport = htmlspecialchars(json_encode('tablaDatos') . ',' . json_encode($nombreArchivo), ENT_QUOTES, 'UTF-8');
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Ingresos y mora por sede';
	$extras      = array (0 => 'datatables',);
	$cabeceraExtra = <<<'CSS'
    <style>
      /* KPI cards */
      .kpi-card            { border-left: 4px solid; position: relative; overflow: hidden; }
      .kpi-azul            { border-color: #3498db; }
      .kpi-rojo            { border-color: #e74c3c; }
      .kpi-verde           { border-color: #2ecc71; }
      .kpi-naranja         { border-color: #e67e22; }
      .kpi-amarillo        { border-color: #f1c40f; }
      .kpi-gris            { border-color: #95a5a6; }
      .kpi-vino            { border-color: #c0392b; }
      .kpi-card .kpi-valor { font-size: 1.6rem; font-weight: 700; line-height: 1.2; white-space: nowrap; }
      .kpi-card .kpi-label { font-size: 0.78rem; color: #888; text-transform: uppercase; letter-spacing: .5px; }
      /* con 7 tarjetas en una fila, el importe no debe partirse en pantallas medianas.
         Va despues de las reglas base: un @media no suma especificidad. */
      @media (min-width: 992px) and (max-width: 1399px) {
        .kpi-card .kpi-valor { font-size: 1.25rem; }
        .kpi-card .kpi-label { font-size: 0.7rem; }
      }
      .kpi-card .kpi-icon  {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        font-size: 3.2rem; opacity: 0.08; pointer-events: none;
      }

      /* Badges de situación */
      .badge-sit           { font-size: 0.8rem; padding: 3px 9px; border-radius: 12px; font-weight: 600; white-space: nowrap; }
      .sit-mora            { background: #f8d7da; color: #721c24; }
      .sit-aldia           { background: #d4edda; color: #155724; }
      .sit-sinreg          { background: #fff3cd; color: #856404; }
      .sit-otro            { background: #e2e3e5; color: #383d41; }

      /* Badges de estado pago */
      .badge-ep            { font-size: 0.78rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
      .ep-completo         { background: #d4edda; color: #155724; }
      .ep-pendiente        { background: #fff3cd; color: #856404; }
      .ep-justificado      { background: #d1ecf1; color: #0c5460; }
      .ep-otro             { background: #e2e3e5; color: #383d41; }

      /* Color filas */
      tr.fila-mora td      { background-color: var(--bs-danger-bg-subtle) !important; color: var(--bs-danger-text-emphasis) !important; }
      tr.fila-aldia td     { background-color: var(--bs-success-bg-subtle) !important; color: var(--bs-success-text-emphasis) !important; }
      tr.fila-sinreg td    { background-color: var(--bs-warning-bg-subtle) !important; color: var(--bs-warning-text-emphasis) !important; }

      /* Tabla */
      .tabla-mora th       { background-color: #343a40; color: #fff; text-align: center; vertical-align: middle; font-size: 0.8rem; }
      .tabla-mora td       { vertical-align: middle; font-size: 0.85rem; }
      .tabla-mora tfoot td { background-color: var(--bs-secondary-bg); color: var(--bs-body-color); font-weight: 700; text-align: center; }

      /* Botones icono */
      .boton-icono {
        width: 30px; height: 30px;
        background-color: transparent; background-size: contain;
        background-repeat: no-repeat; background-position: center;
        border: none; cursor: pointer; transition: transform 0.2s;
      }
      .boton-icono:hover { transform: scale(1.15); }

    /* En tactil, del tamanio del pulgar. Medido: 30x30 (y 23x30 cuando el
       icono es estrecho), por debajo del minimo. No lleva la clase .btn de
       Bootstrap, asi que la regla general de ds_core no lo alcanza; se
       corrige aqui, junto a la definicion que hay que ganar. */
    @media (pointer: coarse) {
      .boton-icono { min-width: 44px; min-height: 44px; }
    }
    </style>
CSS;
	require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">

      <?php require_once "app/views/inc/navbar.php"; ?>
      <?php require_once "app/views/inc/main-sidebar.php"; ?>

      <div class="app-main">

        <!-- Content Header -->
        <div class="app-content-header">
          <div class="container-fluid">
            <div class="row mb-2">
              <div class="col-sm-7">
                <h1 class="m-0">Ingresos y mora por lugar de entrenamiento
                  <small class="text-muted" style="font-size:0.5em; font-weight:400;">
                    &nbsp;— Alumnos activos, <?php echo $labelPeriodo; ?>
                  </small>
                </h1>
              </div>
              <div class="col-sm-5">
                <ol class="breadcrumb float-sm-end">
                  <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
                  <li class="breadcrumb-item active">Ingresos y mora</li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <section class="app-content">
          <div class="container-fluid">

            <!-- ── Card filtro ─────────────────────────────────── -->
            <form action="<?php echo APP_URL."reporteIngresosMorames/" ?>" method="POST" autocomplete="off">
              <div class="card card-default">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-filter me-1"></i> Período de consulta</h3>
                  <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row align-items-end">
                    <div class="col-md-3">
                      <div class="mb-3 mb-0">
                        <label for="le_fecha_inicio">Fecha inicio</label>
                        <div class="input-group">
                                                  <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                      
                          <input type="date" class="form-control" id="le_fecha_inicio" name="le_fecha_inicio"
                                 value="<?php echo $le_fecha_inicio; ?>" required>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="mb-3 mb-0">
                        <label for="le_fecha_fin">Fecha fin</label>
                        <div class="input-group">
                                                  <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                      
                          <input type="date" class="form-control" id="le_fecha_fin" name="le_fecha_fin"
                                 value="<?php echo $le_fecha_fin; ?>" required>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-2">
                      <button type="submit" class="btn btn-info w-100 mt-1">
                        <i class="fas fa-search me-1"></i> Buscar
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </form>

            <?php if(empty($lugar)): ?>
            <!-- Estado vacío -->
            <div class="card card-default">
              <div class="card-body text-center py-5">
                <i class="fas fa-search-dollar fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Sin resultados para el período seleccionado</h4>
                <p class="text-muted">No se encontraron registros entre
                  <strong><?php echo $le_fecha_inicio; ?></strong> y
                  <strong><?php echo $le_fecha_fin; ?></strong>.
                </p>
              </div>
            </div>
            <?php else: ?>

            <!-- ── KPI cards ──────────────────────────────────── -->
            <div class="row mb-3">
              <div class="col-6 col-md-4 col-lg mb-2">
                <div class="card kpi-card kpi-azul h-100 mb-0">
                  <div class="card-body py-3 px-3">
                    <div class="kpi-label">Alumnos activos</div>
                    <div class="kpi-valor text-primary"><?php echo $totalRegistros; ?></div>
                    <i class="fas fa-list-alt kpi-icon text-primary"></i>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-4 col-lg mb-2">
                <div class="card kpi-card kpi-rojo h-100 mb-0">
                  <div class="card-body py-3 px-3">
                    <div class="kpi-label">En mora</div>
                    <div class="kpi-valor text-danger"><?php echo $countMora; ?></div>
                    <i class="fas fa-exclamation-triangle kpi-icon text-danger"></i>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-4 col-lg mb-2">
                <div class="card kpi-card kpi-verde h-100 mb-0">
                  <div class="card-body py-3 px-3">
                    <div class="kpi-label">Al día</div>
                    <div class="kpi-valor text-success"><?php echo $countAlDia; ?></div>
                    <i class="fas fa-check-circle kpi-icon text-success"></i>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-4 col-lg mb-2">
                <div class="card kpi-card kpi-naranja h-100 mb-0">
                  <div class="card-body py-3 px-3">
                    <div class="kpi-label">Sin registro pagos</div>
                    <div class="kpi-valor text-warning"><?php echo $countSinReg; ?></div>
                    <i class="fas fa-user-clock kpi-icon text-warning"></i>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-4 col-lg mb-2">
                <div class="card kpi-card kpi-amarillo h-100 mb-0">
                  <div class="card-body py-3 px-3">
                    <div class="kpi-label">Recaudado período</div>
                    <div class="kpi-valor" style="color:#b8860b;">$<?php echo number_format($totalValor, 2); ?></div>
                    <i class="fas fa-money-bill-wave kpi-icon" style="color:#b8860b;"></i>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-4 col-lg mb-2">
                <div class="card kpi-card kpi-vino h-100 mb-0">
                  <div class="card-body py-3 px-3">
                    <div class="kpi-label">Total en mora</div>
                    <div class="kpi-valor" style="color:#c0392b;">$<?php echo number_format($totalMora, 2); ?></div>
                    <i class="fas fa-hand-holding-usd kpi-icon" style="color:#c0392b;"></i>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-4 col-lg mb-2">
                <div class="card kpi-card kpi-gris h-100 mb-0">
                  <div class="card-body py-3 px-3">
                    <div class="kpi-label">T. saldo pendiente</div>
                    <div class="kpi-valor text-secondary">$<?php echo number_format($totalSaldo, 2); ?></div>
                    <i class="fas fa-hourglass-half kpi-icon text-secondary"></i>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── Card tabla ─────────────────────────────────── -->
            <div class="card card-default">
              <div class="card-header">
                <h3 class="card-title">
                  <i class="fas fa-table me-1"></i> Detalle de alumnos con pagos y mora
                </h3>
                <div class="card-tools d-flex align-items-center" style="gap:6px;">
                  <!-- Leyenda semáforo -->
                  <span class="badge-sit sit-mora"><i class="fas fa-circle me-1"></i> En mora</span>
                  <span class="badge-sit sit-aldia" style="margin-left:4px;"><i class="fas fa-circle me-1"></i> Al día</span>
                  <span class="badge-sit sit-sinreg" style="margin-left:4px;"><i class="fas fa-circle me-1"></i> Sin registro de pagos</span>
                  <button onclick="exportarTablaAExcel(<?php echo $jsArgsExport; ?>)"
                          class="boton-icono"
                          style="background-image: url('<?php echo APP_URL; ?>app/views/imagenes/iconos/Excel.png');"
                          title="Exportar a Excel"></button>
                  <button onclick="exportarTablaAPDF(<?php echo $jsArgsExport; ?>)"
                          class="boton-icono"
                          style="background-image: url('<?php echo APP_URL; ?>app/views/imagenes/iconos/Pdf.png');"
                          title="Exportar a PDF"></button>
                  <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table id="tablaDatos" class="table table-bordered table-sm tabla-mora mb-0">
                    <thead>
                      <tr>
                        <th>Sede</th>
                        <th>Lugar de Entrenamiento</th>
                        <th>Alumno</th>
                        <th>F. Último Pago</th>
                        <th>Situación</th>
                        <th>Período Pagado</th>
                        <th>Concepto Pago</th>
                        <th>Pagado ($)</th>
                        <th>Saldo ($)</th>
                        <th>Mora ($)</th>
                        <th>Estado Pago</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php for ($i = 0; $i < count($lugar); $i++):
                        // Clase de fila según situación
                        $sit = $situacion[$i];
                        if ($sit === 'EN MORA')       $filaClass = 'fila-mora';
                        elseif ($sit === 'AL DÍA')    $filaClass = 'fila-aldia';
                        else                           $filaClass = 'fila-sinreg';

                        // Badge situación
                        if ($sit === 'EN MORA')       $sitClass = 'sit-mora';
                        elseif ($sit === 'AL DÍA')    $sitClass = 'sit-aldia';
                        elseif ($sit === 'No Registra') $sitClass = 'sit-sinreg';
                        else                           $sitClass = 'sit-otro';

                        // Badge estado pago
                        $ep = $estadopago[$i];
                        if ($ep === 'Completo')        $epClass = 'ep-completo';
                        elseif ($ep === 'Pendiente')   $epClass = 'ep-pendiente';
                        elseif ($ep === 'Justificado') $epClass = 'ep-justificado';
                        else                           $epClass = 'ep-otro';
                      ?>
                      <tr class="<?php echo $filaClass; ?>">
                        <td><?php echo $sede[$i]; ?></td>
                        <td><?php echo $lugar[$i]; ?></td>
                        <td><?php echo $alumno[$i]; ?></td>
                        <td class="text-center"><?php echo $fechaultpago[$i]; ?></td>
                        <td class="text-center">
                          <span class="badge-sit <?php echo $sitClass; ?>"><?php echo $sit; ?></span>
                        </td>
                        <td class="text-center"><?php echo $periodo[$i]; ?></td>
                        <td><?php echo $concepto[$i]; ?></td>
                        <td class="text-end"><?php echo $valor[$i] > 0 ? '$'.number_format($valor[$i], 2) : '—'; ?></td>
                        <td class="text-end"><?php echo $saldo[$i] > 0 ? '$'.number_format($saldo[$i], 2) : '—'; ?></td>
                        <td class="text-end">
                          <?php if($mora[$i] > 0): ?>
                            $<?php echo number_format($mora[$i], 2); ?>
                            <?php if($mesesmora[$i] > 0): ?>
                              <br><small class="text-muted"><?php echo $mesesmora[$i].($mesesmora[$i] == 1 ? ' mes' : ' meses'); ?></small>
                            <?php endif; ?>
                          <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-center">
                          <?php if($ep): ?>
                            <span class="badge-ep <?php echo $epClass; ?>"><?php echo $ep; ?></span>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endfor; ?>
                    </tbody>
                    <tfoot>
                      <?php
                        $filasSubtotal = [
                          ['AL DÍA',      'fila-aldia',  'Subtotal al día',      $countAlDia],
                          ['EN MORA',     'fila-mora',   'Subtotal en mora',     $countMora],
                          ['No Registra', 'fila-sinreg', 'Subtotal sin registro', $countSinReg],
                        ];
                        foreach($filasSubtotal as [$sitKey, $cls, $etiqueta, $cuenta]):
                          if($cuenta == 0) continue;
                      ?>
                      <tr class="<?php echo $cls; ?>">
                        <td colspan="7" class="text-end"><?php echo $etiqueta; ?> (<?php echo $cuenta; ?>):</td>
                        <td class="text-end">$<?php echo number_format($subtotales[$sitKey]['valor'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format($subtotales[$sitKey]['saldo'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format($subtotales[$sitKey]['mora'], 2); ?></td>
                        <td></td>
                      </tr>
                      <?php endforeach; ?>
                      <tr>
                        <td colspan="7" class="text-end">Totales (<?php echo $totalRegistros; ?>):</td>
                        <td class="text-end">$<?php echo number_format($totalValor, 2); ?></td>
                        <td class="text-end">$<?php echo number_format($totalSaldo, 2); ?></td>
                        <td class="text-end">$<?php echo number_format($totalMora, 2); ?></td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>

            <!-- Leyenda semáforo -->
            <div class="mb-3" style="display:flex; gap:12px; flex-wrap:wrap;">
              <span class="badge-sit sit-mora"><i class="fas fa-circle me-1"></i> En mora</span>
              <span class="badge-sit sit-aldia" style="margin-left:4px;"><i class="fas fa-circle me-1"></i> Al día</span>
              <span class="badge-sit sit-sinreg" style="margin-left:4px;"><i class="fas fa-circle me-1"></i> Sin registro de pagos</span>
            </div>

            <?php endif; ?>

          </div>
        </section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
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
    <script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.buttons.min.js"></script>
    <script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/buttons.bootstrap5.min.js"></script>
    <script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/buttons.html5.min.js"></script>
	<?php /* pdfmake y jszip pesan 2,2 MB y sirven a dos botones: se traen
			 al pulsarlos, no en cada carga. Va DESPUES de buttons.html5, que es
			 quien define esos botones. */ ?>
	<script src="<?php echo ds_recurso('ds_core/assets/js/exportar.js'); ?>"></script>
    <script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/buttons.print.min.js"></script>
    <script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/buttons.colVis.min.js"></script>
    <!-- AdminLTE -->
    <script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
    <!-- Exportar -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="<?php echo ds_recurso('ds_core/assets/js/pie-pdf.js'); ?>" data-pie="<?php echo htmlspecialchars(ds_pie_pdf_texto(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <?php if(!empty($lugar)): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      new DataTable("#tablaDatos", {
        "paging":       true,
        "lengthChange": true,
        "searching":    true,
        "ordering":     true,
        "info":         true,
        "autoWidth":    false,
        "responsive":   true,
        "pageLength":   25,
        "order":        [[4, "asc"]],   // Ordenar por Situación por defecto
        "language": {
          "emptyTable":    "No hay datos disponibles",
          "info":          "Mostrando _START_ a _END_ de _TOTAL_ entradas",
          "infoEmpty":     "Mostrando 0 a 0 de 0 entradas",
          "infoFiltered":  "(filtrado de _MAX_ entradas totales)",
          "thousands":     ",",
          "lengthMenu":    "Mostrar _MENU_ entradas",
          "loadingRecords":"Cargando...",
          "processing":    "Procesando...",
          "search":        "Buscar:",
          "zeroRecords":   "No se encontraron registros coincidentes",
          "paginate": { "first":"Primero","last":"Último","next":"Siguiente","previous":"Anterior" },
			"buttons": { "copy":"Copiar","print":"Imprimir","colvis":"Columnas" }
        },
        layout: { topStart: 'buttons' },
        "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
      });
    });

    function exportarTablaAExcel(tablaID, nombreArchivo) {
      const tabla = document.getElementById(tablaID);
      const libro = XLSX.utils.table_to_book(tabla, { sheet: "Mora" });
      XLSX.writeFile(libro, nombreArchivo + ".xlsx");
    }

    async function exportarTablaAPDF(tablaID, nombreArchivo) {
      const tabla = document.getElementById(tablaID);
      const canvas = await html2canvas(tabla, { scale: 2 });
      const imgData = canvas.toDataURL('image/png');
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF({ orientation: 'landscape' });
      const imgProps = pdf.getImageProperties(imgData);
      const pdfWidth  = pdf.internal.pageSize.getWidth();
      const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
      pdf.addImage(imgData, 'PNG', 0, 10, pdfWidth, pdfHeight);
      dsPiePdf(pdf);   /* pie de Core › Organización */
      pdf.save(nombreArchivo + ".pdf");
    }
    </script>
    <?php endif; ?>

  </body>
</html>
