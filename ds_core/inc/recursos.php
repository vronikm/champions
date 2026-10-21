<?php
/*
|--------------------------------------------------------------------------
| Version de los recursos propios
|--------------------------------------------------------------------------
| Anade ?v=<fecha del archivo> a las hojas y scripts del ecosistema.
|
| POR QUE HACE FALTA
|
| Los enlaces eran «core.css» a secas y Apache no envia Cache-Control, asi
| que el navegador decide por su cuenta y sirve la copia guardada sin
| preguntar. Consecuencia real: se corrigio el fondo del rotulo de la marca,
| el servidor entregaba la hoja nueva, y en el navegador de quien lo reporto
| seguia viendose el fallo. Se comprobo pidiendo el archivo a Apache: llegaba
| corregido.
|
| Con la fecha del archivo en la URL, el navegador ve una direccion distinta
| en cuanto el archivo cambia y la vuelve a pedir. Mientras no cambie, sigue
| usando su copia, que es justo lo que se quiere.
|
| SOLO PARA LO NUESTRO
|
| Las librerias de terceros (AdminLTE, Bootstrap, DataTables) no se tocan:
| cambian con una actualizacion, no con una edicion, y sus rutas ya llevan
| la version en la carpeta.
|
| USO
|     <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/core.css'); ?>">
*/

if (!function_exists('ds_recurso')) {
    /**
     * URL de un recurso propio con la fecha del archivo como version.
     *
     * @param string $relativa Ruta desde la raiz del ecosistema, sin barra inicial.
     */
    function ds_recurso(string $relativa): string
    {
        $url = DS_HUB_URL . $relativa;

        /* Si el archivo no esta donde se dice, se devuelve la URL tal cual:
           una version equivocada es peor que ninguna, y romper la pagina por
           un recurso mal escrito seria peor todavia. */
        $disco = DS_HUB_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relativa);
        if (!is_file($disco)) {
            return $url;
        }

        return $url . '?v=' . filemtime($disco);
    }
}
