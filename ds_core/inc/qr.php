<?php
/*
|--------------------------------------------------------------------------
| Puente al generador de códigos QR
|--------------------------------------------------------------------------
| Mismo patrón que sri.php: la implementación vive hoy en
| ds_futbol/app/lib y este archivo es el único sitio del ecosistema que
| conoce esa ruta.
|
| POR QUÉ NO SE USA UN SERVICIO DE QR EN LÍNEA
|
| Porque el contenido que se codifica aquí es el SECRETO del segundo
| factor. Mandarlo a un generador ajeno —aunque sea por una URL de
| imagen— es entregarle la llave a un tercero, y quedaría además escrito
| en sus registros de acceso. Se genera en este servidor o no se genera.
|
| POR QUÉ UN ARCHIVO TEMPORAL Y NO SALIDA DIRECTA
|
| displayPNG() con nombre de archivo nulo emite «Content-type: image/png»
| antes de los bytes. Dentro de una página HTML eso rompe la respuesta
| entera. Escribiendo a un temporal se evita la cabecera, y el archivo se
| borra en el mismo turno.
*/

if (!function_exists('qr_disponible')) {

    /*
    | SE USA barcode.php Y NO qrcode.class.php
    |
    | En el mismo directorio hay dos generadores. qrcode.class.php necesita
    | unos archivos .dat de corrección de errores que nunca se subieron
    | —falla con «qrv5_0.dat: No such file or directory»—, así que está
    | roto en esta instalación aunque parezca lo obvio por el nombre.
    | barcode.php es autocontenido y es el que genera los QR de los
    | recibos, es decir, el que se sabe que funciona aquí.
    */
    function qr_ruta_libreria(): string
    {
        return dirname(__DIR__, 2) . '/ds_futbol/app/lib/barcode.php';
    }

    function qr_disponible(): bool
    {
        return is_file(qr_ruta_libreria());
    }

    /**
     * Codigo QR como SVG en linea.
     *
     * SVG y no PNG: se dibuja nitido a cualquier tamano —importa, porque
     * esto se fotografia con un telefono— y no obliga a incrustar binario
     * en base64.
     *
     * Devuelve cadena vacia si no se puede generar; quien lo use debe
     * ofrecer entonces el texto para teclear a mano, que es la alternativa
     * y no un mensaje de error.
     *
     * @param string $texto  lo que se codifica
     * @param int    $escala pixeles por modulo del QR
     */
    function qr_svg(string $texto, int $escala = 5): string
    {
        if ($texto === '' || !qr_disponible()) { return ''; }

        require_once qr_ruta_libreria();

        try {
            $g = new \barcode_generator();

            /* p = -10 es el margen (zona tranquila) que exige la norma
               para que el lector encuentre el codigo. */
            $svg = $g->render_svg('qr', $texto, [
                'sx' => max(2, min(12, $escala)),
                'sy' => max(2, min(12, $escala)),
                'p'  => -10,
            ]);

            /* La declaracion XML sobra al incrustar el SVG dentro del HTML
               y algunos navegadores la muestran como texto. */
            return (string)preg_replace('/^<\?xml[^>]*\?>/', '', $svg);

        } catch (\Throwable $e) {
            return '';
        }
    }
}
