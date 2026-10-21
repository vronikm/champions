<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Becas y descuentos
|--------------------------------------------------------------------------
| Cuánto cuesta el beneficio, a cuántos alcanza, cuánto pagaron aun así y
| cómo asisten. Cuatro preguntas que hasta ahora había que responder a mano.
|
|
| LO QUE ESTA PANTALLA DICE Y NO ESTABA A LA VISTA
|
| La Beca 50 % guarda su importe bien: $15,00 sobre una pensión de $30,00, la
| mitad exacta. La Beca 100 % se guarda con importe 0,00, así que el subsidio
| que la escuela concede no aparecía en ninguna suma. Aquí se calcula desde
| la pensión de la sede y se rotula como DEDUCIDO, separado de lo registrado:
| una cosa es un hecho escrito en la fila y otra una consecuencia inferida.
|
|
| SOBRE LA ASISTENCIA, UNA CAUTELA
|
| Los grupos son de tamaño muy distinto —150 alumnos sin beneficio frente a 7
| con beca completa—. Con siete alumnos, unas pocas faltas mueven el
| porcentaje varios puntos. La pantalla muestra el número de marcas de cada
| grupo justamente para que no se lea una diferencia como si fuera un
| hallazgo cuando puede ser ruido.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$beneficios = $insInsights->beneficios();
$control    = $insInsights->sinBeneficio();
$anomalias  = $insInsights->anomaliasBeneficio();

$totAlumnos  = array_sum(array_column($beneficios, 'alumnos'));
$totActivos  = array_sum(array_column($beneficios, 'activos'));
$totMensual  = array_sum(array_column($beneficios, 'mensual'));
$totDeducido = array_sum(array_column($beneficios, 'deducido'));
$totPagado   = array_sum(array_column($beneficios, 'pagado'));
$totCuotas   = array_sum(array_column($beneficios, 'cuotas'));

$tituloVista = 'Becas y descuentos';
$vistaActual = 'becas';

require_once __DIR__ . "/inc/layout-top.php";
?>

