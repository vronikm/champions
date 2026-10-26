<?php
/*
|--------------------------------------------------------------------------
| Modal «Cambiar contraseña y enviarla»
|--------------------------------------------------------------------------
| Lo usan el listado de usuarios y el formulario de edición. Lo abre
| cualquier botón con la clase .js-clave y estos data-*:
|   data-id, data-usuario, data-nombre, data-correo, data-celular
|
| La contraseña se genera aleatoria al abrir (se puede escribir otra).
| Al guardar, el servidor (coreController::restablecerClave) la cambia y,
| si se marcó, la envía por correo. Para WhatsApp devuelve el enlace
| wa.me con el mensaje: el botón «Enviar por WhatsApp» lo abre en el
| teléfono de quien administra, que es quien lo manda. Se abre con un
| toque y no solo: los navegadores bloquean ventanas que no abre una
| pulsación.
*/
?>
<div class="modal fade" id="modalClave" tabindex="-1" aria-labelledby="modalClaveTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalClaveTitulo"><i class="fas fa-key me-2" aria-hidden="true"></i>Cambiar contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Paso 1: la contraseña y por dónde enviarla -->
            <form id="clavePaso1" autocomplete="off" novalidate>
                <div class="modal-body">
                    <p class="mb-3">Usuario <strong id="claveQuien"></strong><span class="text-body-secondary" id="claveNombre"></span></p>

                    <label for="claveNueva" class="form-label">Nueva contraseña</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" id="claveNueva" autocomplete="new-password"
                               minlength="<?php echo clave_longitud_minima(); ?>" maxlength="<?php echo clave_longitud_maxima(); ?>" required spellcheck="false">
                        <button type="button" class="btn btn-secondary" id="claveGenerar" title="Generar otra" aria-label="Generar otra contraseña"><i class="fas fa-rotate" aria-hidden="true"></i></button>
                    </div>
                    <div class="form-text"><?php echo clave_regla_texto(); ?> Se propone una aleatoria; puede escribir otra.</div>

                    <fieldset class="ds-clave__envio mt-3">
                        <legend class="form-label mb-2">Enviarla al usuario por</legend>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="claveCorreoSi">
                            <label class="form-check-label" for="claveCorreoSi"><i class="fas fa-envelope me-1" aria-hidden="true"></i>Correo</label>
                        </div>
                        <input type="email" class="form-control mt-1 mb-2" id="claveCorreo" placeholder="correo@ejemplo.com" inputmode="email" aria-label="Correo del usuario">

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="claveWaSi">
                            <label class="form-check-label" for="claveWaSi"><i class="fab fa-whatsapp me-1" aria-hidden="true"></i>WhatsApp</label>
                        </div>
                        <input type="tel" class="form-control mt-1" id="claveCelular" placeholder="09…" inputmode="numeric" maxlength="13" aria-label="Celular del usuario">
                        <div class="form-text">El mensaje de WhatsApp lo envía usted desde su teléfono.</div>
                    </fieldset>

                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="fas fa-shield-halved me-1" aria-hidden="true"></i>La contraseña viaja en el mensaje: verifique que el correo y el celular sean de esa persona. Pídale que la cambie al ingresar.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="claveGuardar"><i class="fas fa-save me-1" aria-hidden="true"></i>Guardar contraseña</button>
                </div>
            </form>

            <!-- Paso 2: el resultado y el envío por WhatsApp -->
            <div id="clavePaso2" class="d-none">
                <div class="modal-body">
                    <div class="alert alert-success mb-3"><i class="fas fa-check me-1" aria-hidden="true"></i><span id="claveListoQue">Contraseña cambiada para</span> <strong id="claveListoQuien"></strong>.</div>

                    <label class="form-label" for="claveListo">Nueva contraseña</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control font-monospace" id="claveListo" readonly>
                        <button type="button" class="btn btn-secondary" id="claveCopiar"><i class="fas fa-copy me-1" aria-hidden="true"></i>Copiar mensaje</button>
                    </div>

                    <ul class="list-unstyled ds-clave__resultado mb-0" id="claveResultado"></ul>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-success d-none" id="claveWhatsapp" target="_blank" rel="noopener"><i class="fab fa-whatsapp me-1" aria-hidden="true"></i>Enviar por WhatsApp</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Listo</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* Bootstrap y SweetAlert se cargan en el pie del layout, después de esto. */
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalClave');
    if (!modalEl || typeof bootstrap === 'undefined') { return; }
    var modal = new bootstrap.Modal(modalEl);
    var url = '<?php echo APP_URL; ?>ajax/coreAjax.php';
    var $ = function (id) { return document.getElementById(id); };
    var actual = null, mensaje = '', alCerrar = null;

    /* Aleatoria y legible: sin 0/O ni 1/l/I, tres grupos de cuatro con al
       menos un número. El servidor la valida igual (clave_valida). */
    function generar() {
        var letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', grupos = [], azar = new Uint32Array(12);
        do {
            crypto.getRandomValues(azar); grupos = [];
            for (var g = 0; g < 3; g++) {
                var p = '';
                for (var i = 0; i < 4; i++) { p += letras[azar[g * 4 + i] % letras.length]; }
                grupos.push(p);
            }
        } while (!/\d/.test(grupos.join('')));
        return grupos.join('-');
    }

    function sincronizar() {
        $('claveCorreo').disabled = !$('claveCorreoSi').checked;
        $('claveCelular').disabled = !$('claveWaSi').checked;
    }

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('.js-clave'); if (!b) { return; }
        actual = b.dataset;
        $('claveQuien').textContent = actual.usuario;
        $('claveNombre').textContent = actual.nombre ? ' · ' + actual.nombre : '';
        $('claveNueva').value = generar();
        $('claveCorreo').value = actual.correo || '';
        $('claveCelular').value = actual.celular || '';
        /* Marcados por omisión los que tienen dato en la ficha. */
        $('claveCorreoSi').checked = !!actual.correo;
        $('claveWaSi').checked = !!actual.celular;
        sincronizar();
        $('clavePaso1').classList.remove('d-none'); $('clavePaso2').classList.add('d-none');
        $('claveGuardar').disabled = false;
        modal.show();
    });

    /* El resultado: la contraseña, cómo fue el correo y el botón de
       WhatsApp. Lo usa también el alta de usuario (window.dsClaveResultado). */
    function mostrarResultado(j) {
        mensaje = j.mensaje;
        $('claveListoQuien').textContent = j.usuario;
        $('claveListo').value = j.clave;
        var res = $('claveResultado'); res.innerHTML = '';
        function linea(icono, clase, texto) {
            var li = document.createElement('li'); li.className = 'mb-1 ' + clase;
            li.innerHTML = '<i class="fas ' + icono + ' me-1" aria-hidden="true"></i>';
            li.appendChild(document.createTextNode(texto)); res.appendChild(li);
        }
        if (j.correo.pedido) {
            j.correo.enviado
                ? linea('fa-envelope-circle-check', 'text-success-emphasis', 'Correo enviado a ' + j.correo.a + '.')
                : linea('fa-triangle-exclamation', 'text-danger', 'El servidor no pudo enviar el correo a ' + j.correo.a + '. Copie el mensaje y envíelo por otro medio.');
        }
        var wa = $('claveWhatsapp');
        if (j.whatsapp.pedido && j.whatsapp.url) {
            wa.href = j.whatsapp.url; wa.classList.remove('d-none');
            linea('fa-whatsapp fab', 'text-body-secondary', 'Pulse «Enviar por WhatsApp» para mandarlo desde su teléfono.');
        } else {
            wa.classList.add('d-none');
        }
        if (!j.correo.pedido && !j.whatsapp.pedido) {
            linea('fa-circle-info', 'text-body-secondary', 'No se envió por ningún medio: copie el mensaje para dárselo.');
        }
        $('clavePaso1').classList.add('d-none'); $('clavePaso2').classList.remove('d-none');
        $('claveListoQue').textContent = j.nuevo ? 'Usuario creado:' : 'Contraseña cambiada para';
    }
    window.dsClaveGenerar = generar;
    window.dsClaveResultado = function (j, despues) {
        alCerrar = despues || null;
        mostrarResultado(j);
        modal.show();
    };

    $('claveGenerar').addEventListener('click', function () { $('claveNueva').value = generar(); });
    $('claveCorreoSi').addEventListener('change', sincronizar);
    $('claveWaSi').addEventListener('change', sincronizar);

    $('clavePaso1').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var clave = $('claveNueva').value;
        if (clave.length < <?php echo clave_longitud_minima(); ?>) {
            Swal.fire({ icon: 'error', title: 'Contraseña muy corta', text: 'Debe tener al menos <?php echo clave_longitud_minima(); ?> caracteres.' }); return;
        }
        var fd = new FormData();
        fd.append('modulo_core', 'restablecerClave');
        fd.append('usuario_id', actual.id);
        fd.append('clave', clave);
        fd.append('enviar_correo', $('claveCorreoSi').checked ? 'S' : 'N');
        fd.append('correo', $('claveCorreo').value.trim());
        fd.append('enviar_whatsapp', $('claveWaSi').checked ? 'S' : 'N');
        fd.append('celular', $('claveCelular').value.trim());

        $('claveGuardar').disabled = true;
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (t) {
                var j = null; try { j = JSON.parse(t); } catch (e) {}
                if (!j || !j.ok) {
                    $('claveGuardar').disabled = false;
                    Swal.fire({ icon: 'error', title: (j && j.titulo) || 'No se pudo guardar',
                                text: (j && j.texto) || 'Su sesión pudo caducar. Vuelva a entrar.' });
                    return;
                }
                mostrarResultado(j);
            })
            .catch(function () {
                $('claveGuardar').disabled = false;
                Swal.fire({ icon: 'error', title: 'Sin conexión', text: 'No se pudo contactar con el servidor. No se cambió nada.' });
            });
    });

    $('claveCopiar').addEventListener('click', function () {
        var b = this;
        (navigator.clipboard ? navigator.clipboard.writeText(mensaje) : Promise.reject())
            .then(function () { b.innerHTML = '<i class="fas fa-check me-1" aria-hidden="true"></i>Copiado'; })
            .catch(function () { $('claveListo').select(); });
        setTimeout(function () { b.innerHTML = '<i class="fas fa-copy me-1" aria-hidden="true"></i>Copiar mensaje'; }, 2500);
    });

    /* Al cerrar, nada de la contraseña queda en la página. */
    modalEl.addEventListener('hidden.bs.modal', function () {
        $('claveNueva').value = ''; $('claveListo').value = ''; mensaje = '';
        $('claveWhatsapp').href = '#';
        if (alCerrar) { var f = alCerrar; alCerrar = null; f(); }
    });
});
</script>
