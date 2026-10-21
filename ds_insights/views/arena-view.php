<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Arena Analytics
|--------------------------------------------------------------------------
| Ocupación, mapa de calor y rentabilidad de las instalaciones.
|
|
| EL MAPA DE CALOR VA EN PORCENTAJE, NO EN NÚMERO DE RESERVAS
|
| Cinco reservas a las 21:00 con seis instalaciones abiertas es mucho; las
| mismas cinco a las 10:00 con veinte franjas disponibles es poco. Un mapa
| de conteos pintaría las dos igual y llevaría a subir la tarifa donde no
| toca.
|
| Y las franjas en que no abre ninguna instalación se pintan en blanco, no
| en cero: cerrado no es vacío. Un cero invita a preguntar por qué no se
| vende; un cerrado no.
|
|
| SE HACE CON UNA TABLA Y NO CON UN GRÁFICO
|
| Son 14 franjas por 7 días: una tabla se lee con lector de pantalla, lleva
| el valor exacto en cada celda y distingue el cerrado del cero, cosa que un
| heatmap de ApexCharts no hace sin trucos. El gráfico habría sido más
| vistoso y menos útil.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$p = $insInsights->periodo($_GET['desde'] ?? null, $_GET['hasta'] ?? null);

$res       = $insInsights->arenaResumen($p);
$ocu       = $insInsights->ocupacion($p);
$ocuAnt    = $insInsights->ocupacion($p, true);
$ranking   = $insInsights->ocupacionPorInstalacion($p);
$mapa      = $insInsights->mapaCalor($p);
$anomalias = $insInsights->arenaAnomalias();

$varOcupacion = $ocuAnt['reservadas'] > 0 ? $ocu['pct'] - $ocuAnt['pct'] : null;

$tituloVista = 'Arena';
$vistaActual = 'arena';

/*
| Estilo de una celda del mapa: fondo y color de texto, los dos explícitos.
|
| LA CELDA NO SIGUE AL TEMA, Y ES DELIBERADO
|
| La primera versión pintaba el fondo con cian semitransparente y dejaba el
| color del texto al tema. En claro funcionaba; en oscuro las celdas cálidas
| quedaban con texto claro sobre cian brillante: 2,54 de contraste, medido.
| El fondo lo ponía yo y el texto el tema, y no se coordinaban.
|
| LA RAMPA NO LLEGA AL CIAN BRILLANTE, Y ESO TAMPOCO ES UN CAPRICHO
|
| Al hacerla opaca y elegir el texto por luminancia seguía fallando una banda
| intermedia: hacia el 39-40 % daba 2,48. No era un umbral mal puesto. Con
| texto negro o blanco, el punto donde ambos empatan da 3,83 de contraste:
| CUALQUIER rampa que pase por esa luminancia tiene una banda ilegible, se
| elija el umbral que se elija.
|
| Así que la rampa se queda por debajo de esa banda: del azul muy oscuro a un
| teal medio, siempre con texto claro. Pierde algo de fuerza visual y gana que
| las 94 celdas se lean. Un mapa vistoso que no se puede leer en su franja más
| interesante no sirve de nada.
*/
function ds_calor(?float $pct): string
{
    if ($pct === null) { return 'background:transparent;'; }

    $t = max(0.0, min(1.0, $pct / 55));

    /* Ambos extremos elegidos para que el texto claro rinda >= 4,5:
       #0f2033 da 13,1 y #0b5c73 da 5,9. Todo lo que hay entre ellos queda
       dentro del rango porque la luminancia crece de forma monótona. */
    $frio   = [15, 32, 51];    /* #0f2033 */
    $calido = [11, 92, 115];   /* #0b5c73 */

    $rgb = [];
    foreach ([0, 1, 2] as $i) {
        $rgb[$i] = (int) round($frio[$i] + ($calido[$i] - $frio[$i]) * $t);
    }

    return sprintf('background:rgb(%d,%d,%d);color:#e2e8f0;', $rgb[0], $rgb[1], $rgb[2]);
}
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
                <?php echo (int) $p['dias']; ?> días
            </div>
        </form>
    </div>
</div>

