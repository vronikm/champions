<?php
/*
| Arena › Punto de Venta y Caja › Compras (migración 095).
|
| Lo que entra a bodega. Registrar la compra y pagarla son dos cosas
| separadas: al registrarla se elige si queda por pagar, si se paga con el
| efectivo de la caja (sale como egreso de la caja) o por otro medio
| (transferencia, tarjeta… no toca la caja). Lo pendiente se paga después,
| en uno o varios pagos.
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Compras';
$vistaActual = 'posCompras';

$filtro    = ($_GET['ver'] ?? '') === 'pendientes' ? 'pendiente' : '';
$compras   = $pos->compras($filtro);
$verId     = (int)($_GET['id'] ?? 0);
$compra    = $verId ? $pos->compra($verId) : null;
$porPagar  = $pos->totalPorPagar();
$productos = array_values(array_filter($pos->productos(false), static fn ($p) => $p['producto_tipo'] === 'P'));
$formas    = array_values(array_filter($pos->formasCobro(), static fn ($f) => $f['forma_esmonedero'] !== 'S'));
$miCaja    = $pos->cajaAbierta();
$proveedores = array_values(array_unique(array_column($compras, 'compra_proveedor')));
$puedeCrear  = puede_crear('posCompras');
$puedePagar  = puede_editar('posCompras') || $puedeCrear;
$puedeAnular = puede_eliminar('posCompras');

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";
?>
<style>
    .cp-lineas td, .cp-lineas th { vertical-align: middle; }
    .cp-agregar { display: grid; grid-template-columns: minmax(0, 2fr) 6rem 7rem 9rem auto; gap: .5rem; align-items: end; }
    .cp-pago { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
    .cp-pago .btn { min-height: var(--pos-toque); --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); font-weight: 600; font-size: .875rem; line-height: 1.5;
                   display: flex; align-items: center; justify-content: center; gap: .4rem; padding: .3rem .5rem; }
    /* El ícono al lado del texto, no encima: el botón no crece. */
    .cp-pago .btn i.d-block { display: inline-block !important; margin: 0 !important; }
    @media (max-width: 575.98px) { .cp-pago { grid-template-columns: 1fr; } }
    .cp-pago .btn-check:checked + .btn { background: #0e7490; border-color: #0e7490; color: #fff; }
    @media (max-width: 767.98px) {
        .cp-agregar { grid-template-columns: 1fr 1fr; }
        .cp-agregar > :first-child { grid-column: 1 / -1; }
        .cp-agregar > :last-child { grid-column: 1 / -1; }
    }
</style>

<div class="pos">
<?php if ($compra): ?>
    <!-- ==================== Una compra ==================== -->
    <div class="pos-tarjeta mb-3">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo APP_URL; ?>posCompras/"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Compras</a>
            <h2 class="h6 fw-bold mb-0">C-<?php echo str_pad((string)$compra['compra_id'], 5, '0', STR_PAD_LEFT); ?> · <?php echo $posH($compra['compra_proveedor']); ?></h2>
            <?php if ($compra['compra_estado'] === 'X'): ?><span class="badge text-bg-secondary">Anulada</span>
            <?php elseif ((float)$compra['compra_saldo'] > 0): ?><span class="badge text-bg-warning">Por pagar <?php echo $posDinero($compra['compra_saldo']); ?></span>
            <?php else: ?><span class="badge text-bg-success">Pagada</span><?php endif; ?>
            <span class="ms-auto d-flex gap-2">
                <?php if ($compra['compra_estado'] === 'A' && (float)$compra['compra_saldo'] > 0 && $puedePagar): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="cpBtnPagar"><i class="fas fa-money-bill me-1" aria-hidden="true"></i>Registrar pago</button><?php endif; ?>
                <?php if ($compra['compra_estado'] === 'A' && (float)$compra['compra_pagado'] == 0 && $puedeAnular): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="cpBtnAnular"><i class="fas fa-ban me-1" aria-hidden="true"></i>Anular</button><?php endif; ?>
            </span>
        </div>
        <div class="p-3 small text-body-secondary">
            <?php echo date('d/m/Y', strtotime($compra['compra_fecha'])); ?>
            <?php echo $compra['compra_documento'] ? ' · Documento ' . $posH($compra['compra_documento']) : ''; ?>
            · registró <?php echo $posH($compra['usuario_usuario']); ?>
            <?php echo $compra['compra_observacion'] ? '<br>' . $posH($compra['compra_observacion']) : ''; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 cp-lineas">
                <thead><tr><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Costo</th><th class="text-end">Subtotal</th><th>Vence</th></tr></thead>
                <tbody>
                    <?php foreach ($compra['lineas'] as $l): ?>
                        <tr><td><?php echo $posH($l['producto_nombre']); ?> <small class="text-body-secondary"><?php echo $posH($l['producto_codigo']); ?></small></td>
                            <td class="text-end"><?php echo $posCant($l['detalle_cantidad']); ?></td><td class="text-end"><?php echo $posDinero($l['detalle_costo']); ?></td>
                            <td class="text-end"><?php echo $posDinero($l['detalle_subtotal']); ?></td><td><?php echo $l['detalle_vence'] ? date('d/m/Y', strtotime($l['detalle_vence'])) : '—'; ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end"><?php echo $posDinero($compra['compra_total']); ?></td><td></td></tr></tfoot>
            </table>
        </div>
        <div class="p-3 border-top">
            <h3 class="h6 fw-bold">Pagos</h3>
            <?php if (!$compra['pagos']): ?><p class="small text-body-secondary mb-0">Todavía no se ha pagado nada.</p>
            <?php else: ?>
                <ul class="pos-lista">
                    <?php foreach ($compra['pagos'] as $p): ?>
                        <li class="pos-fila"><span class="pos-fila__titulo"><?php echo $posH($p['forma_nombre']); ?><?php echo $p['pago_cajaid'] ? ' <span class="badge text-bg-info">de la caja</span>' : ''; ?></span>
                            <span class="pos-fila__valor"><?php echo $posDinero($p['pago_valor']); ?></span>
                            <span class="pos-fila__meta"><span><?php echo date('d/m/Y', strtotime($p['pago_fecha'])); ?></span><span><?php echo $posH($p['usuario_usuario']); ?></span>
                                <?php if ($p['pago_referencia']): ?><span>Ref. <?php echo $posH($p['pago_referencia']); ?></span><?php endif; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

    <div class="pos-kpis">
        <div class="pos-kpi<?php echo $porPagar > 0 ? ' pos-kpi--alerta' : ''; ?>"><span class="pos-kpi__etq"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>Por pagar a proveedores</span>
            <strong class="pos-kpi__cifra"><?php echo $posDinero($porPagar); ?></strong>
            <a class="pos-kpi__nota" href="?ver=pendientes">ver pendientes</a></div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <div class="btn-group" role="group" aria-label="Filtro">
            <a class="btn btn-sm <?php echo $filtro === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo APP_URL; ?>posCompras/">Todas</a>
            <a class="btn btn-sm <?php echo $filtro === 'pendiente' ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="?ver=pendientes">Por pagar</a>
        </div>
        <?php if ($puedeCrear): ?>
            <button type="button" class="btn btn-primary pos-btn ms-auto" id="cpNueva"<?php echo $productos ? '' : ' disabled title="Primero registre productos"'; ?>><i class="fas fa-plus" aria-hidden="true"></i>Nueva compra</button>
        <?php endif; ?>
    </div>

    <div class="pos-tarjeta">
        <?php if (!$compras): ?>
            <p class="text-body-secondary text-center p-4 mb-0">No hay compras <?php echo $filtro ? 'por pagar' : 'registradas'; ?>.</p>
        <?php else: ?>
            <ul class="pos-lista">
                <?php foreach ($compras as $c): ?>
                    <li class="pos-fila">
                        <a class="pos-fila__titulo" href="?id=<?php echo (int)$c['compra_id']; ?>">C-<?php echo str_pad((string)$c['compra_id'], 5, '0', STR_PAD_LEFT); ?> · <?php echo $posH($c['compra_proveedor']); ?></a>
                        <span class="pos-fila__valor<?php echo $c['compra_estado'] === 'X' ? ' pos-tachada' : ''; ?>"><?php echo $posDinero($c['compra_total']); ?></span>
                        <span class="pos-fila__meta">
                            <span><?php echo date('d/m/Y', strtotime($c['compra_fecha'])); ?></span><span><?php echo (int)$c['lineas']; ?> producto(s)</span>
                            <?php if ($c['compra_estado'] === 'X'): ?><span class="badge text-bg-secondary">Anulada</span>
                            <?php elseif ((float)$c['compra_saldo'] > 0): ?><span class="badge text-bg-warning">Debe <?php echo $posDinero($c['compra_saldo']); ?></span>
                            <?php else: ?><span class="badge text-bg-success">Pagada</span><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($puedeCrear): ?>
<!-- ==================== Nueva compra ==================== -->
<div class="modal fade pos-hoja" id="cpModal" tabindex="-1" aria-labelledby="cpModalTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <form id="cpForm" autocomplete="off">
                <div class="modal-header"><h5 class="modal-title" id="cpModalTitulo">Nueva compra</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-5"><label class="form-label fw-semibold" for="cpProveedor">Proveedor</label>
                            <input type="text" class="form-control" id="cpProveedor" list="cpProveedores" maxlength="120" required placeholder="¿A quién se compró?">
                            <datalist id="cpProveedores"><?php foreach ($proveedores as $p): ?><option value="<?php echo $posH($p); ?>"><?php endforeach; ?></datalist></div>
                        <div class="col-6 col-md-4"><label class="form-label fw-semibold" for="cpDocumento">Factura / nota <small class="text-body-secondary">(opcional)</small></label>
                            <input type="text" class="form-control" id="cpDocumento" maxlength="40"></div>
                        <div class="col-6 col-md-3"><label class="form-label fw-semibold" for="cpFecha">Fecha</label>
                            <input type="date" class="form-control" id="cpFecha" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required></div>
                    </div>

                    <h3 class="h6 fw-bold">Productos que llegaron</h3>
                    <div class="cp-agregar mb-2">
                        <div><label class="form-label small" for="cpProd">Producto</label>
                            <select class="form-select" id="cpProd"><option value="">Elija…</option>
                                <?php foreach ($productos as $p): ?><option value="<?php echo (int)$p['producto_id']; ?>"><?php echo $posH($p['producto_nombre'] . ' · ' . $p['producto_codigo']); ?></option><?php endforeach; ?>
                            </select></div>
                        <div><label class="form-label small" for="cpCant">Cantidad</label><input type="text" inputmode="decimal" class="form-control text-end" id="cpCant" placeholder="0"></div>
                        <div><label class="form-label small" for="cpCosto">Costo c/u</label><input type="text" inputmode="decimal" class="form-control text-end" id="cpCosto" placeholder="0.00"></div>
                        <div><label class="form-label small" for="cpVence">Vence</label><input type="date" class="form-control" id="cpVence" disabled></div>
                        <button type="button" class="btn btn-outline-primary" id="cpAgregar"><i class="fas fa-plus me-1" aria-hidden="true"></i>Agregar</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm cp-lineas"><thead><tr><th>Producto</th><th class="text-end">Cant.</th><th class="text-end">Costo</th><th class="text-end">Subtotal</th><th>Vence</th><th></th></tr></thead>
                            <tbody id="cpLineas"><tr><td colspan="6" class="text-center text-body-secondary">Agregue los productos de la compra.</td></tr></tbody>
                            <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end" id="cpTotal">$0.00</td><td colspan="2"></td></tr></tfoot></table>
                    </div>

                    <h3 class="h6 fw-bold mt-2">¿Cómo se paga?</h3>
                    <div class="cp-pago mb-2" role="radiogroup" aria-label="Pago">
                        <input type="radio" class="btn-check" name="pago_modo" id="cpPagoP" value="pendiente" checked><label class="btn btn-outline-secondary" for="cpPagoP"><i class="fas fa-clock d-block mb-1" aria-hidden="true"></i>Queda por pagar</label>
                        <input type="radio" class="btn-check" name="pago_modo" id="cpPagoC" value="caja"<?php echo $miCaja ? '' : ' disabled'; ?>><label class="btn btn-outline-secondary" for="cpPagoC"><i class="fas fa-cash-register d-block mb-1" aria-hidden="true"></i>Efectivo de mi caja<?php echo $miCaja ? '' : '<br><small>(caja cerrada)</small>'; ?></label>
                        <input type="radio" class="btn-check" name="pago_modo" id="cpPagoO" value="otro"><label class="btn btn-outline-secondary" for="cpPagoO"><i class="fas fa-building-columns d-block mb-1" aria-hidden="true"></i>Otro medio</label>
                    </div>
                    <div class="row g-2" id="cpOtro" hidden>
                        <div class="col-6"><select class="form-select" id="cpForma" aria-label="Forma de pago"><?php foreach ($formas as $f): ?><option value="<?php echo (int)$f['forma_id']; ?>"><?php echo $posH($f['forma_nombre']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-6"><input type="text" class="form-control" id="cpRef" maxlength="60" placeholder="Comprobante (opcional)"></div>
                    </div>
                    <p class="small text-body-secondary mt-2 mb-0" id="cpNota">El stock entra ahora; el pago se registra después en la compra.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-primary pos-btn" id="cpGuardar"><i class="fas fa-check" aria-hidden="true"></i>Registrar compra</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($compra && $compra['compra_estado'] === 'A'): ?>
<div class="modal fade pos-hoja" id="cpModalPagar" tabindex="-1" aria-labelledby="cpPagarTitulo">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="cpFormPagar" autocomplete="off">
                <div class="modal-header"><h5 class="modal-title" id="cpPagarTitulo">Pagar a <?php echo $posH($compra['compra_proveedor']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <p>Saldo: <strong><?php echo $posDinero($compra['compra_saldo']); ?></strong></p>
                    <label class="form-label fw-semibold" for="cpPValor">Valor</label>
                    <div class="input-group input-group-lg mb-3"><span class="input-group-text">$</span>
                        <input type="text" inputmode="decimal" class="form-control text-end fw-bold" id="cpPValor" value="<?php echo number_format((float)$compra['compra_saldo'], 2, '.', ''); ?>"></div>
                    <div class="cp-pago mb-2" role="radiogroup" aria-label="Medio">
                        <input type="radio" class="btn-check" name="cpPModo" id="cpPModoC" value="caja"<?php echo $miCaja ? ' checked' : ' disabled'; ?>><label class="btn btn-outline-secondary" for="cpPModoC">Efectivo de mi caja</label>
                        <input type="radio" class="btn-check" name="cpPModo" id="cpPModoO" value="otro"<?php echo $miCaja ? '' : ' checked'; ?>><label class="btn btn-outline-secondary" for="cpPModoO">Otro medio</label>
                    </div>
                    <div class="row g-2" id="cpPOtro"<?php echo $miCaja ? ' hidden' : ''; ?>>
                        <div class="col-6"><select class="form-select" id="cpPForma" aria-label="Forma"><?php foreach ($formas as $f): ?><option value="<?php echo (int)$f['forma_id']; ?>"><?php echo $posH($f['forma_nombre']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-6"><input type="text" class="form-control" id="cpPRef" maxlength="60" placeholder="Comprobante"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-primary pos-btn">Registrar pago</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = function (id) { return document.getElementById(id); };
    var num = function (s) { s = String(s || '').replace(/[$\s]/g, ''); if (/^\d+,\d{1,2}$/.test(s)) { s = s.replace(',', '.'); } return Math.round((parseFloat(s.replace(/,/g, '')) || 0) * 100) / 100; };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    var PRODUCTOS = <?php echo json_encode(array_map(static fn ($p) => ['id' => (int)$p['producto_id'], 'nombre' => $p['producto_nombre'],
        'costo' => (float)$p['producto_preciocompra'], 'vence' => $p['producto_vence'] === 'S'], $productos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    var porId = {}; PRODUCTOS.forEach(function (p) { porId[p.id] = p; });

    /* ---------- nueva compra ---------- */
    if ($('cpForm')) {
        var modal = new bootstrap.Modal($('cpModal')), lineas = [];
        $('cpNueva').addEventListener('click', function () { lineas = []; $('cpForm').reset(); pintar(); modal.show(); });
        $('cpProd').addEventListener('change', function () {
            var p = porId[this.value]; $('cpVence').disabled = !(p && p.vence); if (!(p && p.vence)) { $('cpVence').value = ''; }
            if (p) { $('cpCosto').value = p.costo ? p.costo.toFixed(2) : ''; $('cpCant').focus(); }
        });
        $('cpAgregar').addEventListener('click', function () {
            var p = porId[$('cpProd').value], cant = num($('cpCant').value), costo = num($('cpCosto').value);
            if (!p) { $('cpProd').focus(); return; }
            if (cant <= 0) { $('cpCant').focus(); return; }
            if (p.vence && !$('cpVence').value) { $('cpVence').focus(); Swal.fire({ toast: true, position: 'top', icon: 'warning', title: 'Escriba la fecha de vencimiento', showConfirmButton: false, timer: 2200 }); return; }
            lineas.push({ producto_id: p.id, nombre: p.nombre, cantidad: cant, costo: costo, vence: $('cpVence').value });
            $('cpProd').value = ''; $('cpCant').value = ''; $('cpCosto').value = ''; $('cpVence').value = ''; $('cpVence').disabled = true;
            pintar(); $('cpProd').focus();
        });
        $('cpLineas').addEventListener('click', function (e) { var b = e.target.closest('[data-quitar]'); if (b) { lineas.splice(+b.dataset.quitar, 1); pintar(); } });
        function pintar() {
            var total = 0;
            $('cpLineas').innerHTML = lineas.map(function (l, i) {
                var s = Math.round(l.cantidad * l.costo * 100) / 100; total += s;
                return '<tr><td>' + esc(l.nombre) + '</td><td class="text-end">' + l.cantidad + '</td><td class="text-end">' + posDinero(l.costo) + '</td><td class="text-end">' + posDinero(s) + '</td><td>' + (l.vence ? l.vence.split('-').reverse().join('/') : '—') +
                       '</td><td><button type="button" class="btn btn-sm btn-link text-danger" data-quitar="' + i + '" aria-label="Quitar"><i class="fas fa-xmark"></i></button></td></tr>';
            }).join('') || '<tr><td colspan="6" class="text-center text-body-secondary">Agregue los productos de la compra.</td></tr>';
            $('cpTotal').textContent = posDinero(total);
        }
        document.querySelectorAll('input[name="pago_modo"]').forEach(function (r) {
            r.addEventListener('change', function () {
                $('cpOtro').hidden = this.value !== 'otro';
                $('cpNota').textContent = { pendiente: 'El stock entra ahora; el pago se registra después en la compra.',
                    caja: 'Se paga con el efectivo de su caja: queda como egreso de la caja.', otro: 'Pagada por otro medio: no toca el efectivo de la caja.' }[this.value];
            });
        });
        $('cpForm').addEventListener('submit', function (e) {
            e.preventDefault();
            if (!lineas.length) { Swal.fire({ icon: 'info', title: 'Agregue al menos un producto' }); return; }
            var modo = document.querySelector('input[name="pago_modo"]:checked').value, b = $('cpGuardar'); b.disabled = true;
            posEnviar({ modulo_arena: 'posGuardarCompra', proveedor: $('cpProveedor').value, documento: $('cpDocumento').value, fecha: $('cpFecha').value,
                        lineas: JSON.stringify(lineas), pago_modo: modo, pago_forma: $('cpForma').value, pago_referencia: $('cpRef').value })
                .then(function (j) { b.disabled = false; if (j.ok) { modal.hide(); } posAviso(j); });
        });
    }

    /* ---------- pagar y anular una compra ---------- */
    var compraId = <?php echo $compra ? (int)$compra['compra_id'] : 0; ?>;
    if ($('cpBtnPagar')) {
        var mp = new bootstrap.Modal($('cpModalPagar'));
        $('cpBtnPagar').addEventListener('click', function () { mp.show(); });
        document.querySelectorAll('input[name="cpPModo"]').forEach(function (r) { r.addEventListener('change', function () { $('cpPOtro').hidden = this.value !== 'otro'; }); });
        $('cpFormPagar').addEventListener('submit', function (e) {
            e.preventDefault();
            posEnviar({ modulo_arena: 'posPagarCompra', compra_id: compraId, valor: $('cpPValor').value,
                        pago_modo: document.querySelector('input[name="cpPModo"]:checked').value, pago_forma: $('cpPForma').value, pago_referencia: $('cpPRef').value })
                .then(function (j) { if (j.ok) { mp.hide(); } posAviso(j); });
        });
    }
    if ($('cpBtnAnular')) {
        $('cpBtnAnular').addEventListener('click', function () {
            Swal.fire({ icon: 'warning', title: 'Anular la compra', input: 'text', inputLabel: 'Motivo', inputPlaceholder: 'Ej.: se registró dos veces',
                        text: 'El stock que entró con ella volverá a salir.', showCancelButton: true, confirmButtonText: 'Anular', cancelButtonText: 'Volver', confirmButtonColor: '#dc3545' })
                .then(function (r) { if (r.isConfirmed) { posEnviar({ modulo_arena: 'posAnularCompra', compra_id: compraId, motivo: r.value || '' }).then(posAviso); } });
        });
    }
});
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
