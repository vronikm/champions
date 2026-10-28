<?php
/*
|--------------------------------------------------------------------------
| Vistas navegables del módulo Core
|--------------------------------------------------------------------------
| Misma función que la lista blanca de cualquier módulo: la usa el front
| controller para resolver rutas y el propio Core para validar menús.
*/

return [
    'panel',
    /* Seguridad */
    'usuarioList', 'usuarioForm',
    'rolList',
    'permisoRol',
    'menuList', 'menuForm',
    'moduloRol',
    /* Configuración compartida del ecosistema */
    'organizacionForm',
    'sedeList', 'sedeForm',
    'catalogoList',
    'facturacionConfigSri',
    'puntoEmisionList',
    'carnetConfig',
    'tarjetaCumple',
    'redesSociales',
    'consentimientoTextos',
];
