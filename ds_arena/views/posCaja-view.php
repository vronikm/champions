<?php
/*
| Arena › Punto de Venta y Caja › Caja (migración 095).
|
| La caja de cada usuario: cuánto efectivo debería haber y de dónde sale
| (saldo inicial, ventas, otros ingresos y egresos), lo cobrado por otros
| medios, los
| movimientos y el cierre con arqueo. ?id= muestra una caja concreta (las
| cerradas guardan sus cifras del momento del cierre).
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Caja';
$vistaActual = 'posCaja';

$supervisa = $pos->supervisaCajas();
$miCaja    = $pos->cajaAbierta();
$verId     = (int)($_GET['id'] ?? 0);
$caja      = $verId ? $pos->caja($verId) : $miCaja;
if ($caja && $verId && (int)$caja['caja_usuarioid'] !== (int)($_SESSION['usuarioid'] ?? 0) && !$supervisa) {
    $caja = null;   /* la caja de otro, sin permiso de supervisión */
}
$abierta = $caja && $caja['caja_estado'] === 'A';
$propia  = $caja && (int)$caja['caja_usuarioid'] === (int)($_SESSION['usuarioid'] ?? 0);

if ($caja) {
    $r = !$abierta && $caja['caja_resumen'] ? (json_decode($caja['caja_resumen'], true) ?: $pos->resumenCaja($caja)) : $pos->resumenCaja($caja);
    $movs = $pos->movimientosCaja($caja);
}
$cajas  = $pos->cajas(40);
$formas = array_values(array_filter($pos->formasCobro(), static fn ($f) => $f['forma_esmonedero'] !== 'S'));
$puedeMover = puede_crear('posCaja');
/* Abrir caja: con permiso sobre Caja o sobre Vender (las mismas reglas que abrirCaja). */
$puedeAbrir = puede_crear('posCaja') || puede_crear('posVenta');
$sedesCaja  = $puedeAbrir && !$miCaja ? $pos->sedesCaja() : [];

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";
?>
<style>
    .caja-cab { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; margin-bottom: 1rem; }
    .caja-estado { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .8rem; border-radius: 2rem; font-weight: 600; font-size: .9rem; }
    .caja-estado--a { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
    .caja-estado--c { background: var(--bs-secondary-bg-subtle); color: var(--bs-secondary-text-emphasis); }
    .caja-acciones { display: flex; flex-wrap: wrap; gap: .5rem; margin-left: auto; }
    .caja-cuentas { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 1rem; margin-bottom: 1rem; }
    .caja-cuenta { list-style: none; margin: 0; padding: 0; }
    .caja-cuenta li { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border-bottom: 1px dashed var(--bs-border-color); font-variant-numeric: tabular-nums; }
    .caja-cuenta li span:first-child { color: var(--bs-secondary-color); }
    .caja-cuenta li.caja-total { border-bottom: 0; border-top: 2px solid var(--bs-border-color); margin-top: .25rem; padding-top: .6rem; font-size: 1.25rem; font-weight: 800; }
    .caja-cuenta li.caja-total span:first-child { color: var(--bs-body-color); }
    .caja-arqueo { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; text-align: center; }
    .caja-arqueo div { padding: .6rem; border-radius: .6rem; background: var(--bs-tertiary-bg); }
    .caja-arqueo strong { display: block; font-size: 1.2rem; font-variant-numeric: tabular-nums; }
    .caja-dif-cero { background: var(--bs-success-bg-subtle) !important; color: var(--bs-success-text-emphasis); }
    .caja-dif-mal { background: var(--bs-danger-bg-subtle) !important; color: var(--bs-danger-text-emphasis); }
    .caja-dif-mas { background: var(--bs-warning-bg-subtle) !important; color: var(--bs-warning-text-emphasis); }
    .caja-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .6rem; }
    .caja-chips button { border-radius: 2rem; font-size: .8rem; --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }
    .caja-hist td, .caja-hist th { white-space: nowrap; vertical-align: middle; }
    @media (max-width: 991.98px) { .caja-cuentas { grid-template-columns: 1fr; } }
    @media (max-width: 575.98px) {
        .caja-acciones { width: 100%; margin-left: 0; }
        .caja-acciones .btn { flex: 1 1 0; white-space: nowrap; padding-inline: .4rem; }
    }
