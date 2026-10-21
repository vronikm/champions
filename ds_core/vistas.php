<?php
/*
|--------------------------------------------------------------------------
| Catálogo de vistas por módulo
|--------------------------------------------------------------------------
| Permite a DigiSports Core saber qué rutas existen realmente en cada
| módulo, para que un menú nunca pueda apuntar a una vista inexistente.
|
| Cada módulo publica su lista blanca en config/vistas.php; aquí solo se
| resuelve dónde está la de cada uno. Un módulo que aún no existe devuelve
| una lista vacía en lugar de fallar.
*/

if (!function_exists('ds_vistas_modulo')) {

    /** Rutas navegables de un módulo. [] si el módulo no está construido. */
    function ds_vistas_modulo(string $modulo): array
    {
        $raiz = dirname(__DIR__);   // .../barcelona

        $ubicaciones = [
            'core'       => $raiz . '/ds_core/admin/config/vistas.php',
            'basketball' => $raiz . '/ds_futbol/config/vistas.php',
            'arena'      => $raiz . '/ds_arena/config/vistas.php',
            'league'     => $raiz . '/ds_league/config/vistas.php',
            'insights'   => $raiz . '/ds_insights/config/vistas.php',
        ];

        if (!isset($ubicaciones[$modulo]) || !is_file($ubicaciones[$modulo])) {
            return [];
        }

        $vistas = require $ubicaciones[$modulo];

        if (!is_array($vistas)) {
            return [];
        }

        sort($vistas);
        return $vistas;
    }

    /** true si la vista existe en el módulo indicado. */
    function ds_vista_existe(string $modulo, string $vista): bool
    {
        return in_array($vista, ds_vistas_modulo($modulo), true);
    }
}
