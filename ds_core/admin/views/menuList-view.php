<?php
/* Catálogo de vistas registradas por módulo. Son las unidades sobre las
   que se conceden permisos, y de ellas se construye el menú de cada rol. */

use admin\controllers\coreController;

$insCore = new coreController();

$tituloVista = 'Menús';
$vistaActual = 'menuList';

$modulos = ds_modulos_conocidos();
$modSel  = (string)($_GET['modulo'] ?? '');
if ($modSel !== '' && !isset($modulos[$modSel])) $modSel = '';

$menus = $insCore->menus($modSel);

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
        <h3 class="card-title mb-0"><?php echo count($menus); ?> vista<?php echo count($menus) === 1 ? '' : 's'; ?> registradas</h3>

        <div class="d-flex align-items-center" style="gap:12px;">
            <form method="GET" action="<?php echo APP_URL; ?>menuList/" class="d-flex flex-wrap align-items-center gap-2">
                <label for="modulo" class="me-2 mb-0">Módulo</label>
                <select name="modulo" id="modulo" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($modulos as $clave => $nombre): ?>
                        <option value="<?php echo $clave; ?>" <?php echo $modSel === $clave ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if (puede_crear('menuList')): ?>
                <a href="<?php echo APP_URL; ?>menuForm/<?php echo $modSel !== '' ? '?modulo=' . $modSel : ''; ?>"
                   class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Nuevo menú
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Módulo</th>
                        <th>Nombre</th>
                        <th>Vista</th>
                        <th>Grupo</th>
                        <th>Orden</th>
                        <th>Roles con acceso</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$menus): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Sin vistas registradas.</td></tr>
                <?php endif; ?>

                <?php foreach ($menus as $m): ?>
                    <tr>
                        <td><?php echo (int)$m['menu_id']; ?></td>
                        <td>
                            <span class="badge-modulo badge-modulo--<?php echo htmlspecialchars($m['menu_modulo'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $modulos[$m['menu_modulo']] ?? $m['menu_modulo']; ?>
                            </span>
                        </td>
                        <td>
                            <i class="<?php echo htmlspecialchars($m['menu_icono'], ENT_QUOTES, 'UTF-8'); ?> me-2 text-muted"></i>
                            <?php echo htmlspecialchars($m['menu_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($m['menu_vista'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td><?php echo htmlspecialchars((string)$m['padre'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)$m['menu_orden']; ?></td>
                        <td><?php echo (int)$m['roles']; ?></td>
                        <td>
                            <span class="badge text-bg-<?php echo $m['menu_estado'] === 'A' ? 'success' : 'secondary'; ?>">
                                <?php echo $m['menu_estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td class="ds-tabla-acciones">
                            <?php
                            /* Una vista huérfana (el menú apunta a una ruta que ya no
                               existe en el módulo) se señala aquí, que es donde se corrige. */
                            if (!ds_vista_existe($m['menu_modulo'], $m['menu_vista'])): ?>
                                <span class="badge text-bg-danger me-1" title="La vista no existe en el módulo">
                                    <i class="fas fa-unlink"></i> huérfano
                                </span>
                            <?php endif; ?>

                            <?php if (puede_editar('menuList')): ?>
                                <a href="<?php echo APP_URL; ?>menuForm/?id=<?php echo (int)$m['menu_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>

                            <?php if (puede_eliminar('menuList') && $m['menu_modulo'] !== 'core'): ?>
                                <form class="FormularioAjax d-inline" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/coreAjax.php"
                                      data-confirmar="Se eliminarán también los permisos que los roles tengan sobre esta vista.">
                                    <input type="hidden" name="modulo_core" value="eliminarMenu">
                                    <input type="hidden" name="menu_id" value="<?php echo (int)$m['menu_id']; ?>">
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
        Las vistas que no figuran aquí no se restringen: son pantallas de apoyo
        (formularios, PDF, perfiles) que heredan el alcance del listado desde el que se abren.
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
