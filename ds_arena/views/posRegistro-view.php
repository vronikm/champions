<?php
/*
| Arena › Punto de Venta y Caja › Registrar ventas (migración 099).
|
| Lo que ya se vendió y no pasó por «Vender»: se ingresa con su fecha (hoy o
| días anteriores), producto por producto, con el precio de ese día. Un
| producto que no está en el catálogo se escribe por su nombre y se crea.
| No entra en ninguna caja; sale en Reportes › Ventas del día elegido.
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Registrar ventas';
$vistaActual = 'posRegistro';

$hoy   = date('Y-m-d');
$minimo = date('Y-m-d', strtotime($hoy . ' -' . posController::REGISTRO_DIAS_ATRAS . ' days'));
$valida = static fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : null;
$desde = $valida($_GET['desde'] ?? null) ?? date('Y-m-01');
$hasta = $valida($_GET['hasta'] ?? null) ?? $hoy;
if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

$listo      = $pos->ventasConRegistro();
$ventas     = $pos->ventasRegistradas($desde, $hasta);
$vigentes   = array_filter($ventas, static fn ($v) => $v['venta_estado'] === 'A');
$totalVig   = array_sum(array_map(static fn ($v) => (float)$v['venta_total'], $vigentes));
$puedeCrear = puede_crear('posRegistro') && $listo;
$productos  = array_values(array_filter($pos->productos(true), static fn ($p) => $p['producto_estado'] === 'A'));
$formas     = array_values(array_filter($pos->formasCobro(), static fn ($f) => $f['forma_esmonedero'] !== 'S'));
usort($formas, static fn ($a, $b) => ($b['forma_codigo'] === 'EFE') <=> ($a['forma_codigo'] === 'EFE'));
$sedes      = $pos->sedesCaja();

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";
?>
<style>
    @media (max-width: 575.98px) { #rvNueva { width: 100%; } }
    .rv-criterios { display: grid; grid-template-columns: 1fr 1fr auto; gap: .5rem; align-items: end; padding: .75rem; }
    @media (max-width: 575.98px) { .rv-criterios { grid-template-columns: 1fr 1fr; } .rv-criterios > :last-child { grid-column: 1 / -1; } }

    /* Agregar un producto */
    /* Una sola fila: cada columna es etiqueta + campo, alineadas arriba, así
       los campos quedan a la misma altura aunque «Producto» lleve su ayuda
       debajo. El botón lleva un hueco del alto de una etiqueta. */
    .rv-agregar { display: grid; grid-template-columns: minmax(0, 2.2fr) 6rem 7.5rem 7.5rem auto; gap: .5rem; align-items: start; }
    .rv-agregar .form-label { display: block; margin-bottom: .2rem; white-space: nowrap; }
    .rv-agregar__boton .btn { white-space: nowrap; }
    @media (max-width: 767.98px) {
        .rv-agregar { grid-template-columns: 1fr 1fr 1fr; }
        .rv-agregar > :first-child, .rv-agregar > :last-child { grid-column: 1 / -1; }
        .rv-agregar__hueco { display: none !important; }
    }
    .rv-subtotal { font-weight: 700; font-variant-numeric: tabular-nums; background: var(--bs-tertiary-bg); }

    /* Buscador de productos */
    .rv-combo { position: relative; }
    .rv-sug { position: absolute; z-index: 1060; inset: calc(100% + 2px) 0 auto 0; max-height: 16rem; overflow-y: auto; margin: 0; padding: .25rem;
              list-style: none; background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: .6rem; box-shadow: 0 .5rem 1.25rem rgba(0,0,0,.15); }
    .rv-sug li { display: grid; grid-template-columns: 1fr auto; gap: 0 .75rem; padding: .55rem .65rem; border-radius: .45rem; cursor: pointer; min-height: 2.75rem; align-items: center; }
    .rv-sug li small { grid-column: 1; color: var(--bs-secondary-color); }
    .rv-sug li b { grid-row: 1 / span 2; grid-column: 2; font-variant-numeric: tabular-nums; }
    .rv-sug li[aria-selected="true"], .rv-sug li:hover { background: var(--bs-primary-bg-subtle); }
    .rv-sug .rv-sug__nuevo { color: var(--bs-success-text-emphasis); font-weight: 600; grid-template-columns: 1fr; }
    .rv-ayuda { font-size: .8rem; margin-top: .2rem; }
    .rv-ayuda--nuevo { color: var(--bs-success-text-emphasis); }

    /* Las líneas: editables, en fila en pantalla ancha y en ficha en el teléfono */
    .rv-lineas { border: 1px solid var(--bs-border-color); border-radius: .75rem; overflow: hidden; }
    .rv-linea, .rv-cab { display: grid; grid-template-columns: minmax(0, 1fr) 5.5rem 7rem 6.5rem 2.75rem; gap: .5rem; align-items: center; padding: .5rem .65rem; }
    .rv-cab { font-size: .75rem; font-weight: 600; text-transform: uppercase; color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }
    .rv-linea + .rv-linea, .rv-cab + .rv-linea { border-top: 1px solid var(--bs-border-color-translucent); }
    .rv-linea__nombre { font-weight: 600; overflow-wrap: anywhere; }
    .rv-linea__nombre small { display: block; font-weight: 400; color: var(--bs-secondary-color); }
    .rv-linea input { text-align: right; }
    .rv-linea__sub { text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
    .rv-vacio { padding: 1.25rem; text-align: center; color: var(--bs-secondary-color); }
    @media (max-width: 575.98px) {
        .rv-cab { display: none; }
        .rv-linea { grid-template-columns: 1fr 1fr 1fr 2.75rem; }
        .rv-linea__nombre { grid-column: 1 / -1; }
        .rv-linea__sub { align-self: center; }
    }
    .rv-total { display: flex; justify-content: space-between; align-items: baseline; padding: .65rem .8rem; border-radius: .75rem; margin-top: .5rem;
                background: #0e7490; color: #fff; font-weight: 700; }
    .rv-total strong { font-size: 1.5rem; font-variant-numeric: tabular-nums; }

    /* Forma de pago */
    .rv-pago { display: grid; grid-template-columns: repeat(auto-fit, minmax(7rem, 1fr)); gap: .5rem; }
    .rv-pago .btn { min-height: var(--pos-toque); --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); font-weight: 600; font-size: .875rem; }
    .rv-pago .btn-check:checked + .btn { background: #0e7490; border-color: #0e7490; color: #fff; }
    .rv-aviso { padding: .5rem .7rem; border-radius: .6rem; font-size: .875rem; background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); border: 1px solid var(--bs-warning-border-subtle); }
</style>

<div class="pos">
    <?php if (!$listo): ?>
        <div class="alert alert-warning">Falta aplicar la migración 099 para registrar ventas.</div>
    <?php endif; ?>

    <div class="pos-tarjeta mb-3 p-3 d-flex flex-wrap gap-3 align-items-center">
        <p class="mb-0 small text-body-secondary flex-grow-1" style="min-width: 15rem;">
            <i class="fas fa-circle-info me-1" aria-hidden="true"></i>Ingrese lo que <strong>ya se vendió</strong> sin pasar por «Vender»: con la fecha de esa venta y el precio de ese día.
            No entra en ninguna caja y sale en <a href="<?php echo APP_URL; ?>posVentas/">Reportes › Ventas</a> del día elegido.
        </p>
        <?php if ($puedeCrear): ?>
            <button type="button" class="btn btn-primary pos-btn px-4" id="rvNueva"><i class="fas fa-file-pen" aria-hidden="true"></i>Registrar ventas</button>
        <?php endif; ?>
    </div>

    <form class="pos-tarjeta mb-3 rv-criterios" method="GET" action="<?php echo APP_URL; ?>posRegistro/" aria-label="Criterios de búsqueda">
        <div><label class="form-label small fw-semibold mb-1" for="rvDesde">Desde</label><input type="date" class="form-control form-control-sm" id="rvDesde" name="desde" value="<?php echo $desde; ?>" max="<?php echo $hoy; ?>"></div>
        <div><label class="form-label small fw-semibold mb-1" for="rvHasta">Hasta</label><input type="date" class="form-control form-control-sm" id="rvHasta" name="hasta" value="<?php echo $hasta; ?>" max="<?php echo $hoy; ?>"></div>
        <button type="submit" class="btn btn-outline-primary pos-btn"><i class="fas fa-magnifying-glass" aria-hidden="true"></i>Consultar</button>
    </form>

    <div class="pos-kpis">
        <div class="pos-kpi pos-kpi--acento"><span class="pos-kpi__etq"><i class="fas fa-sack-dollar" aria-hidden="true"></i>Total registrado</span>
            <strong class="pos-kpi__cifra"><?php echo $posDinero($totalVig); ?></strong>
            <span class="pos-kpi__nota"><?php echo date('d/m/Y', strtotime($desde)) . ' – ' . date('d/m/Y', strtotime($hasta)); ?></span></div>
        <div class="pos-kpi"><span class="pos-kpi__etq"><i class="fas fa-receipt" aria-hidden="true"></i>Registros</span>
            <strong class="pos-kpi__cifra"><?php echo count($vigentes); ?></strong>
            <span class="pos-kpi__nota"><?php $nx = count($ventas) - count($vigentes); echo $nx ? $nx . ' anulado(s)' : 'vigentes'; ?></span></div>
    </div>

    <div class="pos-tarjeta">
        <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0">Ventas registradas <small class="text-body-secondary fw-normal">· <?php echo count($ventas); ?></small></h3></div>
        <?php if (!$ventas): ?>
            <p class="text-body-secondary text-center p-4 mb-0"><i class="fas fa-file-pen fa-2x d-block mb-2 opacity-50" aria-hidden="true"></i>No hay ventas registradas en esas fechas.</p>
        <?php else: ?>
            <ul class="pos-lista">
                <?php foreach ($ventas as $v): $anulada = $v['venta_estado'] === 'X'; ?>
                    <li class="pos-fila">
                        <span class="pos-fila__titulo<?php echo $anulada ? ' pos-tachada' : ''; ?>"><?php echo $posH($v['venta_numero']); ?> · <?php echo date('d/m/Y', strtotime($v['venta_fecharegistro'])); ?>
                            <?php if ($anulada): ?><span class="badge text-bg-secondary ms-1">Anulada</span><?php endif; ?></span>
                        <span class="pos-fila__valor<?php echo $anulada ? ' pos-tachada' : ''; ?>"><?php echo $posDinero($v['venta_total']); ?></span>
                        <span class="pos-fila__meta">
                            <span><?php echo $posH($v['formas']); ?></span>
                            <span><i class="fas fa-user-pen me-1" aria-hidden="true"></i><?php echo $posH($v['vendedor']); ?><?php echo $v['venta_registro'] ? ' · ' . date('d/m H:i', strtotime($v['venta_registro'])) : ''; ?></span>
                            <span class="w-100"><?php echo $posH(mb_strimwidth((string)$v['resumen'], 0, 120, '…')); ?></span>
                        </span>
                        <span class="d-flex gap-2 mt-1" style="grid-column:1/-1;">
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo APP_URL; ?>posTicket/?id=<?php echo (int)$v['venta_id']; ?>" target="_blank" rel="noopener"><i class="fas fa-receipt me-1" aria-hidden="true"></i>Comprobante</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo APP_URL; ?>posVentas/?dia=<?php echo date('Y-m-d', strtotime($v['venta_fecharegistro'])); ?>"><i class="fas fa-calendar-day me-1" aria-hidden="true"></i>Ventas de ese día</a>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($puedeCrear): ?>
<!-- ==================== Registrar ventas ==================== -->
<div class="modal fade pos-hoja" id="rvModal" tabindex="-1" aria-labelledby="rvModalTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <form id="rvForm" autocomplete="off" novalidate>
                <div class="modal-header"><h5 class="modal-title" id="rvModalTitulo"><i class="fas fa-file-pen me-2" aria-hidden="true"></i>Registrar ventas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-3"><label class="form-label fw-semibold" for="rvFecha">Fecha</label>
                            <input type="date" class="form-control" id="rvFecha" value="<?php echo $hoy; ?>" min="<?php echo $minimo; ?>" max="<?php echo $hoy; ?>" required></div>
                        <?php if (count($sedes) > 1): ?>
                            <div class="col-6 col-md-4"><label class="form-label fw-semibold" for="rvSede">Sede</label>
                                <select class="form-select" id="rvSede"><?php foreach ($sedes as $s): ?><option value="<?php echo (int)$s['sede_id']; ?>"><?php echo $posH($s['sede_nombre']); ?></option><?php endforeach; ?></select></div>
                        <?php endif; ?>
                    </div>
                    <p class="rv-aviso mb-3" id="rvAvisoFecha" hidden><i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i><span></span></p>

                    <h3 class="h6 fw-bold">Productos vendidos</h3>
                    <div class="rv-agregar mb-2">
                        <div><label class="form-label small" for="rvProd">Producto</label>
                            <div class="rv-combo">
                                <input type="text" class="form-control" id="rvProd" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="rvSug"
                                       maxlength="100" placeholder="Escriba para buscar o el nombre de uno nuevo" aria-describedby="rvProdAyuda">
                                <ul class="rv-sug" id="rvSug" role="listbox" hidden></ul>
                            </div>
                            <div class="rv-ayuda text-body-secondary" id="rvProdAyuda">Busque por nombre o código.</div></div>
                        <div><label class="form-label small" for="rvCant">Cantidad</label><input type="text" inputmode="decimal" class="form-control text-end" id="rvCant" placeholder="1"></div>
                        <div><label class="form-label small" for="rvPrecio">Precio c/u</label><input type="text" inputmode="decimal" class="form-control text-end" id="rvPrecio" placeholder="0.00"></div>
                        <div><label class="form-label small" for="rvSub">Subtotal</label><input type="text" class="form-control text-end rv-subtotal" id="rvSub" value="$0.00" readonly tabindex="-1"></div>
                        <div class="rv-agregar__boton"><span class="form-label small rv-agregar__hueco" aria-hidden="true">&nbsp;</span>
                            <button type="button" class="btn btn-outline-primary pos-btn pos-btn--normal w-100" id="rvAgregar"><i class="fas fa-plus" aria-hidden="true"></i>Agregar</button></div>
                    </div>

                    <div class="rv-lineas" id="rvLineas" aria-live="polite"></div>
                    <div class="rv-total"><span>Total</span><strong id="rvTotal">$0.00</strong></div>

                    <h3 class="h6 fw-bold mt-3">Forma de pago</h3>
                    <div class="rv-pago mb-2" role="radiogroup" aria-label="Forma de pago">
                        <?php foreach ($formas as $i => $f): ?>
                            <input type="radio" class="btn-check" name="rvForma" id="rvForma<?php echo (int)$f['forma_id']; ?>" value="<?php echo (int)$f['forma_id']; ?>"
                                   data-ref="<?php echo $posH($f['forma_requiereref']); ?>"<?php echo $i === 0 ? ' checked' : ''; ?>>
                            <label class="btn btn-outline-secondary" for="rvForma<?php echo (int)$f['forma_id']; ?>"><?php echo $posH($f['forma_nombre']); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mb-2" id="rvRefGrupo" hidden><label class="form-label small" for="rvRef">Comprobante <span class="text-body-secondary">(opcional)</span></label>
                        <input type="text" class="form-control" id="rvRef" maxlength="60"></div>

                    <div class="form-check mt-2" id="rvStockGrupo" hidden>
                        <input class="form-check-input" type="checkbox" id="rvStock" checked>
                        <label class="form-check-label" for="rvStock">Descontar del inventario los productos con stock</label>
                        <div class="small text-body-secondary">Desmárquelo si estas ventas son anteriores al conteo del stock.</div>
                    </div>
                    <p class="small text-body-secondary mt-2 mb-0"><i class="fas fa-cash-register me-1" aria-hidden="true"></i>No entra en ninguna caja: son ventas ya hechas.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-primary pos-btn" id="rvGuardar"><i class="fas fa-check" aria-hidden="true"></i>Registrar</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = function (id) { return document.getElementById(id); };
    var num = function (s) { s = String(s || '').replace(/[$\s]/g, ''); if (/^\d+,\d{1,2}$/.test(s)) { s = s.replace(',', '.'); } return Math.round((parseFloat(s.replace(/,/g, '')) || 0) * 100) / 100; };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    var norm = function (s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); };
    var HOY = '<?php echo $hoy; ?>';
    var PRODUCTOS = <?php echo json_encode(array_map(static fn ($p) => ['id' => (int)$p['producto_id'], 'nombre' => $p['producto_nombre'],
        'codigo' => $p['producto_codigo'], 'precio' => (float)$p['producto_precioventa'], 'tipo' => $p['producto_tipo']], $productos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    PRODUCTOS.forEach(function (p) { p.clave = norm(p.nombre + ' ' + p.codigo); });

    var modal = new bootstrap.Modal($('rvModal')), lineas = [], elegido = null, opciones = [], activa = -1;
    var prod = $('rvProd'), sug = $('rvSug'), ayuda = $('rvProdAyuda'), cant = $('rvCant'), precio = $('rvPrecio');

    $('rvNueva').addEventListener('click', function () {
        lineas = []; $('rvForm').reset(); $('rvFecha').value = HOY; limpiarAgregar(); pintar(); avisoFecha(); formaRef(); modal.show();
    });
    $('rvModal').addEventListener('shown.bs.modal', function () { if (window.matchMedia('(min-width: 576px)').matches) { prod.focus(); } });

    /* ---------- fecha ---------- */
    function avisoFecha() {
        var f = $('rvFecha').value, a = $('rvAvisoFecha');
        a.hidden = !(f && f < HOY);
        if (!a.hidden) { a.querySelector('span').textContent = 'Ventas del ' + f.split('-').reverse().join('/') + ': salen en Reportes › Ventas de ese día.'; }
    }
    $('rvFecha').addEventListener('change', avisoFecha);
    $('rvFecha').addEventListener('input', avisoFecha);

    /* ---------- buscador de productos ---------- */
    function cerrarSug() { sug.hidden = true; prod.setAttribute('aria-expanded', 'false'); activa = -1; prod.removeAttribute('aria-activedescendant'); }
    function buscar() {
        var q = norm(prod.value), texto = prod.value.trim();
        elegido = null; ayudaTexto();
        if (!q) { cerrarSug(); return; }
        var partes = q.split(/\s+/);
        opciones = PRODUCTOS.filter(function (p) { return partes.every(function (t) { return p.clave.indexOf(t) !== -1; }); }).slice(0, 8);
        var exacto = PRODUCTOS.some(function (p) { return norm(p.nombre) === q; });
        var html = opciones.map(function (p, i) {
            return '<li role="option" id="rvOp' + i + '" data-i="' + i + '"><span>' + esc(p.nombre) + '</span><small>' + esc(p.codigo) + (p.tipo === 'P' ? ' · con stock' : '') + '</small><b>' + posDinero(p.precio) + '</b></li>';
        }).join('');
        if (!exacto && texto.length >= 2) {
            html += '<li role="option" id="rvOp' + opciones.length + '" data-i="nuevo" class="rv-sug__nuevo"><span><i class="fas fa-plus me-1" aria-hidden="true"></i>Producto nuevo: «' + esc(texto) + '»</span></li>';
        }
        sug.innerHTML = html; sug.hidden = !html; prod.setAttribute('aria-expanded', html ? 'true' : 'false'); activa = -1;
    }
    function ayudaTexto() {
        ayuda.className = 'rv-ayuda ' + (elegido && !elegido.id ? 'rv-ayuda--nuevo' : 'text-body-secondary');
        ayuda.textContent = !elegido ? 'Busque por nombre o código.' : (elegido.id ? elegido.codigo + ' · precio de venta ' + posDinero(elegido.precio)
                          : 'Producto nuevo: se creará en Productos al registrar. Escriba su precio.');
    }
    function elegir(i) {
        if (i === 'nuevo') { elegido = { id: 0, nombre: prod.value.trim(), precio: 0, tipo: 'S' }; precio.value = ''; }
        else { elegido = opciones[+i]; prod.value = elegido.nombre; precio.value = elegido.precio ? elegido.precio.toFixed(2) : ''; }
        cerrarSug(); ayudaTexto();
        if (!cant.value) { cant.value = '1'; }
        calcular();
        (elegido.id ? cant : precio).focus(); (elegido.id ? cant : precio).select();
    }
    function marcar(n) {
        var items = sug.querySelectorAll('li'); if (!items.length) { return; }
        activa = (n + items.length) % items.length;
        items.forEach(function (li, k) { li.setAttribute('aria-selected', k === activa ? 'true' : 'false'); });
        prod.setAttribute('aria-activedescendant', items[activa].id); items[activa].scrollIntoView({ block: 'nearest' });
    }
    prod.addEventListener('input', buscar);
    prod.addEventListener('focus', function () { if (prod.value && !elegido) { buscar(); } });
    prod.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (sug.hidden) { buscar(); } marcar(activa + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(activa - 1); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            var items = sug.querySelectorAll('li');
            if (!sug.hidden && items.length) { elegir(items[Math.max(0, activa)].dataset.i); }
        } else if (e.key === 'Escape' && !sug.hidden) { e.stopPropagation(); cerrarSug(); }
    });
    sug.addEventListener('mousedown', function (e) { e.preventDefault(); });   /* que el campo no pierda el foco antes del clic */
    sug.addEventListener('click', function (e) { var li = e.target.closest('li'); if (li) { elegir(li.dataset.i); } });
    /* Al salir del campo se cierra la lista, salvo que el foco haya vuelto a él. */
    prod.addEventListener('blur', function () { setTimeout(function () { if (document.activeElement !== prod) { cerrarSug(); } }, 150); });

    /* ---------- cantidad × precio ---------- */
    function calcular() { $('rvSub').value = posDinero(Math.round(num(cant.value) * num(precio.value) * 100) / 100); }
    [cant, precio].forEach(function (c) {
        c.addEventListener('input', calcular);
        c.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); agregar(); } });
    });
    function limpiarAgregar() { prod.value = ''; cant.value = ''; precio.value = ''; elegido = null; cerrarSug(); ayudaTexto(); calcular(); }

    function agregar() {
        /* Escribió un nombre y no eligió: si coincide exacto con uno del catálogo, ése; si no, uno nuevo. */
        if (!elegido && prod.value.trim().length >= 2) {
            var q = norm(prod.value), igual = PRODUCTOS.filter(function (p) { return norm(p.nombre) === q || norm(p.codigo) === q; })[0];
            elegido = igual || { id: 0, nombre: prod.value.trim(), precio: 0, tipo: 'S' };
            if (igual && !precio.value) { precio.value = igual.precio.toFixed(2); }
        }
        if (!elegido) { prod.focus(); aviso('Elija o escriba el producto'); return; }
        var c = num(cant.value || '1'), pr = num(precio.value);
        if (c <= 0) { cant.focus(); aviso('Escriba la cantidad'); return; }
        if (pr <= 0) { precio.focus(); aviso('Escriba el precio de ' + elegido.nombre); return; }
        var igualLinea = lineas.filter(function (l) { return l.producto_id === elegido.id && (elegido.id || norm(l.nombre) === norm(elegido.nombre)) && l.precio === pr; })[0];
        if (igualLinea) { igualLinea.cantidad = Math.round((igualLinea.cantidad + c) * 100) / 100; }
        else { lineas.push({ producto_id: elegido.id, nombre: elegido.nombre, codigo: elegido.codigo || '', tipo: elegido.tipo, cantidad: c, precio: pr }); }
        limpiarAgregar(); pintar(); prod.focus();
    }
    $('rvAgregar').addEventListener('click', agregar);
    function aviso(t) { Swal.fire({ toast: true, position: 'top', icon: 'warning', title: t, showConfirmButton: false, timer: 2200 }); }

    /* ---------- las líneas ---------- */
    function total() { return Math.round(lineas.reduce(function (s, l) { return s + Math.round(l.cantidad * l.precio * 100) / 100; }, 0) * 100) / 100; }
    function pintar() {
        if (!lineas.length) { $('rvLineas').innerHTML = '<div class="rv-vacio">Agregue los productos que se vendieron.</div>'; }
        else {
            $('rvLineas').innerHTML = '<div class="rv-cab"><span>Producto</span><span class="text-end">Cantidad</span><span class="text-end">Precio c/u</span><span class="text-end">Subtotal</span><span></span></div>'
              + lineas.map(function (l, i) {
                return '<div class="rv-linea" data-i="' + i + '"><div class="rv-linea__nombre">' + esc(l.nombre)
                     + (l.producto_id ? '<small>' + esc(l.codigo) + (l.tipo === 'P' ? ' · con stock' : '') + '</small>' : '<small class="text-success">Nuevo: se creará en Productos</small>') + '</div>'
                     + '<input type="text" inputmode="decimal" class="form-control" data-campo="cantidad" value="' + l.cantidad + '" aria-label="Cantidad de ' + esc(l.nombre) + '">'
                     + '<input type="text" inputmode="decimal" class="form-control" data-campo="precio" value="' + l.precio.toFixed(2) + '" aria-label="Precio de ' + esc(l.nombre) + '">'
                     + '<span class="rv-linea__sub">' + posDinero(Math.round(l.cantidad * l.precio * 100) / 100) + '</span>'
                     + '<button type="button" class="btn btn-link text-danger" data-quitar="' + i + '" aria-label="Quitar ' + esc(l.nombre) + '"><i class="fas fa-trash-can"></i></button></div>';
            }).join('');
        }
        $('rvTotal').textContent = posDinero(total());
        $('rvGuardar').innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>Registrar' + (lineas.length ? ' ' + posDinero(total()) : '');
        $('rvStockGrupo').hidden = !lineas.some(function (l) { return l.tipo === 'P'; });
    }
    $('rvLineas').addEventListener('click', function (e) { var b = e.target.closest('[data-quitar]'); if (b) { lineas.splice(+b.dataset.quitar, 1); pintar(); } });
    $('rvLineas').addEventListener('change', function (e) {
        var inp = e.target.closest('input[data-campo]'); if (!inp) { return; }
        var l = lineas[+inp.closest('.rv-linea').dataset.i], v = num(inp.value);
        if (v > 0) { l[inp.dataset.campo] = v; }
        pintar();
    });

    /* ---------- forma de pago ---------- */
    function formaRef() { var f = document.querySelector('input[name="rvForma"]:checked'); $('rvRefGrupo').hidden = !(f && f.dataset.ref === 'S'); }
    document.querySelectorAll('input[name="rvForma"]').forEach(function (r) { r.addEventListener('change', formaRef); });

    /* ---------- registrar ---------- */
    $('rvForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (prod.value.trim() && !lineas.length) { agregar(); if (!lineas.length) { return; } }
        if (!lineas.length) { aviso('Agregue al menos un producto'); prod.focus(); return; }
        var f = $('rvFecha').value;
        if (!f || f > HOY || f < $('rvFecha').min) { $('rvFecha').focus(); aviso('Elija una fecha válida: hoy o un día anterior'); return; }
        var forma = document.querySelector('input[name="rvForma"]:checked'), nuevos = lineas.filter(function (l) { return !l.producto_id; }).length;
        Swal.fire({ icon: 'question', title: 'Registrar ' + posDinero(total()),
                    html: lineas.length + ' producto(s) con fecha <strong>' + f.split('-').reverse().join('/') + '</strong>, en ' + esc(forma.nextElementSibling.textContent.trim().toLowerCase()) + '.'
                        + (nuevos ? '<br>Se crearán ' + nuevos + ' producto(s) nuevo(s) en Productos.' : '') + '<br><small>No entra en ninguna caja.</small>',
                    showCancelButton: true, confirmButtonText: 'Registrar', cancelButtonText: 'Revisar', confirmButtonColor: '#0e7490', reverseButtons: true })
            .then(function (r) {
                if (!r.isConfirmed) { return; }
                var b = $('rvGuardar'); b.disabled = true;
                posEnviar({ modulo_arena: 'posRegistrarVentas', fecha: f, sede: $('rvSede') ? $('rvSede').value : '', forma_id: forma.value,
                            referencia: $('rvRef').value, descontar_stock: $('rvStock').checked ? 'S' : 'N',
                            lineas: JSON.stringify(lineas.map(function (l) { return { producto_id: l.producto_id, nombre: l.nombre, cantidad: l.cantidad, precio: l.precio }; })) })
                    .then(function (j) { b.disabled = false; if (j.ok) { modal.hide(); } posAviso(j); });
            });
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
