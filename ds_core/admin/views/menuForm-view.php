<?php
/*
| Alta y edición de un menú.
|
| Hay dos tipos:
|   · Entrada: abre una pantalla. La vista se elige de una lista cerrada
|     —las rutas que el módulo declara en su config/vistas.php— para que
|     un menú nunca apunte a una pantalla inexistente.
|   · Grupo: sólo es una cabecera que agrupa entradas. No abre nada y no
|     lleva permiso propio; el permiso se concede sobre lo que cuelga.
*/

use admin\controllers\coreController;

$insCore = new coreController();

$id    = (int)($_GET['id'] ?? 0);
$menu  = $id > 0 ? $insCore->menu($id) : null;
$esAlta = ($menu === null);

if ($esAlta && !puede_crear('menuList')) {
    require_once __DIR__ . "/accesoDenegado-view.php"; exit();
}
if (!$esAlta && !puede_editar('menuList')) {
    require_once __DIR__ . "/accesoDenegado-view.php"; exit();
}

$tituloVista = $esAlta ? 'Nuevo menú' : 'Editar menú';
$vistaActual = 'menuList';

$modulos = ds_modulos_conocidos();

/* Módulo en edición: el del menú, el pedido por URL, o Basketball. */
$modSel = $menu['menu_modulo'] ?? (string)($_GET['modulo'] ?? 'basketball');
if (!isset($modulos[$modSel])) $modSel = 'basketball';

$vistas = $insCore->vistasDisponibles($modSel, (string)($menu['menu_vista'] ?? ''));
$padres = $insCore->menusPadre($modSel);

/* Un menú con entradas colgando tiene que seguir siendo grupo: pasarlo a
   entrada normal las dejaría huérfanas y desaparecerían del menú. */
$esGrupo    = ($menu['menu_hijo'] ?? 'N') === 'S';
$tieneHijos = $id > 0 && $insCore->menuTieneHijos($id);

