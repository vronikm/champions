<?php
/*
|--------------------------------------------------------------------------
| Configuracion del formulario publico de inscripcion
|--------------------------------------------------------------------------
| Este proyecto llego como copia literal del formulario de otra escuela y
| conservaba SU configuracion: apuntaba a otra URL, a otra base de datos y
| firmaba los tokens con otro secreto. Ahora consume el nucleo de
| DigiSports, de modo que no puede volver a desincronizarse.
|
| Vive dentro del proyecto (ds_form/), no al lado: la URL se deriva de
| DS_HUB_URL —que fija ds_core/config/secrets.php en cada servidor— para
| que no haya dos sitios donde tocarla.
*/

/*----------  Nucleo del ecosistema  ----------*/
// Aporta DS_HUB_URL, DS_SESSION_NAME, la zona horaria y las credenciales.
require_once __DIR__ . "/../../ds_core/config/app.php";

/*----------  Identidad de este formulario  ----------*/
const APP_URL  = DS_HUB_URL . "ds_form/";
const APP_NAME = "DigiSports - Inscripción";

/* Sesion propia y separada: quien rellena el formulario es un visitante
   anonimo, no un usuario del sistema. Compartir la cookie con el panel
   mezclaria dos contextos que no tienen nada que ver. */
const APP_SESSION_NAME = "DigiSportsChampionsCFInscripcion";

/*----------  Enlace de inscripcion  ----------*/
/* El secreto de firma NO se escribe aqui: se toma del nucleo. Cuando cada
   proyecto guardaba su propia copia, bastaba con rotar uno para que todos
   los enlaces emitidos dejaran de validar sin motivo aparente.
   TOKEN_SECRET y TOKEN_EXPIRY llegan desde ds_core/config. */

/*----------  Fotos de alumnos  ----------*/
/* La foto que sube el representante tiene que verse desde el panel, asi
   que se escribe en la carpeta del modulo de la escuela. */
define('DIR_FOTOS_ALUMNO', __DIR__ . "/../../ds_futbol/app/views/imagenes/fotos/alumno/");

/*----------  Nombre visible de la escuela  ----------*/
/* Se lee de la organizacion configurada en Core, no de una constante
   escrita a mano: es el mismo nombre que encabeza recibos y facturas.
   Antes decia "AF Pedro Larrea" y era lo primero que veia el
   representante al abrir el enlace. */
require_once __DIR__ . "/../../ds_core/inc/seguridad.php";
require_once __DIR__ . "/../../ds_core/inc/organizacion.php";

define('ESCUELA_NOMBRE', ds_nombre_organizacion() ?: DS_HUB_NAME);
