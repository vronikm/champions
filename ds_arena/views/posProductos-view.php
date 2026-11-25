<?php
/*
| Arena › Punto de Venta y Caja › Productos e inventario (migración 095).
|
| Lo que se vende: alta y edición (con imagen), precios, stock y su
| mínimo, vencimientos. Arriba, lo que pide atención: agotados, bajo el
| mínimo y lotes vencidos o por vencer. Cada producto lleva su kardex
| (?kardex=id) y se puede ajustar su stock con motivo.
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Productos';
$vistaActual = 'posProductos';

$productos  = $pos->productos(false);
$categorias = $pos->categorias(true);
$alertas    = $pos->alertasInventario();
$puedeCrear = puede_crear('posProductos');
$puedeEditar = puede_editar('posProductos');
$kardexId   = (int)($_GET['kardex'] ?? 0);
$kardexProd = $kardexId ? $pos->producto($kardexId) : null;
$kardex     = $kardexProd ? $pos->kardex($kardexId) : [];
$valorInventario = 0.0;
foreach ($productos as $p) { if ($p['producto_tipo'] === 'P' && $p['producto_estado'] === 'A') { $valorInventario += max(0, (float)$p['producto_stock']) * (float)$p['producto_preciocompra']; } }

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";
?>
<style>
    .pr-barra { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: .75rem; }
    .pr-barra .form-control, .pr-barra .form-select { flex: 1 1 12rem; }
    .pr-alertas { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .pr-alerta { border-radius: var(--pos-radio); padding: .75rem .9rem; border: 1px solid; }
    .pr-alerta h3 { font-size: .9rem; font-weight: 700; margin: 0 0 .35rem; }
    .pr-alerta ul { margin: 0; padding-left: 1rem; font-size: .85rem; }
    .pr-alerta--rojo { background: var(--bs-danger-bg-subtle); border-color: var(--bs-danger-border-subtle); color: var(--bs-danger-text-emphasis); }
    .pr-alerta--ambar { background: var(--bs-warning-bg-subtle); border-color: var(--bs-warning-border-subtle); color: var(--bs-warning-text-emphasis); }
    .pr-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 21rem), 1fr)); gap: .75rem; }
    .pr-item { display: grid; grid-template-columns: 4.5rem 1fr; gap: .25rem .8rem; padding: .7rem; }
    .pr-item.inactivo { opacity: .6; }
    .pr-img { width: 4.5rem; height: 4.5rem; border-radius: .6rem; object-fit: cover; background: var(--bs-tertiary-bg); grid-row: span 2; display: grid; place-items: center; font-weight: 700; font-size: 1.4rem; color: #fff; }
    .pr-nombre { font-weight: 700; line-height: 1.25; overflow-wrap: anywhere; }
    .pr-meta { display: flex; flex-wrap: wrap; gap: .15rem .7rem; font-size: .8rem; color: var(--bs-secondary-color); }
    .pr-datos { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; margin-top: .35rem; text-align: center; }
    .pr-datos div { background: var(--bs-tertiary-bg); border-radius: .5rem; padding: .3rem; font-size: .75rem; color: var(--bs-secondary-color); }
    .pr-datos strong { display: block; font-size: .95rem; color: var(--bs-body-color); font-variant-numeric: tabular-nums; }
    .pr-acciones { grid-column: 1 / -1; display: flex; gap: .4rem; margin-top: .4rem; }
    .pr-acciones .btn { flex: 1 1 0; min-height: 2.5rem; --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }
    .pr-vista-img { width: 6rem; height: 6rem; border-radius: .75rem; object-fit: cover; background: var(--bs-tertiary-bg); }
    .pr-margen { font-size: .85rem; }
</style>

<div class="pos">
<?php if ($kardexProd): ?>
    <!-- ==================== Kardex ==================== -->
    <div class="pos-tarjeta mb-3">
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo APP_URL; ?>posProductos/"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Productos</a>
            <h2 class="h6 fw-bold mb-0">Kardex · <?php echo $posH($kardexProd['producto_codigo'] . ' · ' . $kardexProd['producto_nombre']); ?></h2>
            <span class="ms-auto badge text-bg-primary fs-6">Stock: <?php echo $posCant($kardexProd['producto_stock']); ?></span>
        </div>
        <?php if (!$kardex): ?>
            <p class="text-body-secondary p-3 mb-0">Sin movimientos.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Fecha</th><th>Movimiento</th><th class="text-end">Entra</th><th class="text-end">Sale</th><th class="text-end">Saldo</th><th class="text-end">Costo</th><th>Detalle</th><th>Usuario</th></tr></thead>
                    <tbody>
                        <?php foreach ($kardex as $k): ?>
                            <tr>
                                <td class="text-nowrap"><?php echo date('d/m/Y H:i', strtotime($k['kardex_fecharegistro'])); ?></td>
                                <td><?php echo $posH(posController::ORIGENES_KARDEX[$k['kardex_origen']] ?? $k['kardex_origen']); ?></td>
                                <td class="text-end text-success"><?php echo $k['kardex_tipo'] === 'E' ? $posCant($k['kardex_cantidad']) : ''; ?></td>
                                <td class="text-end text-danger"><?php echo $k['kardex_tipo'] === 'S' ? $posCant($k['kardex_cantidad']) : ''; ?></td>
                                <td class="text-end fw-bold"><?php echo $posCant($k['kardex_stocknuevo']); ?></td>
                                <td class="text-end"><?php echo $posDinero($k['kardex_costo']); ?></td>
                                <td class="small"><?php echo $posH(trim(($k['venta_numero'] ?: '') . ' ' . ($k['compra_proveedor'] ? 'Compra a ' . $k['compra_proveedor'] : '') . ' ' . ($k['kardex_detalle'] ?? ''))); ?></td>
                                <td class="small"><?php echo $posH($k['usuario_usuario']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

    <!-- ==================== Lo que pide atención ==================== -->
    <?php if ($alertas['agotados'] || $alertas['bajos'] || $alertas['vencidos'] || $alertas['porVencer']): ?>
        <div class="pr-alertas">
            <?php if ($alertas['agotados']): ?>
                <div class="pr-alerta pr-alerta--rojo"><h3><i class="fas fa-circle-exclamation me-1" aria-hidden="true"></i><?php echo count($alertas['agotados']); ?> agotado(s)</h3>
                    <ul><?php foreach (array_slice($alertas['agotados'], 0, 6) as $p): ?><li><?php echo $posH($p['producto_nombre']); ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php if ($alertas['bajos']): ?>
                <div class="pr-alerta pr-alerta--ambar"><h3><i class="fas fa-arrow-trend-down me-1" aria-hidden="true"></i><?php echo count($alertas['bajos']); ?> bajo el mínimo: reponer</h3>
                    <ul><?php foreach (array_slice($alertas['bajos'], 0, 6) as $p): ?><li><?php echo $posH($p['producto_nombre']); ?>: quedan <?php echo $posCant($p['producto_stock']); ?> (mín. <?php echo $posCant($p['producto_stockminimo']); ?>)</li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php if ($alertas['vencidos']): ?>
                <div class="pr-alerta pr-alerta--rojo"><h3><i class="fas fa-calendar-xmark me-1" aria-hidden="true"></i>Vencidos: retirar de la venta</h3>
                    <ul><?php foreach ($alertas['vencidos'] as $l): ?><li><?php echo $posH($l['producto_nombre']); ?> · venció el <?php echo date('d/m/Y', strtotime($l['detalle_vence'])); ?> · quedan ~<?php echo $posCant($l['quedan']); ?>
                        <?php if ($puedeEditar): ?><button type="button" class="btn btn-link btn-sm p-0 ms-1 pr-baja" data-id="<?php echo (int)$l['producto_id']; ?>" data-nombre="<?php echo $posH($l['producto_nombre']); ?>" data-cant="<?php echo $posCant($l['quedan']); ?>">dar de baja</button><?php endif; ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php if ($alertas['porVencer']): ?>
                <div class="pr-alerta pr-alerta--ambar"><h3><i class="fas fa-hourglass-half me-1" aria-hidden="true"></i>Por vencer (30 días)</h3>
                    <ul><?php foreach (array_slice($alertas['porVencer'], 0, 6) as $l): ?><li><?php echo $posH($l['producto_nombre']); ?> · <?php echo date('d/m/Y', strtotime($l['detalle_vence'])); ?> · ~<?php echo $posCant($l['quedan']); ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="pos-kpis">
        <div class="pos-kpi"><span class="pos-kpi__etq"><i class="fas fa-boxes-stacked" aria-hidden="true"></i>Productos activos</span>
            <strong class="pos-kpi__cifra"><?php echo count(array_filter($productos, static fn ($p) => $p['producto_estado'] === 'A')); ?></strong>
            <span class="pos-kpi__nota"><?php echo count($categorias); ?> categorías</span></div>
        <div class="pos-kpi"><span class="pos-kpi__etq"><i class="fas fa-warehouse" aria-hidden="true"></i>Valor del inventario</span>
            <strong class="pos-kpi__cifra"><?php echo $posDinero($valorInventario); ?></strong>
            <span class="pos-kpi__nota">al costo de la última compra</span></div>
    </div>

    <div class="pr-barra">
        <input type="search" class="form-control" id="prBuscar" placeholder="Buscar por nombre o código" aria-label="Buscar producto">
        <select class="form-select" id="prCat" aria-label="Categoría" style="max-width:14rem;">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?><option value="<?php echo (int)$c['categoria_id']; ?>"><?php echo $posH($c['categoria_nombre']); ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" id="prFiltro" aria-label="Estado" style="max-width:12rem;">
            <option value="">Todos</option><option value="bajo">Bajo el mínimo / agotados</option><option value="I">Inactivos</option>
        </select>
        <?php if ($puedeCrear): ?>
            <button type="button" class="btn btn-primary pos-btn-grande" id="prNuevo"><i class="fas fa-plus" aria-hidden="true"></i>Nuevo producto</button>
        <?php endif; ?>
    </div>

    <?php if (!$productos): ?>
        <div class="pos-tarjeta p-4 text-center text-body-secondary">Aún no hay productos. <?php echo $puedeCrear ? 'Use «Nuevo producto» para registrar el primero.' : ''; ?></div>
    <?php else: ?>
        <div class="pr-lista" id="prLista">
            <?php foreach ($productos as $p):
                $img = $pos->urlImagen($p['producto_imagen']);
                $s = (float)$p['producto_stock']; $min = (float)$p['producto_stockminimo'];
                $esP = $p['producto_tipo'] === 'P';
                $bajo = $esP && ($s <= 0 || ($min > 0 && $s <= $min));
                $margen = (float)$p['producto_precioventa'] > 0 && (float)$p['producto_preciocompra'] > 0
                    ? ((float)$p['producto_precioventa'] - (float)$p['producto_preciocompra']) / (float)$p['producto_precioventa'] * 100 : null;
                $datos = ['id' => (int)$p['producto_id'], 'codigo' => $p['producto_codigo'], 'nombre' => $p['producto_nombre'],
                          'categoria' => (int)$p['producto_categoriaid'], 'tipo' => $p['producto_tipo'], 'pc' => (float)$p['producto_preciocompra'],
                          'pv' => (float)$p['producto_precioventa'], 'min' => $min, 'vence' => $p['producto_vence'], 'estado' => $p['producto_estado'], 'img' => $img];
            ?>
                <div class="pos-tarjeta pr-item<?php echo $p['producto_estado'] === 'I' ? ' inactivo' : ''; ?>"
                     data-busca="<?php echo $posH(mb_strtolower($p['producto_nombre'] . ' ' . $p['producto_codigo'])); ?>"
                     data-cat="<?php echo (int)$p['producto_categoriaid']; ?>" data-bajo="<?php echo $bajo ? '1' : '0'; ?>" data-estado="<?php echo $posH($p['producto_estado']); ?>">
                    <?php if ($img): ?><img class="pr-img" src="<?php echo $posH($img); ?>" alt="" loading="lazy">
                    <?php else: ?><span class="pr-img" style="background:<?php echo ['#0e7490', '#15803d', '#b45309', '#7c3aed', '#be123c', '#1d4ed8'][(int)$p['producto_id'] % 6]; ?>"><?php echo $posH(mb_strtoupper(mb_substr($p['producto_nombre'], 0, 1))); ?></span><?php endif; ?>
                    <span class="pr-nombre"><?php echo $posH($p['producto_nombre']); ?>
                        <?php if ($p['producto_estado'] === 'I'): ?><span class="badge text-bg-secondary">Inactivo</span><?php endif; ?></span>
                    <span class="pr-meta"><code><?php echo $posH($p['producto_codigo']); ?></code><span><?php echo $posH($p['categoria_nombre'] ?? 'Sin categoría'); ?></span>
                        <?php if (!$esP): ?><span class="badge text-bg-info">Servicio</span><?php endif; ?>
                        <?php if ($p['producto_vence'] === 'S'): ?><span><i class="fas fa-hourglass-half" aria-hidden="true"></i> vence</span><?php endif; ?></span>
                    <div class="pr-datos">
                        <div>Precio<strong><?php echo $posDinero($p['producto_precioventa']); ?></strong></div>
                        <div>Costo<strong><?php echo $posDinero($p['producto_preciocompra']); ?></strong><?php echo $margen !== null ? '<span class="pr-margen">' . round($margen) . ' % margen</span>' : ''; ?></div>
                        <div>Stock<strong class="<?php echo $bajo ? 'pos-neg' : ''; ?>"><?php echo $esP ? $posCant($s) : '—'; ?></strong><?php echo $esP && $min > 0 ? 'mín. ' . $posCant($min) : ''; ?></div>
                    </div>
                    <div class="pr-acciones">
                        <?php if ($puedeEditar): ?><button type="button" class="btn btn-sm btn-outline-secondary pr-editar" data-p='<?php echo $posH(json_encode($datos, JSON_UNESCAPED_UNICODE)); ?>'><i class="fas fa-pen me-1" aria-hidden="true"></i>Editar</button><?php endif; ?>
                        <?php if ($esP && $puedeEditar): ?><button type="button" class="btn btn-sm btn-outline-secondary pr-ajustar" data-id="<?php echo (int)$p['producto_id']; ?>" data-nombre="<?php echo $posH($p['producto_nombre']); ?>" data-stock="<?php echo $posCant($s); ?>"><i class="fas fa-right-left me-1" aria-hidden="true"></i>Ajustar</button><?php endif; ?>
                        <?php if ($esP): ?><a class="btn btn-sm btn-outline-secondary" href="?kardex=<?php echo (int)$p['producto_id']; ?>"><i class="fas fa-list me-1" aria-hidden="true"></i>Kardex</a><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-body-secondary py-3" id="prSinResultados" hidden>Ningún producto coincide.</p>
    <?php endif; ?>
</div>

<?php if ($puedeCrear || $puedeEditar): ?>
<!-- ==================== Producto ==================== -->
<div class="modal fade pos-hoja" id="prModal" tabindex="-1" aria-labelledby="prModalTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form id="prForm" autocomplete="off" enctype="multipart/form-data">
                <div class="modal-header"><h5 class="modal-title" id="prModalTitulo">Nuevo producto</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <input type="hidden" name="producto_id" id="prId" value="0">
                    <div class="d-flex gap-3 align-items-center mb-3">
                        <img id="prVista" class="pr-vista-img" alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">
                        <div class="flex-grow-1">
                            <label class="form-label fw-semibold" for="prImagen">Imagen <small class="text-body-secondary">(opcional · JPG, PNG o WebP)</small></label>
                            <input type="file" class="form-control" id="prImagen" name="imagen" accept="image/jpeg,image/png,image/webp" capture="environment">
                            <div class="form-check mt-1" id="prQuitarCaja" hidden><input class="form-check-input" type="checkbox" id="prQuitar" name="quitar_imagen" value="1"><label class="form-check-label small" for="prQuitar">Quitar la imagen</label></div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-12 col-sm-8"><label class="form-label fw-semibold" for="prNombre">Nombre</label>
                            <input type="text" class="form-control" id="prNombre" name="nombre" maxlength="100" required placeholder="Ej.: Agua sin gas 500 ml"></div>
                        <div class="col-12 col-sm-4"><label class="form-label fw-semibold" for="prCodigo">Código</label>
                            <input type="text" class="form-control text-uppercase" id="prCodigo" name="codigo" maxlength="30" required placeholder="o el código de barras"></div>
                        <div class="col-12 col-sm-6"><label class="form-label fw-semibold" for="prCategoria">Categoría</label>
                            <select class="form-select" id="prCategoria" name="categoria_id">
                                <option value="0">Sin categoría</option>
                                <?php foreach ($categorias as $c): ?><option value="<?php echo (int)$c['categoria_id']; ?>"><?php echo $posH($c['categoria_nombre']); ?></option><?php endforeach; ?>
                                <option value="nueva">+ Nueva categoría…</option>
                            </select>
                            <input type="text" class="form-control mt-2" id="prCatNueva" name="categoria_nueva" maxlength="60" placeholder="Nombre de la nueva categoría" hidden></div>
                        <div class="col-12 col-sm-6"><label class="form-label fw-semibold" for="prTipo">Tipo</label>
                            <select class="form-select" id="prTipo" name="tipo"><option value="P">Producto (lleva stock)</option><option value="S">Servicio (sin stock)</option></select></div>
                        <div class="col-6"><label class="form-label fw-semibold" for="prPv">Precio de venta</label>
                            <div class="input-group"><span class="input-group-text">$</span><input type="text" inputmode="decimal" class="form-control text-end" id="prPv" name="precio_venta" required placeholder="0.00"></div></div>
                        <div class="col-6"><label class="form-label fw-semibold" for="prPc">Costo <small class="text-body-secondary">(compra)</small></label>
                            <div class="input-group"><span class="input-group-text">$</span><input type="text" inputmode="decimal" class="form-control text-end" id="prPc" name="precio_compra" placeholder="0.00"></div></div>
                        <div class="col-12 small text-body-secondary" id="prMargen"></div>
                        <div class="col-6 pr-solo-p" id="prInicialCaja"><label class="form-label fw-semibold" for="prInicial">Stock inicial</label>
                            <input type="text" inputmode="decimal" class="form-control text-end" id="prInicial" name="stock_inicial" placeholder="0"></div>
                        <div class="col-6 pr-solo-p"><label class="form-label fw-semibold" for="prMin">Stock mínimo <small class="text-body-secondary">(aviso)</small></label>
                            <input type="text" inputmode="decimal" class="form-control text-end" id="prMin" name="stock_minimo" placeholder="0"></div>
                        <div class="col-12 pr-solo-p"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="prVence" name="vence" value="S">
                            <label class="form-check-label" for="prVence">Controlar fecha de vencimiento (se pide en cada compra)</label></div></div>
                        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="prActivo" checked>
                            <label class="form-check-label" for="prActivo">Activo (aparece para vender)</label></div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-primary pos-btn-grande" id="prGuardar"><i class="fas fa-check" aria-hidden="true"></i>Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== Ajuste de stock ==================== -->
<div class="modal fade pos-hoja" id="prModalAjuste" tabindex="-1" aria-labelledby="prAjusteTitulo">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="prFormAjuste" autocomplete="off">
                <div class="modal-header"><h5 class="modal-title" id="prAjusteTitulo">Ajustar stock</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <p class="mb-2"><strong id="prAjNombre"></strong> · stock actual <strong id="prAjStock"></strong></p>
                    <p class="small text-body-secondary">Para lo que llega de un proveedor use <a href="<?php echo APP_URL; ?>posCompras/">Compras</a>. El ajuste es para conteos, mermas, regalos o vencidos.</p>
                    <div class="btn-group w-100 mb-3" role="group" aria-label="Tipo de ajuste">
                        <input type="radio" class="btn-check" name="origen" id="prAjE" value="AJE"><label class="btn btn-outline-success" for="prAjE">Entra</label>
                        <input type="radio" class="btn-check" name="origen" id="prAjS" value="AJS" checked><label class="btn btn-outline-danger" for="prAjS">Sale</label>
                        <input type="radio" class="btn-check" name="origen" id="prAjV" value="VEC"><label class="btn btn-outline-warning" for="prAjV">Vencido</label>
                    </div>
                    <label class="form-label fw-semibold" for="prAjCant">Cantidad</label>
                    <input type="text" inputmode="decimal" class="form-control mb-3 text-end" id="prAjCant" required>
                    <label class="form-label fw-semibold" for="prAjMotivo">Motivo</label>
                    <input type="text" class="form-control" id="prAjMotivo" maxlength="200" required placeholder="Ej.: conteo físico, producto dañado">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-primary pos-btn-grande">Guardar ajuste</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = function (id) { return document.getElementById(id); };
    var SUGERIDO = <?php echo json_encode($pos->codigoProductoSugerido()); ?>;
    var vacio = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
    var num = function (s) { s = String(s || '').replace(/[$\s]/g, ''); if (/^\d+,\d{1,2}$/.test(s)) { s = s.replace(',', '.'); } return parseFloat(s.replace(/,/g, '')) || 0; };

    /* Filtrar la lista, en el navegador. */
    function filtrar() {
        var q = ($('prBuscar').value || '').toLowerCase().trim(), cat = $('prCat').value, f = $('prFiltro').value, n = 0;
        document.querySelectorAll('.pr-item').forEach(function (el) {
            var ok = (!q || el.dataset.busca.indexOf(q) !== -1) && (!cat || el.dataset.cat === cat)
                  && (!f || (f === 'bajo' ? el.dataset.bajo === '1' : el.dataset.estado === f));
            el.hidden = !ok; if (ok) { n++; }
        });
        if ($('prSinResultados')) { $('prSinResultados').hidden = n > 0; }
    }
    ['prBuscar', 'prCat', 'prFiltro'].forEach(function (id) { if ($(id)) { $(id).addEventListener('input', filtrar); } });

    /* El formulario del producto. */
    var modal = $('prModal') ? new bootstrap.Modal($('prModal')) : null;
    function tipo() { var p = $('prTipo').value === 'P'; document.querySelectorAll('.pr-solo-p').forEach(function (e) { e.hidden = !p; }); }
    function margen() {
        var pv = num($('prPv').value), pc = num($('prPc').value);
        $('prMargen').textContent = pv > 0 && pc > 0 ? 'Ganancia por unidad: $' + (pv - pc).toFixed(2) + ' (' + Math.round((pv - pc) / pv * 100) + ' %)' + (pc > pv ? ' · ¡el costo supera al precio!' : '') : '';
    }
    function abrir(p) {
        $('prForm').reset();
        $('prModalTitulo').textContent = p ? 'Editar producto' : 'Nuevo producto';
        $('prId').value = p ? p.id : 0;
        $('prNombre').value = p ? p.nombre : ''; $('prCodigo').value = p ? p.codigo : SUGERIDO;
        $('prCategoria').value = p ? (p.categoria || 0) : 0; $('prCatNueva').hidden = true;
        $('prTipo').value = p ? p.tipo : 'P';
        $('prPv').value = p ? p.pv.toFixed(2) : ''; $('prPc').value = p ? p.pc.toFixed(2) : '';
        $('prMin').value = p ? p.min : ''; $('prVence').checked = p ? p.vence === 'S' : false;
        $('prActivo').checked = p ? p.estado === 'A' : true;
        $('prInicialCaja').hidden = !!p;
        $('prVista').src = p && p.img ? p.img : vacio; $('prQuitarCaja').hidden = !(p && p.img);
        tipo(); margen(); modal.show();
    }
    if ($('prNuevo')) { $('prNuevo').addEventListener('click', function () { abrir(null); }); }
    document.addEventListener('click', function (e) { var b = e.target.closest('.pr-editar'); if (b) { abrir(JSON.parse(b.dataset.p)); } });
    if ($('prForm')) {
        $('prTipo').addEventListener('change', function () { tipo(); if (this.value === 'P') { $('prInicialCaja').hidden = $('prId').value !== '0'; } });
        ['prPv', 'prPc'].forEach(function (id) { $(id).addEventListener('input', margen); });
        $('prCategoria').addEventListener('change', function () { $('prCatNueva').hidden = this.value !== 'nueva'; if (this.value === 'nueva') { $('prCatNueva').focus(); } });
        $('prImagen').addEventListener('change', function () { if (this.files[0]) { $('prVista').src = URL.createObjectURL(this.files[0]); } });
        $('prForm').addEventListener('submit', function (e) {
            e.preventDefault();
            var f = new FormData($('prForm'));
            f.append('modulo_arena', 'posGuardarProducto');
            f.set('estado', $('prActivo').checked ? 'A' : 'I');
            f.set('vence', $('prVence').checked ? 'S' : 'N');
            if (f.get('categoria_id') === 'nueva') { f.set('categoria_id', '0'); } else { f.delete('categoria_nueva'); }
            var b = $('prGuardar'); b.disabled = true;
            posEnviar(f).then(function (j) { b.disabled = false; if (j.ok) { modal.hide(); } posAviso(j); });
        });
    }

    /* Ajuste de stock (y la baja de un lote vencido). */
    var mAj = $('prModalAjuste') ? new bootstrap.Modal($('prModalAjuste')) : null, ajId = 0;
    function abrirAjuste(id, nombre, stock, origen, cant) {
        ajId = id; $('prAjNombre').textContent = nombre; $('prAjStock').textContent = stock;
        $('prAjCant').value = cant || ''; $('prAjMotivo').value = origen === 'VEC' ? 'Producto vencido' : '';
        document.getElementById(origen === 'VEC' ? 'prAjV' : 'prAjS').checked = true;
        mAj.show();
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.pr-ajustar'); if (b) { abrirAjuste(b.dataset.id, b.dataset.nombre, b.dataset.stock, 'AJS'); }
        var v = e.target.closest('.pr-baja'); if (v) { abrirAjuste(v.dataset.id, v.dataset.nombre, '', 'VEC', v.dataset.cant); }
    });
    if ($('prFormAjuste')) {
        $('prFormAjuste').addEventListener('submit', function (e) {
            e.preventDefault();
            posEnviar({ modulo_arena: 'posAjustarStock', producto_id: ajId, origen: document.querySelector('input[name="origen"]:checked').value,
                        cantidad: $('prAjCant').value, motivo: $('prAjMotivo').value })
                .then(function (j) { if (j.ok) { mAj.hide(); } posAviso(j); });
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
