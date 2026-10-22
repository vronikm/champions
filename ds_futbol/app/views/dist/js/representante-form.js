/*
| El formulario del representante: alta (representanteNew) y edición
| (representanteUpdate). Va después de form-secciones.js, que lleva los
| obligatorios y el formato.
|
|   · La cédula ecuatoriana se comprueba (dígito verificador). Es un AVISO,
|     no bloquea: la decisión la toma quien tiene la cédula delante.
|   · Al salir del campo se pregunta si la identificación ya está
|     registrada (consultarIdentificacion). En el alta, para ofrecer el
|     representante que ya existe —los padres de un segundo hijo—. En la
|     edición, si es la de OTRO representante: el controlador no lo
|     comprueba al actualizar y quedarían dos con la misma.
|   · Madre → Femenino, Padre → Masculino, si aún no se eligió.
|   · «La misma del representante» copia la dirección al cónyuge.
|
| El formulario es #formRepre. data-base lleva APP_URL y, en la edición,
| data-repre-id el id del propio representante.
*/
(function () {
    'use strict';

    var form = document.getElementById('formRepre');
    if (!form) { return; }
    var tipo  = document.getElementById('repre_tipoidentificacion');
    var ident = document.getElementById('repre_identificacion');
    var aviso = document.getElementById('repre_ident_aviso');
    var BASE  = form.getAttribute('data-base') || '';
    var PROPIO = parseInt(form.getAttribute('data-repre-id') || '0', 10);

    function cedulaValida(c) {
        if (!/^\d{10}$/.test(c)) { return false; }
        var prov = +c.slice(0, 2);
        if (!((prov >= 1 && prov <= 24) || prov === 30) || +c[2] > 5) { return false; }
        var suma = 0;
        for (var i = 0; i < 9; i++) {
            var v = +c[i] * (i % 2 === 0 ? 2 : 1);
            suma += v > 9 ? v - 9 : v;
        }
        return (10 - suma % 10) % 10 === +c[9];
    }

    function pintar(html, clase) {
        aviso.className = 'ds-repre__aviso' + (clase ? ' ' + clase : '');
        aviso.innerHTML = html;
    }

    /* En la edición se parte del valor guardado: no se avisa de nada hasta
       que se cambie. */
    var ultima = PROPIO ? ident.value.trim() : '';

    function revisar() {
        var valor = ident.value.trim();
        if (valor === ultima) { return; }
        ultima = valor;
        if (!valor) { pintar(''); return; }

        var esCedula = /c[eé]dula/i.test(tipo.options[tipo.selectedIndex].text);
        if (esCedula && !cedulaValida(valor)) {
            pintar('<i class="fas fa-circle-exclamation me-1" aria-hidden="true"></i>Revise el número: no es una cédula ecuatoriana válida.', 'text-warning-emphasis');
        } else {
            pintar('');
        }

        var datos = new FormData();
        datos.append('modulo_repre', 'consultarIdentificacion');
        datos.append('repre_identificacion', valor);
        fetch(form.getAttribute('action'), { method: 'POST', body: datos })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.existe || j.id === PROPIO || ident.value.trim() !== valor) { return; }
                var nombre = document.createElement('strong');
                nombre.textContent = j.nombre;
                var alumnos = j.alumnos ? ' (' + j.alumnos + (j.alumnos === 1 ? ' alumno)' : ' alumnos)') : '';
                if (PROPIO) {
                    pintar('<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Esta identificación es de otro representante: '
                        + nombre.outerHTML + alumnos + '. Si guarda, habrá dos con la misma.'
                        + '<span class="ds-repre__aviso-acciones"><a class="btn btn-sm btn-secondary" href="' + BASE + 'representanteProfile/' + j.id + '/">Ver su ficha</a></span>',
                        'ds-repre__aviso--otro');
                } else {
                    pintar('<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Ya está registrado: ' + nombre.outerHTML + alumnos
                        + '<span class="ds-repre__aviso-acciones">'
                        + '<a class="btn btn-sm btn-success" href="' + BASE + 'alumnoNew/' + j.id + '/"><i class="fas fa-plus me-1" aria-hidden="true"></i>Registrar un alumno suyo</a>'
                        + '<a class="btn btn-sm btn-secondary" href="' + BASE + 'representanteProfile/' + j.id + '/">Ver ficha</a></span>',
                        'ds-repre__aviso--existe');
                }
            })
            .catch(function () {});
    }
    ident.addEventListener('change', revisar);
    ident.addEventListener('blur', revisar);
    tipo.addEventListener('change', function () { ultima = ''; revisar(); });

    document.getElementById('repre_parentesco').addEventListener('change', function () {
        var t = this.options[this.selectedIndex].text.trim().toLowerCase();
        var sexo = t === 'madre' ? 'F' : (t === 'padre' ? 'M' : '');
        if (sexo && !form.querySelector('[name="repre_sexo"]:checked')) {
            var r = document.getElementById('repre_sexo' + sexo);
            r.checked = true;
            r.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    /* «La misma del representante»: el valor se toma al pulsar, antes de
       que form-secciones.js lo copie. */
    var copiar = document.getElementById('copiarDireccion');
    if (copiar) {
        copiar.addEventListener('click', function () {
            copiar.setAttribute('data-valor', document.getElementById('repre_direccion').value);
        }, true);
    }
})();
