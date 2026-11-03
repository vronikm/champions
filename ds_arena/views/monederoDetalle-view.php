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
$origenes = ['VUE' => 'Vuelto', 'TRA' => 'Transferencia', 'EFE' => 'Depósito en efectivo',
             'RES' => 'Aplicado a reserva', 'DEV' => 'Egreso a pedido del cliente', 'AJU' => 'Ajuste'];

$puedeRetirar   = puede_eliminar('monederoList') || puede_editar('monederoList');
/* Al cliente dado de baja no se le acredita: el servidor lo rechaza. */
$puedeAcreditar = puede_crear('monederoList') && $cliente['cliente_estado'] !== 'E';

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row">
    <div class="col-lg-5 col-xl-4 mb-3">
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

            <div class="card-footer ds-acciones ar-mon__acciones">
                <?php echo ds_boton('volver', 'Volver', [
                    'href' => APP_URL . 'monederoList/', 'estilo' => 'secondary']); ?>
                <?php if ($puedeRetirar && $saldo > 0): ?>
                    <?php echo ds_boton('quitar', 'Egreso', [
                        'type'   => 'button',
                        'title'  => 'Registrar egreso',
                        'estilo' => 'outline-danger',
                        /* bs-: Bootstrap 5. Con «toggle»/«target» a secas
                           (Bootstrap 4) el botón no abría el modal. */
                        'data'   => ['bs-toggle' => 'modal', 'bs-target' => '#modalEgreso'],
                    ]); ?>
                <?php endif; ?>
                <?php /* El ingreso, la acción principal, va el último: a la derecha. */ ?>
                <?php if ($puedeAcreditar): ?>
                    <?php echo ds_boton('nuevo', 'Ingreso', [
                        'type'   => 'button',
                        'title'  => 'Registrar ingreso',
                        'estilo' => 'success',
                        'data'   => ['bs-toggle' => 'modal', 'bs-target' => '#modalIngreso'],
                    ]); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7 col-xl-8 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0"><?php echo count($movimientos); ?> movimiento<?php echo count($movimientos) === 1 ? '' : 's'; ?></h3>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
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
                            /* movimiento_fecha es la fecha contable (DATE, sin hora); la
                               hora es la del registro (movimiento_fecharegistro). Si se
                               registró otro día, se muestra completo para no confundir. */
                            $registro = (int)($mv['registro_ts'] ?? 0);
                            $mismoDia = $registro && date('Y-m-d', $registro) === $mv['movimiento_fecha'];
                        ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?php echo date('d/m/Y', strtotime($mv['movimiento_fecha'])); ?>
                                    <?php if ($registro): ?>
                                        <small class="d-block text-muted">
                                            <i class="far fa-clock me-1" aria-hidden="true"></i><?php echo $mismoDia ? date('H:i', $registro) : 'registrado ' . date('d/m/Y H:i', $registro); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
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

<?php if ($puedeAcreditar): ?>
<!-- Los id llevan el prefijo «ing_»: el modal de egreso ya usa #valor y #detalle. -->
<div class="modal fade" id="modalIngreso" tabindex="-1" aria-labelledby="modalIngresoTitulo">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                  data-confirmar="El importe se sumará al saldo a favor del cliente.">
                <input type="hidden" name="modulo_arena" value="ingresoMonedero">
                <input type="hidden" name="cliente_id" value="<?php echo $clienteid; ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalIngresoTitulo">Registrar ingreso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted small">
                        Dinero que el cliente deja a favor para sus próximas reservas.<br>
                        Saldo actual: <strong class="text-success">$<?php echo number_format($saldo, 2); ?></strong>
                    </p>

                    <div class="row g-2">
                        <div class="mb-3 col-sm-7">
                            <label for="ing_origen">Origen</label>
                            <select class="form-select" id="ing_origen" name="origen">
                                <option value="TRA">Transferencia del cliente</option>
                                <option value="EFE">Depósito en efectivo</option>
                                <option value="AJU">Ajuste manual</option>
                            </select>
                        </div>
                        <div class="mb-3 col-sm-5">
                            <label for="ing_valor">Importe <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" inputmode="decimal"
                                   class="form-control" id="ing_valor" name="valor" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="ing_referencia">Referencia <span class="text-danger" id="ing_refObligatoria">*</span></label>
                        <input type="text" class="form-control" id="ing_referencia" name="referencia" maxlength="60" required
                               placeholder="N.º o comprobante de la transferencia">
                        <small class="text-muted" id="ing_refAyuda">Obligatoria para transferencias.</small>
                    </div>

                    <div class="mb-0">
                        <label for="ing_detalle">Detalle</label>
                        <input type="text" class="form-control" id="ing_detalle" name="detalle" maxlength="200">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Registrar ingreso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* La referencia sólo es obligatoria en las transferencias (lo exige
   también el servidor); en efectivo o ajuste es opcional. */
(function () {
    var origen = document.getElementById('ing_origen');
    var ref    = document.getElementById('ing_referencia');
    var marca  = document.getElementById('ing_refObligatoria');
    var ayuda  = document.getElementById('ing_refAyuda');
    var modal  = document.getElementById('modalIngreso');
    if (!origen || !ref) { return; }

    function ajustar() {
        var tra = origen.value === 'TRA';
        ref.required = tra;
        marca.hidden = !tra;
        ayuda.textContent = tra ? 'Obligatoria para transferencias.' : 'Opcional: n.º de recibo u otra referencia.';
        ref.placeholder   = tra ? 'N.º o comprobante de la transferencia' : '';
    }
    origen.addEventListener('change', ajustar);
    ajustar();

    modal.addEventListener('shown.bs.modal', function () {
        document.getElementById('ing_valor').focus();
    });
})();
</script>
<?php endif; ?>

<style>
/* Volver, Egreso e Ingreso en una sola fila, también en el teléfono: una
   rejilla de columnas iguales en lugar de la pila de .ds-acciones (que en
   pantalla estrecha pone cada botón a lo ancho). Los textos son cortos
   para que quepan; el completo va en el title y en el título del modal. */
.ar-mon__acciones {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(0, 1fr);
    gap: .5rem;
}
/* El clearfix de .card-footer (::after) sería una celda más de la rejilla. */
.ar-mon__acciones::before,
.ar-mon__acciones::after { content: none; display: none; }
.ar-mon__acciones .btn {
    min-width: 0;
    width: 100%;
    min-height: 2.5rem;
    padding-inline: .5rem;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
