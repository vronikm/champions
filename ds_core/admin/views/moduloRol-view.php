<?php
/* Asignación de módulos del ecosistema a un rol (nivel 1 de acceso). */

use admin\controllers\coreController;

$insCore = new coreController();

$tituloVista = 'Módulos por rol';
$vistaActual = 'moduloRol';

$roles   = $insCore->roles();
$modulos = ds_modulos_conocidos();

$rolSel = (int)($_GET['rol'] ?? 0);
if ($rolSel === 0) {
    foreach ($roles as $r) {
        if ((int)$r['rol_id'] !== self_rol_superadmin()) { $rolSel = (int)$r['rol_id']; break; }
    }
}

$esSuper    = ($rolSel === self_rol_superadmin());
$asignados  = $esSuper ? array_keys($modulos) : $insCore->modulosDelRol($rolSel);
$rolActual  = $insCore->rol($rolSel);
$puedeEdit  = puede_editar('moduloRol') && !$esSuper;

/* Sólo los módulos ya construidos son asignables de forma útil. */
$disponibles = ds_modulos();

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Acceso a módulos</h3>
    </div>
    <div class="card-body">

        <form method="GET" action="<?php echo APP_URL; ?>moduloRol/" class="row g-2 align-items-end mb-4">
            <div class="col-md-5 mb-2">
                <label for="rol" class="mb-1">Rol</label>
                <select name="rol" id="rol" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int)$r['rol_id']; ?>" <?php echo $rolSel === (int)$r['rol_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['rol_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-7 mb-2 text-md-end">
                <a href="<?php echo APP_URL; ?>permisoRol/?rol=<?php echo $rolSel; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-key me-1"></i> Permisos del rol
                </a>
            </div>
        </form>

        <?php if ($esSuper): ?>
            <div class="aviso-superadmin">
                <i class="fas fa-shield-alt fa-lg mt-1"></i>
                <div>
                    <strong>El Super Administrador accede a todos los módulos.</strong><br>
                    Es una condición del rol, no una asignación: por eso no se puede editar aquí.
                </div>
            </div>
        <?php else: ?>

            <form class="FormularioAjax" action="<?php echo APP_URL; ?>ajax/coreAjax.php" method="POST">
                <input type="hidden" name="modulo_core" value="guardarModulos">
                <input type="hidden" name="rol_id" value="<?php echo $rolSel; ?>">

                <div class="row">
                    <?php foreach ($modulos as $clave => $nombre):
                        $activo    = in_array($clave, $asignados, true);
                        $construido = !empty($disponibles[$clave]['activo']) || $clave === 'core';
                    ?>
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label class="d-flex align-items-center w-100 mb-0 p-3"
                                   style="gap:12px;border:1px solid var(--core-borde);border-radius:var(--ds-radius-md);
                                          background:var(--bs-body-bg);cursor:<?php echo $puedeEdit ? 'pointer' : 'default'; ?>;">
                                <span class="switch">
                                    <input type="checkbox" name="modulos[]" value="<?php echo $clave; ?>"
                                           <?php echo $activo ? 'checked' : ''; ?>
                                           <?php echo $puedeEdit ? '' : 'disabled'; ?>>
                                    <span></span>
                                </span>
                                <span>
                                    <strong><?php echo $nombre; ?></strong>
                                    <small class="d-block text-muted">
                                        <?php echo $construido ? 'Disponible' : 'Aún no construido'; ?>
                                    </small>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($puedeEdit): ?>
                    <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
                        <p class="text-muted mb-0 small">
                            Sin acceso al módulo, sus vistas quedan bloqueadas aunque el rol tenga permisos sobre ellas.
                        </p>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Guardar módulos de
                            <?php echo htmlspecialchars($rolActual['rol_nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <p class="text-muted mt-3 mb-0 small">
                        <i class="fas fa-lock me-1"></i> Su rol puede consultar pero no modificar esta asignación.
                    </p>
                <?php endif; ?>
            </form>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
