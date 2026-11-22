<?php
/*
|--------------------------------------------------------------------------
| DigiCheck — la lista para trabajar sin conexión
|--------------------------------------------------------------------------
| GET → JSON con los horarios del usuario y sus alumnos, cada uno con el
| código de su QR (checkController::lista). La pantalla de escaneo la
| guarda en el dispositivo (IndexedDB) y trabaja con ella en la cancha.
|
| Sólo lectura y sólo GET. Mismos controles que las vistas: sesión,
| módulo y permiso sobre «escanear». Sin sesión responde 401 en JSON (no
| una redirección al login, que el fetch recibiría como HTML sin sentido).
*/

require_once __DIR__ . "/../../ds_core/inc/errores.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../../ds_core/autoload.php";
require_once __DIR__ . "/../../ds_core/inc/session_start.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$responder = static function (int $codigo, array $datos): void {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    $responder(405, ['ok' => false, 'texto' => 'Sólo lectura.']);
}
if (!usuario_autenticado()) {
    $responder(401, ['ok' => false, 'sinSesion' => true, 'texto' => 'Su sesión expiró. Vuelva a ingresar.']);
}
if (!usuario_tiene_modulo(DS_MODULO) || !usuario_tiene_permiso('escanear')) {
    $responder(403, ['ok' => false, 'texto' => 'No tiene permiso para escanear asistencia.']);
}

try {
    $responder(200, ['ok' => true] + (new \check\controllers\checkController())->lista());
} catch (\Throwable $e) {
    $responder(500, ['ok' => false, 'texto' => 'No se pudo preparar la lista. Intente de nuevo.']);
}
