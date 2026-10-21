<?php
/*
| Configuración de facturación electrónica (SRI).
|
| Vive en Core porque define la identidad tributaria con la que se emiten
| comprobantes: RUC del emisor, ambiente y certificado de firma. Sólo el
| superadministrador puede entrar, con independencia de los permisos que
| tenga su rol. Emitir facturas es otra cosa y se concede por rol sobre
| «Facturas emitidas» en Basketball.
|
| El formulario guarda contra el módulo Basketball, que es donde está el
| motor del SRI (firma, webservices, RIDE). Core no duplica esa lógica:
| sólo presenta los datos y recoge los cambios.
*/

use admin\controllers\coreController;

if (!es_superadministrador()) {
    http_response_code(403);
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}

$insCore = new coreController();

$cfg        = $insCore->configuracionSri();
$formasPago = $insCore->formasPagoSri();

$tituloVista = 'Facturación electrónica';
$vistaActual = 'facturacionConfigSri';

/* El motor del SRI responde en Basketball. */
$ajaxSri = DS_FUTBOL_URL . 'app/ajax/facturasAjax.php';

$h   = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$sel = static fn($actual, $valor) => ((string)$actual === (string)$valor) ? 'selected' : '';

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="aviso-superadmin mb-3">
    <i class="fas fa-shield-alt fa-lg mt-1"></i>
    <div>
        <strong>Sólo el superadministrador.</strong><br>
        Aquí se define en nombre de quién se emiten comprobantes con validez tributaria.
        Para que alguien pueda <em>emitir</em> facturas basta con darle permiso sobre
        «Facturas emitidas» en <a href="<?php echo APP_URL; ?>permisoRol/">Permisos</a>.
    </div>
</div>

