<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Financial Overview
|--------------------------------------------------------------------------
| El consolidado económico de los tres módulos: de dónde viene el dinero, en
| qué sede se generó, por qué concepto, cuánto se descuenta y qué queda por
| cobrar.
|
|
| TRES COSAS QUE ESTA PANTALLA DICE Y NO CALLA
|
| 1. League aparece como «fuera de sede», no repartido a prorrateo. Sus
|    torneos pueden organizarse fuera de las canchas del club: no tener sede
|    es cómo funciona el negocio, no un hueco que rellenar.
|
| 2. Las becas del 100 % se guardan con descuento_valor = 0,00, así que el
|    subsidio real que la escuela concede NO está en la suma de descuentos.
|    Se muestra aparte y rotulado como estimado: es una consecuencia
|    deducida de la pensión de la sede, no un importe registrado.
|
| 3. Los conceptos de cada módulo NO se unifican. «Pensión» y «Inscripción de
|    equipo» son cosas distintas; mezclarlas bajo una etiqueta común perdería
|    el sentido de ambas.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$p = $insInsights->periodo($_GET['desde'] ?? null, $_GET['hasta'] ?? null);

$ing       = $insInsights->ingresos($p);
$ingAnt    = $insInsights->ingresos($p, true);
$deuda     = $insInsights->porCobrar();
$porSede   = $insInsights->ingresosPorSede($p);
$conceptos = $insInsights->ingresosPorConcepto($p);
$dsc       = $insInsights->descuentos();
$ticket    = $insInsights->ticketPromedio($p);
$facturas  = $insInsights->facturacion($p);

$varIngresos = $insInsights->variacion($ing['total'], $ingAnt['total']);
$totalTrx    = array_sum(array_column($ticket, 'n'));
$dscTotal    = array_sum(array_map(static fn(array $d): float => (float) $d['v'], $dsc['registrados']));

$tituloVista = 'Financiero';
$vistaActual = 'financiero';

require_once __DIR__ . "/inc/layout-top.php";
?>

