<?php
/*
|--------------------------------------------------------------------------
| Identidad de la organización y sus sedes
|--------------------------------------------------------------------------
| Fuente única del nombre legal y del logo que aparecen en PDF, recibos,
| facturas y reportes de todo el ecosistema. Se configuran en Core; los
| módulos sólo los consumen.
|
| Antes cada documento llevaba el nombre escrito a mano, así que cambiar de
| escuela obligaba a editar decenas de archivos.
|
| Formato acordado del encabezado:  "Escuela Barcelona, sede Sauces"
|
| Los logos siguen una regla de respaldo: si la sede no tiene el suyo, se
| usa el de la organización.
*/

if (!function_exists('ds_organizacion')) {

    /** Carpeta pública donde Core guarda los logos. */
    function ds_marca_dir(): string
    {
        return dirname(__DIR__) . '/assets/img/marca/';
    }

    function ds_marca_url(): string
    {
        return DS_HUB_URL . 'ds_core/assets/img/marca/';
    }

    /** Datos de la organización. Una sola consulta por petición. */
    function ds_organizacion(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = ['escuela_id' => 0, 'escuela_nombre' => '', 'escuela_ruc' => '',
                  'escuela_logo' => '', 'escuela_firma' => '', 'escuela_direccion' => '',
                  'escuela_telefono' => '', 'escuela_email' => ''];

        $con = seguridad_conexion();
        if ($con === null) {
            return $cache;
        }

        try {
            $fila = $con->query("SELECT * FROM general_escuela ORDER BY escuela_id LIMIT 1")
                        ->fetch(PDO::FETCH_ASSOC);
            if ($fila) {
                $cache = $fila;
            }
        } catch (\Throwable $e) {
            // Se conservan los valores neutros: un documento sin encabezado
            // es preferible a un error en mitad de un PDF.
        }

        return $cache;
    }

    /** Datos de una sede. Cacheado por id dentro de la petición. */
    function ds_sede_datos(int $sedeid): array
    {
        static $cache = [];
        if (isset($cache[$sedeid])) {
            return $cache[$sedeid];
        }

        $cache[$sedeid] = ['sede_id' => 0, 'sede_nombre' => '', 'sede_foto' => '',
                           'sede_firma' => '', 'sede_direccion' => '', 'sede_telefono' => ''];

        $con = seguridad_conexion();
        if ($con === null || $sedeid <= 0) {
            return $cache[$sedeid];
        }

        try {
            $stmt = $con->prepare("SELECT * FROM general_sede WHERE sede_id = :id");
            $stmt->execute([':id' => $sedeid]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fila) {
                $cache[$sedeid] = $fila;
            }
        } catch (\Throwable $e) {
            // Igual que arriba: se degrada en silencio.
        }

        return $cache[$sedeid];
    }

    /**
     * Encabezado para documentos: "Escuela Barcelona, sede Sauces".
     *
     * Sin sede devuelve sólo el nombre de la organización. Si la sede no
     * existe o no tiene nombre, tampoco se añade la coma suelta.
     */
    function ds_nombre_organizacion(int $sedeid = 0): string
    {
        $org    = ds_organizacion();
        $nombre = trim((string)$org['escuela_nombre']);

        if ($sedeid <= 0) {
            return $nombre;
        }

        $sede = trim((string)ds_sede_datos($sedeid)['sede_nombre']);

        if ($sede === '') {
            return $nombre;
        }

        return $nombre !== '' ? $nombre . ', sede ' . $sede : 'Sede ' . $sede;
    }

    /** Igual que el anterior, en mayúsculas, para encabezados de PDF. */
    function ds_nombre_organizacion_may(int $sedeid = 0): string
    {
        return mb_strtoupper(ds_nombre_organizacion($sedeid), 'UTF-8');
    }

    /**
     * Correo de contacto que se imprime en carnets, recibos y correos
     * salientes. Si la sede tiene el suyo, manda el de la sede.
     */
    function ds_email_organizacion(int $sedeid = 0): string
    {
        if ($sedeid > 0) {
            $propio = trim((string)(ds_sede_datos($sedeid)['sede_email'] ?? ''));
            if ($propio !== '') {
                return $propio;
            }
        }

        return trim((string)ds_organizacion()['escuela_email']);
    }

    /** Misma regla que el correo: la sede manda, la organización respalda. */
    function ds_telefono_organizacion(int $sedeid = 0): string
    {
        if ($sedeid > 0) {
            $propio = trim((string)(ds_sede_datos($sedeid)['sede_telefono'] ?? ''));
            if ($propio !== '') {
                return $propio;
            }
        }

        return trim((string)ds_organizacion()['escuela_telefono']);
    }

    /**
     * Prefijo para nombres de descarga: "Escuela Barcelona" -> "EscuelaBarcelona".
     * Sin nombre configurado cae en el del ecosistema, para no generar
     * archivos llamados "-Alumnos.pdf".
     */
    function ds_nombre_archivo(string $sufijo = ''): string
    {
        $base = ds_organizacion()['escuela_nombre'];
        $base = preg_replace('/[^A-Za-z0-9]/', '', ds_sin_tildes((string)$base));

        if ($base === '') {
            $base = 'DigiSports';
        }

        return $sufijo !== '' ? $base . '-' . $sufijo : $base;
    }

    /** Quita tildes y ñ para que el nombre de archivo viaje sin sorpresas. */
    function ds_sin_tildes(string $texto): string
    {
        $de = ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'];
        $a  = ['a','e','i','o','u','u','n','A','E','I','O','U','U','N'];
        return str_replace($de, $a, $texto);
    }

    /**
     * Regla común de las imágenes de marca (logo y firma):
     * manda la de la sede; si no tiene, o si el archivo ya no está en
     * disco, se usa la de la organización.
     */
    function ds_imagen_marca(string $campoSede, string $campoEscuela, int $sedeid): string
    {
        if ($sedeid > 0) {
            $propio = trim((string)(ds_sede_datos($sedeid)[$campoSede] ?? ''));
            if ($propio !== '' && is_file(ds_marca_dir() . $propio)) {
                return $propio;
            }
        }

        $org = trim((string)(ds_organizacion()[$campoEscuela] ?? ''));
        return ($org !== '' && is_file(ds_marca_dir() . $org)) ? $org : '';
    }

    /**
     * Nombre del archivo de logo aplicable a una sede.
     * Si la sede no tiene el suyo, hereda el de la organización.
     */
    function ds_logo_archivo(int $sedeid = 0): string
    {
        return ds_imagen_marca('sede_foto', 'escuela_logo', $sedeid);
    }

    /**
     * URL del logo para HTML. Cadena vacía si no hay ninguno configurado.
     *
     * LLEVA MARCA DE VERSIÓN
     *
     * El nombre del archivo no cambia cuando se reemplaza su contenido, así
     * que el navegador que ya lo vio sigue mostrando el anterior durante
     * días. Se sustituyó el logo de la escuela por uno con fondo
     * transparente y en pantalla seguía saliendo el blanco: no era el
     * archivo, era la caché.
     *
     * filemtime() como versión, igual que ds_recurso() con el CSS y el JS.
     * Si el archivo no está donde dice el registro se devuelve la URL sin
     * versión: una etiqueta rota es mejor que un aviso de PHP en mitad de
     * la página.
     *
     * Sólo para HTML. Quien necesite la ruta en disco —los PDF— tiene
     * ds_logo_ruta(): FPDF deduce el formato de la extensión del nombre y
     * con «?v=» detrás leería «png?v=123».
     */
    function ds_logo_url(int $sedeid = 0): string
    {
        $archivo = ds_logo_archivo($sedeid);
        if ($archivo === '') {
            return '';
        }

        $url    = ds_marca_url() . rawurlencode($archivo);
        $version = @filemtime(ds_marca_dir() . $archivo);

        return $version !== false ? $url . '?v=' . $version : $url;
    }

    /**
     * Ruta en disco del logo, para FPDF.
     *
     * FPDF lanza excepción si el archivo no existe, así que quien la use
     * debe comprobar que la cadena no esté vacía antes de llamar a Image().
     */
    function ds_logo_ruta(int $sedeid = 0): string
    {
        $archivo = ds_logo_archivo($sedeid);
        return $archivo !== '' ? ds_marca_dir() . $archivo : '';
    }

    /**
     * Firma autorizada que se estampa en los recibos.
     * Misma herencia que el logo: la de la sede manda, la de la
     * organización respalda.
     */
    function ds_firma_archivo(int $sedeid = 0): string
    {
        return ds_imagen_marca('sede_firma', 'escuela_firma', $sedeid);
    }

    function ds_firma_url(int $sedeid = 0): string
    {
        $archivo = ds_firma_archivo($sedeid);
        return $archivo !== '' ? ds_marca_url() . rawurlencode($archivo) : '';
    }

    /** Ruta en disco de la firma, para FPDF. Vacía si no hay ninguna. */
    function ds_firma_ruta(int $sedeid = 0): string
    {
        $archivo = ds_firma_archivo($sedeid);
        return $archivo !== '' ? ds_marca_dir() . $archivo : '';
    }

    /**
     * Dibuja la firma dentro de un recuadro SIN deformarla.
     *
     * Los recibos la pedían con ancho Y alto fijos (47 x 23 mm). FPDF, con
     * las dos medidas dadas, estira la imagen hasta llenarlas: una rúbrica
     * de proporción 1,6:1 metida en un hueco de 2:1 sale un 29% más ancha
     * de lo que es. En una firma eso no es un detalle estético — deja de
     * parecerse al trazo de la persona.
     *
     * Aquí se calcula la escala que cabe y se centra en el hueco. Si no hay
     * firma configurada, o el archivo no está, no se dibuja nada y el
     * documento se genera igual.
     *
     * @param object $pdf  Instancia de FPDF.
     * @return bool  true si llegó a dibujarse.
     */
    function ds_firma_dibujar(object $pdf, int $sedeid,
                              float $x, float $y, float $ancho, float $alto): bool
    {
        return ds_pdf_imagen_encajada($pdf, ds_firma_ruta($sedeid), $x, $y, $ancho, $alto);
    }

    /**
     * Dibuja el logo de la sede dentro de un recuadro SIN deformarlo.
     *
     * Mismo problema que tuvo la firma: los PDF pedían el logo con ancho Y
     * alto fijos y FPDF lo estiraba hasta llenarlos. El escudo es casi
     * cuadrado (0,97:1) y el listado de alumnos lo metía en 47 x 26 mm: salía
     * casi el doble de ancho. Pasaba, en distinta medida, en once PDF.
     *
     * $alinear: 'C' centra en el recuadro; 'L' lo pega a la izquierda (para
     * cabeceras con el logo a un lado y el texto al otro).
     *
     * @param object $pdf  Instancia de FPDF.
     * @return bool  true si llegó a dibujarse.
     */
    function ds_logo_dibujar(object $pdf, int $sedeid,
                             float $x, float $y, float $ancho, float $alto, string $alinear = 'C'): bool
    {
        return ds_pdf_imagen_encajada($pdf, ds_logo_ruta($sedeid), $x, $y, $ancho, $alto, $alinear);
    }

    /**
     * Una imagen dentro de un recuadro, a su proporción: la escala menor de
     * las dos es la que hace que quepa entera, y se centra en el hueco (en
     * horizontal, según $alinear). Si el archivo no está o no es una imagen,
     * no dibuja nada y el documento se genera igual.
     */
    function ds_pdf_imagen_encajada(object $pdf, string $archivo,
                                    float $x, float $y, float $ancho, float $alto, string $alinear = 'C'): bool
    {
        if ($archivo === '' || !is_file($archivo)) { return false; }

        $medidas = @getimagesize($archivo);
        if ($medidas === false || $medidas[0] <= 0 || $medidas[1] <= 0) { return false; }

        $escala = min($ancho / $medidas[0], $alto / $medidas[1]);
        $w = $medidas[0] * $escala;
        $h = $medidas[1] * $escala;
        $dx = $alinear === 'L' ? 0 : ($alinear === 'R' ? $ancho - $w : ($ancho - $w) / 2);

        /*
        | PNG CON TRANSPARENCIA Y SIN ZLIB
        |
        | FPDF necesita la extensión zlib de PHP para el canal alfa de un PNG
        | y, si no está, lanza «Zlib not available, can't handle alpha
        | channel»: un logo de sede con fondo transparente bastaba para que
        | ningún PDF con logo se generara. Sin zlib, el PNG se aplana sobre
        | blanco en un JPEG (con GD) y se dibuja ése.
        |
        | Y si aun así FPDF no puede con la imagen (PNG entrelazado, de 16
        | bits, dañado…), el documento sale sin ella en vez de no salir: un
        | recibo sin logo sirve; una página de error, no.
        */
        $usar = $archivo;
        if ($medidas[2] === IMAGETYPE_PNG && !function_exists('gzuncompress')) {
            $usar = ds_pdf_png_aplanado($archivo);
            if ($usar === '') { return false; }
        }

        try {
            $pdf->Image($usar, $x + $dx, $y + ($alto - $h) / 2, $w, $h);
        } catch (\Throwable $e) {
            error_log('[DS pdf] imagen omitida (' . basename($archivo) . '): ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Copia JPEG de un PNG, aplanada sobre blanco, para FPDF sin zlib.
     *
     * Se guarda en el temporal del sistema con el nombre derivado de la ruta
     * y la fecha del archivo: se genera una vez por logo y se rehace sola si
     * el logo cambia. Devuelve '' si no se puede (sin GD o sin escritura).
     */
    function ds_pdf_png_aplanado(string $archivo): string
    {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) { return ''; }

        $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ds_pdf_imagenes';
        $destino = $dir . DIRECTORY_SEPARATOR . md5($archivo . '|' . @filemtime($archivo)) . '.jpg';
        if (is_file($destino)) { return $destino; }
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) { return ''; }

        $png = @imagecreatefrompng($archivo);
        if ($png === false) { return ''; }
        $ancho = imagesx($png);
        $alto  = imagesy($png);
        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopy($lienzo, $png, 0, 0, 0, 0, $ancho, $alto);
        $ok = @imagejpeg($lienzo, $destino, 92);
        imagedestroy($png);
        imagedestroy($lienzo);

        return $ok ? $destino : '';
    }
}
