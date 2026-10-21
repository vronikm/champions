/*
|--------------------------------------------------------------------------
| DigiSports — Visor de comprobantes
|--------------------------------------------------------------------------
| Abre en grande la imagen de un enlace marcado con data-bs-toggle="lightbox".
|
|
| POR QUE SUSTITUYE A EKKO-LIGHTBOX
|
| El visor anterior era ekko-lightbox, un complemento de jQuery escrito para
| Bootstrap 3 y 4. Dejo de funcionar al migrar a Bootstrap 5, y no por un
| detalle de estilos sino por un cambio de contrato en la API:
|
|     ekko-lightbox.js:155     }).modal(this._config);
|
| En Bootstrap 4, pasar un objeto a $(el).modal(config) inicializaba el modal
| Y LO ABRIA. En Bootstrap 5 la interfaz de jQuery solo ejecuta un metodo
| cuando recibe una CADENA ('show', 'hide'); con un objeto se limita a
| construir la instancia y no abre nada.
|
| El resultado era invisible en el peor sentido: el modal se creaba en el DOM,
| con opacity 0 y sin fondo, sin lanzar ni un error de consola. Al pulsar «no
| pasaba nada» y no habia rastro de por que. La imagen tampoco llegaba a
| cargarse, porque ekko la insertaba dentro del manejador de shown.bs.modal,
| que nunca se disparaba.
|
| Su boton de cerrar tenia ademas marcado de Bootstrap 4 —class="close" y
| data-dismiss="modal"—, invisible e inerte en Bootstrap 5.
|
|
| POR QUE SOLO IMAGENES
|
| ekko-lightbox tambien abria vídeos de YouTube y Vimeo, publicaciones de
| Instagram y páginas sueltas en un iframe. Ninguno de esos tipos se usa aqui:
| los 122 comprobantes cargados en el sistema son jpg (121) y png (1). Copiar
| esas cuatro ramas seria arrastrar codigo que nadie ejecuta.
|
| Si algun dia hiciera falta un PDF, el sitio de anadirlo es abrir(): una rama
| que ponga un <iframe> en lugar de un <img>.
|
|
| NO DEPENDE DE JQUERY
|
| jQuery se sigue cargando en estas pantallas para otras cosas, pero este
| visor no lo necesita. Una dependencia menos en el camino de un boton que
| solo tiene que enseniar una imagen.
*/

(function () {
    'use strict';

    var SELECTOR = '[data-bs-toggle="lightbox"]';

    var caja = null;      /* el <div class="modal">, uno para toda la pagina */
    var instancia = null; /* su bootstrap.Modal                              */
    var grupo = [];       /* los enlaces de la galeria actual                */
    var indice = 0;

    /*
    | Se construye una sola vez y se reutiliza. Crear un modal por cada clic
    | deja nodos huerfanos en el DOM y, con una tabla de veinte pagos, eso se
    | nota.
    */
    function construir() {
        if (caja) { return caja; }

        caja = document.createElement('div');
        caja.className = 'modal fade';
        caja.tabIndex = -1;
        caja.setAttribute('aria-hidden', 'true');
        caja.innerHTML =
            '<div class="modal-dialog modal-dialog-centered modal-lg">' +
              '<div class="modal-content">' +
                '<div class="modal-header py-2">' +
                  '<h5 class="modal-title fs-6" data-visor="titulo"></h5>' +
                  '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
                '</div>' +
                '<div class="modal-body text-center p-2">' +
                  '<img data-visor="imagen" alt="" class="img-fluid rounded" style="max-height:78vh;">' +
                  '<p data-visor="error" class="text-danger small my-3 d-none">' +
                    'No se pudo cargar el comprobante.</p>' +
                '</div>' +
                '<div class="modal-footer py-2 justify-content-between d-none" data-visor="pie">' +
                  '<button type="button" class="btn btn-sm btn-outline-secondary" data-visor="anterior">' +
                    '<i class="fas fa-chevron-left me-1"></i>Anterior</button>' +
                  '<span class="small text-muted" data-visor="cuenta"></span>' +
                  '<button type="button" class="btn btn-sm btn-outline-secondary" data-visor="siguiente">' +
                    'Siguiente<i class="fas fa-chevron-right ms-1"></i></button>' +
                '</div>' +
              '</div>' +
            '</div>';

        document.body.appendChild(caja);

        caja.querySelector('[data-visor="anterior"]')
            .addEventListener('click', function () { mover(-1); });
        caja.querySelector('[data-visor="siguiente"]')
            .addEventListener('click', function () { mover(1); });

        /* Las flechas del teclado tambien pasan de una a otra: con varias
           filas de pagos es lo que se espera de un visor. */
        caja.addEventListener('keydown', function (e) {
            if (grupo.length < 2) { return; }
            if (e.key === 'ArrowLeft')  { mover(-1); }
            if (e.key === 'ArrowRight') { mover(1); }
        });

        instancia = new bootstrap.Modal(caja);
        return caja;
    }

    function mover(paso) {
        if (grupo.length < 2) { return; }
        indice = (indice + paso + grupo.length) % grupo.length;
        pintar();
    }

    function pintar() {
        var enlace = grupo[indice];
        var img    = caja.querySelector('[data-visor="imagen"]');
        var error  = caja.querySelector('[data-visor="error"]');
        var pie    = caja.querySelector('[data-visor="pie"]');

        caja.querySelector('[data-visor="titulo"]').textContent =
            enlace.getAttribute('data-title') || 'Comprobante';

        /* Un comprobante que no carga tiene que DECIRLO. Antes se quedaba el
           icono roto del navegador, que parece un fallo del sistema y no un
           archivo que falta. */
        error.classList.add('d-none');
        img.classList.remove('d-none');
        img.alt = enlace.getAttribute('data-title') || 'Comprobante';
        img.onerror = function () {
            img.classList.add('d-none');
            error.classList.remove('d-none');
        };
        img.src = enlace.getAttribute('href');

        if (grupo.length > 1) {
            pie.classList.remove('d-none');
            caja.querySelector('[data-visor="cuenta"]').textContent =
                (indice + 1) + ' de ' + grupo.length;
        } else {
            pie.classList.add('d-none');
        }
    }

    document.addEventListener('click', function (e) {
        var enlace = e.target.closest(SELECTOR);
        if (!enlace) { return; }

        e.preventDefault();

        /*
        | Sin Bootstrap no hay modal, pero tampoco puede quedarse el clic en
        | nada: se abre la imagen en una pestania. Un boton que no responde es
        | peor que uno que responde de otra manera.
        */
        if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
            window.open(enlace.getAttribute('href'), '_blank', 'noopener');
            return;
        }

        construir();

        /* La galeria son los enlaces que comparten data-gallery. Si no lo
           lleva, la galeria es el propio enlace y no se ensenian las flechas. */
        var galeria = enlace.getAttribute('data-gallery');
        grupo = galeria
            ? Array.prototype.slice.call(
                  document.querySelectorAll(SELECTOR + '[data-gallery="' + galeria + '"]'))
            : [enlace];

        indice = grupo.indexOf(enlace);
        if (indice < 0) { indice = 0; }

        pintar();
        instancia.show();
    });
})();
