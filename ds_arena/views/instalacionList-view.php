<?php
/* Canchas y residencias disponibles para alquiler. */

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Instalaciones';
$vistaActual = 'instalacionList';

$sedes = $insArena->sedesAlquiler();

$sedeSel  = (int)($_GET['sede'] ?? 0);
$claseSel = (string)($_GET['clase'] ?? '');
if (!in_array($claseSel, ['C', 'R'], true)) $claseSel = '';

$instalaciones = $insArena->instalaciones($sedeSel, $claseSel);

$puedeCrear    = puede_crear('instalacionList');
$puedeEditar   = puede_editar('instalacionList');
$puedeEliminar = puede_eliminar('instalacionList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if (!$sedes): ?>
    <div class="aviso-superadmin mb-3">
        <i class="fas fa-info-circle fa-lg mt-1"></i>
        <div>
            <strong>Ninguna sede está marcada como sede de alquiler.</strong><br>
            Arena sólo administra las sedes cuyo tipo sea <em>Alquiler</em> o
            <em>Formativa y Alquiler</em>. Ajuste el campo en el mantenimiento de sedes
            del módulo Fútbol antes de registrar instalaciones.
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
        <h3 class="card-title mb-0">
            <?php echo count($instalaciones); ?>
            instalaci<?php echo count($instalaciones) === 1 ? 'ón' : 'ones'; ?>
        </h3>

        <div class="d-flex align-items-center" style="gap:12px;">
            <form method="GET" action="<?php echo APP_URL; ?>instalacionList/" class="d-flex flex-wrap align-items-center gap-2">
                <select name="sede" class="form-select form-select-sm me-2 w-auto" onchange="this.form.submit()">
                    <option value="0">Todas las sedes</option>
                    <?php foreach ($sedes as $s): ?>
                        <option value="<?php echo (int)$s['sede_id']; ?>" <?php echo $sedeSel === (int)$s['sede_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="clase" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    <option value="">Canchas y residencias</option>
                    <option value="C" <?php echo $claseSel === 'C' ? 'selected' : ''; ?>>Sólo canchas</option>
                    <option value="R" <?php echo $claseSel === 'R' ? 'selected' : ''; ?>>Sólo residencias</option>
                </select>
            </form>

            <?php if ($puedeCrear && $sedes): ?>
                <a href="<?php echo APP_URL; ?>instalacionForm/" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Nueva instalación
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Instalación</th>
                        <th>Sede</th>
                        <th>Tipo</th>
                        <th class="text-end">Valor hora</th>
                        <th>Disponibilidad</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$instalaciones): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">
                        Todavía no hay instalaciones registradas.
                    </td></tr>
                <?php endif; ?>

                <?php foreach ($instalaciones as $i):
                    $esCancha = $i['instalacion_clase'] === 'C';
                ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($i['instalacion_codigo'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td>
                            <strong><?php echo htmlspecialchars($i['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($i['instalacion_detalle']): ?>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($i['instalacion_detalle'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)$i['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($esCancha): ?>
                                <span class="badge-modulo badge-modulo--arena">
                                    <i class="fas fa-basketball-ball"></i>
                                    Cancha <?php echo $i['instalacion_cubierta'] === 'S' ? 'cubierta' : 'descubierta'; ?>
                                </span>
                                <?php if ($i['piso_nombre']): ?>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($i['piso_nombre'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge-modulo badge-modulo--league"><i class="fas fa-bed"></i> Residencia</span>
                                <?php if ($i['instalacion_capacidad']): ?>
                                    <small class="d-block text-muted"><?php echo (int)$i['instalacion_capacidad']; ?> plazas</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <strong>$<?php echo number_format((float)$i['instalacion_valorhora'], 2); ?></strong>
                            <small class="d-block text-muted">por hora</small>
                        </td>
                        <td>
                            <?php if ((int)$i['franjas'] > 0): ?>
                                <span class="text-success"><i class="fas fa-check-circle"></i> <?php echo (int)$i['franjas']; ?> franja(s)</span>
                            <?php else: ?>
                                <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> sin horario</span>
                            <?php endif; ?>
                            <?php if ((int)$i['reservas'] > 0): ?>
                                <small class="d-block text-muted"><?php echo (int)$i['reservas']; ?> reserva(s) vigente(s)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?php echo $i['instalacion_estado'] === 'A' ? 'success' : 'secondary'; ?>">
                                <?php echo $i['instalacion_estado'] === 'A' ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </td>
                        <td class="ds-tabla-acciones">
                            <?php if ($puedeEditar): ?>
                                <a href="<?php echo APP_URL; ?>instalacionForm/?id=<?php echo (int)$i['instalacion_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>

                            <?php if ($puedeEliminar): ?>
                                <form class="FormularioAjax d-inline" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                      data-confirmar="La instalación quedará dada de baja.">
                                    <input type="hidden" name="modulo_arena" value="eliminarInstalacion">
                                    <input type="hidden" name="instalacion_id" value="<?php echo (int)$i['instalacion_id']; ?>">
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

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
