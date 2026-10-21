<?php
/*
|--------------------------------------------------------------------------
| Piezas de interfaz comunes a los módulos administrativos
|--------------------------------------------------------------------------
| Un estándar escrito sólo en la documentación se incumple a la tercera
| pantalla. Aquí se convierte en funciones: si todas las vistas piden sus
| botones a ds_boton()/ds_acciones(), no pueden salirse del patrón.
|
| Reglas que imponen estas funciones:
|   · Las acciones van a la derecha, en el mismo orden: salir primero,
|     confirmar al final.
|   · Todos los botones de acción tienen el mismo tamaño (el de Bootstrap
|     por defecto) y el mismo ancho mínimo.
|   · Cada acción tiene un icono asignado, siempre el mismo en todo el
|     ecosistema. Ver ds_icono().
|
| LOS ICONOS LLEVAN «me-1», LA UTILIDAD DE BOOTSTRAP 5
|
| Durante la migración estuvieron un tiempo con «mr-1 me-1», las dos a la
| vez, porque este archivo lo usan los CUATRO módulos y Basketball seguía
| en Bootstrap 4 mientras los otros tres ya estaban en el 5. Cada versión
| entendía la suya e ignoraba la otra. Ya no hace falta: los cuatro corren
| Bootstrap 5.
*/

