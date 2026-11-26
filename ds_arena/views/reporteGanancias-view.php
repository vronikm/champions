<?php
/*
| Arena › Reportes › Ganancias (migración 101).
|
| La ganancia del período en una sola pantalla:
|   Instalaciones = lo pagado por reservas, también con monedero (el monedero
|                   en sí es ahorro de los clientes, no ingreso) − Egresos Instalaciones
|   Bar           = lo vendido + otros ingresos de caja − egresos del bar (Egresos,
|                   egresos anotados en caja y pagos de compras)
|   Total         = las dos
| con el detalle por día (o por mes si el período pasa de dos meses). Los
| cálculos son los de posController::resumenGanancias, los mismos de cada
| pantalla de egresos.
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Ganancias';
$vistaActual = 'reporteGanancias';

require __DIR__ . '/inc/egresos-periodo.php';

$g    = $pos->resumenGanancias($desde, $hasta);
$inst = $g['inst'];
$bar  = $g['bar'];
$conInstalaciones = $pos->egresosConAmbito();
$puedeImprimir = puede_exportar('reporteGanancias');

$fechaCorta    = static fn ($d) => date('d/m/Y', strtotime($d));
$diasSemana    = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
$meses         = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$tituloPeriodo = $desde === $hasta ? $fechaCorta($desde) : $fechaCorta($desde) . ' – ' . $fechaCorta($hasta);
$etiquetaFila  = static fn ($k) => strlen($k) === 7
    ? ucfirst($meses[(int)substr($k, 5, 2)]) . ' ' . substr($k, 0, 4)
    : $diasSemana[(int)date('w', strtotime($k))] . ' ' . date('d/m/Y', strtotime($k));
$enlace = static fn ($vista) => APP_URL . $vista . '/?desde=' . $desde . '&amp;hasta=' . $hasta;

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";
?>
<style>
    .gn-periodo { padding: .75rem; display: grid; gap: .6rem; }
    .gn-atajos { display: flex; gap: .4rem; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
    .gn-atajos::-webkit-scrollbar { display: none; }
    .gn-atajos .btn { white-space: nowrap; flex: 0 0 auto; }
    .gn-fechas { display: grid; grid-template-columns: 1fr 1fr auto; gap: .5rem; align-items: end; }
    @media (max-width: 575.98px) { .gn-fechas { grid-template-columns: 1fr 1fr; } .gn-fechas > :last-child { grid-column: 1 / -1; } }

    .gn-cuadro { list-style: none; margin: 0; padding: 0; }
    .gn-cuadro li { display: flex; justify-content: space-between; gap: .75rem; padding: .45rem 1rem; font-variant-numeric: tabular-nums; }
    .gn-cuadro li > span:last-child { white-space: nowrap; font-weight: 600; }
    .gn-cuadro .gn-sub { padding-left: 1.75rem; font-size: .875rem; color: var(--bs-secondary-color); }
    .gn-cuadro .gn-sub > span:last-child { font-weight: 500; }
    .gn-cuadro .gn-total { border-top: 1px solid var(--bs-border-color); font-weight: 700; }
    .gn-cuadro .gn-resultado { background: var(--bs-tertiary-bg); font-size: 1.05rem; font-weight: 700; border-top: 2px solid var(--bs-border-color); }
    .gn-cab { display: flex; justify-content: space-between; align-items: center; gap: .5rem; flex-wrap: wrap; }

    /* Detalle por día o mes: tabla en pantalla ancha, fichas en el teléfono */
    .gn-tabla { width: 100%; font-variant-numeric: tabular-nums; }
    .gn-tabla th { font-size: .75rem; text-transform: uppercase; color: var(--bs-secondary-color); font-weight: 600; background: var(--bs-tertiary-bg); }
    .gn-tabla th, .gn-tabla td { padding: .5rem .75rem; text-align: right; white-space: nowrap; }
    .gn-tabla th:first-child, .gn-tabla td:first-child { text-align: left; }
    .gn-tabla tbody tr + tr td { border-top: 1px solid var(--bs-border-color-translucent); }
    .gn-tabla small { display: block; font-size: .75rem; color: var(--bs-secondary-color); }
    .gn-tabla tfoot td { border-top: 2px solid var(--bs-border-color); font-weight: 700; }
    .gn-fichas { display: none; }
    @media (max-width: 767.98px) {
        .gn-tabla-envoltura { display: none; }
        .gn-fichas { display: block; }
    }
    .gn-ficha { display: grid; grid-template-columns: 1fr auto; gap: .1rem .75rem; padding: .6rem 1rem; }
    .gn-ficha + .gn-ficha { border-top: 1px solid var(--bs-border-color-translucent); }
    .gn-ficha__meta { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .1rem .9rem; font-size: .8125rem; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; }
