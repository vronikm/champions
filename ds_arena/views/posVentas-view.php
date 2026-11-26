<?php
/*
| Arena › Reportes › Ventas (migración 095; en el menú, después de «Pagos Reservas»).
|
| Las ventas de un día (o una búsqueda por número o cliente), con sus
| cifras: total, ticket promedio, margen, por forma de pago y lo más
| vendido. Cada venta: su comprobante y, con autorización, su anulación.
| Quien supervisa ve las de todos; el resto, las suyas.
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Ventas';
$vistaActual = 'posVentas';

$hoy = date('Y-m-d');
$dia = (string)($_GET['dia'] ?? $hoy);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || !strtotime($dia)) { $dia = $hoy; }
$buscar   = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 40));
$todos    = $pos->supervisaCajas() || $pos->puedeAutorizar();
$usuario  = $todos ? 0 : (int)($_SESSION['usuarioid'] ?? 0);
$ventas   = $pos->ventas($dia, $buscar, $usuario);
$resumen  = $pos->resumenVentasDia($dia, $usuario);
$autoriza = $pos->puedeAutorizar();
$puedeAnular = puede_crear('posVenta') || $autoriza;

$MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$DIAS  = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$t = strtotime($dia);
$fechaLarga = $DIAS[(int)date('N', $t)] . ' ' . date('j', $t) . ' de ' . $MESES[(int)date('n', $t)];

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";
?>
<style>
    .vt-nav { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .5rem; padding: .6rem; }
    .vt-nav a.btn { width: 2rem; height: 2rem; min-width: var(--pos-toque); min-height: var(--pos-toque); border-radius: 50%; display: inline-grid; place-items: center; padding: 0;
                    --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); }
    .vt-nav h2 { font-size: 1.15rem; font-weight: 700; margin: 0; text-align: center; }
    .vt-filtros { display: flex; flex-wrap: wrap; gap: .5rem; padding: 0 .6rem .6rem; }
    .vt-filtros > * { flex: 1 1 10rem; }
    .vt-dos { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .vt-barra { height: .45rem; border-radius: 1rem; background: var(--bs-tertiary-bg); overflow: hidden; margin-top: .2rem; }
    .vt-barra span { display: block; height: 100%; background: #0e7490; }
    @media (max-width: 767.98px) { .vt-dos { grid-template-columns: 1fr; } }
</style>

<div class="pos">
    <div class="pos-tarjeta mb-3">
        <?php if ($buscar === ''): ?>
            <div class="vt-nav">
                <a class="btn btn-outline-secondary" href="?dia=<?php echo date('Y-m-d', strtotime($dia . ' -1 day')); ?>" aria-label="Día anterior"><i class="fas fa-chevron-left"></i></a>
                <h2><?php echo $posH($fechaLarga); ?><?php if ($dia === $hoy): ?> <span class="badge text-bg-primary align-middle">Hoy</span><?php endif; ?></h2>
                <a class="btn btn-outline-secondary" href="?dia=<?php echo date('Y-m-d', strtotime($dia . ' +1 day')); ?>" aria-label="Día siguiente"><i class="fas fa-chevron-right"></i></a>
            </div>
        <?php endif; ?>
        <form method="get" class="vt-filtros" id="vtFiltros">
            <input type="date" name="dia" class="form-control form-control-sm" value="<?php echo $posH($dia); ?>" max="<?php echo $hoy; ?>" aria-label="Día" onchange="this.form.q.value='';this.form.submit()">
            <input type="search" name="q" class="form-control form-control-sm" value="<?php echo $posH($buscar); ?>" placeholder="Buscar por número o cliente" aria-label="Buscar venta">
            <?php if ($buscar !== ''): ?><a class="btn btn-outline-secondary" href="?dia=<?php echo $posH($dia); ?>" style="flex:0 0 auto;">Quitar búsqueda</a><?php endif; ?>
        </form>
    </div>

    <?php if ($buscar === ''): ?>
        <div class="pos-kpis">
            <div class="pos-kpi pos-kpi--acento"><span class="pos-kpi__etq"><i class="fas fa-sack-dollar" aria-hidden="true"></i>Vendido</span>
                <strong class="pos-kpi__cifra"><?php echo $posDinero($resumen['total']); ?></strong>
                <span class="pos-kpi__nota"><?php echo (int)$resumen['ventas']; ?> ventas<?php echo $todos ? '' : ' (suyas)'; ?></span></div>
            <div class="pos-kpi"><span class="pos-kpi__etq"><i class="fas fa-receipt" aria-hidden="true"></i>Ticket promedio</span>
                <strong class="pos-kpi__cifra"><?php echo $posDinero($resumen['ticket']); ?></strong>
                <span class="pos-kpi__nota">por venta</span></div>
            <?php if ($todos): ?>
                <div class="pos-kpi pos-kpi--ok"><span class="pos-kpi__etq"><i class="fas fa-chart-line" aria-hidden="true"></i>Ganancia estimada</span>
                    <strong class="pos-kpi__cifra"><?php echo $posDinero($resumen['margen']); ?></strong>
                    <span class="pos-kpi__nota">venta − costo de lo vendido</span></div>
            <?php endif; ?>
            <div class="pos-kpi<?php echo $resumen['anuladas'] ? ' pos-kpi--alerta' : ''; ?>"><span class="pos-kpi__etq"><i class="fas fa-ban" aria-hidden="true"></i>Anuladas</span>
                <strong class="pos-kpi__cifra"><?php echo (int)$resumen['anuladas']; ?></strong>
                <span class="pos-kpi__nota"><?php echo $resumen['anuladas'] ? $posDinero($resumen['anulado']) : 'ninguna'; ?><?php echo $resumen['descuentos'] > 0 ? ' · descuentos ' . $posDinero($resumen['descuentos']) : ''; ?></span></div>
        </div>
        <?php if ($resumen['ventas'] > 0): ?>
            <div class="vt-dos">
                <div class="pos-tarjeta p-3">
                    <h3 class="h6 fw-bold">Por forma de pago</h3>
                    <?php foreach ($resumen['formas'] as $f): $pct = $resumen['total'] > 0 ? (float)$f['total'] / $resumen['total'] * 100 : 0; ?>
                        <div class="mb-2"><div class="d-flex justify-content-between small"><span><?php echo $posH($f['forma_nombre']); ?> <span class="text-body-secondary">(<?php echo (int)$f['n']; ?>)</span></span><strong><?php echo $posDinero($f['total']); ?></strong></div>
                            <div class="vt-barra"><span style="width:<?php echo round($pct, 1); ?>%"></span></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="pos-tarjeta p-3">
                    <h3 class="h6 fw-bold">Lo más vendido</h3>
                    <ol class="mb-0 ps-3 small">
                        <?php foreach ($resumen['top'] as $p): ?>
                            <li class="mb-1"><?php echo $posH($p['nombre']); ?> · <strong><?php echo $posCant($p['cantidad']); ?></strong> <span class="text-body-secondary">(<?php echo $posDinero($p['total']); ?>)</span></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="pos-tarjeta">
        <div class="p-3 border-bottom d-flex align-items-center gap-2">
            <h3 class="h6 fw-bold mb-0"><?php echo $buscar !== '' ? 'Resultados' : 'Ventas del día'; ?> <small class="text-body-secondary fw-normal">· <?php echo count($ventas); ?></small></h3>
            <a class="btn btn-primary btn-sm ms-auto" href="<?php echo APP_URL; ?>posVenta/"><i class="fas fa-plus me-1" aria-hidden="true"></i>Nueva venta</a>
        </div>
        <?php if (!$ventas): ?>
            <p class="text-body-secondary text-center p-4 mb-0"><i class="fas fa-receipt fa-2x d-block mb-2 opacity-50" aria-hidden="true"></i>No hay ventas <?php echo $buscar !== '' ? 'que coincidan' : 'este día'; ?>.</p>
        <?php else: ?>
            <ul class="pos-lista">
                <?php foreach ($ventas as $v): $anulada = $v['venta_estado'] === 'X'; ?>
                    <li class="pos-fila">
                        <span class="pos-fila__titulo<?php echo $anulada ? ' pos-tachada' : ''; ?>"><?php echo $posH($v['venta_numero']); ?>
                            <?php if ($anulada): ?><span class="badge text-bg-secondary ms-1">Anulada</span><?php endif; ?>
                            <?php if (($v['venta_origen'] ?? 'P') === 'R'): ?><span class="badge text-bg-info ms-1" title="Registrada después en «Registrar ventas»: no entró en ninguna caja">Registrada</span><?php endif; ?></span>
                        <span class="pos-fila__valor<?php echo $anulada ? ' pos-tachada' : ''; ?>"><?php echo $posDinero($v['venta_total']); ?></span>
                        <span class="pos-fila__meta">
                            <span><i class="far fa-clock me-1" aria-hidden="true"></i><?php echo date($buscar !== '' ? 'd/m H:i' : 'H:i', strtotime($v['venta_fecharegistro'])); ?></span>
                            <span><?php echo $posH($v['formas']); ?></span>
                            <?php if ($v['cliente_nombre']): ?><span><i class="fas fa-user me-1" aria-hidden="true"></i><?php echo $posH($v['cliente_nombre']); ?></span><?php endif; ?>
                            <?php if ($todos): ?><span><?php echo $posH($v['vendedor']); ?></span><?php endif; ?>
                            <span class="w-100"><?php echo $posH(mb_strimwidth((string)$v['resumen'], 0, 90, '…')); ?></span>
                        </span>
                        <span class="d-flex gap-2 mt-1" style="grid-column:1/-1;">
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo APP_URL; ?>posTicket/?id=<?php echo (int)$v['venta_id']; ?>" target="_blank" rel="noopener"><i class="fas fa-receipt me-1" aria-hidden="true"></i>Comprobante</a>
                            <?php if (!$anulada && $puedeAnular): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger vt-anular" data-id="<?php echo (int)$v['venta_id']; ?>"
                                        data-numero="<?php echo $posH($v['venta_numero']); ?>" data-total="<?php echo $posH($posDinero($v['venta_total'])); ?>"><i class="fas fa-ban me-1" aria-hidden="true"></i>Anular</button>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($puedeAnular): ?>
<div class="modal fade pos-hoja" id="vtModalAnular" tabindex="-1" aria-labelledby="vtAnularTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="vtFormAnular" autocomplete="off">
                <div class="modal-header"><h5 class="modal-title" id="vtAnularTitulo"><i class="fas fa-ban me-2 text-danger" aria-hidden="true"></i>Anular venta <span id="vtAnularNum"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <p class="mb-2">Se devolverá el stock al inventario. Si la venta es de una caja ya cerrada, la devolución del dinero se registra como egreso de su caja abierta.</p>
                    <label class="form-label fw-semibold" for="vtMotivo">¿Por qué se anula?</label>
                    <textarea class="form-control mb-3" id="vtMotivo" rows="2" maxlength="200" required placeholder="Ej.: se cobró dos veces"></textarea>
                    <?php if (!$autoriza): ?>
                        <div class="pos-autoriza">
                            <div class="mb-2"><i class="fas fa-user-shield me-1" aria-hidden="true"></i>La autoriza un supervisor:</div>
                            <input type="text" class="form-control mb-2" id="vtAutU" placeholder="Usuario del supervisor" autocomplete="off">
                            <input type="password" class="form-control" id="vtAutC" placeholder="Contraseña" autocomplete="new-password">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger pos-btn"><i class="fas fa-ban" aria-hidden="true"></i>Anular</button></div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var m = new bootstrap.Modal(document.getElementById('vtModalAnular')), id = 0;
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.vt-anular'); if (!b) { return; }
        id = b.dataset.id;
        document.getElementById('vtAnularNum').textContent = b.dataset.numero + ' · ' + b.dataset.total;
        document.getElementById('vtMotivo').value = '';
        m.show();
    });
    document.getElementById('vtFormAnular').addEventListener('submit', function (e) {
        e.preventDefault();
        var u = document.getElementById('vtAutU'), c = document.getElementById('vtAutC');
        posEnviar({ modulo_arena: 'posAnularVenta', venta_id: id, motivo: document.getElementById('vtMotivo').value,
                    aut_usuario: u ? u.value : '', aut_clave: c ? c.value : '' })
            .then(function (j) { if (j.ok) { m.hide(); } if (c) { c.value = ''; } posAviso(j); });
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
