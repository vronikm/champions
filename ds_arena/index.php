<?php
/*
|--------------------------------------------------------------------------
| DigiSports Arena — front controller
|--------------------------------------------------------------------------
| Aplica los mismos tres niveles de control de acceso que el resto del
| ecosistema: sesion, modulo y permiso de lectura sobre la vista.
*/

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/../ds_core/autoload.php";
require_once __DIR__ . "/../ds_core/modulos.php";
require_once __DIR__ . "/../ds_core/inc/session_start.php";

$listaBlanca = require __DIR__ . "/config/vistas.php";

$url   = isset($_GET['views']) ? explode('/', $_GET['views']) : ['panel'];
$vista = $url[0] !== '' ? $url[0] : 'panel';

/*----------  Recibo compartido por WhatsApp  ----------*/
/* Lo abre el cliente, que no tiene cuenta: no pasa por los controles de
   sesión. Su puerta es la firma del enlace (arenaController::
   pagoDeEnlacePublico): sirve UN recibo, caduca sola y deja de valer si el
   pago se anula. Sólo entrega ese PDF; nunca otra vista. */
if ($vista === 'reciboPublico') {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, no-store');
    header('Referrer-Policy: no-referrer');
    $insRecibo = new \arena\controllers\arenaController();
    $pagoPublico = $insRecibo->pagoDeEnlacePublico((string)($url[1] ?? ''));
    if ($pagoPublico > 0) {
        $r = $insRecibo->pagoRecibo($pagoPublico);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Recibo-' . $r['recibo_numero'] . '.pdf"');
        echo $insRecibo->reciboPdf($r);
    } else {
        require_once __DIR__ . "/../ds_core/inc/reciboEnlaceInvalido.php";
    }
    exit();
}

/*----------  Nivel 0: sesion activa  ----------*/
if (!usuario_autenticado()) {
    header("Location: " . DS_FUTBOL_URL . "login/");
    exit();
}

/*----------  Nivel 1: acceso al modulo  ----------*/
if (!usuario_tiene_modulo(DS_MODULO)) {
    http_response_code(403);
    require_once __DIR__ . "/views/accesoDenegado-view.php";
    exit();
}

/*----------  Vista existente  ----------*/
if (!in_array($vista, $listaBlanca, true)
    || !is_file(__DIR__ . "/views/" . $vista . "-view.php")) {
    http_response_code(404);
    $vista = 'panel';
}

/*----------  Nivel 2: permiso de lectura sobre la vista  ----------*/
if (!usuario_tiene_permiso($vista)) {
    http_response_code(403);
    require_once __DIR__ . "/views/accesoDenegado-view.php";
    exit();
}

require_once __DIR__ . "/views/" . $vista . "-view.php";
