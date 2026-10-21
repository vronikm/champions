<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Executive Overview
|--------------------------------------------------------------------------
| La vista consolidada de los tres módulos. Responde, en este orden: cuánto
| entró, cuánto falta por cobrar, cuánto se movió y cómo va la ocupación;
| luego cómo evoluciona; luego cada módulo por separado; y al final qué
| requiere atención.
|
| Ese orden no es casual: primero «¿cómo vamos?», después «¿por qué?».
|
|
| DOS COSAS QUE ESTA PANTALLA HACE Y CONVIENE NO DESHACER
|
| 1. La tarjeta de «por cobrar» NO lleva variación. La cartera de Basketball
|    es una proyección desde hoy —subir la pensión un dólar la infla 217 sin
|    que nadie deje de pagar—, así que comparar dos proyecciones hechas desde
|    el mismo instante no mide nada. Se dice en la tarjeta en vez de fingir
|    un porcentaje.
|
| 2. Cuando el periodo anterior fue cero, la variación se muestra como «—» y
|    no como «+100 %». Pasar de nada a algo no tiene variación porcentual.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

/* El periodo llega por GET y se valida en periodo(): lo que no case con
   AAAA-MM-DD se descarta y cae al mes en curso. */
$p = $insInsights->periodo($_GET['desde'] ?? null, $_GET['hasta'] ?? null);

$ing    = $insInsights->ingresos($p);
$ingAnt = $insInsights->ingresos($p, true);
$trx    = $insInsights->transacciones($p);
$trxAnt = $insInsights->transacciones($p, true);
$ocu    = $insInsights->ocupacion($p);
$ocuAnt = $insInsights->ocupacion($p, true);
$deuda  = $insInsights->porCobrar();
$serie  = $insInsights->serieMensual(8);
$modulos = $insInsights->resumenModulos($p);
$avisos  = $insInsights->requiereAtencion($p);

$varIngresos = $insInsights->variacion($ing['total'], $ingAnt['total']);
$varTrx      = $insInsights->variacion((float) $trx, (float) $trxAnt);
/*
| La ocupación se compara en PUNTOS, no en porcentaje sobre el porcentaje:
| pasar de 34,8 % a 31,9 % es −2,9 puntos, no −8,3 %.
|
| Y sólo se compara si en el periodo anterior hubo ALGO reservado. La guarda
| anterior miraba las horas DISPONIBLES, que existen para cualquier rango de
| fechas porque salen del horario de apertura: con un comparable de 2025 —sin
| datos— la tarjeta mostraba «+33,0 pts», que es la cifra entera disfrazada
| de mejora, mientras las otras tres mostraban «—». Esa incoherencia entre
| tarjetas de la misma pantalla era el sintoma.
*/
$varOcupacion = $ocuAnt['reservadas'] > 0 ? $ocu['pct'] - $ocuAnt['pct'] : null;

$tituloVista = 'Panel';
$vistaActual = 'dashboard';

require_once __DIR__ . "/inc/layout-top.php";
?>

