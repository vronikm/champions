<?php
/*
| Sorteo de grupos de una fase.
|
| La pantalla enseña la semilla en primer plano, y no como un detalle
| técnico escondido: es lo que permite que un tercero reproduzca el
| sorteo. Un acta que sólo dice «el equipo A quedó en el grupo 1» hay que
| creérsela; con la semilla, se comprueba.
|
| Se puede introducir una semilla a mano precisamente para eso: repetir un
| sorteo anterior y verificar que da lo mismo.
*/

use league\controllers\sorteoController;

$insLeague = new sorteoController();

$faseId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);

$fase = $insLeague->faseConContexto($faseId);

if (!$fase) {
    $tituloVista = 'Sorteo';
    $vistaActual = 'sorteoPanel';
    require_once __DIR__ . "/inc/layout-top.php";
    echo '<div class="callout callout-warning"><h6 class="mb-1">'
       . '<i class="fas fa-exclamation-circle me-2"></i>Fase no encontrada</h6>'
       . '<p class="mb-0 text-muted">Entre desde el panel de una categoría.</p></div>';
    require_once __DIR__ . "/inc/layout-bottom.php";
    return;
}

$tituloVista = 'Sorteo · ' . $fase['categoria_nombre'];
$vistaActual = 'sorteoPanel';

$equipos  = $insLeague->equiposDeCategoria((int)$fase['categoria_id']);
$sorteos  = $insLeague->sorteosDeFase($faseId);
$partidos = $insLeague->partidosEnFase($faseId);

$ultimo   = $sorteos[0] ?? null;
$reparto  = $ultimo ? $insLeague->resultadoSorteo((int)$ultimo['sorteo_id']) : [];

/* El resultado, agrupado para pintarlo. */
$porGrupo = [];
foreach ($reparto as $r) { $porGrupo[$r['resultado_grupo']][] = $r; }

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<p class="text-muted mb-3">
    <a href="<?php echo APP_URL; ?>categoriaPanel/<?php echo (int)$fase['categoria_id']; ?>/"
       class="ds-link">← <?php echo $h($fase['categoria_nombre']); ?></a>
    <span class="mx-2">·</span><?php echo $h($fase['torneo_nombre']); ?>
</p>

<?php if ($partidos > 0): ?>
<div class="callout callout-warning">
    <h6 class="mb-1"><i class="fas fa-lock me-2"></i>La fase ya tiene calendario</h6>
    <p class="mb-0 text-muted">
        Hay <?php echo $partidos; ?> partidos generados. Cambiar los grupos ahora dejaría
        encuentros programados que ya no corresponden, así que el sorteo queda bloqueado.
        Para rehacerlo hay que eliminar antes el calendario.
    </p>
</div>
<?php endif; ?>

