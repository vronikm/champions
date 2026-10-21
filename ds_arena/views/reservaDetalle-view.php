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

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if ($reserva['reserva_estado'] === 'X'): ?>
    <?php /* Cancelada: no admite cobros. Si se canceló por error, se reactiva
             desde aquí; el servidor comprueba antes que el horario siga libre. */ ?>
    <div class="alert alert-secondary d-flex flex-wrap align-items-center gap-3 mb-3" role="status">
        <i class="fas fa-ban fa-lg"></i>
        <div class="flex-grow-1">
            <strong>Reserva cancelada.</strong> No admite cobros y su horario quedó libre.
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

                    <?php if ($reserva['reserva_observacion']): ?>
                        <dt class="small text-muted">Observación</dt>
                        <dd class="mb-0"><?php echo htmlspecialchars($reserva['reserva_observacion'], ENT_QUOTES, 'UTF-8'); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="card-footer">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Abonado</span>
                    <strong class="text-success">$<?php echo number_format($abonado, 2); ?></strong>
                </div>
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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

    actualizar();
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
