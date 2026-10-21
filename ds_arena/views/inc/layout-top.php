<?php
/*
| Cabecera de las vistas de DigiSports Arena.
| Espera $tituloVista y $vistaActual definidos por la vista que lo incluye.
|
| El armazón está en ds_core/inc/layout-modulo.php, compartido con League
| y Core.
*/

use arena\controllers\arenaController;

if (!isset($insArena)) {
    $insArena = new arenaController();
}

$tituloVista = $tituloVista ?? 'Arena';
$vistaActual = $vistaActual ?? '';

$moduloNombre = 'Arena';
/* Cian oscuro de la familia de --ds-arena (#22d3ee). El token es un tono
   muy claro para el fondo oscuro del Hub: con texto blanco encima da 1.9
   de contraste, ilegible. Éste sube por encima del mínimo. Medido. */
$moduloAcento = '#0e7490';
$moduloInicio = APP_URL . 'panel/';
$moduloMenu   = $insArena->menuLateral($vistaActual);

require __DIR__ . "/../../../ds_core/inc/layout-modulo.php";
