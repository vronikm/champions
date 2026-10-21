<?php
/*
| Identidad de la organización.
| Es el nombre y el logo que aparecen en recibos, facturas, PDF y reportes
| de todo el ecosistema.
*/

use admin\controllers\coreController;

$insCore = new coreController();

if (!usuario_tiene_permiso('organizacionForm')) {
    require_once __DIR__ . "/accesoDenegado-view.php"; exit();
}

$tituloVista = 'Organización';
$vistaActual = 'organizacionForm';

$org       = $insCore->organizacion();
$logoUrl   = ds_logo_url();
$firmaUrl  = ds_firma_url();
$puedeEdit = puede_editar('organizacionForm');

/* Ejemplo en vivo de cómo saldrá el encabezado en los documentos. */
$sedes    = $insCore->sedes();
$ejemplo  = $sedes ? ds_nombre_organizacion((int)$sedes[0]['sede_id'])
                   : ds_nombre_organizacion();

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row justify-content-center">
    <div class="col-lg-9">

        <div class="card">
            <div class="card-header"><h3 class="card-title">Identidad de la organización</h3></div>

            <form class="FormularioAjax" method="POST" enctype="multipart/form-data"
                  action="<?php echo APP_URL; ?>ajax/coreAjax.php">
                <input type="hidden" name="modulo_core" value="guardarOrganizacion">
                <input type="hidden" name="escuela_id" value="<?php echo (int)$org['escuela_id']; ?>">
                <input type="hidden" name="quitar_logo"  id="quitar_logo"  value="0">
                <input type="hidden" name="quitar_firma" id="quitar_firma" value="0">

                <div class="card-body">

                    <div class="row g-2">
                        <div class="mb-3 col-md-8">
                            <label for="escuela_nombre">Nombre legal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="escuela_nombre" name="escuela_nombre"
                                   maxlength="100" required <?php echo $puedeEdit ? '' : 'readonly'; ?>
                                   value="<?php echo htmlspecialchars((string)$org['escuela_nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                            <small class="text-muted">
                                Es el nombre que encabeza recibos, facturas y reportes.
                            </small>
                        </div>

                        <div class="mb-3 col-md-4">
                            <label for="escuela_ruc">RUC</label>
                            <input type="text" class="form-control" id="escuela_ruc" name="escuela_ruc"
                                   maxlength="20" <?php echo $puedeEdit ? '' : 'readonly'; ?>
                                   value="<?php echo htmlspecialchars((string)$org['escuela_ruc'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <!-- Cómo quedará el encabezado -->
                    <div class="aviso-superadmin mb-3">
                        <i class="fas fa-file-invoice fa-lg mt-1"></i>
                        <div>
                            En los documentos aparecerá así:<br>
                            <strong id="vistaPreviaNombre"><?php echo htmlspecialchars($ejemplo, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="escuela_direccion">Dirección</label>
                        <input type="text" class="form-control" id="escuela_direccion" name="escuela_direccion"
                               maxlength="200" <?php echo $puedeEdit ? '' : 'readonly'; ?>
                               value="<?php echo htmlspecialchars((string)$org['escuela_direccion'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-5">
                            <label for="escuela_email">Correo</label>
                            <input type="email" class="form-control" id="escuela_email" name="escuela_email"
                                   maxlength="50" <?php echo $puedeEdit ? '' : 'readonly'; ?>
                                   value="<?php echo htmlspecialchars((string)$org['escuela_email'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="escuela_telefono">Teléfono</label>
                            <input type="text" class="form-control" id="escuela_telefono" name="escuela_telefono"
                                   maxlength="50" <?php echo $puedeEdit ? '' : 'readonly'; ?>
                                   value="<?php echo htmlspecialchars((string)$org['escuela_telefono'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="mb-3 col-md-3">
                            <label for="escuela_movil">Móvil</label>
                            <input type="text" class="form-control" id="escuela_movil" name="escuela_movil"
                                   maxlength="50" <?php echo $puedeEdit ? '' : 'readonly'; ?>
                                   value="<?php echo htmlspecialchars((string)($org['escuela_movil'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <hr>

                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 text-center mb-3">
                            <?php if ($logoUrl !== ''): ?>
                                <img src="<?php echo $logoUrl; ?>" alt="Logo" id="vistaPreviaLogo"
                                     style="max-height:120px;max-width:100%;border:1px solid var(--core-borde);
                                            border-radius:var(--ds-radius-sm);padding:8px;background:#fff;">
                            <?php else: ?>
                                <div id="vistaPreviaLogo" class="text-muted"
                                     style="height:120px;display:flex;align-items:center;justify-content:center;
                                            border:1px dashed var(--core-borde);border-radius:var(--ds-radius-sm);">
                                    <span><i class="fas fa-image fa-2x"></i><br><small>Sin logo</small></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-9">
                            <div class="mb-3 mb-1">
                                <label for="escuela_logo">Logo de la organización</label>
                                <input type="file" class="form-control" id="escuela_logo" name="escuela_logo"
                                       accept="image/jpeg,image/png,image/webp" <?php echo $puedeEdit ? '' : 'disabled'; ?>>
                                <small class="text-muted">
                                    JPG, PNG o WEBP · máximo 2 MB. Las sedes sin logo propio usan éste.
                                </small>
                            </div>

                            <?php if ($logoUrl !== '' && $puedeEdit): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                                        data-quitar="logo" data-previa="vistaPreviaLogo">
                                    <i class="fas fa-times me-1"></i> Quitar logo actual
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr>

                    <!-- Firma autorizada: se estampa al pie de los recibos -->
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 text-center mb-3">
                            <?php if ($firmaUrl !== ''): ?>
                                <img src="<?php echo $firmaUrl; ?>" alt="Firma autorizada" id="vistaPreviaFirma"
                                     style="max-height:120px;max-width:100%;border:1px solid var(--core-borde);
                                            border-radius:var(--ds-radius-sm);padding:8px;background:#fff;">
                            <?php else: ?>
                                <div id="vistaPreviaFirma" class="text-muted"
                                     style="height:120px;display:flex;align-items:center;justify-content:center;
                                            border:1px dashed var(--core-borde);border-radius:var(--ds-radius-sm);">
                                    <span><i class="fas fa-signature fa-2x"></i><br><small>Sin firma</small></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-9">
                            <div class="mb-3 mb-1">
                                <label for="escuela_firma">Firma autorizada</label>
                                <input type="file" class="form-control" id="escuela_firma" name="escuela_firma"
                                       accept="image/jpeg,image/png,image/webp" <?php echo $puedeEdit ? '' : 'disabled'; ?>>
                                <small class="text-muted">
                                    Va al pie de los recibos, sobre la leyenda «Firma autorizada».
                                    Se recomienda una imagen apaisada (aprox. 2:1) con la firma sobre fondo claro.
                                </small>
                            </div>

                            <?php if ($firmaUrl !== '' && $puedeEdit): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                                        data-quitar="firma" data-previa="vistaPreviaFirma">
                                    <i class="fas fa-times me-1"></i> Quitar firma actual
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'panel/', [
                    'soloLectura' => !$puedeEdit,
                    'nota'        => $puedeEdit ? ''
                        : '<i class="' . ds_icono('bloqueado') . ' me-1"></i> '
                        . 'Su rol puede consultar estos datos pero no modificarlos.',
                ]); ?>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Cómo se usa</h3></div>
            <div class="card-body">
                <ul class="mb-0 text-muted" style="font-size:.9rem;">
                    <li>El <strong>nombre legal</strong> encabeza recibos, facturas, PDF y reportes de todos los módulos.</li>
                    <li>Cuando el documento corresponde a una sede, se compone como
                        <em>«Nombre, sede Tal»</em>.</li>
                    <li>El <strong>logo</strong> y la <strong>firma autorizada</strong> son el respaldo:
                        una sede sin los suyos usa éstos.
                        Se configuran por sede en <a href="<?php echo APP_URL; ?>sedeList/">Sedes</a>.</li>
                    <li>Si no hay firma cargada, los recibos salen con la línea de firma en blanco
                        para firmarlos a mano.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var nombre = document.getElementById('escuela_nombre');
    var previa = document.getElementById('vistaPreviaNombre');
    var sufijo = <?php echo json_encode(
        $sedes ? ', sede ' . $sedes[0]['sede_nombre'] : '', JSON_UNESCAPED_UNICODE); ?>;

    if (nombre && previa) {
        nombre.addEventListener('input', function () {
            previa.textContent = (this.value || '—') + sufijo;
        });
    }

    /* Logo y firma comparten comportamiento: se marcan para quitar y la
       baja se confirma al guardar el formulario. */
    document.querySelectorAll('[data-quitar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            document.getElementById('quitar_' + this.dataset.quitar).value = '1';
            document.getElementById(this.dataset.previa).style.opacity = '.3';
            this.disabled = true;
            this.textContent = 'Se quitará al guardar';
        });
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
