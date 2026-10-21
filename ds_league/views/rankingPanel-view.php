<?php
/*
| Líderes de una categoría.
|
| Los rankings salen de la misma tabla estrecha que el acta, agregando por
| jugador. Por eso admiten cualquier estadística del catálogo sin tocar
| código: añadir «tapones» al catálogo de un deporte lo pone aquí sin
| escribir una consulta nueva.
|
| SE ORDENA POR TOTAL Y SE MUESTRA EL PROMEDIO
|
| Son dos lecturas distintas de lo mismo y las dos importan: el máximo
| anotador de la temporada es un total, pero quién anota más POR PARTIDO es
| un promedio, y con pocos encuentros jugados el promedio engaña. Mostrar
| las dos columnas evita tener que elegir por el usuario.
*/

use league\controllers\estadisticaController;

$insLeague = new estadisticaController();

$categoriaId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);
$categoria   = $insLeague->categoria($categoriaId);

if (!$categoria) {
    $tituloVista = 'Líderes';
    $vistaActual = 'rankingPanel';
    require_once __DIR__ . "/inc/layout-top.php";
    echo '<div class="callout callout-warning"><h6 class="mb-1">'
       . '<i class="fas fa-exclamation-circle me-2"></i>Categoría no encontrada</h6>'
       . '<p class="mb-0 text-muted">Elija una desde '
       . '<a href="' . APP_URL . 'categoriaList/">el listado</a>.</p></div>';
    require_once __DIR__ . "/inc/layout-bottom.php";
    return;
}

$tituloVista = 'Líderes · ' . $categoria['categoria_nombre'];
$vistaActual = 'rankingPanel';

$deporte = 'baloncesto';
$todos   = $insLeague->tiposPorCodigo($deporte);

/* Las estadísticas que tiene sentido rankear: las agregables. Un
   porcentaje o los minutos no encabezan una tabla de líderes. */
$rankeables = [];
foreach ($todos as $c => $t) {
    if ($t['tipo_agregable'] === 'S') { $rankeables[$c] = $t; }
}

$fotosUrl = league\controllers\competenciaController::fotosUrl();
$escudosUrl = league\controllers\competenciaController::escudosUrl();

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<p class="text-muted mb-3">
    <a href="<?php echo APP_URL; ?>categoriaPanel/<?php echo $categoriaId; ?>/"
       class="ds-link">← <?php echo $h($categoria['categoria_nombre']); ?></a>
    <span class="mx-2">·</span><?php echo $h($categoria['torneo_nombre']); ?>
</p>

<?php if (!$rankeables): ?>
<div class="callout callout-warning">
    <p class="mb-0 text-muted">
        No hay ninguna estadística marcada como rankeable en el catálogo de
        <?php echo $h($deporte); ?>.
    </p>
</div>
<?php else: ?>

<div class="row">
<?php foreach ($rankeables as $codigo => $tipo):
    $lideres = $insLeague->lideres($categoriaId, $codigo, 5, $deporte);
    if (!$lideres) { continue; }
?>
    <div class="col-xl-4 col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-medal me-2" style="color:var(--ds-league,#a78bfa);"></i>
                    <?php echo $h($tipo['tipo_nombre']); ?>
                </h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-variant-numeric:tabular-nums;">
                    <thead>
                        <tr>
                            <th style="width:1.8rem;"></th>
                            <th>Jugador</th>
                            <th class="text-end" style="width:3.2rem;"
                                title="Partidos jugados">PJ</th>
                            <th class="text-end" style="width:3.6rem;">Total</th>
                            <th class="text-end" style="width:3.8rem;"
                                title="Por partido">Prom.</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lideres as $i => $l): ?>
                        <tr>
                            <td class="text-muted"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center" style="gap:.45rem;min-width:0;">
                                    <?php if ($l['persona_foto']): ?>
                                        <img src="<?php echo $fotosUrl . rawurlencode($l['persona_foto']); ?>"
                                             alt="" style="width:26px;height:26px;object-fit:cover;
                                                          border-radius:50%;flex:0 0 auto;">
                                    <?php endif; ?>
                                    <span style="min-width:0;">
                                        <span style="display:block;white-space:nowrap;overflow:hidden;
                                                     text-overflow:ellipsis;">
                                            <?php echo $h($l['persona_apellidos'] . ' ' . $l['persona_nombres']); ?>
                                        </span>
                                        <small class="text-muted" style="display:block;white-space:nowrap;
                                                     overflow:hidden;text-overflow:ellipsis;">
                                            <?php echo $h($l['equipo_nombre']); ?>
                                        </small>
                                    </span>
                                </div>
                            </td>
                            <td class="text-end text-muted"><?php echo (int)$l['partidos']; ?></td>
                            <td class="text-end"><strong><?php echo (int)round($l['total']); ?></strong></td>
                            <td class="text-end text-muted">
                                <?php echo number_format((float)$l['promedio'], 1, ',', ''); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php
/* Si ninguna estadística devolvió líderes, es que no hay actas cargadas.
   Decirlo es más útil que dejar la pantalla en blanco. */
$hayAlguno = false;
foreach ($rankeables as $c => $t) {
    if ($insLeague->lideres($categoriaId, $c, 1, $deporte)) { $hayAlguno = true; break; }
}
if (!$hayAlguno): ?>
<div class="card">
    <div class="card-body text-center text-muted py-5">
        Todavía no hay actas cargadas en esta categoría.<br>
        Los líderes aparecen a medida que se registran las estadísticas de cada partido.
    </div>
</div>
<?php endif; ?>

<div class="callout" style="border-left-color:var(--ds-league,#a78bfa);">
    <p class="mb-0 text-muted" style="font-size:.88rem;">
        <strong>Total</strong> y <strong>promedio</strong> se muestran juntos a propósito:
        el máximo anotador de la temporada es un total, pero quién anota más por partido
        es un promedio, y con pocos encuentros jugados el promedio engaña.
    </p>
</div>

<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
