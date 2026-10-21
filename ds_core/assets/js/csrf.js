/*
| Testigo CSRF en todas las peticiones que salen del navegador
| ===========================================================================
|
| QUÉ PROBLEMA RESUELVE
|
| El navegador manda la cookie de sesión en TODA petición al dominio, venga de
| donde venga. Si alguien con sesión abierta visita otra página y esa página
| envía un formulario contra DigiSports, el servidor recibe una petición con
| la cookie correcta y la da por buena. Eso es CSRF.
|
| La defensa es un valor secreto que sólo está en la sesión y en el HTML de la
| página de verdad. La página atacante no puede leerlo —está en otro dominio y
| la política de mismo origen se lo impide— así que no puede acompañarlo.
|
|
| POR QUÉ AQUÍ Y NO CAMPO A CAMPO EN CADA FORMULARIO
|
| Hay 82 formularios y, además, una buena parte de los envíos no salen de un
| formulario: son fetch() y $.ajax() que arman su propio FormData. Añadir el
| campo a mano en cada sitio son 82 ediciones hoy y un olvido garantizado en
| el siguiente formulario que alguien escriba —y un olvido aquí no rompe nada
| visible, simplemente deja esa operación desprotegida—.
|
| Interceptando el transporte, el testigo viaja en todo lo que sale, incluido
| lo que se escriba mañana.
|
|
| ESTO NO ES LA DEFENSA
|
| La defensa está en el servidor: los guards comprueban el testigo y rechazan
| lo que llegue sin él. Este archivo sólo se encarga de que las peticiones
| legítimas lo lleven. Si este archivo no se cargara, el sistema no quedaría
| desprotegido: quedaría inservible, que es el lado correcto en el que fallar.
|
|
| POR QUÉ SE PARCHEAN LOS OBJETOS GLOBALES
|
| Parchear fetch y XMLHttpRequest es invasivo y conviene decirlo. La
| alternativa era tocar los 82 formularios y las dos docenas de llamadas
| sueltas, con el riesgo de romper alguna y sin resolver el olvido futuro. Se
| eligió el parche por eso, y se acota con dos reglas estrictas: sólo al mismo
| origen —nunca se filtra el testigo a un tercero— y sólo en métodos que
| escriben.
*/

(function () {
    'use strict';

    var meta = document.querySelector('meta[name="ds-csrf"]');
    var TESTIGO = meta ? (meta.getAttribute('content') || '') : '';
    var CAMPO = 'ds_csrf';

    /*
    | Marcador de que este archivo se ejecutó.
    |
    | No es adorno: sin él, «el testigo no viaja» y «el script no llegó a
    | correr» se ven exactamente igual desde fuera —una petición rechazada— y
    | se busca el fallo en el sitio equivocado. Escribiendo «dsCsrf» en la
    | consola del navegador se distingue en un segundo.
    |
    | Ojo: NO sirve para automatizar la comprobación. Los navegadores de
    | prueba evalúan en un contexto aislado donde «window» es otro objeto, y
    | allí este marcador no se ve aunque el script esté corriendo
    | perfectamente. Se perdió un rato con eso. Lo que sí se puede comprobar
    | desde fuera es lo único que importa de verdad: si la petición que sale
    | lleva el campo ds_csrf.
    */
    window.dsCsrf = { cargado: true, testigo: TESTIGO !== '', parcheado: false };

    /* Sin testigo no hay nada que añadir. Ocurre en las pantallas que se
       dibujan sin sesión; sus envíos no llevan cookie que robar. */
    if (TESTIGO === '') { return; }

    /** ¿La petición va a nuestro propio servidor? */
    function mismoOrigen(url) {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (e) {
            /* Una URL relativa rara o vacía: se resuelve contra la página, así
               que si new URL falla es que no es navegable. No se toca. */
            return false;
        }
    }

    /** GET y HEAD no cambian nada en el servidor: no necesitan testigo. */
    function escribe(metodo) {
        var m = String(metodo || 'GET').toUpperCase();
        return m !== 'GET' && m !== 'HEAD';
    }

    /*==================================================================
    | 1. fetch()
    |==================================================================*/
    var fetchOriginal = window.fetch;

    if (typeof fetchOriginal === 'function') {
        window.fetch = function (recurso, config) {
            try {
                var url = (typeof recurso === 'string') ? recurso
                        : (recurso && recurso.url) || '';
                var metodo = (config && config.method)
                          || (recurso && recurso.method) || 'GET';

                if (escribe(metodo) && mismoOrigen(url)
                    && config && config.body instanceof FormData
                    && !config.body.has(CAMPO)) {
                    config.body.append(CAMPO, TESTIGO);
                }
            } catch (e) {
                /* Nunca impedir la petición por un fallo al adornarla: el
                   servidor la rechazará si de verdad le falta el testigo, y
                   ese mensaje es más útil que una excepción aquí. */
            }

            return fetchOriginal.apply(this, arguments);
        };
    }

    /*==================================================================
    | 2. XMLHttpRequest  (por aquí pasa jQuery.ajax y DataTables)
    |==================================================================*/
    var abrirOriginal    = XMLHttpRequest.prototype.open;
    var enviarOriginal   = XMLHttpRequest.prototype.send;
    var cabeceraOriginal = XMLHttpRequest.prototype.setRequestHeader;

    XMLHttpRequest.prototype.open = function (metodo, url) {
        this.__dsEscribe = escribe(metodo);
        this.__dsPropio  = mismoOrigen(url);
        this.__dsTipo    = '';
        return abrirOriginal.apply(this, arguments);
    };

    /* El tipo de contenido decide cómo se añade el testigo a un cuerpo que es
       texto: en un urlencoded se concatena, en un JSON lo estropearía. */
    XMLHttpRequest.prototype.setRequestHeader = function (nombre, valor) {
        try {
            if (String(nombre).toLowerCase() === 'content-type') {
                this.__dsTipo = String(valor || '').toLowerCase();
            }
        } catch (e) { }
        return cabeceraOriginal.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function (cuerpo) {
        try {
            if (this.__dsEscribe && this.__dsPropio) {

                if (cuerpo instanceof FormData) {
                    if (!cuerpo.has(CAMPO)) { cuerpo.append(CAMPO, TESTIGO); }

                } else if (typeof cuerpo === 'string'
                           && this.__dsTipo.indexOf('application/x-www-form-urlencoded') === 0
                           && cuerpo.indexOf(CAMPO + '=') === -1) {
                    cuerpo += (cuerpo === '' ? '' : '&')
                            + CAMPO + '=' + encodeURIComponent(TESTIGO);
                }
            }
        } catch (e) { }

        return enviarOriginal.call(this, cuerpo);
    };

    /*==================================================================
    | 3. Envíos normales de formulario, sin AJAX
    |==================================================================*/
    /* En fase de captura y no de burbuja: así el campo ya está puesto cuando
       el manejador de la vista construye su FormData a partir del formulario.
       En burbuja llegaría tarde y el envío saldría sin testigo. */
    document.addEventListener('submit', function (e) {
        try {
            var f = e.target;
            if (!f || f.tagName !== 'FORM') { return; }
            if (!escribe(f.getAttribute('method') || 'GET')) { return; }
            if (!mismoOrigen(f.getAttribute('action') || window.location.href)) { return; }
            if (f.querySelector('[name="' + CAMPO + '"]')) { return; }

            var i = document.createElement('input');
            i.type = 'hidden';
            i.name = CAMPO;
            i.value = TESTIGO;
            f.appendChild(i);
        } catch (err) { }
    }, true);

    window.dsCsrf.parcheado = true;
})();
