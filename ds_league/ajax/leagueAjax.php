<?php
/*
|--------------------------------------------------------------------------
| Endpoint AJAX de DigiSports League
|--------------------------------------------------------------------------
| Mismo guard que el resto del ecosistema: sesión, origen (anti-CSRF) y
| acceso al módulo. El permiso por acción lo comprueba cada método del
| controlador, porque depende de la operación concreta.
|
| Este archivo NO decide permisos: sólo comprueba que la petición sea
| legítima y despacha. Si se resolviera aquí, habría dos sitios donde
| mirar cuando algo se deniega.
*/

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../../ds_core/autoload.php";
require_once __DIR__ . "/../../ds_core/modulos.php";
require_once __DIR__ . "/../../ds_core/inc/session_start.php";

use league\controllers\competenciaController;

function league_rechazar(int $codigo, array $alerta): void
{
    if (!headers_sent()) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($alerta, JSON_UNESCAPED_UNICODE);
    exit;
}

/*----------  1. Sesión activa  ----------*/
if (!usuario_autenticado()) {
    league_rechazar(401, [
        'tipo'   => 'redireccionar',
        'icono'  => 'warning',
        'titulo' => 'Sesión finalizada',
        'texto'  => 'Su sesión expiró. Vuelva a ingresar.',
        'url'    => DS_FUTBOL_URL . 'login/',
    ]);
}

/*----------  2. Origen de la petición (anti-CSRF)  ----------*/
if (!origen_es_valido()) {
    league_rechazar(403, [
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
    league_rechazar(403, [
        "tipo"   => "simple",
        "icono"  => "error",
        "titulo" => "Solicitud no verificada",
        "texto"  => "La solicitud llegó sin el testigo de seguridad de la página. "
                  . "Recargue la pantalla e inténtelo de nuevo.",
    ]);
}

/*----------  3. Acceso al módulo  ----------*/
if (!usuario_tiene_modulo(DS_MODULO)) {
    league_rechazar(403, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Acceso denegado',
        'texto'  => 'Su rol no tiene acceso al módulo League.',
    ]);
}

/*----------  Despacho  ----------*/
if (!isset($_POST['modulo_league'])) {
    league_rechazar(400, [
        'tipo'   => 'simple',
        'icono'  => 'error',
        'titulo' => 'Solicitud incompleta',
        'texto'  => 'No se indicó la operación a realizar.',
    ]);
}

$ins = new competenciaController();

switch ($_POST['modulo_league']) {

    case 'guardarTemporada':  echo $ins->guardarTemporada();  break;
    case 'guardarTorneo':     echo $ins->guardarTorneo();     break;
    case 'publicarTorneo':    echo $ins->publicarTorneo();    break;
    case 'guardarCategoria':  echo $ins->guardarCategoria();  break;
    case 'guardarEquipo':     echo $ins->guardarEquipo();     break;

    case 'inscribirEquipo':   echo $ins->inscribirEquipo();   break;
    case 'estadoInscripcion': echo $ins->cambiarEstadoInscripcion(); break;

    case 'generarFixture':    echo $ins->generarFixtureAjax(); break;
    case 'guardarResultado':  echo $ins->guardarResultado();   break;
    case 'programarPartido': echo $ins->programarPartido();  break;

    case 'guardarDesignacion':  echo $ins->guardarDesignacion();  break;
    case 'eliminarDesignacion': echo $ins->eliminarDesignacion(); break;

    case 'guardarPersona':     echo $ins->guardarPersona();     break;
    case 'habilitarPlantilla': echo $ins->habilitarPlantilla(); break;
    case 'consentimientoImagen': echo $ins->consentimientoImagen(); break;
    case 'bajaPlantilla':      echo $ins->bajaPlantilla();      break;

    case 'ejecutarSorteo':
        echo (new \league\controllers\sorteoController())->ejecutarSorteo();
        break;

    case 'guardarFase':      echo $ins->guardarFase();      break;

    case 'guardarActa':
        echo (new \league\controllers\estadisticaController())->guardarActa();
        break;

    case 'generarLlaves':
        echo (new \league\controllers\playoffController())->generarLlaves();
        break;

    /* Finanzas. Cada caso nombra su método de forma literal: despachar
       con $ins->{$_POST[...]}() sería más corto, pero convierte un campo
       del formulario en el nombre del método a ejecutar. */
    case 'guardarConcepto':
        echo (new \league\controllers\finanzaController())->guardarConcepto();
        break;

    case 'guardarObligacion':
        echo (new \league\controllers\finanzaController())->guardarObligacion();
        break;

    case 'guardarAbono':
        echo (new \league\controllers\finanzaController())->guardarAbono();
        break;

    case 'anularAbono':
        echo (new \league\controllers\finanzaController())->anularAbono();
        break;

    case 'anularObligacion':
        echo (new \league\controllers\finanzaController())->anularObligacion();
        break;

    case 'emitirComprobante':
        echo (new \league\controllers\comprobanteController())->emitir();
        break;

    default:
        league_rechazar(400, [
            'tipo'   => 'simple',
            'icono'  => 'error',
            'titulo' => 'Operación desconocida',
            'texto'  => 'La operación solicitada no existe en este módulo.',
        ]);
}
