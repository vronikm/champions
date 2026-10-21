<?php
/*
|--------------------------------------------------------------------------
| Vistas navegables del módulo Arena
|--------------------------------------------------------------------------
| Fuente única de verdad sobre qué rutas existen. La usan el front
| controller para resolver URLs y DigiSports Core para validar que un menú
| apunte a una vista real.
*/

return [
    'panel',
    'instalacionList', 'instalacionForm',
    'horarioList',
    'bloqueoList',
    'clienteList', 'clienteForm',
    'reservaList', 'reservaForm', 'reservaDetalle',
    'reciboPago', 'reciboPdf',
    'monederoList', 'monederoDetalle',
];
