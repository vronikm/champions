<?php
/*
| Detalle de una reserva con su historial de abonos.
| Es donde se registra cada pago hasta completar el total.
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$id      = (int)($_GET['id'] ?? 0);
$reserva = $insArena->reserva($id);

if ($reserva === null) {
    header("Location: " . APP_URL . "reservaList/");
    exit();
}

$tituloVista = 'Reserva ' . $reserva['reserva_codigo'];
$vistaActual = 'reservaList';

$pagos  = $insArena->pagos($id);
$devoluciones = $insArena->devoluciones($id);   /* migración 092 */
$devuelto = array_sum(array_map(static fn ($d) => (float)$d['devolucion_valor'], $devoluciones));
$formas = $insArena->formasIngreso();
$saldoMonedero = $insArena->saldoMonedero((int)$reserva['reserva_clienteid']);

$total    = (float)$reserva['reserva_total'];
$abonado  = (float)$reserva['reserva_abonado'];
$saldo    = (float)$reserva['reserva_saldo'];
$progreso = $total > 0 ? round($abonado / $total * 100) : 0;

/* Del catálogo del controlador, no una copia local. Ver reservaList-view. */
$catalogo = $insArena->estadosReserva();
$estados  = array_map(static fn ($e) => $e['nombre'], $catalogo);
$colores  = array_map(static fn ($e) => $e['tono'],   $catalogo);

$puedeCobrar = puede_crear('reservaList') && $saldo > 0.001 && $reserva['reserva_estado'] !== 'X';

/* Anular un abono registrado por error (migración 098). En una cancelada
   no: lo abonado ya se devolvió al cancelar. */
$puedeAnularPago = $insArena->puedeAnularPagos() && $reserva['reserva_estado'] !== 'X';
$pagosAnulados   = $insArena->pagosAnulados($id);
/* Corregir sólo la fecha de un abono (un cobro de ayer registrado hoy). */
$puedeFechaPago  = puede_editar('reservaList') && $reserva['reserva_estado'] !== 'X';

