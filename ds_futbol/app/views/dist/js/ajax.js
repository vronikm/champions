/* Enviar formularios via AJAX */
const formularios_ajax = document.querySelectorAll(".FormularioAjax");

/* Procesa la respuesta del servidor de forma robusta: lee el texto e intenta
   parsear JSON. Si el servidor devolvio algo que no es JSON (p. ej. un warning
   de PHP antes de la respuesta), muestra un mensaje claro y registra la
   respuesta cruda en la consola para diagnostico. */
/* El formulario que origino la peticion viaja hasta la alerta porque la
   respuesta de tipo "limpiar" tiene que vaciar ESE y no otro. */
function manejarRespuestaAjax(texto, formulario) {
    let data;
    try {
        data = JSON.parse(texto);
    } catch (e) {
        console.error('Respuesta no-JSON del servidor:', texto);
        const detalle = (texto || '').trim();
        const detalleHtml = detalle
            ? '<details style="text-align:left;margin-top:10px;"><summary style="cursor:pointer;">Ver detalle técnico del servidor</summary>' +
              '<pre style="white-space:pre-wrap;word-break:break-word;max-height:220px;overflow:auto;background:#f5f5f5;padding:8px;border-radius:4px;font-size:12px;">' +
              escaparHtml(detalle.slice(0, 2000)) + '</pre></details>'
            : '<p style="margin-top:10px;"><em>(El servidor no devolvió contenido.)</em></p>';
        Swal.fire({
            icon: 'error',
            title: 'Respuesta inesperada del servidor',
            html: 'El servidor devolvió una respuesta que no se pudo procesar. ' +
                  'Verifique si el registro se guardó antes de reintentar, para no duplicarlo.' +
                  detalleHtml,
            confirmButtonText: 'Aceptar'
        });
        return;
    }
    return alertas_ajax(data, formulario);
}

/* Escapa texto para insertarlo de forma segura como HTML. */
function escaparHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

/* Falla de red/conexion (la peticion fetch fue rechazada). */
function errorConexionAjax() {
    Swal.fire({
        icon: 'error',
        title: 'Error de conexión',
        text: 'No fue posible comunicarse con el servidor. Revise su conexión a internet e intente de nuevo.',
        confirmButtonText: 'Aceptar'
    });
}

formularios_ajax.forEach(formularios => {

    formularios.addEventListener("submit", function (e) {

        e.preventDefault();

        /* Se guarda aqui la referencia al formulario: mas abajo la respuesta
           llega dentro de funciones de flecha, donde «this» ya no lo es. */
        const formEnviado = this;

        // Verificar si el formulario tiene el atributo para recargar directo
        if (this.hasAttribute("data-recargar-directo")) {
            let data = new FormData(this);
            let method = this.getAttribute("method");
            let action = this.getAttribute("action");

            let encabezados = new Headers();

            let config = {
                method: method,
                headers: encabezados,
                mode: 'cors',
                cache: 'no-cache',
                body: data
            };

            fetch(action, config)
                .then(respuesta => respuesta.text())
                .then(t => manejarRespuestaAjax(t, formEnviado))
                .catch(errorConexionAjax);

            return; // Salir antes de mostrar la alerta
        }

        Swal.fire({
            // title: '¿Está seguro?',
            text: "¿Desea realizar la acción solicitada?",
            //icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3e80c1',
            cancelButtonColor: '#844c4f',
            confirmButtonText: 'Si, realizar',
            cancelButtonText: 'No, cancelar'
        }).then((result) => {
            if (result.isConfirmed) {

                let data = new FormData(this);
                let method = this.getAttribute("method");
                let action = this.getAttribute("action");

                let encabezados = new Headers();

                let config = {
                    method: method,
                    headers: encabezados,
                    mode: 'cors',
                    cache: 'no-cache',
                    body: data
                };

                fetch(action, config)
                    .then(respuesta => respuesta.text())
                    .then(t => manejarRespuestaAjax(t, formEnviado))
                    .catch(errorConexionAjax);
            }
        });

    });

});


/* «formulario» es opcional: hay vistas que llaman a esta funcion por su
   cuenta, sin venir de un envio. */
