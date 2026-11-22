<?php
/*
|--------------------------------------------------------------------------
| Configuracion del modulo DigiSports Insights
|--------------------------------------------------------------------------
| Business Intelligence del ecosistema: consolida, compara y visualiza lo que
| generan Basketball, Arena y League. No administra ningun proceso propio.
|
| Lo comun al ecosistema (credenciales, sesion, seguridad) viene de ds_core.
*/

require_once __DIR__ . "/../../ds_core/config/app.php";

/*----------  Identidad de este modulo  ----------*/
const APP_URL  = DS_INSIGHTS_URL;
const APP_NAME = "DigiSports - Insights";

// Clave del modulo en seguridad_menu.menu_modulo y seguridad_rol_modulo.
const DS_MODULO = "insights";

// Sesion unica del ecosistema.
const APP_SESSION_NAME = DS_SESSION_NAME;

/*
|--------------------------------------------------------------------------
| Permisos estrictos
|--------------------------------------------------------------------------
| Por omision, una vista que no esta registrada en seguridad_menu NO se
| restringe. Es una decision deliberada del ecosistema para las vistas de
| apoyo de Basketball —formularios, PDF, recibos— cuyo control efectivo esta
| en el listado desde el que se abren.
|
| Insights la rechaza. Aqui TODA vista es informacion gerencial: ingresos,
| cartera, rentabilidad. Olvidar registrar una no puede significar dejarla
| abierta a cualquiera con acceso al modulo. League ya lo hace por el mismo
| motivo.
*/
const DS_PERMISOS_ESTRICTOS = true;

/* Recursos visuales: el vendor comun de ds_core, que es donde viven
   AdminLTE 4, Bootstrap 5, DataTables 2, FontAwesome 6 y ApexCharts. */
const DS_VENDOR_URL = DS_FUTBOL_URL . "app/views/dist/";

/* ApexCharts, autoalojada. Se enlaza desde el aplicativo y no desde un CDN,
   igual que el resto del vendor del proyecto. Ver MODELO_INSIGHTS.md, R6. */
const DS_INSIGHTS_GRAFICOS_JS  = DS_VENDOR_CORE_URL . "apexcharts3/js/apexcharts.min.js";
const DS_INSIGHTS_GRAFICOS_CSS = DS_VENDOR_CORE_URL . "apexcharts3/css/apexcharts.css";

/*----------  Autocarga del modulo  ----------*/
/* El autoloader del nucleo resuelve las clases dentro de ds_core, asi que no
   encuentra las de este modulo. Se registra uno propio para el espacio de
   nombres insights\, anclado en la carpeta del modulo. Mismo patron que
   Arena y League. */
spl_autoload_register(function ($clase) {
    if (strpos($clase, 'insights\\') !== 0) {
        return;
    }

    $relativa = str_replace('\\', '/', substr($clase, strlen('insights\\')));
    $archivo  = __DIR__ . '/../' . $relativa . '.php';

    if (is_file($archivo)) {
        require_once $archivo;
    }
});

/*
|--------------------------------------------------------------------------
| Solo los módulos del rol
|--------------------------------------------------------------------------
| Insights consolida Fútbol, Arena y League, pero cada usuario ve SOLO lo
| de los módulos que su rol tiene concedidos (seguridad_rol_modulo): un
| Administrador sin Arena no ve cuánto cobra ni cuánto le deben a Arena, ni
| en el panel, ni en Financiero, ni en Cartera, ni en una exportación.
|
| Se resuelve aquí, en un solo sitio, y lo aplican las consultas del
| controlador (no calculan lo que no se puede ver), las vistas, el menú,
| el catálogo de reportes, las exportaciones y el front controller.
| El Super Administrador los tiene todos (usuario_tiene_modulo).
*/

/** Los módulos con datos en Insights que el rol puede ver, con su nombre. */
function insights_modulos(): array
{
    static $visibles = null;
    if ($visibles === null) {
        $visibles = [];
        foreach (['basketball' => 'Fútbol', 'arena' => 'Arena', 'league' => 'League'] as $m => $n) {
            if (PHP_SAPI === 'cli' || usuario_tiene_modulo($m)) { $visibles[$m] = $n; }
        }
    }
    return $visibles;
}

/** ¿El rol ve los datos de ese módulo? */
function insights_ve(string $modulo): bool
{
    return isset(insights_modulos()[$modulo]);
}

/** El módulo del que depende cada vista o reporte de Insights (las demás son transversales). */
const INSIGHTS_VISTA_MODULO = [
    'basketball' => 'basketball',
    'becas'      => 'basketball',
    'arena'      => 'arena',
    'league'     => 'league',
];

/** ¿La vista es de un módulo que el rol ve? (El permiso de la vista se comprueba aparte.) */
function insights_vista_visible(string $vista): bool
{
    $m = INSIGHTS_VISTA_MODULO[$vista] ?? null;
    return $m === null || insights_ve($m);
}
