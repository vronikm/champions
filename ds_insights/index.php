<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — front controller
|--------------------------------------------------------------------------
| Aplica los mismos cuatro controles de acceso que el resto del ecosistema:
| sesion, modulo, vista existente y permiso de lectura.
|
| No hay nada nuevo aqui a proposito. El §8 del encargo pide bloquear el
| acceso directo por URL, devolver 403 y no revelar informacion interna: eso
| ya estaba resuelto en Arena y League, y replicarlo vale mas que inventar un
| mecanismo propio que habria que auditar aparte.
|
| La diferencia esta en config/app.php: DS_PERMISOS_ESTRICTOS = true. En este
| modulo, una vista no registrada en seguridad_menu se DENIEGA en vez de
| pasar. Toda vista de Insights es informacion gerencial.
*/

require_once __DIR__ . "/../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/../ds_core/autoload.php";
require_once __DIR__ . "/../ds_core/modulos.php";
require_once __DIR__ . "/../ds_core/inc/session_start.php";

$listaBlanca = require __DIR__ . "/config/vistas.php";

$url   = isset($_GET['views']) ? explode('/', $_GET['views']) : ['dashboard'];
$vista = $url[0] !== '' ? $url[0] : 'dashboard';

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
    $vista = 'dashboard';
}

/*----------  Nivel 2: permiso de lectura sobre la vista  ----------*/
if (!usuario_tiene_permiso($vista)) {
    http_response_code(403);
    require_once __DIR__ . "/views/accesoDenegado-view.php";
    exit();
}

require_once __DIR__ . "/views/" . $vista . "-view.php";