function alertas_ajax(alerta, formulario) {
    if (alerta.tipo == "simple") {

        Swal.fire({
            icon: alerta.icono,
            title: alerta.titulo,
            text: alerta.texto,
            confirmButtonText: 'Aceptar'
        });  

    } else if (alerta.tipo == "recargar") {

        Swal.fire({
            icon: alerta.icono,
            title: alerta.titulo,
            text: alerta.texto,
            confirmButtonText: 'Aceptar'
        }).then((result) => {
            if (result.isConfirmed) {
                location.reload();
            }
        });

    } else if (alerta.tipo == "limpiar") {

        Swal.fire({
            icon: alerta.icono,
            title: alerta.titulo,
            text: alerta.texto,
            confirmButtonText: 'Aceptar'
        }).then((result) => {
            if (result.isConfirmed) {
                /* Antes se limpiaba el primer formulario del documento, que
                   desde que el navbar se incluye arriba es el de cambiar la
                   contrasena. El resultado: tras registrar un alumno los datos
                   seguian en pantalla, invitando a guardarlo dos veces. */
                if (formulario) { formulario.reset(); }
            }
        });

    } else if (alerta.tipo == "redireccionar") {
        Swal.fire({
            icon: alerta.icono,
            title: alerta.titulo,
            text: alerta.texto,
            confirmButtonText: 'Aceptar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = alerta.url;
            }
        });

    } else if (alerta.tipo == "recargar_directo") {
        location.reload();

    } else if (alerta.tipo == "Toast_Success") {
        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true, // Muestra una barra de progreso
            didClose: () => {
                // Recargar la página cuando se cierra el mensaje
                location.reload();
            }
        });
    
        Toast.fire({
            icon: 'success',
            title: alerta.titulo
        });
    }else if (alerta.tipo == "Toast_Success_simple") {
        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true, // Muestra una barra de progreso            
        });
    
        Toast.fire({
            icon: 'success',
            title: alerta.titulo
        });
    } else if (alerta.tipo == "mensajes_toast") {
        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true, // Muestra una barra de progreso
            didClose: () => {
                // Recargar la página cuando se cierra el mensaje
                location.reload();
            }
        });
    
        Toast.fire({
            icon: alerta.icono,
            title: alerta.titulo
        });
    }
}

/* Boton cerrar sesion
|
| POR CLASE, NO POR ID, Y RECORRIENDO TODOS
|
| Hay DOS enlaces de salida —el del menu de usuario y el del menu lateral—
| y ambos llevaban id="btn_exit". getElementById devuelve siempre el
| primero, asi que la confirmacion se enganchaba solo al de arriba: el del
| menu lateral cerraba la sesion SIN preguntar. No fallaba, simplemente no
| avisaba, que es peor.
|
| Ademas se comprueba que exista. Antes, una vista sin ese elemento hacia
| addEventListener sobre null, y esa excepcion detenia la ejecucion del
| resto de este archivo: los formularios de esa pantalla dejaban de
| enviarse por AJAX sin que nada lo explicara.
*/
document.querySelectorAll(".js-salir").forEach(function (btn_exit) {

  btn_exit.addEventListener("click", function(e){

    e.preventDefault();

    Swal.fire({
        title: '¿Quiere salir del sistema?',
        text: "La sesión actual se cerrará y saldrá del sistema",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Si, salir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            /* El destino sale del propio enlace pulsado, no de una
               variable capturada: con dos botones hay dos href. */
            window.location.href = btn_exit.getAttribute("href");
        }
    });

  });

});

// Botón enviar correo
let btn_correo = document.getElementById("btn_correo");

if (btn_correo) {
    btn_correo.addEventListener("click", function(e) {
        e.preventDefault();

        Swal.fire({
            title: '¿Enviar correo?',
            text: "¿Está seguro de que desea enviar el recibo por correo?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {

                // Mostrar loading
                Swal.fire({
                    title: 'Enviando...',
                    text: 'Por favor espere un momento',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Leer URL desde href
                let url=this.getAttribute("href");
                window.location.href=url;
            }
        });
    });
}

