<?php
/*
|--------------------------------------------------------------------------
| PLANTILLA DE SECRETOS
|--------------------------------------------------------------------------
| Copie este archivo como  ds_core/config/secrets.php  y complete los
| valores reales del entorno. secrets.php NUNCA se versiona (ver .gitignore).
|
|   cp ds_core/config/secrets.example.php ds_core/config/secrets.php
*/

/*----------  URL publica del sistema  ----------*/
// Con barra final. Todos los enlaces, el formulario de inscripcion y los
// recursos se arman a partir de ella. Si se omite, se asume la de
// desarrollo (http://localhost/champions_cf_new/).
//
// Produccion:
define('DS_HUB_URL', 'https://champions.digitech.com.ec/');

/*----------  Base de datos  ----------*/
// En produccion, un usuario propio con permisos SOLO sobre esta base.
// Nunca root: root puede leer y borrar cualquier base del servidor.
const DB_SERVER = "localhost";
const DB_NAME   = "nombre_de_la_base";
const DB_USER   = "usuario_de_la_base";
const DB_PASS   = 'clave_de_la_base';

/*----------  Firma de los enlaces de inscripcion (HMAC)  ----------*/
// Uno distinto por entorno: el de desarrollo no debe valer en produccion.
// Generar uno nuevo con:  php -r "echo bin2hex(random_bytes(32));"
const TOKEN_SECRET = 'reemplazar_por_un_secreto_aleatorio_largo';
