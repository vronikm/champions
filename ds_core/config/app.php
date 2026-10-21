<?php
/*
|--------------------------------------------------------------------------
| Configuracion comun del ecosistema DigiSports
|--------------------------------------------------------------------------
| Todo lo que comparten el Hub y los modulos: URLs del ecosistema, zona
| horaria y credenciales. Cada modulo define aparte su propia identidad
| (APP_URL, APP_NAME) en su config/app.php.
|
| Este archivo NO define APP_URL ni APP_NAME a proposito: esas constantes
| pertenecen al contexto que se este ejecutando (el Hub o un modulo), y
| definirlas aqui provocaria una redefinicion.
*/

/*----------  Politica de errores  ----------*/
/* Lo primero de todo: si algo falla mientras se carga la configuracion,
   tambien tiene que fallar en silencio hacia fuera. Ver el archivo para
   el porque —resumen: Xdebug imprime los argumentos de cada llamada, y
   la clave de la base viaja como argumento de new PDO. */
require_once __DIR__ . "/../inc/errores.php";

/*----------  Version de los recursos  ----------*/
/* ds_recurso() anade la fecha del archivo a las hojas propias, para que
   un cambio de CSS llegue al navegador sin recarga forzada. */
require_once __DIR__ . "/../inc/recursos.php";

/*----------  Secretos y datos del entorno  ----------*/
// Credenciales de BD, TOKEN_SECRET y la URL publica. Fuente unica para todo
// el ecosistema. Se cargan ANTES de la raiz porque es secrets.php quien la
// fija en cada servidor.
require_once __DIR__ . "/server.php";

/*----------  Raiz del ecosistema  ----------*/
/* La URL depende del servidor, no del codigo: en produccion es
   https://championscf.digitech.com.ec/ y en desarrollo la de WAMP. Escrita
   aqui, cada despliegue obligaba a editar un archivo versionado —y bastaba
   un pull para devolver produccion a localhost—. Se declara en secrets.php
   (DS_HUB_URL); si no esta, se asume el entorno de desarrollo. */
defined('DS_HUB_URL') || define('DS_HUB_URL', "http://localhost/champions_cf_new/");
const DS_HUB_NAME = "DigiSports";
const DS_TAGLINE  = "Sports Management Ecosystem";

/*
| La misma raiz, pero en el disco.
|
| Hasta ahora solo habia constantes de URL, asi que el codigo que necesitaba
| MIRAR un archivo -no enlazarlo- tenia que reconstruir la ruta a mano. Se
| deriva de la posicion de este archivo, que esta en ds_core/config/, en vez
| de escribirla: asi el dia que el proyecto cambie de carpeta no hay nada
| que recordar.
|
| Termina en separador, igual que DS_HUB_URL.
*/
define('DS_HUB_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);

/*----------  URL de cada modulo  ----------*/
const DS_FUTBOL_URL = DS_HUB_URL . "ds_futbol/";
const DS_ARENA_URL      = DS_HUB_URL . "ds_arena/";
const DS_LEAGUE_URL     = DS_HUB_URL . "ds_league/";
const DS_INSIGHTS_URL   = DS_HUB_URL . "ds_insights/";

/*----------  Recursos de interfaz compartidos  ----------*/
/*
| VENDOR DEL ECOSISTEMA, NO DE UN MODULO
|
| Hasta ahora los cuatro modulos cargaban AdminLTE, jQuery y Bootstrap con
| DS_VENDOR_URL = DS_FUTBOL_URL . "app/views/dist/", es decir, desde
| DENTRO de Basketball. Si Basketball se mueve, se renombra o se despliega
| aparte, Arena, League y Core se quedan sin interfaz. Una libreria de
| terceros no pertenece a un modulo: pertenece al ecosistema.
|
| DOS VERSIONES A LA VEZ, Y A PROPOSITO
|
| AdminLTE 4 corre sobre Bootstrap 5, que renombro las utilidades y los
| atributos data-* de todos los componentes. Basketball depende de mas de
| veinte plugins atados a Bootstrap 4 —datatables-bs4, select2 con tema
| bs4, tempusdominus-bootstrap-4, que ademas esta abandonado— y migrarlo
| exige reemplazar ese stack entero.
|
| Migrar los cuatro modulos a la vez seria dejar el sistema entero fuera
| de servicio hasta terminar. Como cada modulo declara su propio
| DS_VENDOR_URL, pueden convivir: los que ya no dependen de esos plugins
| pasan a la 4, y Basketball sigue en la 3 hasta que le toque.
*/
const DS_VENDOR_CORE_URL = DS_HUB_URL . "ds_core/assets/vendor/";

/* AdminLTE 4.8.5 + Bootstrap 5.3.8 + OverlayScrollbars 2.11.0, servidos
   desde este servidor. AdminLTE los pide por CDN en su plantilla de
   ejemplo; traerlos aqui evita que quien controle un CDN ejecute lo que
   quiera dentro de una sesion con datos de menores. */
const DS_ADMINLTE4_URL = DS_VENDOR_CORE_URL . "adminlte4/";
const DS_BOOTSTRAP5_URL = DS_VENDOR_CORE_URL . "bootstrap5/";
const DS_OVERLAYSCROLL_URL = DS_VENDOR_CORE_URL . "overlayscrollbars/";

/*----------  Sesion unica del ecosistema  ----------*/
// El mismo nombre de cookie en Hub y modulos, con path "/", hace que
// iniciar sesion una vez valga para todo DigiSports.
const DS_SESSION_NAME = "DigiSportsChampionsCF";

/*----------  Cotejamiento de la conexion  ----------*/
/*
| SET NAMES utf8mb4 a secas deja collation_connection en el que el servidor
| tenga por defecto —aqui salia utf8mb4_general_ci— mientras la base esta en
| utf8mb4_0900_ai_ci. Se comprobo que eso NO es cosmetico:
|
|     SELECT ... FROM v_comprobante_emitido WHERE origen_modulo LIKE ?
|     ERROR 1267: Illegal mix of collations
|
| origen_modulo sale de un literal dentro de la vista, asi que es COERCIBLE
| igual que un parametro enlazado; dos COERCIBLE con cotejamientos distintos
| no se pueden comparar. Con la conexion alineada, la misma consulta va.
|
| Hoy facturacion.php filtra por columnas reales y no lo pisa, pero
| origen_modulo es justo la columna que separa las facturas de Basketball de
| las de League (migracion 040): el dia que se filtre por ella, rompe.
*/
const DS_DB_COLLATION   = "utf8mb4_0900_ai_ci";
const DS_DB_INIT_COMANDO = "SET NAMES utf8mb4 COLLATE " . DS_DB_COLLATION;

/*----------  Zona horaria  ----------*/
date_default_timezone_set("America/Guayaquil");

/*----------  Transporte  ----------*/
// Fuera de la maquina local, el login viaja por HTTPS o no viaja. Sin
// cifrado, la contrasena va en claro por la red y la cookie de sesion se
// puede copiar tal cual desde cualquier punto intermedio.
//
// localhost y las redes privadas quedan exentas: el desarrollo sigue
// funcionando por HTTP sin tocar nada.
//
// Poner en false SOLO si el servidor publico aun no tiene certificado, y
// entendiendo que hasta entonces las credenciales circulan legibles.
const DS_FORZAR_HTTPS = true;

// Meses que el navegador recordara que este sitio es solo HTTPS (HSTS).
// Se empieza corto a proposito: si algo va mal, el efecto caduca pronto.
// Una vez comprobado que todo funciona, subirlo a 12.
const DS_HSTS_MESES = 1;
