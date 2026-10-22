/*
| Formularios en secciones (form.ds-editar): alta y edición de alumno, alta
| de representante.
|
| Lo que comparten, y que antes repetía cada vista a su manera:
|
|   · OBLIGATORIOS Y FORMATO. Se comprueban ANTES que ajax.js (en captura,
|     sobre el documento): si falta algo o un dato no tiene la forma
|     esperada —un correo sin @, un celular de nueve cifras—, ajax.js ni
|     se entera y no pregunta «¿Desea realizar la acción?» para luego
|     fallar. Se lleva la vista hasta el campo, se marca y se nombra.
|     El formato lo dice el propio campo (type="email", pattern): aquí no
|     se repite ninguna regla.
|   · data-ds-copiar="id" data-valor="…": un botón que copia un dato, como
|     la dirección del representante.
|   · data-ds-digitos: el campo sólo admite cifras (celulares). Sustituye a
|     la máscara de inputmask, que necesitaba jQuery.
|   · Tras un reset (lo hace ajax.js después de un alta correcta) se quitan
|     las marcas de error.
|
| Necesita SweetAlert2.
*/
(function () {
    'use strict';

    function grupoDe(campo) { return campo.type === 'radio' ? campo.closest('.ds-opciones') : campo; }

    function rotuloDe(form, campo) {
        var g = campo.closest('[role="radiogroup"]');
        var l = g ? document.getElementById(g.getAttribute('aria-labelledby')) : form.querySelector('label[for="' + campo.id + '"]');
        if (!l) { return campo.name; }
        /* AdminLTE añade a la etiqueta un «(required)» oculto para lectores
           de pantalla: no va en el aviso. */
        var copia = l.cloneNode(true);
        copia.querySelectorAll('.visually-hidden, .sr-only').forEach(function (x) { x.remove(); });
        return copia.textContent.trim();
    }

    /* 'vacio', 'formato' o '' (bien). Los campos de una sección plegada
       también cuentan: plegar no es borrar lo escrito. */
    function problemaDe(form, campo) {
        if (campo.type === 'radio') {
            return campo.required && !form.querySelector('[name="' + campo.name + '"]:checked') ? 'vacio' : '';
        }
        var valor = campo.value.trim();
        if (valor === '') { return campo.required ? 'vacio' : ''; }
        return campo.checkValidity() ? '' : 'formato';
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form.ds-editar')) { return; }

        var primero = null, motivo = '';
        form.querySelectorAll('input, select, textarea').forEach(function (campo) {
            if (!campo.name || campo.type === 'hidden' || campo.type === 'file' || campo.disabled) { return; }
            if (campo.type === 'radio' && !campo.required) { return; }
            var p = problemaDe(form, campo);
            grupoDe(campo).classList.toggle('is-invalid', p !== '');
            if (p && !primero) { primero = campo; motivo = p; }
        });
        if (!primero) { return; }

        e.preventDefault();
        e.stopImmediatePropagation();

        /* Si está en una sección plegada, se despliega antes de ir a él. */
        var plegada = primero.closest('.collapse:not(.show)');
        if (plegada && window.bootstrap) { bootstrap.Collapse.getOrCreateInstance(plegada).show(); }

        setTimeout(function () {
            grupoDe(primero).scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function () { primero.focus({ preventScroll: true }); }, 300);
        }, plegada ? 350 : 0);

        var rotulo = rotuloDe(form, primero);
        Swal.fire(motivo === 'vacio'
            ? { icon: 'warning', title: 'Falta un dato obligatorio', text: 'Complete «' + rotulo + '».', confirmButtonText: 'Entendido' }
            : { icon: 'warning', title: 'Revise un dato', text: '«' + rotulo + '» no tiene el formato esperado'
                    + (primero.title ? ': ' + primero.title : '') + '.', confirmButtonText: 'Entendido' });
    }, true);

    document.addEventListener('input', function (e) {
        var campo = e.target;
        if (!campo.closest || !campo.closest('form.ds-editar')) { return; }
        if (campo.hasAttribute('data-ds-digitos')) {
            var limpio = campo.value.replace(/\D/g, '');
            if (limpio !== campo.value) { campo.value = limpio; }
        }
        var g = campo.type === 'radio' ? campo.closest('.ds-opciones') : campo;
        if (g) { g.classList.remove('is-invalid'); }
    });

    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('form.ds-editar [data-ds-copiar]');
        if (!b) { return; }
        var destino = document.getElementById(b.getAttribute('data-ds-copiar'));
        if (!destino) { return; }
        destino.value = b.getAttribute('data-valor') || '';
        destino.dispatchEvent(new Event('input', { bubbles: true }));
        destino.focus();
    });

    document.addEventListener('reset', function (e) {
        if (!e.target.matches || !e.target.matches('form.ds-editar')) { return; }
        setTimeout(function () {
            e.target.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
        }, 0);
    });
})();
