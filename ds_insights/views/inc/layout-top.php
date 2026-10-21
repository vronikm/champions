<?php
/*
| Cabecera de las vistas de DigiSports Insights.
| Espera $tituloVista y $vistaActual definidos por la vista que lo incluye.
|
| El armazón está en ds_core/inc/layout-modulo.php, compartido con Arena,
| League y Core. Insights no lo duplica: lo reutiliza igual que ellos.
*/

use insights\controllers\insightsController;

require_once __DIR__ . '/formato.php';

if (!isset($insInsights)) {
    $insInsights = new insightsController();
}

$tituloVista = $tituloVista ?? 'Insights';
$vistaActual = $vistaActual ?? '';

$moduloNombre = 'Insights';

/* Verde oscuro de la familia de --ds-insights (#22c55e). El token es
   demasiado claro para llevar texto blanco encima: da 2.28 de contraste,
   por debajo del 4.5 que exige WCAG. Éste da 5.02. Medido, no elegido a
   ojo, y por el mismo motivo que Arena bajó su cian a #0e7490. */
$moduloAcento = '#15803d';

$moduloInicio = APP_URL . 'dashboard/';
$moduloMenu   = $insInsights->menuLateral($vistaActual);

require __DIR__ . "/../../../ds_core/inc/layout-modulo.php";
