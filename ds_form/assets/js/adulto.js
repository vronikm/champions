/**
 * Formulario público de inscripción de ALUMNOS ADULTOS (ds_form/adulto.php, migración 086).
 *
 * Archivo propio: form.js —el del formulario de menores— no se toca.
 * Todas estas validaciones se repiten en el servidor
 * (ds_core/inc/alumno_adulto.php); aquí sólo evitan descubrir los errores al enviar.
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    var EDAD_MINIMA = 18;
    var COLOR = "#06102a";

    function cedulaValida(c) {
        c = c.replace(/\D/g, "");
        if (c.length !== 10 || /^(.)\1{9}$/.test(c)) return false;
        var prov = parseInt(c.substring(0, 2), 10);
        if (prov < 1 || prov > 24 || parseInt(c[2], 10) > 5) return false;
        var suma = 0;
        for (var i = 0; i < 9; i++) {
            var d = parseInt(c[i], 10);
            if (i % 2 === 0) { d *= 2; if (d > 9) d -= 9; }
            suma += d;
        }
        return (10 - suma % 10) % 10 === parseInt(c[9], 10);
    }

    function edadDe(valor) {
        var p = (valor || "").split("-");
        if (p.length !== 3) return NaN;
        var n = new Date(+p[0], +p[1] - 1, +p[2]), hoy = new Date();
        return hoy.getFullYear() - n.getFullYear()
             - ((hoy.getMonth() < n.getMonth() || (hoy.getMonth() === n.getMonth() && hoy.getDate() < n.getDate())) ? 1 : 0);
    }

    function marcar(input, bien, msg) {
        var col = input.closest("[class*='col-']");
        var fb = col && col.querySelector(".invalid-feedback");
        input.classList.toggle("is-invalid", !bien);
        input.classList.toggle("is-valid", bien);
        if (!bien && fb && msg) fb.textContent = msg;
        return bien;
    }

    var $ = function (id) { return document.getElementById(id); };
    var tipo = $("tipoidentificacion"), ident = $("identificacion"), fnac = $("fechanacimiento"),
        edadTxt = $("edad_texto"), correo = $("correo"), celular = $("celular");

    /* Edad junto a la fecha. */
    if (fnac && edadTxt) {
        var ponerEdad = function () {
            var e = edadDe(fnac.value);
            edadTxt.textContent = isNaN(e) ? "--" : e + (e === 1 ? " año" : " años");
        };
        fnac.addEventListener("input", ponerEdad);
        ponerEdad();
    }

    /* Celular: sólo dígitos. */
    if (celular) {
        celular.addEventListener("input", function () { this.value = this.value.replace(/\D/g, "").slice(0, 10); });
    }
    if (tipo && ident) {
        tipo.addEventListener("change", function () {
            ident.placeholder = tipo.value === "CED" ? "10 dígitos" : "Número de pasaporte";
            ident.classList.remove("is-invalid", "is-valid");
        });
    }

    function validarDatos() {
        var ok = true;
        ["primernombre", "apellidopaterno", "direccion"].forEach(function (id) {
            var el = $(id);
            if (el) ok = marcar(el, el.value.trim().length >= 2, "Este campo es obligatorio") && ok;
        });

        var v = ident.value.trim().toUpperCase();
        ok = marcar(ident, tipo.value === "CED" ? cedulaValida(v) : /^[A-Z0-9]{5,13}$/.test(v),
                    tipo.value === "CED" ? "La cédula no es válida" : "Entre 5 y 13 letras o números") && ok;

        var e = edadDe(fnac.value);
        ok = marcar(fnac, !isNaN(e) && e >= EDAD_MINIMA && e <= 100,
                    isNaN(e) ? "Seleccione la fecha de nacimiento"
                             : "Esta inscripción es sólo para mayores de " + EDAD_MINIMA + " años") && ok;

        ok = marcar(correo, /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.value.trim()), "Ingrese un correo válido") && ok;
        ok = marcar(celular, /^09\d{8}$/.test(celular.value.trim()), "Debe iniciar con 09 y tener 10 dígitos") && ok;

        var genero = document.querySelector('input[name="genero"]:checked');
        var avisoGenero = $("aviso_genero");
        if (avisoGenero) avisoGenero.style.display = genero ? "none" : "block";
        ok = !!genero && ok;

        if (!ok) {
            var primero = document.querySelector("#seccion-datos .is-invalid");
            if (primero) primero.focus();
        }
        return ok;
    }

    /* Foto: vista previa. */
    var foto = $("foto"), prev = $("foto_preview"), ph = $("foto_placeholder");
    if (foto && prev) {
        foto.addEventListener("change", function () {
            var f = this.files[0];
            if (!f) return;
            if (["image/jpeg", "image/png"].indexOf(f.type) === -1) {
                Swal.fire("Formato no válido", "Sólo se permiten imágenes JPG o PNG.", "error"); this.value = ""; return;
            }
            if (f.size > 4 * 1024 * 1024) {
                Swal.fire("Archivo muy grande", "La imagen no debe superar los 4 MB.", "error"); this.value = ""; return;
            }
            var r = new FileReader();
            r.onload = function (ev) { prev.src = ev.target.result; prev.style.display = "block"; if (ph) ph.style.display = "none"; };
            r.readAsDataURL(f);
        });
    }

    /* Pasos. */
    function mostrar(id) { new bootstrap.Tab($(id)).show(); }
    var sig = $("btn_to_autorizacion"), atras = $("btn_to_datos");
    if (sig) sig.addEventListener("click", function () {
        var bien = validarDatos();
        $("tab-datos").classList.toggle("cz-hecho", bien);
        if (bien) mostrar("tab-autorizacion");
    });
    if (atras) atras.addEventListener("click", function () { mostrar("tab-datos"); });

    /* Autorizaciones: el botón se habilita con las dos. */
    var form = $("formAdulto"), btn = $("btn_submit"), cargando = $("btn_loading");
    var casillas = document.querySelectorAll(".consentimiento-obligatorio"), aviso = $("aviso_consentimientos");
    function revisar() {
        var faltan = 0;
        casillas.forEach(function (c) { if (!c.checked) faltan++; });
        if (btn) btn.disabled = faltan > 0;
        if (aviso) {
            aviso.classList.toggle("alert-warning", faltan > 0);
            aviso.classList.toggle("alert-success", faltan === 0);
            aviso.querySelector("i").className = faltan > 0 ? "fas fa-triangle-exclamation mt-1" : "fas fa-circle-check mt-1";
            aviso.querySelector("div").innerHTML = faltan > 0
                ? "Debe aceptar <strong>las dos autorizaciones</strong> para completar la inscripción."
                : "Autorizaciones aceptadas. Ya puede registrar su inscripción.";
        }
    }
    casillas.forEach(function (c) { c.addEventListener("change", revisar); });
    revisar();

    if (form) form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!validarDatos()) { mostrar("tab-datos"); return; }

        if (btn) btn.style.display = "none";
        if (cargando) cargando.classList.add("active");

        fetch(form.action, { method: "POST", body: new FormData(form), credentials: "same-origin" })
            .then(function (r) { return r.json().catch(function () { throw new Error("HTTP " + r.status); }); })
            .then(function (data) {
                if (btn) btn.style.display = "inline-block";
                if (cargando) cargando.classList.remove("active");
                if (data.tipo === "exito") {
                    Swal.fire({ title: data.titulo, text: data.texto, icon: data.icono,
                                allowOutsideClick: false, confirmButtonColor: COLOR })
                        .then(function () {
                            $("formContainer").style.display = "none";
                            $("successContainer").style.display = "block";
                            window.scrollTo(0, 0);
                        });
                } else {
                    Swal.fire({ title: data.titulo, text: data.texto, icon: data.icono, confirmButtonColor: COLOR });
                }
            })
            .catch(function () {
                if (btn) btn.style.display = "inline-block";
                if (cargando) cargando.classList.remove("active");
                Swal.fire("Error de conexión", "No se pudo enviar el formulario. Verifique su conexión a internet.", "error");
            });
    });

    /* Reloj del enlace. */
    var reloj = $("cz_timer");
    if (reloj && typeof TOKEN_EXPIRY_TIMESTAMP !== "undefined") {
        var intervalo = null;
        var tic = function () {
            var resta = TOKEN_EXPIRY_TIMESTAMP - Math.floor(Date.now() / 1000);
            if (resta <= 0) {
                reloj.className = "cz-timer cz-timer--fin";
                reloj.innerHTML = '<i class="fas fa-clock" aria-hidden="true"></i> Enlace expirado';
                clearInterval(intervalo);
                if (form) form.querySelectorAll("input, select, textarea, button").forEach(function (el) { el.disabled = true; });
                Swal.fire({ title: "Enlace expirado", icon: "warning", allowOutsideClick: false, confirmButtonColor: COLOR,
                            text: "El tiempo para completar el formulario ha finalizado. Solicite un nuevo enlace a la escuela." });
                return;
            }
            reloj.className = "cz-timer" + (resta < 900 ? " cz-timer--pronto" : "");
            var d = Math.floor(resta / 86400), h = Math.floor(resta % 86400 / 3600),
                m = Math.floor(resta % 3600 / 60), s = resta % 60;
            reloj.innerHTML = '<i class="fas fa-clock" aria-hidden="true"></i> ' + (d > 0 ? d + "d " : "")
                + String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0")
                + '<span class="visually-hidden"> restantes para completar el formulario</span>';
        };
        tic();
        intervalo = setInterval(tic, 1000);
    }
});
