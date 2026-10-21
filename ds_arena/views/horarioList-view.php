<?php
/*
| Disponibilidad semanal de una instalación.
| Son las franjas en las que se puede reservar; fuera de ellas el sistema
| rechaza la reserva.
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Horarios';
$vistaActual = 'horarioList';

$instalaciones = $insArena->instalaciones();
$dias          = $insArena->dias();

$instSel = (int)($_GET['instalacion'] ?? 0);
if ($instSel === 0 && $instalaciones) {
    $instSel = (int)$instalaciones[0]['instalacion_id'];
}

$horarios = $instSel > 0 ? $insArena->horarios($instSel) : [];

/* Agrupado por día para leerlo como una semana, no como una lista plana. */
$porDia = array_fill_keys(array_keys($dias), []);
foreach ($horarios as $h) {
    $porDia[(int)$h['horario_dia']][] = $h;
}

$puedeCrear    = puede_crear('horarioList');
$puedeEliminar = puede_eliminar('horarioList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if (!$instalaciones): ?>

    <div class="aviso-superadmin">
        <i class="fas fa-info-circle fa-lg mt-1"></i>
        <div>
            <strong>Todavía no hay instalaciones registradas.</strong><br>
            Cree una cancha o residencia antes de definir su disponibilidad.
            <a href="<?php echo APP_URL; ?>instalacionList/" class="ms-2">Ir a Instalaciones →</a>
        </div>
    </div>

<?php else: ?>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
            <h3 class="card-title mb-0">Disponibilidad semanal</h3>

            <div class="d-flex align-items-center" style="gap:12px;">
                <form method="GET" action="<?php echo APP_URL; ?>horarioList/" class="d-flex flex-wrap align-items-center gap-2">
                    <label for="instalacion" class="me-2 mb-0">Instalación</label>
                    <select name="instalacion" id="instalacion" class="form-select form-select-sm w-auto"
                            onchange="this.form.submit()">
                        <?php foreach ($instalaciones as $i): ?>
                            <option value="<?php echo (int)$i['instalacion_id']; ?>"
                                <?php echo $instSel === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($puedeCrear): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalFranja">
                        <i class="fas fa-plus me-1"></i> Añadir franja
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <div class="row">
                <?php foreach ($dias as $num => $nombre): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                        <div style="border:1px solid var(--core-borde);border-radius:var(--ds-radius-md);
                                    background:var(--bs-body-bg);height:100%;">
                            <div style="padding:10px 14px;border-bottom:1px solid var(--core-borde);
                                        font-weight:700;font-size:.85rem;">
                                <?php echo $nombre; ?>
                                <span class="float-end text-muted" style="font-weight:400;">
                                    <?php echo count($porDia[$num]); ?>
                                </span>
                            </div>

                            <div style="padding:10px 14px;">
                                <?php if (!$porDia[$num]): ?>
                                    <small class="text-muted">Cerrado</small>
                                <?php endif; ?>

                                <?php foreach ($porDia[$num] as $h): ?>
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span style="font-size:.86rem;">
                                            <i class="far fa-clock text-muted me-1"></i>
                                            <?php echo substr($h['horario_desde'], 0, 5); ?>–<?php echo substr($h['horario_hasta'], 0, 5); ?>
                                        </span>

                                        <?php if ($puedeEliminar): ?>
                                            <form class="FormularioAjax d-inline" method="POST"
                                                  action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                                  data-confirmar="Se retirará esa franja de disponibilidad.">
                                                <input type="hidden" name="modulo_arena" value="eliminarHorario">
                                                <input type="hidden" name="horario_id" value="<?php echo (int)$h['horario_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Quitar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-footer text-muted small">
            Fuera de estas franjas no se puede reservar. Para cierres puntuales
            —mantenimiento, eventos— use <a href="<?php echo APP_URL; ?>bloqueoList/">Mantenimiento</a>.
        </div>
    </div>

    <?php if ($puedeCrear): ?>
        <!-- ---------- Alta de franja ---------- -->
        <div class="modal fade" id="modalFranja" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                        <input type="hidden" name="modulo_arena" value="guardarHorario">
                        <input type="hidden" name="horario_id" value="0">
                        <input type="hidden" name="horario_instalacionid" value="<?php echo $instSel; ?>">

                        <div class="modal-header">
                            <h5 class="modal-title">Añadir franja de disponibilidad</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Días <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap" style="gap:8px;">
                                    <?php foreach ($dias as $num => $nombre): ?>
                                        <label class="d-flex align-items-center mb-0 px-2 py-1"
                                               style="gap:6px;border:1px solid var(--core-borde);
                                                      border-radius:var(--ds-radius-sm);cursor:pointer;">
                                            <input type="checkbox" name="horario_dia[]" value="<?php echo $num; ?>">
                                            <?php /* mb_substr y no substr: substr corta por bytes y
                                                     partía la «é» de Miércoles (dos bytes en UTF-8),
                                                     dejando «Mi» más medio carácter: «Mi�». */ ?>
                                            <span style="font-size:.84rem;"><?php echo mb_substr($nombre, 0, 3, 'UTF-8'); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">Se creará la misma franja en cada día marcado.</small>
                            </div>

                            <div class="row g-2">
                                <div class="mb-3 col-6">
                                    <label for="horario_desde">Desde <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="horario_desde" name="horario_desde" required>
                                </div>
                                <div class="mb-3 col-6 mb-0">
                                    <label for="horario_hasta">Hasta <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="horario_hasta" name="horario_hasta" required>
                                </div>
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