<!-- ==================== Filtro de periodo ==================== -->
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
                Comparado con
                <strong><?php echo htmlspecialchars($p['antDesde'] . ' — ' . $p['antHasta'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="ms-2"><?php echo (int) $p['dias']; ?> días</span>
            </div>
        </form>
    </div>
</div>

<!-- ==================== Las cuatro cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-success h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($ing['total'], 2); ?></h3>
                <p class="mb-0">Ingresos cobrados</p>
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
                <p class="mb-0 small opacity-75"><span class="opacity-75"
                          title="La cartera de Fútbol se proyecta desde hoy: compararla entre periodos no mediría nada. Para eso están las fotos mensuales.">
                        <i class="fas fa-info-circle"></i> sin comparar
                    </span></p>
            </div>
            <i class="fas fa-hand-holding-usd small-box-icon" aria-hidden="true"></i>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($trx); ?></h3>
                <p class="mb-0">Transacciones</p>
                <p class="mb-0 small opacity-75"><?php echo ds_variacion_sobre_color($varTrx); ?></p>
            </div>
            <i class="fas fa-receipt small-box-icon" aria-hidden="true"></i>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-info h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($ocu['pct'], 1); ?> %</h3>
                <p class="mb-0">Ocupación de Arena</p>
                <p class="mb-0 small opacity-75"><?php echo ds_variacion_sobre_color($varOcupacion, ' pts'); ?></p>
            </div>
            <i class="fas fa-warehouse small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<!-- ==================== Evolución y reparto ==================== -->
<div class="row">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Evolución de ingresos</h3>
                <span class="text-muted small">últimos 8 meses</span>
            </div>
            <div class="card-body">
                <?php if (count($serie) === 0): ?>
                    <p class="text-muted mb-0">
                        No hay cobros registrados en los últimos ocho meses.
                    </p>
                <?php else: ?>
                    <div id="grafico-evolucion" style="min-height:300px;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Ingresos por módulo</h3>
            </div>
            <div class="card-body">
                <?php if ($ing['total'] <= 0): ?>
                    <p class="text-muted mb-0">
                        No hay cobros en el periodo seleccionado.
                    </p>
                <?php else: ?>
                    <?php foreach (['basketball' => 'Fútbol', 'arena' => 'Arena', 'league' => 'League'] as $k => $nombre):
                        $pct = $ing[$k] / $ing['total'] * 100; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span><?php echo $nombre; ?></span>
                                <span class="fw-semibold"><?php echo number_format($pct, 1); ?> %</span>
                            </div>
                            <div class="progress" style="height:8px;" role="progressbar"
                                 aria-label="<?php echo $nombre; ?>" aria-valuenow="<?php echo (int) $pct; ?>"
                                 aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-<?php echo ['basketball'=>'primary','arena'=>'info','league'=>'purple'][$k] ?? 'secondary'; ?>"
                                     style="width:<?php echo number_format($pct, 1); ?>%"></div>
                            </div>
                            <small class="text-muted">$<?php echo number_format($ing[$k], 2); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Cada módulo ==================== -->
<div class="row">
    <?php foreach ($modulos as $m): ?>
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="<?php echo $m['icono']; ?> me-2"></i><?php echo $m['nombre']; ?>
                    </h3>
                    <a href="<?php echo $m['url']; ?>" class="small">abrir módulo →</a>
                </div>
                <div class="card-body">
                    <div class="fs-4 fw-bold mb-3">$<?php echo number_format($m['ingreso'], 2); ?></div>
                    <?php foreach ($m['lineas'] as [$etiqueta, $valor]): ?>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted"><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ==================== Requiere tu atención ==================== -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="fas fa-bell me-2"></i>Requiere tu atención</h3>
    </div>
    <div class="card-body">
        <?php if (count($avisos) === 0): ?>
            <p class="text-muted mb-0">
                Nada reclama atención con los datos actuales.
            </p>
        <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($avisos as $a): ?>
                    <li class="d-flex align-items-start py-2 border-bottom">
                        <i class="fas <?php echo $a['icono']; ?> text-<?php echo $a['tono']; ?> me-3 mt-1"></i>
                        <span><?php echo htmlspecialchars($a['texto'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if (count($serie) > 0): ?>
<script src="<?php echo DS_INSIGHTS_GRAFICOS_JS; ?>"></script>
<script src="<?php echo ds_recurso('ds_insights/assets/js/graficos.js'); ?>"></script>
<script>
/*
| El gráfico se pinta desde el aplicativo: ApexCharts está autoalojada en
| ds_core/assets/vendor/, sin CDN, igual que el resto del vendor.
|
| Los colores del texto y de la rejilla se leen del tema activo en vez de
| fijarse: con el tema claro, unas etiquetas gris claro sobre fondo blanco
| serían ilegibles. Es el mismo defecto que ya apareció tres veces en este
| proyecto —fondo fijo, color heredado— y aquí se evita desde el principio.
*/
(function () {
    var datos = <?php echo json_encode(array_map(static fn(array $f): array => [
        'mes' => $f['mes'],
        'bk'  => round((float) $f['basketball'], 2),
        'ar'  => round((float) $f['arena'], 2),
        'lg'  => round((float) $f['league'], 2),
    ], $serie), JSON_UNESCAPED_UNICODE); ?>;

    var meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    var etiquetas = datos.map(function (d) {
        var p = d.mes.split('-');
        return meses[parseInt(p[1], 10) - 1] + ' ' + p[0].slice(2);
    });

    dsGrafico('grafico-evolucion', {
        chart: { type: 'area', height: 300, stacked: true, toolbar: { show: false } },
        colors: ['#3b82f6', '#22d3ee', '#a78bfa'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        series: [
            { name: 'Fútbol', data: datos.map(function (d) { return d.bk; }) },
            { name: 'Arena',      data: datos.map(function (d) { return d.ar; }) },
            { name: 'League',     data: datos.map(function (d) { return d.lg; }) }
        ],
        xaxis: { categories: etiquetas },
        yaxis: { labels: {
                 formatter: function (v) { return '$' + Math.round(v).toLocaleString('es-EC'); } } },
        grid: { strokeDashArray: 3 },
        tooltip: { y: { formatter: function (v) { return '$' + v.toFixed(2); } } }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
