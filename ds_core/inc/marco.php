<?php
/*
|--------------------------------------------------------------------------
| Análisis del marco de la tarjeta de cumpleaños
|--------------------------------------------------------------------------
| La tarjeta se dibuja poniendo la foto del alumno y encima el PNG del marco.
| Para que la foto se vea, el marco necesita una VENTANA TRANSPARENTE justo
| donde va la foto. Si no la tiene, el marco tapa la foto entera y la tarjeta
| sale con el alumno invisible.
|
|
| POR QUÉ HACE FALTA COMPROBARLO
|
| Esto no es una precaución teórica: ocurrió. Se subió un marco por la
| pantalla de Core, se guardó sin una sola advertencia, y las tarjetas
| salieron sin foto. El PNG parecía correcto —en el editor de imágenes se veía
| el damero gris de la transparencia— pero ese damero estaba PINTADO dentro
| del archivo. Es un fallo clásico al exportar: el editor muestra el damero
| para representar el vacío, y ciertas rutas de exportación lo hornean como
| píxeles grises de verdad.
|
| Desde fuera las dos cosas son idénticas. Sólo se distinguen mirando el canal
| alfa, que es justo lo que hace este archivo.
|
|
| POR QUÉ NO SE MIRA SÓLO «¿TIENE ALFA?»
|
| Un PNG puede tener canal alfa y aun así estar opaco donde importa. Lo que
| decide si la tarjeta va a salir bien no es que el archivo admita
| transparencia, sino que sea transparente EN EL RECTÁNGULO DE LA FOTO. Por
| eso se mide dentro de ese rectángulo y no en el archivo entero.
*/

