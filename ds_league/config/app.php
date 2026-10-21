<?php
/*
|--------------------------------------------------------------------------
| Configuración del módulo DigiSports League
|--------------------------------------------------------------------------
| Organización de torneos y ligas: temporadas, categorías, equipos,
| inscripciones, sorteos, fixture, partidos, estadísticas y clasificación.
|
| Lo común al ecosistema (credenciales, sesión, seguridad) viene de ds_core.
| Los escenarios y su disponibilidad vienen de Arena: League no administra
| canchas propias, para que no existan dos calendarios sobre el mismo
| espacio físico.
*/

require_once __DIR__ . "/../../ds_core/config/app.php";

/*----------  Identidad de este módulo  ----------*/
const APP_URL  = DS_LEAGUE_URL;
const APP_NAME = "DigiSports - League";

// Clave del módulo en seguridad_menu.menu_modulo y seguridad_rol_modulo.
const DS_MODULO = "league";

// Sesión única del ecosistema.
const APP_SESSION_NAME = DS_SESSION_NAME;

/* Recursos visuales: se reutiliza el vendor de Basketball —AdminLTE,
   SweetAlert2, DataTables, Select2— en lugar de duplicarlo, igual que hace
   Arena. La identidad propia de League se aplica encima con su hoja de
   estilos. */
const DS_VENDOR_URL = DS_FUTBOL_URL . "app/views/dist/";

/*----------  Permisos: modo estricto  ----------*/
/* Una vista que no esté registrada en seguridad_menu se DENIEGA.

   El comportamiento por omisión del ecosistema es el contrario: lo no
   registrado se permite, porque en Basketball son vistas de apoyo cuyo
   control efectivo está en el listado desde el que se abren. Ahí es una
   decisión razonada y se respeta.

   Aquí no puede serlo. League tendrá vistas de sorteo, de designación de
   árbitros y de carga de resultados; olvidar registrar una significaría
   dejarla abierta a cualquiera con acceso al módulo, incluidos los
   perfiles de árbitro y oficial de mesa, que deben ver casi nada.

   Con esto, el olvido deja de ser un agujero silencioso y se convierte en
   un 403 visible en la primera prueba. Para que una vista quede sujeta a
   permiso sin salir en el menú, se registra con menu_estado = 'O'. */
const DS_PERMISOS_ESTRICTOS = true;

/*----------  Facturación  ----------*/
/* League no lleva su propia identidad fiscal ni su propio certificado: son
   del contribuyente y viven en el Core. Lo que sí es suyo es el punto de
   emisión, que se consulta en facturas_electronicas_punto_emision por esta
   misma clave de módulo. Puntos distintos por módulo es lo que impide que
   dos series de secuenciales colisionen. */

/*----------  Autocarga del módulo  ----------*/
/* El autoloader del núcleo resuelve las clases dentro de ds_core, así que
   no encuentra las de este módulo. Se registra uno propio para el espacio
   de nombres league\, anclado en la carpeta del módulo. */
spl_autoload_register(function ($clase) {
    if (strpos($clase, 'league\\') !== 0) {
        return;
    }

    $relativa = str_replace('\\', '/', substr($clase, strlen('league\\')));
    $archivo  = __DIR__ . '/../' . $relativa . '.php';

    if (is_file($archivo)) {
        require_once $archivo;
    }
});
