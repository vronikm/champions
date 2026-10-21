<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Basketball Analytics
|--------------------------------------------------------------------------
| Alumnos, retención, asistencia y cumplimiento de pago.
|
|
| LA RETENCIÓN SE MUESTRA CON SU EXPOSICIÓN, Y ES LO MÁS IMPORTANTE DE ESTA
| PANTALLA
|
| Las cifras reales van del 38 % en enero al 100 % en agosto. Leerlo como una
| mejora sería un error: la cohorte de agosto no ha tenido tiempo de irse.
| Comparar ocho meses de exposición con cero no compara nada.
|
| Con los meses delante, el dato se lee bien: enero al 38 % con siete meses
| SÍ destaca frente a septiembre al 77 % con once. Esa es la lectura útil, y
| sin la columna de exposición no se puede hacer.
|
|
| LA ASISTENCIA ESTÁ MUY CONCENTRADA
|
| La Salle acumula la práctica totalidad de las marcas; las demás sedes
| apenas registran. Se muestra el número de marcas junto a cada porcentaje
| para que no se compare un 57 % de siete marcas con un 68 % de mil.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$p = $insInsights->periodo($_GET['desde'] ?? null, $_GET['hasta'] ?? null);

$alu       = $insInsights->alumnos($p);
$cohortes  = $insInsights->retencionPorCohorte(12);
$porSede   = $insInsights->alumnosPorSede();
$porAnio   = $insInsights->alumnosPorAnio();
$porProfe  = $insInsights->alumnosPorEntrenador();
$asisMes   = $insInsights->asistenciaMensual();
$asisSede  = $insInsights->asistenciaPorSede();
$pago      = $insInsights->cumplimientoPago();
$anomalias = $insInsights->anomaliasAlumno();

$varAltas = $insInsights->variacion((float) $alu['altas'], (float) $alu['altasAnt']);

$tituloVista = 'Fútbol';
$vistaActual = 'basketball';

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
                El periodo afecta a las altas; el resto retrata el estado de hoy.
            </div>
        </form>
    </div>
</div>

