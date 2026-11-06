<?php
/*
| Disponibilidad semanal de una instalación y lo que cuesta cada franja.
|
| Las franjas son las horas en que se puede reservar; fuera de ellas el
| sistema rechaza la reserva. Cada franja puede tener su propio valor por
| hora (la noche, el fin de semana); si no lo tiene, vale el de la
| instalación (Instalaciones) y lo sigue si éste cambia.
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

$inst     = $instSel > 0 ? $insArena->instalacion($instSel) : null;
$general  = $inst ? (float)$inst['instalacion_valorhora'] : 0.0;
$horarios = $instSel > 0 ? $insArena->horarios($instSel) : [];

/* Agrupado por día para leerlo como una semana, no como una lista plana. */
$porDia = array_fill_keys(array_keys($dias), []);
foreach ($horarios as $h) {
    $porDia[(int)$h['horario_dia']][] = $h;
}
$conValor = count(array_filter($horarios, static fn ($h) => $h['horario_valorhora'] !== null));

$puedeCrear    = puede_crear('horarioList');
$puedeEditar   = puede_editar('horarioList');
$puedeEliminar = puede_eliminar('horarioList');

$e     = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$hhmm  = static fn ($t): string => substr((string)$t, 0, 5);
$money = static fn ($v): string => '$' . number_format((float)$v, 2);

