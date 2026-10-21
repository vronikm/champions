<?php
/*
|--------------------------------------------------------------------------
| Subida de imágenes del ecosistema
|--------------------------------------------------------------------------
| Este pipeline existía dentro de coreController, en privado, y sólo servía
| a los logos de la organización. Se promueve aquí para que League —y lo que
| venga— use el mismo, en vez de escribir cada módulo su propia validación.
|
| Una subida de archivos es de los puntos más atacados de un sistema web, y
| la defensa que de verdad importa no es la lista de extensiones:
|
|   1. NO se confía en el nombre ni en la extensión que envía el navegador:
|      ambos los escribe quien sube. Se mira el contenido real con
|      mime_content_type().
|
|   2. La imagen NO se guarda tal cual: se vuelve a dibujar con GD y se
|      escribe de nuevo. Eso descarta cualquier carga incrustada —un PHP
|      escondido tras una cabecera PNG no sobrevive a imagecreatefrompng()
|      seguido de imagepng()—, que es el vector clásico.
|
|   3. El nombre del archivo lo genera el servidor. Sin eso, un nombre como
|      "../../index.php" decide dónde acaba el archivo.
|
|   4. Se limita el tamaño antes de tocar nada, y las dimensiones después:
|      una imagen de 20000×20000 píxeles agota la memoria de PHP aunque el
|      archivo pese poco. Es la «bomba de descompresión».
|
| Se escribe siempre PNG plano sobre blanco, sin canal alfa: FPDF revienta
| al encontrar transparencia, y estas imágenes acaban en carnés y actas.
*/

