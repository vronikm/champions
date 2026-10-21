<?php
/*
|--------------------------------------------------------------------------
| Datos de la tabla de fotos de alumnos (DataTables serverSide)
|--------------------------------------------------------------------------
| Endpoint de SOLO LECTURA. Devuelve una página en el formato de DataTables:
| draw, recordsTotal, recordsFiltered y data.
|
| Va aparte de fotoAlumnoAjax.php, que recibe la subida de la imagen y
| responde alertas: son dos protocolos distintos y el guardia tendría que
| adivinar cuál aplicar si compartieran archivo.
|
| GET porque es una lectura y no cambia nada. El guardia exige el testigo
| CSRF sólo en POST —lo explica allí— y aquí se comprueba a mano el permiso
| de VER, porque la acción se deduce del campo POST modulo_* que un GET no
| trae.
|
| EL ALCANCE POR SEDE NO SE COMPRUEBA AQUI, Y ES A PROPOSITO
| Lo aplica listar() dentro del controlador, antes que ningún filtro que
| elija el usuario. Repetirlo aquí daría la falsa impresión de que es este
| archivo quien protege, y el día que alguien llame al controlador desde
| otro sitio se quedaría sin ello.
*/

require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
require_once "../../config/app.php";
require_once "../views/inc/session_start.php";
require_once "../../autoload.php";

$GUARD_VISTA = "alumnoFotos";
require_once "../views/inc/ajax_guard.php";

use app\controllers\fotoAlumnoController;

if (!puede('ver', $GUARD_VISTA)) {
    guard_rechazar(403, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Acceso denegado',
        'texto'  => 'Su rol no tiene permiso para consultar esta pantalla.',
    ]);
}

$ins = new fotoAlumnoController();

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode($ins->fotosServidor($_GET), JSON_UNESCAPED_UNICODE);