<!-- ==================== Las cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-info h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($ocu['pct'], 1); ?> %</h3>
                <p class="mb-0">Ocupación</p>
                <p class="mb-0 small opacity-75"><?php echo ds_variacion_sobre_color($varOcupacion, ' pts'); ?></p>
            </div>
            <i class="fas fa-percent small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($res['reservas']); ?></h3>
                <p class="mb-0">Reservas del periodo</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($res['vigentes']); ?> vigentes · <?php echo number_format($res['hoy']); ?> hoy</span></p>
            </div>
            <i class="fas fa-calendar-check small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-warning h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $res['cancelacion'] === null ? '—' : number_format($res['cancelacion'], 1) . ' %'; ?></h3>
                <p class="mb-0">Cancelaciones</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($res['canceladas']); ?> de <?php echo number_format($res['reservas']); ?></span></p>
            </div>
            <i class="fas fa-ban small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-success h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo $ocu['reservadas'] > 0
                    ? number_format($res['facturado'] / $ocu['reservadas'], 2) : '0.00'; ?></h3>
                <p class="mb-0">Ingreso por hora</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($ocu['reservadas'], 0); ?> horas reservadas</span></p>
            </div>
            <i class="fas fa-clock small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<!-- ==================== Mapa de calor ==================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Mapa de ocupación</h3>
        <span class="text-muted small">% de franjas ocupadas sobre las disponibles</span>
    </div>
    <div class="card-body">
        <?php if (count($mapa) === 0): ?>
            <p class="text-muted mb-0">
                No hay horarios declarados: sin ellos no se puede saber qué franjas estaban
                disponibles, y la ocupación no es calculable.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0" style="font-variant-numeric:tabular-nums;">
                    <caption class="text-muted small">
                        Cada celda es el porcentaje de franjas ocupadas en ese día de la semana
                        y esa hora. Las celdas vacías son horas en las que no abre ninguna
                        instalación.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width:4.5rem;">Hora</th>
                            <?php
                            $dias = [2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue',
                                     6 => 'Vie', 7 => 'Sáb', 1 => 'Dom'];
                            foreach ($dias as $etiqueta): ?>
                                <th scope="col" class="text-center"><?php echo $etiqueta; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mapa as $hora => $fila): ?>
                        <tr>
                            <th scope="row" class="text-muted fw-normal"><?php echo sprintf('%02d:00', $hora); ?></th>
                            <?php foreach (array_keys($dias) as $d):
                                $v = $fila[$d] ?? null; ?>
                                <td class="text-center" style="<?php echo ds_calor($v); ?>">
                                    <?php if ($v === null): ?>
                                        <span class="text-muted" title="No abre ninguna instalación">·</span>
                                    <?php else: ?>
                                        <?php echo number_format($v, 0); ?><span class="small">%</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- ==================== Ranking ==================== -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Instalaciones</h3>
                                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small">ocupación e ingreso por hora</span>
                    <?php echo ds_botones_exportar('instalaciones', 'arena', $p); ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($ranking) === 0): ?>
                    <p class="text-muted mb-0">Ninguna instalación activa con horario declarado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Instalación</th>
                                    <th>Sede</th>
                                    <th class="text-end">Reservas</th>
                                    <th class="text-end">Horas</th>
                                    <th class="text-end">Ingreso</th>
                                    <th class="text-end">Ocupación</th>
                                    <th class="text-end">$/hora</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($ranking as $i): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars((string) $i['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        <small class="text-muted d-block"><?php echo $i['clase'] === 'R' ? 'residencia' : 'cancha'; ?></small>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars((string) $i['sede'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end"><?php echo number_format((int) $i['reservas']); ?></td>
                                    <td class="text-end text-muted"><?php echo number_format((float) $i['horas'], 0); ?></td>
                                    <td class="text-end fw-semibold">$<?php echo number_format((float) $i['ingreso'], 2); ?></td>
                                    <td class="text-end"><?php echo $i['ocupacion'] !== null ? number_format((float) $i['ocupacion'], 1) . ' %' : '—'; ?></td>
                                    <td class="text-end"><?php echo $i['ingresoHora'] !== null ? '$' . number_format((float) $i['ingresoHora'], 2) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="callout mt-3 mb-0">
                        <strong>Ocupación e ingreso por hora dicen cosas distintas.</strong>
                        Una residencia se alquila por bloques largos: ocupa mucho y rinde poco
                        por hora. Una cancha, al revés. Decidir con una sola de las dos columnas
                        lleva a conclusiones opuestas según cuál se mire.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== Estado ==================== -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Cobro</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">Facturado en el periodo</span>
                    <span class="fw-semibold">$<?php echo number_format($res['facturado'], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">Pendiente de cobro</span>
                    <span class="fw-semibold text-warning">$<?php echo number_format($res['saldo'], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Clientes con reserva</span>
                    <span class="fw-semibold"><?php echo number_format($res['clientes']); ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-triangle-exclamation me-2"></i>Conviene revisar</h3>
            </div>
            <div class="card-body">
                <?php if (count($anomalias) === 0): ?>
                    <p class="text-muted mb-0">Nada incoherente en la configuración de Arena.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($anomalias as $a): ?>
                            <li class="d-flex align-items-start py-2 border-bottom">
                                <i class="fas <?php echo $a['icono']; ?> text-<?php echo $a['tono']; ?> me-3 mt-1"></i>
                                <span><?php echo htmlspecialchars($a['texto'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
