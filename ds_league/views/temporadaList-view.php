<?php
/*
| Temporadas: el contenedor temporal del que cuelga todo lo demás.
|
| Listado y formulario en la misma pantalla porque una temporada tiene
| tres campos: mandar a otra vista para eso sería un viaje de ida y vuelta
| por un nombre y dos fechas.
*/

use league\controllers\competenciaController;

$insLeague = new competenciaController();

$tituloVista = 'Temporadas';
$vistaActual = 'temporadaList';

$temporadas = $insLeague->temporadas();

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row">
    <!-- ==================== Listado ==================== -->
    <div class="col-lg-7 mb-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-alt me-2"></i>Temporadas</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Temporada</th>
                                <th>Desde</th>
                                <th>Hasta</th>
                                <th class="text-end">Torneos</th>
                                <th class="ds-tabla-acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$temporadas): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                Todavía no hay temporadas. Cree la primera en el formulario de al lado.
                            </td></tr>
                        <?php else: foreach ($temporadas as $t): ?>
                            <tr>
                                <td><strong><?php echo $h($t['temporada_nombre']); ?></strong></td>
                                <td><?php echo $h($t['temporada_desde']); ?></td>
                                <td><?php echo $h($t['temporada_hasta']); ?></td>
                                <td class="text-end"><?php echo (int)$t['torneos']; ?></td>
                                <td class="ds-tabla-acciones">
                                    <a href="<?php echo APP_URL; ?>torneoList/<?php echo (int)$t['temporada_id']; ?>/"
                                       class="btn btn-sm btn-ver" title="Ver torneos">
                                        <i class="fas fa-trophy"></i>
                                    </a>
                                    <?php if (puede_editar('temporadaList')): ?>
                                    <button type="button" class="btn btn-sm btn-actualizar js-editar"
                                            title="Editar"
                                            data-fila='<?php echo $h(json_encode([
                                                'temporada_id'     => (int)$t['temporada_id'],
                                                'temporada_nombre' => $t['temporada_nombre'],
                                                'temporada_desde'  => $t['temporada_desde'],
                                                'temporada_hasta'  => $t['temporada_hasta'],
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

    <!-- ==================== Alta y edición ==================== -->
    <div class="col-lg-5 mb-3">
        <?php if (puede_crear('temporadaList') || puede_editar('temporadaList')): ?>
        <form class="FormularioAjax" method="POST" autocomplete="off"
              action="<?php echo APP_URL; ?>ajax/leagueAjax.php" id="formFila">
            <input type="hidden" name="modulo_league" value="guardarTemporada">
            <input type="hidden" name="temporada_id" id="temporada_id" value="0">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="tituloForm">
                        <i class="fas fa-plus me-2"></i>Nueva temporada
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="temporada_nombre">Nombre</label>
                        <input type="text" name="temporada_nombre" id="temporada_nombre"
                               class="form-control" maxlength="80" placeholder="Temporada 2026" required>
                    </div>
                    <div class="row g-2">
                        <div class="mb-3 col-6">
                            <label for="temporada_desde">Desde</label>
                            <input type="date" name="temporada_desde" id="temporada_desde"
                                   class="form-control" required>
                        </div>
                        <div class="mb-3 col-6 mb-0">
                            <label for="temporada_hasta">Hasta</label>
                            <input type="date" name="temporada_hasta" id="temporada_hasta"
                                   class="form-control" required>
                        </div>
                    </div>
                </div>
                <?php echo ds_acciones_form('', ['limpiar' => true]); ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . "/inc/editor-fila.php"; ?>
<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
