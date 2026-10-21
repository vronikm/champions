<?php
/*
|--------------------------------------------------------------------------
| DigiSports Core — front controller
|--------------------------------------------------------------------------
| Administracion del ecosistema: usuarios, roles, permisos, menus y acceso
| a modulos. Aplica los tres niveles de control de acceso del nucleo.
*/

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/../autoload.php";
require_once __DIR__ . "/../modulos.php";
require_once __DIR__ . "/../vistas.php";
require_once __DIR__ . "/../inc/session_start.php";

/* Vistas navegables del modulo. Todo lo que no este aqui es 404. */
$listaBlanca = require __DIR__ . "/config/vistas.php";

$url   = isset($_GET['views']) ? explode('/', $_GET['views']) : ['panel'];
$vista = $url[0] !== '' ? $url[0] : 'panel';

/*----------  Nivel 0: sesion activa  ----------*/
if (!usuario_autenticado()) {
    header("Location: " . DS_FUTBOL_URL . "login/");
    exit();
}

/*----------  Nivel 1: acceso al modulo  ----------*/
if (!usuario_tiene_modulo(DS_MODULO)) {
    http_response_code(403);
    require_once __DIR__ . "/views/accesoDenegado-view.php";
    exit();
}

/*----------  Vista existente  ----------*/
if (!in_array($vista, $listaBlanca, true)
    || !is_file(__DIR__ . "/views/" . $vista . "-view.php")) {
    http_response_code(404);
    $vista = 'panel';
}

/*----------  Nivel 2: permiso de lectura sobre la vista  ----------*/
if (!usuario_tiene_permiso($vista)) {
    http_response_code(403);
    require_once __DIR__ . "/views/accesoDenegado-view.php";
    exit();
}

require_once __DIR__ . "/views/" . $vista . "-view.php";
