<?php
/*
|--------------------------------------------------------------------------
| Puente hacia el nucleo del ecosistema
|--------------------------------------------------------------------------
| Sesion, permisos, validacion de origen (anti-CSRF) y URLs de medios son
| comunes a todo DigiSports y viven en ds_core/inc/. Este archivo se
| conserva porque el modulo ya lo incluye desde varias rutas relativas.
|
| No agregar codigo aqui: el sitio correcto es ds_core/inc/seguridad.php
*/

require_once __DIR__ . "/../../../../ds_core/inc/seguridad.php";
