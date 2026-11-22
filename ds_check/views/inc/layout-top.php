<?php
/*
| Cabecera de las vistas de DigiCheck.
| Espera $tituloVista y $vistaActual; $cabeceraExtra (HTML opcional) va al
| final del <head> (la PWA de «escanear»).
|
| El armazón está en ds_core/inc/layout-modulo.php, compartido con Arena,
| League, Insights y Core.
*/

use check\controllers\checkController;

if (!isset($insCheck)) {
    $insCheck = new checkController();
}

$tituloVista  = $tituloVista ?? 'DigiCheck';
$vistaActual  = $vistaActual ?? '';
$moduloNombre = 'DigiCheck';
$moduloAcento = DS_CHECK_ACENTO;
$moduloInicio = APP_URL . 'escanear/';
$moduloMenu   = $insCheck->menuLateral($vistaActual);

require __DIR__ . "/../../../ds_core/inc/layout-modulo.php";
