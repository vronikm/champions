<?php
/*
|--------------------------------------------------------------------------
| Diseño del carnet
|--------------------------------------------------------------------------
| Qué elementos lleva el carnet y con qué medidas. Se edita en Core ›
| Carnets › Diseño del carnet y lo usan los dos PDF de Fútbol (lote del
| mes y carnet suelto) a través de app/lib/carnet_render.php.
|
| Se guarda en carnet_configuracion, una clave por ajuste con el prefijo
| «diseno_» (config_valor es VARCHAR(255): un JSON no cabría).
|
| Los valores por defecto reproducen exactamente el carnet de siempre: sin
| tocar nada, el PDF sale igual que antes de existir esta pantalla.
|
| Todo valor pasa por carnet_diseno_normalizar(): lo que llega del
| formulario o de la base se acota aquí, en un solo sitio.
*/

if (!function_exists('carnet_diseno_defecto')) {

    /** Ajustes y su valor por defecto (el carnet de siempre). */
    function carnet_diseno_defecto(): array
    {
        return [
            'franja_ancho'    => 20,          // mm, franja lateral con la imagen y el color del mes
            'franja_opacidad' => 50,          // %, color del mes sobre la imagen
            'franja_imagen'   => 1,           // imagen de fondo de la franja
            'linea'           => 1,           // línea decorativa vertical
            'logo'            => 1,
            'logo_tamano'     => 18,          // mm (lado mayor; el logo va a su proporción)
            'qr'              => 1,
            'titulo'          => 'DEPORTISTA',
            'ci'              => 1,
            'horario'         => 1,
            'edad'            => 1,
            'sede'            => 1,
            'correo'          => 1,
            'telefono'        => 1,
            'fecha'           => 1,           // «Impreso: dd/mm/aaaa»
        ];
    }

    /** Rangos de los ajustes numéricos: [mínimo, máximo]. */
    function carnet_diseno_rangos(): array
    {
        return ['franja_ancho' => [12, 30], 'franja_opacidad' => [0, 100], 'logo_tamano' => [10, 24]];
    }

    /**
     * Acota un diseño (del formulario o de la base) a valores válidos. Lo
     * que falte toma el valor por defecto.
     */
    function carnet_diseno_normalizar(array $entrada): array
    {
        $d = carnet_diseno_defecto();
        $rangos = carnet_diseno_rangos();
        foreach ($d as $clave => $defecto) {
            if (!array_key_exists($clave, $entrada)) { continue; }
            $v = $entrada[$clave];
            if (isset($rangos[$clave])) {
                [$min, $max] = $rangos[$clave];
                $d[$clave] = is_numeric($v) ? max($min, min($max, (int)round((float)$v))) : $defecto;
            } elseif ($clave === 'titulo') {
                /* Una línea en mayúsculas y sin etiquetas; vacío = el de siempre. */
                $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$v)));
                $t = mb_strtoupper(mb_substr($t, 0, 24, 'UTF-8'), 'UTF-8');
                $d[$clave] = $t !== '' ? $t : $defecto;
            } else {
                $d[$clave] = in_array($v, [1, '1', true, 'on', 'S'], true) ? 1 : 0;
            }
        }
        return $d;
    }

    /** Lee el diseño guardado con una conexión PDO (de Core o de Fútbol). */
    function carnet_diseno_leer(\PDO $con): array
    {
        $guardado = [];
        try {
            $st = $con->query("SELECT config_clave, config_valor FROM carnet_configuracion WHERE config_clave LIKE 'diseno\\_%'");
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $f) {
                $guardado[substr($f['config_clave'], 7)] = $f['config_valor'];
            }
        } catch (\Throwable $e) {
            /* Sin tabla todavía: el carnet de siempre. */
        }
        return carnet_diseno_normalizar($guardado);
    }
}
