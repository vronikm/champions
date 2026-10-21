<?php
/*
| Catálogo de equipos.
|
| Un equipo vive aquí y persiste entre temporadas: es lo que permite el
| histórico. Inscribirlo a una categoría concreta es otra cosa y se hace
| desde el panel de la categoría.
*/

use league\controllers\competenciaController;

$insLeague = new competenciaController();

$tituloVista = 'Equipos';
$vistaActual = 'equipoList';

$equipos    = $insLeague->equipos();
$escudosUrl = competenciaController::escudosUrl();

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users me-2"></i>Equipos</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Contacto</th>
                                <th class="text-end">Inscripciones</th>
                                <th class="ds-tabla-acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$equipos): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">
                                Todavía no hay equipos.
                            </td></tr>
                        <?php else: foreach ($equipos as $q): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center" style="gap:.6rem;">
                                        <?php if ($q['equipo_escudo']): ?>
                                            <img src="<?php echo $escudosUrl . rawurlencode($q['equipo_escudo']); ?>"
                                                 alt="" style="width:32px;height:32px;object-fit:contain;
                                                              border:1px solid var(--ds-border,#dee2e6);border-radius:4px;">
                                        <?php else: ?>
                                            <span class="text-muted d-inline-flex align-items-center justify-content-center"
                                                  style="width:32px;height:32px;border:1px dashed #ced4da;border-radius:4px;
                                                         font-size:.7rem;" title="Sin escudo">
                                                <i class="fas fa-shield-alt"></i>
                                            </span>
                                        <?php endif; ?>
                                        <span>
                                            <strong><?php echo $h($q['equipo_nombre']); ?></strong>
                                            <?php if ($q['equipo_corto'] !== ''): ?>
                                                <span class="badge text-bg-light border ms-1"><?php echo $h($q['equipo_corto']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-muted">
                                    <?php echo $h($q['equipo_contacto']); ?>
                                    <?php if ($q['equipo_telefono'] !== ''): ?>
                                        <br><small><?php echo $h($q['equipo_telefono']); ?></small>
                                    <?php endif; ?>
                                    <?php /* Que se pueda facturar o no se ve
                                             desde el listado: descubrirlo al
                                             emitir es descubrirlo tarde. */ ?>
                                    <?php $facturable = $q['equipo_identificacion'] !== ''
                                                     && $q['equipo_razonsocial'] !== ''
                                                     && $q['equipo_direccion'] !== ''; ?>
                                    <br><small class="<?php echo $facturable ? 'text-success' : 'text-muted'; ?>"
                                               title="<?php echo $facturable
                                                   ? 'Tiene los datos que exige un comprobante'
                                                   : 'Faltan datos tributarios: no se le puede emitir'; ?>">
                                        <i class="fas fa-<?php echo $facturable ? 'file-invoice' : 'ban'; ?> me-1"></i>
                                        <?php echo $facturable
                                            ? $h($q['equipo_identificacion'])
                                            : 'sin datos para facturar'; ?>
                                    </small>
                                </td>
                                <td class="text-end"><?php echo (int)$q['inscripciones']; ?></td>
                                <td class="ds-tabla-acciones">
                                    <?php if (puede_editar('equipoList')): ?>
                                    <button type="button" class="btn btn-sm btn-actualizar js-editar" title="Editar"
                                            data-fila='<?php echo $h(json_encode([
                                                'equipo_id'       => (int)$q['equipo_id'],
                                                'equipo_nombre'   => $q['equipo_nombre'],
                                                'equipo_corto'    => $q['equipo_corto'],
                                                'equipo_contacto' => $q['equipo_contacto'],
                                                'equipo_telefono' => $q['equipo_telefono'],
                                                'equipo_email'    => $q['equipo_email'],
                                                'equipo_idtipo'         => $q['equipo_idtipo'],
                                                'equipo_identificacion' => $q['equipo_identificacion'],
                                                'equipo_razonsocial'    => $q['equipo_razonsocial'],
                                                'equipo_direccion'      => $q['equipo_direccion'],
                                            ], JSON_UNESCAPED_UNICODE)); ?>'>
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted" style="font-size:.85rem;">
                Un equipo persiste entre temporadas. Inscribirlo a una categoría se hace
                desde el panel de esa categoría, no aquí.
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-3">
        <?php if (puede_crear('equipoList') || puede_editar('equipoList')): ?>
        <?php /* enctype es imprescindible: sin el, el navegador envia
                 solo el nombre del archivo y $_FILES llega vacio. */ ?>
        <form class="FormularioAjax" method="POST" autocomplete="off"
              enctype="multipart/form-data"
              action="<?php echo APP_URL; ?>ajax/leagueAjax.php" id="formFila">
            <input type="hidden" name="modulo_league" value="guardarEquipo">
            <input type="hidden" name="equipo_id" id="equipo_id" value="0">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="tituloForm">
                        <i class="fas fa-plus me-2"></i>Nuevo equipo
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="mb-3 col-8">
                            <label for="equipo_nombre">Nombre</label>
                            <input type="text" name="equipo_nombre" id="equipo_nombre"
                                   class="form-control" maxlength="120" required>
                        </div>
                        <div class="mb-3 col-4">
                            <label for="equipo_corto">Siglas</label>
                            <input type="text" name="equipo_corto" id="equipo_corto"
                                   class="form-control" maxlength="20" placeholder="BCC">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="equipo_contacto">Delegado o responsable</label>
                        <input type="text" name="equipo_contacto" id="equipo_contacto"
                               class="form-control" maxlength="150">
                    </div>
                    <div class="row g-2 mb-0">
                        <div class="mb-3 col-5 mb-0">
                            <label for="equipo_telefono">Teléfono</label>
                            <input type="text" name="equipo_telefono" id="equipo_telefono"
                                   class="form-control" maxlength="30">
                        </div>
                        <div class="mb-3 col-7 mb-0">
                            <label for="equipo_email">Correo</label>
                            <input type="email" name="equipo_email" id="equipo_email"
                                   class="form-control" maxlength="150">
                        </div>
                    </div>

                    <hr>
                    <?php /* Datos del comprobante. Se piden aquí, una vez,
                             y no en cada emisión: el club factura siempre
                             al mismo RUC, y retecleárlo cada vez es la
                             forma más eficaz de que el SRI devuelva el
                             comprobante por un dígito cambiado. */ ?>
                    <p class="text-muted mb-2" style="font-size:.85rem;">
                        <i class="fas fa-file-invoice me-1"></i>
                        <strong>Datos para facturar.</strong> Sólo hacen falta si se le van a
                        emitir comprobantes. El número se valida al guardar.
                    </p>
                    <div class="row g-2">
                        <div class="mb-3 col-5">
                            <label for="equipo_idtipo">Identificación</label>
                            <select name="equipo_idtipo" id="equipo_idtipo" class="form-select">
                                <option value="04">RUC</option>
                                <option value="05">Cédula</option>
                                <option value="06">Pasaporte</option>
                                <option value="07">Consumidor final</option>
                            </select>
                        </div>
                        <div class="mb-3 col-7">
                            <label for="equipo_identificacion">Número</label>
                            <input type="text" name="equipo_identificacion" id="equipo_identificacion"
                                   class="form-control" maxlength="20" placeholder="0705727287001">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="equipo_razonsocial">Razón social</label>
                        <input type="text" name="equipo_razonsocial" id="equipo_razonsocial"
                               class="form-control" maxlength="300"
                               placeholder="Como consta en el RUC">
                    </div>
                    <div class="mb-3">
                        <label for="equipo_direccion">Dirección</label>
                        <input type="text" name="equipo_direccion" id="equipo_direccion"
                               class="form-control" maxlength="300">
                    </div>

                    <hr>
                    <div class="mb-3 mb-0">
                        <label for="equipo_escudo">Escudo</label>
                        <div class="d-flex align-items-center" style="gap:.9rem;">
                            <img id="vistaEscudo" src="" alt=""
                                 style="width:64px;height:64px;object-fit:contain;display:none;
                                        border:1px solid #dee2e6;border-radius:6px;">
                            <div style="flex:1;min-width:0;">
                                <input type="file" name="equipo_escudo" id="equipo_escudo"
                                       class="form-control" accept="image/jpeg,image/png,image/webp">
                                <small class="form-text text-muted">
                                    JPG, PNG o WEBP, hasta 2 MB. Se guarda como PNG de 600 px
                                    sobre fondo blanco, para que sirva igual en pantalla que
                                    impreso en un acta.
                                </small>
                                <label class="mt-1 mb-0 text-muted" style="font-size:.85rem;">
                                    <input type="checkbox" name="quitar_escudo" value="1" id="quitar_escudo">
                                    Quitar el escudo actual
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo ds_acciones_form('', ['limpiar' => true]); ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . "/inc/editor-fila.php"; ?>
<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
