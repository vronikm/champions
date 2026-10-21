<?php
/* Alta y edición de una sede del ecosistema. */

use admin\controllers\coreController;

$insCore = new coreController();

$id     = (int)($_GET['id'] ?? 0);
$sede   = $id > 0 ? $insCore->sede($id) : null;
$esAlta = ($sede === null);

if ($esAlta && !puede_crear('sedeList'))   { require_once __DIR__ . "/accesoDenegado-view.php"; exit(); }
if (!$esAlta && !puede_editar('sedeList')) { require_once __DIR__ . "/accesoDenegado-view.php"; exit(); }

$tituloVista = $esAlta ? 'Nueva sede' : 'Editar sede';
$vistaActual = 'sedeList';

$escuelas = $insCore->escuelas();
$tipos    = $insCore->valoresPorNombre('sede_tipoingreso');

/* Logo y firma: se muestra el que la sede va a usar realmente. Puede ser
   el suyo o, si no tiene, el heredado de la organización. */
$logoPropio      = trim((string)($sede['sede_foto'] ?? ''));
$tieneLogoPropio = $logoPropio !== '' && is_file(ds_marca_dir() . $logoPropio);
$urlLogo         = ds_logo_url($id);

$firmaPropia      = trim((string)($sede['sede_firma'] ?? ''));
$tieneFirmaPropia = $firmaPropia !== '' && is_file(ds_marca_dir() . $firmaPropia);
$urlFirma         = ds_firma_url($id);

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo $tituloVista; ?></h3></div>

            <form class="FormularioAjax" method="POST" enctype="multipart/form-data"
                  action="<?php echo APP_URL; ?>ajax/coreAjax.php">
                <input type="hidden" name="modulo_core" value="guardarSede">
                <input type="hidden" name="sede_id" value="<?php echo $id; ?>">
                <input type="hidden" name="quitar_logo"  id="quitar_logo"  value="0">
                <input type="hidden" name="quitar_firma" id="quitar_firma" value="0">

                <div class="card-body">
                    <div class="row g-2">
                        <div class="mb-3 col-md-7">
                            <label for="sede_nombre">Nombre de la sede <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sede_nombre" name="sede_nombre"
                                   maxlength="100" required
                                   value="<?php echo htmlspecialchars((string)($sede['sede_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3 col-md-5">
                            <label for="sede_escuelaid">Organización <span class="text-danger">*</span></label>
                            <select class="form-select" id="sede_escuelaid" name="sede_escuelaid" required>
                                <option value="">Seleccione…</option>
                                <?php foreach ($escuelas as $e): ?>
                                    <option value="<?php echo (int)$e['escuela_id']; ?>"
                                        <?php echo (int)($sede['sede_escuelaid'] ?? 0) === (int)$e['escuela_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($e['escuela_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- El campo que decide qué módulos operan sobre la sede -->
                    <div class="mb-3">
                        <label for="sede_tipoingreso">Tipo de sede <span class="text-danger">*</span></label>
                        <select class="form-select" id="sede_tipoingreso" name="sede_tipoingreso" required>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['catalogo_valor'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo ($sede['sede_tipoingreso'] ?? 'STF') === $t['catalogo_valor'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['catalogo_descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">
                            <strong>Formativa</strong>: sólo escuela · <strong>Alquiler</strong>: sólo Arena ·
                            <strong>Formativa y Alquiler</strong>: ambos módulos.
                        </small>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-8">
                            <label for="sede_direccion">Dirección</label>
                            <input type="text" class="form-control" id="sede_direccion" name="sede_direccion" maxlength="200"
                                   value="<?php echo htmlspecialchars((string)($sede['sede_direccion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3 col-md-4">
                            <label for="sede_telefono">Teléfono</label>
                            <input type="text" class="form-control" id="sede_telefono" name="sede_telefono" maxlength="50"
                                   value="<?php echo htmlspecialchars((string)($sede['sede_telefono'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-6">
                            <label for="sede_email">Correo</label>
                            <input type="email" class="form-control" id="sede_email" name="sede_email" maxlength="50"
                                   value="<?php echo htmlspecialchars((string)($sede['sede_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3 col-md-3">
                            <label for="sede_inscripcion">Inscripción</label>
                            <input type="number" step="0.01" min="0" class="form-control"
                                   id="sede_inscripcion" name="sede_inscripcion"
                                   value="<?php echo htmlspecialchars((string)($sede['sede_inscripcion'] ?? '0.00'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3 col-md-3">
                            <label for="sede_pension">Pensión</label>
                            <input type="number" step="0.01" min="0" class="form-control"
                                   id="sede_pension" name="sede_pension"
                                   value="<?php echo htmlspecialchars((string)($sede['sede_pension'] ?? '0.00'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <p class="text-muted small">
                        Inscripción y pensión son los valores por defecto que consume el módulo
                        de la escuela; Arena usa las tarifas por hora de cada instalación.
                    </p>

                    <hr>

                    <!-- Logo propio de la sede; si no tiene, hereda el de la organización -->
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 text-center mb-3">
                            <?php if ($urlLogo !== ''): ?>
                                <img src="<?php echo $urlLogo; ?>" alt="Logo" id="vistaPreviaLogo"
                                     style="max-height:110px;max-width:100%;border:1px solid var(--core-borde);
                                            border-radius:var(--ds-radius-sm);padding:8px;background:#fff;">
                            <?php else: ?>
                                <div id="vistaPreviaLogo" class="text-muted"
                                     style="height:110px;display:flex;align-items:center;justify-content:center;
                                            border:1px dashed var(--core-borde);border-radius:var(--ds-radius-sm);">
                                    <span><i class="fas fa-image fa-2x"></i><br><small>Sin logo</small></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-9">
                            <div class="mb-3 mb-1">
                                <label for="sede_foto">Logo de la sede</label>
                                <input type="file" class="form-control" id="sede_foto" name="sede_foto"
                                       accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted">
                                    Opcional. Si no se carga ninguno, la sede usa el logo de la organización.
                                </small>
                            </div>

                            <?php if ($tieneLogoPropio): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                                        data-quitar="logo" data-previa="vistaPreviaLogo">
                                    <i class="fas fa-times me-1"></i> Quitar logo propio
                                </button>
                            <?php else: ?>
                                <p class="text-muted small mb-0 mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Esta sede está usando el logo de la organización.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr>

                    <!-- Firma propia de la sede; si no tiene, hereda la de la organización -->
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 text-center mb-3">
                            <?php if ($urlFirma !== ''): ?>
                                <img src="<?php echo $urlFirma; ?>" alt="Firma autorizada" id="vistaPreviaFirma"
                                     style="max-height:110px;max-width:100%;border:1px solid var(--core-borde);
                                            border-radius:var(--ds-radius-sm);padding:8px;background:#fff;">
                            <?php else: ?>
                                <div id="vistaPreviaFirma" class="text-muted"
                                     style="height:110px;display:flex;align-items:center;justify-content:center;
                                            border:1px dashed var(--core-borde);border-radius:var(--ds-radius-sm);">
                                    <span><i class="fas fa-signature fa-2x"></i><br><small>Sin firma</small></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-9">
                            <div class="mb-3 mb-1">
                                <label for="sede_firma">Firma autorizada de la sede</label>
                                <input type="file" class="form-control" id="sede_firma" name="sede_firma"
                                       accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted">
                                    Opcional. Útil cuando cada sede firma sus propios recibos.
                                </small>
                            </div>

                            <?php if ($tieneFirmaPropia): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                                        data-quitar="firma" data-previa="vistaPreviaFirma">
                                    <i class="fas fa-times me-1"></i> Quitar firma propia
                                </button>
                            <?php else: ?>
                                <p class="text-muted small mb-0 mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Esta sede está usando la firma de la organización.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'sedeList/'); ?>
            </form>

            <script>
            (function () {
                /* Logo y firma comparten comportamiento: se marcan para
                   quitar y la baja se confirma al guardar. */
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
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
