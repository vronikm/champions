<?php
/*
|--------------------------------------------------------------------------
| Endpoint AJAX de DigiSports Arena
|--------------------------------------------------------------------------
| Mismo guard que el resto del ecosistema: sesion, origen (anti-CSRF) y
| acceso al modulo. El permiso por accion lo comprueba cada metodo del
| controlador, porque depende de la operacion concreta.
*/

require_once __DIR__ . "/../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../../ds_core/autoload.php";
require_once __DIR__ . "/../../ds_core/modulos.php";
require_once __DIR__ . "/../../ds_core/inc/session_start.php";

use arena\controllers\arenaController;

function arena_rechazar(int $codigo, array $alerta): void
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
    arena_rechazar(401, [
        'tipo'   => 'redireccionar',
        'icono'  => 'warning',
        'titulo' => 'Sesión finalizada',
        'texto'  => 'Su sesión expiró. Vuelva a ingresar.',
        'url'    => DS_FUTBOL_URL . 'login/',
    ]);
}

/*----------  2. Origen de la peticion (anti-CSRF)  ----------*/
if (!origen_es_valido()) {
    arena_rechazar(403, [
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
    arena_rechazar(403, [
        "tipo"   => "simple",
        "icono"  => "error",
        "titulo" => "Solicitud no verificada",
        "texto"  => "La solicitud llegó sin el testigo de seguridad de la página. "
                  . "Recargue la pantalla e inténtelo de nuevo.",
    ]);
}

/*----------  3. Acceso al modulo  ----------*/
if (!usuario_tiene_modulo(DS_MODULO)) {
    arena_rechazar(403, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Acceso denegado',
        'texto'  => 'Su rol no tiene acceso al módulo Arena.',
    ]);
}

/*----------  Despacho  ----------*/
if (!isset($_POST['modulo_arena'])) {
    arena_rechazar(400, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Solicitud incompleta',
        'texto'  => 'No se indicó la operación a realizar.',
    ]);
}

$insArena = new arenaController();

switch ($_POST['modulo_arena']) {

    case 'guardarInstalacion':  echo $insArena->guardarInstalacion();  break;
    case 'eliminarInstalacion': echo $insArena->eliminarInstalacion(); break;
    case 'sugerirCodigo':       echo $insArena->sugerirCodigo();       break;

    case 'guardarHorario':      echo $insArena->guardarHorario();      break;
    case 'eliminarHorario':     echo $insArena->eliminarHorario();     break;

    case 'guardarBloqueo':      echo $insArena->guardarBloqueo();      break;
    case 'eliminarBloqueo':     echo $insArena->eliminarBloqueo();     break;

    case 'guardarCliente':      echo $insArena->guardarCliente();      break;
    case 'eliminarCliente':     echo $insArena->eliminarCliente();     break;

    case 'consultarDisponibilidad': echo $insArena->consultarDisponibilidad(); break;
    case 'enviarReciboCorreo':    echo $insArena->enviarReciboCorreo();    break;
    case 'guardarReserva':        echo $insArena->guardarReserva();        break;
    case 'cambiarEstadoReserva':  echo $insArena->cambiarEstadoReserva();  break;

    case 'registrarPago':         echo $insArena->registrarPago();         break;
    case 'ingresoMonedero':       echo $insArena->ingresoMonedero();       break;
    case 'egresoMonedero':        echo $insArena->egresoMonedero();        break;

    default:
        arena_rechazar(400, [
            'tipo'   => 'simple',
            'icono'  => 'error',
            'titulo' => 'Operación desconocida',
            'texto'  => 'La operación solicitada no existe en este módulo.',
        ]);
}
