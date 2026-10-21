<?php
/*
|--------------------------------------------------------------------------
| Puente hacia las credenciales del nucleo
|--------------------------------------------------------------------------
| El modulo no guarda credenciales propias: las toma de ds_core, que es la
| unica fuente del ecosistema. Este archivo existe porque mainModel lo
| incluye por ruta relativa.
|
| No agregar credenciales aqui: el sitio correcto es
| ds_core/config/secrets.php (excluido del repositorio).
*/

require_once __DIR__ . "/../../ds_core/config/server.php";
