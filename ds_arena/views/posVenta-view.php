<?php
/*
| Arena › Punto de Venta y Caja › Vender (migración 095).
|
| La pantalla de mostrador: tocar productos, cobrar y entregar el
| comprobante. Hecha para el teléfono y para quien no sabe de contabilidad:
| todo se calcula solo y cada paso dice lo que va a pasar.
|
|   · Sin caja abierta, lo primero es abrirla con el efectivo inicial.
|   · El catálogo son botones con imagen; el buscador acepta nombre o
|     código (un lector de código de barras escribe el código y Enter).
|   · El carrito, en el teléfono, sube desde abajo; en el escritorio es la
|     columna derecha. Queda guardado en el navegador si se recarga.
|   · Los importes que se ven aquí son una vista previa: los manda el
|     servidor (posController::vender).
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Vender';
$vistaActual = 'posVenta';

$puedeVender = puede_crear('posVenta');
$caja        = $pos->cajaAbierta();
$autoriza    = $pos->puedeAutorizar();
$sedes       = $pos->sedesCaja();

if ($caja) {
    $productos  = $pos->productos(true);
    $categorias = $pos->categorias();
    $formas     = $pos->formasCobro();
    $resumen    = $pos->resumenCaja($caja);
}

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";
?>

<div class="pos">
<?php if (!$puedeVender): ?>
    <div class="alert alert-warning">Su rol no tiene permiso para vender. Pida acceso a «Vender» en Core › Permisos.</div>

<?php elseif (!$caja): ?>
    <!-- ==================== Abrir caja ==================== -->
    <div class="pos-tarjeta mx-auto p-3 p-md-4" style="max-width: 34rem;">
        <div class="text-center mb-3">
            <span class="d-inline-grid place-items-center rounded-circle text-bg-primary mb-2" style="width:3.5rem;height:3.5rem;display:inline-grid;place-items:center;">
                <i class="fas fa-cash-register fa-lg" aria-hidden="true"></i></span>
            <h2 class="h4 mb-1">Abra su caja para empezar a vender</h2>
            <p class="text-body-secondary mb-0">Escriba con cuánto efectivo empieza (el «sencillo» para dar cambio). Si no tiene, deje 0.</p>
        </div>
        <form id="posAbrir" autocomplete="off">
            <label for="posInicial" class="form-label fw-semibold">Efectivo inicial</label>
            <div class="input-group input-group-lg mb-2">
                <span class="input-group-text">$</span>
                <input type="text" inputmode="decimal" class="form-control text-end fw-bold" id="posInicial" name="saldo_inicial" value="0.00" required>
            </div>
            <details class="mb-3">
                <summary class="small text-primary" style="cursor:pointer;"><i class="fas fa-calculator me-1" aria-hidden="true"></i>Contar billetes y monedas</summary>
                <p class="small text-body-secondary my-2">Escriba cuántos hay de cada uno: el total se pone solo.</p>
                <?php echo $posConteoHtml('posConteoAbrir'); ?>
            </details>
            <?php if (count($sedes) > 1): ?>
                <label for="posSede" class="form-label fw-semibold">Sede</label>
                <select class="form-select mb-3" id="posSede" name="sede">
                    <?php foreach ($sedes as $s): ?><option value="<?php echo (int)$s['sede_id']; ?>"><?php echo $posH($s['sede_nombre']); ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-100 pos-btn"><i class="fas fa-lock-open" aria-hidden="true"></i>Abrir caja</button>
            <p class="small text-body-secondary text-center mt-2 mb-0">Queda a su nombre (<?php echo $posH(ds_nombre_usuario()); ?>). Al terminar el turno, ciérrela en <a href="<?php echo APP_URL; ?>posCaja/">Caja</a>.</p>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('posAbrir'), conteo = document.getElementById('posConteoAbrir'), ini = document.getElementById('posInicial');
        conteo.addEventListener('input', function () { ini.value = posContar(conteo).total.toFixed(2); });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var c = posContar(conteo);
            var datos = { modulo_arena: 'posAbrirCaja', saldo_inicial: ini.value, sede: (document.getElementById('posSede') || {}).value || '' };
            if (c.total > 0) { datos.conteo = c.json; }
            form.querySelector('button[type=submit]').disabled = true;
            posEnviar(datos).then(function (j) { form.querySelector('button[type=submit]').disabled = false; posAviso(j); });
        });
    });
    </script>

<?php else:
    $datosProductos = array_map(static fn ($p) => [
        'id' => (int)$p['producto_id'], 'codigo' => $p['producto_codigo'], 'nombre' => $p['producto_nombre'],
        'cat' => (int)$p['producto_categoriaid'], 'precio' => (float)$p['producto_precioventa'],
        'stock' => (float)$p['producto_stock'], 'minimo' => (float)$p['producto_stockminimo'],
        'servicio' => $p['producto_tipo'] === 'S', 'img' => $pos->urlImagen($p['producto_imagen']),
    ], $productos);
    $datosFormas = array_map(static fn ($f) => [
        'id' => (int)$f['forma_id'], 'codigo' => $f['forma_codigo'], 'nombre' => $f['forma_nombre'],
        'monedero' => $f['forma_esmonedero'] === 'S', 'ref' => $f['forma_requiereref'] === 'S',
    ], $formas);
    $iconoForma = ['EFE' => 'fa-money-bill-wave', 'TRA' => 'fa-building-columns', 'TAR' => 'fa-credit-card', 'MON' => 'fa-wallet'];
?>
    <style>
        .pos-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin-bottom: .75rem; }
        .pos-caja-chip { display: inline-flex; align-items: center; gap: .45rem; padding: .35rem .75rem; border-radius: 2rem;
                         background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); font-size: .85rem; text-decoration: none; }
        .pos-vender { display: grid; grid-template-columns: minmax(0, 1fr) 24rem; gap: 1rem; align-items: start; }
        .pos-buscar { position: relative; }
        .pos-buscar i { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--bs-secondary-color); }
        .pos-buscar input { padding-left: 2.5rem; min-height: var(--pos-toque); font-size: 1rem; border-radius: 2rem; }
        .pos-cats { display: flex; gap: .4rem; overflow-x: auto; padding: .6rem 0; scrollbar-width: none; }
        .pos-cats::-webkit-scrollbar { display: none; }
        .pos-cats button { flex: 0 0 auto; border-radius: 2rem; min-height: var(--pos-toque); padding: .25rem .8rem; font-size: .875rem;
                           --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }
        .pos-cats button[aria-pressed="true"] { background: #0e7490; border-color: #0e7490; color: #fff; }
        .pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr)); gap: .6rem; }
        .pos-prod { position: relative; display: flex; flex-direction: column; text-align: left; padding: 0; overflow: hidden;
                    border: 1px solid var(--bs-border-color); border-radius: var(--pos-radio); background: var(--bs-body-bg); color: var(--bs-body-color);
                    transition: transform .08s, box-shadow .15s; }
        .pos-prod:hover { box-shadow: 0 .25rem .75rem rgba(0,0,0,.12); }
        .pos-prod:active { transform: scale(.97); }
        .pos-prod:focus-visible { outline: 3px solid #0e7490; outline-offset: 2px; }
        .pos-prod[disabled] { opacity: .5; }
        .pos-prod__img { aspect-ratio: 4 / 3; width: 100%; object-fit: cover; background: var(--bs-tertiary-bg); }
        .pos-prod__ini { aspect-ratio: 4 / 3; width: 100%; display: grid; place-items: center; font-size: 1.6rem; font-weight: 700; color: #fff; }
        .pos-prod__cuerpo { padding: .45rem .6rem .55rem; display: flex; flex-direction: column; gap: .1rem; flex: 1 1 auto; }
        .pos-prod__nombre { font-size: .875rem; font-weight: 600; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .pos-prod__precio { font-weight: 700; font-size: 1rem; margin-top: auto; font-variant-numeric: tabular-nums; }
        .pos-prod__stock { position: absolute; top: .35rem; right: .35rem; font-size: .7rem; }
        .pos-prod__en { position: absolute; top: .35rem; left: .35rem; min-width: 1.6rem; height: 1.6rem; border-radius: 1rem; display: none;
                        place-items: center; padding: 0 .35rem; font-size: .8rem; font-weight: 700; background: #0e7490; color: #fff; }
        .pos-prod.en-carrito { border-color: #0e7490; box-shadow: 0 0 0 1px #0e7490; }
        .pos-prod.en-carrito .pos-prod__en { display: grid; }
        .pos-prod.pulso { animation: posPulso .3s; }
        @keyframes posPulso { 50% { transform: scale(.94); } }

        /* El carrito */
        .pos-carrito { position: sticky; top: 4.5rem; display: flex; flex-direction: column; max-height: calc(100vh - 6rem); }
        .pos-carrito__cab { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1rem; border-bottom: 1px solid var(--bs-border-color); }
        .pos-carrito__lineas { overflow-y: auto; flex: 1 1 auto; min-height: 4rem; }
        .pos-linea { display: grid; grid-template-columns: 1fr auto; gap: .3rem .5rem; padding: .6rem 1rem; border-bottom: 1px solid var(--bs-border-color-translucent); }
        .pos-linea__nombre { font-weight: 600; font-size: .9rem; overflow-wrap: anywhere; }
        .pos-linea__sub { font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }
        .pos-linea__ctrl { grid-column: 1 / -1; display: flex; align-items: center; gap: .4rem; }
        .pos-paso { width: 2rem; height: 2rem; min-width: var(--pos-toque); min-height: var(--pos-toque); border-radius: 50%; display: inline-grid; place-items: center; padding: 0;
                    --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }
        .pos-linea__cant { width: 4rem; text-align: center; font-weight: 700; }
        .pos-linea__precio { font-size: .8rem; color: var(--bs-secondary-color); margin-left: auto; }
        .pos-carrito__vacio { text-align: center; color: var(--bs-secondary-color); padding: 2rem 1rem; }
        .pos-carrito__pie { padding: .75rem 1rem; border-top: 1px solid var(--bs-border-color); display: flex; flex-direction: column; gap: .5rem; }
        .pos-tot { display: flex; justify-content: space-between; font-variant-numeric: tabular-nums; }
        .pos-tot--grande { font-size: 1.5rem; font-weight: 800; }
        .pos-extra { display: flex; gap: .4rem; }
        .pos-extra .btn { flex: 1 1 0; min-height: var(--pos-toque); font-size: .875rem; padding: .25rem .5rem; --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }
        .pos-cliente { display: flex; align-items: center; gap: .5rem; padding: .45rem .6rem; border-radius: .6rem; background: var(--bs-info-bg-subtle); font-size: .85rem; }
        .pos-resultados { list-style: none; margin: .3rem 0 0; padding: 0; border: 1px solid var(--bs-border-color); border-radius: .6rem; max-height: 12rem; overflow-y: auto; }
        .pos-resultados button { width: 100%; text-align: left; border: 0; background: transparent; padding: .5rem .7rem; color: var(--bs-body-color); }
        .pos-resultados button:hover { background: var(--bs-tertiary-bg); }
        .pos-resultados li + li { border-top: 1px solid var(--bs-border-color-translucent); }

        /* Barra «Cobrar» y carrito como hoja en el teléfono y la tableta */
        .pos-barra-movil, .pos-velo { display: none; }
        @media (max-width: 991.98px) {
            .pos-vender { grid-template-columns: 1fr; }
            .pos-grid { padding-bottom: 5.5rem; }
            .pos-carrito { position: fixed; left: 0; right: 0; bottom: 0; top: auto; z-index: 1045; max-height: 88vh; max-height: 88dvh;
                           border-radius: 1rem 1rem 0 0; transform: translateY(105%); transition: transform .25s ease; box-shadow: 0 -.5rem 1.5rem rgba(0,0,0,.25); }
            .pos-carrito.abierto { transform: none; }
            .pos-carrito__cab::before { content: ""; position: absolute; top: .35rem; left: 50%; width: 2.5rem; height: .25rem; margin-left: -1.25rem;
                                        border-radius: 1rem; background: var(--bs-border-color); }
            .pos-carrito__cab { position: relative; padding-top: 1rem; }
            .pos-carrito__pie { padding-bottom: max(.75rem, env(safe-area-inset-bottom)); }
            .pos-velo.abierto { display: block; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1044; }
            .pos-barra-movil { display: flex; position: fixed; left: .75rem; right: .75rem; bottom: max(.75rem, env(safe-area-inset-bottom)); z-index: 1040;
                               align-items: center; gap: .75rem; padding: .55rem .6rem .55rem 1rem; border-radius: 1rem; background: #0e7490; color: #fff;
                               box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.3); border: 0; text-align: left; }
            .pos-barra-movil[hidden] { display: none; }
            .pos-barra-movil strong { font-size: 1.15rem; font-variant-numeric: tabular-nums; }
            .pos-barra-movil .btn { margin-left: auto; min-height: var(--pos-toque); font-weight: 700; }
        }
        @media (max-width: 575.98px) { .pos-grid { grid-template-columns: repeat(auto-fill, minmax(7.2rem, 1fr)); gap: .45rem; } }

        /* Cobrar */
        .pos-formas { display: grid; grid-template-columns: repeat(auto-fit, minmax(7rem, 1fr)); gap: .5rem; }
        .pos-formas .btn { min-height: var(--pos-toque); font-size: .875rem; display: flex; flex-direction: row; align-items: center; justify-content: center; gap: .4rem; padding: .3rem .5rem;
                           --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); font-weight: 600; }
        .pos-formas .btn i { font-size: 1rem; }
        .pos-formas .btn-check:checked + .btn { background: #0e7490; border-color: #0e7490; color: #fff; }
        .pos-rapidos { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; }
        .pos-rapidos .btn { flex: 1 1 4.5rem; min-height: var(--pos-toque); font-size: .875rem; padding: .25rem .5rem; font-weight: 600; --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }
        .pos-cambio { margin-top: .75rem; padding: .75rem 1rem; border-radius: .75rem; background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis);
                      display: flex; justify-content: space-between; align-items: center; font-size: 1.1rem; }
        .pos-cambio strong { font-size: 1.6rem; font-variant-numeric: tabular-nums; }
        .pos-cambio.falta { background: var(--bs-danger-bg-subtle); color: var(--bs-danger-text-emphasis); }
        .pos-hecho { text-align: center; padding: 1rem .5rem; }
        .pos-hecho__icono { width: 4.5rem; height: 4.5rem; border-radius: 50%; display: inline-grid; place-items: center; font-size: 2rem; background: var(--bs-success); color: #fff; }
    </style>

    <!-- ==================== La caja ==================== -->
    <div class="pos-barra">
        <a class="pos-caja-chip" href="<?php echo APP_URL; ?>posCaja/" title="Ver la caja">
            <i class="fas fa-circle text-success" style="font-size:.55rem;" aria-hidden="true"></i>
            Caja abierta desde las <?php echo date('H:i', strtotime($caja['caja_apertura'])); ?>
            · efectivo <strong id="posEfectivoCaja"><?php echo $posDinero($resumen['esperado']); ?></strong>
        </a>
        <span class="text-body-secondary small"><?php echo (int)$resumen['ventas']; ?> ventas · <?php echo $posDinero($resumen['vendido']); ?></span>
        <a class="small ms-auto" href="<?php echo APP_URL; ?>posVentas/"><i class="fas fa-receipt me-1" aria-hidden="true"></i>Ventas de hoy</a>
    </div>

    <div class="pos-vender">
        <!-- ==================== El catálogo ==================== -->
        <section aria-label="Productos">
            <div class="pos-buscar">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="posBuscar" class="form-control" placeholder="Buscar por nombre o código"
                       aria-label="Buscar producto por nombre o código" autocomplete="off" enterkeyhint="search">
            </div>
            <div class="pos-cats" role="group" aria-label="Categorías">
                <button type="button" class="btn btn-outline-secondary" data-cat="0" aria-pressed="true">Todo</button>
                <?php foreach ($categorias as $c): if ((int)$c['productos'] === 0) { continue; } ?>
                    <button type="button" class="btn btn-outline-secondary" data-cat="<?php echo (int)$c['categoria_id']; ?>" aria-pressed="false"><?php echo $posH($c['categoria_nombre']); ?></button>
                <?php endforeach; ?>
            </div>
            <div class="pos-grid" id="posGrid"></div>
            <p class="text-center text-body-secondary py-4 mb-0" id="posSinProductos" hidden>
                <?php if (!$productos): ?>
                    Aún no hay productos. <a href="<?php echo APP_URL; ?>posProductos/">Registre el primero</a>.
                <?php else: ?>
                    Ningún producto coincide con la búsqueda.
                <?php endif; ?>
            </p>
        </section>

        <!-- ==================== El carrito ==================== -->
        <div class="pos-velo" id="posVelo"></div>
        <aside class="pos-tarjeta pos-carrito" id="posCarrito" aria-label="Carrito">
            <div class="pos-carrito__cab">
                <strong><i class="fas fa-basket-shopping me-1" aria-hidden="true"></i>Venta actual</strong>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-link text-danger" id="posVaciar">Vaciar</button>
                    <button type="button" class="btn btn-sm btn-link d-lg-none" id="posCerrarCarrito" aria-label="Cerrar el carrito"><i class="fas fa-chevron-down"></i></button>
                </div>
            </div>
            <div class="pos-carrito__lineas" id="posLineas"></div>
            <div class="pos-carrito__pie">
                <div id="posClienteCaja"></div>
                <div id="posDescuentoCaja" hidden>
                    <div class="d-flex gap-2 mb-2">
                        <select class="form-select" id="posDescTipo" style="max-width:6.5rem;" aria-label="Tipo de descuento">
                            <option value="valor">$</option><option value="porcentaje">%</option>
                        </select>
                        <input type="text" inputmode="decimal" class="form-control text-end" id="posDescValor" placeholder="0.00" aria-label="Descuento">
                    </div>
                    <input type="text" class="form-control mb-2" id="posDescMotivo" maxlength="150" placeholder="Motivo del descuento" aria-label="Motivo del descuento">
                    <?php if (!$autoriza): ?>
                        <div class="pos-autoriza small">
                            <div class="mb-1"><i class="fas fa-user-shield me-1" aria-hidden="true"></i>Lo autoriza un supervisor:</div>
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" id="posDescAutU" placeholder="Usuario" autocomplete="off" aria-label="Usuario del supervisor">
                                <input type="password" class="form-control form-control-sm" id="posDescAutC" placeholder="Contraseña" autocomplete="new-password" aria-label="Contraseña del supervisor">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="pos-extra">
                    <button type="button" class="btn btn-outline-secondary" id="posBtnCliente"><i class="fas fa-user me-1" aria-hidden="true"></i>Cliente</button>
                    <button type="button" class="btn btn-outline-secondary" id="posBtnDescuento"><i class="fas fa-tag me-1" aria-hidden="true"></i>Descuento</button>
                </div>
                <div class="pos-tot small text-body-secondary" id="posFilaSub" hidden><span>Subtotal</span><span id="posSub">$0.00</span></div>
                <div class="pos-tot small text-danger" id="posFilaDesc" hidden><span>Descuento</span><span id="posDesc">−$0.00</span></div>
                <div class="pos-tot pos-tot--grande"><span>Total</span><span id="posTotal">$0.00</span></div>
                <button type="button" class="btn btn-primary pos-btn w-100" id="posCobrar" disabled>
                    <i class="fas fa-hand-holding-dollar" aria-hidden="true"></i><span>Cobrar</span></button>
            </div>
        </aside>
    </div>

    <button type="button" class="pos-barra-movil" id="posBarraMovil" hidden aria-label="Ver la venta actual">
        <span><i class="fas fa-basket-shopping me-1" aria-hidden="true"></i><span id="posBarraN">0</span> art.<br><strong id="posBarraTotal">$0.00</strong></span>
        <span class="btn btn-light btn-sm">Ver y cobrar <i class="fas fa-chevron-up ms-1" aria-hidden="true"></i></span>
    </button>

    <!-- ==================== Cobrar ==================== -->
    <div class="modal fade pos-hoja" id="posModalCobro" tabindex="-1" aria-labelledby="posCobroTitulo">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="posFormCobro" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="posCobroTitulo">Cobrar <span id="posCobroTotal"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" id="posCobroCuerpo">
                        <p class="form-label fw-semibold mb-2">¿Cómo paga?</p>
                        <div class="pos-formas" role="radiogroup" aria-label="Forma de pago">
                            <?php foreach ($datosFormas as $i => $f): ?>
                                <input type="radio" class="btn-check" name="forma" id="posForma<?php echo $f['id']; ?>" value="<?php echo $f['id']; ?>" <?php echo $i === 0 ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-secondary" for="posForma<?php echo $f['id']; ?>">
                                    <i class="fas <?php echo $iconoForma[$f['codigo']] ?? 'fa-coins'; ?>" aria-hidden="true"></i><?php echo $posH($f['nombre']); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div id="posEfectivo" class="mt-3">
                            <label for="posRecibido" class="form-label fw-semibold mb-1">¿Con cuánto paga?</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">$</span>
                                <input type="text" inputmode="decimal" class="form-control text-end fw-bold" id="posRecibido" placeholder="0.00">
                            </div>
                            <div class="pos-rapidos" id="posRapidos"></div>
                            <div class="pos-cambio" id="posCambio"><span>Cambio</span><strong id="posCambioValor">$0.00</strong></div>
                        </div>
                        <div id="posRefCaja" class="mt-3" hidden>
                            <label for="posReferencia" class="form-label fw-semibold mb-1">Número de comprobante</label>
                            <input type="text" class="form-control" id="posReferencia" maxlength="60" placeholder="Ej.: 004512">
                        </div>
                        <div id="posMonederoNota" class="alert alert-info small mt-3 mb-0" hidden></div>
                    </div>
                    <div class="modal-footer" id="posCobroPie">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                        <button type="submit" class="btn btn-primary pos-btn" id="posConfirmar"><i class="fas fa-check" aria-hidden="true"></i>Registrar venta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';
        var PRODUCTOS = <?php echo json_encode($datosProductos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
        var FORMAS    = <?php echo json_encode($datosFormas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
        var AUTORIZA  = <?php echo $autoriza ? 'true' : 'false'; ?>;
        var CLAVE_LS  = 'pos_carrito_<?php echo (int)($_SESSION['usuarioid'] ?? 0); ?>';
        var porId = {}; PRODUCTOS.forEach(function (p) { porId[p.id] = p; });

        var $ = function (id) { return document.getElementById(id); };
        var carrito = [];         // [{id, cant}]
        var cliente = null;       // {id, nombre, ident, celular, saldo}
        var categoria = 0;

        /* ---------- utilidades ---------- */
        var norm = function (s) { return (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
        var cent = function (v) { return Math.round((Number(v) || 0) * 100) / 100; };
        var num = function (s) { s = String(s || '').replace(/[$\s]/g, ''); if (/^\d+,\d{1,2}$/.test(s)) { s = s.replace(',', '.'); } return cent(parseFloat(s.replace(/,/g, '')) || 0); };
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
        var colores = ['#0e7490', '#15803d', '#b45309', '#7c3aed', '#be123c', '#1d4ed8', '#0f766e', '#a16207'];
        var guardar = function () { try { localStorage.setItem(CLAVE_LS, JSON.stringify({ c: carrito, cl: cliente })); } catch (e) {} };
        try {
            var g = JSON.parse(localStorage.getItem(CLAVE_LS) || 'null');
            if (g && Array.isArray(g.c)) { carrito = g.c.filter(function (l) { return porId[l.id] && l.cant > 0; }); cliente = g.cl || null; }
        } catch (e) {}

        /* ---------- catálogo ---------- */
        function pintarCatalogo() {
            var q = norm($('posBuscar').value.trim()), html = '', n = 0;
            PRODUCTOS.forEach(function (p) {
                if (categoria && p.cat !== categoria) { return; }
                if (q && norm(p.nombre).indexOf(q) === -1 && norm(p.codigo).indexOf(q) === -1) { return; }
                n++;
                var agotado = !p.servicio && p.stock <= 0;
                var bajo = !p.servicio && !agotado && p.minimo > 0 && p.stock <= p.minimo;
                var en = carrito.filter(function (l) { return l.id === p.id; })[0];
                var img = p.img ? '<img class="pos-prod__img" src="' + esc(p.img) + '" alt="" loading="lazy">'
                                : '<span class="pos-prod__ini" style="background:' + colores[p.id % colores.length] + '">' + esc(p.nombre.trim().charAt(0).toUpperCase()) + '</span>';
                html += '<button type="button" class="pos-prod' + (en ? ' en-carrito' : '') + '" data-id="' + p.id + '"' + (agotado ? ' disabled' : '') +
                        ' aria-label="' + esc(p.nombre + ', ' + posDinero(p.precio) + (agotado ? ', agotado' : '')) + '">' + img +
                        '<span class="pos-prod__en">' + (en ? fmtCant(en.cant) : '') + '</span>' +
                        (agotado ? '<span class="badge text-bg-secondary pos-prod__stock">Agotado</span>'
                                 : (bajo ? '<span class="badge text-bg-warning pos-prod__stock">Quedan ' + fmtCant(p.stock) + '</span>' : '')) +
                        '<span class="pos-prod__cuerpo"><span class="pos-prod__nombre">' + esc(p.nombre) + '</span>' +
                        '<span class="pos-prod__precio">' + posDinero(p.precio) + '</span></span></button>';
            });
            $('posGrid').innerHTML = html;
            $('posSinProductos').hidden = n > 0;
        }
        function fmtCant(c) { return String(cent(c)).replace(/\.0+$/, ''); }

        function agregar(id, cant) {
            var p = porId[id]; if (!p) { return; }
            var l = carrito.filter(function (x) { return x.id === id; })[0];
            var nueva = cent((l ? l.cant : 0) + (cant || 1));
            if (!p.servicio && nueva > p.stock) {
                aviso('Sólo quedan ' + fmtCant(p.stock) + ' de «' + p.nombre + '».'); return;
            }
            if (l) { l.cant = nueva; } else { carrito.push({ id: id, cant: nueva }); }
            if (navigator.vibrate) { try { navigator.vibrate(12); } catch (e) {} }
            refrescar();
            var b = document.querySelector('.pos-prod[data-id="' + id + '"]');
            if (b) { b.classList.remove('pulso'); void b.offsetWidth; b.classList.add('pulso'); }
        }
        function aviso(t) {
            if (window.Swal) { Swal.fire({ toast: true, position: 'top', icon: 'warning', title: t, showConfirmButton: false, timer: 2200 }); }
            else { alert(t); }
        }

        $('posGrid').addEventListener('click', function (e) {
            var b = e.target.closest('.pos-prod'); if (b && !b.disabled) { agregar(parseInt(b.dataset.id, 10), 1); }
        });
        $('posBuscar').addEventListener('input', pintarCatalogo);
        /* Enter: un código exacto (o un único resultado) va directo al carrito. */
        $('posBuscar').addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') { return; }
            e.preventDefault();
            var q = norm(this.value.trim()); if (!q) { return; }
            var exacto = PRODUCTOS.filter(function (p) { return norm(p.codigo) === q; })[0];
            var visibles = document.querySelectorAll('#posGrid .pos-prod:not([disabled])');
            var id = exacto ? exacto.id : (visibles.length === 1 ? parseInt(visibles[0].dataset.id, 10) : 0);
            if (id) { agregar(id, 1); this.value = ''; pintarCatalogo(); }
        });
        document.querySelector('.pos-cats').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-cat]'); if (!b) { return; }
            categoria = parseInt(b.dataset.cat, 10);
            this.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
            pintarCatalogo();
        });

        /* ---------- carrito ---------- */
        function totales() {
            var sub = 0;
            carrito.forEach(function (l) { sub += cent(porId[l.id].precio * l.cant); });
            sub = cent(sub);
            var desc = 0, v = num($('posDescValor').value);
            if (!$('posDescuentoCaja').hidden && v > 0) {
                desc = $('posDescTipo').value === 'porcentaje' ? cent(sub * Math.min(100, v) / 100) : Math.min(sub, v);
            }
            return { sub: sub, desc: cent(desc), total: cent(sub - desc), n: carrito.reduce(function (a, l) { return a + l.cant; }, 0) };
        }
        function refrescar() {
            var html = '';
            carrito.forEach(function (l, i) {
                var p = porId[l.id];
                html += '<div class="pos-linea"><span class="pos-linea__nombre">' + esc(p.nombre) + '</span>' +
                        '<span class="pos-linea__sub">' + posDinero(p.precio * l.cant) + '</span>' +
                        '<span class="pos-linea__ctrl">' +
                        '<button type="button" class="btn btn-outline-secondary pos-paso" data-menos="' + i + '" aria-label="Uno menos de ' + esc(p.nombre) + '"><i class="fas ' + (l.cant <= 1 ? 'fa-trash-can text-danger' : 'fa-minus') + '"></i></button>' +
                        '<input type="text" inputmode="decimal" class="form-control pos-linea__cant" data-cant="' + i + '" value="' + fmtCant(l.cant) + '" aria-label="Cantidad de ' + esc(p.nombre) + '">' +
                        '<button type="button" class="btn btn-outline-secondary pos-paso" data-mas="' + i + '" aria-label="Uno más de ' + esc(p.nombre) + '"><i class="fas fa-plus"></i></button>' +
                        '<span class="pos-linea__precio">' + posDinero(p.precio) + ' c/u</span></span></div>';
            });
            $('posLineas').innerHTML = html || '<div class="pos-carrito__vacio"><i class="fas fa-basket-shopping fa-2x mb-2 opacity-50" aria-hidden="true"></i><br>Toque un producto para agregarlo.</div>';
            var t = totales();
            $('posSub').textContent = posDinero(t.sub);
            $('posDesc').textContent = posDinero(-t.desc);
            $('posFilaSub').hidden = $('posFilaDesc').hidden = !(t.desc > 0);
            $('posTotal').textContent = posDinero(t.total);
            $('posCobrar').disabled = carrito.length === 0;
            $('posCobrar').querySelector('span').textContent = carrito.length ? 'Cobrar ' + posDinero(t.total) : 'Cobrar';
            $('posBarraMovil').hidden = carrito.length === 0;
            $('posBarraN').textContent = fmtCant(t.n);
            $('posBarraTotal').textContent = posDinero(t.total);
            pintarCliente();
            pintarCatalogo();
            guardar();
        }
        $('posLineas').addEventListener('click', function (e) {
            var b = e.target.closest('[data-menos],[data-mas]'); if (!b) { return; }
            if (b.dataset.mas !== undefined) { agregar(carrito[+b.dataset.mas].id, 1); return; }
            var l = carrito[+b.dataset.menos];
            l.cant = cent(l.cant - 1);
            if (l.cant <= 0) { carrito.splice(+b.dataset.menos, 1); }
            refrescar();
        });
        $('posLineas').addEventListener('change', function (e) {
            var i = e.target.dataset.cant; if (i === undefined) { return; }
            var l = carrito[+i], p = porId[l.id], v = num(e.target.value);
            if (v <= 0) { carrito.splice(+i, 1); }
            else if (!p.servicio && v > p.stock) { aviso('Sólo quedan ' + fmtCant(p.stock) + ' de «' + p.nombre + '».'); l.cant = cent(p.stock); }
            else { l.cant = v; }
            refrescar();
        });
        $('posVaciar').addEventListener('click', function () {
            if (!carrito.length) { return; }
            var vaciar = function () { carrito = []; cliente = null; limpiarDescuento(); refrescar(); };
            if (window.Swal) {
                Swal.fire({ icon: 'question', title: '¿Vaciar la venta actual?', showCancelButton: true, confirmButtonText: 'Sí, vaciar',
                            cancelButtonText: 'No', confirmButtonColor: '#dc3545' }).then(function (r) { if (r.isConfirmed) { vaciar(); } });
            } else if (confirm('¿Vaciar la venta actual?')) { vaciar(); }
        });

        /* ---------- descuento ---------- */
        function limpiarDescuento() { $('posDescValor').value = ''; $('posDescMotivo').value = ''; $('posDescuentoCaja').hidden = true;
            ['posDescAutU', 'posDescAutC'].forEach(function (id) { if ($(id)) { $(id).value = ''; } }); }
        $('posBtnDescuento').addEventListener('click', function () {
            var caja = $('posDescuentoCaja');
            if (caja.hidden) { caja.hidden = false; $('posDescValor').focus(); } else { limpiarDescuento(); }
            refrescar();
        });
        ['posDescValor', 'posDescTipo'].forEach(function (id) { $(id).addEventListener('input', refrescar); });

        /* ---------- cliente (opcional) ---------- */
        function pintarCliente() {
            var c = $('posClienteCaja');
            if (cliente) {
                c.innerHTML = '<div class="pos-cliente"><i class="fas fa-user" aria-hidden="true"></i><span class="flex-grow-1"><strong>' + esc(cliente.nombre) + '</strong>' +
                    (cliente.saldo > 0 ? '<br><small>Monedero: ' + posDinero(cliente.saldo) + '</small>' : '') + '</span>' +
                    '<button type="button" class="btn btn-sm btn-link" id="posQuitarCliente" aria-label="Quitar cliente"><i class="fas fa-xmark"></i></button></div>';
                $('posQuitarCliente').onclick = function () { cliente = null; refrescar(); };
            } else if (!c.querySelector('#posClienteQ')) {
                c.innerHTML = '';
            }
        }
        var temporizador;
        $('posBtnCliente').addEventListener('click', function () {
            if (cliente || $('posClienteQ')) { return; }
            $('posClienteCaja').innerHTML = '<input type="search" class="form-control" id="posClienteQ" placeholder="Nombre, cédula o celular" autocomplete="off">' +
                '<ul class="pos-resultados" id="posClienteRes" hidden></ul><div class="form-text">Opcional: la venta se puede hacer sin cliente.</div>';
            $('posClienteQ').focus();
            $('posClienteQ').addEventListener('input', function () {
                clearTimeout(temporizador);
                var q = this.value.trim();
                temporizador = setTimeout(function () {
                    if (q.length < 2) { $('posClienteRes').hidden = true; return; }
                    posEnviar({ modulo_arena: 'posBuscarClientes', q: q }).then(function (j) {
                        var r = $('posClienteRes'); if (!r) { return; }
                        r.innerHTML = (j.clientes || []).map(function (c, i) {
                            return '<li><button type="button" data-i="' + i + '"><strong>' + esc(c.nombre) + '</strong><br><small class="text-body-secondary">' +
                                   esc(c.ident) + (c.saldo > 0 ? ' · monedero ' + posDinero(c.saldo) : '') + '</small></button></li>';
                        }).join('') || '<li class="p-2 small text-body-secondary">Sin resultados. Regístrelo en Clientes si hace falta.</li>';
                        r.hidden = false;
                        r.onclick = function (e) { var b = e.target.closest('button[data-i]'); if (b) { cliente = j.clientes[+b.dataset.i]; $('posClienteCaja').innerHTML = ''; refrescar(); } };
                    });
                }, 250);
            });
        });

        /* ---------- el carrito en el teléfono ---------- */
        var abrirCarrito = function (si) { $('posCarrito').classList.toggle('abierto', si); $('posVelo').classList.toggle('abierto', si); };
        $('posBarraMovil').addEventListener('click', function () { abrirCarrito(true); });
        $('posVelo').addEventListener('click', function () { abrirCarrito(false); });
        $('posCerrarCarrito').addEventListener('click', function () { abrirCarrito(false); });

        /* ---------- cobrar ---------- */
        var modal = new bootstrap.Modal($('posModalCobro'));
        var formaSel = function () { var r = document.querySelector('input[name="forma"]:checked'); return FORMAS.filter(function (f) { return String(f.id) === (r ? r.value : ''); })[0]; };
        function pintarCobro() {
            var t = totales(), f = formaSel();
            $('posCobroTotal').textContent = posDinero(t.total);
            var efe = f && f.codigo === 'EFE';
            $('posEfectivo').hidden = !efe;
            $('posRefCaja').hidden = !(f && f.ref);
            var nota = $('posMonederoNota'), ok = true;
            if (f && f.monedero) {
                nota.hidden = false;
                if (!cliente) { nota.textContent = 'Para pagar con monedero, primero elija al cliente (botón «Cliente»).'; ok = false; }
                else if (cliente.saldo + 0.001 < t.total) { nota.textContent = cliente.nombre + ' tiene ' + posDinero(cliente.saldo) + ' en su monedero: no alcanza.'; ok = false; }
                else { nota.textContent = 'Se descontará ' + posDinero(t.total) + ' del monedero de ' + cliente.nombre + ' (le quedarán ' + posDinero(cliente.saldo - t.total) + ').'; }
            } else { nota.hidden = true; }
            if (efe) {
                var rec = $('posRecibido').value.trim() === '' ? t.total : num($('posRecibido').value);
                var cambio = cent(rec - t.total);
                $('posCambio').classList.toggle('falta', cambio < 0);
                $('posCambio').firstElementChild.textContent = cambio < 0 ? 'Falta' : 'Cambio';
                $('posCambioValor').textContent = posDinero(Math.abs(cambio));
                if (cambio < 0) { ok = false; }
                /* Atajos: exacto y los billetes que cubren el total. */
                var opciones = [t.total], base = Math.ceil(t.total);
                [base, Math.ceil(t.total / 5) * 5, Math.ceil(t.total / 10) * 10, 20, 50, 100].forEach(function (v) {
                    if (v >= t.total && opciones.indexOf(v) === -1 && opciones.length < 5) { opciones.push(v); }
                });
                $('posRapidos').innerHTML = opciones.map(function (v, i) {
                    return '<button type="button" class="btn btn-outline-secondary" data-v="' + v + '">' + (i === 0 ? 'Exacto' : posDinero(v).replace('.00', '')) + '</button>';
                }).join('');
            }
            $('posConfirmar').disabled = !ok;
        }
        $('posCobrar').addEventListener('click', function () {
            if (!carrito.length) { return; }
            var t = totales();
            if (!$('posDescuentoCaja').hidden && t.desc > 0 && $('posDescMotivo').value.trim().length < 4) { aviso('Escriba el motivo del descuento.'); $('posDescMotivo').focus(); return; }
            $('posRecibido').value = ''; $('posReferencia').value = '';
            restaurarCobro();
            pintarCobro();
            abrirCarrito(false);
            modal.show();
        });
        $('posModalCobro').addEventListener('shown.bs.modal', function () { if (!$('posEfectivo').hidden && matchMedia('(min-width: 576px)').matches) { $('posRecibido').focus(); } });
        document.querySelectorAll('input[name="forma"]').forEach(function (r) { r.addEventListener('change', pintarCobro); });
        $('posRecibido').addEventListener('input', pintarCobro);
        $('posRapidos').addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (b) { $('posRecibido').value = Number(b.dataset.v).toFixed(2); pintarCobro(); } });

        var cuerpoCobro = $('posCobroCuerpo').innerHTML, pieCobro = $('posCobroPie').innerHTML;
        function restaurarCobro() {
            if (!$('posEfectivo')) {   /* tras una venta, el cuerpo muestra el resultado: se repone */
                $('posCobroCuerpo').innerHTML = cuerpoCobro; $('posCobroPie').innerHTML = pieCobro;
                document.querySelectorAll('input[name="forma"]').forEach(function (r) { r.addEventListener('change', pintarCobro); });
                $('posRecibido').addEventListener('input', pintarCobro);
                $('posRapidos').addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (b) { $('posRecibido').value = Number(b.dataset.v).toFixed(2); pintarCobro(); } });
            }
        }

        $('posFormCobro').addEventListener('submit', function (e) {
            e.preventDefault();
            var f = formaSel(), t = totales();
            if (!f) { return; }
            if (f.ref && $('posReferencia').value.trim() === '') { aviso('Escriba el número de comprobante.'); $('posReferencia').focus(); return; }
            var datos = {
                modulo_arena: 'posVender',
                items: JSON.stringify(carrito.map(function (l) { return { id: l.id, cantidad: l.cant }; })),
                forma_id: f.id, recibido: f.codigo === 'EFE' ? $('posRecibido').value : '', referencia: $('posReferencia').value,
                cliente_id: cliente ? cliente.id : '',
                descuento: !$('posDescuentoCaja').hidden ? $('posDescValor').value : '', descuento_tipo: $('posDescTipo').value,
                descuento_motivo: $('posDescMotivo').value,
                aut_usuario: $('posDescAutU') ? $('posDescAutU').value : '', aut_clave: $('posDescAutC') ? $('posDescAutC').value : ''
            };
            var btn = $('posConfirmar'); btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Registrando…';
            posEnviar(datos).then(function (j) {
                if (!j.ok) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i>Registrar venta'; posAviso(j); return; }
                $('posCobroCuerpo').innerHTML = '<div class="pos-hecho"><span class="pos-hecho__icono"><i class="fas fa-check"></i></span>' +
                    '<h3 class="h4 mt-3 mb-1">Venta registrada</h3><p class="text-body-secondary mb-2">' + esc(j.numero) + ' · ' + esc(j.forma) + ' · ' + posDinero(j.total) + '</p>' +
                    (j.cambio > 0 ? '<div class="pos-cambio justify-content-center gap-3"><span>Entregue de cambio</span><strong>' + posDinero(j.cambio) + '</strong></div>' : '') +
                    '<div class="d-grid gap-2 mt-3">' +
                    '<a class="btn btn-outline-secondary pos-btn" href="' + j.ticket + '&imprimir=1" target="_blank" rel="noopener"><i class="fas fa-print"></i>Imprimir comprobante</a>' +
                    /* WhatsApp: la nota de venta con el modal del recibo abierto (PDF y mensaje, sin enlaces). */
                    '<a class="btn btn-outline-success pos-btn" href="' + j.ticket + '&whatsapp=1" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i>Enviar por WhatsApp</a>' +
                    '</div></div>';
                $('posCobroPie').innerHTML = '<button type="button" class="btn btn-primary pos-btn w-100" data-bs-dismiss="modal"><i class="fas fa-plus"></i>Nueva venta</button>';
                /* El stock de lo vendido baja también en pantalla. */
                carrito.forEach(function (l) { var p = porId[l.id]; if (!p.servicio) { p.stock = cent(p.stock - l.cant); } });
                carrito = []; cliente = null; limpiarDescuento(); refrescar();
                if (j.forma === 'Efectivo') {
                    var ef = $('posEfectivoCaja'); ef.textContent = posDinero(num(ef.textContent) + j.total);
                }
            });
        });

        refrescar();
    });
    </script>
<?php endif; ?>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