/* Lo que necesita el JS del cambio de valor en bloque: qué franjas hay. */
$franjasJs = array_map(static fn ($h) => [(int)$h['horario_dia'], substr($h['horario_desde'], 0, 5), substr($h['horario_hasta'], 0, 5)], $horarios);
$minHora = $horarios ? min(array_map(static fn ($h) => substr($h['horario_desde'], 0, 5), $horarios)) : '08:00';
$maxHora = $horarios ? max(array_map(static fn ($h) => substr($h['horario_hasta'], 0, 5), $horarios)) : '23:00';

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

    <div class="card ar-hor">
        <div class="card-header ar-hor__cabecera">
            <h3 class="card-title mb-0">Disponibilidad semanal</h3>

            <div class="ar-hor__herramientas">
                <form method="GET" action="<?php echo APP_URL; ?>horarioList/" class="d-flex align-items-center gap-2">
                    <label for="instalacion" class="mb-0 small text-muted">Instalación</label>
                    <select name="instalacion" id="instalacion" class="form-select form-select-sm"
                            onchange="this.form.submit()">
                        <?php foreach ($instalaciones as $i): ?>
                            <option value="<?php echo (int)$i['instalacion_id']; ?>"
                                <?php echo $instSel === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                                <?php echo $e($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="ar-hor__botones">
                    <?php if ($puedeEditar && $horarios): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalValor">
                            <i class="fas fa-tags me-1"></i> Valor por hora
                        </button>
                    <?php endif; ?>
                    <?php if ($puedeCrear): ?>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalFranja">
                            <i class="fas fa-plus me-1"></i> Añadir franja
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ar-hor__valor">
            <div>
                <span class="text-muted">Valor de la instalación:</span>
                <strong><?php echo $money($general); ?></strong> <span class="text-muted">por hora</span>
                <?php if (puede_editar('instalacionList')): ?>
                    <a href="<?php echo APP_URL; ?>instalacionForm/?id=<?php echo $instSel; ?>" class="ms-1 small">Cambiar</a>
                <?php endif; ?>
            </div>
            <div class="ar-hor__leyenda small">
                <span><span class="ar-hor__precio ar-hor__precio--general">$</span> el de la instalación</span>
                <span><span class="ar-hor__precio ar-hor__precio--propio">$</span> valor propio<?php echo $conValor ? ' (' . $conValor . ')' : ''; ?></span>
            </div>
        </div>

        <div class="card-body">
            <div class="row">
                <?php foreach ($dias as $num => $nombre): ?>
                    <div class="col-xl-3 col-lg-4 col-sm-6 mb-3">
                        <div class="ar-hor__dia">
                            <div class="ar-hor__dia-titulo">
                                <?php echo $nombre; ?>
                                <span class="text-muted fw-normal"><?php echo count($porDia[$num]); ?></span>
                            </div>

                            <div class="ar-hor__franjas">
                                <?php if (!$porDia[$num]): ?>
                                    <small class="text-muted">Cerrado</small>
                                <?php endif; ?>

                                <?php foreach ($porDia[$num] as $h):
                                    $propio = $h['horario_valorhora'] !== null;
                                    $valor  = $propio ? (float)$h['horario_valorhora'] : $general;
                                ?>
                                    <div class="ar-hor__franja">
                                        <span class="ar-hor__hora">
                                            <?php echo $hhmm($h['horario_desde']); ?>–<?php echo $hhmm($h['horario_hasta']); ?>
                                        </span>

                                        <span class="ar-hor__precio <?php echo $propio ? 'ar-hor__precio--propio' : 'ar-hor__precio--general'; ?>"
                                              title="<?php echo $propio ? 'Valor propio de esta franja' : 'Valor de la instalación'; ?>">
                                            <?php echo $money($valor); ?>
                                        </span>

                                        <span class="ar-hor__acciones">
                                            <?php if ($puedeEditar): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal" data-bs-target="#modalEditarFranja"
                                                        data-id="<?php echo (int)$h['horario_id']; ?>"
                                                        data-dia="<?php echo (int)$h['horario_dia']; ?>"
                                                        data-desde="<?php echo $hhmm($h['horario_desde']); ?>"
                                                        data-hasta="<?php echo $hhmm($h['horario_hasta']); ?>"
                                                        data-valor="<?php echo $propio ? number_format((float)$h['horario_valorhora'], 2, '.', '') : ''; ?>"
                                                        title="Editar" aria-label="Editar la franja <?php echo $hhmm($h['horario_desde']); ?> del <?php echo $e(mb_strtolower($nombre, 'UTF-8')); ?>">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($puedeEliminar): ?>
                                                <form class="FormularioAjax d-inline" method="POST"
                                                      action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                                      data-confirmar="Se retirará esa franja de disponibilidad.">
                                                    <input type="hidden" name="modulo_arena" value="eliminarHorario">
                                                    <input type="hidden" name="horario_id" value="<?php echo (int)$h['horario_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Quitar"
                                                            aria-label="Quitar la franja">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-footer text-muted small">
            Fuera de estas franjas no se puede reservar. Una reserva toma el valor de su franja; las ya
            hechas conservan el suyo. Para cierres puntuales —mantenimiento, eventos— use
            <a href="<?php echo APP_URL; ?>bloqueoList/">Mantenimiento</a>.
        </div>
    </div>

    <?php
    /* El campo de valor, igual en los tres modales. */
    $campoValor = static function (string $id, string $ayuda) use ($general, $money): string {
        return '<label for="' . $id . '" class="form-label">Valor por hora</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="decimal" class="form-control" id="' . $id . '" name="horario_valorhora"
                           placeholder="' . number_format($general, 2, '.', '') . '" autocomplete="off"
                           pattern="^\d{1,8}([.,]\d{1,2})?$" title="Un valor como 25 o 27.50">
                </div>
                <small class="text-muted d-block mt-1">' . $ayuda . '</small>';
    };
    ?>

    <?php if ($puedeCrear): ?>
        <!-- ---------- Alta de franja ---------- -->
        <div class="modal fade" id="modalFranja" tabindex="-1" aria-labelledby="modalFranjaTitulo">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form class="FormularioAjax ar-hor" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                        <input type="hidden" name="modulo_arena" value="guardarHorario">
                        <input type="hidden" name="horario_id" value="0">
                        <input type="hidden" name="horario_instalacionid" value="<?php echo $instSel; ?>">

                        <div class="modal-header">
                            <h5 class="modal-title" id="modalFranjaTitulo">Añadir franja de disponibilidad</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <fieldset class="mb-3 border-0 p-0">
                                <legend class="form-label fs-6 mb-2">Días <span class="text-danger">*</span></legend>
                                <div class="ar-hor__dias">
                                    <?php foreach ($dias as $num => $nombre): ?>
                                        <input type="checkbox" class="btn-check" name="horario_dia[]" id="alta_dia_<?php echo $num; ?>" value="<?php echo $num; ?>">
                                        <?php /* mb_substr y no substr: substr corta por bytes y
                                                 partía la «é» de Miércoles (dos bytes en UTF-8). */ ?>
                                        <label class="btn btn-outline-secondary" for="alta_dia_<?php echo $num; ?>" title="<?php echo $nombre; ?>">
                                            <?php echo mb_substr($nombre, 0, 3, 'UTF-8'); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">Se creará la misma franja en cada día marcado.</small>
                            </fieldset>

                            <div class="row g-2">
                                <div class="mb-3 col-6">
                                    <label for="horario_desde" class="form-label">Desde <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="horario_desde" name="horario_desde" required>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="horario_hasta" class="form-label">Hasta <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="horario_hasta" name="horario_hasta" required>
                                </div>
                            </div>

                            <div class="mb-0">
                                <?php echo $campoValor('alta_valor', 'Déjelo vacío para usar el de la instalación (' . $money($general) . ').'); ?>
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

    <?php if ($puedeEditar): ?>
        <!-- ---------- Editar una franja ---------- -->
        <div class="modal fade" id="modalEditarFranja" tabindex="-1" aria-labelledby="modalEditarFranjaTitulo">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form class="FormularioAjax ar-hor" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                        <input type="hidden" name="modulo_arena" value="guardarHorario">
                        <input type="hidden" name="horario_id" id="edit_id" value="0">
                        <input type="hidden" name="horario_instalacionid" value="<?php echo $instSel; ?>">

                        <div class="modal-header">
                            <h5 class="modal-title" id="modalEditarFranjaTitulo">Editar franja</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_dia" class="form-label">Día</label>
                                <select class="form-select" id="edit_dia" name="horario_diaunico">
                                    <?php foreach ($dias as $num => $nombre): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $nombre; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="mb-3 col-6">
                                    <label for="edit_desde" class="form-label">Desde</label>
                                    <input type="time" class="form-control" id="edit_desde" name="horario_desde" required>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="edit_hasta" class="form-label">Hasta</label>
                                    <input type="time" class="form-control" id="edit_hasta" name="horario_hasta" required>
                                </div>
                            </div>
                            <div class="mb-0">
                                <?php echo $campoValor('edit_valor', 'Vacío = el de la instalación (' . $money($general) . '), y lo sigue si cambia.'); ?>
                                <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="edit_general">
                                    Usar el de la instalación
                                </button>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ---------- Valor por hora de varias franjas ---------- -->
        <div class="modal fade" id="modalValor" tabindex="-1" aria-labelledby="modalValorTitulo">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form class="FormularioAjax ar-hor" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" id="formValor">
                        <input type="hidden" name="modulo_arena" value="guardarValorHorario">
                        <input type="hidden" name="horario_instalacionid" value="<?php echo $instSel; ?>">

                        <div class="modal-header">
                            <h5 class="modal-title" id="modalValorTitulo">Valor por hora</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <p class="small text-muted mb-3">
                                Cambia el valor de las franjas de los días marcados que caen dentro de esas horas.
                                Ej.: lunes a viernes de 18:00 a 23:00 a $35.
                            </p>

                            <fieldset class="mb-3 border-0 p-0">
                                <div class="d-flex justify-content-between align-items-baseline mb-2">
                                    <legend class="form-label fs-6 mb-0">Días</legend>
                                    <span class="ar-hor__atajos">
                                        <button type="button" class="btn btn-link btn-sm p-0" data-dias="1,2,3,4,5,6,7">Todos</button>
                                        <button type="button" class="btn btn-link btn-sm p-0" data-dias="1,2,3,4,5">Lun–vie</button>
                                        <button type="button" class="btn btn-link btn-sm p-0" data-dias="6,7">Sáb–dom</button>
                                    </span>
                                </div>
                                <div class="ar-hor__dias">
                                    <?php foreach ($dias as $num => $nombre): ?>
                                        <input type="checkbox" class="btn-check" name="horario_dia[]" id="val_dia_<?php echo $num; ?>" value="<?php echo $num; ?>" checked>
                                        <label class="btn btn-outline-secondary" for="val_dia_<?php echo $num; ?>" title="<?php echo $nombre; ?>">
                                            <?php echo mb_substr($nombre, 0, 3, 'UTF-8'); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                            <div class="row g-2">
                                <div class="mb-3 col-6">
                                    <label for="val_desde" class="form-label">Desde</label>
                                    <input type="time" class="form-control" id="val_desde" name="horario_desde" value="<?php echo $e($minHora); ?>" required>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="val_hasta" class="form-label">Hasta</label>
                                    <input type="time" class="form-control" id="val_hasta" name="horario_hasta" value="<?php echo $e($maxHora); ?>" required>
                                </div>
                            </div>

                            <fieldset class="border-0 p-0 mb-2">
                                <legend class="form-label fs-6 mb-2">¿Qué valor?</legend>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="horario_usar" id="val_usar_otro" value="valor" checked>
                                    <label class="form-check-label" for="val_usar_otro">Otro valor</label>
                                </div>
                                <div class="ms-4 mb-2" id="val_campo">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" inputmode="decimal" class="form-control" id="val_valor" name="horario_valorhora"
                                               placeholder="<?php echo number_format($general, 2, '.', ''); ?>" autocomplete="off" required
                                               pattern="^\d{1,8}([.,]\d{1,2})?$" title="Un valor como 25 o 27.50">
                                        <span class="input-group-text">por hora</span>
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="horario_usar" id="val_usar_general" value="general">
                                    <label class="form-check-label" for="val_usar_general">
                                        El de la instalación (<?php echo $money($general); ?>)
                                    </label>
                                </div>
                            </fieldset>

                            <div id="val_resumen" class="ar-hor__resumen" aria-live="polite"></div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="val_guardar"><i class="fas fa-save me-1"></i> Aplicar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var $ = function (id) { return document.getElementById(id); };

            /* Editar una franja: el botón trae sus datos. */
            var mEdit = $('modalEditarFranja');
            mEdit.addEventListener('show.bs.modal', function (ev) {
                var d = (ev.relatedTarget && ev.relatedTarget.dataset) || {};
                $('edit_id').value = d.id || '0';
                $('edit_dia').value = d.dia || '1';
                $('edit_desde').value = d.desde || '';
                $('edit_hasta').value = d.hasta || '';
                $('edit_valor').value = d.valor || '';
            });
            $('edit_general').addEventListener('click', function () { $('edit_valor').value = ''; $('edit_valor').focus(); });

            /* Valor en bloque: cuenta en vivo las franjas a las que llega. */
            var FRANJAS = <?php echo json_encode($franjasJs); ?>;
            var form = $('formValor'), resumen = $('val_resumen'), campo = $('val_valor');
            var dias = form.querySelectorAll('input[name="horario_dia[]"]');

            function actualizar() {
                var general = $('val_usar_general').checked;
                campo.disabled = general;
                $('val_campo').classList.toggle('opacity-50', general);
                var sel = Array.prototype.filter.call(dias, function (c) { return c.checked; }).map(function (c) { return +c.value; });
                var a = $('val_desde').value, b = $('val_hasta').value;
                if (!a || !b || b <= a) {
                    resumen.className = 'ar-hor__resumen text-danger';
                    resumen.textContent = 'La hora final debe ser posterior a la inicial.';
                    return;
                }
                var n = FRANJAS.filter(function (f) { return sel.indexOf(f[0]) >= 0 && f[1] >= a && f[2] <= b; }).length;
                if (!sel.length || !n) {
                    resumen.className = 'ar-hor__resumen text-warning';
                    resumen.textContent = !sel.length ? 'Marque al menos un día.' : 'Ninguna franja de esos días cae completa entre ' + a + ' y ' + b + '.';
                    return;
                }
                var v = campo.value.trim().replace(',', '.');
                var que = general ? 'volverán al valor de la instalación'
                                  : (v && !isNaN(v) ? 'quedarán a $' + (+v).toFixed(2) + ' por hora' : 'cambiarán de valor');
                resumen.className = 'ar-hor__resumen text-body';
                resumen.innerHTML = '<i class="fas fa-tags me-1"></i><strong>' + n + (n === 1 ? ' franja' : ' franjas') + '</strong> ' + que + '.';
            }
            form.addEventListener('input', actualizar);
            form.addEventListener('change', actualizar);
            form.addEventListener('click', function (ev) {
                var b = ev.target.closest('button[data-dias]');
                if (!b) { return; }
                var s = b.dataset.dias.split(',');
                dias.forEach(function (c) { c.checked = s.indexOf(c.value) >= 0; });
                actualizar();
            });
            $('modalValor').addEventListener('show.bs.modal', actualizar);
        })();
        </script>
    <?php endif; ?>

