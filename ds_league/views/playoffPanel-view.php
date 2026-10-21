<?php
/*
| Eliminatorias de una categoría.
|
| El cuadro se siembra a partir de la clasificación de una fase anterior,
| y cada llave se resuelve al mejor de N. La pantalla enseña el marcador
| DE LA SERIE —partidos ganados— junto al de cada encuentro, porque son
| dos cosas distintas y confundirlas es el error habitual: gana la serie
| quien más partidos ganó, no quien más puntos anotó.
|
| Los encuentros cancelados por serie decidida se muestran atenuados en
| vez de ocultarse: el calendario publicado los tenía, y hacerlos
| desaparecer sin más deja a la gente buscando un partido que sí estaba
| anunciado.
*/

use league\controllers\playoffController;

$insLeague = new playoffController();

$categoriaId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);
$categoria   = $insLeague->categoria($categoriaId);

if (!$categoria) {
    $tituloVista = 'Eliminatorias';
    $vistaActual = 'playoffPanel';
    require_once __DIR__ . "/inc/layout-top.php";
    echo '<div class="callout callout-warning"><h6 class="mb-1">'
       . '<i class="fas fa-exclamation-circle me-2"></i>Categoría no encontrada</h6>'
       . '<p class="mb-0 text-muted">Elija una desde '
       . '<a href="' . APP_URL . 'categoriaList/">el listado</a>.</p></div>';
    require_once __DIR__ . "/inc/layout-bottom.php";
    return;
}

$tituloVista = 'Eliminatorias · ' . $categoria['categoria_nombre'];
$vistaActual = 'playoffPanel';

$fases = $insLeague->fasesDeCategoria($categoriaId);

/* Fases que pueden servir de origen (las que ya tienen resultados) y las
   que son eliminatorias. */
$origenes = [];
$elimina  = [];
foreach ($fases as $f) {
    if ($f['fase_tipo'] === 'G') { $origenes[] = $f; }
    else                         { $elimina[]  = $f; }
}

$faseActiva = $elimina[0] ?? null;
$faseElimId = (int)($faseActiva['fase_id'] ?? 0);
$series     = $faseElimId > 0 ? $insLeague->seriesDeFase($faseElimId) : [];

$tono = ['exito' => 'success', 'aviso' => 'warning', 'peligro' => 'danger',
         'info' => 'info', 'neutro' => 'secondary'];

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<p class="text-muted mb-3">
    <a href="<?php echo APP_URL; ?>categoriaPanel/<?php echo $categoriaId; ?>/"
       class="ds-link">← <?php echo $h($categoria['categoria_nombre']); ?></a>
    <span class="mx-2">·</span><?php echo $h($categoria['torneo_nombre']); ?>
</p>

<?php if (!$elimina): ?>
<!-- ==================== No hay fase eliminatoria ==================== -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sitemap me-2"></i>Crear la fase eliminatoria</h3>
            </div>
            <?php if (puede_crear('playoffPanel')): ?>
            <form class="FormularioAjax" method="POST"
                  action="<?php echo APP_URL; ?>ajax/leagueAjax.php">
                <input type="hidden" name="modulo_league" value="guardarFase">
                <input type="hidden" name="categoria_id" value="<?php echo $categoriaId; ?>">
                <div class="card-body">
                    <p class="text-muted">
                        Una eliminatoria es una fase más de la categoría, que va después de
                        los grupos. Se crea aquí y luego se siembra el cuadro con los
                        clasificados.
                    </p>
                    <div class="mb-3">
                        <label for="fase_nombre">Nombre</label>
                        <input type="text" name="fase_nombre" id="fase_nombre" class="form-control"
                               maxlength="60" value="Semifinales" required>
                    </div>
                    <div class="mb-3 mb-0">
                        <label for="fase_tipo">Formato</label>
                        <select name="fase_tipo" id="fase_tipo" class="form-select">
                            <option value="S">Series al mejor de N</option>
                            <option value="E">Eliminación directa (partido único)</option>
                        </select>
                    </div>
                </div>
                <?php echo ds_acciones_form('', ['guardar' => 'Crear fase']); ?>
            </form>
            <?php else: ?>
            <div class="card-body">
                <p class="text-muted mb-0">Su rol no puede crear fases.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>

