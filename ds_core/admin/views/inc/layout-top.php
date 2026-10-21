<?php
/*
| Cabecera de las vistas de DigiSports Core.
| Espera $tituloVista y $vistaActual definidos por la vista que lo incluye.
|
| El armazón está en ds_core/inc/layout-modulo.php, compartido con Arena y
| League.
*/

use admin\controllers\coreController;

if (!isset($insCore)) {
    $insCore = new coreController();
}

$tituloVista = $tituloVista ?? 'Core';
$vistaActual = $vistaActual ?? '';

$moduloNombre = 'Core';
/* Rosa oscuro de la familia de --ds-core (#f43f5e). El token está pensado
   para el fondo oscuro del Hub; con texto blanco encima no llega al
   mínimo de contraste. Éste sí. Medido, no estimado. */
$moduloAcento = '#be123c';
$moduloInicio = APP_URL . 'panel/';
$moduloMenu   = $insCore->menuLateral($vistaActual);

require __DIR__ . "/../../../inc/layout-modulo.php";
