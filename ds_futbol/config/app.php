<?php
/*
|--------------------------------------------------------------------------
| Configuracion del modulo DigiSports Basketball
|--------------------------------------------------------------------------
| Lo comun al ecosistema (URLs de modulos, credenciales, zona horaria,
| nombre de la sesion) viene de ds_core. Aqui solo va la identidad propia
| de este modulo.
*/

/*----------  Nucleo del ecosistema  ----------*/
require_once __DIR__ . "/../../ds_core/config/app.php";

/*----------  Identidad de este modulo  ----------*/
const APP_URL  = DS_FUTBOL_URL;
const APP_NAME = "DigiSports - Fútbol";

// Clave del modulo en seguridad_menu.menu_modulo y seguridad_rol_modulo.
// La capa de seguridad la usa para resolver menus y permisos del contexto.
const DS_MODULO = "basketball";

// La sesion es unica en todo DigiSports: se reutiliza la del nucleo para
// que iniciar sesion en el Hub valga tambien aqui.
const APP_SESSION_NAME = DS_SESSION_NAME;

/*----------  Enlace de inscripción  ----------*/
// URL del formulario público al que apuntan los enlaces generados.
// Se deriva de la raíz del ecosistema porque el formulario vive DENTRO del
// proyecto (barcelona/ds_form). Antes apuntaba a "barcelona_form/", una
// ruta que no existe: todos los enlaces emitidos daban 404.
const FORM_URL = DS_HUB_URL . "ds_form/";

// Vigencia por defecto del enlace: 72 horas
const TOKEN_EXPIRY = 259200;
