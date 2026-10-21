<?php
/*
| Categorías de un torneo.
|
| Es la pantalla donde se define lo que de verdad rige la competencia: el
| rango de edad con su fecha de corte y cuánto vale cada resultado. Ambas
| cosas se piden aquí y no más adelante porque el fixture y la
| clasificación las dan por hechas.
*/

use league\controllers\competenciaController;

$insLeague = new competenciaController();

$torneoId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);

$torneos    = $insLeague->torneos();
$categorias = $insLeague->categorias($torneoId);

$torneo = [];
foreach ($torneos as $o) {
    if ((int)$o['torneo_id'] === $torneoId) { $torneo = $o; break; }
}

$tituloVista = $torneo ? 'Categorías · ' . $torneo['torneo_nombre'] : 'Categorías';
$vistaActual = 'categoriaList';

$generos = ['M' => 'Masculino', 'F' => 'Femenino', 'X' => 'Mixto'];

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if (!$torneos): ?>
<div class="callout callout-warning">
    <h6 class="mb-1"><i class="fas fa-exclamation-circle me-2"></i>No hay torneos</h6>
    <p class="mb-0 text-muted">
        Una categoría pertenece a un torneo.
        <a href="<?php echo APP_URL; ?>torneoList/">Cree uno primero</a>.
    </p>
</div>
<?php else: ?>

