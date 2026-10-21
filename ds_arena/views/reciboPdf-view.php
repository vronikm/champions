<?php
/*
| PDF del recibo de un pago de Arena, con sesión.
|   reciboPdf/?id=N              se abre en el navegador (ver / imprimir)
|   reciboPdf/?id=N&descargar=1  se descarga como archivo
| El mismo PDF que se adjunta por correo y que abre el enlace de WhatsApp:
| los tres salen de arenaController::reciboPdf().
*/

use arena\controllers\arenaController;

$insArena = new arenaController();
$recibo   = $insArena->pagoRecibo((int)($_GET['id'] ?? 0));

if ($recibo === null) {
    header("Location: " . APP_URL . "reservaList/");
    exit();
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . (isset($_GET['descargar']) ? 'attachment' : 'inline')
       . '; filename="Recibo-' . $recibo['recibo_numero'] . '.pdf"');
header('Cache-Control: private, no-store');
echo $insArena->reciboPdf($recibo);
