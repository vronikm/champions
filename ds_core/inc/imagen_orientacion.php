<?php
/*
|--------------------------------------------------------------------------
| Orientación EXIF de las fotos
|--------------------------------------------------------------------------
| El celular no gira los píxeles al tomar una foto en vertical: los guarda
| como salen del sensor (apaisados) y anota en el EXIF «girar 90°». El
| navegador lee esa nota y la enseña derecha, por eso la vista previa se
| ve bien. Pero GD no la lee: al redimensionar con imagecreatefromjpeg() +
| imagejpeg() se dibujan los píxeles crudos y el EXIF se pierde, así que
| la foto guardada queda acostada para siempre.
|
| Aquí se lee esa nota y se giran los píxeles ANTES de redimensionar.
|
| La lectura se hace a mano —buscar el segmento APP1 «Exif» y la etiqueta
| 0x0112 del primer IFD— y no con exif_read_data(): la extensión exif no
| siempre está activa en el hosting, y sin ella el arreglo no haría nada
| sin avisar.
|
| Sin dependencias del resto del ecosistema: lo cargan ds_futbol, ds_form
| y ds_core/inc/imagenes.php.
*/

if (!function_exists('ds_imagen_orientacion_exif')) {

    /**
     * Valor de Orientation (1 a 8) de un JPEG; 1 si no tiene o no se lee.
     */
    function ds_imagen_orientacion_exif(string $archivo): int
    {
        $fh = @fopen($archivo, 'rb');
        if ($fh === false) {
            return 1;
        }
        /* El EXIF va al principio y un APP1 no pasa de 64 KB. */
        $d = (string)fread($fh, 131072);
        fclose($fh);

        $n = strlen($d);
        if ($n < 4 || substr($d, 0, 2) !== "\xFF\xD8") {
            return 1;
        }

        $pos = 2;
        while ($pos + 4 <= $n) {
            if ($d[$pos] !== "\xFF") {
                return 1;
            }
            $marca = ord($d[$pos + 1]);
            if ($marca === 0xFF) {                       /* relleno */
                $pos++;
                continue;
            }
            if ($marca === 0xDA || $marca === 0xD9) {    /* empiezan los datos: no hay EXIF */
                return 1;
            }
            if ($marca === 0x01 || ($marca >= 0xD0 && $marca <= 0xD7)) {
                $pos += 2;
                continue;
            }

            $largo = unpack('n', substr($d, $pos + 2, 2))[1];

            if ($marca === 0xE1 && substr($d, $pos + 4, 6) === "Exif\0\0") {
                $tiff = $pos + 10;
                if ($tiff + 8 > $n) {
                    return 1;
                }
                $orden = substr($d, $tiff, 2);
                if ($orden !== 'II' && $orden !== 'MM') {
                    return 1;
                }
                $le  = $orden === 'II';
                $u16 = static fn(int $o): int => unpack($le ? 'v' : 'n', substr($d, $o, 2))[1];
                $u32 = static fn(int $o): int => unpack($le ? 'V' : 'N', substr($d, $o, 4))[1];

                $ifd = $tiff + $u32($tiff + 4);
                if ($ifd + 2 > $n) {
                    return 1;
                }
                $entradas = $u16($ifd);
                for ($i = 0; $i < $entradas; $i++) {
                    $e = $ifd + 2 + $i * 12;
                    if ($e + 12 > $n) {
                        break;
                    }
                    if ($u16($e) === 0x0112) {
                        $v = $u16($e + 8);
                        return ($v >= 1 && $v <= 8) ? $v : 1;
                    }
                }
                return 1;
            }

            $pos += 2 + $largo;
        }

        return 1;
    }

    /**
     * Gira o refleja los píxeles según la orientación EXIF. Devuelve la
     * imagen resultante (puede ser otra: imagerotate() crea una nueva).
     */
    function ds_imagen_orientar(\GdImage $img, int $orientacion): \GdImage
    {
        $girar = static function (\GdImage $i, int $grados): \GdImage {
            $nueva = imagerotate($i, $grados, 0);
            /* Sin imagedestroy(): desde PHP 8 no hace nada (la libera el
               recolector) y en 8.5 está obsoleta. */
            return $nueva === false ? $i : $nueva;
        };

        /* imagerotate() gira en sentido ANTIHORARIO: -90 es horario. */
        switch ($orientacion) {
            case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 3: $img = $girar($img, 180); break;
            case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
            case 5: $img = $girar($img, -90); imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 6: $img = $girar($img, -90); break;
            case 7: $img = $girar($img, -90); imageflip($img, IMG_FLIP_VERTICAL); break;
            case 8: $img = $girar($img, 90); break;
        }

        return $img;
    }
}