</style>

<div class="pos">
<?php if (!$caja): ?>
    <?php /* Abrir la caja aquí mismo (2026-11-26): antes el botón llevaba a
             «Vender», y un rol con permiso sobre Caja pero no sobre Vender
             recibía «Acceso denegado». abrirCaja ya admite cualquiera de los dos. */ ?>
    <div class="pos-tarjeta mx-auto p-3 p-md-4 mb-3" style="max-width: 34rem;">
        <div class="text-center mb-3">
            <i class="fas fa-cash-register fa-2x text-body-secondary mb-2" aria-hidden="true"></i>
            <h2 class="h5 mb-1">No tiene una caja abierta</h2>
            <p class="text-body-secondary mb-0">Para vender o registrar ingresos y egresos, ábrala con el efectivo inicial (el «sencillo» para dar cambio). Si no tiene, deje 0.</p>
        </div>
        <?php if ($puedeAbrir): ?>
            <form id="cajaAbrir" autocomplete="off">
                <label for="cajaInicial" class="form-label fw-semibold">Efectivo inicial</label>
                <div class="input-group mb-2">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="decimal" class="form-control text-end fw-bold" id="cajaInicial" value="0.00" required>
                </div>
                <details class="mb-3">
                    <summary class="small text-primary" style="cursor:pointer;"><i class="fas fa-calculator me-1" aria-hidden="true"></i>Contar billetes y monedas</summary>
                    <p class="small text-body-secondary my-2">Escriba cuántos hay de cada uno: el total se pone solo.</p>
                    <?php echo $posConteoHtml('cajaConteoAbrir'); ?>
                </details>
                <?php if (count($sedesCaja) > 1): ?>
                    <label for="cajaSede" class="form-label fw-semibold">Sede</label>
                    <select class="form-select mb-3" id="cajaSede">
                        <?php foreach ($sedesCaja as $s): ?><option value="<?php echo (int)$s['sede_id']; ?>"><?php echo $posH($s['sede_nombre']); ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary w-100 pos-btn pos-btn--normal"><i class="fas fa-lock-open" aria-hidden="true"></i>Abrir caja</button>
                <p class="small text-body-secondary text-center mt-2 mb-0">Queda a su nombre (<?php echo $posH(ds_nombre_usuario()); ?>).</p>
            </form>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var form = document.getElementById('cajaAbrir'), conteo = document.getElementById('cajaConteoAbrir'), ini = document.getElementById('cajaInicial');
                conteo.addEventListener('input', function () { ini.value = posContar(conteo).total.toFixed(2); });
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var c = posContar(conteo), b = form.querySelector('button[type=submit]');
                    var datos = { modulo_arena: 'posAbrirCaja', saldo_inicial: ini.value, sede: (document.getElementById('cajaSede') || {}).value || '' };
                    if (c.total > 0) { datos.conteo = c.json; }
                    b.disabled = true;
                    posEnviar(datos).then(function (j) { b.disabled = false; posAviso(j); });
                });
            });
            </script>
        <?php else: ?>
            <p class="alert alert-warning mb-0 small">Su rol no puede abrir caja. Pida a un administrador el permiso «crear» sobre «Caja» en Core › Roles.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="caja-cab">
        <span class="caja-estado caja-estado--<?php echo $abierta ? 'a' : 'c'; ?>">
            <i class="fas <?php echo $abierta ? 'fa-lock-open' : 'fa-lock'; ?>" aria-hidden="true"></i><?php echo $abierta ? 'Caja abierta' : 'Caja cerrada'; ?></span>
        <span class="text-body-secondary">
            <strong class="text-body"><?php echo $posH($caja['usuario_usuario']); ?></strong>
            · desde <?php echo date('d/m/Y H:i', strtotime($caja['caja_apertura'])); ?>
            <?php if (!$abierta): ?> hasta <?php echo date('d/m/Y H:i', strtotime($caja['caja_cierre'])); ?><?php endif; ?>
            <?php if ($caja['sede_nombre']): ?> · <?php echo $posH($caja['sede_nombre']); ?><?php endif; ?>
        </span>
        <?php if ($abierta): ?>
            <div class="caja-acciones pos-no-imprimir">
                <?php if ($propia && $puedeMover): ?>
                    <button type="button" class="btn btn-outline-success pos-btn" data-mov="I"><i class="fas fa-plus" aria-hidden="true"></i>Ingreso</button>
                    <button type="button" class="btn btn-outline-danger pos-btn" data-mov="E"><i class="fas fa-minus" aria-hidden="true"></i>Egreso</button>
                <?php endif; ?>
                <?php if ($propia || $supervisa): ?>
                    <button type="button" class="btn btn-primary pos-btn" id="cajaBtnCerrar"><i class="fas fa-lock" aria-hidden="true"></i>Cerrar caja</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <button type="button" class="btn btn-outline-secondary ms-auto pos-no-imprimir" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir</button>
        <?php endif; ?>
    </div>

    <div class="pos-kpis">
        <div class="pos-kpi pos-kpi--acento">
            <span class="pos-kpi__etq"><i class="fas fa-money-bill-wave" aria-hidden="true"></i>Efectivo que debe haber</span>
            <strong class="pos-kpi__cifra"><?php echo $posDinero($r['esperado']); ?></strong>
            <span class="pos-kpi__nota"><?php echo $abierta ? 'en el cajón, ahora' : 'al cerrar'; ?></span>
        </div>
        <div class="pos-kpi">
            <span class="pos-kpi__etq"><i class="fas fa-basket-shopping" aria-hidden="true"></i>Ventas</span>
            <strong class="pos-kpi__cifra"><?php echo $posDinero($r['vendido']); ?></strong>
            <span class="pos-kpi__nota"><?php echo (int)$r['ventas']; ?> ventas<?php echo $r['anuladas'] ? ' · ' . (int)$r['anuladas'] . ' anulada(s)' : ''; ?></span>
        </div>
        <?php /* Los cobros de reservas ya no entran en la caja: sólo los
                 muestra una caja cerrada antes de ese cambio que los tenía. */
        if ((int)($r['cobrosReservas'] ?? 0) > 0): ?>
        <div class="pos-kpi">
            <span class="pos-kpi__etq"><i class="fas fa-calendar-check" aria-hidden="true"></i>Cobros de reservas</span>
            <strong class="pos-kpi__cifra"><?php echo (int)$r['cobrosReservas']; ?></strong>
            <span class="pos-kpi__nota">en efectivo <?php echo $posDinero($r['reservasEfectivo']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!$abierta): $dif = (float)$caja['caja_diferencia']; ?>
            <div class="pos-kpi <?php echo abs($dif) < 0.01 ? 'pos-kpi--ok' : 'pos-kpi--alerta'; ?>">
                <span class="pos-kpi__etq"><i class="fas fa-scale-balanced" aria-hidden="true"></i>Diferencia del arqueo</span>
                <strong class="pos-kpi__cifra"><?php echo abs($dif) < 0.01 ? 'Cuadra' : $posDinero($dif); ?></strong>
                <span class="pos-kpi__nota"><?php echo abs($dif) < 0.01 ? 'contado = esperado' : ($dif > 0 ? 'sobró dinero' : 'faltó dinero'); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="caja-cuentas">
        <div class="pos-tarjeta p-3">
            <h3 class="h6 fw-bold mb-2">De dónde sale el efectivo</h3>
            <ul class="caja-cuenta">
                <li><span>Saldo inicial</span><span><?php echo $posDinero($r['inicial']); ?></span></li>
                <li><span>+ Ventas en efectivo</span><span><?php echo $posDinero($r['ventasEfectivo']); ?></span></li>
                <?php if ((float)($r['reservasEfectivo'] ?? 0) > 0): ?><li><span>+ Cobros de reservas en efectivo</span><span><?php echo $posDinero($r['reservasEfectivo']); ?></span></li><?php endif; ?>
                <?php if ($r['depositosEfectivo'] > 0): ?><li><span>+ Depósitos al monedero</span><span><?php echo $posDinero($r['depositosEfectivo']); ?></span></li><?php endif; ?>
                <li><span>+ Otros ingresos</span><span><?php echo $posDinero($r['ingresosEfectivo']); ?></span></li>
                <li><span>− Egresos</span><span class="pos-neg"><?php echo $posDinero(-$r['egresosEfectivo']); ?></span></li>
                <?php if ($r['devolucionesEfectivo'] > 0): ?><li><span>− Devoluciones de reservas</span><span class="pos-neg"><?php echo $posDinero(-$r['devolucionesEfectivo']); ?></span></li><?php endif; ?>
                <li class="caja-total"><span>Debe haber</span><span><?php echo $posDinero($r['esperado']); ?></span></li>
            </ul>
            <?php if (!$abierta): ?>
                <div class="caja-arqueo mt-3">
                    <div><small>Esperado</small><strong><?php echo $posDinero($caja['caja_esperado']); ?></strong></div>
                    <div><small>Contado</small><strong><?php echo $posDinero($caja['caja_contado']); ?></strong></div>
                    <?php $d = (float)$caja['caja_diferencia']; ?>
                    <div class="<?php echo abs($d) < 0.01 ? 'caja-dif-cero' : ($d < 0 ? 'caja-dif-mal' : 'caja-dif-mas'); ?>"><small>Diferencia</small><strong><?php echo $posDinero($d); ?></strong></div>
                </div>
                <?php if ($caja['caja_observacion']): ?><p class="small mt-2 mb-0"><i class="fas fa-comment-dots me-1" aria-hidden="true"></i><?php echo $posH($caja['caja_observacion']); ?></p><?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="pos-tarjeta p-3">
            <h3 class="h6 fw-bold mb-2">Por forma de pago</h3>
            <?php if (!$r['porForma']): ?>
                <p class="text-body-secondary small mb-0">Todavía no hay cobros.</p>
            <?php else: ?>
                <ul class="caja-cuenta">
                    <?php foreach ($r['porForma'] as $nombre => $f): ?>
                        <li><span><?php echo $posH($nombre); ?><?php echo $f['efectivo'] ? '' : ' <small class="text-body-secondary">(no está en el cajón)</small>'; ?></span>
                            <span class="<?php echo $f['total'] < 0 ? 'pos-neg' : ''; ?>"><?php echo $posDinero($f['total']); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($r['monederoAplicado'] > 0): ?><p class="small text-body-secondary mt-2 mb-0">Pagado con monedero: <?php echo $posDinero($r['monederoAplicado']); ?> (saldo a favor de clientes, no entra dinero).</p><?php endif; ?>
            <?php if ($r['descuentos'] > 0): ?><p class="small text-body-secondary mt-1 mb-0">Descuentos concedidos: <?php echo $posDinero($r['descuentos']); ?></p><?php endif; ?>
        </div>
    </div>

    <div class="pos-tarjeta mb-3">
        <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0">Movimientos <small class="text-body-secondary fw-normal">· <?php echo count($movs); ?></small></h3></div>
        <?php if (!$movs): ?>
            <p class="text-body-secondary p-3 mb-0">Sin movimientos todavía.</p>
        <?php else: ?>
            <ul class="pos-lista">
                <?php foreach ($movs as $m):
                    $icono = ['venta' => 'fa-basket-shopping text-primary', 'reserva' => 'fa-calendar-check text-info', 'ingreso' => 'fa-arrow-down text-success', 'egreso' => 'fa-arrow-up text-danger'][$m['tipo']] ?? 'fa-circle'; ?>
                    <li class="pos-fila">
                        <span class="pos-fila__titulo<?php echo $m['anulada'] ? ' pos-tachada' : ''; ?>">
                            <i class="fas <?php echo $icono; ?> me-1" aria-hidden="true"></i>
                            <?php if ($m['tipo'] === 'venta'): ?><a href="<?php echo APP_URL; ?>posTicket/?id=<?php echo (int)$m['id']; ?>" target="_blank" rel="noopener"><?php echo $posH($m['titulo']); ?></a>
                            <?php else: ?><?php echo $posH($m['titulo']); ?><?php endif; ?>
                            <?php if ($m['anulada']): ?><span class="badge text-bg-secondary ms-1">Anulada</span><?php endif; ?>
                        </span>
                        <span class="pos-fila__valor <?php echo $m['signo'] < 0 ? 'pos-neg' : ''; ?><?php echo $m['anulada'] ? ' pos-tachada' : ''; ?>"><?php echo $posDinero($m['signo'] * $m['valor']); ?></span>
                        <span class="pos-fila__meta"><span><?php echo date('H:i', $m['ts']); ?></span><span><?php echo $posH($m['forma']); ?></span>
                            <?php if ($m['detalle'] !== ''): ?><span><?php echo $posH($m['detalle']); ?></span><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

    <!-- ==================== Historial ==================== -->
    <div class="pos-tarjeta pos-no-imprimir">
        <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0"><?php echo $supervisa ? 'Cajas' : 'Mis cajas'; ?> <small class="text-body-secondary fw-normal">· últimas <?php echo count($cajas); ?></small></h3></div>
        <?php if (!$cajas): ?>
            <p class="text-body-secondary p-3 mb-0">Aún no hay cajas.</p>
        <?php else: ?>
            <ul class="pos-lista">
                <?php foreach ($cajas as $c): $d = $c['caja_diferencia'] !== null ? (float)$c['caja_diferencia'] : null; ?>
                    <li class="pos-fila">
                        <a class="pos-fila__titulo" href="<?php echo APP_URL; ?>posCaja/?id=<?php echo (int)$c['caja_id']; ?>">
                            <?php echo date('d/m/Y H:i', strtotime($c['caja_apertura'])); ?> · <?php echo $posH($c['usuario_usuario']); ?></a>
                        <span class="pos-fila__valor">
                            <?php if ($c['caja_estado'] === 'A'): ?><span class="badge text-bg-success">Abierta</span>
                            <?php elseif (abs((float)$d) < 0.01): ?><span class="badge text-bg-success">Cuadra</span>
                            <?php else: ?><span class="badge <?php echo $d < 0 ? 'text-bg-danger' : 'text-bg-warning'; ?>"><?php echo $d < 0 ? 'Faltó ' : 'Sobró '; ?><?php echo $posDinero(abs($d)); ?></span><?php endif; ?>
                        </span>
                        <span class="pos-fila__meta">
                            <span><?php echo (int)$c['ventas']; ?> ventas · <?php echo $posDinero($c['vendido']); ?></span>
                            <?php if ($c['caja_estado'] === 'C'): ?><span>Cerró <?php echo date('d/m H:i', strtotime($c['caja_cierre'])); ?> · contado <?php echo $posDinero($c['caja_contado']); ?></span><?php endif; ?>
                            <?php if ($c['sede_nombre']): ?><span><?php echo $posH($c['sede_nombre']); ?></span><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($caja && $abierta): ?>
