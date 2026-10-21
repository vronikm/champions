/*
| El selector de foto: elegir, ver lo elegido y quitarlo.
|
| QUE HABIA
|
| El marcado de un plugin —Jasny fileinput, de la epoca de Bootstrap 3— que
| NO se cargaba en ninguna de las trece vistas que lo usan. Se veia el
| resultado: los dos rotulos del boton salian a la vez, «Seleccionar Foto
| Cambiar», el enlace «Remover» no hacia nada y la imagen elegida no se veia
| hasta despues de guardar.
|
| POR QUE NO SE CARGA EL PLUGIN Y SE ESCRIBE ESTO
|
| Son 6 KB de JavaScript que dependen de jQuery mas 42 KB de CSS escrito
| para Bootstrap 3, en un sistema que corre sobre el 5 y del que se esta
| retirando jQuery. Traerlo seria caminar hacia atras para recuperar algo
| que hoy hacen tres lineas de JavaScript moderno.
|
| SE RESPETA EL MARCADO EXISTENTE
|
| Las trece vistas ya tienen la estructura del plugin. En vez de cambiarlas
| una por una, este archivo entiende esa estructura: el contenedor lleva la
| clase de estado, los hijos «-new» se ven cuando no hay foto y los
| «-exists» cuando la hay.
|
| LO QUE APORTA Y ANTES NO HABIA
|
| Ver la foto antes de guardarla. Subir la foto de un alumno sin poder
| comprobar cual se ha elegido es como firmar sin leer.
*/
(function () {
    'use strict';

    /* Se libera el objeto anterior al cambiar de imagen: cada
       createObjectURL reserva memoria hasta que se revoca. */
    function ponerPrevisualizacion(caja, archivo) {
        var vista = caja.querySelector('.fileinput-preview');
        if (!vista) { return; }

        var previa = vista.querySelector('img');
        if (previa && previa.dataset.dsObjeto) { URL.revokeObjectURL(previa.src); }

        vista.innerHTML = '';
        var img = document.createElement('img');
        img.src = URL.createObjectURL(archivo);
        img.dataset.dsObjeto = '1';
        img.alt = 'Vista previa de la imagen elegida';
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        vista.appendChild(img);
    }

    function limpiar(caja) {
        var campo = caja.querySelector('input[type="file"]');
        if (campo) { campo.value = ''; }

        var vista = caja.querySelector('.fileinput-preview');
        if (vista) {
            var img = vista.querySelector('img');
            if (img && img.dataset.dsObjeto) { URL.revokeObjectURL(img.src); }
            vista.innerHTML = '';
        }
        caja.classList.remove('fileinput-exists');
        caja.classList.add('fileinput-new');
    }

    function preparar(caja) {
        if (caja.dataset.dsFoto) { return; }   /* ya preparada */
        caja.dataset.dsFoto = '1';

        var campo = caja.querySelector('input[type="file"]');
        if (!campo) { return; }

        /* Pulsar sobre la miniatura abre el selector, que es lo que la
           gente intenta hacer. */
        var disparador = caja.querySelector('[data-trigger="fileinput"]');
        if (disparador) {
            disparador.style.cursor = 'pointer';
            disparador.addEventListener('click', function () { campo.click(); });
        }

        campo.addEventListener('change', function () {
            var archivo = campo.files && campo.files[0];
            if (!archivo) { limpiar(caja); return; }

            ponerPrevisualizacion(caja, archivo);
            caja.classList.remove('fileinput-new');
            caja.classList.add('fileinput-exists');
        });

        /* El enlace de quitar. El atributo es el que ya trae el marcado. */
        var quitar = caja.querySelectorAll('[data-bs-dismiss="fileinput"], [data-dismiss="fileinput"]');
        for (var i = 0; i < quitar.length; i++) {
            quitar[i].addEventListener('click', function (e) {
                e.preventDefault();
                limpiar(caja);
            });
        }
    }

    function arrancar() {
        var cajas = document.querySelectorAll('.fileinput');
        for (var i = 0; i < cajas.length; i++) { preparar(cajas[i]); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
