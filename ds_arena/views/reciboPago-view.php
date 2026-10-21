<?php
/*
| Recibo de un pago de una reserva.
|
| Es a donde se llega tras registrar un abono (arenaController::registrarPago)
| y desde el listado y el detalle de reservas. Desde aquí el recibo se ve,
| se imprime, se descarga o se envía por correo o WhatsApp, como el recibo
| de la escuela en Fútbol (ds_futbol/pagosRecibo).
*/

use arena\controllers\arenaController;

$insArena = new arenaController();
$r = $insArena->pagoRecibo((int)($_GET['id'] ?? 0));

if ($r === null) {
    header("Location: " . APP_URL . "reservaList/");
    exit();
}

$tituloVista = 'Recibo ' . $r['recibo_numero'];
$vistaActual = 'reservaList';

$h       = static fn ($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$din     = static fn ($v) => '$' . number_format((float)$v, 2);
$pdf     = APP_URL . 'reciboPdf/?id=' . (int)$r['pago_id'];
$hermanos = $insArena->pagosDeReservas([(int)$r['reserva_id']])[(int)$r['reserva_id']] ?? [];
$pagada  = (float)$r['saldo_despues'] <= 0.001;
$sedeId  = (int)$r['reserva_sedeid'];
$logo    = ds_logo_url($sedeId);

/* WhatsApp: la pieza es del núcleo (ds_core/inc/whatsappRecibo.php). */
$enlace  = $insArena->enlacePublicoRecibo((int)$r['pago_id']);
$waDatos = [
    'repre'     => $r['cliente_nombre'],
    'subtitulo' => 'Cliente · ' . $r['cliente_identificacion'],
    'celular'   => preg_replace('/\D/', '', (string)$r['cliente_celular']),
    'intro'     => ds_nombre_organizacion() . ' le confirma el pago de su reserva:',
    'lineas'    => [
        '🧾 Recibo N.º ' . $r['recibo_numero'],
        '🏟️ Reserva ' . $r['reserva_codigo'] . ': ' . $r['instalacion_nombre'] . ($r['sede_nombre'] ? ' · ' . $r['sede_nombre'] : ''),
        '📅 ' . date('d/m/Y', strtotime($r['reserva_fecha'])) . ', de ' . substr($r['reserva_horainicio'], 0, 5) . ' a ' . substr($r['reserva_horafin'], 0, 5),
        '💵 Valor pagado: USD ' . number_format((float)$r['pago_valor'], 2) . ' (' . $r['forma_nombre'] . ')',
        $pagada ? '✅ La reserva queda pagada en su totalidad.'
                : '⏳ Saldo pendiente: USD ' . number_format((float)$r['saldo_despues'], 2),
    ],
    'recibo'    => $r['recibo_numero'],
    'enlace'    => $enlace['url'],
    'vence'     => $enlace['vence'] ? date('d/m/Y', $enlace['vence']) : '',
    'pdf'       => $pdf,
];

require_once __DIR__ . "/inc/layout-top.php";
?>

<!-- ==================== Acciones ==================== -->
<div class="ra-acciones mb-3">
    <a href="<?php echo APP_URL; ?>reservaDetalle/?id=<?php echo (int)$r['reserva_id']; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Volver a la reserva
    </a>
    <div class="ra-acciones__grupo">
        <a href="<?php echo $h($pdf); ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm" title="Abrir el PDF para verlo o imprimirlo">
            <i class="fas fa-print me-1"></i> Ver / imprimir
        </a>
        <a href="<?php echo $h($pdf . '&descargar=1'); ?>" class="btn btn-outline-secondary btn-sm" title="Guardar el PDF en este equipo">
            <i class="fas fa-download me-1"></i> Descargar PDF
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCorreo">
            <i class="fas fa-envelope me-1"></i> Enviar por correo
        </button>
        <button type="button" class="btn btn-whatsapp btn-sm" data-bs-toggle="modal" data-bs-target="#modalWhatsapp">
            <i class="fab fa-whatsapp me-1"></i> Enviar por WhatsApp
        </button>
    </div>
</div>

<div class="row g-3">
    <!-- ==================== El recibo ==================== -->
    <div class="col-xl-8">
        <div class="card ra-recibo">
            <div class="card-body">
                <div class="ra-recibo__cabecera">
                    <?php if ($logo !== ''): ?>
                        <img src="<?php echo $h($logo); ?>" alt="" class="ra-recibo__logo">
                    <?php endif; ?>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold fs-5"><?php echo $h(ds_nombre_organizacion_may($sedeId)); ?></div>
                        <div class="small text-body-secondary">Alquiler de instalaciones · Arena</div>
                        <div class="small text-body-secondary">
                            Sede <?php echo $h($r['sede_nombre']); ?>
                            <?php if ($r['sede_direccion']): ?> · <?php echo $h($r['sede_direccion']); ?><?php endif; ?>
                            <?php if ($r['sede_telefono']): ?> · Tel. <?php echo $h($r['sede_telefono']); ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="ra-recibo__numero">
                        <span>Recibo de pago</span>
                        <strong><?php echo $h($r['recibo_numero']); ?></strong>
                        <small><?php echo date('d/m/Y', strtotime($r['pago_fecha'])); ?></small>
                    </div>
                </div>

                <div class="ra-recibo__monto">
                    <div>
                        <span class="text-body-secondary small d-block">Valor recibido</span>
                        <span class="ra-recibo__cifra"><?php echo $din($r['pago_valor']); ?></span>
                    </div>
                    <span class="badge rounded-pill <?php echo $pagada ? 'text-bg-success' : 'text-bg-warning'; ?>">
                        <i class="fas <?php echo $pagada ? 'fa-check-circle' : 'fa-hourglass-half'; ?> me-1"></i>
                        <?php echo $pagada ? 'Reserva pagada' : 'Saldo pendiente ' . $din($r['saldo_despues']); ?>
                    </span>
                </div>

                <dl class="ra-recibo__filas">
                    <dt>Recibí de</dt>
                    <dd><?php echo $h($r['cliente_nombre']); ?> <span class="text-body-secondary">· <?php echo $h($r['cliente_identificacion']); ?></span></dd>
                    <dt>La cantidad de</dt>
                    <dd><?php echo $h($insArena->montoEnLetras((float)$r['pago_valor'])); ?></dd>
                    <dt>Por concepto de</dt>
                    <dd><?php echo $h($r['concepto']); ?> <span class="text-body-secondary">· Reserva <?php echo $h($r['reserva_codigo']); ?></span></dd>
                    <dt>Forma de pago</dt>
                    <dd>
                        <?php echo $h($r['forma_nombre']); ?>
                        <?php if ($r['pago_referencia']): ?><span class="text-body-secondary">· Ref. <?php echo $h($r['pago_referencia']); ?></span><?php endif; ?>
                        <?php if ((float)$r['pago_vuelto'] > 0): ?>
                            <span class="text-body-secondary">· Recibido <?php echo $din($r['pago_recibido']); ?>, vuelto <?php echo $din($r['pago_vuelto']); ?><?php echo $r['pago_vueltoamonedero'] === 'S' ? ' (al monedero)' : ''; ?></span>
                        <?php endif; ?>
                    </dd>
                    <?php if ($r['pago_observacion']): ?>
                        <dt>Observación</dt>
                        <dd><?php echo $h($r['pago_observacion']); ?></dd>
                    <?php endif; ?>
                </dl>

                <div class="ra-recibo__totales">
                    <div><span>Total de la reserva</span><strong><?php echo $din($r['reserva_total']); ?></strong></div>
                    <div><span>Este pago</span><strong><?php echo $din($r['pago_valor']); ?></strong></div>
                    <div><span>Saldo pendiente</span><strong class="<?php echo $pagada ? 'text-success' : 'text-danger'; ?>"><?php echo $din($r['saldo_despues']); ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== Contexto ==================== -->
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-user me-2"></i>Cliente</h3></div>
            <div class="card-body small">
                <div class="fw-semibold"><?php echo $h($r['cliente_nombre']); ?></div>
                <div class="text-body-secondary mb-2"><?php echo $h($r['cliente_identificacion']); ?></div>
                <div><i class="fas fa-envelope fa-fw me-1 text-body-secondary"></i>
                    <?php echo $r['cliente_correo'] ? $h($r['cliente_correo']) : '<span class="text-warning">Sin correo registrado</span>'; ?></div>
                <div><i class="fas fa-mobile-alt fa-fw me-1 text-body-secondary"></i>
                    <?php echo $r['cliente_celular'] ? $h($r['cliente_celular']) : '<span class="text-warning">Sin celular registrado</span>'; ?></div>
                <a href="<?php echo APP_URL; ?>clienteForm/?id=<?php echo (int)$r['cliente_id']; ?>" class="d-inline-block mt-2">Editar datos de contacto →</a>
            </div>
        </div>

        <?php if (count($hermanos) > 1): ?>
            <div class="card ra-hermanos">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-receipt me-2"></i>Recibos de esta reserva</h3></div>
                <div class="list-group list-group-flush">
                    <?php foreach ($hermanos as $p): $actual = (int)$p['pago_id'] === (int)$r['pago_id']; ?>
                        <a href="<?php echo APP_URL; ?>reciboPago/?id=<?php echo (int)$p['pago_id']; ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?php echo $actual ? ' active' : ''; ?>"
                           <?php echo $actual ? 'aria-current="page"' : ''; ?>>
                            <span>
                                <span class="fw-semibold"><?php echo $h($p['recibo_numero']); ?></span>
                                <small class="d-block <?php echo $actual ? '' : 'text-body-secondary'; ?>"><?php echo date('d/m/Y', strtotime($p['pago_fecha'])); ?> · <?php echo $h($p['forma_nombre']); ?></small>
                            </span>
                            <strong><?php echo $din($p['pago_valor']); ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== Enviar por correo ==================== -->
<div class="modal fade" id="modalCorreo" tabindex="-1" aria-labelledby="modalCorreoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="formCorreo" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="modalCorreoTitulo"><i class="fas fa-envelope me-2"></i>Enviar recibo por correo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-body-secondary mb-3">
                    Se enviará el recibo <strong><?php echo $h($r['recibo_numero']); ?></strong> en PDF como archivo adjunto.
                </p>
                <label for="correoDestino" class="form-label">Correo del destinatario <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="correoDestino" required maxlength="120"
                       value="<?php echo $h($r['cliente_correo']); ?>" placeholder="cliente@correo.com">
                <div class="invalid-feedback">Escriba un correo válido.</div>
                <?php if (!$r['cliente_correo']): ?>
                    <div class="form-text text-warning"><i class="fas fa-exclamation-triangle me-1"></i>El cliente no tiene correo registrado.</div>
                <?php endif; ?>

                <label for="correoNota" class="form-label mt-3">Nota para el cliente <span class="text-body-secondary small">(opcional)</span></label>
                <textarea class="form-control" id="correoNota" rows="3" maxlength="500"
                          placeholder="Por ejemplo: ¡Gracias por reservar con nosotros!"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" id="correoEnviar">
                    <i class="fas fa-paper-plane me-1"></i><span>Enviar recibo</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require DS_HUB_PATH . "ds_core/inc/whatsappRecibo.php"; ?>

<script>
(function () {
    var form   = document.getElementById('formCorreo');
    var correo = document.getElementById('correoDestino');
    var nota   = document.getElementById('correoNota');
    var boton  = document.getElementById('correoEnviar');
    var modal  = document.getElementById('modalCorreo');

    modal.addEventListener('shown.bs.modal', function () { correo.focus(); correo.select(); });
    correo.addEventListener('input', function () { correo.classList.remove('is-invalid'); });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!correo.checkValidity() || !correo.value.trim()) {
            correo.classList.add('is-invalid');
            correo.focus();
            return;
        }

        var datos = new FormData();
        datos.append('modulo_arena', 'enviarReciboCorreo');
        datos.append('pago_id', '<?php echo (int)$r['pago_id']; ?>');
        datos.append('correo', correo.value.trim());
        datos.append('nota', nota.value.trim());

        var etiqueta = boton.querySelector('span');
        boton.disabled = true;
        etiqueta.textContent = 'Enviando…';

        fetch('<?php echo APP_URL; ?>ajax/arenaAjax.php', { method: 'POST', body: datos, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (a) {
                if (a.icono === 'success') { bootstrap.Modal.getInstance(modal).hide(); }
                Swal.fire({ icon: a.icono || 'info', title: a.titulo || '', text: a.texto || '',
                            confirmButtonText: 'Aceptar', confirmButtonColor: '#1d3f9e' });
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Sin respuesta',
                            text: 'No se pudo contactar con el servidor. Inténtelo de nuevo.' });
            })
            .finally(function () {
                boton.disabled = false;
                etiqueta.textContent = 'Enviar recibo';
            });
    });
})();
</script>

