<?php
/*
| Catálogos del sistema: las listas de valores que consumen todos los
| módulos (formas de pago, parentescos, tipos de sede…).
*/

use admin\controllers\coreController;

$insCore = new coreController();

$tituloVista = 'Catálogos';
$vistaActual = 'catalogoList';

$catalogos = $insCore->catalogos();

$tablaSel = (int)($_GET['tabla'] ?? 0);
if ($tablaSel === 0 && $catalogos) {
    $tablaSel = (int)$catalogos[0]['tabla_id'];
}

$valores = $tablaSel > 0 ? $insCore->valoresCatalogo($tablaSel) : [];

$nombreSel = '';
foreach ($catalogos as $c) {
    if ((int)$c['tabla_id'] === $tablaSel) { $nombreSel = $c['tabla_nombre']; break; }
}

$puedeCrear    = puede_crear('catalogoList');
$puedeEditar   = puede_editar('catalogoList');
$puedeEliminar = puede_eliminar('catalogoList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row">
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0"><?php echo count($catalogos); ?> catálogos</h3></div>
            <div class="card-body p-0" style="max-height:620px;overflow:auto;">
                <div class="list-group list-group-flush">
                    <?php foreach ($catalogos as $c): $activo = (int)$c['tabla_id'] === $tablaSel; ?>
                        <a href="<?php echo APP_URL; ?>catalogoList/?tabla=<?php echo (int)$c['tabla_id']; ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?php echo $activo ? ' active' : ''; ?>"
                           <?php echo $activo ? 'style="background:var(--ds-primary);border-color:var(--ds-primary);"' : ''; ?>>
                            <span><?php echo htmlspecialchars($c['tabla_nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="badge text-bg-<?php echo $activo ? 'light' : 'secondary'; ?>">
                                <?php echo (int)$c['activos']; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    <?php echo htmlspecialchars($nombreSel ?: 'Valores', ENT_QUOTES, 'UTF-8'); ?>
                </h3>
                <?php if ($puedeCrear && $tablaSel > 0): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalValor"
                            onclick="prepararValor(1,'','','A')">
                        <i class="fas fa-plus me-1"></i> Nuevo valor
                    </button>
                <?php endif; ?>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th style="width:90px;">Código</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$valores): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Este catálogo no tiene valores.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($valores as $v): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($v['catalogo_valor'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><?php echo htmlspecialchars($v['catalogo_descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge text-bg-<?php echo $v['catalogo_estado'] === 'A' ? 'success' : 'secondary'; ?>">
                                        <?php echo $v['catalogo_estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="ds-tabla-acciones">
                                    <?php if ($puedeEditar): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Editar"
                                                data-bs-toggle="modal" data-bs-target="#modalValor"
                                                onclick="prepararValor(0,
                                                    <?php echo htmlspecialchars(json_encode($v['catalogo_valor']), ENT_QUOTES, 'UTF-8'); ?>,
                                                    <?php echo htmlspecialchars(json_encode($v['catalogo_descripcion']), ENT_QUOTES, 'UTF-8'); ?>,
                                                    '<?php echo $v['catalogo_estado']; ?>')">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($puedeEliminar): ?>
                                        <form class="FormularioAjax d-inline" method="POST"
                                              action="<?php echo APP_URL; ?>ajax/coreAjax.php"
                                              data-confirmar="Se eliminará el valor del catálogo.">
                                            <input type="hidden" name="modulo_core" value="eliminarValorCatalogo">
                                            <input type="hidden" name="catalogo_valor" value="<?php echo htmlspecialchars($v['catalogo_valor'], ENT_QUOTES, 'UTF-8'); ?>">
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
                Los códigos son de 3 caracteres y <strong>únicos en todo el sistema</strong>,
                no sólo dentro del catálogo.
            </div>
        </div>
    </div>
</div>

<?php if ($puedeCrear || $puedeEditar): ?>
<div class="modal fade" id="modalValor" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php">
                <input type="hidden" name="modulo_core" value="guardarValorCatalogo">
                <input type="hidden" name="catalogo_tablaid" value="<?php echo $tablaSel; ?>">
                <input type="hidden" name="es_nuevo" id="es_nuevo" value="1">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalValorTitulo">Nuevo valor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="catalogo_valor">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase" id="catalogo_valor" name="catalogo_valor"
                               maxlength="3" minlength="3" pattern="[A-Za-z0-9]{3}" required>
                        <small class="text-muted">Exactamente 3 caracteres. No se puede cambiar después.</small>
                    </div>

                    <div class="mb-3">
                        <label for="catalogo_descripcion">Descripción <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="catalogo_descripcion" name="catalogo_descripcion"
                               maxlength="50" required>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="catalogo_estado">Estado</label>
                        <select class="form-select" id="catalogo_estado" name="catalogo_estado">
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
function prepararValor(esNuevo, valor, descripcion, estado) {
    document.getElementById('es_nuevo').value             = esNuevo ? '1' : '0';
    document.getElementById('catalogo_valor').value       = valor || '';
    document.getElementById('catalogo_valor').readOnly    = !esNuevo;
    document.getElementById('catalogo_descripcion').value = descripcion || '';
    document.getElementById('catalogo_estado').value      = estado || 'A';
    document.getElementById('modalValorTitulo').textContent = esNuevo ? 'Nuevo valor' : 'Editar valor';
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