<!-- ==================== Las cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($alu['activos']); ?></h3>
                <p class="mb-0">Alumnos activos</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($alu['total']); ?> registrados</span></p>
            </div>
            <i class="fas fa-users small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-success h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($alu['altas']); ?></h3>
                <p class="mb-0">Altas del periodo</p>
                <p class="mb-0 small opacity-75"><?php echo ds_variacion_sobre_color($varAltas); ?></p>
            </div>
            <i class="fas fa-user-plus small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-warning h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $alu['abandono'] === null ? '—' : number_format($alu['abandono'], 1) . ' %'; ?></h3>
                <p class="mb-0">Tasa de abandono</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($alu['inactivos']); ?> inactivos</span></p>
            </div>
            <i class="fas fa-user-minus small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-danger h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $pago['cumplimiento'] === null ? '—' : number_format($pago['cumplimiento'], 1) . ' %'; ?></h3>
                <p class="mb-0">Cumplimiento de pago</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"
                          title="Cuotas de pensión cobradas frente a los meses que cada alumno lleva matriculado. Excluye las becas del 100 %.">
                        <?php echo number_format($pago['cuotas']); ?> de <?php echo number_format($pago['mesesDebidos']); ?> meses
                    </span></p>
            </div>
            <i class="fas fa-money-check-dollar small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<!-- ==================== Retención por cohorte ==================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Retención por mes de ingreso</h3>
                <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">cuántos de los que entraron siguen activos</span>
            <?php echo ds_botones_exportar('retencion', 'basketball', $p); ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($cohortes) === 0): ?>
            <p class="text-muted mb-0">No hay ingresos registrados en los últimos doce meses.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Ingresaron en</th>
                            <th class="text-end">Alumnos</th>
                            <th class="text-end">Siguen</th>
                            <th class="text-end">Retención</th>
                            <th style="width:38%;">Exposición</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $maxMeses = max(array_column($cohortes, 'meses')) ?: 1;
                    foreach ($cohortes as $c):
                        /* Una retención alta con poca exposición no dice nada
                           todavía: se marca en gris para que no se lea como
                           un logro. */
                        $fiable = $c['meses'] >= 3;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['cohorte'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?php echo number_format($c['ingresaron']); ?></td>
                            <td class="text-end"><?php echo number_format($c['siguen']); ?></td>
                            <td class="text-end fw-semibold <?php echo $fiable ? ($c['retencion'] < 60 ? 'text-danger' : '') : 'text-muted'; ?>">
                                <?php echo number_format($c['retencion'], 1); ?> %
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;">
                                        <div class="progress-bar bg-secondary"
                                             style="width:<?php echo round($c['meses'] / $maxMeses * 100); ?>%"></div>
                                    </div>
                                    <small class="text-muted" style="width:5.5rem;">
                                        <?php echo (int) $c['meses']; ?> mes<?php echo $c['meses'] === 1 ? '' : 'es'; ?>
                                    </small>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="callout mt-3 mb-0">
                <strong>La exposición cambia la lectura.</strong>
                Una cohorte reciente retiene casi el 100 % simplemente porque no ha tenido
                tiempo de irse: esas van en gris. Lo que sí destaca es una retención baja
                con muchos meses detrás, y eso se puede comparar entre cohortes de
                antigüedad parecida.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- ==================== Por año de nacimiento ==================== -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Alumnos por año de nacimiento</h3>
                <span class="text-muted small">incluye los años sin alumnos</span>
            </div>
            <div class="card-body">
                <?php if (count($porAnio) === 0): ?>
                    <p class="text-muted mb-0">No hay fechas de nacimiento utilizables.</p>
                <?php else: ?>
                    <div id="grafico-anios" style="min-height:300px;"></div>
                    <p class="text-muted small mb-0 mt-2">
                        El sistema no tiene bandas U8/U10: lo que llama categoría es el año de
                        nacimiento. Los años vacíos se muestran porque un hueco dice que no hay
                        relevo en esa edad.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== Por sede ==================== -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Por sede</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Sede</th><th class="text-end">Activos</th><th class="text-end">Edad media</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($porSede as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $s['sede'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-end fw-semibold"><?php echo number_format((int) $s['activos']); ?></td>
                                <td class="text-end text-muted"><?php echo $s['edad'] !== null ? number_format((float) $s['edad'], 1) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- ==================== Entrenadores ==================== -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Por entrenador</h3>
            </div>
            <div class="card-body">
                <?php if (count($porProfe) === 0): ?>
                    <p class="text-muted mb-0">Ningún entrenador tiene alumnos activos asignados.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th>Entrenador</th><th class="text-end">Alumnos</th><th class="text-end">Asistencia</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($porProfe as $e): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars((string) $e['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($e['estado'] !== 'A'): ?>
                                            <span class="badge text-bg-danger ms-1">inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-semibold"><?php echo number_format((int) $e['alumnos']); ?></td>
                                    <td class="text-end">
                                        <?php if ($e['asistencia'] === null): ?>
                                            <span class="text-muted">sin marcas</span>
                                        <?php else: ?>
                                            <?php echo number_format((float) $e['asistencia'], 1); ?> %
                                            <small class="text-muted d-block"><?php echo number_format((int) $e['marcas']); ?> marcas</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        Tres alumnos tienen más de un entrenador, así que las filas no suman
                        el total de alumnos: cada uno cuenta en los suyos.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== Asistencia ==================== -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Asistencia por sede</h3>
            </div>
            <div class="card-body">
                <?php if (count($asisSede) === 0): ?>
                    <p class="text-muted mb-0">Todavía no se ha registrado asistencia.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th>Sede</th><th class="text-end">Marcas</th><th class="text-end">Asistencia</th><th class="text-end">Avisadas</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($asisSede as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) $s['sede'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end text-muted"><?php echo number_format((int) $s['marcas']); ?></td>
                                    <td class="text-end fw-semibold"><?php echo number_format((float) $s['asistencia'], 1); ?> %</td>
                                    <td class="text-end"><?php echo $s['avisadas'] !== null ? number_format((float) $s['avisadas'], 1) . ' %' : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        Las marcas están muy concentradas en una sede: un porcentaje sobre
                        siete marcas no es comparable con uno sobre mil.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Datos que no cuadran ==================== -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="fas fa-triangle-exclamation me-2"></i>Datos que no cuadran</h3>
    </div>
    <div class="card-body">
        <?php if (count($anomalias) === 0): ?>
            <p class="text-muted mb-0">Nada incoherente en los datos de alumnos.</p>
        <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($anomalias as $a): ?>
                    <li class="d-flex align-items-start py-2 border-bottom">
                        <i class="fas <?php echo $a['icono']; ?> text-<?php echo $a['tono']; ?> me-3 mt-1"></i>
                        <span><?php echo htmlspecialchars($a['texto'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="text-muted small mb-0 mt-3">
                Insights no corrige datos, sólo los señala. Un promedio de edad calculado
                sobre una fecha de nacimiento imposible sale imposible, y sin este aviso
                nadie sabría por qué.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (count($porAnio) > 0): ?>
<script src="<?php echo DS_INSIGHTS_GRAFICOS_JS; ?>"></script>
<script src="<?php echo ds_recurso('ds_insights/assets/js/graficos.js'); ?>"></script>
<script>
(function () {
    dsGrafico('grafico-anios', {
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        colors: ['#3b82f6'],
        plotOptions: { bar: { borderRadius: 3, columnWidth: '65%' } },
        dataLabels: { enabled: false },
        series: [{ name: 'Alumnos activos', data: <?php
            echo json_encode(array_map(static fn(array $a): int => (int) $a['alumnos'], $porAnio));
        ?> }],
        xaxis: { categories: <?php
            echo json_encode(array_map(static fn(array $a): string => (string) $a['anio'], $porAnio));
        ?> },
        yaxis: {},
        tooltip: { y: { formatter: function (v) { return v + ' alumno(s)'; } },
                   x: { formatter: function (v) { return 'Nacidos en ' + v; } } }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
