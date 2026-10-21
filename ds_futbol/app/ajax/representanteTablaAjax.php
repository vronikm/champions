<?php
/*
|--------------------------------------------------------------------------
| Datos de la tabla de representantes (DataTables serverSide)
|--------------------------------------------------------------------------
| Endpoint de SOLO LECTURA. Devuelve una página de representantes en el
| formato que espera DataTables: draw, recordsTotal, recordsFiltered y data.
|
| POR QUE ESTA APARTE DE representanteAjax.php
|
| Aquel despacha operaciones de escritura por el campo POST modulo_repre y
| responde alertas para alertas_ajax(). Este responde una tabla. Mezclar las
| dos cosas obligaría a que un mismo archivo hablara dos protocolos, y el
| guardia tendría que adivinar cuál aplicar.
|
| POR QUE GET
|
| Es una lectura: no cambia nada en el servidor. El guardia exige el testigo
| CSRF sólo en POST y lo explica allí mismo —pedirlo en las lecturas de las
| tablas no aportaría nada y las rompería—. Lo que sí se exige aquí es
| sesión, origen, módulo y permiso de VER sobre la vista.
|
| EL PERMISO DE LECTURA SE COMPRUEBA A MANO
|
| El guardia deduce la acción del campo POST modulo_*, que en un GET no
| existe: sin esta comprobación explícita, el endpoint quedaría abierto a
| cualquier sesión con acceso al módulo. Se pide ver sobre representanteList,
| que es la pantalla a la que pertenecen estos datos.
*/

require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
require_once "../../config/app.php";
require_once "../views/inc/session_start.php";
require_once "../../autoload.php";

$GUARD_VISTA = "representanteList";
require_once "../views/inc/ajax_guard.php";

use app\controllers\representanteController;

if (!puede('ver', $GUARD_VISTA)) {
    guard_rechazar(403, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Acceso denegado',
        'texto'  => 'Su rol no tiene permiso para consultar esta pantalla.',
    ]);
}

$ins = new representanteController();

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode($ins->representantesServidor($_GET), JSON_UNESCAPED_UNICODE);
