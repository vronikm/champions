<?php
/* Alta y edición de un cliente de Arena. */

use arena\controllers\arenaController;

$insArena = new arenaController();

$id     = (int)($_GET['id'] ?? 0);
$cli    = $id > 0 ? $insArena->cliente($id) : null;
$esAlta = ($cli === null);

if ($esAlta && !puede_crear('clienteList'))  { require_once __DIR__ . "/accesoDenegado-view.php"; exit(); }
if (!$esAlta && !puede_editar('clienteList')) { require_once __DIR__ . "/accesoDenegado-view.php"; exit(); }

$tituloVista = $esAlta ? 'Nuevo cliente' : 'Editar cliente';
$vistaActual = 'clienteList';

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo $tituloVista; ?></h3></div>

            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                <input type="hidden" name="modulo_arena" value="guardarCliente">
                <input type="hidden" name="cliente_id" value="<?php echo $id; ?>">

                <div class="card-body">
                    <div class="row g-2">
                        <div class="mb-3 col-md-5">
                            <label for="cliente_identificacion">Identificación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cliente_identificacion" name="cliente_identificacion"
                                   maxlength="20" required
                                   value="<?php echo htmlspecialchars((string)($cli['cliente_identificacion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <small class="text-muted">Cédula, RUC o pasaporte.</small>
                        </div>

                        <div class="mb-3 col-md-7">
                            <label for="cliente_nombre">Nombre o razón social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cliente_nombre" name="cliente_nombre"
                                   maxlength="120" required
                                   value="<?php echo htmlspecialchars((string)($cli['cliente_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-5">
                            <label for="cliente_celular">Celular</label>
                            <input type="text" class="form-control" id="cliente_celular" name="cliente_celular" maxlength="20"
                                   value="<?php echo htmlspecialchars((string)($cli['cliente_celular'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3 col-md-5">
                            <label for="cliente_correo">Correo</label>
                            <input type="email" class="form-control" id="cliente_correo" name="cliente_correo" maxlength="80"
                                   value="<?php echo htmlspecialchars((string)($cli['cliente_correo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3 col-md-2">
                            <label for="cliente_estado">Estado</label>
                            <select class="form-select" id="cliente_estado" name="cliente_estado">
                                <option value="A" <?php echo ($cli['cliente_estado'] ?? 'A') === 'A' ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo ($cli['cliente_estado'] ?? '') === 'I' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="cliente_direccion">Dirección</label>
                        <input type="text" class="form-control" id="cliente_direccion" name="cliente_direccion" maxlength="200"
                               value="<?php echo htmlspecialchars((string)($cli['cliente_direccion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'clienteList/'); ?>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
