<?php
/*
|--------------------------------------------------------------------------
| Vistas navegables del módulo League
|--------------------------------------------------------------------------
| Fuente única de verdad sobre qué rutas existen. La usan el front
| controller para resolver URLs y DigiSports Core para validar que un menú
| apunte a una vista real.
|
| Estar en esta lista NO concede acceso: sólo declara que la ruta existe.
| El permiso se resuelve aparte y, en este módulo, en modo estricto: una
| vista que no esté ADEMÁS registrada en seguridad_menu se deniega.
*/

return [
    'panel',

    /* Configuración de la competencia */
    'temporadaList',
    'torneoList',
    'categoriaList',

    /* Participantes y operación */
    'equipoList',
    'categoriaPanel',

    /* Agenda propia: filtrada por designación para cualquiera */
    'partidoAgenda',

    /* Plantilla de un equipo inscrito */
    'plantillaPanel',

    /* Sorteo de grupos de una fase */
    'sorteoPanel',

    /* Cuadro eliminatorio de una categoría */
    'playoffPanel',

    /* Estadísticas */
    'actaPartido',
    'rankingPanel',

    /* Finanzas */
    'conceptoList',
    'cobranzaPanel',
];
