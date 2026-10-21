<?php
/* Alta, edición y baja de roles. */

use admin\controllers\coreController;

$insCore = new coreController();

$tituloVista = 'Roles';
$vistaActual = 'rolList';

$roles   = $insCore->roles();
$modulos = ds_modulos_conocidos();

$puedeCrear    = puede_crear('rolList');
$puedeEditar   = puede_editar('rolList');
$puedeEliminar = puede_eliminar('rolList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Roles</h3>
        <?php if ($puedeCrear): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRol"
                    onclick="prepararRol(0,'','','A')">
                <i class="fas fa-plus me-1"></i> Nuevo rol
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Rol</th>
                        <th>Usuarios</th>
                        <th>Permisos</th>
                        <th>Módulos</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $r): $esSuper = (int)$r['rol_id'] === self_rol_superadmin(); ?>
                    <tr>
                        <td><?php echo (int)$r['rol_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($r['rol_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($esSuper): ?><span class="badge text-bg-warning ms-1">Protegido</span><?php endif; ?>
                            <?php if ($r['rol_detalle']): ?>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($r['rol_detalle'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)$r['usuarios']; ?></td>
                        <td><?php echo $esSuper ? '<span class="text-muted">todos</span>' : (int)$r['permisos']; ?></td>
                        <td>
                            <?php
                            $asignados = $esSuper ? array_keys($modulos) : array_filter(explode(',', (string)$r['modulos']));
                            if (!$asignados) echo '<span class="text-muted small">ninguno</span>';
                            foreach ($asignados as $mod):
                                if (!isset($modulos[$mod])) continue; ?>
                                <span class="badge-modulo badge-modulo--<?php echo $mod; ?>"><?php echo $modulos[$mod]; ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?php echo $r['rol_estado'] === 'A' ? 'success' : 'secondary'; ?>">
                                <?php echo $r['rol_estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td class="ds-tabla-acciones">
                            <a href="<?php echo APP_URL; ?>permisoRol/?rol=<?php echo (int)$r['rol_id']; ?>"
                               class="btn btn-sm btn-outline-primary" title="Permisos"><i class="fas fa-key"></i></a>

                            <?php if ($puedeEditar && !$esSuper): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Editar"
                                        data-bs-toggle="modal" data-bs-target="#modalRol"
                                        onclick="prepararRol(<?php echo (int)$r['rol_id']; ?>,
                                                 <?php echo htmlspecialchars(json_encode($r['rol_nombre']), ENT_QUOTES, 'UTF-8'); ?>,
                                                 <?php echo htmlspecialchars(json_encode($r['rol_detalle']), ENT_QUOTES, 'UTF-8'); ?>,
                                                 '<?php echo $r['rol_estado']; ?>')">
                                    <i class="fas fa-pen"></i>
                                </button>
                            <?php endif; ?>

                            <?php if ($puedeEliminar && !$esSuper): ?>
                                <form class="FormularioAjax d-inline" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/coreAjax.php"
                                      data-confirmar="Se eliminarán también los permisos de este rol.">
                                    <input type="hidden" name="modulo_core" value="eliminarRol">
                                    <input type="hidden" name="rol_id" value="<?php echo (int)$r['rol_id']; ?>">
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

<!-- ---------- Modal de alta / edición ---------- -->
<div class="modal fade" id="modalRol" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php">
                <input type="hidden" name="modulo_core" value="guardarRol">
                <input type="hidden" name="rol_id" id="rol_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalRolTitulo">Nuevo rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="rol_nombre">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="rol_nombre" id="rol_nombre" maxlength="20" required>
                        <small class="text-muted">Máximo 20 caracteres.</small>
                    </div>
                    <div class="mb-3">
                        <label for="rol_detalle">Descripción</label>
                        <textarea class="form-control" name="rol_detalle" id="rol_detalle" rows="2" maxlength="300"></textarea>
                    </div>
                    <div class="mb-3 mb-0">
                        <label for="rol_estado">Estado</label>
                        <select class="form-select" name="rol_estado" id="rol_estado">
                            <option value="A">Activo</option>
                            <option value="I">Inactivo</option>
                        </select>
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

<script>
function prepararRol(id, nombre, detalle, estado) {
    document.getElementById('rol_id').value      = id;
    document.getElementById('rol_nombre').value  = nombre || '';
    document.getElementById('rol_detalle').value = detalle || '';
    document.getElementById('rol_estado').value  = estado || 'A';
    document.getElementById('modalRolTitulo').textContent = id > 0 ? 'Editar rol' : 'Nuevo rol';
}
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