<!-- ==================== Periodo ==================== -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="desde" class="form-label mb-1 small text-muted">Desde</label>
                <input type="date" class="form-control form-control-sm" id="desde" name="desde"
                       value="<?php echo htmlspecialchars($p['desde'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-auto">
                <label for="hasta" class="form-label mb-1 small text-muted">Hasta</label>
                <input type="date" class="form-control form-control-sm" id="hasta" name="hasta"
                       value="<?php echo htmlspecialchars($p['hasta'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-filter me-1"></i>Aplicar
                </button>
            </div>
            <div class="col-auto ms-auto text-muted small">
                <?php echo (int) $p['dias']; ?> días · comparado con
                <strong><?php echo htmlspecialchars($p['antDesde'] . ' — ' . $p['antHasta'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- ==================== Las cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-success h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($ing['total'], 2); ?></h3>
                <p class="mb-0">Cobrado</p>
                <p class="mb-0 small opacity-75"><?php echo ds_variacion_sobre_color($varIngresos); ?></p>
            </div>
            <i class="fas fa-dollar-sign small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-warning h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($deuda['total'], 2); ?></h3>
                <p class="mb-0">Por cobrar</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75">saldo vivo</span></p>
            </div>
            <i class="fas fa-hand-holding-usd small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-danger h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($dscTotal, 2); ?></h3>
                <p class="mb-0">Descuentos vigentes</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75">sin contar becas del 100 %</span></p>
            </div>
            <i class="fas fa-tags small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo $totalTrx > 0 ? number_format($ing['total'] / $totalTrx, 2) : '0.00'; ?></h3>
                <p class="mb-0">Ticket promedio</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($totalTrx); ?> transacciones</span></p>
            </div>
            <i class="fas fa-receipt small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<div class="row">
    <!-- ==================== Ingresos por módulo ==================== -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Ingresos por módulo</h3>
            </div>
            <div class="card-body">
                <?php if ($ing['total'] <= 0): ?>
                    <p class="text-muted mb-0">No hay cobros en el periodo seleccionado.</p>
                <?php else: ?>
                    <div id="grafico-modulos" style="min-height:280px;"></div>
                    <table class="table table-sm mt-3 mb-0">
                        <tbody>
                        <?php foreach (['basketball' => 'Fútbol', 'arena' => 'Arena', 'league' => 'League'] as $k => $n): ?>
                            <tr>
                                <td><?php echo $n; ?></td>
                                <td class="text-end fw-semibold">$<?php echo number_format($ing[$k], 2); ?></td>
                                <td class="text-end text-muted" style="width:5rem;">
                                    <?php echo number_format($ing[$k] / $ing['total'] * 100, 1); ?> %
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== Ingresos por sede ==================== -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Ingresos por sede</h3>
                                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small">sólo sedes con movimiento</span>
                    <?php echo ds_botones_exportar('financiero', 'financiero', $p); ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($porSede) === 0): ?>
                    <p class="text-muted mb-0">Ninguna sede registró cobros en el periodo.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th class="text-end">Fútbol</th>
                                    <th class="text-end">Arena</th>
                                    <th class="text-end">League</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($porSede as $s): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($s['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($s['sinSede'])): ?>
                                            <i class="fas fa-info-circle text-muted ms-1"
                                               title="Los torneos de League pueden organizarse fuera de las instalaciones del club, así que no tienen sede. No se reparte a prorrateo."></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo $s['basketball'] > 0 ? '$' . number_format($s['basketball'], 2) : '<span class="text-muted">—</span>'; ?></td>
                                    <td class="text-end"><?php echo $s['arena'] > 0 ? '$' . number_format($s['arena'], 2) : '<span class="text-muted">—</span>'; ?></td>
                                    <td class="text-end"><?php echo $s['league'] > 0 ? '$' . number_format($s['league'], 2) : '<span class="text-muted">—</span>'; ?></td>
                                    <td class="text-end fw-semibold">$<?php echo number_format($s['total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end">$<?php echo number_format($ing['basketball'], 2); ?></th>
                                    <th class="text-end">$<?php echo number_format($ing['arena'], 2); ?></th>
                                    <th class="text-end">$<?php echo number_format($ing['league'], 2); ?></th>
                                    <th class="text-end">$<?php echo number_format($ing['total'], 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- ==================== Por concepto ==================== -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Ingresos por concepto</h3>
                <?php echo ds_botones_exportar('conceptos', 'financiero', $p); ?>
            </div>
            <div class="card-body">
                <?php if (count($conceptos) === 0): ?>
                    <p class="text-muted mb-0">No hay cobros por concepto en el periodo.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th>Módulo</th><th>Concepto</th><th class="text-end">Cobros</th><th class="text-end">Importe</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($conceptos as $c): ?>
                                <tr>
                                    <td><span class="text-muted small"><?php echo $c['modulo']; ?></span></td>
                                    <td><?php echo htmlspecialchars((string) $c['concepto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end"><?php echo number_format($c['n']); ?></td>
                                    <td class="text-end fw-semibold">$<?php echo number_format($c['valor'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== Descuentos y facturación ==================== -->
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Descuentos y becas vigentes</h3>
            </div>
            <div class="card-body">
                <table class="table table-sm align-middle mb-2">
                    <tbody>
                    <?php foreach ($dsc['registrados'] as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $d['concepto'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end text-muted"><?php echo number_format((int) $d['n']); ?></td>
                            <td class="text-end fw-semibold">$<?php echo number_format((float) $d['v'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($dsc['becaCompleta']['n'] > 0): ?>
                    <div class="callout mb-0">
                        <strong><?php echo (int) $dsc['becaCompleta']['n']; ?> beca(s) del 100 %</strong>
                        suponen <strong>$<?php echo number_format($dsc['becaCompleta']['mensual'], 2); ?></strong>
                        al mes que la escuela no cobra.
                        <div class="text-muted small mt-1">
                            No consta como descuento: se guardan con importe 0,00. Esta cifra es
                            <em>estimada</em> a partir de la pensión de la sede de cada alumno.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Facturación electrónica</h3>
            </div>
            <div class="card-body">
                <?php if (count($facturas) === 0): ?>
                    <p class="text-muted mb-0">No se emitieron facturas electrónicas en el periodo.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                        <?php foreach ($facturas as $f): ?>
                            <tr>
                                <td>
                                    <span class="badge text-bg-<?php echo $f['estado'] === 'AUTORIZADO' ? 'success' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars((string) $f['estado'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="text-end text-muted"><?php echo number_format((int) $f['n']); ?></td>
                                <td class="text-end fw-semibold">$<?php echo number_format((float) $f['v'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted small mb-0 mt-2">
                        Un comprobante emitido y no autorizado es dinero que el sistema da por
                        facturado y el SRI no reconoce.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($ing['total'] > 0): ?>
<script src="<?php echo DS_INSIGHTS_GRAFICOS_JS; ?>"></script>
<script src="<?php echo ds_recurso('ds_insights/assets/js/graficos.js'); ?>"></script>
<script>
/* dsGrafico() pone los colores del tema activo y los REAPLICA cuando el
   usuario lo cambia: un gráfico se dibuja una vez y no reacciona solo. */
(function () {
    dsGrafico('grafico-modulos', {
        chart: { type: 'donut', height: 280 },
        colors: ['#3b82f6', '#22d3ee', '#a78bfa'],
        labels: ['Fútbol', 'Arena', 'League'],
        series: [
            <?php echo round($ing['basketball'], 2); ?>,
            <?php echo round($ing['arena'], 2); ?>,
            <?php echo round($ing['league'], 2); ?>
        ],
        legend: { position: 'bottom' },
        dataLabels: { enabled: true, formatter: function (v) { return v.toFixed(1) + '%'; } },
        tooltip: { y: { formatter: function (v) { return '$' + v.toFixed(2); } } },
        plotOptions: { pie: { donut: { labels: { show: true,
            total: { show: true, label: 'Total',
                     formatter: function (w) {
                         return '$' + w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0)
                                        .toLocaleString('es-EC', { minimumFractionDigits: 2 });
                     } } } } } }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
