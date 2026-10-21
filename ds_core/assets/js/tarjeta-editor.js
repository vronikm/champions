/*
| Editor de la tarjeta de cumpleaños
| ===========================================================================
| Arrastrar las cajas sobre el marco y que los números se escriban solos, o
| escribir los números y que las cajas se muevan. Los dos sentidos, porque los
| dos se usan: se coloca a ojo y luego se afina de tres en tres décimas.
|
|
| TODO EN PORCENTAJE, NUNCA EN PÍXELES
|
| El lienzo mide lo que mida la pantalla, y el marco puede ser de 900 px o de
| 3000. Si esto trabajara en píxeles, cada valor guardado dependería del ancho
| del navegador de quien lo configuró. Se convierte a % en el momento de
| soltar, contra el tamaño real del lienzo.
|
|
| POR QUÉ NO SE USA UNA LIBRERÍA
|
| Arrastrar y redimensionar tres cajas son cuarenta líneas. Traer interact.js
| para esto son 60 KB, una dependencia que actualizar y una hoja de estilos
| más que cargar en una pantalla de administración que se abre dos veces al
| año.
*/

(function () {
    'use strict';

    var lienzo = document.getElementById('lienzo');
    if (!lienzo) { return; }

    /* Qué campo del formulario corresponde a cada lado de cada caja. Las de
       texto no tienen alto propio: su altura la decide el tamaño de letra, no
       una caja, así que el tirador sólo cambia el ancho. */
    var MAPA = {
        foto:   { x: 'foto_x',   y: 'foto_y',   w: 'foto_ancho',   h: 'foto_alto' },
        nombre: { x: 'nombre_x', y: 'nombre_y', w: 'nombre_ancho', h: null },
        edad:   { x: 'edad_x',   y: 'edad_y',   w: 'edad_ancho',   h: null }
    };

    var campo = function (nombre) { return document.getElementById(nombre); };
    var acotar = function (v) { return Math.max(0, Math.min(100, v)); };

    /* Los números al DOM. */
    function pintarDesdeCampos(area) {
        var m = MAPA[area];
        var caja = lienzo.querySelector('[data-area="' + area + '"]');
        if (!caja || !m) { return; }

        caja.style.left  = acotar(parseFloat(campo(m.x).value) || 0) + '%';
        caja.style.top   = acotar(parseFloat(campo(m.y).value) || 0) + '%';
        caja.style.width = acotar(parseFloat(campo(m.w).value) || 0) + '%';
        if (m.h) {
            caja.style.height = acotar(parseFloat(campo(m.h).value) || 0) + '%';
        }
    }

    /* El DOM a los números. Tres decimales: es lo que guarda la base, y
       redondear a entero movería la caja al soltarla. */
    function escribirCampos(area, izq, arriba, ancho, alto) {
        var m = MAPA[area];
        campo(m.x).value = acotar(izq).toFixed(3);
        campo(m.y).value = acotar(arriba).toFixed(3);
        campo(m.w).value = acotar(ancho).toFixed(3);
        if (m.h && alto !== null) { campo(m.h).value = acotar(alto).toFixed(3); }
    }

    Object.keys(MAPA).forEach(function (area) {
        var m = MAPA[area];
        [m.x, m.y, m.w, m.h].forEach(function (c) {
            if (!c) { return; }
            var el = campo(c);
            if (el) { el.addEventListener('input', function () { pintarDesdeCampos(area); }); }
        });
    });

    /*----------  Arrastrar y redimensionar  ----------*/
    var activo = null;

    lienzo.addEventListener('pointerdown', function (e) {
        var caja = e.target.closest('.caja-area');
        if (!caja) { return; }

        var r = lienzo.getBoundingClientRect();
        var c = caja.getBoundingClientRect();

        activo = {
            caja: caja,
            area: caja.getAttribute('data-area'),
            /* Redimensionar si se agarró el tirador; mover en cualquier otro
               punto de la caja. */
            modo: e.target.classList.contains('tirador') ? 'tamano' : 'mover',
            soloAncho: e.target.classList.contains('tirador-ancho'),
            lienzoW: r.width,
            lienzoH: r.height,
            /* El desfase del puntero dentro de la caja: sin él, la caja salta
               para poner su esquina bajo el cursor en cuanto se toca. */
            dx: e.clientX - c.left,
            dy: e.clientY - c.top,
            x0: c.left - r.left,
            y0: c.top - r.top,
            w0: c.width,
            h0: c.height,
            cx: e.clientX,
            cy: e.clientY
        };

        caja.setPointerCapture(e.pointerId);
        e.preventDefault();
    });

    lienzo.addEventListener('pointermove', function (e) {
        if (!activo) { return; }

        var r = lienzo.getBoundingClientRect();
        var a = activo;

        if (a.modo === 'mover') {
            var x = e.clientX - r.left - a.dx;
            var y = e.clientY - r.top  - a.dy;
            escribirCampos(a.area,
                x / a.lienzoW * 100,
                y / a.lienzoH * 100,
                a.w0 / a.lienzoW * 100,
                a.h0 / a.lienzoH * 100);
        } else {
            /* Un ancho o alto de cero deja la caja invisible y sin forma de
               volver a agarrarla: se guarda un mínimo del 2 %. */
            var w = Math.max(a.lienzoW * 0.02, a.w0 + (e.clientX - a.cx));
            var h = a.soloAncho ? a.h0 : Math.max(a.lienzoH * 0.02, a.h0 + (e.clientY - a.cy));
            escribirCampos(a.area,
                a.x0 / a.lienzoW * 100,
                a.y0 / a.lienzoH * 100,
                w / a.lienzoW * 100,
                h / a.lienzoH * 100);
        }

        pintarDesdeCampos(a.area);
    });

    var soltar = function () { activo = null; };
    lienzo.addEventListener('pointerup', soltar);
    lienzo.addEventListener('pointercancel', soltar);

    /*
    | El teclado también mueve la caja seleccionada.
    |
    | No es un extra: sin esto, ajustar tres décimas exige el ratón o escribir
    | en el campo, y quien no puede usar un ratón se queda fuera del editor
    | entero.
    */
    lienzo.querySelectorAll('.caja-area').forEach(function (caja) {
        caja.tabIndex = 0;
        caja.setAttribute('role', 'application');
        caja.setAttribute('aria-label',
            'Área de ' + caja.getAttribute('data-area') + '. Use las flechas para moverla.');

        caja.addEventListener('keydown', function (e) {
            var paso = e.shiftKey ? 1 : 0.1;
            var m = MAPA[caja.getAttribute('data-area')];
            var dx = 0, dy = 0;

            if (e.key === 'ArrowLeft')  { dx = -paso; }
            else if (e.key === 'ArrowRight') { dx = paso; }
            else if (e.key === 'ArrowUp')    { dy = -paso; }
            else if (e.key === 'ArrowDown')  { dy = paso; }
            else { return; }

            e.preventDefault();
            campo(m.x).value = acotar((parseFloat(campo(m.x).value) || 0) + dx).toFixed(3);
            campo(m.y).value = acotar((parseFloat(campo(m.y).value) || 0) + dy).toFixed(3);
            pintarDesdeCampos(caja.getAttribute('data-area'));
        });
    });

    /*
    | Al elegir un marco nuevo se enseña ese, sin esperar a guardar.
    |
    | Si no, se sube el archivo, se guarda, y sólo entonces se descubre que las
    | cajas están descolocadas para el marco nuevo. Verlo antes permite
    | recolocarlas en la misma pasada.
    */
    var entrada = document.getElementById('plantilla_marco');
    var img = document.getElementById('marcoImg');
    if (entrada && img) {
        entrada.addEventListener('change', function () {
            var f = this.files && this.files[0];
            if (!f) { return; }
            var url = URL.createObjectURL(f);
            img.src = url;
            img.addEventListener('load', function () {
                URL.revokeObjectURL(url);
                /* La proporción del lienzo se rehace con la del marco nuevo:
                   si no, las cajas caerían sobre una forma que ya no es. */
                if (img.naturalWidth && img.naturalHeight) {
                    lienzo.style.aspectRatio = img.naturalWidth + '/' + img.naturalHeight;
                }
            }, { once: true });
        });
    }
})();