<!-- ==================== Las cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($totAlumnos); ?></h3>
                <p class="mb-0">Alumnos con beneficio</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($totActivos); ?> activos</span></p>
            </div>
            <i class="fas fa-user-graduate small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-danger h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($totMensual, 2); ?></h3>
                <p class="mb-0">Valor mensual</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75">que la escuela deja de cobrar</span></p>
            </div>
            <i class="fas fa-tags small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-warning h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($totDeducido, 2); ?></h3>
                <p class="mb-0">De eso, no registrado</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"
                          title="Las becas del 100 % se guardan con importe 0,00. Esta cifra se deduce de la pensión de la sede de cada alumno.">
                        <i class="fas fa-info-circle"></i> deducido, no escrito
                    </span></p>
            </div>
            <i class="fas fa-eye-slash small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-success h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($totPagado, 2); ?></h3>
                <p class="mb-0">Pagado por los beneficiarios</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($totCuotas); ?> cuotas de pensión</span></p>
            </div>
            <i class="fas fa-money-bill-wave small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<!-- ==================== El cuadro por tipo ==================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Por tipo de beneficio</h3>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">beneficios vigentes</span>
            <?php echo ds_botones_exportar('becas', 'becas', $insInsights->periodo()); ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($beneficios) === 0): ?>
            <p class="text-muted mb-0">No hay becas ni descuentos vigentes.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th class="text-end">Alumnos</th>
                            <th class="text-end">Activos</th>
                            <th class="text-end">Registrado</th>
                            <th class="text-end">Valor real / mes</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Cuotas</th>
                            <th class="text-end">Asistencia</th>
                            <th class="text-end">Avisadas</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($beneficios as $b): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($b['tipo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($b['deducido'] > 0): ?>
                                    <i class="fas fa-info-circle text-warning ms-1"
                                       title="Se guarda con importe 0,00: su valor real se deduce de la pensión de la sede."></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo number_format($b['alumnos']); ?></td>
                            <td class="text-end text-muted"><?php echo number_format($b['activos']); ?></td>
                            <td class="text-end">
                                <?php echo $b['registrado'] > 0
                                    ? '$' . number_format($b['registrado'], 2)
                                    : '<span class="text-danger">$0,00</span>'; ?>
                            </td>
                            <td class="text-end fw-semibold">$<?php echo number_format($b['mensual'], 2); ?></td>
                            <td class="text-end">$<?php echo number_format($b['pagado'], 2); ?></td>
                            <td class="text-end text-muted"><?php echo number_format($b['cuotas']); ?></td>
                            <td class="text-end">
                                <?php if ($b['asistencia'] === null): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <?php echo number_format($b['asistencia'], 1); ?> %
                                    <small class="text-muted d-block"><?php echo number_format($b['marcas']); ?> marcas</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php echo $b['avisadas'] === null
                                    ? '<span class="text-muted">—</span>'
                                    : number_format($b['avisadas'], 1) . ' %'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-top">
                            <th>Sin beneficio <span class="text-muted fw-normal small">(comparación)</span></th>
                            <th class="text-end"><?php echo number_format($control['alumnos']); ?></th>
                            <th class="text-end text-muted"><?php echo number_format($control['alumnos']); ?></th>
                            <th class="text-end text-muted">—</th>
                            <th class="text-end text-muted">—</th>
                            <th class="text-end text-muted">—</th>
                            <th class="text-end text-muted">—</th>
                            <th class="text-end">
                                <?php if ($control['asistencia'] === null): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <?php echo number_format($control['asistencia'], 1); ?> %
                                    <small class="text-muted d-block fw-normal"><?php echo number_format($control['marcas']); ?> marcas</small>
                                <?php endif; ?>
                            </th>
                            <th class="text-end">
                                <?php echo $control['avisadas'] === null
                                    ? '<span class="text-muted">—</span>'
                                    : number_format($control['avisadas'], 1) . ' %'; ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- ==================== Asistencia comparada ==================== -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Asistencia según el beneficio</h3>
            </div>
            <div class="card-body">
                <div id="grafico-becas" style="min-height:280px;"></div>
                <div class="callout mt-3 mb-0">
                    <strong>Léase con cautela.</strong>
                    Los grupos son de tamaño muy distinto: <?php echo number_format($control['marcas']); ?>
                    marcas de asistencia en el grupo sin beneficio frente a
                    <?php
                    $menor = null;
                    foreach ($beneficios as $b) {
                        if ($menor === null || $b['marcas'] < $menor['marcas']) { $menor = $b; }
                    }
                    echo $menor ? number_format($menor['marcas']) . ' en «' . htmlspecialchars($menor['tipo'], ENT_QUOTES, 'UTF-8') . '»' : '—';
                    ?>.
                    Con pocas marcas, unas pocas faltas mueven el porcentaje varios puntos:
                    una diferencia no es un hallazgo hasta que se sostiene con más datos.
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== Lo que conviene revisar ==================== -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-search me-2"></i>Conviene revisar</h3>
            </div>
            <div class="card-body">
                <?php if (count($anomalias) === 0): ?>
                    <p class="text-muted mb-0">Nada incoherente en los beneficios vigentes.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($anomalias as $a): ?>
                            <li class="d-flex align-items-start py-2 border-bottom">
                                <i class="fas fa-circle-exclamation text-<?php echo $a['tono']; ?> me-3 mt-1"></i>
                                <span><?php echo htmlspecialchars($a['texto'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($totDeducido > 0): ?>
                    <div class="callout mt-3 mb-0">
                        <strong>$<?php echo number_format($totDeducido, 2); ?> al mes</strong>
                        de beca completa no constan como descuento en la base: se guardan
                        con importe 0,00. Registrar su valor real haría que el total de
                        descuentos y la cartera proyectada dejaran de estar sesgados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo DS_INSIGHTS_GRAFICOS_JS; ?>"></script>
<script src="<?php echo ds_recurso('ds_insights/assets/js/graficos.js'); ?>"></script>
<script>
/* Barras horizontales: los nombres de los tipos no caben girados y girar el
   texto del eje es lo primero que hace ilegible un gráfico. */
(function () {
    dsGrafico('grafico-becas', {
        chart: { type: 'bar', height: 280, toolbar: { show: false } },
        colors: ['#22c55e'],
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '55%' } },
        dataLabels: { enabled: true, formatter: function (v) { return v.toFixed(1) + '%'; } },
        series: [{ name: 'Asistencia', data: <?php
            $datos = [];
            foreach ($beneficios as $b) {
                if ($b['asistencia'] !== null) { $datos[] = round($b['asistencia'], 1); }
            }
            if ($control['asistencia'] !== null) { $datos[] = round($control['asistencia'], 1); }
            echo json_encode($datos);
        ?> }],
        xaxis: { categories: <?php
            $cats = [];
            foreach ($beneficios as $b) {
                if ($b['asistencia'] !== null) { $cats[] = $b['tipo']; }
            }
            if ($control['asistencia'] !== null) { $cats[] = 'Sin beneficio'; }
            echo json_encode($cats, JSON_UNESCAPED_UNICODE);
        ?>, max: 100 },
        yaxis: {},
        tooltip: { y: { formatter: function (v) { return v.toFixed(1) + ' % de asistencia'; } } }
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
