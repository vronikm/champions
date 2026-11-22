<?php
/*
|--------------------------------------------------------------------------
| DigiCheck — front controller
|--------------------------------------------------------------------------
| Los mismos cuatro controles que el resto del ecosistema: sesión, módulo,
| vista existente y permiso de lectura (ver ds_insights/index.php).
|
| Una diferencia: «escanear» se abre también SIN conexión, desde la copia
| que guarda el service worker (sw-check.js). Eso no pasa por aquí: lo
| sirve el navegador. Lo que llega aquí es siempre con sesión.
*/

require_once __DIR__ . "/../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/../ds_core/autoload.php";
require_once __DIR__ . "/../ds_core/modulos.php";
require_once __DIR__ . "/../ds_core/inc/session_start.php";

$listaBlanca = require __DIR__ . "/config/vistas.php";

$url   = isset($_GET['views']) ? explode('/', $_GET['views']) : ['escanear'];
$vista = $url[0] !== '' ? $url[0] : 'escanear';

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
    $vista = 'escanear';
}

/*----------  Nivel 2: permiso de lectura sobre la vista  ----------*/
if (!usuario_tiene_permiso($vista)) {
    /* Quien sólo tiene una de las dos (p. ej. carnets sin escanear) va a
       la que sí puede abrir, en vez de un «acceso denegado» en la portada. */
    foreach ($listaBlanca as $otra) {
        if ($otra !== $vista && usuario_tiene_permiso($otra) && ($url[0] ?? '') === '') {
            header("Location: " . APP_URL . $otra . "/");
            exit();
        }
    }
    http_response_code(403);
    require_once __DIR__ . "/views/accesoDenegado-view.php";
    exit();
}

require_once __DIR__ . "/views/" . $vista . "-view.php";
