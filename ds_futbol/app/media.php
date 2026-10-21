<?php
/*
|--------------------------------------------------------------------------
| Proxy de medios con datos personales
|--------------------------------------------------------------------------
| Sirve fotos de alumnos (menores de edad), cedulas escaneadas, fotos de
| empleados y comprobantes de pago SOLO a usuarios con sesion activa.
|
| Antes estas imagenes eran accesibles por URL directa sin autenticacion y,
| como el nombre del archivo deriva de la cedula, bastaba conocerla para
| descargar la foto de un menor. Las carpetas quedan bloqueadas por
| .htaccess y este es el unico camino de lectura.
|
| Uso:  app/media.php?t=<tipo>&f=<archivo>
| El catalogo de tipos vive en medios_catalogo(), en views/inc/seguridad.php.
*/

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/views/inc/session_start.php";

/* Nunca cachear en proxies compartidos: son datos personales. */
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

/*----------  1. Sesion activa  ----------*/
if (!usuario_autenticado()) {
    http_response_code(403);
    exit;
}

/*----------  2. Tipo dentro del catalogo  ----------*/
$catalogo = medios_catalogo();
$tipo     = (string)($_GET['t'] ?? '');

if (!isset($catalogo[$tipo])) {
    http_response_code(404);
    exit;
}

/*----------  3. Nombre de archivo  ----------*/
/* basename() descarta cualquier intento de recorrido de directorios. */
$archivo = basename((string)($_GET['f'] ?? ''));

if ($archivo === '' || $archivo[0] === '.') {
    http_response_code(404);
    exit;
}

$mime = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

$ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

if (!isset($mime[$ext])) {
    http_response_code(404);
    exit;
}

/*----------  4. Confinar la ruta dentro de la carpeta permitida  ----------*/
$base = realpath(__DIR__ . "/views/" . $catalogo[$tipo]);
$ruta = ($base === false) ? false : realpath($base . DIRECTORY_SEPARATOR . $archivo);

if ($base === false || $ruta === false
    || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0
    || !is_file($ruta)) {
    http_response_code(404);
    exit;
}

/*----------  5. Entregar  ----------*/
header('Content-Type: ' . $mime[$ext]);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . $archivo . '"');

readfile($ruta);
