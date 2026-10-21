<?php
	use app\controllers\dashboardController;
	$insDashboard = new dashboardController();

	$ingresosLugar = $insDashboard->ingresosLugarEntr();
	$datos = $ingresosLugar->fetchAll();

	$sede = $lugar = $alumnos = $sinregpago = $alumnosad = $pensiones = $recaudado = [];

	foreach($datos as $rows){
		$sede[]      = $rows['sede_nombre'];
		$lugar[]     = $rows['lugar_nombre'];
		$alumnos[]   = (int)$rows['ALUMNOS_ENTRENAN'];
		$sinregpago[]= (int)$rows['ALUMNOS_SINREGPAGOS'];
		$alumnosad[] = (int)$rows['ALUMNOS_ADEUDAN'];
		$pensiones[] = (float)$rows['TOTALPENSIONES'];
		$recaudado[] = (float)$rows['TOTALRECAUDADO'];
	}

	$totalAlumnos    = array_sum($alumnos);
	$totalAlumnosAd  = array_sum($alumnosad);
	$totalAlSinPagos = array_sum($sinregpago);
	$totalPensiones  = array_sum($pensiones);
	$totalRecaudado  = array_sum($recaudado);
	$totalPorRecaudar= $totalPensiones - $totalRecaudado;
	$pctTotal        = $totalPensiones > 0 ? ($totalRecaudado / $totalPensiones * 100) : 0;

	$meses   = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
	$mesAnio = $meses[(int)date('n')] . ' ' . date('Y');
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Estadísticas';
	$extras      = array (0 => 'datatables',);
	$cabeceraExtra = <<<'CSS'
  <style>
    /* ── KPI cards ─────────────────────────────────── */
    .kpi-card            { border-left: 4px solid; position: relative; overflow: hidden; }
    .kpi-azul            { border-color: #3498db; }
    .kpi-rojo            { border-color: #e74c3c; }
    .kpi-naranja         { border-color: #e67e22; }
    .kpi-verde           { border-color: #2ecc71; }
    .kpi-amarillo        { border-color: #f1c40f; }
    .kpi-card .kpi-valor { font-size: 1.55rem; font-weight: 700; line-height: 1.2; }
    .kpi-card .kpi-label { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
    .kpi-card .kpi-icon  {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      font-size: 3.2rem; opacity: 0.08; pointer-events: none;
    }

    /* ── Badges % ──────────────────────────────────── */
    .badge-pct           { font-size: 0.82rem; padding: 3px 8px; border-radius: 12px; font-weight: 600; white-space: nowrap; }
    .pct-verde           { background: #d4edda; color: #155724; }
    .pct-amarillo        { background: #fff3cd; color: #856404; }
    .pct-rojo            { background: #f8d7da; color: #721c24; }

    /* ── Color filas ───────────────────────────────── */
    tr.fila-verde   td   { background-color: var(--bs-success-bg-subtle) !important; color: var(--bs-success-text-emphasis) !important; }
    tr.fila-amarillo td  { background-color: var(--bs-warning-bg-subtle) !important; color: var(--bs-warning-text-emphasis) !important; }
    tr.fila-rojo    td   { background-color: var(--bs-danger-bg-subtle) !important; color: var(--bs-danger-text-emphasis) !important; }

    /* ── Tabla ─────────────────────────────────────── */
    .tabla-estadisticas th {
      background-color: #343a40; color: #fff;
      text-align: center; vertical-align: middle; font-size: 0.8rem;
    }
    .tabla-estadisticas td  { vertical-align: middle; font-size: 0.86rem; }
    .tabla-estadisticas tfoot td { background-color: var(--bs-secondary-bg); color: var(--bs-body-color); font-weight: 700; text-align: center; }

    /* ── Mini progress-bar ─────────────────────────── */
    .prog-wrap           { min-width: 80px; }
    .prog-wrap .progress { height: 7px; border-radius: 4px; margin-top: 3px; }

    /* ── Botones icono ─────────────────────────────── */
    .boton-icono {
      width: 30px; height: 30px; background-color: transparent;
      background-size: contain; background-repeat: no-repeat;
      background-position: center; border: none; cursor: pointer;
      transition: transform 0.2s;
    }
    .boton-icono:hover   { transform: scale(1.15); }

    /* En tactil, del tamanio del pulgar. Medido: 30x30 (y 23x30 cuando el
       icono es estrecho), por debajo del minimo. No lleva la clase .btn de
       Bootstrap, asi que la regla general de ds_core no lo alcanza; se
       corrige aqui, junto a la definicion que hay que ganar. */
    @media (pointer: coarse) {
      .boton-icono { min-width: 44px; min-height: 44px; }
    }

    /* ── Barra progreso recaudo en KPI ─────────────── */
    .kpi-progress        { height: 6px; border-radius: 3px; margin-top: 6px; }
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
        <div class="row mb-1">
          <div class="col-sm-6">
            <h1 class="m-0">Estadísticas
              <small class="text-muted" style="font-size:0.55em; font-weight:400;">
                &nbsp;— <?php echo $mesAnio; ?>
              </small>
            </h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
              <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
              <li class="breadcrumb-item active">Estadísticas <?php echo $mesAnio; ?></li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="app-content">
      <div class="container-fluid">

        <?php if(empty($lugar)): ?>
        <!-- Estado vacío -->
        <div class="card card-default">
          <div class="card-body text-center py-5">
            <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">No hay datos disponibles para <?php echo $mesAnio; ?></h4>
            <p class="text-muted">Verifique que existan alumnos activos con lugares de entrenamiento asignados.</p>
          </div>
        </div>
        <?php else: ?>

        <!-- ── KPI cards ────────────────────────────────── -->
        <div class="row mb-3">

          <!-- Alumnos entrenando -->
          <div class="col-6 col-md-4 col-lg mb-2">
            <div class="card kpi-card kpi-azul h-100 mb-0">
              <div class="card-body py-3 px-3">
                <div class="kpi-label">Alumnos entrenando</div>
                <div class="kpi-valor text-primary"><?php echo $totalAlumnos; ?></div>
                <i class="fas fa-running kpi-icon text-primary"></i>
              </div>
            </div>
          </div>

          <!-- Adeudan -->
          <div class="col-6 col-md-4 col-lg mb-2">
            <div class="card kpi-card kpi-rojo h-100 mb-0">
              <div class="card-body py-3 px-3">
                <div class="kpi-label">Adeudan este mes</div>
                <div class="kpi-valor text-danger"><?php echo $totalAlumnosAd; ?></div>
                <i class="fas fa-exclamation-circle kpi-icon text-danger"></i>
              </div>
            </div>
          </div>

          <!-- Sin registro -->
          <div class="col-6 col-md-4 col-lg mb-2">
            <div class="card kpi-card kpi-naranja h-100 mb-0">
              <div class="card-body py-3 px-3">
                <div class="kpi-label">Sin registro pagos</div>
                <div class="kpi-valor text-warning"><?php echo $totalAlSinPagos; ?></div>
                <i class="fas fa-user-clock kpi-icon text-warning"></i>
              </div>
            </div>
          </div>

          <!-- Pensiones -->
          <div class="col-6 col-md-4 col-lg mb-2">
            <div class="card kpi-card kpi-verde h-100 mb-0">
              <div class="card-body py-3 px-3">
                <div class="kpi-label">Total pensiones est.</div>
                <div class="kpi-valor text-success">$<?php echo number_format($totalPensiones, 2); ?></div>
                <i class="fas fa-file-invoice-dollar kpi-icon text-success"></i>
              </div>
            </div>
          </div>

          <!-- Recaudado + barra de progreso -->
          <div class="col-6 col-md-4 col-lg mb-2">
            <div class="card kpi-card kpi-amarillo h-100 mb-0">
              <div class="card-body py-3 px-3">
                <div class="kpi-label">Total recaudado</div>
                <div class="kpi-valor" style="color:#b8860b;">$<?php echo number_format($totalRecaudado, 2); ?></div>
                <?php $badgeClass = $pctTotal >= 80 ? 'pct-verde' : ($pctTotal >= 50 ? 'pct-amarillo' : 'pct-rojo');
                      $barColor   = $pctTotal >= 80 ? 'bg-success' : ($pctTotal >= 50 ? 'bg-warning'  : 'bg-danger'); ?>
                <div class="mt-1">
                  <span class="badge-pct <?php echo $badgeClass; ?>"><?php echo number_format($pctTotal, 1); ?>% recaudado</span>
                </div>
                <i class="fas fa-hand-holding-usd kpi-icon" style="color:#b8860b;"></i>
              </div>
            </div>
          </div>

        </div>

        <!-- ── Card tabla ────────────────────────────────── -->
        <div class="card card-default">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-table me-1"></i> Detalle por lugar de entrenamiento</h3>
            <div class="card-tools d-flex align-items-center flex-wrap" style="gap:6px;">
              <!-- Leyenda semáforo -->
              <span class="badge-pct pct-verde d-none d-md-inline"><i class="fas fa-circle me-1"></i> ≥ 80%</span>
              <span class="badge-pct pct-amarillo d-none d-md-inline"><i class="fas fa-circle me-1"></i> 50–79%</span>
              <span class="badge-pct pct-rojo d-none d-md-inline"><i class="fas fa-circle me-1"></i> &lt; 50%</span>
              <div style="width:1px; height:20px; background:#dee2e6;" class="d-none d-md-inline-block mx-1"></div>
              <button onclick="exportarTablaAExcel('tablaDatos','SC-Estadisticas-<?php echo date('Y-m'); ?>')"
                      class="boton-icono"
                      style="background-image: url('<?php echo APP_URL; ?>app/views/imagenes/iconos/Excel.png');"
                      title="Exportar a Excel"></button>
              <button onclick="exportarTablaAPDF('tablaDatos','SC-Estadisticas-<?php echo date('Y-m'); ?>')"
                      class="boton-icono"
                      style="background-image: url('<?php echo APP_URL; ?>app/views/imagenes/iconos/Pdf.png');"
                      title="Exportar a PDF"></button>
              <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table id="tablaDatos" class="table table-bordered table-sm tabla-estadisticas mb-0">
                <thead>
                  <tr>
                    <th>Sede</th>
                    <th>Lugar de Entrenamiento</th>
                    <th>Alumnos<br>Entrenando</th>
                    <th>Adeudan<br>el mes</th>
                    <th>Sin registro<br>de pagos</th>
                    <th>Pensiones<br>Estimadas ($)</th>
                    <th>Recaudado ($)</th>
                    <th>Por recaudar ($)</th>
                    <th>% Recaudado</th>
                  </tr>
                </thead>
                <tbody>
                  <?php for ($i = 0; $i < count($lugar); $i++):
                    $pct         = $pensiones[$i] > 0 ? ($recaudado[$i] / $pensiones[$i] * 100) : 0;
                    $porRecaudar = $pensiones[$i] - $recaudado[$i];
                    $filaClass   = $pct >= 80 ? 'fila-verde'   : ($pct >= 50 ? 'fila-amarillo'  : 'fila-rojo');
                    $badgeClass  = $pct >= 80 ? 'pct-verde'    : ($pct >= 50 ? 'pct-amarillo'   : 'pct-rojo');
                    $barColor    = $pct >= 80 ? 'bg-success'   : ($pct >= 50 ? 'bg-warning'     : 'bg-danger');
                  ?>
                  <tr class="<?php echo $filaClass; ?>">
                    <td><?php echo $sede[$i]; ?></td>
                    <td><?php echo $lugar[$i]; ?></td>
                    <td class="text-center"><?php echo $alumnos[$i]; ?></td>
                    <td class="text-center"><?php echo $alumnosad[$i]; ?></td>
                    <td class="text-center"><?php echo $sinregpago[$i]; ?></td>
                    <td class="text-end">$<?php echo number_format($pensiones[$i], 2); ?></td>
                    <td class="text-end">$<?php echo number_format($recaudado[$i], 2); ?></td>
                    <td class="text-end <?php echo $porRecaudar > 0 ? 'text-danger' : 'text-success'; ?>">
                      <?php echo $porRecaudar > 0 ? '$'.number_format($porRecaudar, 2) : '<i class="fas fa-check-circle text-success"></i>'; ?>
                    </td>
                    <td class="text-center">
                      <div class="prog-wrap">
                        <span class="badge-pct <?php echo $badgeClass; ?>"><?php echo number_format($pct, 1); ?>%</span>
                      </div>
                    </td>
                  </tr>
                  <?php endfor; ?>
                </tbody>
                <tfoot>
                  <?php
                    $totalPorRecaudar = $totalPensiones - $totalRecaudado;
                    $badgeTotal = $pctTotal >= 80 ? 'pct-verde' : ($pctTotal >= 50 ? 'pct-amarillo' : 'pct-rojo');
                    $barTotal   = $pctTotal >= 80 ? 'bg-success' : ($pctTotal >= 50 ? 'bg-warning'  : 'bg-danger');
                  ?>
                  <tr>
                    <td colspan="2" class="text-end">Totales:</td>
                    <td><?php echo $totalAlumnos; ?></td>
                    <td><?php echo $totalAlumnosAd; ?></td>
                    <td><?php echo $totalAlSinPagos; ?></td>
                    <td class="text-end">$<?php echo number_format($totalPensiones, 2); ?></td>
                    <td class="text-end">$<?php echo number_format($totalRecaudado, 2); ?></td>
                    <td class="text-end <?php echo $totalPorRecaudar > 0 ? 'text-danger' : 'text-success'; ?>">
                      $<?php echo number_format($totalPorRecaudar, 2); ?>
                    </td>
                    <td class="text-center">
                      <div class="prog-wrap">
                        <span class="badge-pct <?php echo $badgeTotal; ?>"><?php echo number_format($pctTotal, 1); ?>%</span>
                      </div>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- ── Card gráficos ─────────────────────────────── -->
        <div class="card card-default">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar me-1"></i> Gráficos por lugar de entrenamiento</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <!-- Gráfico 1: Monetario -->
              <div class="col-12 col-xl-6 mb-3 mb-xl-0">
                <p class="text-center text-muted mb-1" style="font-size:0.82rem; text-transform:uppercase; letter-spacing:.5px;">
                  <i class="fas fa-dollar-sign me-1"></i> Pensiones estimadas vs. Recaudado ($)
                </p>
                <canvas id="graficoMonetario"></canvas>
              </div>
              <!-- Gráfico 2: Conteos -->
              <div class="col-12 col-xl-6">
                <p class="text-center text-muted mb-1" style="font-size:0.82rem; text-transform:uppercase; letter-spacing:.5px;">
                  <i class="fas fa-users me-1"></i> Alumnos entrenando / adeudan / sin registro
                </p>
                <canvas id="graficoAlumnos"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Leyenda semáforo mobile (visible solo en móvil) -->
        <div class="d-flex d-md-none mb-3" style="gap:8px; flex-wrap:wrap;">
          <span class="badge-pct pct-verde"><i class="fas fa-circle me-1"></i> ≥ 80% recaudado</span>
          <span class="badge-pct pct-amarillo"><i class="fas fa-circle me-1"></i> 50–79%</span>
          <span class="badge-pct pct-rojo"><i class="fas fa-circle me-1"></i> &lt; 50%</span>
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
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<!-- Exportar -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<?php if(!empty($lugar)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ── DataTables ────────────────────────────────────
  new DataTable("#tablaDatos", {
    "paging":       false,
    "lengthChange": false,
    "searching":    false,
    "ordering":     true,
    "info":         true,
    "autoWidth":    false,
    "responsive":   true,
    "order":        [[8, "asc"]],
    "columnDefs": [
      { "orderable": false, "targets": [8] }
    ],
    "language": {
      "emptyTable":   "No hay datos disponibles",
      "info":         "Mostrando _START_ a _END_ de _TOTAL_ entradas",
      "infoEmpty":    "Mostrando 0 a 0 de 0 entradas",
      "infoFiltered": "(filtrado de _MAX_ entradas totales)",
      "thousands":    ",",
      "zeroRecords":  "No se encontraron registros coincidentes",
			"buttons": { "copy":"Copiar","print":"Imprimir","colvis":"Columnas" }
    },
    layout: { topStart: 'buttons' },
    "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
  });
});

// ── Gráfico 1: Monetario ──────────────────────────
const ctxMon = document.getElementById('graficoMonetario').getContext('2d');
new Chart(ctxMon, {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($lugar); ?>,
    datasets: [
      {
        label: 'Pensiones estimadas ($)',
        backgroundColor: 'rgba(46,204,113,0.75)',
        borderColor: 'rgba(46,204,113,1)',
        borderWidth: 1,
        data: <?php echo json_encode($pensiones); ?>
      },
      {
        label: 'Recaudado ($)',
        backgroundColor: 'rgba(241,196,15,0.75)',
        borderColor: 'rgba(241,196,15,1)',
        borderWidth: 1,
        data: <?php echo json_encode($recaudado); ?>
      }
    ]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: {
      legend: { position: 'top' },
      datalabels: {
        color: '#333', font: { weight: 'bold', size: 10 },
        anchor: 'end', align: 'right',
        display: function(ctx) { return ctx.dataset.data[ctx.dataIndex] > 0; },
        formatter: function(v) {
          return '$' + v.toLocaleString('es-EC', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
      },
      tooltip: {
        callbacks: {
          label: function(ctx) {
            return ctx.dataset.label + ': $' +
              ctx.parsed.x.toLocaleString('es-EC', { minimumFractionDigits: 2 });
          }
        }
      }
    },
    scales: {
      x: { beginAtZero: true, ticks: { display: false } },
      y: { beginAtZero: true }
    }
  },
  plugins: [ChartDataLabels]
});

// ── Gráfico 2: Conteos alumnos ────────────────────
const ctxAlu = document.getElementById('graficoAlumnos').getContext('2d');
new Chart(ctxAlu, {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($lugar); ?>,
    datasets: [
      {
        label: 'Alumnos entrenando',
        backgroundColor: 'rgba(52,152,219,0.75)',
        borderColor: 'rgba(52,152,219,1)',
        borderWidth: 1,
        data: <?php echo json_encode($alumnos); ?>
      },
      {
        label: 'Adeudan el mes',
        backgroundColor: 'rgba(231,76,60,0.75)',
        borderColor: 'rgba(231,76,60,1)',
        borderWidth: 1,
        data: <?php echo json_encode($alumnosad); ?>
      },
      {
        label: 'Sin registro de pagos',
        backgroundColor: 'rgba(230,126,34,0.75)',
        borderColor: 'rgba(230,126,34,1)',
        borderWidth: 1,
        data: <?php echo json_encode($sinregpago); ?>
      }
    ]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: {
      legend: { position: 'top' },
      datalabels: {
        color: '#333', font: { weight: 'bold', size: 10 },
        anchor: 'end', align: 'right',
        display: function(ctx) { return ctx.dataset.data[ctx.dataIndex] > 0; },
        formatter: function(v) { return v; }
      },
      tooltip: {
        callbacks: {
          label: function(ctx) { return ctx.dataset.label + ': ' + ctx.parsed.x; }
        }
      }
    },
    scales: {
      x: { beginAtZero: true, ticks: { display: false } },
      y: { beginAtZero: true }
    }
  },
  plugins: [ChartDataLabels]
});

// ── Exportar ──────────────────────────────────────
function exportarTablaAExcel(tablaID, nombreArchivo) {
  const libro = XLSX.utils.table_to_book(document.getElementById(tablaID), { sheet: "Estadísticas" });
  XLSX.writeFile(libro, nombreArchivo + ".xlsx");
}

async function exportarTablaAPDF(tablaID, nombreArchivo) {
  const canvas  = await html2canvas(document.getElementById(tablaID), { scale: 2 });
  const imgData = canvas.toDataURL('image/png');
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF({ orientation: 'landscape' });
  const w   = pdf.internal.pageSize.getWidth();
  pdf.addImage(imgData, 'PNG', 0, 10, w, (canvas.height * w) / canvas.width);
  pdf.save(nombreArchivo + ".pdf");
}
</script>
<?php endif; ?>

</body>
</html>
