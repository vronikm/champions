<?php
/*
|--------------------------------------------------------------------------
| Configuracion del modulo DigiSports Core
|--------------------------------------------------------------------------
| Core es el modulo de administracion del ecosistema: usuarios, roles,
| permisos, menus y acceso a modulos. Se apoya en el mismo nucleo que los
| demas modulos, por lo que comparte credenciales y sesion.
*/

require_once __DIR__ . "/../config/app.php";

const APP_URL  = DS_HUB_URL . "ds_core/admin/";
const APP_NAME = "DigiSports - Core";

// Clave del modulo en seguridad_menu.menu_modulo y seguridad_rol_modulo.
const DS_MODULO = "core";

// Sesion unica del ecosistema.
const APP_SESSION_NAME = DS_SESSION_NAME;

/* Recursos visuales: Core reutiliza AdminLTE y SweetAlert2 del modulo
   Basketball en lugar de duplicar el vendor completo. */
const DS_VENDOR_URL = DS_FUTBOL_URL . "app/views/dist/";
