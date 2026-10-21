<?php
/*
| Libro mayor del monedero de un cliente.
| Cada asiento deja el saldo antes y después, de modo que el saldo actual
| se puede auditar recorriendo el histórico.
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$clienteid = (int)($_GET['cliente'] ?? 0);
$cliente   = $insArena->cliente($clienteid);

if ($cliente === null) {
    header("Location: " . APP_URL . "monederoList/");
    exit();
}

$tituloVista = 'Monedero · ' . $cliente['cliente_nombre'];
$vistaActual = 'monederoList';

$saldo       = $insArena->saldoMonedero($clienteid);
$movimientos = $insArena->movimientos($clienteid);

/* 'DEV' es el código en base; la etiqueta usa el término del negocio:
   un egreso es la salida de dinero del monedero a pedido del cliente. */
$origenes = ['VUE' => 'Vuelto', 'TRA' => 'Transferencia', 'RES' => 'Aplicado a reserva',
             'DEV' => 'Egreso a pedido del cliente', 'AJU' => 'Ajuste'];

$puedeRetirar = puede_eliminar('monederoList') || puede_editar('monederoList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row">
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <span class="d-inline-flex align-items-center justify-content-center rounded bg-success text-white mx-auto mb-3"
                      style="width:56px;height:56px;font-size:1.4rem;">
                    <i class="fas fa-wallet" aria-hidden="true"></i>
                </span>
                <div class="text-muted text-uppercase fw-semibold" style="font-size:.8rem;letter-spacing:.04em;">Saldo disponible</div>
                <div class="fw-bold" style="font-size:2rem;line-height:1.15;">$<?php echo number_format($saldo, 2); ?></div>

                <hr>

                <div class="text-start">
                    <dt class="small text-muted">Cliente</dt>
                    <dd><?php echo htmlspecialchars($cliente['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="small text-muted">Identificación</dt>
                    <dd class="mb-0"><?php echo htmlspecialchars($cliente['cliente_identificacion'], ENT_QUOTES, 'UTF-8'); ?></dd>
                </div>
            </div>

            <div class="card-footer ds-acciones">
                <?php echo ds_boton('volver', 'Volver', [
                    'href' => APP_URL . 'monederoList/', 'estilo' => 'secondary']); ?>
                <?php if ($puedeRetirar && $saldo > 0): ?>
                    <?php echo ds_boton('quitar', 'Registrar egreso', [
                        'type'   => 'button',
                        'estilo' => 'outline-danger',
                        'data'   => ['toggle' => 'modal', 'target' => '#modalEgreso'],
                    ]); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0"><?php echo count($movimientos); ?> movimiento<?php echo count($movimientos) === 1 ? '' : 's'; ?></h3>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th class="text-end">Entrada</th>
                                <th class="text-end">Salida</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$movimientos): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Sin movimientos.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($movimientos as $mv):
                            $esIngreso = $mv['movimiento_tipo'] === 'I';
                        ?>
                            <tr>
                                <td><?php echo $mv['movimiento_fecha']; ?></td>
                                <td>
                                    <strong><?php echo $origenes[$mv['movimiento_origen']] ?? $mv['movimiento_origen']; ?></strong>
                                    <?php if ($mv['reserva_codigo']): ?>
                                        <small class="d-block text-muted">
                                            <a href="<?php echo APP_URL; ?>reservaDetalle/?id=<?php echo (int)$mv['movimiento_reservaid']; ?>">
                                                <?php echo htmlspecialchars($mv['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </small>
                                    <?php elseif ($mv['movimiento_detalle']): ?>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($mv['movimiento_detalle'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                    <?php if ($mv['movimiento_referencia']): ?>
                                        <small class="d-block text-muted">Ref.: <?php echo htmlspecialchars($mv['movimiento_referencia'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo $esIngreso ? '<span class="text-success">$' . number_format((float)$mv['movimiento_valor'], 2) . '</span>' : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo !$esIngreso ? '<span class="text-danger">$' . number_format((float)$mv['movimiento_valor'], 2) . '</span>' : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td class="text-end"><strong>$<?php echo number_format((float)$mv['movimiento_saldonuevo'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeRetirar && $saldo > 0): ?>
<div class="modal fade" id="modalEgreso" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                  data-confirmar="Se entregará el importe al cliente y saldrá del monedero.">
                <input type="hidden" name="modulo_arena" value="egresoMonedero">
                <input type="hidden" name="cliente_id" value="<?php echo $clienteid; ?>">

                <div class="modal-header">
                    <h5 class="modal-title">Registrar egreso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted small">
                        Salida de dinero del monedero a pedido del cliente.<br>
                        Disponible: <strong class="text-success">$<?php echo number_format($saldo, 2); ?></strong>
                    </p>

                    <div class="mb-3">
                        <label for="valor">Importe del egreso <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="<?php echo number_format($saldo, 2, '.', ''); ?>"
                               class="form-control" id="valor" name="valor" required
                               value="<?php echo number_format($saldo, 2, '.', ''); ?>">
                        <small class="text-muted">No puede superar el saldo disponible.</small>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="detalle">Motivo</label>
                        <input type="text" class="form-control" id="detalle" name="detalle" maxlength="200"
                               placeholder="Egreso a pedido del cliente">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Registrar egreso</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
