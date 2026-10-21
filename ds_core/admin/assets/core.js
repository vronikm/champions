/* ==========================================================================
   DigiSports Core — envío de formularios por AJAX
   --------------------------------------------------------------------------
   Mismo contrato de respuesta que el módulo Basketball (alertas_ajax), para
   que el comportamiento sea idéntico en todo el ecosistema.
   ========================================================================== */

(function () {
    'use strict';

    function escaparHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function alertas(alerta) {
        var base = {
            icon: alerta.icono || 'info',
            title: alerta.titulo || '',
            text: alerta.texto || '',
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#1d3f9e'
        };

        if (alerta.tipo === 'recargar') {
            Swal.fire(base).then(function (r) { if (r.isConfirmed) location.reload(); });

        } else if (alerta.tipo === 'redireccionar') {
            Swal.fire(base).then(function (r) { if (r.isConfirmed) window.location.href = alerta.url; });

        } else if (alerta.tipo === 'toast') {
            Swal.mixin({
                toast: true, position: 'top-end', showConfirmButton: false,
                timer: 2200, timerProgressBar: true
            }).fire({ icon: alerta.icono || 'success', title: alerta.titulo });

        } else {
            Swal.fire(base);
        }
    }

    function procesar(texto) {
        var data;
        try {
            data = JSON.parse(texto);
        } catch (e) {
            console.error('Respuesta no-JSON del servidor:', texto);
            Swal.fire({
                icon: 'error',
                title: 'Respuesta inesperada del servidor',
                html: 'El servidor devolvió una respuesta que no se pudo procesar.' +
                      '<details style="text-align:left;margin-top:10px;">' +
                      '<summary style="cursor:pointer;">Ver detalle técnico</summary>' +
                      '<pre style="white-space:pre-wrap;max-height:220px;overflow:auto;' +
                      'background:#f5f5f5;padding:8px;border-radius:4px;font-size:12px;">' +
                      escaparHtml((texto || '').trim().slice(0, 2000)) + '</pre></details>',
                confirmButtonText: 'Aceptar'
            });
            return;
        }
        alertas(data);
    }

    function enviar(formulario) {
        fetch(formulario.getAttribute('action'), {
            method: formulario.getAttribute('method') || 'POST',
            cache: 'no-cache',
            body: new FormData(formulario)
        })
            .then(function (r) { return r.text(); })
            .then(procesar)
            .catch(function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No fue posible comunicarse con el servidor.',
                    confirmButtonText: 'Aceptar'
                });
            });
    }

    document.querySelectorAll('.FormularioAjax').forEach(function (formulario) {
        formulario.addEventListener('submit', function (e) {
            e.preventDefault();
            var self = this;

            /* Los formularios marcados como destructivos piden confirmación. */
            var confirmar = self.getAttribute('data-confirmar');

            if (confirmar === null) {
                enviar(self);
                return;
            }

            Swal.fire({
                title: '¿Está seguro?',
                text: confirmar || '¿Desea realizar la acción solicitada?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then(function (r) { if (r.isConfirmed) enviar(self); });
        });
    });

    /* ----- Matriz de permisos: "ver" gobierna al resto de acciones ----- */
    document.querySelectorAll('.tabla-permisos tr[data-menu]').forEach(function (fila) {
        var ver = fila.querySelector('input[data-accion="ver"]');
        if (!ver) return;

        function sincronizar() {
            fila.classList.toggle('sin-lectura', !ver.checked);
            fila.querySelectorAll('input[data-accion]:not([data-accion="ver"])').forEach(function (i) {
                i.disabled = !ver.checked;
                if (!ver.checked) i.checked = false;
            });
        }

        ver.addEventListener('change', sincronizar);
        sincronizar();
    });

    /* ----- Marcar / desmarcar una columna completa ----- */
    document.querySelectorAll('[data-marcar-columna]').forEach(function (boton) {
        boton.addEventListener('click', function (e) {
            e.preventDefault();
            var accion = this.getAttribute('data-marcar-columna');
            var casillas = document.querySelectorAll(
                '.tabla-permisos input[data-accion="' + accion + '"]:not(:disabled)'
            );
            var marcarTodo = Array.prototype.some.call(casillas, function (c) { return !c.checked; });

            casillas.forEach(function (c) {
                c.checked = marcarTodo;
                c.dispatchEvent(new Event('change'));
            });
        });
    });
})();
