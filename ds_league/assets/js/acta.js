/*
| Guardado automático del acta.
|
| POR QUÉ NO HAY BOTÓN
|
| El acta se llena en la mesa de anotación, en directo, mientras el partido
| corre. Pulsar un botón por jugador después de cada canasta es trabajo que
| la pantalla puede ahorrarse, y olvidarlo significa perder la línea entera.
|
| POR QUÉ NO SE RECARGA
|
| Antes cada guardado devolvía «recargar» y la página se pedía de nuevo: se
| perdía el foco, se cerraba el teclado del teléfono y había que volver a
| buscar la fila. Aquí sólo se repinta lo que cambió.
|
| QUÉ SE REPINTA Y DE DÓNDE SALE
|
| Las derivadas —PTS, REB, VAL—, los totales del equipo y los puntos del
| encabezado. Todos llegan CALCULADOS POR EL SERVIDOR: su fórmula vive en
| dsl_estadistica_tipo, y repetirla aquí sería una segunda copia que se
| quedaría atrás el día que alguien cambie la valoración.
*/
(function () {
    'use strict';

    var ESPERA = 700;      /* ms de calma antes de guardar */
    var temporizadores = {};
    var enVuelo = {};

    /*----------  Estado visible de cada fila  ----------*/

    function pintarEstado(fila, estado, detalle) {
        var caja = fila.querySelector('.ds-acta-estado');
        if (!caja) { return; }

        caja.dataset.estado = estado;
        caja.title = detalle || '';

        if (estado === 'guardando') {
            caja.innerHTML = '<i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>'
                           + '<span class="visually-hidden">Guardando</span>';
        } else if (estado === 'guardado') {
            caja.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>'
                           + '<span class="visually-hidden">Guardado</span>';
            /* Se apaga solo: un tick permanente en cada fila deja de
               significar nada cuando están todas marcadas. */
            clearTimeout(caja._apagar);
            caja._apagar = setTimeout(function () {
                if (caja.dataset.estado === 'guardado') {
                    caja.dataset.estado = 'listo';
                    caja.innerHTML = '';
                }
            }, 2500);
        } else if (estado === 'error') {
            caja.innerHTML = '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i>'
                           + '<span class="visually-hidden">No se guardó</span>';
        } else {
            caja.innerHTML = '';
        }
    }

    /*----------  Repintado con lo que responde el servidor  ----------*/

    function repintar(fila, datos) {
        /* Derivadas del jugador. */
        Object.keys(datos.derivadas || {}).forEach(function (codigo) {
            var celda = fila.querySelector('[data-calc="' + codigo + '"]');
            if (celda) { celda.textContent = datos.derivadas[codigo]; }
        });

        /* Totales del equipo, en el pie de SU tabla. */
        if (datos.equipo) {
            var pie = document.querySelector('tfoot[data-equipo="' + cssEscapar(datos.equipo) + '"]');
            if (pie) {
                Object.keys(datos.totales || {}).forEach(function (codigo) {
                    var celda = pie.querySelector('[data-total="' + codigo + '"]');
                    if (celda) { celda.textContent = datos.totales[codigo]; }
                });
            }
        }

        /* Puntos del acta del encabezado, para los dos equipos. */
        Object.keys(datos.puntosActa || {}).forEach(function (equipo) {
            var span = document.querySelector('[data-puntos-equipo="' + cssEscapar(equipo) + '"]');
            if (span) { span.textContent = datos.puntosActa[equipo]; }
        });
    }

    /* Los nombres de equipo llevan espacios y acentos y van dentro de un
       selector de atributo: hay que escapar las comillas o el selector se
       rompe con un nombre como «Club "X"». */
    function cssEscapar(valor) {
        return String(valor).replace(/["\\]/g, '\\$&');
    }

    /*----------  El envío  ----------*/

    function guardar(form) {
        var fila = document.querySelector('tr[data-form="' + form.id + '"]');
        if (!fila) { return; }

        /* Si ya hay una petición de esta fila en vuelo, se marca para
           repetir al terminar: dos guardados simultáneos de la misma línea
           pueden llegar desordenados y dejar escrito el valor viejo. */
        if (enVuelo[form.id]) {
            enVuelo[form.id].repetir = true;
            return;
        }

        enVuelo[form.id] = { repetir: false };
        pintarEstado(fila, 'guardando');

        fetch(form.getAttribute('action'), {
            method: 'POST',
            cache: 'no-cache',
            body: new FormData(form)
        })
            .then(function (r) { return r.text(); })
            .then(function (texto) {
                var datos;
                try { datos = JSON.parse(texto); }
                catch (e) { throw new Error('Respuesta ilegible del servidor'); }

                if (datos.tipo !== 'acta') {
                    /* Validación rechazada, permiso denegado o sesión caída:
                       el servidor lo explica y se muestra tal cual. */
                    throw new Error(datos.texto || 'No se pudo guardar');
                }

                repintar(fila, datos);
                pintarEstado(fila, 'guardado');
            })
            .catch(function (err) {
                pintarEstado(fila, 'error', err.message);
                avisar(err.message);
            })
            .finally(function () {
                var pendiente = enVuelo[form.id] && enVuelo[form.id].repetir;
                delete enVuelo[form.id];
                if (pendiente) { guardar(form); }
            });
    }

    /* Un aviso que no interrumpe: el acta se llena con el partido en marcha
       y un diálogo modal por cada error obligaría a soltar el teclado. */
    var avisoCaja = null;
    function avisar(mensaje) {
        if (!avisoCaja) {
            avisoCaja = document.createElement('div');
            avisoCaja.className = 'ds-acta-aviso alert alert-danger shadow';
            avisoCaja.setAttribute('role', 'alert');
            document.body.appendChild(avisoCaja);
        }
        avisoCaja.textContent = mensaje;
        avisoCaja.hidden = false;
        clearTimeout(avisoCaja._apagar);
        avisoCaja._apagar = setTimeout(function () { avisoCaja.hidden = true; }, 6000);
    }

    /*----------  Disparadores  ----------*/

    function programar(input) {
        var id = input.getAttribute('form');
        var form = id ? document.getElementById(id) : input.closest('form');
        if (!form) { return; }

        var fila = document.querySelector('tr[data-form="' + form.id + '"]');
        if (fila) { pintarEstado(fila, 'pendiente'); }

        clearTimeout(temporizadores[form.id]);
        temporizadores[form.id] = setTimeout(function () { guardar(form); }, ESPERA);
    }

    document.addEventListener('input', function (e) {
        if (e.target.matches('.ds-acta input[type="number"]')) { programar(e.target); }
    });

    /* Al salir del campo se guarda ya, sin esperar: quien pasa al siguiente
       jugador espera que lo anterior esté escrito. */
    document.addEventListener('focusout', function (e) {
        if (!e.target.matches('.ds-acta input[type="number"]')) { return; }
        var id = e.target.getAttribute('form');
        var form = id ? document.getElementById(id) : e.target.closest('form');
        if (!form || !temporizadores[form.id]) { return; }
        clearTimeout(temporizadores[form.id]);
        delete temporizadores[form.id];
        guardar(form);
    });

    /* Enter guarda y no envía el formulario por el camino tradicional, que
       recargaría la página. */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') { return; }
        if (!e.target.matches('.ds-acta input[type="number"]')) { return; }
        e.preventDefault();
        e.target.blur();
    });

    /* Nadie debería irse con una línea a medio guardar sin saberlo. */
    window.addEventListener('beforeunload', function (e) {
        var pendientes = Object.keys(temporizadores).length + Object.keys(enVuelo).length;
        if (pendientes > 0) { e.preventDefault(); e.returnValue = ''; }
    });
})();