if (!function_exists('ds_marco_analizar')) {

    /**
     * Tope de píxeles que se acepta analizar.
     *
     * Una imagen de 20000x20000 pesa poco comprimida y agota la memoria de
     * PHP al descomprimirla —la «bomba de descompresión»—. Un marco de
     * imprenta a 300 ppp para una tarjeta A6 ronda los 3 MP; 25 deja margen
     * de sobra sin dejar la puerta abierta.
     */
    function ds_marco_max_pixeles(): int
    {
        return 25 * 1000 * 1000;
    }

    /**
     * ¿Es este color uno de los dos tonos del damero?
     *
     * Gris de verdad —los tres canales casi iguales— y claro. Se exige que sea
     * neutro además de claro porque el marco tiene grises propios con matiz
     * (sombras, balones, líneas de cancha) que NO hay que tocar.
     *
     * El límite inferior es 155 y no 170 por medición: el tono oscuro del
     * damero de Photoshop ronda el #A5A5A5, y con 170 quedaban filas enteras
     * sin cortar que se veían como bandas grises sobre la foto.
     */
    function ds_marco_es_damero(int $c): bool
    {
        $r = ($c >> 16) & 0xFF;
        $g = ($c >> 8) & 0xFF;
        $b = $c & 0xFF;

        if (max($r, $g, $b) - min($r, $g, $b) > 8) { return false; }

        return $r >= 155 && $r <= 250;
    }

    /** ¿Está este píxel lo bastante transparente como para que asome la foto? */
    function ds_marco_es_hueco(int $c): bool
    {
        return (($c >> 24) & 0x7F) > 100;
    }

    /**
     * Qué porcentaje del rectángulo de la foto deja pasar la imagen de abajo.
     *
     * $rect llega en PORCENTAJE del marco, que es como se guarda todo en
     * cumple_plantilla; aquí se convierte a píxeles contra el tamaño real.
     */
    function ds_marco_hueco_en_rect(\GdImage $im, array $rect): float
    {
        $W = imagesx($im);
        $H = imagesy($im);

        $x0 = (int) round($W * max(0, min(100, (float) $rect['x'])) / 100);
        $y0 = (int) round($H * max(0, min(100, (float) $rect['y'])) / 100);
        $x1 = (int) round($x0 + $W * max(0, min(100, (float) $rect['w'])) / 100);
        $y1 = (int) round($y0 + $H * max(0, min(100, (float) $rect['h'])) / 100);

        $x1 = min($x1, $W - 1);
        $y1 = min($y1, $H - 1);

        if ($x1 <= $x0 || $y1 <= $y0) { return 0.0; }

        /* Se muestrea de 2 en 2. Un damero de 8 px de celda o una ventana
           maciza se detectan igual, y el análisis baja a la cuarta parte del
           tiempo en marcos de imprenta. */
        $vistos = 0;
        $huecos = 0;
        for ($y = $y0; $y <= $y1; $y += 2) {
            for ($x = $x0; $x <= $x1; $x += 2) {
                $vistos++;
                if (ds_marco_es_hueco(imagecolorat($im, $x, $y))) { $huecos++; }
            }
        }

        return $vistos > 0 ? round($huecos / $vistos * 100, 2) : 0.0;
    }

    /**
     * El rectángulo que ocupa la zona ya transparente del marco, en píxeles.
     *
     * Sirve para el caso «el marco SÍ tiene ventana, pero el área de la foto
     * está colocada en otro sitio»: en vez de rechazarlo, se puede decir dónde
     * está la ventana de verdad.
     *
     * Devuelve null si no hay transparencia suficiente como para llamarla
     * ventana. El mínimo del 1 % descarta los bordes suavizados de un PNG que
     * es opaco a todos los efectos.
     */
    function ds_marco_ventana_alfa(\GdImage $im): ?array
    {
        $W = imagesx($im);
        $H = imagesy($im);

        $minx = $W; $maxx = -1; $miny = $H; $maxy = -1; $n = 0; $vistos = 0;

        for ($y = 0; $y < $H; $y += 2) {
            for ($x = 0; $x < $W; $x += 2) {
                $vistos++;
                if (!ds_marco_es_hueco(imagecolorat($im, $x, $y))) { continue; }
                $n++;
                if ($x < $minx) { $minx = $x; }
                if ($x > $maxx) { $maxx = $x; }
                if ($y < $miny) { $miny = $y; }
                if ($y > $maxy) { $maxy = $y; }
            }
        }

        if ($vistos === 0 || $n / $vistos < 0.01 || $maxx < 0) { return null; }

        return ['x' => $minx, 'y' => $miny, 'x2' => $maxx, 'y2' => $maxy];
    }

    /**
     * El rectángulo del damero PINTADO, en píxeles, o null si no hay ninguno.
     *
     *
     * POR QUÉ SE BUSCA EL PATRÓN Y NO EL COLOR
     *
     * El primer intento marcaba todo píxel gris claro del archivo y agujereó
     * medio dibujo: los balones, los globos blancos y las líneas de la cancha
     * caen en el mismo rango de gris. El color no distingue nada.
     *
     * Lo que sí distingue a un damero es que ocupa un rectángulo grande y
     * macizo. Así que se mide, fila a fila, la RACHA más larga de grises
     * neutros seguidos, y se toman por ventana sólo las filas cuya racha pasa
     * de la mitad del ancho de la imagen. Un globo blanco no produce una racha
     * de 500 px; una ventana sí.
     */
    function ds_marco_ventana_damero(\GdImage $im): ?array
    {
        /*
        | SE BUSCA SOBRE UNA COPIA REDUCIDA
        |
        | La primera versión recorría los píxeles del marco a tamaño real
        | llamando a ds_marco_es_damero() en cada uno. Sobre un marco de 941 x
        | 1672 eso son 1.573.352 llamadas, y con Xdebug cargado —que es como
        | corre este servidor: xdebug.mode=develop— cada llamada cuesta lo
        | suyo. Medido: 7,58 segundos de los 8,1 que tardaba el análisis
        | entero. Guardar un marco agotaba los 120 segundos de PHP y el
        | administrador veía un error fatal.
        |
        | La ventana es un rectángulo grande y macizo, así que buscarla a un
        | cuarto de escala la encuentra igual con dieciséis veces menos
        | píxeles. El rectángulo hallado se devuelve en coordenadas del marco
        | REAL, y el recorte sigue haciéndose a tamaño completo: lo que se
        | reduce es la búsqueda, no el resultado.
        |
        | Se reescala con IMG_NEAREST_NEIGHBOUR y no con el filtro suave: al
        | promediar, un gris del damero mezclado con el dibujo de al lado da
        | un color que ya no es neutro, y la prueba lo descartaría. El vecino
        | más cercano conserva los tonos exactos.
        */
        $Worig = imagesx($im);
        $Horig = imagesy($im);

        $lado   = max($Worig, $Horig);
        $escala = $lado > 700 ? 700 / $lado : 1.0;

        $chico = $escala < 1.0
            ? imagescale($im, (int) round($Worig * $escala), (int) round($Horig * $escala),
                         IMG_NEAREST_NEIGHBOUR)
            : $im;

        if (!$chico) { $chico = $im; $escala = 1.0; }

        $r = ds_marco_damero_en($chico, $escala);

        if ($chico !== $im) { imagedestroy($chico); }

        return $r;
    }

    /**
     * El barrido propiamente dicho, sobre la imagen que se le dé.
     *
     * La comprobación de «gris neutro claro» va EN LÍNEA y no llamando a
     * ds_marco_es_damero(): en el bucle interno se ejecuta una vez por píxel,
     * y una llamada a función por píxel es justamente lo que hacía inviable
     * la versión anterior. La condición es la misma; si se cambia una, hay
     * que cambiar la otra.
     *
     * $escala es el factor con que se redujo la imagen, para devolver el
     * rectángulo en coordenadas del marco original.
     */
    function ds_marco_damero_en(\GdImage $im, float $escala): ?array
    {
        $W = imagesx($im);
        $H = imagesy($im);

        /*
        | SE BUSCA LA ALTERNANCIA, NO EL BRILLO
        |
        | La versión anterior daba por damero todo gris neutro entre 155 y 250.
        | Sobre el marco de Caballito Zeballos eso salió catastróficamente mal:
        |
        |   · el damero de ese marco está pintado en tonos CASI BLANCOS
        |     —medido: r entre 250 y 255—, o sea FUERA del rango;
        |   · y su fondo general es un hueso de r 247..249, o sea DENTRO.
        |
        | Resultado: no reconoció la ventana y sí reconoció el fondo, y el
        | recorte agujereó dos tercios del marco. Quedó inservible y encima
        | era la plantilla activa.
        |
        | Lo que distingue a un damero de un fondo no es el tono: es que
        | ALTERNA. Un fondo, por claro u oscuro que sea, es liso. Así que se
        | cuentan los cambios de tono entre vecinos dentro de una racha de
        | píxeles neutros. Medido sobre ese mismo marco: las filas de la
        | ventana dan entre 97 y 118 alternancias, y las del fondo entre 9 y
        | 41. La separación es de más del doble.
        */
        $minFilas = max(8, (int) round(50 * $escala));

        /* Una celda de damero rara vez pasa de 25 px en el marco real; en la
           copia reducida, de 25*escala. Sirve para exigir «al menos una
           alternancia cada tantos píxeles» y no un número absoluto. */
        $celdaMax = max(4, (int) round(25 * $escala));

        /*
        | El discriminador es la VARIACIÓN LOCAL, medida.
        |
        | Sobre el marco que rompió la versión anterior: dentro de la ventana,
        | el brillo oscila 56..78 dentro de una franja de ±8 px; en el fondo,
        | 1..19. Entre medias hay un abismo, así que el umbral de 25 no está
        | elegido a ojo sino puesto en mitad de un hueco medido.
        |
        | Se hace así y no contando cambios de tono seguidos porque en ese
        | marco el fondo y la ventana son AMBOS neutros y claros: una racha de
        | «píxeles neutros» no se corta en el borde de la ventana y abarcaba la
        | fila entera. La variación local sí se corta ahí.
        */
        $radio  = max(2, (int) round(8 * $escala));
        $umbral = 25;

        $filas = [];
        for ($y = 0; $y < $H; $y++) {

            /* La fila entera de una vez: un imagecolorat por píxel y no uno
               por cada comparación de la ventana deslizante. */
            $rr = []; $neu = [];
            for ($x = 0; $x < $W; $x++) {
                $c = imagecolorat($im, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $max = $r > $g ? ($r > $b ? $r : $b) : ($g > $b ? $g : $b);
                $min = $r < $g ? ($r < $b ? $r : $b) : ($g < $b ? $g : $b);
                $rr[$x]  = $r;
                /* Neutro y no oscuro. El tono NO decide: hay dameros casi
                   blancos (250..255) y clásicos (175..245). */
                $neu[$x] = ($max - $min <= 10) && $r >= 150;
            }

            $ini = -1; $mejor = 0; $mejorIni = 0; $mejorFin = 0;

            for ($x = 0; $x <= $W; $x++) {

                $vale = false;
                if ($x < $W && $neu[$x]) {
                    $a = $x - $radio; if ($a < 0)   { $a = 0; }
                    $z = $x + $radio; if ($z >= $W) { $z = $W - 1; }
                    $mx = 0; $mn = 255;
                    for ($i = $a; $i <= $z; $i++) {
                        if ($rr[$i] > $mx) { $mx = $rr[$i]; }
                        if ($rr[$i] < $mn) { $mn = $rr[$i]; }
                    }
                    $vale = ($mx - $mn) >= $umbral;
                }

                if ($vale) {
                    if ($ini < 0) { $ini = $x; }
                } else {
                    if ($ini >= 0) {
                        $largo = $x - $ini;
                        if ($largo > $mejor && $largo >= $celdaMax * 4) {
                            $mejor = $largo; $mejorIni = $ini; $mejorFin = $x;
                        }
                    }
                    $ini = -1;
                }
            }

            /*
            | La ventana ocupa al menos un octavo del ancho.
            |
            | Fue un cuarto y se bajo: las filas de las ESQUINAS REDONDEADAS
            | tienen la racha mas corta que el resto, y con un cuarto se
            | quedaban fuera. El resultado era una franja de damero sin
            | cortar bajo la foto. Lo que impide que este umbral mas laxo
            | admita cualquier cosa no es el umbral: es exigir que las filas
            | vayan SEGUIDAS y la salvaguarda de mas abajo.
            */
            if ($mejor >= $W * 0.12) {
                $filas[$y] = [$mejorIni, $mejorFin];
            }
        }

        if (count($filas) < $minFilas) { return null; }

        /*
        | Sólo el bloque de filas SEGUIDAS más largo.
        |
        | Sin esto, dos manchas alternantes lejanas —el damero arriba y una
        | trama de puntos abajo— se funden en un rectángulo que las abarca a
        | las dos y a todo lo que hay en medio.
        */
        $ys = array_keys($filas);
        sort($ys);

        $mejorIniY = $ys[0]; $mejorLargo = 1;
        $iniY = $ys[0]; $largo = 1;
        for ($i = 1, $n = count($ys); $i < $n; $i++) {
            if ($ys[$i] - $ys[$i - 1] <= 3) {
                $largo++;
            } else {
                $iniY = $ys[$i]; $largo = 1;
            }
            if ($largo > $mejorLargo) { $mejorLargo = $largo; $mejorIniY = $iniY; }
        }

        $ys = array_values(array_filter($ys,
            static fn($y) => $y >= $mejorIniY && $y < $mejorIniY + $mejorLargo + 3));

        if (count($ys) < $minFilas) { return null; }

        $filas = array_intersect_key($filas, array_flip($ys));

        /*
        | De vuelta a las coordenadas del marco real.
        |
        | Se redondea HACIA FUERA —floor en el inicio, ceil en el final— para
        | que el rectángulo devuelto cubra la ventana entera. Redondeando
        | hacia dentro quedaría una orla de damero sin cortar en los cuatro
        | bordes, que es exactamente lo que se veía como líneas grises sobre
        | la foto la primera vez que esto se hizo a mano.
        */
        $f = $escala > 0 ? 1 / $escala : 1;

        $x  = min(array_column($filas, 0));
        $x2 = max(array_column($filas, 1));
        $y  = min($ys);
        $y2 = max($ys);

        /*
        | LA SALVAGUARDA
        |
        | Una ventana de foto no ocupa casi todo el marco. Si la detección
        | dice que sí, la detección está mal, y hacerle caso significa borrar
        | el dibujo del cliente.
        |
        | Esto no es prudencia teórica: la detección rota devolvió «x 0..941,
        | y 592..1671» sobre un marco de 941x1672 —el ancho ENTERO y dos
        | tercios del alto— y se ejecutó sin rechistar. Con este límite se
        | habría rechazado y el marco seguiría intacto.
        |
        | Ante la duda no se recorta: el mensaje de «no reconozco la ventana»
        | cuesta un minuto de trabajo, y rehacer un marco destruido cuesta el
        | trabajo de un diseñador.
        */
        $anchoPct = ($x2 - $x) / $W;
        $altoPct  = ($y2 - $y) / $H;

        if ($anchoPct > 0.90 || $altoPct > 0.80) { return null; }

        return [
            'x'  => (int) floor($x  * $f),
            'y'  => (int) floor($y  * $f),
            'x2' => (int) ceil($x2 * $f),
            'y2' => (int) ceil($y2 * $f),
        ];
    }

    /**
     * Vuelve transparente el damero de dentro del rectángulo y guarda el PNG.
     *
     * Se corta dentro del rectángulo completo y no fila a fila: cortar sólo la
     * racha más larga de cada fila deja las celdas de los extremos, que se ven
     * como líneas horizontales tenues sobre la foto. Como la condición sigue
     * siendo «gris neutro claro» y el rectángulo ya está acotado a la ventana,
     * lo que hay fuera del damero dentro de ese rectángulo —el borde redondeado
     * del marco, por ejemplo— no cumple la condición y se queda.
     *
     * Devuelve cuántos píxeles se abrieron.
     */
    function ds_marco_abrir_ventana(\GdImage $im, array $v, string $destino): int
    {
        imagealphablending($im, false);
        imagesavealpha($im, true);

        $nada = imagecolorallocatealpha($im, 0, 0, 0, 127);
        $n = 0;

        /*
        | El rectángulo se acota a la imagen antes de recorrerlo.
        |
        | Viene de una búsqueda hecha sobre una copia reducida y devuelto con
        | redondeo hacia fuera, así que su esquina puede caer un píxel más
        | allá del borde. imagecolorat() fuera de límites emite un aviso por
        | cada píxel, y con una ventana de medio millón eso llena el registro
        | y tira el guardado.
        */
        $x0 = max(0, (int) $v['x']);
        $y0 = max(0, (int) $v['y']);
        $x1 = min(imagesx($im) - 1, (int) $v['x2']);
        $y1 = min(imagesy($im) - 1, (int) $v['y2']);

        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                /* Prueba en línea, como en el barrido: este bucle recorre
                   medio millón de píxeles y una llamada a función por píxel
                   es lo que agotaba el tiempo de PHP. */
                $c = imagecolorat($im, $x, $y);

                if ((($c >> 24) & 0x7F) > 100) { continue; }   /* ya es hueco */

                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;

                $max = $r > $g ? ($r > $b ? $r : $b) : ($g > $b ? $g : $b);
                $min = $r < $g ? ($r < $b ? $r : $b) : ($g < $b ? $g : $b);

                /*
                | La MISMA condición que usa el detector, sin tope superior.
                |
                | Antes el corte exigía r <= 250 mientras el detector aceptaba
                | hasta 255. En un damero de tonos casi blancos —el del marco
                | de Caballito Zeballos— eso dejaba a medias la ventana: se
                | quitaban las casillas oscuras y quedaban las claras, con un
                | moteado fino sobre la foto.
                |
                | Detectar y cortar tienen que mirar lo mismo. Aquí no hace
                | falta tope: el rectángulo ya viene acotado por una detección
                | verificada y por la salvaguarda, así que lo neutro que haya
                | dentro es la ventana, y si algo de fondo cae en el borde
                | redondeado da igual: ahí encima va la foto.
                */
                if ($max - $min <= 10 && $r >= 150) {
                    imagesetpixel($im, $x, $y, $nada);
                    $n++;
                }
            }
        }

        return imagepng($im, $destino, 6) ? $n : 0;
    }

    /**
     * Diagnóstico completo de un marco frente al rectángulo de la foto.
     *
     * Devuelve un array con:
     *   ok        bool    la foto se verá
     *   hueco     float   % del rectángulo de la foto que deja pasar la foto
     *   ancho/alto int    tamaño real del marco
     *   alfa      ?array  ventana transparente ya existente, en %
     *   damero    ?array  damero pintado detectado, en % (y en px, para cortar)
     *   error     string  motivo por el que no se pudo analizar
     */
    function ds_marco_analizar(string $ruta, array $rect): array
    {
        $base = [
            'ok' => false, 'hueco' => 0.0, 'ancho' => 0, 'alto' => 0,
            'alfa' => null, 'damero' => null, 'error' => '',
        ];

        $tam = @getimagesize($ruta);
        if ($tam === false || $tam[2] !== IMAGETYPE_PNG) {
            return ['error' => 'El archivo no es un PNG legible.'] + $base;
        }

        if ($tam[0] * $tam[1] > ds_marco_max_pixeles()) {
            return ['error' => 'El marco es de ' . $tam[0] . '×' . $tam[1] . ' píxeles. '
                             . 'El máximo admitido son 25 millones de píxeles.'] + $base;
        }

        $im = @imagecreatefrompng($ruta);
        if (!$im) {
            return ['error' => 'No se pudo abrir el PNG.'] + $base;
        }

        $W = imagesx($im);
        $H = imagesy($im);

        /** Un rectángulo en píxeles llevado a porcentaje del marco. */
        $aPct = static function (array $v) use ($W, $H): array {
            return [
                'x' => round($v['x'] / $W * 100, 3),
                'y' => round($v['y'] / $H * 100, 3),
                'w' => round(($v['x2'] - $v['x']) / $W * 100, 3),
                'h' => round(($v['y2'] - $v['y']) / $H * 100, 3),
            ];
        };

        $r = $base;
        $r['ancho'] = $W;
        $r['alto']  = $H;
        $r['hueco'] = ds_marco_hueco_en_rect($im, $rect);

        /* Un 2 % basta: significa que hay transparencia real ahí, no el borde
           suavizado de un PNG opaco. Por debajo, la foto no se vería. */
        $r['ok'] = $r['hueco'] >= 2.0;

        if (!$r['ok']) {
            $alfa = ds_marco_ventana_alfa($im);
            if ($alfa !== null) { $r['alfa'] = $aPct($alfa); }

            $dam = ds_marco_ventana_damero($im);
            if ($dam !== null) {
                $r['damero'] = $aPct($dam) + ['px' => $dam];
            }
        }

        imagedestroy($im);

        return $r;
    }
}
