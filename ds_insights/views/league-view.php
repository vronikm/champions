<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — League Analytics
|--------------------------------------------------------------------------
| Torneos, participación, calendario, clasificación y recaudación.
|
|
| LA PUNTUACIÓN NO ESTÁ ESCRITA AQUÍ
|
| Sale de `dsl_categoria` —puntos por victoria, por derrota y por walkover—
| y de `dsl_estado.estado_efectivo`, que dice qué partidos cuentan. Un
| walkover suma; un cancelado es final pero no cuenta.
|
| Escribir «2 puntos por victoria» en la consulta habría funcionado hoy y
| habría mentido el día que alguien cambiara la regla desde la pantalla de
| categorías, sin que nada avisara.
|
| Se ve en los datos reales: el Club Pumas suma 12 con cinco victorias y tres
| derrotas, cuando 5×2 + 3×1 daría 13. La diferencia es un walkover perdido,
| que vale 0 en vez de 1. Una tabla con la regla cableada habría dicho 13.
|
|
| LEAGUE NO TIENE SEDE, PERO SÍ ESCENARIO
|
| Sus torneos pueden organizarse fuera del club, así que no lleva sede. Pero
| `partido_instalacionid` apunta a una instalación de Arena cuando el partido
| sí se juega en casa, y ese reparto sí se puede saber.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$p = $insInsights->periodo($_GET['desde'] ?? null, $_GET['hasta'] ?? null);

/* Esta vista NO lleva selector de periodo: casi todo lo que muestra —la
   clasificacion, los proximos partidos, la participacion— es el estado del
   torneo, no un recorte temporal. El desglose por fechas esta en la vista
   financiera. */
$res       = $insInsights->leagueResumen($p);
$torneos   = $insInsights->torneos();
$cats      = $insInsights->categoriasConPartidos();
$proximos  = $insInsights->proximosPartidos(8);
$escen     = $insInsights->escenarios();
$anomalias = $insInsights->leagueAnomalias();

/* La categoría de la tabla llega por GET; si no, la primera con partidos. */
$catId = isset($_GET['categoria']) ? (int) $_GET['categoria'] : (int) ($cats[0]['id'] ?? 0);
$tabla = $catId > 0 ? $insInsights->tablaPosiciones($catId) : ['categoria' => null, 'filas' => []];

$tituloVista = 'League';
$vistaActual = 'league';

require_once __DIR__ . "/inc/layout-top.php";
?>

