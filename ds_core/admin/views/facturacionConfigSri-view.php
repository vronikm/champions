<?php
/*
| Configuración de facturación electrónica (SRI).
|
| Vive en Core porque define la identidad tributaria con la que se emiten
| comprobantes: RUC del emisor, ambiente y certificado de firma. Sólo el
| superadministrador puede entrar, con independencia de los permisos que
| tenga su rol. Emitir facturas es otra cosa y se concede por rol sobre
| «Facturas emitidas» en Fútbol.
|
| El formulario guarda contra el módulo Fútbol, que es donde está el
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
$ambientes  = sri_ambientes();               // ds_core/inc/sri.php
$ambientesSri = sri_ambientes_por_defecto();

$tituloVista = 'Facturación electrónica';
$vistaActual = 'facturacionConfigSri';

/* El motor del SRI responde en Fútbol. */
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

<?php
/* Estado del servicio: el interruptor se guarda en la fila de configuración,
   así que sólo funciona cuando ya hay emisor guardado. */
$servicioActivo = (int)$cfg['servicio_activo'] === 1;
$hayConfig      = !empty($cfg['id']);
?>
<div class="card ds-servicio <?php echo $servicioActivo ? 'ds-servicio--activo' : 'ds-servicio--mantenimiento'; ?>" id="estadoServicio">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-power-off me-2"></i>Estado del servicio de facturación electrónica</h3>
        <span class="badge <?php echo $servicioActivo ? 'text-bg-success' : 'text-bg-warning'; ?> ms-auto ds-servicio__estado">
            <i class="fas <?php echo $servicioActivo ? 'fa-check-circle' : 'fa-tools'; ?> me-1"></i><?php echo $servicioActivo ? 'En servicio' : 'En mantenimiento'; ?>
        </span>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            Al bajar el servicio se detiene la generación y el envío de facturas al SRI. Las pantallas de
            facturación mostrarán un aviso de mantenimiento y esta pantalla de configuración seguirá disponible
            para volver a levantarlo. <strong>El registro de pagos no se ve afectado.</strong>
        </p>
        <form class="FormularioAjax" method="POST" autocomplete="off" action="<?php echo $ajaxSri; ?>"
              data-confirmar="<?php echo $servicioActivo
                  ? 'Nadie podrá generar ni enviar facturas al SRI hasta que vuelva a levantar el servicio.'
                  : 'La facturación electrónica volverá a estar disponible para todos.'; ?>">
            <input type="hidden" name="modulo_facturas" value="CAMBIAR_ESTADO_SERVICIO_SRI">
            <input type="hidden" name="servicio_activo" value="<?php echo $servicioActivo ? '0' : '1'; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-lg">
                    <label for="mantenimiento_mensaje">Mensaje para los usuarios <small class="text-muted">(opcional)</small></label>
                    <input type="text" class="form-control" id="mantenimiento_mensaje" name="mantenimiento_mensaje" maxlength="300"
                           placeholder="El servicio de facturación electrónica está temporalmente fuera de servicio por mantenimiento."
                           value="<?php echo $h($cfg['mantenimiento_mensaje']); ?>">
                    <small class="text-muted">Vacío = el mensaje por defecto. Útil para decir hasta cuándo dura la interrupción.</small>
                </div>
                <div class="col-lg-auto ds-servicio__accion">
                    <?php if ($servicioActivo): ?>
                        <button type="submit" class="btn btn-warning w-100"<?php echo $hayConfig ? '' : ' disabled'; ?>>
                            <i class="fas fa-tools me-1"></i> Bajar el servicio por mantenimiento
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-play me-1"></i> Levantar el servicio
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$hayConfig): ?>
                <p class="small text-muted mt-2 mb-0"><i class="fas fa-info-circle me-1"></i>Guarde primero los datos del emisor: el estado del servicio se almacena con esa configuración.</p>
            <?php endif; ?>
        </form>
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

                    <div class="row g-2">
                        <div class="mb-3 col-md-8">
                            <label for="direccion_establecimiento">Dirección del establecimiento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="direccion_establecimiento"
                                   name="direccion_establecimiento" required
                                   value="<?php echo $h($cfg['direccion_establecimiento']); ?>">
                        </div>
                        <div class="mb-3 col-md-4">
                            <label for="telefono">Teléfono de la factura</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="50"
                                   value="<?php echo $h($cfg['telefono']); ?>">
                            <small class="text-muted">Sale en el RIDE.</small>
                        </div>
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

                    <!-- Proveedor del sistema de facturación -->
                    <h4 class="ds-config__seccion"><i class="fas fa-handshake me-2" aria-hidden="true"></i>Proveedor del sistema de facturación</h4>
                    <p class="text-muted small">
                        La resolución <strong>NAC-DGERCGC26-00000027</strong> (art. 5) obliga a quienes emiten con un
                        sistema provisto por un tercero a incluir el <strong>RUC de ese proveedor</strong> en la
                        información adicional del comprobante. Si se deja vacío, el XML sale como hasta ahora.
                    </p>
                    <div class="row g-2">
                        <div class="mb-3 col-md-4">
                            <label for="ruc_proveedor">RUC del proveedor</label>
                            <input type="text" class="form-control" id="ruc_proveedor" name="ruc_proveedor"
                                   maxlength="13" inputmode="numeric" pattern="\d{13}" title="13 dígitos"
                                   value="<?php echo $h($cfg['ruc_proveedor']); ?>">
                            <small class="text-muted">13 dígitos. Vacío = no se agrega.</small>
                        </div>
                        <div class="mb-3 col-md-8">
                            <label for="etiqueta_ruc_proveedor">Nombre del campo en el XML</label>
                            <input type="text" class="form-control" id="etiqueta_ruc_proveedor" name="etiqueta_ruc_proveedor"
                                   maxlength="300" placeholder="RUC Proveedor"
                                   value="<?php echo $h($cfg['etiqueta_ruc_proveedor'] ?: 'RUC Proveedor'); ?>">
                            <small class="text-muted">
                                Es el atributo <code>nombre</code> de <code>&lt;campoAdicional&gt;</code>. Es configurable porque el
                                literal exacto lo fija la Ficha Técnica de Comprobantes Electrónicos vigente: verifíquelo
                                antes de emitir en producción.
                            </small>
                        </div>
                    </div>
                    <div class="ds-config__xml mb-3" id="xmlProveedor"<?php echo trim((string)$cfg['ruc_proveedor']) === '' ? ' hidden' : ''; ?>>
                        <strong class="small">Así saldrá en el XML:</strong>
                        <pre class="mb-0 mt-1"><code>&lt;infoAdicional&gt;
  &lt;campoAdicional nombre="<span data-xml="etiqueta"><?php echo $h($cfg['etiqueta_ruc_proveedor'] ?: 'RUC Proveedor'); ?></span>"&gt;<span data-xml="ruc"><?php echo $h($cfg['ruc_proveedor']); ?></span>&lt;/campoAdicional&gt;
