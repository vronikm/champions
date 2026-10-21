<?php
/*
|--------------------------------------------------------------------------
| DigiSports League — front controller
|--------------------------------------------------------------------------
| Aplica los mismos tres niveles de control de acceso que el resto del
| ecosistema: sesión, módulo y permiso de lectura sobre la vista.
|
| La diferencia con Basketball y Arena está en el tercer nivel: este módulo
| declara DS_PERMISOS_ESTRICTOS, de modo que una vista no registrada en
| seguridad_menu se deniega en lugar de permitirse. Ver config/app.php.
*/

require_once __DIR__ . "/../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/../ds_core/autoload.php";
require_once __DIR__ . "/../ds_core/modulos.php";
require_once __DIR__ . "/../ds_core/inc/session_start.php";

$listaBlanca = require __DIR__ . "/config/vistas.php";

$url   = isset($_GET['views']) ? explode('/', $_GET['views']) : ['panel'];
$vista = $url[0] !== '' ? $url[0] : 'panel';

/*----------  Nivel 0: sesión activa  ----------*/
if (!usuario_autenticado()) {
    header("Location: " . DS_FUTBOL_URL . "login/");
    exit();
}

/*----------  Nivel 1: acceso al módulo  ----------*/
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