if (!function_exists('ds_imagen_subir')) {

    /** Tamaño máximo aceptado, en bytes. */
    function ds_imagen_max_bytes(): int
    {
        return 2 * 1024 * 1024;
    }

    /** Lado mayor al que se reduce la imagen guardada. */
    function ds_imagen_lado_max(): int
    {
        return 600;
    }

    /**
     * Límite de píxeles totales admitido en el original.
     *
     * GD reserva unos 4 bytes por píxel al abrir la imagen, así que un
     * archivo de 300 KB con 100 millones de píxeles pediría ~400 MB y
     * tumbaría el proceso. El tamaño en disco no protege de eso.
     */
    function ds_imagen_max_pixeles(): int
    {
        return 40_000_000;
    }

    /**
     * Recibe un archivo subido, lo valida y lo guarda normalizado.
     *
     * @param string $campo   nombre del input file
     * @param string $destino carpeta absoluta donde escribir
     * @param string $prefijo prefijo del nombre generado
     * @return string nombre del archivo guardado, '' si no se subió nada,
     *                o el motivo del fallo precedido de '!'
     */
    function ds_imagen_subir(string $campo, string $destino, string $prefijo): string
    {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        $f = $_FILES[$campo];

        if ($f['error'] !== UPLOAD_ERR_OK) {
            /* Los códigos 1 y 2 son límites de tamaño y merecen un mensaje
               que el usuario pueda accionar. */
            return in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? '!La imagen supera el tamaño permitido por el servidor.'
                : '!No se pudo subir la imagen (código ' . $f['error'] . ').';
        }

        /* Sin esto, una petición manipulada podría señalar a un archivo
           cualquiera del servidor y hacer que se copie a la carpeta
           pública. */
        if (!is_uploaded_file($f['tmp_name'])) {
            return '!El archivo recibido no proviene de una subida válida.';
        }

        if ($f['size'] > ds_imagen_max_bytes()) {
            return '!La imagen no puede superar '
                 . round(ds_imagen_max_bytes() / 1048576) . ' MB.';
        }

        $tipo = @mime_content_type($f['tmp_name']);

        if (!in_array($tipo, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return '!Formato no admitido. Use JPG, PNG o WEBP.';
        }

        /* Dimensiones antes de abrir: getimagesize sólo lee la cabecera y
           no reserva memoria por la imagen entera. */
        $medidas = @getimagesize($f['tmp_name']);
        if ($medidas === false) {
            return '!El archivo no es una imagen legible.';
        }
        if ((int)$medidas[0] * (int)$medidas[1] > ds_imagen_max_pixeles()) {
            return '!La imagen tiene demasiados píxeles. Redúzcala antes de subirla.';
        }

        if (!is_dir($destino) && !@mkdir($destino, 0775, true)) {
            return '!No existe la carpeta de destino y no se pudo crear.';
        }

        /* El nombre lo pone el servidor. Se añade un tramo aleatorio para
           que dos subidas en el mismo segundo no se pisen. */
        $nombre = $prefijo . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.png';

        $error = ds_imagen_normalizar($f['tmp_name'], rtrim($destino, '/\\') . '/' . $nombre, $tipo);

        return $error === '' ? $nombre : '!' . $error;
    }

    /**
     * Aplana la imagen sobre blanco y la guarda como PNG sin transparencia
     * ni entrelazado, con el lado mayor limitado.
     *
     * Volver a dibujarla es lo que descarta cualquier contenido que no sea
     * imagen: lo que se escribe son píxeles leídos por GD, no los bytes que
     * llegaron.
     */
    function ds_imagen_normalizar(string $origen, string $destino, string $mime): string
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($origen),
            'image/png'  => @imagecreatefrompng($origen),
            'image/webp' => @imagecreatefromwebp($origen),
            default      => false,
        };

        if ($img === false) {
            return 'La imagen está dañada o no se pudo leer.';
        }

        $ancho = imagesx($img);
        $alto  = imagesy($img);

        if ($ancho < 1 || $alto < 1) {
            imagedestroy($img);
            return 'La imagen no tiene dimensiones válidas.';
        }

        $lado  = ds_imagen_lado_max();
        $razon = min(1, $lado / max($ancho, $alto));

        $nuevoAncho = max(1, (int)round($ancho * $razon));
        $nuevoAlto  = max(1, (int)round($alto  * $razon));

        $salida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        /* Fondo blanco: un escudo con transparencia sobre un acta impresa
           en blanco se ve igual, y así FPDF no encuentra canal alfa. */
        imagefilledrectangle($salida, 0, 0, $nuevoAncho, $nuevoAlto,
                             imagecolorallocate($salida, 255, 255, 255));

        imagecopyresampled($salida, $img, 0, 0, 0, 0,
                           $nuevoAncho, $nuevoAlto, $ancho, $alto);

        imageinterlace($salida, false);
        $ok = imagepng($salida, $destino, 6);

        imagedestroy($img);
        imagedestroy($salida);

        return $ok ? '' : 'No se pudo guardar la imagen en el servidor.';
    }

    /**
     * Decide con qué archivo se queda un campo de imagen: el recién subido,
     * ninguno si se pidió quitarlo, o el que ya había. Borra del disco el
     * que deja de usarse.
     *
     * Devuelve el nombre resultante, o el motivo del fallo precedido de '!'.
     */
    function ds_imagen_resolver(string $campo, string $carpeta, string $prefijo,
                                string $actual, bool $quitar): string
    {
        $subido = ds_imagen_subir($campo, $carpeta, $prefijo);

        if ($subido !== '' && $subido[0] === '!') {
            return $subido;
        }

        if ($subido !== '') {
            ds_imagen_borrar($carpeta, $actual);
            return $subido;
        }

        if ($quitar) {
            ds_imagen_borrar($carpeta, $actual);
            return '';
        }

        return $actual;
    }

    /**
     * Borra una imagen que ya no se usa.
     *
     * basename() es deliberado: si el nombre guardado estuviera
     * manipulado, esto impide que el borrado salga de su carpeta.
     */
    function ds_imagen_borrar(string $carpeta, string $archivo): void
    {
        if ($archivo === '') { return; }

        $ruta = rtrim($carpeta, '/\\') . '/' . basename($archivo);

        if (is_file($ruta)) { @unlink($ruta); }
    }
}