</style>

<div class="pos">
    <?php if (!$conInstalaciones): ?>
        <div class="alert alert-warning">Falta aplicar la migración 101: los egresos de instalaciones no se están contando.</div>
    <?php endif; ?>

    <form class="pos-tarjeta mb-3 gn-periodo pos-no-imprimir" method="GET" action="<?php echo APP_URL; ?>reporteGanancias/" aria-label="Período">
        <div class="gn-atajos" role="group" aria-label="Períodos rápidos">
            <?php foreach ($periodos as $n => [$a, $b]): ?>
                <a class="btn btn-sm <?php echo $periodoActivo === $n ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                   href="<?php echo APP_URL; ?>reporteGanancias/?desde=<?php echo $a; ?>&amp;hasta=<?php echo $b; ?>"<?php echo $periodoActivo === $n ? ' aria-current="true"' : ''; ?>><?php echo $n; ?></a>
            <?php endforeach; ?>
        </div>
        <div class="gn-fechas">
            <div><label class="form-label small fw-semibold mb-1" for="gnDesde">Desde</label><input type="date" class="form-control form-control-sm" id="gnDesde" name="desde" value="<?php echo $desde; ?>" max="<?php echo $hoy; ?>"></div>
            <div><label class="form-label small fw-semibold mb-1" for="gnHasta">Hasta</label><input type="date" class="form-control form-control-sm" id="gnHasta" name="hasta" value="<?php echo $hasta; ?>" max="<?php echo $hoy; ?>"></div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary pos-btn flex-grow-1"><i class="fas fa-magnifying-glass" aria-hidden="true"></i>Consultar</button>
                <?php if ($puedeImprimir): ?>
                    <button type="button" class="btn btn-outline-secondary pos-btn" onclick="window.print()" aria-label="Imprimir"><i class="fas fa-print" aria-hidden="true"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <h2 class="h5 fw-bold mb-3">Ganancias · <?php echo $tituloPeriodo; ?></h2>

    <div class="pos-kpis">
        <div class="pos-kpi <?php echo $g['total'] < 0 ? 'pos-kpi--alerta' : 'pos-kpi--acento'; ?>"><span class="pos-kpi__etq"><i class="fas fa-sack-dollar" aria-hidden="true"></i>Ganancia total</span>
            <strong class="pos-kpi__cifra"><?php echo $posDinero($g['total']); ?></strong>
            <span class="pos-kpi__nota"><?php echo $g['total'] < 0 ? 'Se gastó más de lo que entró' : 'Instalaciones y bar'; ?></span></div>
        <div class="pos-kpi"><span class="pos-kpi__etq"><i class="fas fa-arrow-down text-success" aria-hidden="true"></i>Ingresos</span>
            <strong class="pos-kpi__cifra pos-pos"><?php echo $posDinero($g['ingresos']); ?></strong>
            <span class="pos-kpi__nota">Reservas y ventas</span></div>
        <div class="pos-kpi"><span class="pos-kpi__etq"><i class="fas fa-arrow-up text-danger" aria-hidden="true"></i>Egresos</span>
            <strong class="pos-kpi__cifra pos-neg"><?php echo $posDinero($g['egresos']); ?></strong>
            <span class="pos-kpi__nota">Instalaciones y bar</span></div>
        <div class="pos-kpi <?php echo $inst['ganancia'] < 0 ? 'pos-kpi--alerta' : 'pos-kpi--ok'; ?>"><span class="pos-kpi__etq"><i class="fas fa-warehouse" aria-hidden="true"></i>Instalaciones</span>
            <strong class="pos-kpi__cifra"><?php echo $posDinero($inst['ganancia']); ?></strong>
            <span class="pos-kpi__nota">Reservas − sus egresos</span></div>
        <div class="pos-kpi <?php echo $bar['resultado'] < 0 ? 'pos-kpi--alerta' : 'pos-kpi--ok'; ?>"><span class="pos-kpi__etq"><i class="fas fa-cash-register" aria-hidden="true"></i>Bar</span>
            <strong class="pos-kpi__cifra"><?php echo $posDinero($bar['resultado']); ?></strong>
            <span class="pos-kpi__nota">Ventas − sus egresos</span></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="pos-tarjeta h-100 overflow-hidden">
                <div class="p-3 border-bottom gn-cab"><h3 class="h6 fw-bold mb-0"><i class="fas fa-warehouse me-2" aria-hidden="true"></i>Instalaciones</h3>
                    <a class="small pos-no-imprimir" href="<?php echo $enlace('egresoInstalaciones'); ?>">Egresos Instalaciones</a></div>
                <ul class="gn-cuadro">
                    <li><span>Cobros de reservas <small class="text-body-secondary">(<?php echo $inst['nCobros']; ?>)</small></span><span><?php echo $posDinero($inst['cobros']); ?></span></li>
                    <li><span>Pagado con monedero <small class="text-body-secondary">(<?php echo $inst['nMonedero']; ?>)</small></span><span><?php echo $posDinero($inst['monederoAplicado']); ?></span></li>
                    <?php if ($inst['devReservas'] > 0): ?><li><span>Devoluciones de reservas</span><span><?php echo $posDinero(-$inst['devReservas']); ?></span></li><?php endif; ?>
                    <li class="gn-total"><span>Ingresos por reservas</span><span class="pos-pos"><?php echo $posDinero($inst['ingresos']); ?></span></li>
                    <?php foreach (array_slice($inst['porCategoria'], 0, 6, true) as $cat => $v): ?>
                        <li class="gn-sub"><span><?php echo $posH($cat); ?></span><span><?php echo $posDinero(-$v); ?></span></li>
                    <?php endforeach; ?>
                    <?php if (count($inst['porCategoria']) > 6): ?>
                        <li class="gn-sub"><span>Otras categorías</span><span><?php echo $posDinero(-array_sum(array_slice($inst['porCategoria'], 6))); ?></span></li>
                    <?php endif; ?>
                    <li class="gn-total"><span>Egresos de instalaciones</span><span class="pos-neg"><?php echo $posDinero(-$inst['egresos']); ?></span></li>
                    <li class="gn-resultado"><span>Ganancia instalaciones</span><span class="<?php echo $inst['ganancia'] < 0 ? 'pos-neg' : 'pos-pos'; ?>"><?php echo $posDinero($inst['ganancia']); ?></span></li>
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="pos-tarjeta h-100 overflow-hidden">
                <div class="p-3 border-bottom gn-cab"><h3 class="h6 fw-bold mb-0"><i class="fas fa-cash-register me-2" aria-hidden="true"></i>Bar</h3>
                    <a class="small pos-no-imprimir" href="<?php echo $enlace('posEgresos'); ?>">Egresos del bar</a></div>
                <ul class="gn-cuadro">
                    <li><span>Ventas en «Vender» <small class="text-body-secondary">(<?php echo $bar['nPos']; ?>)</small></span><span><?php echo $posDinero($bar['ventasPos']); ?></span></li>
                    <li><span>Ventas registradas <small class="text-body-secondary">(<?php echo $bar['nReg']; ?>)</small></span><span><?php echo $posDinero($bar['ventasReg']); ?></span></li>
                    <?php if ($bar['otrosIngresos'] > 0): ?><li><span>Otros ingresos de caja</span><span><?php echo $posDinero($bar['otrosIngresos']); ?></span></li><?php endif; ?>
                    <li class="gn-total"><span>Ingresos del bar</span><span class="pos-pos"><?php echo $posDinero($bar['ingresos']); ?></span></li>
                    <li class="gn-sub"><span>Egresos del bar</span><span><?php echo $posDinero(-$bar['egresos']); ?></span></li>
                    <?php if ($bar['egresosCaja'] > 0): ?><li class="gn-sub"><span>Egresos anotados en la caja</span><span><?php echo $posDinero(-$bar['egresosCaja']); ?></span></li><?php endif; ?>
                    <li class="gn-sub"><span>Pagos de compras</span><span><?php echo $posDinero(-$bar['compras']); ?></span></li>
                    <li class="gn-total"><span>Egresos del bar (total)</span><span class="pos-neg"><?php echo $posDinero(-$bar['totalEgresos']); ?></span></li>
                    <li class="gn-resultado"><span>Ganancia bar</span><span class="<?php echo $bar['resultado'] < 0 ? 'pos-neg' : 'pos-pos'; ?>"><?php echo $posDinero($bar['resultado']); ?></span></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="pos-tarjeta">
        <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0"><?php echo $g['porMes'] ? 'Mes por mes' : 'Día por día'; ?>
            <small class="text-body-secondary fw-normal">· <?php echo count($g['filas']); ?> <?php echo $g['porMes'] ? 'mes(es)' : 'día(s) con movimiento'; ?></small></h3></div>
        <?php if (!$g['filas']): ?>
            <p class="text-body-secondary text-center p-4 mb-0">No hubo ingresos ni egresos en esas fechas.</p>
        <?php else: ?>
            <div class="gn-tabla-envoltura table-responsive">
                <table class="gn-tabla">
                    <thead><tr><th scope="col"><?php echo $g['porMes'] ? 'Mes' : 'Día'; ?></th><th scope="col">Instalaciones</th><th scope="col">Bar</th><th scope="col">Ganancia</th></tr></thead>
                    <tbody>
                        <?php foreach ($g['filas'] as $k => $x): ?>
                            <tr>
                                <td><?php echo $etiquetaFila($k); ?></td>
                                <td class="<?php echo $x['instGanancia'] < 0 ? 'pos-neg' : ''; ?>"><?php echo $posDinero($x['instGanancia']); ?>
                                    <small><?php echo $posDinero($x['instIngresos']); ?> − <?php echo $posDinero($x['instEgresos']); ?></small></td>
                                <td class="<?php echo $x['barGanancia'] < 0 ? 'pos-neg' : ''; ?>"><?php echo $posDinero($x['barGanancia']); ?>
                                    <small><?php echo $posDinero($x['barIngresos']); ?> − <?php echo $posDinero($x['barEgresos']); ?></small></td>
                                <td class="fw-bold <?php echo $x['total'] < 0 ? 'pos-neg' : 'pos-pos'; ?>"><?php echo $posDinero($x['total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td>Total</td><td><?php echo $posDinero($inst['ganancia']); ?></td><td><?php echo $posDinero($bar['resultado']); ?></td>
                        <td class="<?php echo $g['total'] < 0 ? 'pos-neg' : 'pos-pos'; ?>"><?php echo $posDinero($g['total']); ?></td></tr></tfoot>
                </table>
            </div>
            <div class="gn-fichas">
                <?php foreach ($g['filas'] as $k => $x): ?>
                    <div class="gn-ficha">
                        <span class="fw-semibold"><?php echo $etiquetaFila($k); ?></span>
                        <span class="fw-bold <?php echo $x['total'] < 0 ? 'pos-neg' : 'pos-pos'; ?>" style="font-variant-numeric: tabular-nums;"><?php echo $posDinero($x['total']); ?></span>
                        <span class="gn-ficha__meta"><span>Instalaciones <?php echo $posDinero($x['instGanancia']); ?></span><span>Bar <?php echo $posDinero($x['barGanancia']); ?></span></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <p class="small text-body-secondary mt-3 mb-0">
        Ingresos por reservas: lo pagado por reservas (también con monedero), menos las devoluciones de reservas. Los depósitos y
        retiros del monedero no son ingreso: son el ahorro de los clientes. Cobros como en <a href="<?php echo $enlace('reportePagos'); ?>">Reportes › Pagos Reservas</a>.
        Bar: lo vendido (anuladas fuera) y los otros ingresos de caja, menos sus egresos y los pagos de compras.
    </p>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