<!-- ==================== Ingreso / egreso ==================== -->
<div class="modal fade pos-hoja" id="cajaModalMov" tabindex="-1" aria-labelledby="cajaMovTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="cajaFormMov" autocomplete="off">
                <div class="modal-header"><h5 class="modal-title" id="cajaMovTitulo">Ingreso</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <input type="hidden" name="tipo" id="cajaMovTipo" value="I">
                    <div class="caja-chips" id="cajaMovChips"></div>
                    <label class="form-label fw-semibold" for="cajaMovConcepto">Concepto</label>
                    <input type="text" class="form-control mb-3" id="cajaMovConcepto" name="concepto" maxlength="120" required placeholder="¿De qué se trata?">
                    <label class="form-label fw-semibold" for="cajaMovValor">Valor</label>
                    <div class="input-group input-group-lg mb-3"><span class="input-group-text">$</span>
                        <input type="text" inputmode="decimal" class="form-control text-end fw-bold" id="cajaMovValor" name="valor" required placeholder="0.00"></div>
                    <label class="form-label fw-semibold" for="cajaMovForma">Forma</label>
                    <select class="form-select mb-3" id="cajaMovForma" name="forma">
                        <?php foreach ($formas as $f): ?><option value="<?php echo $posH($f['forma_codigo']); ?>"><?php echo $posH($f['forma_nombre']); ?></option><?php endforeach; ?>
                    </select>
                    <label class="form-label" for="cajaMovDetalle">Detalle <small class="text-body-secondary">(opcional)</small></label>
                    <input type="text" class="form-control" id="cajaMovDetalle" name="detalle" maxlength="200" placeholder="Ej.: factura 001-002-345">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-primary pos-btn" id="cajaMovGuardar">Registrar</button></div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== Cerrar caja ==================== -->
