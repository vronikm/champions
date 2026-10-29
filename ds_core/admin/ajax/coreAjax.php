<?php
/*
|--------------------------------------------------------------------------
| Endpoint AJAX de DigiSports Core
|--------------------------------------------------------------------------
| Aplica el mismo guard que el resto del ecosistema: sesion activa, origen
| valido (anti-CSRF) y acceso al modulo. El permiso por accion lo verifica
| cada metodo del controlador, porque depende de la operacion concreta.
*/

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../../autoload.php";
require_once __DIR__ . "/../../modulos.php";
require_once __DIR__ . "/../../vistas.php";
require_once __DIR__ . "/../../inc/session_start.php";

use admin\controllers\coreController;

/*----------  Respuesta uniforme de rechazo  ----------*/
function core_rechazar(int $codigo, array $alerta): void
{
    if (!headers_sent()) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($alerta, JSON_UNESCAPED_UNICODE);
    exit;
}

/*----------  1. Sesion activa  ----------*/
if (!usuario_autenticado()) {
    core_rechazar(401, [
        'tipo'   => 'redireccionar',
        'icono'  => 'warning',
        'titulo' => 'Sesión finalizada',
        'texto'  => 'Su sesión expiró. Vuelva a ingresar.',
        'url'    => DS_FUTBOL_URL . 'login/',
    ]);
}

/*----------  2. Origen de la peticion (anti-CSRF)  ----------*/
if (!origen_es_valido()) {
    core_rechazar(403, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Solicitud bloqueada',
        'texto'  => 'La solicitud no proviene del sistema. Recargue la página e inténtelo de nuevo.',
    ]);
}

/*----------  2b. Testigo CSRF  ----------*/
/*
| La explicacion larga esta en ds_futbol/app/views/inc/ajax_guard.php.
| En corto: la cabecera Origin la pone el navegador y hay clientes y proxies
| que la recortan; el testigo no depende de eso, porque vive en la sesion y
| en el HTML de la pagina, y otro dominio no puede leerlo.
|
| Solo se exige en POST: GET no cambia nada en el servidor.
|
| Lo pone ds_core/assets/js/csrf.js, cargado desde la cabecera de cada
| pantalla, asi que ningun formulario tiene que acordarse de nada.
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && !csrf_valido()) {
    core_rechazar(403, [
        "tipo"   => "simple",
        "icono"  => "error",
        "titulo" => "Solicitud no verificada",
        "texto"  => "La solicitud llegó sin el testigo de seguridad de la página. "
                  . "Recargue la pantalla e inténtelo de nuevo.",
    ]);
}

/*----------  3. Acceso al modulo  ----------*/
if (!usuario_tiene_modulo(DS_MODULO)) {
    core_rechazar(403, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Acceso denegado',
        'texto'  => 'Su rol no tiene acceso a la administración del ecosistema.',
    ]);
}

/*----------  Despacho  ----------*/
if (!isset($_POST['modulo_core'])) {
    core_rechazar(400, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Solicitud incompleta',
        'texto'  => 'No se indicó la operación a realizar.',
    ]);
}

$insCore = new coreController();

/* Cada metodo comprueba por su cuenta la accion que necesita. */
switch ($_POST['modulo_core']) {

    case 'guardarRol':       echo $insCore->guardarRol();         break;
    case 'eliminarRol':      echo $insCore->eliminarRol();        break;
    case 'guardarModulos':   echo $insCore->guardarModulosRol();  break;
    case 'guardarPermisos':  echo $insCore->guardarPermisos();    break;
    case 'guardarUsuario':   echo $insCore->guardarUsuario();     break;
    case 'eliminarUsuario':  echo $insCore->eliminarUsuario();    break;
    /* Nueva contraseña para OTRO usuario y su envío por correo o WhatsApp. */
    case 'restablecerClave': echo $insCore->restablecerClave();   break;
    case 'guardarMenu':      echo $insCore->guardarMenu();        break;
    case 'eliminarMenu':     echo $insCore->eliminarMenu();       break;

    case 'guardarOrganizacion': echo $insCore->guardarOrganizacion(); break;

    case 'guardarSede':      echo $insCore->guardarSede();        break;
    case 'eliminarSede':     echo $insCore->eliminarSede();       break;
    case 'estadoSede':       echo $insCore->estadoSede();         break;

    case 'guardarValorCatalogo':  echo $insCore->guardarValorCatalogo();  break;

    /* La numeracion de comprobantes es del contribuyente, no de un
       modulo: el metodo comprueba por su cuenta que quien llama sea el
       superadministrador. */
    case 'guardarPuntoEmision': echo $insCore->guardarPuntoEmision(); break;
    case 'eliminarValorCatalogo': echo $insCore->eliminarValorCatalogo(); break;

    /* Lo unico del segundo factor que es administrativo: quitarselo a
       OTRA persona cuando pierde el telefono. El autoservicio vive en el
       Hub, porque solo el rol 1 tiene concedido este modulo y los demas
       usuarios tambien tienen que poder proteger su cuenta. */
    case 'restablecerSegundoFactor': echo $insCore->restablecerSegundoFactor(); break;
    /* Tarjeta de cumpleaños. Una plantilla por marco y sólo una activa; el
       generador de Basketball lee siempre la activa. */
    case 'guardarRedes':     echo $insCore->guardarRedes();       break;
    case 'probarRedes':      echo $insCore->probarRedes();        break;
    case 'guardarPlantillaCumple':  echo $insCore->guardarPlantillaCumple();  break;
    case 'nuevaPlantillaCumple':    echo $insCore->nuevaPlantillaCumple();    break;
    case 'activarPlantillaCumple':  echo $insCore->activarPlantillaCumple();  break;
    case 'eliminarPlantillaCumple': echo $insCore->eliminarPlantillaCumple(); break;
    case 'guardarTextoConsentimiento': echo $insCore->guardarTextoConsentimiento(); break;

    default:
        core_rechazar(400, [
            'tipo'   => 'simple',
            'icono'  => 'error',
            'titulo' => 'Operación desconocida',
            'texto'  => 'La operación solicitada no existe en este módulo.',
        ]);
}
