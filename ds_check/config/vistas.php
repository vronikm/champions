<?php
/*
|--------------------------------------------------------------------------
| Vistas navegables del módulo DigiCheck
|--------------------------------------------------------------------------
| Con DS_PERMISOS_ESTRICTOS, además de estar aquí hay que tener la vista
| concedida en seguridad_permiso (migración 091).
*/

return [
    /* La pantalla de la cancha: escaneo continuo, sin conexión. */
    'escanear',

    /* Los carnets con su QR: el listado, el carnet de un alumno en grande
       (para mostrarlo en el celular) y la hoja para imprimir. */
    'carnets',
];