<style>
    .ra-acciones { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between; }
    .ra-acciones__grupo { display: flex; flex-wrap: wrap; gap: .5rem; }
    .ra-recibo { border-top: 4px solid var(--ds-acento, #0e7490); }
    .ra-recibo__cabecera { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; padding-bottom: 14px; border-bottom: 1px dashed var(--bs-border-color); }
    .ra-recibo__logo { width: 64px; height: 64px; object-fit: contain; border-radius: 10px; background: #fff; color: #212529; padding: 3px; }
    .ra-recibo__numero { text-align: right; margin-left: auto; }
    .ra-recibo__numero span  { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary-color); }
    .ra-recibo__numero strong{ display: block; font-size: 1.35rem; letter-spacing: .02em; }
    .ra-recibo__numero small { color: var(--bs-secondary-color); }
    .ra-recibo__monto { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 16px 0; }
    .ra-recibo__cifra { font-size: 2rem; font-weight: 700; line-height: 1.1; }
    .ra-recibo__filas { display: grid; grid-template-columns: 150px 1fr; gap: 8px 16px; margin: 0 0 16px; }
    .ra-recibo__filas dt { font-weight: 600; color: var(--bs-secondary-color); }
    .ra-recibo__filas dd { margin: 0; }
    .ra-recibo__totales { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .ra-recibo__totales > div { padding: 10px 12px; border-radius: var(--ds-radius-sm, 10px); background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); }
    .ra-recibo__totales span { display: block; font-size: .75rem; color: var(--bs-secondary-color); }
    .ra-recibo__totales strong { font-size: 1.1rem; }
    .ra-hermanos .list-group-item.active { background: var(--ds-acento, #0e7490); border-color: var(--ds-acento, #0e7490); }
    @media (max-width: 575.98px) {
        .ra-recibo__numero { text-align: left; margin-left: 0; width: 100%; }
        .ra-recibo__filas { grid-template-columns: 1fr; gap: 2px; }
        .ra-recibo__filas dd { margin-bottom: 8px; }
        .ra-recibo__totales { grid-template-columns: 1fr; }
        .ra-acciones__grupo .btn { flex: 1 1 calc(50% - .5rem); }
    }
</style>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