/* Promoción (102): el descuento que tiene o la que se le puede aplicar por su fecha de juego. */
$descuento   = (float)($reserva['reserva_descuento'] ?? 0);
$subtotal    = round($total + $descuento, 2);
$puedePromo  = puede_editar('reservaList') && $reserva['reserva_estado'] !== 'X' && $insArena->promocionesListas();
$promoOferta = null;
if ($puedePromo && $descuento <= 0) {
    foreach ($insArena->promocionesPara((string)$reserva['reserva_fecha']) as $p) {
        if (!$promoOferta || arenaController::descuentoPromocion($p, $subtotal) > arenaController::descuentoPromocion($promoOferta, $subtotal)) { $promoOferta = $p; }
    }
}
$h = static fn ($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<style>
    .ar-det-promo { padding: .5rem .65rem; border-radius: .6rem; background: var(--bs-danger-bg-subtle); }
    .ar-det-oferta { display: flex; align-items: center; gap: .65rem; padding: .55rem .65rem; border: 1px dashed var(--bs-danger-border-subtle); border-radius: .6rem; }
    .ar-det-oferta__icono { width: 2rem; height: 2rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
                            background: var(--bs-danger-bg-subtle); color: var(--bs-danger-text-emphasis); }
    .ar-det-oferta__texto { flex: 1 1 auto; min-width: 0; }
    .ar-det-oferta .btn { flex: 0 0 auto; min-height: 2.25rem; }
</style>

<?php if ($reserva['reserva_estado'] === 'X'): ?>
    <?php /* Cancelada: no admite cobros. Si se canceló por error, se reactiva
             desde aquí; el servidor comprueba antes que el horario siga libre. */ ?>
    <div class="alert alert-secondary d-flex flex-wrap align-items-center gap-3 mb-3" role="status">
        <i class="fas fa-ban fa-lg"></i>
        <div class="flex-grow-1">
            <strong>Reserva cancelada.</strong> No admite cobros y su horario quedó libre.
            <?php /* Por qué, cuándo y quién (migración 087). Las canceladas
                     antes de esa migración no lo tienen y se dice así. */
            $motivoX = trim((string)($reserva['reserva_motivocancelacion'] ?? '')); ?>
            <span class="d-block mt-1">
                <i class="fas fa-comment-dots me-1" aria-hidden="true"></i><strong>Motivo:</strong>
                <?php echo $motivoX !== '' ? htmlspecialchars($motivoX, ENT_QUOTES, 'UTF-8') : '<em>sin motivo registrado</em>'; ?>
            </span>
            <?php if (!empty($reserva['reserva_fechacancelacion'])): ?>
                <span class="d-block small text-body-secondary">
                    Cancelada el <?php echo date('d/m/Y H:i', strtotime($reserva['reserva_fechacancelacion'])); ?>
                    <?php if (!empty($reserva['cancelada_por'])): ?>
                        por <?php echo htmlspecialchars($reserva['cancelada_por'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <span class="d-block small">Si se canceló por error, puede reactivarla: volverá a Pendiente si nadie ocupó ese horario.</span>
        </div>
        <?php if (puede_editar('reservaList')): ?>
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                  data-confirmar="La reserva <?php echo htmlspecialchars($reserva['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?> volverá a estar activa como Pendiente. Antes se comprobará que el horario siga libre.">
                <input type="hidden" name="modulo_arena"   value="cambiarEstadoReserva">
                <input type="hidden" name="reserva_id"     value="<?php echo (int)$reserva['reserva_id']; ?>">
                <input type="hidden" name="reserva_estado" value="P">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-undo me-1"></i> Reactivar reserva</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><?php echo htmlspecialchars($reserva['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <span class="badge text-bg-<?php echo $colores[$reserva['reserva_estado']] ?? 'secondary'; ?>">
                    <?php echo $estados[$reserva['reserva_estado']] ?? $reserva['reserva_estado']; ?>
                </span>
            </div>

            <div class="card-body">
                <dl class="mb-0">
                    <dt class="small text-muted">Cliente</dt>
                    <dd>
                        <?php echo htmlspecialchars($reserva['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        <small class="d-block text-muted"><?php echo htmlspecialchars($reserva['cliente_identificacion'], ENT_QUOTES, 'UTF-8'); ?></small>
                    </dd>

                    <dt class="small text-muted">Instalación</dt>
                    <dd><?php echo htmlspecialchars($reserva['instalacion_codigo'] . ' · ' . $reserva['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?></dd>

                    <dt class="small text-muted">Cuándo</dt>
                    <dd>
                        <?php echo $reserva['reserva_fecha']; ?>,
                        <?php echo substr($reserva['reserva_horainicio'], 0, 5); ?>–<?php echo substr($reserva['reserva_horafin'], 0, 5); ?>
                        <small class="text-muted">
                            (<?php echo rtrim(rtrim(number_format((float)$reserva['reserva_horas'], 2), '0'), '.'); ?> h
                            × $<?php echo number_format((float)$reserva['reserva_valorhora'], 2); ?>)
                        </small>
                    </dd>

                    <?php if ($reserva['reserva_fijaid']): ?>
                        <dt class="small text-muted">Reserva fija</dt>
                        <dd>
                            <a href="<?php echo APP_URL; ?>reservaList/?fija=<?php echo (int)$reserva['reserva_fijaid']; ?>">
                                <i class="fas fa-repeat me-1" aria-hidden="true"></i><?php echo htmlspecialchars($reserva['fija_codigo'], ENT_QUOTES, 'UTF-8'); ?></a>
                            · cada <?php echo htmlspecialchars(mb_strtolower($insArena->dias()[(int)$reserva['fija_dia']] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            de <?php echo substr($reserva['fija_horainicio'], 0, 5); ?> a <?php echo substr($reserva['fija_horafin'], 0, 5); ?>
                            <?php if ($reserva['fija_estado'] === 'X'): ?><span class="badge text-bg-secondary ms-1">Cancelada</span><?php endif; ?>
                            <small class="d-block text-muted">Cancelar esta semana no afecta a las demás.</small>
                        </dd>
                    <?php endif; ?>

                    <?php if ($reserva['reserva_observacion']): ?>
                        <dt class="small text-muted">Observación</dt>
                        <dd class="mb-0"><?php echo htmlspecialchars($reserva['reserva_observacion'], ENT_QUOTES, 'UTF-8'); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="card-footer">
                <?php if ($descuento > 0): ?>
                    <?php /* Promoción aplicada (102). */ ?>
                    <div class="ar-det-promo mb-2">
                        <div class="d-flex justify-content-between small text-body-secondary"><span>Valor de la reserva</span><span>$<?php echo number_format($subtotal, 2); ?></span></div>
                        <div class="d-flex justify-content-between small fw-semibold text-danger-emphasis">
                            <span><i class="fas fa-tags me-1" aria-hidden="true"></i><?php echo $h($reserva['promocion_nombre'] ?? 'Descuento'); ?>
                                <?php if (!empty($reserva['promocion_tipo'])): ?><span class="badge text-bg-danger ms-1"><?php echo $h(arenaController::etiquetaPromocion($reserva)); ?></span><?php endif; ?></span>
                            <span>−$<?php echo number_format($descuento, 2); ?></span>
                        </div>
                        <?php if ($puedePromo): ?>
                            <form class="FormularioAjax text-end" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                  data-confirmar="Se quitará el descuento: la reserva volverá a valer $<?php echo number_format($subtotal, 2); ?>.">
                                <input type="hidden" name="modulo_arena" value="promocionReserva">
                                <input type="hidden" name="reserva_id" value="<?php echo (int)$reserva['reserva_id']; ?>">
                                <input type="hidden" name="accion" value="quitar">
                                <button type="submit" class="btn btn-link btn-sm p-0 text-body-secondary">Quitar descuento</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php elseif ($promoOferta): $dOferta = arenaController::descuentoPromocion($promoOferta, $subtotal); ?>
                    <?php /* Hay una promoción para su fecha de juego y no la tiene (102). */ ?>
                    <form class="FormularioAjax ar-det-oferta mb-2" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                          data-confirmar="Se aplicará <?php echo $h($promoOferta['promocion_nombre']); ?> (<?php echo $h(arenaController::etiquetaPromocion($promoOferta)); ?>): la reserva pasará de $<?php echo number_format($subtotal, 2); ?> a $<?php echo number_format($subtotal - $dOferta, 2); ?>.">
                        <input type="hidden" name="modulo_arena" value="promocionReserva">
                        <input type="hidden" name="reserva_id" value="<?php echo (int)$reserva['reserva_id']; ?>">
                        <input type="hidden" name="promocion_id" value="<?php echo (int)$promoOferta['promocion_id']; ?>">
                        <span class="ar-det-oferta__icono" aria-hidden="true"><i class="fas fa-tags"></i></span>
                        <span class="ar-det-oferta__texto">
                            <strong><?php echo $h($promoOferta['promocion_nombre']); ?></strong>
                            <span class="badge text-bg-danger"><?php echo $h(arenaController::etiquetaPromocion($promoOferta)); ?></span>
                            <small class="d-block text-body-secondary">Aplica a esta fecha: descuenta $<?php echo number_format($dOferta, 2); ?>.</small>
                        </span>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-percent me-1" aria-hidden="true"></i>Aplicar</button>
                    </form>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Abonado</span>
                    <strong class="text-success">$<?php echo number_format($abonado, 2); ?></strong>
                </div>
                <?php if ($devuelto > 0): ?>
                    <div class="d-flex justify-content-between mb-1 small">
                        <a href="#devoluciones" class="text-warning-emphasis text-decoration-none"><i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Devuelto</a>
                        <span class="text-warning-emphasis">$<?php echo number_format($devuelto, 2); ?></span>
                    </div>
                <?php endif; ?>
                <div class="progress mb-2" style="height:8px;">
                    <div class="progress-bar bg-success" style="width:<?php echo $progreso; ?>%"></div>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Total $<?php echo number_format($total, 2); ?></span>
                    <?php if ($reserva['reserva_estado'] === 'X'): ?>
                        <span class="text-body-secondary small">Cancelada · no se cobra</span>
                    <?php elseif ($saldo > 0.001): ?>
                        <strong class="text-danger">Saldo $<?php echo number_format($saldo, 2); ?></strong>
                    <?php else: ?>
                        <span class="badge text-bg-success">Pagada</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><?php echo count($pagos); ?> abono<?php echo count($pagos) === 1 ? '' : 's'; ?></h3>
                <?php if ($puedeCobrar): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAbono">
                        <i class="fas fa-plus me-1"></i> Registrar abono
                    </button>
                <?php endif; ?>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Forma</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">Vuelto</th>
                                <th>Referencia</th>
                                <th class="text-end">Recibo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$pagos): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Sin abonos registrados.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($pagos as $p): ?>
                            <tr>
                                <td><?php echo $p['pago_fecha']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($p['forma_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($p['forma_codigo'] === 'TRA'): /* transferencia sin banco (103): se elige con «Ajustar» */ ?>
                                        <?php if ($puedeFechaPago): ?>
                                            <button type="button" class="badge text-bg-warning border-0 ar-pago__sinbanco" data-ajustar="<?php echo (int)$p['pago_id']; ?>"
                                                    title="Elegir el banco de esta transferencia"><i class="fas fa-building-columns me-1" aria-hidden="true"></i>Elija el banco</button>
                                        <?php else: ?>
                                            <span class="badge text-bg-warning">Sin banco</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($p['pago_observacion']): ?>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($p['pago_observacion'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><strong>$<?php echo number_format((float)$p['pago_valor'], 2); ?></strong></td>
                                <td class="text-end">
                                    <?php if ((float)$p['pago_vuelto'] > 0): ?>
                                        $<?php echo number_format((float)$p['pago_vuelto'], 2); ?>
                                        <?php if ($p['pago_vueltoamonedero'] === 'S'): ?>
                                            <small class="d-block text-success"><i class="fas fa-wallet"></i> al monedero</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo htmlspecialchars((string)$p['pago_referencia'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td class="text-end text-nowrap">
                                    <?php /* El recibo de cada abono: verlo, descargarlo o enviarlo. */ ?>
                                    <a href="<?php echo APP_URL; ?>reciboPago/?id=<?php echo (int)$p['pago_id']; ?>"
                                       class="btn btn-sm btn-outline-primary" title="Ver, descargar o enviar el recibo">
                                        <i class="fas fa-receipt me-1"></i><?php echo $insArena->reciboNumero((int)$p['pago_id']); ?>
                                    </a>
                                    <?php if ($puedeFechaPago): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ar-pago__fecha"
                                                data-bs-toggle="modal" data-bs-target="#modalFechaPago"
                                                data-id="<?php echo (int)$p['pago_id']; ?>"
                                                data-recibo="<?php echo $insArena->reciboNumero((int)$p['pago_id']); ?>"
                                                data-fecha="<?php echo $h($p['pago_fecha']); ?>"
                                                data-maxima="<?php echo $insArena->fechaMaximaPago($p); ?>"
                                                data-registro="<?php echo date('d/m/Y H:i', strtotime((string)$p['pago_fecharegistro'])); ?>"
                                                data-valor="<?php echo number_format((float)$p['pago_valor'], 2, '.', ''); ?>"
                                                data-forma="<?php echo $h($p['forma_nombre']); ?>"
                                                data-formaid="<?php echo (int)$p['pago_formaid']; ?>"
                                                data-ref="<?php echo $h($p['pago_referencia']); ?>"
                                                data-monedero="<?php echo $p['forma_codigo'] === 'MON' ? 'S' : 'N'; ?>"
                                                data-sinbanco="<?php echo $p['forma_codigo'] === 'TRA' ? 'S' : 'N'; ?>"
                                                title="Ajustar la fecha o la forma de pago" aria-label="Ajustar el abono <?php echo $insArena->reciboNumero((int)$p['pago_id']); ?>">
                                            <i class="fas fa-pen-to-square" aria-hidden="true"></i><span class="ar-pago__anular-txt">Ajustar</span>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($puedeAnularPago): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger ar-pago__anular"
                                                data-bs-toggle="modal" data-bs-target="#modalAnularPago"
                                                data-id="<?php echo (int)$p['pago_id']; ?>"
                                                data-recibo="<?php echo $insArena->reciboNumero((int)$p['pago_id']); ?>"
                                                data-fecha="<?php echo date('d/m/Y', strtotime($p['pago_fecha'])); ?>"
                                                data-forma="<?php echo $h($p['forma_nombre']); ?>"
                                                data-ref="<?php echo $h($p['pago_referencia']); ?>"
                                                data-valor="<?php echo number_format((float)$p['pago_valor'], 2, '.', ''); ?>"
                                                data-monedero="<?php echo $p['forma_codigo'] === 'MON' ? 'S' : 'N'; ?>"
                                                data-vuelto="<?php echo $p['pago_vueltoamonedero'] === 'S' ? number_format((float)$p['pago_vuelto'], 2, '.', '') : '0'; ?>"
                                                title="Anular este abono (registrado por error)" aria-label="Anular el abono <?php echo $insArena->reciboNumero((int)$p['pago_id']); ?>">
                                            <i class="fas fa-ban" aria-hidden="true"></i><span class="ar-pago__anular-txt">Anular</span>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($devoluciones):
                    $mediosDev = arena\controllers\arenaController::MEDIOS_DEVOLUCION; ?>
                    <?php /* Lo que se devolvió al cancelar o al pasar a un horario
                             más barato (migración 092). Los abonos de arriba no
                             cambian: siguen siendo lo que el cliente pagó. */ ?>
                    <div class="ar-devs" id="devoluciones">
                        <h4 class="ar-devs__titulo"><i class="fas fa-rotate-left me-2" aria-hidden="true"></i><?php
                            echo count($devoluciones) === 1 ? '1 devolución' : count($devoluciones) . ' devoluciones'; ?></h4>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Fecha</th><th>Medio</th><th class="text-end">Valor</th><th>Motivo</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($devoluciones as $d): ?>
                                    <tr>
                                        <td class="text-nowrap"><?php echo date('d/m/Y H:i', (int)$d['registro_ts']); ?>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($d['usuario'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                        <td><?php echo htmlspecialchars($mediosDev[$d['devolucion_medio']] ?? $d['devolucion_medio'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($d['devolucion_referencia']): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($d['devolucion_referencia'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php endif; ?></td>
                                        <td class="text-end text-nowrap"><strong class="text-warning-emphasis">−$<?php echo number_format((float)$d['devolucion_valor'], 2); ?></strong></td>
                                        <td><small><span class="badge text-bg-light border me-1"><?php echo $d['devolucion_origen'] === 'C' ? 'Al cancelar' : 'Al editar'; ?></span><?php
                                            echo htmlspecialchars($d['devolucion_motivo'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($pagosAnulados): ?>
                    <?php /* Abonos anulados (migración 098): no cuentan en lo abonado,
                             ni en el reporte de pagos ni en la caja. */ ?>
                    <div class="ar-anulados" id="abonosAnulados">
                        <h4 class="ar-anulados__titulo"><i class="fas fa-ban me-2" aria-hidden="true"></i><?php
                            echo count($pagosAnulados) === 1 ? '1 abono anulado' : count($pagosAnulados) . ' abonos anulados'; ?></h4>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Abono y justificación</th><th class="text-end">Valor</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($pagosAnulados as $a): ?>
                                    <tr>
                                        <td><strong><?php echo $insArena->reciboNumero((int)$a['pago_id']); ?></strong>
                                            <small class="text-muted"> · <?php echo date('d/m/Y', strtotime($a['pago_fecha'])) . ' · ' . $h($a['forma_nombre'])
                                                . ($a['pago_referencia'] ? ' · Ref. ' . $h($a['pago_referencia']) : ''); ?></small>
                                            <div class="ar-anulados__motivo"><i class="fas fa-comment-dots me-1" aria-hidden="true"></i><?php echo $h($a['pago_anulamotivo']); ?></div>
                                            <small class="d-block text-muted">Anuló <?php echo $h($a['anulo_usuario']); ?><?php
                                                echo $a['anulo_ts'] ? ' el ' . date('d/m/Y H:i', (int)$a['anulo_ts']) : ''; ?></small></td>
                                        <td class="text-end text-nowrap"><s class="text-body-secondary">$<?php echo number_format((float)$a['pago_valor'], 2); ?></s></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-footer ds-acciones">
                <?php echo ds_boton('volver', 'Volver a reservas', [
                    'href' => APP_URL . 'reservaList/', 'estilo' => 'secondary']); ?>
                <?php echo ds_boton('detalle', 'Monedero: $' . number_format($saldoMonedero, 2), [
                    'href'   => APP_URL . 'monederoDetalle/?cliente=' . (int)$reserva['reserva_clienteid'],
                    'estilo' => 'outline-secondary']); ?>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeCobrar): ?>
<div class="modal fade" id="modalAbono" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                <input type="hidden" name="modulo_arena" value="registrarPago">
                <input type="hidden" name="reserva_id" value="<?php echo $id; ?>">

                <div class="modal-header">
                    <h5 class="modal-title">Registrar abono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted small">
                        Saldo pendiente: <strong class="text-danger">$<?php echo number_format($saldo, 2); ?></strong>
                    </p>
                    <?php /* De madrugada, el cobro de una reserva de ayer queda en el día de la reserva. */
                    $fechaCobro = $insArena->fechaDelCobro((string)$reserva['reserva_fecha']);
                    if ($fechaCobro !== date('Y-m-d')): ?>
                        <div class="alert alert-info py-2 small d-flex gap-2 align-items-start" role="note">
                            <i class="fas fa-moon mt-1" aria-hidden="true"></i>
                            <span>Son las <?php echo date('H:i'); ?>: este cobro queda con fecha
                                <strong><?php echo date('d/m/Y', strtotime($fechaCobro)); ?></strong>, el día de la reserva, y cuenta en la caja de ese día.</span>
                        </div>
                    <?php endif; ?>

                    <?php /* Fecha del cobro (2026-11-25): un abono que el cliente pagó otro
                             día y no se registró a tiempo se registra con esa fecha. Hoy o
                             antes, nunca después (el servidor lo vuelve a comprobar). */ ?>
                    <div class="mb-3">
                        <label for="pago_fecha">Fecha del cobro <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="pago_fecha" name="pago_fecha" required
                               value="<?php echo $fechaCobro; ?>" max="<?php echo date('Y-m-d'); ?>"
                               min="<?php echo date('Y-m-d', strtotime('-' . arenaController::COBRO_DIAS_ATRAS . ' days')); ?>"
                               aria-describedby="pago_fecha_ayuda">
                        <small class="text-muted" id="pago_fecha_ayuda">El día en que el cliente pagó. Si fue antes y no se registró, elija ese día.</small>
                        <div class="ar-cobro-pasado small mt-2" id="avisoCobroPasado" hidden>
                            <i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i>
                            <span>Cobro de un día anterior: sale en los reportes y en la caja de ese día, no en la de hoy.</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="pago_formaid">Forma de ingreso <span class="text-danger">*</span></label>
                        <select class="form-select" id="pago_formaid" name="pago_formaid" required>
                            <?php foreach ($formas as $f):
                                /* Pagar con monedero sólo si hay saldo suficiente. */
                                $esMon = $f['forma_esmonedero'] === 'S';
                                if ($esMon && $saldoMonedero <= 0) continue;
                            ?>
                                <option value="<?php echo (int)$f['forma_id']; ?>"
                                        data-monedero="<?php echo $f['forma_esmonedero']; ?>"
                                        data-ref="<?php echo $f['forma_requiereref']; ?>">
                                    <?php echo htmlspecialchars($f['forma_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo $esMon ? ' (disponible $' . number_format($saldoMonedero, 2) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="pago_valor">Importe del abono <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="<?php echo number_format($saldo, 2, '.', ''); ?>"
                               class="form-control" id="pago_valor" name="pago_valor" required
                               value="<?php echo number_format($saldo, 2, '.', ''); ?>">
                        <small class="text-muted">No puede superar el saldo pendiente.</small>
                    </div>

                    <div class="mb-3" id="grupoRecibido">
                        <label for="pago_recibido">Recibido del cliente</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="pago_recibido" name="pago_recibido">
                        <small class="text-muted">Sólo si entrega más de lo que se cobra, para calcular el vuelto.</small>
                    </div>

                    <div class="mb-3" id="grupoVuelto" style="display:none;">
                        <div class="aviso-superadmin">
                            <i class="fas fa-coins fa-lg mt-1"></i>
                            <div>
                                Vuelto: <strong id="textoVuelto">$0.00</strong><br>
                                <label class="mb-0 mt-2">
                                    <input type="checkbox" name="pago_vueltoamonedero" value="S">
                                    Dejar el vuelto en el monedero del cliente
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="grupoReferencia">
                        <label for="pago_referencia">Referencia</label>
                        <input type="text" class="form-control" id="pago_referencia" name="pago_referencia" maxlength="60">
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="pago_observacion">Observación</label>
                        <input type="text" class="form-control" id="pago_observacion" name="pago_observacion" maxlength="200">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var forma     = document.getElementById('pago_formaid');
    var valor     = document.getElementById('pago_valor');
    var recibido  = document.getElementById('pago_recibido');
    var gRecibido = document.getElementById('grupoRecibido');
    var gVuelto   = document.getElementById('grupoVuelto');
    var gRef      = document.getElementById('grupoReferencia');
    var txtVuelto = document.getElementById('textoVuelto');

    function actualizar() {
        var op     = forma.options[forma.selectedIndex];
        var esMon  = op && op.dataset.monedero === 'S';
        var pideRef = op && op.dataset.ref === 'S';

        /* Pagar con monedero no admite efectivo recibido ni vuelto. */
        gRecibido.style.display = esMon ? 'none' : '';
        gRef.style.display      = pideRef ? '' : 'none';

        var v = parseFloat(valor.value) || 0;
        var r = parseFloat(recibido.value) || 0;
        var vuelto = (!esMon && r > v) ? r - v : 0;

        gVuelto.style.display = vuelto > 0 ? '' : 'none';
        txtVuelto.textContent = '$' + vuelto.toFixed(2);
    }

    [forma, valor, recibido].forEach(function (c) {
        c.addEventListener('input', actualizar);
        c.addEventListener('change', actualizar);
    });

    /* Fecha del cobro: aviso si es de un día anterior a hoy. */
    var fechaPago = document.getElementById('pago_fecha');
    var avisoPasado = document.getElementById('avisoCobroPasado');
    var HOY = '<?php echo date('Y-m-d'); ?>';
    function avisarFecha() { avisoPasado.hidden = !(fechaPago.value && fechaPago.value < HOY); }
    fechaPago.addEventListener('input', avisarFecha);
    fechaPago.addEventListener('change', avisarFecha);
    avisarFecha();

    actualizar();
})();
</script>
<?php endif; ?>

<?php if ($puedeFechaPago && $pagos): ?>
<?php /* Ajustar un abono: la fecha del cobro, la forma de pago y el comprobante (no el valor). */ ?>
<div class="modal fade" id="modalFechaPago" tabindex="-1" aria-labelledby="modalFechaPagoTitulo">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" id="formFechaPago" novalidate>
                <input type="hidden" name="modulo_arena" value="cambiarFechaPago">
                <input type="hidden" name="pago_id" id="afp_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalFechaPagoTitulo"><i class="fas fa-pen-to-square me-2" aria-hidden="true"></i>Ajustar abono <span id="afp_recibo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <dl class="ar-anp__resumen mb-3">
                        <div><dt>Valor</dt><dd id="afp_valor"></dd></div>
                        <div><dt>Registrado</dt><dd id="afp_registro"></dd></div>
                    </dl>
                    <label for="afp_fecha" class="form-label fw-semibold">Fecha del cobro <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-lg" id="afp_fecha" name="pago_fecha" required
                           min="<?php echo date('Y-m-d', strtotime('-' . arenaController::COBRO_DIAS_ATRAS . ' days')); ?>" aria-describedby="afp_ayuda">
                    <div class="invalid-feedback" id="afp_error">Elija una fecha válida.</div>
                    <div class="d-flex flex-wrap gap-2 mt-2" role="group" aria-label="Atajos">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-afp-dias="1">Ayer</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-afp-reserva>Día de la reserva (<?php echo date('d/m/Y', strtotime($reserva['reserva_fecha'])); ?>)</button>
                    </div>
                    <small class="text-muted d-block mt-2" id="afp_ayuda">El día en que el cliente pagó. Cuenta en el reporte de pagos de ese día.</small>

                    <?php /* La forma de pago (2026-11-26): entre las que no son el monedero. */ ?>
                    <label for="afp_forma_sel" class="form-label fw-semibold mt-3">Forma de pago <span class="text-danger">*</span></label>
                    <select class="form-select" id="afp_forma_sel" name="pago_formaid">
                        <?php foreach ($formas as $f): if ($f['forma_esmonedero'] === 'S') { continue; } ?>
                            <option value="<?php echo (int)$f['forma_id']; ?>" data-ref="<?php echo $h($f['forma_requiereref']); ?>"><?php echo $h($f['forma_nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-1" id="afp_forma_ayuda">Si se registró con la forma equivocada (por ejemplo, otro banco).</small>
                    <div class="mt-3" id="afp_ref_grupo">
                        <label for="afp_ref" class="form-label fw-semibold">Comprobante <span class="text-danger" id="afp_ref_req">*</span></label>
                        <input type="text" class="form-control" id="afp_ref" name="pago_referencia" maxlength="60" autocomplete="off" placeholder="Número de la transferencia">
                        <div class="invalid-feedback" id="afp_ref_error">Indique el número de comprobante.</div>
                    </div>
                    <small class="text-muted d-block mt-2">El valor no cambia: si está mal, anule el abono y regístrelo de nuevo.</small>
                </div>

                <div class="modal-footer ar-anp__pie">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1" aria-hidden="true"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var fecha = $('afp_fecha'), original = '', FECHA_RESERVA = '<?php echo $h($reserva['reserva_fecha']); ?>';
    var sel = $('afp_forma_sel'), ref = $('afp_ref'), orig = { forma: '', ref: '' }, monedero = false;
    var dmy = function (d) { return d.split('-').reverse().join('/'); };
    var menos = function (n) { var d = new Date('<?php echo date('Y-m-d'); ?>T12:00:00'); d.setDate(d.getDate() - n); return d.toISOString().slice(0, 10); };
    /* El comprobante: obligatorio si la forma lo pide. */
    function formaRef() {
        var op = sel.options[sel.selectedIndex], pide = !!op && op.dataset.ref === 'S';
        $('afp_ref_req').hidden = !pide;
        ref.placeholder = pide ? 'Número de la transferencia' : 'Opcional';
        ref.classList.remove('is-invalid');
    }
    $('modalFechaPago').addEventListener('show.bs.modal', function (e) {
        var b = e.relatedTarget; if (!b) { return; }
        $('afp_id').value = b.dataset.id;
        $('afp_recibo').textContent = b.dataset.recibo;
        $('afp_valor').textContent = '$' + (+b.dataset.valor).toFixed(2);
        $('afp_registro').textContent = b.dataset.registro;
        fecha.max = b.dataset.maxima; fecha.value = original = b.dataset.fecha;
        fecha.classList.remove('is-invalid');
        /* La forma: la del abono. Un abono con monedero no cambia de forma. */
        monedero = b.dataset.monedero === 'S';
        orig.forma = b.dataset.formaid; orig.ref = b.dataset.ref || '';
        ref.value = orig.ref;
        if (monedero) {
            sel.disabled = true;
            sel.querySelectorAll('option[data-inactiva]').forEach(function (o) { o.remove(); });
            if (!sel.querySelector('option[data-monedero]')) { sel.insertAdjacentHTML('afterbegin', '<option value="' + orig.forma + '" data-monedero="S"></option>'); }
            var om = sel.querySelector('option[data-monedero]'); om.value = orig.forma; om.textContent = b.dataset.forma;
            sel.value = orig.forma;
            $('afp_forma_ayuda').textContent = 'Pagado con monedero: para cambiar la forma, anule el abono (el saldo vuelve al monedero) y regístrelo de nuevo.';
            $('afp_ref_grupo').hidden = true;
        } else {
            sel.disabled = false;
            sel.querySelectorAll('option[data-monedero], option[data-inactiva]').forEach(function (o) { o.remove(); });
            /* Una forma ya desactivada (p. ej. Tarjeta): se muestra para este abono, para no cambiarla sin querer. */
            if (!sel.querySelector('option[value="' + orig.forma + '"]')) {
                sel.insertAdjacentHTML('afterbegin', '<option data-inactiva="S"></option>');
                var oi = sel.querySelector('option[data-inactiva]'); oi.value = orig.forma;
                oi.textContent = b.dataset.sinbanco === 'S' ? b.dataset.forma + ' (sin banco: elija cuál)' : b.dataset.forma + ' (ya no disponible)';
                oi.dataset.ref = 'N';
            }
            sel.value = orig.forma;
            $('afp_forma_ayuda').textContent = b.dataset.sinbanco === 'S'
                ? 'Transferencia registrada antes de separar por banco: elija a qué banco llegó.'
                : 'Si se registró con la forma equivocada (por ejemplo, otro banco).';
            $('afp_ref_grupo').hidden = false;
        }
        formaRef();
        /* Los atajos sólo si caen en el rango (hasta el día en que se registró). */
        var fuera = function (d) { return d > fecha.max || d < fecha.min || d === original; };
        document.querySelector('[data-afp-reserva]').hidden = fuera(FECHA_RESERVA);
        document.querySelectorAll('[data-afp-dias]').forEach(function (x) { x.hidden = fuera(menos(+x.dataset.afpDias)); });
    });
    sel.addEventListener('change', formaRef);
    /* «Elija el banco» abre el mismo «Ajustar» de ese abono, con la lista de formas a la vista. */
    document.querySelectorAll('[data-ajustar]').forEach(function (x) {
        x.addEventListener('click', function () {
            var btn = document.querySelector('.ar-pago__fecha[data-id="' + x.dataset.ajustar + '"]');
            if (!btn) { return; }
            btn.click();
            $('modalFechaPago').addEventListener('shown.bs.modal', function () { sel.focus(); }, { once: true });
        });
    });
    ref.addEventListener('input', function () { ref.classList.remove('is-invalid'); });
    document.querySelectorAll('[data-afp-dias]').forEach(function (b) { b.addEventListener('click', function () { fecha.value = menos(+b.dataset.afpDias); fecha.classList.remove('is-invalid'); }); });
    document.querySelector('[data-afp-reserva]').addEventListener('click', function () { fecha.value = FECHA_RESERVA; fecha.classList.remove('is-invalid'); });
    fecha.addEventListener('input', function () { fecha.classList.remove('is-invalid'); });
    /* Antes del envío de ajax.js: fecha en rango, comprobante si hace falta y algún cambio. */
    $('formFechaPago').addEventListener('submit', function (e) {
        var v = fecha.value, msg = '', campo = fecha;
        var op = sel.options[sel.selectedIndex], pide = !monedero && !!op && op.dataset.ref === 'S';
        if (!v || v < fecha.min || v > fecha.max) { msg = 'Elija una fecha entre el ' + dmy(fecha.min) + ' y el ' + dmy(fecha.max) + ' (el día en que se registró).'; }
        else if (pide && !ref.value.trim()) { campo = ref; msg = 'Indique el número de comprobante.'; }
        else if (v === original && sel.value === orig.forma && ref.value.trim() === orig.ref) { msg = 'No cambió nada: el abono ya tiene esa fecha, forma y comprobante.'; }
        if (msg) {
            e.preventDefault(); e.stopImmediatePropagation();
            if (campo === ref) { $('afp_ref_error').textContent = msg; } else { $('afp_error').textContent = msg; }
            campo.classList.add('is-invalid'); campo.focus();
        }
    }, true);
})();
</script>
<?php endif; ?>

<?php if ($puedeAnularPago && $pagos): ?>
<?php /* Anular un abono registrado por error (migración 098). */ ?>
<div class="modal fade" id="modalAnularPago" tabindex="-1" aria-labelledby="modalAnularPagoTitulo">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" id="formAnularPago" novalidate
                  data-confirmar="El abono quedará anulado y la reserva volverá a tener ese saldo pendiente. No se puede deshacer.">
                <input type="hidden" name="modulo_arena" value="anularPagoReserva">
                <input type="hidden" name="pago_id" id="anp_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalAnularPagoTitulo"><i class="fas fa-ban me-2 text-danger" aria-hidden="true"></i>Anular el abono <span id="anp_recibo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <dl class="ar-anp__resumen">
                        <div><dt>Fecha</dt><dd id="anp_fecha"></dd></div>
                        <div><dt>Forma</dt><dd id="anp_forma"></dd></div>
                        <div><dt>Valor</dt><dd id="anp_valor"></dd></div>
                    </dl>

                    <ul class="ar-anp__efecto" id="anp_efecto"></ul>

                    <label for="anp_motivo" class="form-label mt-3">Justificación: ¿qué ocurrió? <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="anp_motivo" name="motivo" rows="3" maxlength="250" minlength="10" required
                              placeholder="Por ejemplo: se registró como transferencia, pero se descontó del monedero del cliente."></textarea>
                    <div class="invalid-feedback">Escriba al menos 10 caracteres: qué ocurrió y por qué se anula.</div>
                    <div class="d-flex justify-content-between gap-2">
                        <small class="text-muted">Queda registrada con la anulación, con su nombre y la hora.</small>
                        <small class="text-muted text-nowrap" id="anp_contador">0/250</small>
                    </div>
                </div>

                <div class="modal-footer ar-anp__pie">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="anp_enviar"><i class="fas fa-ban me-1" aria-hidden="true"></i>Anular abono</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('modalAnularPago');
    var $ = function (id) { return document.getElementById(id); };
    var ABONADO = <?php echo json_encode(round($abonado, 2)); ?>, TOTAL = <?php echo json_encode(round($total, 2)); ?>;
    var MONEDERO = <?php echo json_encode(round($saldoMonedero, 2)); ?>;
    var motivo = $('anp_motivo'), contador = $('anp_contador'), enviar = $('anp_enviar');
    var dinero = function (v) { return '$' + (+v).toFixed(2); };
    var item = function (icono, html, clase) { return '<li class="' + (clase || '') + '"><i class="fas ' + icono + ' me-2" aria-hidden="true"></i><span>' + html + '</span></li>'; };

    modal.addEventListener('show.bs.modal', function (e) {
        var b = e.relatedTarget; if (!b) { return; }
        var v = +b.dataset.valor, vuelto = +b.dataset.vuelto, esMon = b.dataset.monedero === 'S';
        $('anp_id').value = b.dataset.id;
        $('anp_recibo').textContent = b.dataset.recibo;
        $('anp_fecha').textContent = b.dataset.fecha;
        $('anp_forma').textContent = b.dataset.forma + (b.dataset.ref ? ' · Ref. ' + b.dataset.ref : '');
        $('anp_valor').textContent = dinero(v);

        var abonado = Math.max(0, ABONADO - v), mon = MONEDERO + (esMon ? v : 0) - vuelto;
        var html = item('fa-arrow-trend-down', 'Lo abonado pasa de <strong>' + dinero(ABONADO) + '</strong> a <strong>' + dinero(abonado)
                 + '</strong>: quedan <strong>' + dinero(TOTAL - abonado) + '</strong> pendientes.')
                 + item('fa-chart-column', 'Deja de contar en el reporte de pagos y en la caja del ' + b.dataset.fecha + '.');
        if (esMon)      { html += item('fa-wallet', '<strong>' + dinero(v) + '</strong> vuelven al monedero del cliente.'); }
        if (vuelto > 0) { html += item('fa-coins', 'Sale del monedero el vuelto de <strong>' + dinero(vuelto) + '</strong> que se dejó con este abono.'); }
        if (esMon || vuelto > 0) { html += item('fa-scale-balanced', 'Saldo del monedero: ' + dinero(MONEDERO) + ' → <strong>' + dinero(mon) + '</strong>.'); }
        if (mon < -0.001) {
            html += item('fa-triangle-exclamation', 'El cliente ya usó ese vuelto: el monedero no lo cubre y no se puede anular.', 'ar-anp__error');
        }
        $('anp_efecto').innerHTML = html;
        enviar.disabled = mon < -0.001;
        motivo.value = ''; contador.textContent = '0/250';
        motivo.classList.remove('is-invalid');
    });
    modal.addEventListener('shown.bs.modal', function () { if (window.matchMedia('(min-width: 576px)').matches) { motivo.focus(); } });
    motivo.addEventListener('input', function () { contador.textContent = motivo.value.length + '/250'; motivo.classList.remove('is-invalid'); });
    /* Antes de la confirmación de ajax.js: la justificación, de al menos 10 caracteres. */
    $('formAnularPago').addEventListener('submit', function (e) {
        if (motivo.value.trim().length < 10) {
            e.preventDefault(); e.stopImmediatePropagation();
            motivo.classList.add('is-invalid'); motivo.focus();
        }
    }, true);
})();
</script>
<?php endif; ?>

<style>
/* Anular un abono (098) */
.ar-pago__anular, .ar-pago__fecha { margin-left: .25rem; }
.ar-pago__anular-txt { margin-left: .35rem; }
@media (max-width: 575.98px) { .ar-pago__anular-txt { display: none; } }
.ar-anulados { border-top: 1px solid var(--bs-border-color); padding: .75rem 1rem 0; }
.ar-anulados__titulo { font-size: .95rem; font-weight: 600; margin: 0 0 .5rem; color: var(--bs-danger-text-emphasis); }
.ar-anulados .table { --bs-table-bg: transparent; }
.ar-anulados__motivo { font-size: .875rem; margin-top: .2rem; overflow-wrap: anywhere; }
.ar-anp__resumen { display: grid; grid-template-columns: 5rem 1fr; gap: .3rem .75rem; margin: 0; }
.ar-anp__resumen > div { display: contents; }
.ar-anp__resumen dt { color: var(--bs-secondary-color); font-weight: 600; }
.ar-anp__resumen dd { margin: 0; overflow-wrap: anywhere; }
.ar-anp__efecto { list-style: none; margin: .75rem 0 0; padding: .5rem .75rem; border-radius: var(--ds-radius-sm, 10px); font-size: .9rem;
                  background: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis); border: 1px solid var(--bs-info-border-subtle); }
.ar-anp__efecto li { display: flex; align-items: baseline; }
.ar-anp__efecto li + li { margin-top: .3rem; }
.ar-anp__efecto .ar-anp__error { color: var(--bs-danger-text-emphasis); font-weight: 600; }
@media (max-width: 575.98px) {
    .ar-anp__pie { flex-direction: column-reverse; align-items: stretch; }
    .ar-anp__pie .btn { width: 100%; margin: 0; min-height: 2.75rem; }
}
</style>

<style>
/* Devoluciones, bajo los abonos (migración 092). */
.ar-devs { border-top: 1px solid var(--bs-border-color); padding: .75rem 1rem 0; }
.ar-devs__titulo { font-size: .95rem; font-weight: 600; margin: 0 0 .5rem; color: var(--bs-warning-text-emphasis); }
.ar-devs .table { --bs-table-bg: transparent; }
/* Cobro de un día anterior */
.ar-cobro-pasado { padding: .45rem .7rem; border-radius: var(--ds-radius-sm, 10px);
                   background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); border: 1px solid var(--bs-warning-border-subtle); }
</style>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
