<?php
/*
|--------------------------------------------------------------------------
| Configuración del módulo DigiCheck
|--------------------------------------------------------------------------
| CONTROL INTELIGENTE DE ASISTENCIA · «Porque cada entrenamiento cuenta».
|
| La asistencia con el QR del carnet del alumno:
|   escanear   la pantalla de la cancha: lee el QR con la cámara, reconoce
|              al alumno, guarda la marca en el dispositivo y la confirma
|              con color y sonido. Funciona SIN Internet: la lista se
|              descarga antes y las marcas se envían al volver la señal.
|   carnets    los carnets con su QR, para imprimir o mostrar en el
|              celular.
|
| No tiene tablas propias. La lista sale de los horarios y alumnos de
| Fútbol, y las marcas se envían por el mismo camino que la asistencia sin
| conexión de Fútbol (asistenciaOfflineAjax.php → registrarMarcasLote):
| las mismas reglas de plazo, permisos y conflictos, sin repetirlas aquí.
|
| Lo común al ecosistema (credenciales, sesión, seguridad) viene de ds_core.
*/

require_once __DIR__ . "/../../ds_core/config/app.php";
require_once __DIR__ . "/../../ds_core/inc/digicheck.php";

/*----------  Identidad de este módulo  ----------*/
const APP_URL  = DS_CHECK_URL;
const APP_NAME = "DigiSports - DigiCheck";

// Clave del módulo en seguridad_menu.menu_modulo y seguridad_rol_modulo.
const DS_MODULO = "check";

// Sesión única del ecosistema.
const APP_SESSION_NAME = DS_SESSION_NAME;

/* Una vista no registrada en seguridad_menu se DENIEGA: el módulo lee
   datos de alumnos (nombre, cédula) y emite su credencial. */
const DS_PERMISOS_ESTRICTOS = true;

/* Recursos visuales: el vendor común (AdminLTE 4, Bootstrap 5, FontAwesome 6). */
const DS_VENDOR_URL = DS_FUTBOL_URL . "app/views/dist/";

/* Acento: el naranja de --ds-check (#fb923c) es claro para texto blanco
   (2.3:1). Éste, de la misma familia, da 5.2:1. */
const DS_CHECK_ACENTO = '#c2410c';

/*----------  Autocarga del módulo  ----------*/
spl_autoload_register(function ($clase) {
    if (strpos($clase, 'check\\') !== 0) {
        return;
    }
    $archivo = __DIR__ . '/../' . str_replace('\\', '/', substr($clase, strlen('check\\'))) . '.php';
    if (is_file($archivo)) {
        require_once $archivo;
    }
});