<?php endif; ?>

<style>
.ar-hor__cabecera { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
.ar-hor__cabecera::before, .ar-hor__cabecera::after { content: none; display: none; }
.ar-hor__herramientas { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.ar-hor__herramientas .form-select { width: auto; max-width: 100%; }
.ar-hor__botones { display: flex; flex-wrap: wrap; gap: 8px; }
.ar-hor__valor {
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 6px 16px;
    padding: .6rem 1rem; border-bottom: 1px solid var(--bs-border-color); background: var(--bs-tertiary-bg); font-size: .9rem;
}
.ar-hor__leyenda { display: flex; flex-wrap: wrap; gap: 12px; color: var(--bs-secondary-color); }
.ar-hor__leyenda .ar-hor__precio { padding: 0 .35rem; }

.ar-hor__dia { border: 1px solid var(--core-borde, var(--bs-border-color)); border-radius: var(--ds-radius-md, .5rem); background: var(--bs-body-bg); height: 100%; }
.ar-hor__dia-titulo { display: flex; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid var(--core-borde, var(--bs-border-color)); font-weight: 700; font-size: .85rem; }
.ar-hor__franjas { padding: 8px 10px; }
.ar-hor__franja { display: flex; align-items: center; gap: 8px; padding: 3px 4px; border-radius: .35rem; }
.ar-hor__franja:hover { background: var(--bs-tertiary-bg); }
.ar-hor__hora { font-size: .86rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
.ar-hor__precio {
    margin-left: auto; padding: .05rem .4rem; border-radius: .3rem; font-size: .8rem; font-weight: 600;
    font-variant-numeric: tabular-nums; white-space: nowrap;
}
.ar-hor__precio--general { color: var(--bs-secondary-color); }
.ar-hor__precio--propio  { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
.ar-hor__acciones { display: inline-flex; gap: 4px; }
.ar-hor__acciones form { margin: 0; }
.ar-hor__acciones .btn { padding: .1rem .4rem; line-height: 1.4; }
.ar-hor__acciones .btn-outline-secondary { --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }

.ar-hor legend { padding: 0; float: none; width: auto; }
.ar-hor__dias { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .35rem; margin-bottom: .25rem; }
.ar-hor__dias .btn {
    padding: .4rem 0; font-size: .82rem; font-weight: 600;
    --bs-btn-color: var(--bs-body-color); --bs-btn-hover-color: var(--bs-body-color); --bs-btn-hover-bg: var(--bs-tertiary-bg);
    background: var(--bs-body-bg); border-color: var(--bs-border-color);
}
.ar-hor__dias .btn-check:checked + .btn { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
.ar-hor__atajos { display: flex; gap: .75rem; }
.ar-hor__atajos .btn { font-size: .8rem; text-decoration: none; }
.ar-hor__resumen { min-height: 1.25rem; font-size: .875rem; }

@media (max-width: 575.98px) {
    .ar-hor__herramientas, .ar-hor__herramientas form { width: 100%; }
    .ar-hor__herramientas .form-select { flex: 1 1 auto; }
    .ar-hor__botones { width: 100%; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); }
    .ar-hor__dias .btn { font-size: .74rem; }
    /* Con el dedo: botones de 36 px y la fila un poco más alta. */
    .ar-hor__acciones .btn { min-width: 36px; min-height: 36px; }
    .ar-hor__franja { padding: 4px; }
}
</style>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
