<?php
/*
| Bloqueos: periodos concretos en los que una instalación no se alquila,
| aunque caigan dentro de su horario habitual (mantenimiento, eventos).
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Mantenimiento';
$vistaActual = 'bloqueoList';

$instalaciones = $insArena->instalaciones();

$instSel   = (int)($_GET['instalacion'] ?? 0);
$historico = isset($_GET['historico']);

$bloqueos = $insArena->bloqueos($instSel, !$historico);

$tipos = ['M' => 'Mantenimiento', 'E' => 'Evento propio', 'O' => 'Otro'];
$color = ['M' => 'warning', 'E' => 'info', 'O' => 'secondary'];

$puedeCrear    = puede_crear('bloqueoList');
$puedeEliminar = puede_eliminar('bloqueoList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if (!$instalaciones): ?>

    <div class="aviso-superadmin">
        <i class="fas fa-info-circle fa-lg mt-1"></i>
        <div>
            <strong>Todavía no hay instalaciones registradas.</strong><br>
            <a href="<?php echo APP_URL; ?>instalacionList/">Ir a Instalaciones →</a>
        </div>
    </div>

<?php else: ?>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
            <h3 class="card-title mb-0">
                <?php echo count($bloqueos); ?> bloqueo<?php echo count($bloqueos) === 1 ? '' : 's'; ?>
                <?php echo $historico ? '(incluye pasados)' : 'vigente(s)'; ?>
            </h3>

            <?php /* flex-wrap: sin el, este bloque no rompia linea y sobresalia
                     2 px del viewport en telefono. La cabecera que lo contiene
                     si envuelve; este hijo se habia quedado sin ello. */ ?>
            <div class="d-flex flex-wrap align-items-center" style="gap:12px;">
                <form method="GET" action="<?php echo APP_URL; ?>bloqueoList/" class="d-flex flex-wrap align-items-center gap-2">
                    <select name="instalacion" class="form-select form-select-sm me-2 w-auto" onchange="this.form.submit()">
                        <option value="0">Todas las instalaciones</option>
                        <?php foreach ($instalaciones as $i): ?>
                            <option value="<?php echo (int)$i['instalacion_id']; ?>"
                                <?php echo $instSel === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mb-0 small">
                        <input type="checkbox" name="historico" value="1" <?php echo $historico ? 'checked' : ''; ?>
                               onchange="this.form.submit()"> Ver pasados
                    </label>
                </form>

                <?php if ($puedeCrear): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBloqueo">
                        <i class="fas fa-plus me-1"></i> Nuevo bloqueo
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Instalación</th>
                            <th>Tipo</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th>Motivo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$bloqueos): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">
                            No hay bloqueos registrados.
                        </td></tr>
                    <?php endif; ?>

                    <?php foreach ($bloqueos as $b): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($b['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars((string)$b['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?></small>
                            </td>
                            <td>
                                <span class="badge text-bg-<?php echo $color[$b['bloqueo_tipo']] ?? 'secondary'; ?>">
                                    <?php echo $tipos[$b['bloqueo_tipo']] ?? $b['bloqueo_tipo']; ?>
                                </span>
                            </td>
                            <td><?php echo $b['bloqueo_inicio']; ?></td>
                            <td><?php echo $b['bloqueo_fin']; ?></td>
                            <td><?php echo htmlspecialchars((string)$b['bloqueo_motivo'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end">
                                <?php if ($puedeEliminar): ?>
                                    <form class="FormularioAjax d-inline" method="POST"
                                          action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                          data-confirmar="La instalación volverá a estar disponible en ese periodo.">
                                        <input type="hidden" name="modulo_arena" value="eliminarBloqueo">
                                        <input type="hidden" name="bloqueo_id" value="<?php echo (int)$b['bloqueo_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($puedeCrear): ?>
        <div class="modal fade" id="modalBloqueo" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                        <input type="hidden" name="modulo_arena" value="guardarBloqueo">
                        <input type="hidden" name="bloqueo_id" value="0">

                        <div class="modal-header">
                            <h5 class="modal-title">Nuevo bloqueo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="bloqueo_instalacionid">Instalación <span class="text-danger">*</span></label>
                                <select class="form-select w-auto" id="bloqueo_instalacionid" name="bloqueo_instalacionid" required>
                                    <option value="">Seleccione…</option>
                                    <?php foreach ($instalaciones as $i): ?>
                                        <option value="<?php echo (int)$i['instalacion_id']; ?>"
                                            <?php echo $instSel === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="bloqueo_tipo">Tipo</label>
                                <select class="form-select w-auto" id="bloqueo_tipo" name="bloqueo_tipo">
                                    <?php foreach ($tipos as $k => $v): ?>
                                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-2">
                                <div class="mb-3 col-6">
                                    <label for="bloqueo_inicio">Desde <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="bloqueo_inicio" name="bloqueo_inicio" required>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="bloqueo_fin">Hasta <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="bloqueo_fin" name="bloqueo_fin" required>
                                </div>
                            </div>

                            <div class="mb-3 mb-0">
                                <label for="bloqueo_motivo">Motivo</label>
                                <input type="text" class="form-control" id="bloqueo_motivo" name="bloqueo_motivo" maxlength="150">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
