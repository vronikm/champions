<?php
/*
| Arena › Punto de venta › Nota de venta (migración 095).
|
| El comprobante de una venta en formato ticket (80 mm) para imprimir, con
| la descarga en PDF y el envío por WhatsApp. Página suelta, sin menú: es lo
| que se imprime. ?imprimir=1 abre el diálogo de impresión al cargar;
| ?whatsapp=1 abre el envío por WhatsApp (lo usa el cobro de posVenta);
| ?pdf=1 entrega el PDF (con &descargar=1, como archivo para guardar).
|
| Cabecera: el nombre de la instalación de la sede de la caja
| (posController::cabeceraTicket), no «ORGANIZACIÓN, SEDE X». Pie: el texto
| de Core › Organización › «Pie de los PDF», el mismo de los recibos.
|
| WhatsApp: el mismo modal que el recibo de una reserva (reciboPago), pieza
| del núcleo ds_core/inc/whatsappRecibo.php: el PDF y el mensaje juntos,
| nunca un enlace.
*/

use arena\controllers\posController;

$pos = new posController();
$v = $pos->venta((int)($_GET['id'] ?? 0));
if (!$v) {
    http_response_code(404);
    require __DIR__ . '/accesoDenegado-view.php';
    exit();
}
if (($_GET['pdf'] ?? '') === '1') {
    $disposicion = ($_GET['descargar'] ?? '') === '1' ? 'attachment' : 'inline';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposicion . '; filename="Nota-de-venta-' . $v['venta_numero'] . '.pdf"');
    echo $pos->ticketPdf($v);
    exit();
}
$h    = static fn ($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$din  = static fn ($x) => '$' . number_format((float)$x, 2);
$cant = static fn ($x) => rtrim(rtrim(number_format((float)$x, 2), '0'), '.');
$sede     = (int)($v['caja_sedeid'] ?? 0);
$id       = (int)$v['venta_id'];
$anulada  = $v['venta_estado'] === 'X';
$cabecera = $pos->cabeceraTicket($v);
$logo     = function_exists('ds_logo_url') ? ds_logo_url($sede) : '';
$pie      = function_exists('ds_pie_pdf_texto') ? ds_pie_pdf_texto() : '';
$pdf      = APP_URL . 'posTicket/?id=' . $id . '&pdf=1';
$fecha    = strtotime($v['venta_fecharegistro']);

/* WhatsApp: el detalle de la compra, ya redactado; el saludo, el aviso del
   PDF adjunto y la despedida los pone la pieza del núcleo. */
$waLineas = [
    '🧾 Nota de venta N.º ' . $v['venta_numero'],
    '📍 ' . $cabecera,
    '📅 ' . date('d/m/Y', $fecha) . ', ' . date('H:i', $fecha),
];
$maxLineas = 8;
foreach (array_slice($v['lineas'], 0, $maxLineas) as $l) {
    $waLineas[] = '• ' . $cant($l['detalle_cantidad']) . ' x ' . $l['detalle_nombre'] . ': USD ' . number_format((float)$l['detalle_subtotal'], 2);
}
if (count($v['lineas']) > $maxLineas) {
    $waLineas[] = '• … y ' . (count($v['lineas']) - $maxLineas) . ' producto(s) más';
}
if ((float)$v['venta_descuento'] > 0) {
    $waLineas[] = '🏷️ Descuento: USD ' . number_format((float)$v['venta_descuento'], 2);
}
$formas = implode(', ', array_unique(array_column($v['pagos'], 'forma_nombre')));
$waLineas[] = '💵 Total: USD ' . number_format((float)$v['venta_total'], 2) . ($formas !== '' ? ' (' . $formas . ')' : '');
if ($anulada) {
    $waLineas[] = '⚠️ Esta nota de venta está ANULADA.';
}
$waDatos = [
    'repre'     => (string)($v['cliente_nombre'] ?? ''),
    'subtitulo' => $v['cliente_nombre'] ? 'Cliente · ' . $v['cliente_identificacion'] : 'Consumidor final',
    'celular'   => preg_replace('/\D/', '', (string)($v['cliente_celular'] ?? '')),
    'intro'     => ds_nombre_organizacion() . ($anulada ? ' le informa sobre su nota de venta:' : ' le confirma su compra:'),
    'lineas'    => $waLineas,
    'recibo'    => $v['venta_numero'],
    'pdf'       => $pdf,
];
?><!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Nota de venta <?php echo $h($v['venta_numero']); ?></title>
    <link rel="stylesheet" href="<?php echo DS_BOOTSTRAP5_URL; ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
    <link rel="stylesheet" href="<?php echo DS_VENDOR_URL; ?>css/sweetalert2.min.css">
    <style>
        :root { color-scheme: light; --tk-acento: #0e7490; }
        body { margin: 0; background: #e9ecef; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; }

        /* Barra de acciones: fija arriba, no se imprime. */
        .tk-barra { position: sticky; top: 0; z-index: 10; background: rgba(233, 236, 239, .92); backdrop-filter: blur(6px);
                    border-bottom: 1px solid #dee2e6; }
        .tk-barra__in { max-width: 46rem; margin: 0 auto; padding: .75rem 1rem; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .tk-barra__grupo { display: flex; flex-wrap: wrap; gap: .5rem; margin-left: auto; }
        .tk-barra .btn { min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; gap: .45rem; font-weight: 500; }
        .tk-barra .btn-primary { background: var(--tk-acento); border-color: var(--tk-acento); }
        .tk-barra .btn-primary:hover, .tk-barra .btn-primary:focus-visible { background: #0b5f75; border-color: #0b5f75; }

        .tk-estado { max-width: 80mm; margin: 1rem auto .75rem; display: flex; align-items: center; justify-content: center; gap: .5rem;
                     font-size: .85rem; color: #495057; text-align: center; }

        /* El ticket */
        .ticket { width: 80mm; max-width: calc(100% - 2rem); box-sizing: border-box; margin: 0 auto 2rem; background: #fff; padding: 5mm 5mm 6mm;
                  font-size: 12px; line-height: 1.35; color: #111; box-shadow: 0 .35rem 1.25rem rgba(0,0,0,.12); border-radius: 4px; position: relative; }
        .ticket .c { text-align: center; } .ticket .r { text-align: right; white-space: nowrap; } .ticket .b { font-weight: 700; }
        .tk-logo { display: block; max-width: 34mm; max-height: 18mm; margin: 0 auto 4px; object-fit: contain; }
        .tk-nombre { font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: .01em; line-height: 1.2; }
        .tk-dato { font-size: 11px; color: #333; }
        .tk-titulo { font-size: 14px; font-weight: 800; letter-spacing: .02em; }
        .ticket hr { border: 0; border-top: 1px dashed #666; margin: 7px 0; opacity: 1; }
        .ticket table { width: 100%; border-collapse: collapse; }
        .ticket td { vertical-align: top; padding: 1px 0; }
        .tk-linea td { padding-top: 3px; }
        .tk-total td { font-size: 17px; font-weight: 800; padding: 4px 0 3px; }
        .tk-anulada { border: 2px solid #c00; color: #c00; font-weight: 800; text-align: center; padding: 3px; margin: 6px 0 3px; font-size: 14px; letter-spacing: .1em; }
        .tk-gracias { font-weight: 700; font-size: 12.5px; }
        .tk-aviso { font-size: 10px; color: #444; }
        .tk-pie { font-size: 9.5px; color: #666; margin-top: 6px; line-height: 1.3; word-break: break-word; }

        @media (max-width: 575.98px) {
            .tk-barra__in { padding: .6rem 1rem; }
            .tk-barra__grupo { margin-left: 0; width: 100%; display: grid; grid-template-columns: 1fr 1fr; }
            .tk-barra__grupo .tk-wa { grid-column: 1 / -1; order: -1; min-height: 3rem; font-size: 1.02rem; }
            .tk-volver { width: 100%; }
        }

        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff; }
            .tk-barra, .tk-estado, .modal, .modal-backdrop, .swal2-container { display: none !important; }
            .ticket { box-shadow: none; margin: 0; width: 80mm; max-width: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="tk-barra">
        <div class="tk-barra__in">
            <a class="btn btn-outline-secondary tk-volver" href="<?php echo APP_URL; ?>posVenta/">
                <i class="fas fa-basket-shopping" aria-hidden="true"></i>Volver a vender
            </a>
            <div class="tk-barra__grupo">
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print" aria-hidden="true"></i>Imprimir
                </button>
                <a class="btn btn-outline-secondary" href="<?php echo $h($pdf . '&descargar=1'); ?>" title="Guardar la nota de venta en PDF">
                    <i class="fas fa-download" aria-hidden="true"></i>Descargar PDF
                </a>
                <button type="button" class="btn btn-whatsapp tk-wa" data-bs-toggle="modal" data-bs-target="#modalWhatsapp">
                    <i class="fab fa-whatsapp" aria-hidden="true"></i>Enviar por WhatsApp
                </button>
            </div>
        </div>
    </div>

    <div class="tk-estado">
        <?php if ($anulada): ?>
            <span class="badge rounded-pill text-bg-danger"><i class="fas fa-ban me-1" aria-hidden="true"></i>Anulada</span>
        <?php else: ?>
            <span class="badge rounded-pill text-bg-success"><i class="fas fa-check me-1" aria-hidden="true"></i>Venta registrada</span>
        <?php endif; ?>
        <span><?php echo $h($v['venta_numero']); ?> · <?php echo $din($v['venta_total']); ?></span>
    </div>

    <div class="ticket">
        <?php if ($logo !== ''): ?><img class="tk-logo" src="<?php echo $h($logo); ?>" alt=""><?php endif; ?>
        <div class="c tk-nombre"><?php echo $h($cabecera); ?></div>
        <?php if ($v['sede_direccion']): ?><div class="c tk-dato"><?php echo $h($v['sede_direccion']); ?></div><?php endif; ?>
        <?php if ($v['sede_telefono']): ?><div class="c tk-dato">Tel. <?php echo $h($v['sede_telefono']); ?></div><?php endif; ?>
        <hr>
        <div class="c tk-titulo">NOTA DE VENTA <?php echo $h($v['venta_numero']); ?></div>
        <div class="c"><?php echo date('d/m/Y H:i', $fecha); ?> · Atendió: <?php echo $h($v['vendedor']); ?></div>
        <div class="c">Cliente: <?php echo $v['cliente_nombre'] ? $h($v['cliente_nombre'] . ' · ' . $v['cliente_identificacion']) : 'Consumidor final'; ?></div>
        <?php if ($anulada): ?>
            <div class="tk-anulada">ANULADA</div>
            <div class="c" style="font-size:11px;"><?php echo $h($v['venta_anulamotivo']); ?></div>
        <?php endif; ?>
        <hr>
        <table>
            <?php foreach ($v['lineas'] as $l): ?>
                <tr class="tk-linea"><td colspan="2" class="b"><?php echo $h($l['detalle_nombre']); ?></td></tr>
                <tr><td><?php echo $cant($l['detalle_cantidad']); ?> x <?php echo $din($l['detalle_precio']); ?></td><td class="r"><?php echo $din($l['detalle_subtotal']); ?></td></tr>
            <?php endforeach; ?>
        </table>
        <hr>
        <table>
            <?php if ((float)$v['venta_descuento'] > 0): ?>
                <tr><td>Subtotal</td><td class="r"><?php echo $din($v['venta_subtotal']); ?></td></tr>
                <tr><td>Descuento</td><td class="r">-<?php echo $din($v['venta_descuento']); ?></td></tr>
            <?php endif; ?>
            <tr class="tk-total"><td>TOTAL</td><td class="r"><?php echo $din($v['venta_total']); ?></td></tr>
            <?php foreach ($v['pagos'] as $p): ?>
                <tr><td><?php echo $h($p['forma_nombre']); ?><?php echo $p['pago_referencia'] ? ' · ' . $h($p['pago_referencia']) : ''; ?></td>
                    <td class="r"><?php echo $din($p['pago_recibido'] ?? $p['pago_valor']); ?></td></tr>
                <?php if ((float)$p['pago_cambio'] > 0): ?><tr><td>Cambio</td><td class="r"><?php echo $din($p['pago_cambio']); ?></td></tr><?php endif; ?>
            <?php endforeach; ?>
        </table>
        <hr>
        <div class="c tk-gracias">¡Gracias por su compra!</div>
        <div class="c tk-aviso">Documento interno sin valor tributario.</div>
        <?php if ($pie !== ''): ?><div class="c tk-pie"><?php echo $h($pie); ?></div><?php endif; ?>
    </div>

    <?php require DS_HUB_PATH . 'ds_core/inc/whatsappRecibo.php'; ?>

    <script src="<?php echo DS_BOOTSTRAP5_URL; ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo DS_VENDOR_URL; ?>js/sweetalert2.all.min.js"></script>
    <?php if (($_GET['imprimir'] ?? '') === '1'): ?>
        <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
    <?php elseif (($_GET['whatsapp'] ?? '') === '1'): ?>
        <!-- Desde el cobro del punto de venta: el modal ya abierto. -->
        <script>window.addEventListener('load', function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('modalWhatsapp')).show(); });</script>
    <?php endif; ?>
</body>
</html>
