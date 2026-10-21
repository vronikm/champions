/*
|--------------------------------------------------------------------------
| Gráficos de DigiSports Insights
|--------------------------------------------------------------------------
| Envuelve ApexCharts para resolver dos cosas que, hechas en cada vista, se
| harían distinto en cada vista.
|
|
| 1 · LA PALETA SALE DEL TEMA, NO DE UN LITERAL
|
| Un gris claro sobre fondo blanco es ilegible, y un gris oscuro sobre fondo
| oscuro también. Es el mismo defecto —color fijo, fondo del tema— que en
| este proyecto ya apareció en las tarjetas de horario, en el callout de
| AdminLTE y en las tablas de League.
|
|
| 2 · Y SE VUELVE A APLICAR AL CAMBIAR DE TEMA
|
| Esto es lo que no era evidente. El resto de la interfaz reacciona sola
| porque su color vive en CSS; un gráfico se dibuja UNA VEZ con los colores
| que había en ese momento y se queda así. Cambiar a claro dejaba el gráfico
| con la tinta del tema oscuro, ilegible, y nada fallaba: simplemente no se
| leía.
|
| Se observa el atributo data-bs-theme de <html>, que es lo que mueve el
| interruptor de AdminLTE, y se reaplican los colores.
*/

(function (global) {
    'use strict';

    /** Colores de texto y rejilla según el tema activo. */
    function paleta() {
        var attr = document.documentElement.getAttribute('data-bs-theme');
        /* Sin atributo, manda la preferencia del sistema: es el estado por
           omisión del interruptor de AdminLTE, no un caso raro. */
        var oscuro = attr === 'dark'
            || (!attr && window.matchMedia('(prefers-color-scheme: dark)').matches);

        return {
            oscuro:  oscuro,
            modo:    oscuro ? 'dark' : 'light',
            tinta:   oscuro ? '#94a3b8' : '#475569',
            rejilla: oscuro ? '#1f2c42' : '#e2e8f0',
        };
    }

    /*
    | Devuelve unas opciones NUEVAS con la paleta aplicada. No toca las que
    | recibe, y eso es lo que hace que el redibujado funcione.
    |
    | La primera version mutaba el objeto original. Al rehacer el grafico se
    | le pasaba el mismo objeto, que ApexCharts ya habia consumido y
    | normalizado, y el eje Y se quedaba con el color de la primera vez: la
    | leyenda cambiaba y las etiquetas de eje no. Se vio siguiendo el id del
    | SVG —que si cambiaba— junto al color del eje, que no.
    */
    function vestir(opciones, p) {
        var o = Object.assign({}, opciones);

        o.theme   = { mode: p.modo };
        o.chart   = Object.assign({ background: 'transparent', fontFamily: 'inherit' }, opciones.chart || {});
        o.grid    = Object.assign({}, opciones.grid || {}, { borderColor: p.rejilla });
        o.legend  = Object.assign({}, opciones.legend || {}, { labels: { colors: p.tinta } });
        o.tooltip = Object.assign({}, opciones.tooltip || {}, { theme: p.modo });

        ['xaxis', 'yaxis'].forEach(function (eje) {
            if (!opciones[eje]) { return; }
            o[eje] = Object.assign({}, opciones[eje], {
                labels: Object.assign({}, opciones[eje].labels || {},
                                      { style: { colors: p.tinta } })
            });
        });

        /* El total del centro de un donut lleva su propio color, y tambien
           hay que copiarlo en vez de escribirlo encima del original. */
        var pie = opciones.plotOptions && opciones.plotOptions.pie;
        var etiquetas = pie && pie.donut && pie.donut.labels;
        if (etiquetas) {
            var copia = {};
            ['total', 'name', 'value'].forEach(function (k) {
                if (etiquetas[k]) { copia[k] = Object.assign({}, etiquetas[k], { color: p.tinta }); }
            });
            o.plotOptions = Object.assign({}, opciones.plotOptions, {
                pie: Object.assign({}, pie, {
                    donut: Object.assign({}, pie.donut, {
                        labels: Object.assign({}, etiquetas, copia)
                    })
                })
            });
        }

        return o;
    }

    /**
     * Dibuja un gráfico que sobrevive al cambio de tema.
     *
     * @param {string} id       id del contenedor
     * @param {object} opciones opciones de ApexCharts, sin colores de tema
     */
    global.dsGrafico = function (id, opciones) {
        var nodo = document.getElementById(id);
        if (!nodo || typeof ApexCharts !== 'function') { return null; }

        var grafico = null;

        function pintar() {
            if (grafico) { grafico.destroy(); }
            grafico = new ApexCharts(nodo, vestir(opciones, paleta()));
            grafico.render();
        }

        pintar();

        /*
        | Al cambiar el tema se REHACE el grafico entero, no se actualiza.
        |
        | Se intentaron dos versiones mas suaves y ninguna sirve:
        |
        |   updateOptions campo por campo   se dejaba plotOptions, donde vive
        |                                   el color del total del donut: 2,56
        |   updateOptions con la paleta     ApexCharts no reaplica el estilo de
        |   entera, incluso con             las etiquetas de EJE. Se quedaban
        |   redrawPaths a true              con la tinta anterior: 2,04
        |
        | Rehacerlo cuesta un parpadeo minimo y es lo unico que deja TODOS los
        | textos con el color del tema. Un grafico ilegible no avisa: no falla
        | nada, simplemente no se lee.
        |
        | Se midieron los 16 textos del grafico, no una muestra: la primera
        | sonda miraba solo leyenda y donut y daba verde con los ejes rotos.
        */
        var observador = new MutationObserver(pintar);

        observador.observe(document.documentElement,
                           { attributes: true, attributeFilter: ['data-bs-theme'] });

        return grafico;
    };
})(window);
