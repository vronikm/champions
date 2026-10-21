<?php
/*
|--------------------------------------------------------------------------
| DigiSports — Hub de aplicaciones
|--------------------------------------------------------------------------
| Puerta de entrada al ecosistema. Presenta los modulos como un lanzador de
| aplicaciones y delega en cada ds_* la gestion de su dominio.
|
| La sesion es unica en todo DigiSports (mismo nombre de cookie y path "/"),
| asi que iniciar sesion en cualquier modulo vale tambien aqui.
*/

/*----------  Nucleo del ecosistema  ----------*/
require_once __DIR__ . "/ds_core/config/app.php";

/*----------  Identidad de este contexto  ----------*/
/* La capa de seguridad compartida trabaja con APP_URL / APP_NAME; en el Hub
   esas constantes apuntan a la raiz del ecosistema. */
const APP_URL          = DS_HUB_URL;
const APP_NAME         = DS_HUB_NAME;
const APP_SESSION_NAME = DS_SESSION_NAME;

require_once __DIR__ . "/ds_core/autoload.php";
require_once __DIR__ . "/ds_core/modulos.php";
require_once __DIR__ . "/ds_core/inc/session_start.php";

/*----------  Lo que el Hub sirve  ----------*/
/* LISTA BLANCA, NO CONCATENACION. Sin ella, ?p=../algo convertiria este
   parametro en un lector de archivos del servidor.

   Todo pasa por aqui porque el .htaccess de la raiz bloquea ds_core/
   entero salvo assets/ y admin/: el nucleo es codigo de servidor y no se
   pide por URL. Este index.php es la unica cara publica del Hub. */
$paginas = [
    'hub'           => 'hub-view.php',
    /* Seguridad de la propia cuenta. Vive aqui y no en Core porque solo
       el rol 1 tiene concedido aquel modulo, y proteger la cuenta de uno
       tiene que poder hacerlo cualquiera que entre al sistema. */
    'seguridad'     => 'seguridad-view.php',
    'seguridadAjax' => 'seguridadAjax.php',
];

$pagina = (string)($_GET['p'] ?? 'hub');
if (!isset($paginas[$pagina])) { $pagina = 'hub'; }

$esAjax = $pagina === 'seguridadAjax';

/*----------  Solo usuarios autenticados  ----------*/
/* El inicio de sesion vive todavia en el modulo Basketball; al ser la
   sesion comun, basta con enviar alli a quien no la tenga.

   A una peticion AJAX se le responde en JSON: una redireccion a HTML la
   recibiria el fetch() como texto sin sentido y el usuario veria un error
   de conexion en lugar de «su sesion expiro». */
if (!usuario_autenticado()) {
    if ($esAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'tipo'   => 'redireccionar',
            'icono'  => 'warning',
            'titulo' => 'Sesión finalizada',
            'texto'  => 'Su sesión expiró. Vuelva a ingresar.',
            'url'    => DS_FUTBOL_URL . 'login/',
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    header("Location: " . DS_FUTBOL_URL . "login/");
    exit();
}

require_once __DIR__ . "/ds_core/hub/" . $paginas[$pagina];
