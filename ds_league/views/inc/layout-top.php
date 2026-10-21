<?php
/*
| Cabecera de las vistas de DigiSports League.
| Espera $tituloVista y $vistaActual definidos por la vista que lo incluye.
|
| El armazón está en ds_core/inc/layout-modulo.php: es el mismo para
| Arena, League y Core, y tenerlo tres veces significaba arreglar una copia
| y olvidar las otras dos. Aquí sólo queda lo que de verdad distingue a
| este módulo.
*/

use league\controllers\leagueController;

if (!isset($insLeague)) {
    $insLeague = new leagueController();
}

$tituloVista = $tituloVista ?? 'League';
$vistaActual = $vistaActual ?? '';

$moduloNombre = 'League';
/* Violeta oscuro de la familia de --ds-league (#a78bfa). El token es un
   tono claro pensado para el fondo oscuro del Hub: con texto blanco da
   2.72 de contraste. Éste da 5.70. Medido. */
$moduloAcento = '#7c3aed';
$moduloInicio = APP_URL . 'panel/';
$moduloMenu   = $insLeague->menuLateral($vistaActual);

require __DIR__ . "/../../../ds_core/inc/layout-modulo.php";