<div class="row">
    <!-- ==================== Configuración ==================== -->
    <div class="col-lg-5 mb-3">
        <?php if ($partidos === 0 && puede_crear('sorteoPanel')): ?>
        <form class="FormularioAjax" method="POST" autocomplete="off"
              action="<?php echo APP_URL; ?>ajax/leagueAjax.php" id="formSorteo">
            <input type="hidden" name="modulo_league" value="ejecutarSorteo">
            <input type="hidden" name="fase_id" value="<?php echo $faseId; ?>">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-random me-2"></i>Nuevo sorteo</h3>
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label for="grupos">Número de grupos</label>
                        <input type="number" name="grupos" id="grupos" class="form-control"
                               min="1" max="32" value="2" required>
                        <small class="form-text text-muted">
                            <?php echo count($equipos); ?> equipos habilitados para repartir.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label>Cabezas de serie</label>
                        <div style="max-height:11rem;overflow-y:auto;border:1px solid #dee2e6;
                                    border-radius:4px;padding:.5rem;">
                            <?php if (!$equipos): ?>
                                <p class="text-muted mb-0" style="font-size:.85rem;">
                                    No hay equipos habilitados todavía.
                                </p>
                            <?php else: foreach ($equipos as $e): ?>
                                <label class="d-block mb-1" style="font-weight:400;font-size:.9rem;">
                                    <input type="checkbox" name="cabezas[]"
                                           value="<?php echo (int)$e['inscripcion_id']; ?>">
                                    <?php echo $h($e['equipo_nombre']); ?>
                                </label>
                            <?php endforeach; endif; ?>
                        </div>
                        <small class="form-text text-muted">
                            Van al bombo 1 y se reparten primero, uno por grupo. Si marca
                            tantas como grupos, cada grupo tendrá exactamente una.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="semilla">Semilla</label>
                        <input type="text" name="semilla" id="semilla" class="form-control text-monospace"
                               placeholder="Se genera automáticamente" inputmode="numeric">
                        <small class="form-text text-muted">
                            Déjela vacía para un sorteo nuevo. Para <strong>reproducir</strong> uno
                            anterior, copie aquí su semilla: dará exactamente el mismo resultado.
                        </small>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="observacion">Acta / observación</label>
                        <input type="text" name="observacion" id="observacion" class="form-control"
                               maxlength="300" placeholder="Ante quién se celebró, por ejemplo">
                    </div>
                </div>
                <?php echo ds_acciones_form('', ['guardar' => 'Sortear']); ?>
            </div>
        </form>
        <?php endif; ?>

        <!-- ==================== Historial ==================== -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history me-2"></i>Sorteos celebrados</h3>
            </div>
            <div class="card-body p-0">
                <?php if (!$sorteos): ?>
                    <p class="text-center text-muted py-4 mb-0">Ninguno todavía.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php foreach ($sorteos as $s):
                            $cfg = json_decode((string)$s['sorteo_config'], true) ?: [];
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo $h($s['sorteo_fecha']); ?></strong>
                                    <span class="badge text-bg-<?php
                                        echo $s['sorteo_estado'] === 'APLICADO' ? 'success' : 'secondary'; ?> ms-1">
                                        <?php echo $h($s['sorteo_estado']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo (int)($cfg['grupos'] ?? 0); ?> grupos ·
                                        <?php echo (int)$s['equipos']; ?> equipos ·
                                        por <?php echo $h($s['sorteo_usuario']); ?>
                                    </small>
                                    <?php if ($s['sorteo_observacion'] !== ''): ?>
                                        <br><small><?php echo $h($s['sorteo_observacion']); ?></small>
                                    <?php endif; ?>
                                    <div class="mt-1">
                                        <code class="js-semilla" style="cursor:pointer;font-size:.8rem;"
                                              title="Copiar al formulario para reproducirlo"
                                              data-semilla="<?php echo $h($s['sorteo_semilla']); ?>">
                                            semilla <?php echo $h($s['sorteo_semilla']); ?>
                                        </code>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-muted" style="font-size:.85rem;">
                Pulse una semilla para copiarla al formulario. Volver a sortear con ella
                da el mismo resultado: es lo que permite verificar un sorteo impugnado.
            </div>
        </div>
    </div>

    <!-- ==================== Resultado ==================== -->
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-layer-group me-2"></i>
                    <?php echo $ultimo ? 'Grupos formados' : 'Sin sortear'; ?>
                </h3>
            </div>
            <div class="card-body">
                <?php if (!$porGrupo): ?>
                    <p class="text-center text-muted py-4 mb-0">
                        Configure el sorteo y ejecútelo para formar los grupos.
                    </p>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($porGrupo as $nombre => $miembros): ?>
                    <div class="col-md-6 mb-3">
                        <div style="border:1px solid #dee2e6;border-radius:6px;overflow:hidden;">
                            <div style="background:var(--ds-league,#a78bfa);color:#fff;
                                        padding:.45rem .8rem;font-weight:600;font-size:.9rem;">
                                <?php echo $h($nombre); ?>
                            </div>
                            <table class="table table-sm mb-0">
                                <tbody>
                                <?php foreach ($miembros as $m): ?>
                                    <tr>
                                        <td style="width:1.6rem;" class="text-muted">
                                            <?php echo (int)$m['resultado_posicion']; ?>
                                        </td>
                                        <td>
                                            <?php echo $h($m['equipo_nombre']); ?>
                                            <?php if ((int)$m['resultado_bombo'] === 1): ?>
                                                <i class="fas fa-star text-warning ms-1"
                                                   title="Cabeza de serie" style="font-size:.7rem;"></i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
/* Copiar una semilla del historial al formulario. Es el gesto que hace
   verificable un sorteo: se pega y se vuelve a ejecutar. */
(function () {
    var campo = document.getElementById('semilla');

    document.querySelectorAll('.js-semilla').forEach(function (c) {
        c.addEventListener('click', function () {
            if (!campo) { return; }
            campo.value = c.getAttribute('data-semilla');
            campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            campo.focus();

            Swal.fire({
                icon: 'info',
                title: 'Semilla copiada',
                text: 'Al sortear con ella se obtiene el mismo reparto, siempre que los '
                    + 'equipos y las cabezas de serie sean los mismos.',
                timer: 3800,
                timerProgressBar: true
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
