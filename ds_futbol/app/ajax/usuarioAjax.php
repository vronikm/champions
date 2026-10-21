<?php
/*
|--------------------------------------------------------------------------
| Autoservicio de la cuenta del usuario
|--------------------------------------------------------------------------
| La administración de usuarios y roles vive en DigiSports Core; este
| módulo sólo conserva lo que un usuario hace sobre SU PROPIA cuenta desde
| la barra superior: cambiar la contraseña.
|
| Las demás operaciones (alta de usuarios, roles, permisos) se retiraron a
| propósito: sus vistas ya no existen aquí y el endpoint habría quedado sin
| control real, porque una vista que no figura en seguridad_menu no se
| restringe. Ver ds_core/admin/ para su equivalente.
*/

require_once "../../config/app.php";
require_once "../views/inc/session_start.php";
require_once "../../autoload.php";

/* Sin $GUARD_VISTA: CAMBIAR_CLAVE es autoservicio y no depende de ningún
   permiso de pantalla. El guardia aplica igual sesión, origen y módulo. */
require_once "../views/inc/ajax_guard.php";

use app\controllers\userController;

if (isset($_POST['modulo_usuario']) && $_POST['modulo_usuario'] === 'CAMBIAR_CLAVE') {

    $insUsuario = new userController();
    echo $insUsuario->actualizarClaveUsuarioControlador();

} else {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Operación no disponible',
        'texto'  => 'La administración de usuarios y roles se realiza desde DigiSports Core.',
    ], JSON_UNESCAPED_UNICODE);
}
