<?php
/*
| Matriz de permisos: rol × vista × acción.
| Es la pantalla central de Core: define qué ve y qué puede hacer cada rol
| dentro de cada módulo del ecosistema.
*/

use admin\controllers\coreController;

$insCore = new coreController();

$tituloVista = 'Permisos';
$vistaActual = 'permisoRol';

$roles   = $insCore->roles();
$modulos = ds_modulos_conocidos();

/* Rol y módulo seleccionados; por defecto el primer rol no superadmin. */
$rolSel = (int)($_GET['rol'] ?? 0);
if ($rolSel === 0) {
    foreach ($roles as $r) {
        if ((int)$r['rol_id'] !== self_rol_superadmin()) { $rolSel = (int)$r['rol_id']; break; }
    }
}

$modSel = (string)($_GET['modulo'] ?? 'basketball');
if (!isset($modulos[$modSel])) $modSel = 'basketball';

$esSuper   = ($rolSel === self_rol_superadmin());
$matriz    = $esSuper ? [] : $insCore->matrizPermisos($rolSel, $modSel);
$rolActual = $insCore->rol($rolSel);
$puedeEdit = puede_editar('permisoRol');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center" style="gap:12px;">
        <h3 class="card-title mb-0">Permisos por rol y módulo</h3>
    </div>

    <div class="card-body">

        <!-- ---------- Selección de rol y módulo ---------- -->
        <form method="GET" action="<?php echo APP_URL; ?>permisoRol/" class="row g-2 align-items-end mb-4">
            <div class="col-md-4 mb-2">
                <label for="rol" class="mb-1">Rol</label>
                <select name="rol" id="rol" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int)$r['rol_id']; ?>" <?php echo $rolSel === (int)$r['rol_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['rol_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            (<?php echo (int)$r['usuarios']; ?> usuario<?php echo (int)$r['usuarios'] === 1 ? '' : 's'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 mb-2">
                <label for="modulo" class="mb-1">Módulo</label>
                <select name="modulo" id="modulo" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($modulos as $clave => $nombre): ?>
                        <option value="<?php echo $clave; ?>" <?php echo $modSel === $clave ? 'selected' : ''; ?>>
                            <?php echo $nombre; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 mb-2 text-md-end">
                <a href="<?php echo APP_URL; ?>moduloRol/?rol=<?php echo $rolSel; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-th-large me-1"></i> Módulos del rol
                </a>
            </div>
        </form>

        <?php if ($esSuper): ?>

            <div class="aviso-superadmin">
                <i class="fas fa-shield-alt fa-lg mt-1"></i>
                <div>
                    <strong>El Super Administrador tiene acceso total por definición.</strong><br>
                    No se le asignan permisos: pasa por encima del control de acceso para
                    garantizar que el sistema nunca quede sin administrador. Seleccione otro
                    rol para editar sus permisos.
                </div>
            </div>

        <?php elseif (empty($matriz)): ?>

            <div class="text-center text-muted py-5">
                <i class="fas fa-folder-open fa-2x mb-3 d-block"></i>
                El módulo <strong><?php echo $modulos[$modSel]; ?></strong> todavía no tiene vistas registradas.
            </div>

        <?php else: ?>

            <form class="FormularioAjax" action="<?php echo APP_URL; ?>ajax/coreAjax.php" method="POST">
                <input type="hidden" name="modulo_core" value="guardarPermisos">
                <input type="hidden" name="rol_id" value="<?php echo $rolSel; ?>">
                <input type="hidden" name="modulo" value="<?php echo htmlspecialchars($modSel, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="table-responsive">
                    <table class="table table-sm tabla-permisos mb-0">
                        <thead>
                            <tr>
                                <th>Vista</th>
                                <th class="accion">
                                    Ver
                                    <?php if ($puedeEdit): ?>
                                        <a href="#" data-marcar-columna="ver" class="d-block small font-weight-normal">alternar</a>
                                    <?php endif; ?>
                                </th>
                                <th class="accion">
                                    Crear
                                    <?php if ($puedeEdit): ?>
                                        <a href="#" data-marcar-columna="crear" class="d-block small font-weight-normal">alternar</a>
                                    <?php endif; ?>
                                </th>
                                <th class="accion">
                                    Editar
                                    <?php if ($puedeEdit): ?>
                                        <a href="#" data-marcar-columna="editar" class="d-block small font-weight-normal">alternar</a>
                                    <?php endif; ?>
                                </th>
                                <th class="accion">
                                    Eliminar
                                    <?php if ($puedeEdit): ?>
                                        <a href="#" data-marcar-columna="eliminar" class="d-block small font-weight-normal">alternar</a>
                                    <?php endif; ?>
                                </th>
                                <th class="accion">
                                    Exportar
                                    <?php if ($puedeEdit): ?>
                                        <a href="#" data-marcar-columna="exportar" class="d-block small font-weight-normal">alternar</a>
                                    <?php endif; ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $grupoPrevio = null;
                        foreach ($matriz as $m):
                            $grupo = $m['padre'] !== '' ? $m['padre'] : 'General';
                            if ($grupo !== $grupoPrevio):
                                $grupoPrevio = $grupo;
                        ?>
                            <tr class="grupo"><td colspan="6"><?php echo htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <?php endif; ?>

                            <tr data-menu="<?php echo (int)$m['menu_id']; ?>">
                                <td>
                                    <i class="<?php echo htmlspecialchars($m['menu_icono'], ENT_QUOTES, 'UTF-8'); ?> me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($m['menu_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    <small class="text-muted d-block ms-4"><?php echo htmlspecialchars($m['menu_vista'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>

                                <?php
                                $acciones = [
                                    'ver'      => $m['ver'],
                                    'crear'    => $m['crear'],
                                    'editar'   => $m['editar'],
                                    'eliminar' => $m['eliminar'],
                                    'exportar' => $m['exportar'],
                                ];
                                foreach ($acciones as $accion => $valor):
                                ?>
                                    <td class="accion">
                                        <label class="switch <?php echo $accion === 'ver' ? 'switch--ver' : ''; ?>">
                                            <input type="checkbox"
                                                   name="perm[<?php echo (int)$m['menu_id']; ?>][<?php echo $accion; ?>]"
                                                   value="1"
                                                   data-accion="<?php echo $accion; ?>"
                                                   <?php echo $valor === 'S' ? 'checked' : ''; ?>
                                                   <?php echo $puedeEdit ? '' : 'disabled'; ?>>
                                            <span></span>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($puedeEdit): ?>
                    <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
                        <p class="text-muted mb-0 small">
                            Sin <strong>Ver</strong> la vista no aparece en el menú del usuario ni puede abrirse por URL;
                            las demás acciones quedan desactivadas automáticamente.
                        </p>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Guardar permisos de
                            <?php echo htmlspecialchars($rolActual['rol_nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <p class="text-muted mt-3 mb-0 small">
                        <i class="fas fa-lock me-1"></i> Su rol puede consultar los permisos pero no modificarlos.
                    </p>
                <?php endif; ?>
            </form>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