if (!function_exists('ds_icono')) {

    /**
     * Icono canónico de cada acción.
     *
     * El objetivo es que "guardar" se dibuje igual en Core, en Arena y en
     * Basketball. Si una acción no está en la tabla se devuelve un círculo
     * neutro, que llama la atención sin romper la pantalla.
     */
    function ds_icono(string $accion): string
    {
        $iconos = [
            /* Navegación */
            'volver'    => 'fas fa-arrow-left',
            'ver'       => 'fas fa-eye',
            'detalle'   => 'fas fa-list',
            /* Escritura */
            'nuevo'     => 'fas fa-plus',
            'guardar'   => 'fas fa-save',
            'editar'    => 'fas fa-pen',
            'eliminar'  => 'fas fa-trash',
            'quitar'    => 'fas fa-times',
            /* Estado */
            'bloqueado' => 'fas fa-lock',
            'aviso'     => 'fas fa-info-circle',
            'alerta'    => 'fas fa-exclamation-triangle',
            /* Documentos y archivos */
            'imprimir'  => 'fas fa-print',
            'descargar' => 'fas fa-download',
            'subir'     => 'fas fa-upload',
            'enviar'    => 'fas fa-paper-plane',
            'probar'    => 'fas fa-vial',
            /* Dominio deportivo y economico */
            'vincular'  => 'fas fa-link',
            'pago'      => 'fas fa-dollar-sign',
            'factura'   => 'fas fa-file-invoice-dollar',
            'equipo'    => 'fas fa-users',
        ];

        return $iconos[$accion] ?? 'far fa-circle';
    }

    /**
     * Botón con el icono y el tamaño del estándar.
     *
     * $opciones admite: href, type, class, title, name, value, id, target,
     * data (array de atributos data-*), disabled.
     */
    function ds_boton(string $accion, string $texto, array $opciones = []): string
    {
        $estilo = $opciones['estilo'] ?? 'secondary';
        $clases = trim('btn btn-' . $estilo . ' ' . ($opciones['class'] ?? ''));

        $attr = '';
        foreach (['title', 'id', 'name', 'value', 'target'] as $a) {
            if (!empty($opciones[$a])) {
                $attr .= ' ' . $a . '="' . htmlspecialchars((string)$opciones[$a], ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        foreach ($opciones['data'] ?? [] as $clave => $valor) {
            $attr .= ' data-' . $clave . '="' . htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8') . '"';
        }
        if (!empty($opciones['disabled'])) {
            $attr .= ' disabled';
        }

        $contenido = '<i class="' . ds_icono($accion) . ' me-1"></i> '
                   . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

        if (!empty($opciones['href'])) {
            return '<a href="' . htmlspecialchars((string)$opciones['href'], ENT_QUOTES, 'UTF-8')
                 . '" class="' . $clases . '"' . $attr . '>' . $contenido . '</a>';
        }

        $tipo = $opciones['type'] ?? 'submit';
        return '<button type="' . $tipo . '" class="' . $clases . '"' . $attr . '>' . $contenido . '</button>';
    }

    /**
     * Pie de formulario: Volver y Guardar, siempre a la derecha.
     *
     * $nota es un texto que queda a la izquierda sin desplazar los botones;
     * sirve para avisos del tipo «Su rol sólo puede consultar».
     */
    function ds_acciones_form(string $urlVolver, array $opciones = []): string
    {
        $guardar = $opciones['guardar'] ?? 'Guardar';
        $volver  = $opciones['volver']  ?? 'Volver';
        $nota    = trim((string)($opciones['nota'] ?? ''));

        $html = '<div class="card-footer ds-acciones">';

        if ($nota !== '') {
            $html .= '<span class="ds-acciones__nota">' . $nota . '</span>';
        }

        /* Volver.
           Con $urlVolver vacía se retrocede en el historial. No es lo
           ideal —el destino depende de por dónde se llegó— pero es lo que
           hacían las pantallas de Basketball con btn_back.php, y cambiarlo
           por una URL fija exige decidir el listado de cada una. Se
           conserva el comportamiento y se unifica sólo el aspecto. */
        /* Hay pantallas que no se "vuelven": se abren con target="_blank"
           desde un listado y la salida natural es cerrar la pestaña. Antes
           quedaban fuera del estándar por eso. Con salirJs el botón es el
           mismo —mismo sitio, mismo tamaño, mismo icono— y sólo cambia lo
           que hace al pulsarlo. */
        $salirJs = trim((string)($opciones['salirJs'] ?? ''));

        if ($salirJs !== '') {
            $html .= str_replace('<button type="button"',
                '<button type="button" onclick="'
                    . htmlspecialchars($salirJs, ENT_QUOTES, 'UTF-8') . ';return false;"',
                ds_boton('volver', $volver, ['type' => 'button', 'estilo' => 'secondary']));
        } elseif ($urlVolver === '') {
            /* El onclick va en el propio botón en vez de en un script
               aparte: así la función es autosuficiente y no hay que
               acordarse de cargar nada en cada una de las 86 vistas. */
            $html .= str_replace('<button type="button"',
                '<button type="button" onclick="history.back();return false;"',
                ds_boton('volver', $volver, ['type' => 'button', 'estilo' => 'secondary']));
        } else {
            $html .= ds_boton('volver', $volver, ['href' => $urlVolver, 'estilo' => 'secondary']);
        }

        /* Limpiar: un reset del formulario. Va entre Volver y Guardar
           porque no confirma nada, pero tampoco abandona la pantalla. */
        if (($opciones['limpiar'] ?? false) === true) {
            $html .= ds_boton('quitar', 'Limpiar', ['type' => 'reset', 'estilo' => 'secondary']);
        }

        if (($opciones['soloLectura'] ?? false) !== true) {
            $html .= ds_boton('guardar', $guardar, ['estilo' => 'primary', 'type' => 'submit']);
        }

        return $html . '</div>';
    }

    /**
     * Botón de una barra de búsqueda.
     *
     * Va aparte de ds_boton() porque vive dentro de un input-group y
     * necesita form-control para estirarse a la altura de los campos. Sin
     * eso queda un botón flotando a media altura junto a los selectores.
     */
    function ds_boton_buscar(string $texto = 'Buscar'): string
    {
        /* w-100 da el ancho; form-control daba ademas el alto de una caja de
               texto, que no es el de un boton. */
            return '<button type="submit" class="btn btn-primary w-100">'
             . '<i class="' . ds_icono('ver') . ' me-1"></i> '
             . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</button>';
    }

    /** Celda de acciones de una tabla: iconos del mismo tamaño, a la derecha. */
    function ds_acciones_tabla(string $contenido): string
    {
        return '<td class="ds-tabla-acciones">' . $contenido . '</td>';
    }

    /** Botón de icono para una fila de tabla. */
    function ds_boton_fila(string $accion, string $titulo, array $opciones = []): string
    {
        $estilo = $opciones['estilo'] ?? 'outline-secondary';
        $attr   = ' title="' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '"';

        foreach (['id', 'target'] as $a) {
            if (!empty($opciones[$a])) {
                $attr .= ' ' . $a . '="' . htmlspecialchars((string)$opciones[$a], ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        $icono = '<i class="' . ds_icono($accion) . '"></i>';

        if (!empty($opciones['href'])) {
            return '<a href="' . htmlspecialchars((string)$opciones['href'], ENT_QUOTES, 'UTF-8')
                 . '" class="btn btn-sm btn-' . $estilo . '"' . $attr . '>' . $icono . '</a>';
        }

        return '<button type="submit" class="btn btn-sm btn-' . $estilo . '"' . $attr . '>' . $icono . '</button>';
    }

    /** Hueco del ancho de un botón, para que las filas no se descuadren. */
    function ds_hueco(string $titulo = ''): string
    {
        return '<span class="ds-hueco" title="' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '">'
             . '<i class="' . ds_icono('bloqueado') . '"></i></span>';
    }

    /**
     * Ancho del contenedor de un formulario.
     *
     * Se centraliza para que todas las fichas midan lo mismo: antes había
     * col-lg-8, col-lg-9 y col-lg-7 según quién escribiera la vista.
     */
    function ds_ancho_form(): string
    {
        return 'col-lg-9';
    }

    /**
     * Identificador obligatorio tomado de la URL.
     *
     * Muchas pantallas se abren con un id detrás (pagosRecibo/1054/) y
     * daban por hecho que estaría. Al entrar sin él, el id vacío llegaba a
     * la consulta, la cláusula quedaba truncada y la página moría
     * mostrando el SQL y la ruta del servidor: además de romperse, contaba
     * de más a quien mirara.
     *
     * Aquí se corta antes: si no hay un id numérico se vuelve al listado
     * de donde se venía, sin ejecutar nada.
     *
     * Devuelve el id como cadena de dígitos; conviértalo con (int) al
     * pasarlo a la consulta.
     */
    function ds_id_de_url(array $url, int $posicion, string $destino): string
    {
        $valor = isset($url[$posicion]) ? trim((string)$url[$posicion]) : '';

        /* El cero se descarta como el vacío: ninguna tabla lo usa como
           clave, y colarlo sólo lleva a una consulta sin resultados que la
           vista interpreta como registro existente. */
        if ($valor === '' || !ctype_digit($valor) || (int)$valor === 0) {
            header('Location: ' . $destino);
            exit();
        }

        return $valor;
    }
}
