<?php
/*
|--------------------------------------------------------------------------
| Puente hacia el nucleo del ecosistema
|--------------------------------------------------------------------------
| El arranque de sesion y su endurecimiento (HttpOnly, SameSite=Lax, Secure
| bajo HTTPS) son comunes a todo DigiSports y viven en ds_core/inc/. Este
| archivo se conserva porque decenas de rutas relativas del modulo ya lo
| incluyen; delega para no duplicar la logica.
|
| No agregar codigo aqui: el sitio correcto es ds_core/inc/session_start.php
*/

require_once __DIR__ . "/../../../../ds_core/inc/session_start.php";
