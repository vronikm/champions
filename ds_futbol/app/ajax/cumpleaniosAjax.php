<?php
/*
| Cumpleaños: publicar la felicitación en redes sociales (migración 073).
| La lógica y sus reglas (sede, autorización de imagen LOPDP, sin repetir)
| están en cumpleaniosController::publicarCumple().
*/
require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
require_once "../../config/app.php";
require_once "../views/inc/session_start.php";
require_once "../../autoload.php";

/* Guardia: sesión + origen + CSRF + permiso. «crear_…» exige el permiso de
   crear sobre Cumpleaños: publicar en las redes de la escuela es un acto,
   no una consulta. */
$GUARD_VISTA = "cumpleaniosList";
require_once "../views/inc/ajax_guard.php";

use app\controllers\cumpleaniosController;

header('Content-Type: application/json; charset=utf-8');

switch ($_POST['modulo_cumple'] ?? '') {
    case 'crear_publicacion_cumple':
        echo (new cumpleaniosController())->publicarCumple();
        break;
    default:
        http_response_code(400);
        echo json_encode(['tipo' => 'simple', 'titulo' => 'Operación no válida', 'texto' => 'No se reconoce la operación.', 'icono' => 'error'], JSON_UNESCAPED_UNICODE);
}
