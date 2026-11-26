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
    'promocionList',                      /* Promociones (migración 102) */
    'egresoInstalaciones',                /* Egresos Instalaciones (migración 101) */
    'reportePagos',                       /* Reportes › Pagos (migración 088) */
    'reporteGanancias',                   /* Reportes › Ganancias (migración 101) */
    /* Punto de Venta y Caja (migración 095). posTicket: la nota de venta. */
    'posRegistro', 'posVenta', 'posVentas', 'posCaja', 'posProductos', 'posEgresos', 'posCompras', 'posTicket',
];
