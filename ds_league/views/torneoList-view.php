<?php
/*
| Torneos de una temporada.
|
| El id de la temporada viene en la URL. Si no viene, se listan todos los
| torneos: la pantalla sirve igual como índice general que como detalle de
| una temporada, y así el menú puede apuntar aquí sin parámetros.
*/

use league\controllers\competenciaController;

$insLeague = new competenciaController();

$temporadaId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);

$temporadas = $insLeague->temporadas();
$torneos    = $insLeague->torneos($temporadaId);

$temporada = [];
foreach ($temporadas as $t) {
    if ((int)$t['temporada_id'] === $temporadaId) { $temporada = $t; break; }
}

$tituloVista = $temporada ? 'Torneos · ' . $temporada['temporada_nombre'] : 'Torneos';
$vistaActual = 'torneoList';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if (!$temporadas): ?>
<div class="callout callout-warning">
    <h6 class="mb-1"><i class="fas fa-exclamation-circle me-2"></i>No hay temporadas</h6>
    <p class="mb-0 text-muted">
        Un torneo pertenece a una temporada.
        <a href="<?php echo APP_URL; ?>temporadaList/">Cree una primero</a>.
    </p>
</div>
<?php else: ?>

<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-trophy me-2"></i>Torneos</h3>
                <?php if ($temporadaId > 0): ?>
                    <a href="<?php echo APP_URL; ?>torneoList/" class="ds-link">Ver todos →</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Torneo</th>
                                <th>Temporada</th>
                                <th>Deporte</th>
                                <th class="text-end">Categorías</th>
                                <th>Portal</th>
                                <th class="ds-tabla-acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$torneos): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                No hay torneos<?php echo $temporadaId > 0 ? ' en esta temporada' : ''; ?>.
                            </td></tr>
                        <?php else: foreach ($torneos as $o): ?>
                            <tr>
                                <td><strong><?php echo $h($o['torneo_nombre']); ?></strong></td>
                                <td class="text-muted"><?php echo $h($o['temporada_nombre']); ?></td>
                                <td class="text-muted"><?php echo $h($o['torneo_deporte']); ?></td>
                                <td class="text-end"><?php echo (int)$o['categorias']; ?></td>
                                <td>
                                    <?php $pub = ($o['torneo_publico'] ?? 'N') === 'S'; ?>
                                    <?php if (puede_eliminar('torneoList')): ?>
                                        <button type="button" data-id="<?php echo (int)$o['torneo_id']; ?>"
                                                class="btn btn-sm btn-<?php echo $pub ? 'success' : 'outline-secondary'; ?> js-publicar"
                                                data-nombre="<?php echo $h($o['torneo_nombre']); ?>"
                                                data-pub="<?php echo $pub ? 'S' : 'N'; ?>">
                                            <i class="fas fa-<?php echo $pub ? 'globe' : 'eye-slash'; ?> me-1"></i>
                                            <?php echo $pub ? 'Publicado' : 'Privado'; ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge text-bg-<?php echo $pub ? 'success' : 'secondary'; ?>">
                                            <?php echo $pub ? 'Publicado' : 'Privado'; ?></span>
                                    <?php endif; ?>
                                    <?php if ($pub && !empty($o['torneo_slug'])): ?>
                                        <br><a href="<?php echo APP_URL; ?>publico/t/<?php echo $h($o['torneo_slug']); ?>/"
                                               target="_blank" rel="noopener" style="font-size:.78rem;">ver en el portal ↗</a>
                                    <?php endif; ?>
                                </td>
                                <td class="ds-tabla-acciones">
                                    <a href="<?php echo APP_URL; ?>categoriaList/<?php echo (int)$o['torneo_id']; ?>/"
                                       class="btn btn-sm btn-ver" title="Ver categorías">
                                        <i class="fas fa-layer-group"></i>
                                    </a>
                                    <?php if (puede_editar('torneoList')): ?>
                                    <button type="button" class="btn btn-sm btn-actualizar js-editar" title="Editar"
                                            data-fila='<?php echo $h(json_encode([
                                                'torneo_id'          => (int)$o['torneo_id'],
                                                'torneo_nombre'      => $o['torneo_nombre'],
                                                'torneo_temporadaid' => (int)$o['torneo_temporadaid'],
                                                'torneo_deporte'     => $o['torneo_deporte'],
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
        <?php if (puede_crear('torneoList') || puede_editar('torneoList')): ?>
        <form class="FormularioAjax" method="POST" autocomplete="off"
              action="<?php echo APP_URL; ?>ajax/leagueAjax.php" id="formFila">
            <input type="hidden" name="modulo_league" value="guardarTorneo">
            <input type="hidden" name="torneo_id" id="torneo_id" value="0">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="tituloForm">
                        <i class="fas fa-plus me-2"></i>Nuevo torneo
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="torneo_nombre">Nombre</label>
                        <input type="text" name="torneo_nombre" id="torneo_nombre"
                               class="form-control" maxlength="120" required>
                    </div>
                    <div class="mb-3">
                        <label for="torneo_temporadaid">Temporada</label>
                        <select name="torneo_temporadaid" id="torneo_temporadaid" class="form-select" required>
                            <?php foreach ($temporadas as $t): ?>
                                <option value="<?php echo (int)$t['temporada_id']; ?>"
                                    <?php echo (int)$t['temporada_id'] === $temporadaId ? 'selected' : ''; ?>>
                                    <?php echo $h($t['temporada_nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 mb-0">
                        <label for="torneo_deporte">Deporte</label>
                        <select name="torneo_deporte" id="torneo_deporte" class="form-select">
                            <option value="baloncesto">Baloncesto</option>
                        </select>
                        <small class="form-text text-muted">
                            El campo existe desde el principio para que otro deporte sea
                            configuración y no un cambio de esquema.
                        </small>
                    </div>
                </div>
                <?php echo ds_acciones_form(APP_URL . 'temporadaList/', ['limpiar' => true]); ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<script>
/* Publicar abre al mundo datos que incluyen nombres de menores, así que se
   confirma explícitamente y el aviso dice qué se publica y qué no. Un
   interruptor silencioso convertiría una decisión de privacidad en un clic
   distraído. */
(function () {
    document.querySelectorAll('.js-publicar').forEach(function (b) {
        b.addEventListener('click', function () {
            var pub = b.getAttribute('data-pub') === 'S';
            var nom = b.getAttribute('data-nombre');

            Swal.fire({
                icon:  pub ? 'question' : 'warning',
                title: pub ? '¿Retirar del portal?' : '¿Publicar en el portal?',
                html:  pub
                    ? '«' + nom + '» dejará de ser visible para el público.'
                    : '<div style="text-align:left">«' + nom + '» pasará a ser visible para '
                      + 'cualquiera, sin necesidad de iniciar sesión.<br><br>'
                      + '<b>Se publica:</b> nombres, dorsales, equipos, resultados y estadísticas.'
                      + '<br><b>No se publica:</b> documentos de identidad ni fechas de nacimiento. '
                      + 'Las fotografías, sólo con autorización registrada.</div>',
                showCancelButton:  true,
                confirmButtonText: pub ? 'Retirar' : 'Publicar',
                cancelButtonText:  'Cancelar',
                confirmButtonColor: pub ? '#6c757d' : '#28a745'
            }).then(function (r) {
                if (!r.isConfirmed) { return; }

                var fd = new FormData();
                fd.append('modulo_league', 'publicarTorneo');
                fd.append('torneo_id', b.getAttribute('data-id'));
                fd.append('publicar', pub ? 'N' : 'S');

                fetch('<?php echo APP_URL; ?>ajax/leagueAjax.php', { method: 'POST', body: fd })
                    .then(function (x) { return x.json(); })
                    .then(function (j) {
                        Swal.fire({ icon: j.icono, title: j.titulo, text: j.texto })
                            .then(function () { if (j.tipo === 'recargar') { location.reload(); } });
                    })
                    .catch(function () {
                        Swal.fire({ icon: 'error', title: 'Sin respuesta',
                                    text: 'No se pudo contactar con el servidor.' });
                    });
            });
        });
    });
})();
</script>
<?php require_once __DIR__ . "/inc/editor-fila.php"; ?>
<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