<!-- ==================== Las cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($res['torneos']); ?></h3>
                <p class="mb-0">Torneos activos</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($res['categorias']); ?> categorías</span></p>
            </div>
            <i class="fas fa-trophy small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-info h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($res['inscritos']); ?></h3>
                <p class="mb-0">Equipos inscritos</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"
                          title="Jugadores con ficha habilitada. Los no habilitados no pueden jugar.">
                        <?php echo number_format($res['jugadores']); ?> de <?php echo number_format($res['fichas']); ?> jugadores habilitados
                    </span></p>
            </div>
            <i class="fas fa-people-group small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-success h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($res['jugados']); ?></h3>
                <p class="mb-0">Partidos jugados</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($res['pendientes']); ?> por jugar de <?php echo number_format($res['partidos']); ?></span></p>
            </div>
            <i class="fas fa-basketball small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-warning h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($res['recaudado'], 2); ?></h3>
                <p class="mb-0">Recaudado</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75">$<?php echo number_format($res['pendienteCobro'], 2); ?> pendiente</span></p>
            </div>
            <i class="fas fa-dollar-sign small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<!-- ==================== Torneos ==================== -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0">Torneos</h3>
        <?php echo ds_botones_exportar('torneos', 'league', $p); ?>
    </div>
    <div class="card-body">
        <?php if (count($torneos) === 0): ?>
            <p class="text-muted mb-0">No hay torneos registrados.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Torneo</th>
                            <th class="text-end">Categorías</th>
                            <th class="text-end">Equipos</th>
                            <th class="text-end">Jugadores</th>
                            <th class="text-end">Partidos</th>
                            <th class="text-end">Jugados</th>
                            <th class="text-end">Recaudado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($torneos as $t): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars((string) $t['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($t['desde']): ?>
                                    <small class="text-muted d-block">
                                        <?php echo htmlspecialchars((string) $t['desde'], ENT_QUOTES, 'UTF-8'); ?>
                                        — <?php echo htmlspecialchars((string) ($t['hasta'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo number_format((int) $t['categorias']); ?></td>
                            <td class="text-end"><?php echo number_format((int) $t['equipos']); ?></td>
                            <td class="text-end"><?php echo number_format((int) $t['jugadores']); ?></td>
                            <td class="text-end text-muted"><?php echo number_format((int) $t['partidos']); ?></td>
                            <td class="text-end"><?php echo number_format((int) $t['jugados']); ?></td>
                            <td class="text-end fw-semibold">$<?php echo number_format((float) $t['recaudado'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- ==================== Tabla de posiciones ==================== -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0">Tabla de posiciones</h3>
                <?php if (count($cats) > 1): ?>
                    <form method="get" class="d-flex align-items-center gap-2">
                        <label for="categoria" class="visually-hidden">Categoría</label>
                        <select name="categoria" id="categoria" class="form-select form-select-sm"
                                style="width:auto;" onchange="this.form.submit()">
                            <?php foreach ($cats as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>"
                                    <?php echo (int) $c['id'] === $catId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $c['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-sm btn-secondary">Ver</button></noscript>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($tabla['filas']) === 0): ?>
                    <p class="text-muted mb-0">
                        Todavía no hay partidos jugados en esta categoría, así que no hay
                        clasificación que mostrar.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-variant-numeric:tabular-nums;">
                            <thead>
                                <tr>
                                    <th style="width:2rem;">#</th>
                                    <th>Equipo</th>
                                    <th class="text-end" title="Partidos jugados">PJ</th>
                                    <th class="text-end" title="Ganados">PG</th>
                                    <th class="text-end" title="Perdidos">PP</th>
                                    <th class="text-end" title="Puntos a favor">PF</th>
                                    <th class="text-end" title="Puntos en contra">PC</th>
                                    <th class="text-end" title="Diferencia">DIF</th>
                                    <th class="text-end">Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tabla['filas'] as $i => $f): ?>
                                <tr>
                                    <td class="text-muted"><?php echo $i + 1; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars((string) $f['equipo'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ((int) $f['walkovers'] > 0): ?>
                                            <span class="badge text-bg-secondary ms-1"
                                                  title="Partidos por walkover: puntúan distinto que una derrota normal">
                                                <?php echo (int) $f['walkovers']; ?> W
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo (int) $f['jugados']; ?></td>
                                    <td class="text-end"><?php echo (int) $f['ganados']; ?></td>
                                    <td class="text-end"><?php echo (int) $f['perdidos']; ?></td>
                                    <td class="text-end text-muted"><?php echo (int) $f['favor']; ?></td>
                                    <td class="text-end text-muted"><?php echo (int) $f['contra']; ?></td>
                                    <td class="text-end <?php echo (int) $f['diferencia'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ((int) $f['diferencia'] >= 0 ? '+' : '') . (int) $f['diferencia']; ?>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo (int) $f['puntos']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        Puntuación de esta categoría: victoria <?php echo (int) $tabla['categoria']['v']; ?>,
                        derrota <?php echo (int) $tabla['categoria']['d']; ?>,
                        walkover <?php echo (int) $tabla['categoria']['w']; ?>.
                        Se lee de la configuración de la categoría, no está escrita en el informe.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== Próximos y estado ==================== -->
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Próximos partidos</h3>
            </div>
            <div class="card-body">
                <?php if (count($proximos) === 0): ?>
                    <p class="text-muted mb-0">No hay partidos programados por delante.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($proximos as $x): ?>
                            <li class="py-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div><?php echo htmlspecialchars((string) $x['local'], ENT_QUOTES, 'UTF-8'); ?>
                                             <span class="text-muted">vs</span>
                                             <?php echo htmlspecialchars((string) $x['visitante'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars((string) $x['categoria'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($x['escenario']): ?>
                                                · <?php echo htmlspecialchars((string) $x['escenario'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="text-end" style="white-space:nowrap;">
                                        <div class="small"><?php echo htmlspecialchars((string) $x['fecha'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small class="text-muted"><?php echo substr((string) $x['hora'], 0, 5); ?></small>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Escenarios</h3>
            </div>
            <div class="card-body">
                <?php if ($escen['total'] === 0): ?>
                    <p class="text-muted mb-0">No hay partidos registrados.</p>
                <?php else: ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span class="text-muted">En instalaciones propias</span>
                        <span class="fw-semibold"><?php echo number_format($escen['propios']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Fuera del club</span>
                        <span class="fw-semibold"><?php echo number_format($escen['externos']); ?></span>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        League no lleva sede porque sus torneos pueden organizarse fuera del
                        club. Pero cuando el partido se juega en una instalación de Arena queda
                        registrado, y ese reparto sí se puede saber.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-triangle-exclamation me-2"></i>Conviene revisar</h3>
            </div>
            <div class="card-body">
                <?php if (count($anomalias) === 0): ?>
                    <p class="text-muted mb-0">Nada incoherente en League.</p>
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
