<?php
/*
| Carga una fila de la tabla en el formulario de al lado.
|
| Va aquí y no repetido en cada vista porque el comportamiento es el mismo
| en todas: los botones marcados con .js-editar llevan un data-fila con el
| registro ya escapado por el servidor, y sus claves coinciden con los id
| de los campos del formulario. Añadir una pantalla nueva no requiere
| escribir JavaScript, sólo respetar esa convención.
|
| Espera un formulario con id="formFila".
*/
?>
<script>
(function () {
    var form = document.getElementById('formFila');
    if (!form) { return; }

    /* El título y el id ocultos se restauran al limpiar. Sin esto, el
       campo oculto seguiría apuntando al último registro editado y un
       "nuevo" acabaría sobrescribiéndolo en silencio. */
    var titulo   = document.getElementById('tituloForm');
    var tituloOriginal = titulo ? titulo.innerHTML : '';
    var oculto   = form.querySelector('input[type=hidden][id]');

    document.querySelectorAll('.js-editar').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var d;
            try { d = JSON.parse(boton.getAttribute('data-fila')); } catch (e) { return; }

            var primero = null;
            for (var clave in d) {
                var campo = document.getElementById(clave);
                if (!campo) { continue; }
                campo.value = d[clave] === null ? '' : d[clave];
                if (!primero && campo.type !== 'hidden') { primero = campo; }
            }

            if (titulo) {
                titulo.innerHTML = '<i class="fas fa-pen me-2"></i>Editar';
            }

            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (primero) { primero.focus(); }
        });
    });

    form.addEventListener('reset', function () {
        /* El navegador aplica el reset DESPUÉS del evento, así que la
           restauración va en el siguiente ciclo o la pisaría. */
        setTimeout(function () {
            if (oculto) { oculto.value = '0'; }
            if (titulo) { titulo.innerHTML = tituloOriginal; }
        }, 0);
    });
})();
</script>