<div class="modal fade pos-hoja" id="cajaModalCerrar" tabindex="-1" aria-labelledby="cajaCerrarTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form id="cajaFormCerrar" autocomplete="off">
                <div class="modal-header"><h5 class="modal-title" id="cajaCerrarTitulo"><i class="fas fa-lock me-2" aria-hidden="true"></i>Cerrar caja</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <p class="mb-2">Cuente el efectivo del cajón. Puede escribir cuántos billetes y monedas hay de cada uno, o el total directamente.</p>
                    <?php echo $posConteoHtml('cajaConteo'); ?>
                    <label class="form-label fw-semibold mt-3" for="cajaContado">Efectivo contado</label>
                    <div class="input-group input-group-lg mb-3"><span class="input-group-text">$</span>
                        <input type="text" inputmode="decimal" class="form-control text-end fw-bold" id="cajaContado" placeholder="0.00" required></div>
                    <div class="caja-arqueo mb-3">
                        <div><small>Debe haber</small><strong><?php echo $posDinero($r['esperado']); ?></strong></div>
                        <div><small>Contado</small><strong id="cajaArqContado">$0.00</strong></div>
                        <div id="cajaArqDifCaja"><small id="cajaArqDifEt">Diferencia</small><strong id="cajaArqDif">—</strong></div>
                    </div>
                    <label class="form-label" for="cajaObs">Observación <small class="text-body-secondary" id="cajaObsAyuda">(opcional si cuadra)</small></label>
                    <textarea class="form-control" id="cajaObs" rows="2" maxlength="250" placeholder="Si no cuadra, explique por qué"></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger pos-btn"><i class="fas fa-lock" aria-hidden="true"></i>Cerrar caja</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = function (id) { return document.getElementById(id); };
    var ESPERADO = <?php echo json_encode((float)$r['esperado']); ?>;
    var CAJA = <?php echo (int)$caja['caja_id']; ?>;
    var num = function (s) { s = String(s || '').replace(/[$\s]/g, ''); if (/^\d+,\d{1,2}$/.test(s)) { s = s.replace(',', '.'); } return Math.round((parseFloat(s.replace(/,/g, '')) || 0) * 100) / 100; };
    var atajos = { I: ['Sencillo adicional', 'Pago de deuda de un cliente', 'Otro ingreso'],
                   E: ['Compra de hielo', 'Pago de servicios', 'Movilización', 'Retiro para depósito en banco', 'Limpieza'] };

    var modalMov = $('cajaModalMov') ? new bootstrap.Modal($('cajaModalMov')) : null;
    document.querySelectorAll('[data-mov]').forEach(function (b) {
        b.addEventListener('click', function () {
            var t = b.dataset.mov;
            $('cajaMovTipo').value = t;
            $('cajaMovTitulo').textContent = t === 'I' ? 'Registrar un ingreso' : 'Registrar un egreso (salida de dinero)';
            $('cajaMovConcepto').value = ''; $('cajaMovValor').value = ''; $('cajaMovDetalle').value = '';
            $('cajaMovChips').innerHTML = atajos[t].map(function (a) { return '<button type="button" class="btn btn-sm btn-outline-secondary">' + a + '</button>'; }).join('');
            $('cajaMovGuardar').className = 'btn pos-btn ' + (t === 'I' ? 'btn-success' : 'btn-danger');
            modalMov.show();
        });
    });
    if ($('cajaMovChips')) {
        $('cajaMovChips').addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { $('cajaMovConcepto').value = b.textContent; $('cajaMovValor').focus(); } });
        $('cajaFormMov').addEventListener('submit', function (e) {
            e.preventDefault();
            var b = $('cajaMovGuardar'); b.disabled = true;
            posEnviar({ modulo_arena: 'posMovimientoCaja', tipo: $('cajaMovTipo').value, concepto: $('cajaMovConcepto').value,
                        valor: $('cajaMovValor').value, forma: $('cajaMovForma').value, detalle: $('cajaMovDetalle').value })
                .then(function (j) { b.disabled = false; if (j.ok) { modalMov.hide(); } posAviso(j); });
        });
    }

    var modalCerrar = $('cajaModalCerrar') ? new bootstrap.Modal($('cajaModalCerrar')) : null;
    function arqueo() {
        var c = num($('cajaContado').value), d = Math.round((c - ESPERADO) * 100) / 100, caja = $('cajaArqDifCaja');
        $('cajaArqContado').textContent = posDinero(c);
        $('cajaArqDif').textContent = $('cajaContado').value.trim() === '' ? '—' : (Math.abs(d) < 0.01 ? 'Cuadra' : posDinero(d));
        $('cajaArqDifEt').textContent = Math.abs(d) < 0.01 ? 'Diferencia' : (d < 0 ? 'Falta' : 'Sobra');
        caja.className = $('cajaContado').value.trim() === '' ? '' : (Math.abs(d) < 0.01 ? 'caja-dif-cero' : (d < 0 ? 'caja-dif-mal' : 'caja-dif-mas'));
        $('cajaObsAyuda').textContent = Math.abs(d) < 0.01 || $('cajaContado').value.trim() === '' ? '(opcional si cuadra)' : '(obligatoria: no cuadra)';
    }
    if (modalCerrar) {
        $('cajaBtnCerrar').addEventListener('click', function () { modalCerrar.show(); });
        $('cajaConteo').addEventListener('input', function () { var c = posContar($('cajaConteo')); $('cajaContado').value = c.total.toFixed(2); arqueo(); });
        $('cajaContado').addEventListener('input', arqueo);
        $('cajaFormCerrar').addEventListener('submit', function (e) {
            e.preventDefault();
            var c = posContar($('cajaConteo'));
            var datos = { modulo_arena: 'posCerrarCaja', caja_id: CAJA, contado: $('cajaContado').value, observacion: $('cajaObs').value };
            if (c.total > 0 && Math.abs(c.total - num($('cajaContado').value)) < 0.005) { datos.conteo = c.json; }
            var go = function () { posEnviar(datos).then(function (j) { if (j.ok) { modalCerrar.hide(); } posAviso(j); }); };
            if (window.Swal) {
                Swal.fire({ icon: 'question', title: '¿Cerrar la caja?', text: 'Después no se podrá vender con ella. Para seguir vendiendo, abra otra.',
                            showCancelButton: true, confirmButtonText: 'Sí, cerrar', cancelButtonText: 'Volver', confirmButtonColor: '#dc3545' })
                    .then(function (r) { if (r.isConfirmed) { go(); } });
            } else if (confirm('¿Cerrar la caja?')) { go(); }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
