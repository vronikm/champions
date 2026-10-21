<?php
/* Saldos a favor de los clientes. */

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Monedero';
$vistaActual = 'monederoList';

$monederos = $insArena->monederos();
$clientes  = $insArena->clientes();

$totalSaldo = array_sum(array_map(fn($m) => (float)$m['monedero_saldo'], $monederos));
$conSaldo   = count(array_filter($monederos, fn($m) => (float)$m['monedero_saldo'] > 0));

$puedeAcreditar = puede_crear('monederoList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row">
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="info-box">
            <span class="info-box-icon bg-success shadow-sm"><i class="fas fa-wallet" aria-hidden="true"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Saldo total a favor de clientes</span>
                <span class="info-box-number">$<?php echo number_format($totalSaldo, 2); ?></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="info-box">
            <span class="info-box-icon bg-info shadow-sm"><i class="fas fa-user-friends" aria-hidden="true"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Clientes con saldo</span>
                <span class="info-box-number"><?php echo (int)$conSaldo; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Monederos</h3>
        <?php if ($puedeAcreditar && $clientes): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalIngreso">
                <i class="fas fa-plus me-1"></i> Acreditar saldo
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Identificación</th>
                        <th class="text-end">Saldo</th>
                        <th>Movimientos</th>
                        <th>Último</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$monederos): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        Todavía no hay monederos. Se crean solos al acreditar un saldo o dejar un vuelto.
                    </td></tr>
                <?php endif; ?>

                <?php foreach ($monederos as $m): $saldo = (float)$m['monedero_saldo']; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($m['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($m['cliente_identificacion'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td class="text-end">
                            <?php if ($saldo > 0): ?>
                                <strong class="text-success">$<?php echo number_format($saldo, 2); ?></strong>
                            <?php else: ?>
                                <span class="text-muted">$0.00</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)$m['movimientos']; ?></td>
                        <td><small class="text-muted"><?php echo $m['ultimo'] ?: '—'; ?></small></td>
                        <td class="text-end">
                            <a href="<?php echo APP_URL; ?>monederoDetalle/?cliente=<?php echo (int)$m['cliente_id']; ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-list me-1"></i> Movimientos
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer text-muted small">
        El saldo se alimenta de vueltos que el cliente decide no llevarse y de transferencias
        anticipadas. Se consume al pagar reservas o cuando el cliente pide que se le devuelva.
    </div>
</div>

<?php if ($puedeAcreditar && $clientes): ?>
<div class="modal fade" id="modalIngreso" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                <input type="hidden" name="modulo_arena" value="ingresoMonedero">

                <div class="modal-header">
                    <h5 class="modal-title">Acreditar saldo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cliente_id">Cliente <span class="text-danger">*</span></label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?php echo (int)$c['cliente_id']; ?>">
                                    <?php echo htmlspecialchars($c['cliente_nombre'] . ' · ' . $c['cliente_identificacion'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-7">
                            <label for="origen">Origen</label>
                            <select class="form-select" id="origen" name="origen">
                                <option value="TRA">Transferencia del cliente</option>
                                <option value="AJU">Ajuste manual</option>
                            </select>
                        </div>
                        <div class="mb-3 col-5">
                            <label for="valor">Importe <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="valor" name="valor" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="referencia">Referencia</label>
                        <input type="text" class="form-control" id="referencia" name="referencia" maxlength="60">
                        <small class="text-muted">Obligatoria para transferencias.</small>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="detalle">Detalle</label>
                        <input type="text" class="form-control" id="detalle" name="detalle" maxlength="200">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Acreditar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