<div class="row">
    <!-- ==================== Sembrar el cuadro ==================== -->
    <div class="col-lg-4 mb-3">
        <?php if (!$series && puede_crear('playoffPanel')): ?>
        <form class="FormularioAjax" method="POST"
              action="<?php echo APP_URL; ?>ajax/leagueAjax.php">
            <input type="hidden" name="modulo_league" value="generarLlaves">
            <input type="hidden" name="fase_id" value="<?php echo $faseElimId; ?>">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sitemap me-2"></i>Sembrar el cuadro</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="fase_origen">Clasifican desde</label>
                        <select name="fase_origen" id="fase_origen" class="form-select" required>
                            <?php foreach ($origenes as $o): ?>
                                <option value="<?php echo (int)$o['fase_id']; ?>">
                                    <?php echo $h($o['fase_nombre']); ?>
                                    (<?php echo (int)$o['partidos']; ?> partidos)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$origenes): ?>
                            <small class="form-text text-danger">
                                No hay ninguna fase de grupos de la que sacar clasificados.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-6">
                            <label for="clasifican">Clasifican por grupo</label>
                            <input type="number" name="clasifican" id="clasifican" class="form-control"
                                   min="1" max="8" value="2" required>
                        </div>
                        <div class="mb-3 col-6">
                            <label for="mejor_de">Al mejor de</label>
                            <select name="mejor_de" id="mejor_de" class="form-select">
                                <option value="1">1 · partido único</option>
                                <option value="3">3</option>
                                <option value="5">5</option>
                                <option value="7">7</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-muted mb-0" style="font-size:.85rem;">
                        La siembra es cruzada: el primero de un grupo no se enfrenta al
                        segundo del suyo, porque acaban de jugar entre ellos.
                    </p>
                </div>
                <?php echo ds_acciones_form('', ['guardar' => 'Generar cuadro']); ?>
            </div>
        </form>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-layer-group me-2"></i>Fases</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($fases as $f): ?>
                        <tr<?php echo (int)$f['fase_id'] === $faseElimId ? ' class="table-active"' : ''; ?>>
                            <td>
                                <strong><?php echo $h($f['fase_nombre']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo ['G'=>'Grupos','E'=>'Eliminación directa','S'=>'Series'][$f['fase_tipo']] ?? '—'; ?>
                                    · <?php echo (int)$f['partidos']; ?> partidos
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== El cuadro ==================== -->
    <div class="col-lg-8 mb-3">
        <?php if (!$series): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    Todavía no hay cuadro. Siémbrelo con los clasificados de la fase de grupos.
                </div>
            </div>
        <?php else: foreach ($series as $s):
            $umbral    = $insLeague->umbral((int)$s['serie_mejorde']);
            $cerrada   = $s['serie_estado'] === 'CERRADA';
            $partidos  = $insLeague->partidosDeSerie((int)$s['serie_id']);
        ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    <?php echo $h($s['serie_nombre']); ?>
                    <span class="text-muted" style="font-weight:400;font-size:.85rem;">
                        · al mejor de <?php echo (int)$s['serie_mejorde']; ?>
                        (se gana con <?php echo $umbral; ?>)
                    </span>
                </h3>
                <span class="badge text-bg-<?php echo $cerrada ? 'success' : 'secondary'; ?>">
                    <?php echo $cerrada ? 'Decidida' : 'En juego'; ?>
                </span>
            </div>

            <!-- Marcador DE LA SERIE: partidos ganados, no puntos -->
            <?php /* Fondo claro explicito: --ds-surface-2 es de la paleta OSCURA
                     del ecosistema y aqui la pagina la pinta AdminLTE en claro,
                     asi que el token dejaba texto oscuro sobre fondo oscuro. */ ?>
            <div class="card-body py-2" style="background:var(--bs-tertiary-bg);border-top:1px solid var(--bs-border-color);
                                                border-bottom:1px solid var(--bs-border-color);">
                <div class="d-flex align-items-center justify-content-center"
                     style="gap:1.2rem;font-size:1.05rem;">
                    <span<?php echo $cerrada && (int)$s['serie_ganadorid'] === (int)$s['serie_localid']
                            ? ' style="font-weight:700"' : ''; ?>>
                        <?php echo $h($s['local'] ?? '—'); ?>
                    </span>
                    <strong style="font-variant-numeric:tabular-nums;font-size:1.3rem;">
                        <?php echo (int)$s['serie_ganadas_local']; ?>
                        –
                        <?php echo (int)$s['serie_ganadas_visitante']; ?>
                    </strong>
                    <span<?php echo $cerrada && (int)$s['serie_ganadorid'] === (int)$s['serie_visitanteid']
                            ? ' style="font-weight:700"' : ''; ?>>
                        <?php echo $h($s['visitante'] ?? '—'); ?>
                    </span>
                </div>
                <?php if ($cerrada): ?>
                    <p class="text-center text-muted mb-0 mt-1" style="font-size:.85rem;">
                        <i class="fas fa-trophy text-warning me-1"></i>
                        Pasa <strong><?php echo $h($s['ganador']); ?></strong>
                    </p>
                <?php endif; ?>
            </div>

            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($partidos as $i => $p):
                        $cancelado = $p['estado_codigo'] === 'CANCELADO';
                        $jugado    = $p['estado_efectivo'] === 'S';
                    ?>
                        <tr<?php echo $cancelado ? ' style="opacity:.5"' : ''; ?>>
                            <td class="text-muted" style="width:3.5rem;">
                                #<?php echo $i + 1; ?>
                            </td>
                            <td><?php echo $h($p['local']); ?></td>
                            <td class="text-center" style="width:9rem;">
                                <?php if ($jugado): ?>
                                    <strong><?php echo (int)$p['partido_puntoslocal']; ?>
                                            – <?php echo (int)$p['partido_puntosvisitante']; ?></strong>
                                <?php elseif ($cancelado): ?>
                                    <span class="text-muted" style="font-size:.85rem;">no se juega</span>
                                <?php elseif (puede_editar('categoriaPanel')): ?>
                                    <form class="FormularioAjax d-inline-flex" method="POST"
                                          action="<?php echo APP_URL; ?>ajax/leagueAjax.php"
                                          style="gap:.25rem;">
                                        <input type="hidden" name="modulo_league" value="guardarResultado">
                                        <input type="hidden" name="partido_id" value="<?php echo (int)$p['partido_id']; ?>">
                                        <input type="number" name="puntos_local" class="form-control form-control-sm"
                                               style="width:3.4rem;" min="0" max="300" required>
                                        <input type="number" name="puntos_visitante" class="form-control form-control-sm"
                                               style="width:3.4rem;" min="0" max="300" required>
                                        <button type="submit" class="btn btn-sm btn-primary" title="Guardar">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $h($p['visitante']); ?></td>
                            <td style="width:8rem;">
                                <span class="badge text-bg-<?php echo $tono[$p['estado_tono']] ?? 'secondary'; ?>">
                                    <?php echo $h($p['estado_nombre']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($cerrada): ?>
            <div class="card-footer text-muted" style="font-size:.85rem;">
                Los partidos que quedaban se cancelaron al decidirse la serie, y sus canchas
                quedaron libres en Arena.
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
