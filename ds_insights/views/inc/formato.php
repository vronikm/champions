<?php
/*
| Presentación compartida por las vistas de Insights.
|
| Vive aparte porque la primera version definia ds_variacion() dentro de
| dashboard-view.php, y la vista financiera la usaba sin tenerla: habria
| dado un error fatal en cuanto alguien abriera «Financiero» sin pasar antes
| por el panel.
*/

if (!function_exists('ds_variacion')) {
    /**
     * Pinta la variacion de una tarjeta, o un guion si no se puede calcular.
     *
     * El guion NO es un cero. Cuando el periodo anterior fue nada, no hay
     * variacion porcentual que mostrar: pintar «+100 %» seria inventar un
     * dato con aspecto de medicion.
     */
    function ds_variacion(?float $v, string $sufijo = '%'): string
    {
        if ($v === null) {
            return '<span class="text-muted small" title="Sin periodo anterior comparable">—</span>';
        }
        $sube   = $v >= 0;
        $color  = $sube ? 'text-success' : 'text-danger';
        $flecha = $sube ? 'up' : 'down';

        return sprintf(
            '<span class="%s small"><i class="fas fa-arrow-%s"></i> %s%s%s</span>',
            $color, $flecha, $sube ? '+' : '', number_format($v, 1), $sufijo
        );
    }
}

if (!function_exists('ds_variacion_sobre_color')) {
    /**
     * La misma variacion, pero para leerse SOBRE UN FONDO DE COLOR.
     *
     * POR QUE HACE FALTA UNA SEGUNDA VERSION
     *
     * ds_variacion() pinta text-success o text-danger, que funcionan sobre
     * el fondo blanco de .info-box. Las tarjetas pasaron a .small-box, que
     * lleva fondo de color: verde de Bootstrap sobre el verde de la tarjeta
     * es ilegible, y rojo sobre rojo, peor.
     *
     * Aqui el sentido NO lo lleva el color sino la flecha y el signo, que
     * es ademas lo correcto segun WCAG 1.4.1: no fiar una informacion
     * unicamente al color. El texto va en blanco, que es el que la propia
     * tarjeta ya usa y esta medido contra los cuatro fondos.
     */
    function ds_variacion_sobre_color(?float $v, string $sufijo = '%'): string
    {
        if ($v === null) {
            return '<span class="opacity-75" title="Sin periodo anterior comparable">—</span>';
        }
        $sube   = $v >= 0;
        $flecha = $sube ? 'up' : 'down';

        return sprintf(
            '<span><i class="fas fa-arrow-%s" aria-hidden="true"></i> %s%s%s</span>',
            $flecha, $sube ? '+' : '', number_format($v, 1), $sufijo
        );
    }
}

if (!function_exists('ds_botones_exportar')) {
    /**
     * Botones de exportación de un reporte, o nada si el usuario no puede.
     *
     * Ocultarlos no es la protección —esa está en exportar-view.php, que
     * comprueba puede_exportar() antes de escribir un solo byte—. Es que
     * ofrecer un botón que lleva a un 403 no informa de nada: sólo enseña
     * qué existe a quien no debería saberlo.
     *
     * @param string $reporte clave en el catálogo del controlador
     * @param string $vista   vista sobre la que se pide el permiso
     * @param array  $p       periodo, para que el archivo salga del mismo recorte
     */
    function ds_botones_exportar(string $reporte, string $vista, array $p): string
    {
        if (!puede_exportar($vista)) { return ''; }

        $qs = 'reporte=' . urlencode($reporte)
            . '&desde=' . urlencode($p['desde'])
            . '&hasta=' . urlencode($p['hasta']);

        return '<span class="btn-group btn-group-sm" role="group" aria-label="Exportar">'
             . '<a href="' . APP_URL . 'exportar/?' . $qs . '&formato=csv"'
             . ' class="btn btn-outline-secondary" title="Descargar en CSV, abre en Excel">'
             . '<i class="fas fa-file-csv me-1"></i>CSV</a>'
             . '<a href="' . APP_URL . 'exportar/?' . $qs . '&formato=pdf"'
             . ' class="btn btn-outline-secondary" title="Descargar en PDF">'
             . '<i class="fas fa-file-pdf me-1"></i>PDF</a>'
             . '</span>';
    }
}