<div class="row">
    <div class="col-lg-8">

        <form class="FormularioAjax" method="POST" autocomplete="off"
              action="<?php echo $ajaxSri; ?>" data-recargar-directo>
            <input type="hidden" name="modulo_facturas" value="GUARDAR_CONFIG_SRI">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-building me-2"></i>Emisor y comprobantes</h3>
                </div>

                <div class="card-body">
                    <div class="row g-2">
                        <div class="mb-3 col-md-4">
                            <label for="ambiente">Ambiente <span class="text-danger">*</span></label>
                            <select class="form-select" id="ambiente" name="ambiente" required>
                                <option value="1" <?php echo $sel($cfg['ambiente'], '1'); ?>>Pruebas</option>
                                <option value="2" <?php echo $sel($cfg['ambiente'], '2'); ?>>Producción</option>
                            </select>
                            <small class="text-muted">En Producción los comprobantes son reales.</small>
                        </div>

                        <div class="mb-3 col-md-4">
                            <label for="ruc">RUC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ruc" name="ruc" maxlength="13" required
                                   value="<?php echo $h($cfg['ruc']); ?>">
                            <small class="text-muted">13 dígitos; debe coincidir con el del certificado.</small>
                        </div>

                        <div class="mb-3 col-md-4">
                            <label for="obligado_contabilidad">Obligado a llevar contabilidad</label>
                            <select class="form-select" id="obligado_contabilidad" name="obligado_contabilidad">
                                <option value="NO" <?php echo $sel($cfg['obligado_contabilidad'], 'NO'); ?>>No</option>
                                <option value="SI" <?php echo $sel($cfg['obligado_contabilidad'], 'SI'); ?>>Sí</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="razon_social">Razón social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="razon_social" name="razon_social" required
                               value="<?php echo $h($cfg['razon_social']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="nombre_comercial">Nombre comercial</label>
                        <input type="text" class="form-control" id="nombre_comercial" name="nombre_comercial"
                               value="<?php echo $h($cfg['nombre_comercial']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="direccion_matriz">Dirección matriz <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="direccion_matriz" name="direccion_matriz" required
                               value="<?php echo $h($cfg['direccion_matriz']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="direccion_establecimiento">Dirección del establecimiento</label>
                        <input type="text" class="form-control" id="direccion_establecimiento"
                               name="direccion_establecimiento"
                               value="<?php echo $h($cfg['direccion_establecimiento']); ?>">
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-4">
                            <label for="codigo_establecimiento">Establecimiento</label>
                            <input type="text" class="form-control" id="codigo_establecimiento"
                                   name="codigo_establecimiento" maxlength="3"
                                   value="<?php echo $h($cfg['codigo_establecimiento']); ?>">
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="punto_emision">Punto de emisión</label>
                            <input type="text" class="form-control" id="punto_emision" name="punto_emision" maxlength="3"
                                   value="<?php echo $h($cfg['punto_emision']); ?>">
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="secuencial_inicio">Siguiente secuencial</label>
                            <input type="number" min="1" max="999999999" step="1" class="form-control"
                                   id="secuencial_inicio" name="secuencial_inicio"
                                   value="<?php echo (int)$cfg['secuencial_inicio']; ?>">
                            <small class="text-muted">Número que llevará la próxima factura.</small>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-6">
                            <label for="iva_tarifa_default">IVA por defecto</label>
                            <?php /* Las tarifas admitidas las valida el módulo de facturación:
                                     se envían como entero, no como decimal. */
                                  $ivaActual = (string)(int)round((float)$cfg['iva_tarifa_default']); ?>
                            <select class="form-select" id="iva_tarifa_default" name="iva_tarifa_default" required>
                                <?php foreach (['0' => '0 %', '12' => '12 %', '14' => '14 %', '15' => '15 %'] as $v => $t): ?>
                                    <option value="<?php echo $v; ?>" <?php echo $sel($ivaActual, $v); ?>>
                                        <?php echo $t; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="forma_pago_default">Forma de pago por defecto</label>
                            <select class="form-select" id="forma_pago_default" name="forma_pago_default" required>
                                <?php foreach ($formasPago as $codigo => $texto): ?>
                                    <option value="<?php echo $codigo; ?>"
                                        <?php echo $sel($cfg['forma_pago_default'], $codigo); ?>>
                                        <?php echo $codigo . ' · ' . $h($texto); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-4">
                            <label for="contribuyente_especial">Contribuyente especial</label>
                            <input type="text" class="form-control" id="contribuyente_especial"
                                   name="contribuyente_especial" maxlength="13"
                                   value="<?php echo $h($cfg['contribuyente_especial']); ?>">
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="agente_retencion">Agente de retención</label>
                            <input type="text" class="form-control" id="agente_retencion" name="agente_retencion"
                                   maxlength="8" value="<?php echo $h($cfg['agente_retencion']); ?>">
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="contribuyente_rimpe">Régimen RIMPE</label>
                            <input type="text" class="form-control" id="contribuyente_rimpe" name="contribuyente_rimpe"
                                   value="<?php echo $h($cfg['contribuyente_rimpe']); ?>">
                        </div>
                    </div>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'panel/'); ?>
            </div>
        </form>
    </div>

    <div class="col-lg-4">

        <!-- Estado del certificado: lo resuelve Basketball, que es quien
             sabe descifrar la clave y leer el .p12 -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-certificate me-2"></i>Certificado de firma</h3>
            </div>
            <div class="card-body" id="estadoCertificado">
                <p class="text-muted mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Consultando…</p>
            </div>
        </div>

        <form class="FormularioAjax" method="POST" enctype="multipart/form-data"
              action="<?php echo $ajaxSri; ?>" autocomplete="off">
            <input type="hidden" name="modulo_facturas" value="SUBIR_CERTIFICADO_SRI">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-upload me-2"></i>Cargar certificado</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="certificado">Archivo .p12 / .pfx</label>
                        <input type="file" class="form-control" id="certificado" name="certificado"
                               accept=".p12,.pfx" required>
                    </div>
                    <div class="mb-3 mb-0">
                        <label for="clave_certificado">Clave del certificado</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="clave_certificado"
                                   name="clave_certificado" autocomplete="new-password" required>
                                                        <button type="button" class="btn btn-outline-secondary" id="btnVerClave"
                                    title="Ver clave"><i class="fas fa-eye"></i></button>
                        
                        </div>
                        <small class="text-muted">
                            Se guarda cifrada fuera del repositorio. El archivo no se descarga nunca.
                        </small>
                    </div>
                </div>
                <div class="card-footer ds-acciones">
                    <?php echo ds_boton('subir', 'Cargar', ['estilo' => 'primary', 'type' => 'submit']); ?>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-vial me-2"></i>Comprobaciones</h3>
            </div>
            <div class="card-body">
                <form class="FormularioAjax" method="POST" action="<?php echo $ajaxSri; ?>">
                    <input type="hidden" name="modulo_facturas" value="PROBAR_CERTIFICADO_SRI">
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-key me-1"></i> Probar certificado y clave
                    </button>
                </form>
                <form class="FormularioAjax mt-2" method="POST" action="<?php echo $ajaxSri; ?>">
                    <input type="hidden" name="modulo_facturas" value="PROBAR_CONEXION_SRI">
                    <button type="submit" class="btn btn-outline-secondary w-100 mb-0">
                        <i class="fas fa-network-wired me-1"></i> Probar conexión con el SRI
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var ver   = document.getElementById('btnVerClave');
    var clave = document.getElementById('clave_certificado');

    if (ver && clave) {
        ver.addEventListener('click', function () {
            var oculta = clave.type === 'password';
            clave.type = oculta ? 'text' : 'password';
            this.innerHTML = oculta ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        });
    }

    /* El estado del certificado lo sirve Basketball. */
    var caja = document.getElementById('estadoCertificado');

    var etiquetas = {
        VALIDO:              ['success', 'Válido'],
        CADUCADO:            ['danger',  'Caducado'],
        CLAVE_INVALIDA:      ['danger',  'La clave no abre el certificado'],
        RUC_NO_COINCIDE:     ['danger',  'El RUC del certificado no coincide con el del emisor'],
        SIN_CLAVE:           ['warning', 'Falta la clave'],
        NO_CONFIGURADO:      ['warning', 'Sin certificado cargado'],
        NO_LEIBLE:           ['warning', 'No se pudo leer'],
        OPENSSL_NO_DISPONIBLE: ['warning', 'OpenSSL no disponible en el servidor'],
        NO_AUTORIZADO:       ['danger',  'Sin autorización para consultarlo']
    };

    function fila(rotulo, valor) {
        if (!valor && valor !== 0) { return ''; }
        return '<dt class="col-5 text-muted font-weight-normal">' + rotulo + '</dt>' +
               '<dd class="col-7">' + valor + '</dd>';
    }

    function escapar(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    fetch('<?php echo $ajaxSri; ?>?modulo_facturas=INFO_CERTIFICADO_SRI', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (info) {
            var e = etiquetas[info.estado] || ['warning', info.estado || 'Desconocido'];
            /* text-bg-, no badge-: en Bootstrap 5 el color del distintivo
               cambió de prefijo. Esta clase se arma en JavaScript, así que
               ningún buscador sobre el HTML la encontraba y el distintivo
               salía sin color —blanco sobre blanco, ilegible— sin que nada
               fallara. Lo destapó medir el contraste, no mirar la pantalla. */
            var html = '<span class="badge text-bg-' + e[0] + ' mb-3">' + escapar(e[1]) + '</span>';

            html += '<dl class="row mb-0" style="font-size:.9rem;">';
            html += fila('Archivo',  escapar(info.archivo));
            html += fila('Titular',  escapar(info.titular));
            html += fila('Emisor',   escapar(info.emisor));
            html += fila('RUC',      escapar(info.ruc));
            html += fila('Vigencia', info.valido_hasta ? 'hasta ' + escapar(info.valido_hasta) : '');
            if (info.dias_restantes !== null && info.dias_restantes !== undefined) {
                html += fila('Días restantes',
                    '<strong class="text-' + (info.dias_restantes < 30 ? 'danger' : 'muted') + '">' +
                    escapar(info.dias_restantes) + '</strong>');
            }
            html += '</dl>';

            caja.innerHTML = html;
        })
        .catch(function () {
            caja.innerHTML = '<p class="text-danger mb-0">' +
                '<i class="fas fa-exclamation-triangle me-1"></i>' +
                'No se pudo consultar el certificado.</p>';
        });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