<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-layer-group me-2"></i>Categorías</h3>
                <?php if ($torneoId > 0): ?>
                    <a href="<?php echo APP_URL; ?>categoriaList/" class="ds-link">Ver todas →</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Género</th>
                                <th>Edad</th>
                                <th class="text-end">Equipos</th>
                                <th class="ds-tabla-acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$categorias): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                No hay categorías<?php echo $torneoId > 0 ? ' en este torneo' : ''; ?>.
                            </td></tr>
                        <?php else: foreach ($categorias as $c):
                            $edad = '—';
                            if ($c['categoria_edadmin'] !== null || $c['categoria_edadmax'] !== null) {
                                $edad = trim(($c['categoria_edadmin'] ?? '') . '–' . ($c['categoria_edadmax'] ?? ''), '–');
                                $edad .= ' años';
                            }
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo $h($c['categoria_nombre']); ?></strong>
                                    <br><small class="text-muted"><?php echo $h($c['torneo_nombre']); ?></small>
                                </td>
                                <td class="text-muted"><?php echo $h($generos[$c['categoria_genero']] ?? '—'); ?></td>
                                <td class="text-muted">
                                    <?php echo $h($edad); ?>
                                    <?php if ($c['categoria_fechacorte']): ?>
                                        <br><small>al <?php echo $h($c['categoria_fechacorte']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?php echo (int)$c['equipos']; ?></td>
                                <td class="ds-tabla-acciones">
                                    <a href="<?php echo APP_URL; ?>categoriaPanel/<?php echo (int)$c['categoria_id']; ?>/"
                                       class="btn btn-sm btn-ver" title="Abrir la competencia">
                                        <i class="fas fa-list-ol"></i>
                                    </a>
                                    <?php if (puede_editar('categoriaList')): ?>
                                    <button type="button" class="btn btn-sm btn-actualizar js-editar" title="Editar"
                                            data-fila='<?php echo $h(json_encode([
                                                'categoria_id'         => (int)$c['categoria_id'],
                                                'categoria_nombre'     => $c['categoria_nombre'],
                                                'categoria_torneoid'   => (int)$c['categoria_torneoid'],
                                                'categoria_genero'     => $c['categoria_genero'],
                                                'categoria_edadmin'    => $c['categoria_edadmin'],
                                                'categoria_edadmax'    => $c['categoria_edadmax'],
                                                'categoria_fechacorte' => $c['categoria_fechacorte'],
                                                'categoria_ptsvictoria'=> (int)$c['categoria_ptsvictoria'],
                                                'categoria_ptsderrota' => (int)$c['categoria_ptsderrota'],
                                                'categoria_ptswalkover'=> (int)$c['categoria_ptswalkover'],
                                            ], JSON_UNESCAPED_UNICODE)); ?>'>
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-3">
        <?php if (puede_crear('categoriaList') || puede_editar('categoriaList')): ?>
        <form class="FormularioAjax" method="POST" autocomplete="off"
              action="<?php echo APP_URL; ?>ajax/leagueAjax.php" id="formFila">
            <input type="hidden" name="modulo_league" value="guardarCategoria">
            <input type="hidden" name="categoria_id" id="categoria_id" value="0">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="tituloForm">
                        <i class="fas fa-plus me-2"></i>Nueva categoría
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="categoria_nombre">Nombre</label>
                        <input type="text" name="categoria_nombre" id="categoria_nombre"
                               class="form-control" maxlength="80" placeholder="Sub-14 Masculino" required>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-7">
                            <label for="categoria_torneoid">Torneo</label>
                            <select name="categoria_torneoid" id="categoria_torneoid" class="form-select" required>
                                <?php foreach ($torneos as $o): ?>
                                    <option value="<?php echo (int)$o['torneo_id']; ?>"
                                        <?php echo (int)$o['torneo_id'] === $torneoId ? 'selected' : ''; ?>>
                                        <?php echo $h($o['torneo_nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 col-5">
                            <label for="categoria_genero">Género</label>
                            <select name="categoria_genero" id="categoria_genero" class="form-select">
                                <?php foreach ($generos as $k => $v): ?>
                                    <option value="<?php echo $k; ?>"><?php echo $h($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-4">
                            <label for="categoria_edadmin">Edad mín.</label>
                            <input type="number" name="categoria_edadmin" id="categoria_edadmin"
                                   class="form-control" min="4" max="99">
                        </div>
                        <div class="mb-3 col-4">
                            <label for="categoria_edadmax">Edad máx.</label>
                            <input type="number" name="categoria_edadmax" id="categoria_edadmax"
                                   class="form-control" min="4" max="99">
                        </div>
                        <div class="mb-3 col-4">
                            <label for="categoria_fechacorte">Medida al</label>
                            <input type="date" name="categoria_fechacorte" id="categoria_fechacorte"
                                   class="form-control">
                        </div>
                    </div>
                    <p class="text-muted" style="font-size:.85rem;">
                        Con un rango de edad, la fecha de corte es obligatoria: sin ella
                        «Sub-14» significa algo distinto cada mes.
                    </p>

                    <hr>
                    <p class="text-muted mb-2"
                       style="font-size:.72rem;letter-spacing:.09em;text-transform:uppercase;font-weight:700;">
                        Puntuación de la tabla
                    </p>
                    <div class="row g-2">
                        <div class="mb-3 col-4">
                            <label for="categoria_ptsvictoria">Victoria</label>
                            <input type="number" name="categoria_ptsvictoria" id="categoria_ptsvictoria"
                                   class="form-control" min="0" max="10" value="2" required>
                        </div>
                        <div class="mb-3 col-4">
                            <label for="categoria_ptsderrota">Derrota</label>
                            <input type="number" name="categoria_ptsderrota" id="categoria_ptsderrota"
                                   class="form-control" min="0" max="10" value="1" required>
                        </div>
                        <div class="mb-3 col-4">
                            <label for="categoria_ptswalkover">Walkover</label>
                            <input type="number" name="categoria_ptswalkover" id="categoria_ptswalkover"
                                   class="form-control" min="0" max="10" value="0" required>
                        </div>
                    </div>
                    <p class="text-muted mb-0" style="font-size:.85rem;">
                        Por omisión, las reglas FIBA: 2 por victoria y 1 por derrota jugada,
                        de modo que no presentarse cueste más que perder.
                    </p>
                </div>
                <?php echo ds_acciones_form(APP_URL . 'torneoList/', ['limpiar' => true]); ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . "/inc/editor-fila.php"; ?>
<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
