<?php
/*
|--------------------------------------------------------------------------
| Credenciales de base de datos
|--------------------------------------------------------------------------
| Este archivo ya NO declara credenciales propias. Antes apuntaba a la base
| de otra escuela (digitech_adfpl), de modo que una inscripcion hecha desde
| este formulario habria acabado en el sistema de un tercero.
|
| La fuente unica del ecosistema es ds_core/config/secrets.php, que llega a
| traves de ds_core/config/app.php. Se conserva el archivo porque
| app/models/mainModel.php lo requiere por ruta relativa; el require_once de
| abajo es inocuo si el nucleo ya esta cargado.
*/

require_once __DIR__ . "/../../ds_core/config/app.php";
