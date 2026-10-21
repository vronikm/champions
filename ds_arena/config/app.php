<?php
/*
|--------------------------------------------------------------------------
| Configuracion del modulo DigiSports Arena
|--------------------------------------------------------------------------
| Gestion de instalaciones deportivas y residencias: disponibilidad,
| reservas por hora, abonos y monedero del cliente.
|
| Lo comun al ecosistema (credenciales, sesion, seguridad) viene de ds_core.
*/

require_once __DIR__ . "/../../ds_core/config/app.php";

/*----------  Identidad de este modulo  ----------*/
const APP_URL  = DS_ARENA_URL;
const APP_NAME = "DigiSports - Arena";

// Clave del modulo en seguridad_menu.menu_modulo y seguridad_rol_modulo.
const DS_MODULO = "arena";

// Sesion unica del ecosistema.
const APP_SESSION_NAME = DS_SESSION_NAME;

/* Recursos visuales: Arena reutiliza AdminLTE y SweetAlert2 del modulo
   Basketball en lugar de duplicar el vendor completo. */
const DS_VENDOR_URL = DS_FUTBOL_URL . "app/views/dist/";

/*----------  Reglas de negocio  ----------*/
// Codigos del catalogo general sede_tipoingreso cuyas sedes ofrecen
// alquiler; son las unicas que Arena administra.
const ARENA_SEDES_ALQUILER = ['STA', 'STM'];

/*----------  Autocarga del modulo  ----------*/
// El autoloader del nucleo resuelve las clases dentro de ds_core, asi que
// no encuentra las de este modulo. Se registra uno propio para el espacio
// de nombres arena\, anclado en la carpeta del modulo.
spl_autoload_register(function ($clase) {
    if (strpos($clase, 'arena\\') !== 0) {
        return;
    }

    $relativa = str_replace('\\', '/', substr($clase, strlen('arena\\')));
    $archivo  = __DIR__ . '/../' . $relativa . '.php';

    if (is_file($archivo)) {
        require_once $archivo;
    }
});