/* Si el módulo no tiene vistas libres, sólo cabe crear grupos. */
$esGrupo = $esGrupo || ($esAlta && !$vistas);

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo $tituloVista; ?></h3>
            </div>

            <!-- Cambiar de módulo recarga la lista de vistas disponibles. -->
            <div class="card-body border-bottom">
                <form method="GET" action="<?php echo APP_URL; ?>menuForm/" class="row g-2 align-items-end mb-0">
                    <?php if ($id > 0): ?>
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <?php endif; ?>
                    <div class="col-md-8">
                        <label for="modulo" class="mb-1">Módulo <span class="text-danger">*</span></label>
                        <select name="modulo" id="modulo" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($modulos as $clave => $nombre): ?>
                                <option value="<?php echo $clave; ?>" <?php echo $modSel === $clave ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                    (<?php echo count(ds_vistas_modulo($clave)); ?> vistas)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Determina qué vistas se pueden elegir abajo.</small>
                    </div>
                </form>
            </div>

            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php">
                <input type="hidden" name="modulo_core" value="guardarMenu">
                <input type="hidden" name="menu_id" value="<?php echo $id; ?>">
                <input type="hidden" name="menu_modulo" value="<?php echo htmlspecialchars($modSel, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="menu_grupo" id="menu_grupo" value="<?php echo $esGrupo ? '1' : '0'; ?>">

                <div class="card-body">

                    <!-- Tipo: abre una pantalla, o sólo agrupa otras entradas -->
                    <div class="mb-3">
                        <label class="mb-1">Tipo de menú</label>
                        <div class="btn-group btn-group-toggle d-flex" data-bs-toggle="buttons">
                            <label class="btn btn-outline-primary flex-fill <?php echo $esGrupo ? '' : 'active'; ?>">
                                <input type="radio" name="tipo_menu" value="entrada" autocomplete="off"
                                       <?php echo $esGrupo ? '' : 'checked'; ?>
                                       <?php echo ($vistas && !$tieneHijos) ? '' : 'disabled'; ?>>
                                <i class="fas fa-link me-1"></i> Abre una pantalla
                            </label>
                            <label class="btn btn-outline-primary flex-fill <?php echo $esGrupo ? 'active' : ''; ?>">
                                <input type="radio" name="tipo_menu" value="grupo" autocomplete="off"
                                       <?php echo $esGrupo ? 'checked' : ''; ?>>
                                <i class="fas fa-folder me-1"></i> Grupo (sólo agrupa)
                            </label>
                        </div>
                        <small class="text-muted d-block">
                            Un grupo es una cabecera del menú lateral: no abre pantalla ni lleva permiso
                            propio. El permiso se concede sobre las entradas que cuelgan de él.
                        </small>

                        <?php if ($tieneHijos): ?>
                            <small class="d-block text-warning mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Este menú agrupa otras entradas, así que debe seguir siendo un grupo.
                                Muévalas antes de convertirlo.
                            </small>
                        <?php elseif (!$vistas): ?>
                            <small class="d-block text-warning mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php if (!ds_vistas_modulo($modSel)): ?>
                                    <?php echo $modulos[$modSel]; ?> todavía no publica sus vistas en
                                    <code>config/vistas.php</code>, así que aquí sólo puede crear grupos.
                                <?php else: ?>
                                    Todas las vistas de <?php echo $modulos[$modSel]; ?> ya tienen menú:
                                    aquí sólo puede crear grupos.
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-6 solo-entrada">
                            <label for="menu_vista">Vista <span class="text-danger">*</span></label>
                            <select class="form-select" id="menu_vista" name="menu_vista">
                                <option value="">Seleccione…</option>
                                <?php foreach ($vistas as $v): ?>
                                    <option value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($menu['menu_vista'] ?? '') === $v ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                Sólo rutas que existen en el módulo y aún no tienen menú.
                            </small>
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="menu_nombre">Texto del menú <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="menu_nombre" name="menu_nombre"
                                   maxlength="50" required
                                   value="<?php echo htmlspecialchars((string)($menu['menu_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-6 solo-entrada">
                            <label for="menu_padreid">Grupo</label>
                            <select class="form-select" id="menu_padreid" name="menu_padreid">
                                <option value="0">Sin grupo (nivel superior)</option>
                                <?php foreach ($padres as $p):
                                    if ((int)$p['menu_id'] === $id) continue; ?>
                                    <option value="<?php echo (int)$p['menu_id']; ?>"
                                        <?php echo (int)($menu['menu_padreid'] ?? 0) === (int)$p['menu_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['menu_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$padres): ?>
                                <small class="text-muted">Todavía no hay grupos en este módulo.</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3 col-md-3">
                            <label for="menu_orden">Orden</label>
                            <input type="number" class="form-control" id="menu_orden" name="menu_orden" min="0"
                                   value="<?php echo (int)($menu['menu_orden'] ?? 0); ?>">
                        </div>

                        <div class="mb-3 col-md-3">
                            <label for="menu_estado">Estado</label>
                            <select class="form-select" id="menu_estado" name="menu_estado">
                                <option value="A" <?php echo ($menu['menu_estado'] ?? 'A') === 'A' ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo ($menu['menu_estado'] ?? '') === 'I' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="menu_icono">Icono</label>
                        <div class="input-group">
                                                        <span class="input-group-text"><i id="vistaPrevia" class="<?php echo htmlspecialchars((string)($menu['menu_icono'] ?? 'nav-icon far fa-circle'), ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                        
                            <input type="text" class="form-control" id="menu_icono" name="menu_icono" maxlength="50"
                                   value="<?php echo htmlspecialchars((string)($menu['menu_icono'] ?? 'nav-icon far fa-circle'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <small class="text-muted">
                            Clase de Font Awesome <strong>5</strong>, por ejemplo <code>nav-icon fas fa-users</code>.
                        </small>
                    </div>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'menuList/'); ?>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    /* Vista previa del icono mientras se escribe. */
    var campoIcono = document.getElementById('menu_icono');
    if (campoIcono) {
        campoIcono.addEventListener('input', function () {
            document.getElementById('vistaPrevia').className = this.value || 'nav-icon far fa-circle';
        });
    }

    /* Los campos de vista y grupo sólo tienen sentido en una entrada. */
    var marca  = document.getElementById('menu_grupo');
    var vista  = document.getElementById('menu_vista');
    var campos = document.querySelectorAll('.solo-entrada');

    function pintar(esGrupo) {
        marca.value = esGrupo ? '1' : '0';
        campos.forEach(function (c) { c.style.display = esGrupo ? 'none' : ''; });
        if (vista) { vista.required = !esGrupo; }
    }

    document.querySelectorAll('input[name="tipo_menu"]').forEach(function (r) {
        r.addEventListener('change', function () { pintar(this.value === 'grupo'); });
    });

    pintar(marca.value === '1');
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