&lt;/infoAdicional&gt;</code></pre>
                    </div>

                    <!-- Correo de facturación -->
                    <h4 class="ds-config__seccion"><i class="fas fa-envelope me-2" aria-hidden="true"></i>Correo de facturación</h4>
                    <p class="text-muted small">
                        Remitente de las facturas que se envían al cliente. Con «Correo del servidor» salen por
                        <code>mail()</code>, como hasta ahora; con «SMTP autenticado», por la cuenta que indique
                        (Gmail, Outlook, el correo del dominio…), que suele llegar mejor a la bandeja de entrada.
                    </p>
                    <div class="row g-2">
                        <div class="mb-3 col-md-6">
                            <label for="correo_from">Correo remitente</label>
                            <input type="email" class="form-control" id="correo_from" name="correo_from" maxlength="150"
                                   placeholder="facturacion@dominio.com" value="<?php echo $h($cfg['correo_from']); ?>">
                            <small class="text-muted">Vacío = el correo de la organización.</small>
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="correo_from_nombre">Nombre remitente</label>
                            <input type="text" class="form-control" id="correo_from_nombre" name="correo_from_nombre" maxlength="150"
                                   placeholder="<?php echo $h(ds_nombre_organizacion()); ?>" value="<?php echo $h($cfg['correo_from_nombre']); ?>">
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="smtp_activo">Método de envío</label>
                            <select class="form-select" id="smtp_activo" name="smtp_activo">
                                <option value="0" <?php echo $sel((int)$cfg['smtp_activo'], 0); ?>>Correo del servidor (mail)</option>
                                <option value="1" <?php echo $sel((int)$cfg['smtp_activo'], 1); ?>>SMTP autenticado</option>
                            </select>
                        </div>
                    </div>
                    <fieldset class="row g-2 ds-config__smtp" id="camposSmtp"<?php echo (int)$cfg['smtp_activo'] === 1 ? '' : ' hidden'; ?>>
                        <legend class="visually-hidden">Servidor SMTP</legend>
                        <div class="mb-3 col-md-6">
                            <label for="smtp_host">Servidor SMTP</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" maxlength="150"
                                   placeholder="smtp.gmail.com" value="<?php echo $h($cfg['smtp_host']); ?>">
                        </div>
                        <div class="mb-3 col-6 col-md-3">
                            <label for="smtp_port">Puerto</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" min="1" max="65535"
                                   inputmode="numeric" value="<?php echo (int)$cfg['smtp_port']; ?>">
                        </div>
                        <div class="mb-3 col-6 col-md-3">
                            <label for="smtp_seguridad">Seguridad</label>
                            <select class="form-select" id="smtp_seguridad" name="smtp_seguridad">
                                <option value="tls" <?php echo $sel($cfg['smtp_seguridad'], 'tls'); ?>>TLS (587)</option>
                                <option value="ssl" <?php echo $sel($cfg['smtp_seguridad'], 'ssl'); ?>>SSL (465)</option>
                                <option value="ninguna" <?php echo $sel($cfg['smtp_seguridad'], 'ninguna'); ?>>Ninguna</option>
                            </select>
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="smtp_usuario">Usuario SMTP</label>
                            <input type="text" class="form-control" id="smtp_usuario" name="smtp_usuario" maxlength="150"
                                   autocomplete="off" value="<?php echo $h($cfg['smtp_usuario']); ?>">
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="smtp_clave">Clave SMTP</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="smtp_clave" name="smtp_clave" autocomplete="new-password"
                                       placeholder="<?php echo trim((string)$cfg['smtp_clave_cifrada']) !== '' ? 'Guardada; déjela vacía para conservarla' : 'Clave o contraseña de aplicación'; ?>">
                                <button type="button" class="btn btn-outline-secondary" data-ver-clave="smtp_clave"
                                        title="Ver clave" aria-label="Ver clave"><i class="fas fa-eye"></i></button>
                            </div>
                            <small class="text-muted">Se guarda cifrada; nunca se muestra.</small>
                        </div>
                    </fieldset>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'panel/'); ?>
            </div>
        </form>

        <!-- Ambientes del SRI: direcciones de los servicios web y tiempos.
             Antes estaban escritos en el código; ahora se guardan en
             facturas_electronicas_ambiente (migración 069). -->
        <form class="FormularioAjax" method="POST" autocomplete="off" action="<?php echo $ajaxSri; ?>"
              id="formAmbientes" data-confirmar="Las próximas facturas se enviarán con estas direcciones y tiempos.">
            <input type="hidden" name="modulo_facturas" value="GUARDAR_AMBIENTES_SRI">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-server me-2"></i>Ambientes del SRI</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Direcciones de los servicios de recepción y autorización y cuánto se espera al SRI.
                        El ambiente <strong>en uso</strong> se elige arriba, en «Emisor y comprobantes».
                        Sólo se admiten direcciones <code>https://</code> del dominio <code>sri.gob.ec</code>.
                    </p>

                    <?php if (!$ambientes['1']['guardado'] && !$ambientes['2']['guardado']): ?>
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Falta aplicar la migración <strong>069_sri_ambientes.sql</strong>. Mientras tanto se
                            usan los valores publicados por el SRI y no se pueden guardar cambios.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($ambientes as $clave => $a):
                        $enUso = (string)$cfg['ambiente'] === (string)$clave;
                        $p = 'amb[' . $clave . ']';
                        $id = 'amb' . $clave . '_'; ?>
                        <fieldset class="ds-ambiente<?php echo $enUso ? ' ds-ambiente--en-uso' : ''; ?>"
                                  data-ambiente="<?php echo $clave; ?>">
                            <legend class="ds-ambiente__titulo">
                                <span><?php echo $h($a['nombre']); ?></span>
                                <?php if ($enUso): ?>
                                    <span class="badge text-bg-success">En uso</span>
                                <?php endif; ?>
                            </legend>

                            <div class="mb-2">
                                <label for="<?php echo $id; ?>recepcion" class="form-label small">Recepción</label>
                                <input type="url" class="form-control form-control-sm" required maxlength="300"
                                       id="<?php echo $id; ?>recepcion" name="<?php echo $p; ?>[url_recepcion]"
                                       pattern="https://([a-zA-Z0-9-]+\.)*sri\.gob\.ec(/.*)?"
                                       value="<?php echo $h($a['url_recepcion']); ?>">
                            </div>
                            <div class="mb-2">
                                <label for="<?php echo $id; ?>autorizacion" class="form-label small">Autorización</label>
                                <input type="url" class="form-control form-control-sm" required maxlength="300"
                                       id="<?php echo $id; ?>autorizacion" name="<?php echo $p; ?>[url_autorizacion]"
                                       pattern="https://([a-zA-Z0-9-]+\.)*sri\.gob\.ec(/.*)?"
                                       value="<?php echo $h($a['url_autorizacion']); ?>">
                            </div>

                            <div class="row g-2">
                                <?php foreach ([
                                    'timeout_conexion'      => ['Conexión (s)', 5, 120, 'Segundos para conectar con el SRI.'],
                                    'timeout_respuesta'     => ['Respuesta (s)', 10, 300, 'Segundos que se espera cada respuesta.'],
                                    'intentos_autorizacion' => ['Intentos', 1, 10, 'Consultas de autorización tras enviar.'],
                                    'espera_segundos'       => ['Espera (s)', 0, 30, 'Pausa entre consultas de autorización.'],
                                ] as $campo => [$rotulo, $min, $max, $ayuda]): ?>
                                    <div class="col-6 col-md-3">
                                        <label for="<?php echo $id . $campo; ?>" class="form-label small"
                                               title="<?php echo $h($ayuda); ?>"><?php echo $rotulo; ?></label>
                                        <input type="number" class="form-control form-control-sm" required
                                               inputmode="numeric" step="1" min="<?php echo $min; ?>" max="<?php echo $max; ?>"
                                               id="<?php echo $id . $campo; ?>" name="<?php echo $p . '[' . $campo . ']'; ?>"
                                               value="<?php echo (int)$a[$campo]; ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="ds-ambiente__pie">
                                <small class="text-muted">
                                    <?php echo $a['actualizado'] !== ''
                                        ? 'Modificado por ' . $h($a['actualizado'])
                                        : 'Valores de la instalación'; ?>
                                </small>
                                <div class="ds-ambiente__botones">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-restaurar="<?php echo $clave; ?>"
                                            title="Vuelve a poner los valores publicados por el SRI (falta guardar)">
                                        <i class="fas fa-undo me-1"></i> Valores del SRI
                                    </button>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" form="probarAmbiente<?php echo $clave; ?>"
                                            title="Prueba las direcciones guardadas">
                                        <i class="fas fa-network-wired me-1"></i> Probar
                                    </button>
                                </div>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer ds-acciones">
                    <?php echo ds_boton('guardar', 'Guardar ambientes', ['estilo' => 'primary', 'type' => 'submit']); ?>
                </div>
            </div>
        </form>

        <?php /* Formularios de prueba: fuera del de ambientes (no se anidan);
                 los botones «Probar» los envían con el atributo form. */
        foreach (array_keys($ambientes) as $clave): ?>
            <form class="FormularioAjax d-none" method="POST" action="<?php echo $ajaxSri; ?>"
                  id="probarAmbiente<?php echo $clave; ?>">
                <input type="hidden" name="modulo_facturas" value="PROBAR_AMBIENTE_SRI">
                <input type="hidden" name="ambiente" value="<?php echo $clave; ?>">
            </form>
        <?php endforeach; ?>
    </div>

    <div class="col-lg-4">

        <!-- Estado del certificado: lo resuelve Fútbol, que es quien
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

    /* Correo: los datos del servidor SMTP sólo a la vista si se elige SMTP. */
    var metodo = document.getElementById('smtp_activo'), camposSmtp = document.getElementById('camposSmtp');
    if (metodo && camposSmtp) {
        metodo.addEventListener('change', function () { camposSmtp.hidden = metodo.value !== '1'; });
    }

    /* Botones «ver clave» genéricos (data-ver-clave="id del campo"). */
    document.querySelectorAll('[data-ver-clave]').forEach(function (b) {
        b.addEventListener('click', function () {
            var campo = document.getElementById(b.getAttribute('data-ver-clave')), oculta = campo.type === 'password';
            campo.type = oculta ? 'text' : 'password';
            b.innerHTML = oculta ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        });
    });

    /* Proveedor: vista previa del campo adicional mientras se escribe. */
    var rucProv = document.getElementById('ruc_proveedor'), etqProv = document.getElementById('etiqueta_ruc_proveedor'),
        cajaXml = document.getElementById('xmlProveedor');
    function previsualizar() {
        var ruc = rucProv.value.replace(/\D+/g, '');
        cajaXml.hidden = ruc === '';
        cajaXml.querySelector('[data-xml="ruc"]').textContent = ruc;
        cajaXml.querySelector('[data-xml="etiqueta"]').textContent = etqProv.value.trim() || 'RUC Proveedor';
    }
    if (rucProv && etqProv && cajaXml) {
        rucProv.addEventListener('input', previsualizar);
        etqProv.addEventListener('input', previsualizar);
    }

    /* «Valores del SRI»: repone en el formulario los valores publicados
       por el SRI. No guarda: hay que pulsar «Guardar ambientes». */
    var porDefecto = <?php echo json_encode(array_map(static fn($a) => array_intersect_key($a, array_flip(['url_recepcion', 'url_autorizacion', 'timeout_conexion', 'timeout_respuesta', 'intentos_autorizacion', 'espera_segundos'])), $ambientesSri), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
    document.querySelectorAll('[data-restaurar]').forEach(function (b) {
        b.addEventListener('click', function () {
            var clave = b.getAttribute('data-restaurar'), v = porDefecto[clave] || {};
            var campos = { recepcion: v.url_recepcion, autorizacion: v.url_autorizacion,
                timeout_conexion: v.timeout_conexion, timeout_respuesta: v.timeout_respuesta,
                intentos_autorizacion: v.intentos_autorizacion, espera_segundos: v.espera_segundos };
            Object.keys(campos).forEach(function (k) {
                var i = document.getElementById('amb' + clave + '_' + k);
                if (i && campos[k] !== undefined) { i.value = campos[k]; }
            });
            b.closest('.ds-ambiente').classList.add('ds-ambiente--cambiado');
        });
    });

    /* El estado del certificado lo sirve Fútbol. */
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
