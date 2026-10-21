<?php
/*
| Sedes del ecosistema.
| Core las administra; Basketball y Arena sólo las consumen. El tipo de
| sede decide qué módulos pueden operar sobre ella.
*/

use admin\controllers\coreController;

$insCore = new coreController();

$tituloVista = 'Sedes';
$vistaActual = 'sedeList';

$sedes = $insCore->sedes();
$tipos = $insCore->valoresPorNombre('sede_tipoingreso');

$puedeCrear    = puede_crear('sedeList');
$puedeEditar   = puede_editar('sedeList');
$puedeEliminar = puede_eliminar('sedeList');

/* Color del distintivo según lo que la sede permite. */
$claseTipo = ['STF' => 'badge-modulo--basketball',
              'STA' => 'badge-modulo--arena',
              'STM' => 'badge-modulo--league'];

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><?php echo count($sedes); ?> sede<?php echo count($sedes) === 1 ? '' : 's'; ?></h3>
        <?php if ($puedeCrear): ?>
            <a href="<?php echo APP_URL; ?>sedeForm/" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Nueva sede
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Sede</th>
                        <th>Organización</th>
                        <th>Tipo</th>
                        <th class="text-end">Inscripción</th>
                        <th class="text-end">Pensión</th>
                        <th>Uso</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$sedes): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay sedes registradas.</td></tr>
                <?php endif; ?>

                <?php foreach ($sedes as $s): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($s['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($s['sede_direccion']): ?>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($s['sede_direccion'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo htmlspecialchars((string)$s['escuela_nombre'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></small></td>
                        <td>
                            <span class="badge-modulo <?php echo $claseTipo[$s['sede_tipoingreso']] ?? ''; ?>">
                                <?php echo htmlspecialchars((string)$s['tipo_nombre'] ?: $s['sede_tipoingreso'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td class="text-end">$<?php echo number_format((float)$s['sede_inscripcion'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format((float)$s['sede_pension'], 2); ?></td>
                        <td>
                            <small class="d-block text-muted">
                                <?php echo (int)$s['alumnos']; ?> alumnos · <?php echo (int)$s['empleados']; ?> empleados
                            </small>
                            <?php if ((int)$s['instalaciones'] > 0): ?>
                                <small class="d-block text-info">
                                    <i class="fas fa-warehouse"></i> <?php echo (int)$s['instalaciones']; ?> instalación(es)
                                </small>
                            <?php endif; ?>
                        </td>
                        <td class="ds-tabla-acciones">
                            <?php if ($puedeEditar): ?>
                                <a href="<?php echo APP_URL; ?>sedeForm/?id=<?php echo (int)$s['sede_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>

                            <?php if ($puedeEliminar): ?>
                                <form class="FormularioAjax d-inline" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/coreAjax.php"
                                      data-confirmar="La sede se eliminará de forma permanente.">
                                    <input type="hidden" name="modulo_core" value="eliminarSede">
                                    <input type="hidden" name="sede_id" value="<?php echo (int)$s['sede_id']; ?>">
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

    <div class="card-footer text-muted small">
        El <strong>tipo</strong> decide qué módulos operan sobre la sede:
        <em>Formativa</em> sólo para la escuela, <em>Alquiler</em> sólo para Arena,
        <em>Formativa y Alquiler</em> para ambos.
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
