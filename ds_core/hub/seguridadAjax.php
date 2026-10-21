<?php
/*
|--------------------------------------------------------------------------
| Operaciones del Hub sobre la seguridad de la propia cuenta
|--------------------------------------------------------------------------
| NO SE PIDE POR URL. Se incluye desde el index.php de la raíz, que es la
| única cara pública del Hub: el .htaccess bloquea ds_core/ entero salvo
| assets/ y admin/, precisamente porque el núcleo es código de servidor.
| Por eso aquí no se carga configuración ni sesión: ya lo hizo quien
| incluye este archivo.
|
| EL GUARD ES MÁS CORTO QUE EL DE LOS MÓDULOS, Y ES DELIBERADO
|
| Sólo exige sesión activa y origen válido. NO comprueba acceso a ningún
| módulo, porque proteger la propia cuenta no depende de a qué módulos se
| entre. Ese fue el error de la primera versión, que colgaba esto de Core
| y dejaba a cuatro de los cinco usuarios sin poder activar su segundo
| factor.
|
| NINGUNA operación acepta un id de usuario: todas actúan sobre quien
| tiene la sesión abierta. Por eso no hace falta comprobar permisos sobre
| terceros — no hay terceros.
*/

use hub\hubController;

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

/*----------  1. Origen de la petición (anti-CSRF)  ----------*/
/* La sesión ya la comprobó index.php antes de llegar aquí. */
if (!origen_es_valido()) {
    http_response_code(403);
    echo json_encode([
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Solicitud bloqueada',
        'texto'  => 'La solicitud no proviene del sistema. Recargue la página.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/*----------  1b. Testigo CSRF  ----------*/
/*
| Aquí importa más que en ningún otro sitio: estas operaciones cambian la
| contraseña y el segundo factor de quien tiene la sesión abierta. Una
| petición forjada que colara un cambio de clave entregaría la cuenta entera.
|
| La explicación larga está en ds_futbol/app/views/inc/ajax_guard.php.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valido()) {
    http_response_code(403);
    echo json_encode([
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Solicitud no verificada',
        'texto'  => 'La solicitud llegó sin el testigo de seguridad de la página. '
                  . 'Recargue la pantalla e inténtelo de nuevo.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_POST['modulo_hub'])) {
    http_response_code(400);
    echo json_encode([
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Solicitud incompleta',
        'texto'  => 'No se indicó la operación a realizar.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$insHub = new hubController();

switch ($_POST['modulo_hub']) {

    case 'prepararSegundoFactor':
        echo $insHub->prepararSegundoFactor();
        break;

    case 'activarSegundoFactor':
        echo $insHub->activarSegundoFactor();
        break;

    case 'desactivarSegundoFactor':
        echo $insHub->desactivarSegundoFactor();
        break;

    case 'regenerarCodigos':
        echo $insHub->regenerarCodigosRecuperacion();
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'tipo'   => 'simple',
            'icono'  => 'error',
            'titulo' => 'Operación desconocida',
            'texto'  => 'La operación solicitada no existe.',
        ], JSON_UNESCAPED_UNICODE);
}

exit;
