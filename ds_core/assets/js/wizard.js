/*
| Convierte un juego de pestanas en un asistente por pasos.
|
| EL FALLO QUE LO JUSTIFICA
|
| El alta de alumno tiene cinco pestanas y los CATORCE campos obligatorios
| estan en la primera. El boton Guardar esta siempre visible. Asi que quien
| rellena la ficha medica y pulsa Guardar se encuentra con esto:
|
|     An invalid form control with name='alumno_identificacion'
|     is not focusable.
|
| El navegador rechaza el envio porque falta un obligatorio, pero NO puede
| enseñar su mensaje: el campo esta en otra pestaña, oculto. Resultado: no
| pasa nada. Ni aviso, ni cambio de pestaña, ni pista. El boton parece
| muerto. Comprobado en el navegador antes de escribir esto.
|
| COMO LO ARREGLA
|
|   1. No se puede pasar de un paso hasta que ese paso esta completo, asi que
|      la situacion de arriba no llega a darse.
|   2. Y si aun asi el formulario se enviara invalido —por cualquier camino—
|      el asistente SALTA al paso donde esta el campo que falta y lo enfoca.
|      Ese es el remedio de verdad: convierte el silencio en una indicacion.
|   3. Guardar solo aparece en el ultimo paso.
|
| NO REESCRIBE EL MARCADO
|
| Se apoya en las pestañas que la vista ya tiene. Se activa poniendo
| data-ds-wizard en el contenedor, de modo que ninguna otra pantalla con
| pestañas se ve afectada.
|
| HACIA ATRAS SE VA LIBREMENTE
|
| Volver a un paso anterior nunca se bloquea: corregir algo que ya se
| escribio no puede costar mas que escribirlo.
*/
(function () {
    'use strict';

    function preparar(caja) {
        var enlaces = [].slice.call(caja.querySelectorAll('[data-bs-toggle="tab"]'));
        var paneles = [].slice.call(caja.querySelectorAll('.tab-pane'));
        if (enlaces.length < 2 || paneles.length !== enlaces.length) { return; }

        /* El formulario: se busca por los campos, no por el arbol. El <form>
           de estas vistas abre dentro de la primera pestaña y cierra despues
           de la ultima, asi que sus botones NO son descendientes suyos
           aunque el navegador si los asocie. */
        var campo = caja.querySelector('input[name], select[name], textarea[name]');
        var form  = campo ? campo.form : null;
        if (!form) { return; }

        var total  = enlaces.length;
        var actual = 0;

        /*----------  La barra de progreso  ----------*/
        var barra = document.createElement('div');
        barra.className = 'ds-wizard-progreso mb-3';
        barra.innerHTML =
            '<div class="d-flex justify-content-between align-items-center mb-1">' +
              '<small class="text-body-secondary" data-ds-wizard-paso></small>' +
              '<small class="text-body-secondary" data-ds-wizard-titulo></small>' +
            '</div>' +
            '<div class="progress" style="height:6px;" role="progressbar" ' +
                 'aria-label="Avance del formulario" aria-valuemin="0" aria-valuemax="100">' +
              '<div class="progress-bar" data-ds-wizard-barra style="width:0%"></div>' +
            '</div>';

        var contenido = caja.querySelector('.tab-content');
        contenido.parentNode.insertBefore(barra, contenido);

        /*----------  Los botones de navegacion  ----------*/
        var pie = document.createElement('div');
        pie.className = 'ds-wizard-pie d-flex justify-content-between gap-2 mt-3';
        pie.innerHTML =
            '<button type="button" class="btn btn-secondary" data-ds-wizard="atras">' +
              '<i class="fas fa-arrow-left me-1"></i>Anterior</button>' +
            '<button type="button" class="btn btn-primary" data-ds-wizard="adelante">' +
              'Siguiente<i class="fas fa-arrow-right ms-1"></i></button>';
        contenido.parentNode.insertBefore(pie, contenido.nextSibling);

        var atras    = pie.querySelector('[data-ds-wizard="atras"]');
        var adelante = pie.querySelector('[data-ds-wizard="adelante"]');

        /* El boton de guardar del formulario: se saca de form.elements por lo
           dicho arriba. */
        var guardar = [].slice.call(form.elements).filter(function (e) {
            return e.type === 'submit';
        })[0] || null;

        /*----------  Estado  ----------*/
        function panelDe(i) { return paneles[i]; }

        function invalidoEn(panel) {
            var campos = panel.querySelectorAll('input, select, textarea');
            for (var i = 0; i < campos.length; i++) {
                if (!campos[i].checkValidity()) { return campos[i]; }
            }
            return null;
        }

        function pintar() {
            var pct = Math.round(((actual + 1) / total) * 100);
            barra.querySelector('[data-ds-wizard-barra]').style.width = pct + '%';
            barra.querySelector('[data-ds-wizard-paso]').textContent =
                'Paso ' + (actual + 1) + ' de ' + total;
            barra.querySelector('[data-ds-wizard-titulo]').textContent =
                enlaces[actual].textContent.trim();
            barra.querySelector('.progress').setAttribute('aria-valuenow', String(pct));

            atras.disabled = (actual === 0);
            adelante.classList.toggle('d-none', actual === total - 1);

            /* Guardar solo al final: en los pasos intermedios el envio no
               puede prosperar y el boton solo confunde. */
            if (guardar) { guardar.classList.toggle('d-none', actual !== total - 1); }
        }

        function ir(i) {
            if (i < 0 || i >= total) { return; }
            actual = i;
            /* Se usa la pestaña de Bootstrap para no duplicar su logica. */
            if (window.bootstrap && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(enlaces[i]).show();
            } else {
                enlaces.forEach(function (a, k) { a.classList.toggle('active', k === i); });
                paneles.forEach(function (d, k) { d.classList.toggle('active', k === i); });
            }
            pintar();
        }

        /*----------  Avanzar: solo si este paso esta completo  ----------*/
        adelante.addEventListener('click', function () {
            var malo = invalidoEn(panelDe(actual));
            if (malo) {
                /* reportValidity enseña el mensaje del navegador junto al
                   campo, en el idioma del usuario y sin inventarse textos. */
                malo.reportValidity();
                return;
            }
            ir(actual + 1);
        });

        atras.addEventListener('click', function () { ir(actual - 1); });

        /* Pulsar una pestaña: hacia atras libre; hacia delante, solo si lo
           anterior esta completo. */
        enlaces.forEach(function (a, i) {
            a.addEventListener('click', function (e) {
                if (i <= actual) { actual = i; pintar(); return; }
                for (var k = actual; k < i; k++) {
                    var malo = invalidoEn(panelDe(k));
                    if (malo) {
                        e.preventDefault();
                        e.stopPropagation();
                        ir(k);
                        malo.reportValidity();
                        return;
                    }
                }
                actual = i;
                pintar();
            });
        });

        /*----------  La red de seguridad  ----------*/
        /*
        | SE ESCUCHA EL CLIC DEL BOTON, NO EL ENVIO DEL FORMULARIO.
        |
        | Parece mas natural engancharse a «submit», pero no sirve: cuando el
        | formulario tiene un obligatorio sin rellenar, el navegador bloquea
        | el envio en la fase de validacion y NUNCA llega a disparar submit.
        | Se probo, y el manejador no corria una sola vez.
        |
        | Y ese bloqueo es justamente el fallo: si el campo esta en otra
        | pestaña, el navegador tampoco puede enseñar su mensaje —«is not
        | focusable»— asi que no ocurre nada de nada.
        |
        | Al escuchar el clic en captura se llega ANTES de que el navegador
        | decida, y se puede llevar al usuario al paso del campo que falta.
        */
        if (guardar) {
            guardar.addEventListener('click', function (e) {
                if (form.checkValidity()) { return; }
                e.preventDefault();
                e.stopImmediatePropagation();

                for (var i = 0; i < total; i++) {
                    var malo = invalidoEn(panelDe(i));
                    if (malo) {
                        ir(i);
                        /* Tras cambiar de pestaña hace falta un instante para
                           que el campo sea visible y se pueda enfocar. */
                        (function (c) {
                            setTimeout(function () { c.focus(); c.reportValidity(); }, 80);
                        })(malo);
                        return;
                    }
                }
            }, true);
        }

        ir(0);
    }

    function arrancar() {
        var cajas = document.querySelectorAll('[data-ds-wizard]');
        for (var i = 0; i < cajas.length; i++) { preparar(cajas[i]); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
