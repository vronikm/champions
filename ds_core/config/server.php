<?php
/*
|--------------------------------------------------------------------------
| Carga de credenciales
|--------------------------------------------------------------------------
| Las credenciales NO viven aqui: se cargan desde ds_core/config/secrets.php,
| que esta excluido del repositorio. Este archivo si se versiona.
|
| Es la unica fuente de credenciales del ecosistema: los modulos lo incluyen
| en lugar de tener su propia copia.
*/

if (!file_exists(__DIR__ . "/secrets.php")) {
    http_response_code(500);
    die(
        "Falta el archivo ds_core/config/secrets.php.<br>" .
        "Copie ds_core/config/secrets.example.php a secrets.php y complete " .
        "las credenciales de este entorno."
    );
}

require_once __DIR__ . "/secrets.php";
