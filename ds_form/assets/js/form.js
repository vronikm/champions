/**
 * Formulario público de inscripción — Caballito Zeballos
 * Validación del lado del cliente + envío AJAX.
 *
 * Nota: todas estas validaciones se repiten en el servidor. Acá existen
 * solo para que el representante no descubra los errores al enviar.
 */
document.addEventListener("DOMContentLoaded", function () {

    /* ═══════════════════════════════════════════
       VALIDACIÓN DE CÉDULA ECUATORIANA
    ═══════════════════════════════════════════ */
    function validarCedulaEC(cedula) {
        cedula = cedula.replace(/\D/g, "");
        if (cedula.length !== 10) return false;
        if (/^(.)\1{9}$/.test(cedula)) return false;

        var provincia = parseInt(cedula.substring(0, 2), 10);
        if (provincia < 1 || provincia > 24) return false;

        var tercerDigito = parseInt(cedula[2], 10);
        if (tercerDigito > 5) return false;

        var suma = 0;
        for (var i = 0; i < 9; i++) {
            var digito = parseInt(cedula[i], 10);
            if (i % 2 === 0) {
                digito *= 2;
                if (digito > 9) digito -= 9;
            }
            suma += digito;
        }

        var verificador = 10 - (suma % 10);
        if (verificador === 10) verificador = 0;

        return verificador === parseInt(cedula[9], 10);
    }

    function validarCelularEC(celular) {
        return /^09[0-9]{8}$/.test(celular.trim());
    }

    /* ═══════════════════════════════════════════
       UTILIDADES
    ═══════════════════════════════════════════ */
    function setFieldState(input, isValid, msg) {
        var feedback = input.parentElement.querySelector(".invalid-feedback");
        if (isValid) {
            input.classList.remove("is-invalid");
            input.classList.add("is-valid");
        } else {
            input.classList.remove("is-valid");
            input.classList.add("is-invalid");
            if (feedback && msg) feedback.textContent = msg;
        }
        return isValid;
    }

    function clearFieldState(input) {
        input.classList.remove("is-valid", "is-invalid");
    }

    /* ═══════════════════════════════════════════
       VALIDACIONES EN TIEMPO REAL
    ═══════════════════════════════════════════ */
    var repreCedula = document.getElementById("repre_identificacion");
    var repreTipo   = document.getElementById("repre_tipoidentificacion");

    if (repreCedula) {
        repreCedula.addEventListener("input", function () {
            var esCedula = repreTipo && repreTipo.value === "CED";
            if (esCedula) this.value = this.value.replace(/\D/g, "");

            var val = this.value.trim();
            if (!esCedula) {
                val.length > 0 ? setFieldState(this, true) : clearFieldState(this);
            } else if (val.length === 10) {
                setFieldState(this, validarCedulaEC(val), "Cédula ecuatoriana no válida");
            } else if (val.length > 0) {
                setFieldState(this, false, "La cédula debe tener 10 dígitos");
            } else {
                clearFieldState(this);
            }
        });
    }

    var repreCorreo = document.getElementById("repre_correo");
    if (repreCorreo) {
        repreCorreo.addEventListener("blur", function () {
            var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.trim() !== "") {
                setFieldState(this, emailRe.test(this.value), "Ingrese un correo válido");
            }
        });
    }

    var repreCelular = document.getElementById("repre_celular");
    if (repreCelular) {
        repreCelular.addEventListener("input", function () {
            var val = this.value.replace(/\D/g, "");
            this.value = val;
            if (val.length === 10) {
                setFieldState(this, validarCelularEC(val), "Debe iniciar con 09 y tener 10 dígitos");
            } else if (val.length > 0) {
                setFieldState(this, false, "Debe tener 10 dígitos (ej: 0991234567)");
            } else {
                clearFieldState(this);
            }
        });
    }

    var alumnoCedula = document.getElementById("alumno_identificacion");
    var alumnoTipo   = document.getElementById("alumno_tipoidentificacion");

    if (alumnoCedula) {
        alumnoCedula.addEventListener("input", function () {
            var esCedula = alumnoTipo && alumnoTipo.value === "CED";
            if (esCedula) this.value = this.value.replace(/\D/g, "");

            var val = this.value.trim();
            if (!esCedula) {
                val.length > 0 ? setFieldState(this, true) : clearFieldState(this);
            } else if (val.length === 10) {
                setFieldState(this, validarCedulaEC(val), "Cédula ecuatoriana no válida");
            } else if (val.length > 0) {
                setFieldState(this, false, "La cédula debe tener 10 dígitos");
            } else {
                clearFieldState(this);
            }
        });
    }

    // Al cambiar el tipo de documento se revalida lo ya escrito
    [[repreTipo, repreCedula], [alumnoTipo, alumnoCedula]].forEach(function (par) {
        if (par[0] && par[1]) {
            par[0].addEventListener("change", function () {
                clearFieldState(par[1]);
                par[1].dispatchEvent(new Event("input"));
            });
        }
    });

    /* ═══════════════════════════════════════════
       PREVIEW DE FOTO DEL ALUMNO
    ═══════════════════════════════════════════ */
    var fotoInput       = document.getElementById("alumno_foto");
    var fotoPreview     = document.getElementById("foto_preview");
    var fotoPlaceholder = document.getElementById("foto_placeholder");

    if (fotoInput && fotoPreview) {
        fotoInput.addEventListener("change", function () {
            var file = this.files[0];
            if (!file) return;

            if (["image/jpeg", "image/png"].indexOf(file.type) === -1) {
                Swal.fire("Formato no válido", "Solo se permiten imágenes JPG o PNG.", "error");
                this.value = "";
                return;
            }

            if (file.size > 4 * 1024 * 1024) {
                Swal.fire("Archivo muy grande", "La imagen no debe superar los 4 MB.", "error");
                this.value = "";
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                fotoPreview.src = e.target.result;
                fotoPreview.style.display = "block";
                if (fotoPlaceholder) fotoPlaceholder.style.display = "none";
            };
            reader.readAsDataURL(file);
        });
    }

    /* ═══════════════════════════════════════════
       VALIDACIÓN POR SECCIÓN
    ═══════════════════════════════════════════ */
    function validarSeccionRepresentante() {
        var valid  = true;
        var campos = ["repre_identificacion", "repre_primernombre", "repre_apellidopaterno",
                      "repre_direccion", "repre_correo", "repre_celular", "repre_parentesco"];

        campos.forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.value.trim() === "") {
                setFieldState(el, false, "Este campo es obligatorio");
                valid = false;
            }
        });

        if (repreTipo && repreTipo.value === "CED" && repreCedula) {
            if (!validarCedulaEC(repreCedula.value)) {
                setFieldState(repreCedula, false, "Cédula ecuatoriana no válida");
                valid = false;
            }
        }

        if (repreCorreo && repreCorreo.value.trim() !== "") {
            var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRe.test(repreCorreo.value)) {
                setFieldState(repreCorreo, false, "Ingrese un correo válido");
                valid = false;
            }
        }

        if (repreCelular && repreCelular.value.trim() !== "") {
            if (!validarCelularEC(repreCelular.value)) {
                setFieldState(repreCelular, false, "Debe iniciar con 09 y tener 10 dígitos");
                valid = false;
            }
        }

        if (!valid) {
            Swal.fire("Campos incompletos", "Complete correctamente los datos del representante.", "warning");
        }

        return valid;
    }

    function validarSeccionAlumno() {
        var valid  = true;
        var campos = ["alumno_identificacion", "alumno_primernombre",
                      "alumno_apellidopaterno", "alumno_fechanacimiento"];

        campos.forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.value.trim() === "") {
                setFieldState(el, false, "Este campo es obligatorio");
                valid = false;
            }
        });

        if (!document.querySelector('input[name="alumno_genero"]:checked')) {
            valid = false;
        }

        if (alumnoTipo && alumnoTipo.value === "CED" && alumnoCedula) {
            if (alumnoCedula.value.trim() !== "" && !validarCedulaEC(alumnoCedula.value)) {
                setFieldState(alumnoCedula, false, "Cédula ecuatoriana no válida");
                valid = false;
            }
        }

        if (!valid) {
            Swal.fire("Campos incompletos", "Complete correctamente los datos del alumno, incluido el género.", "warning");
        }

        return valid;
    }

    /* ═══════════════════════════════════════════
       NAVEGACIÓN ENTRE SECCIONES
    ═══════════════════════════════════════════ */
    function mostrarTab(id) {
        new bootstrap.Tab(document.getElementById(id)).show();
    }

    var btnToAlumno       = document.getElementById("btn_to_alumno");
    var btnToRepre        = document.getElementById("btn_to_repre");
    var btnToAutorizacion = document.getElementById("btn_to_autorizacion");
    var btnToAlumnoBack   = document.getElementById("btn_to_alumno_back");

    if (btnToAlumno) {
        btnToAlumno.addEventListener("click", function () {
            var bien = validarSeccionRepresentante();
            marcarPaso("tab-repre", bien);
            if (bien) mostrarTab("tab-alumno");
        });
    }

    if (btnToRepre) {
        btnToRepre.addEventListener("click", function () { mostrarTab("tab-repre"); });
    }

    if (btnToAutorizacion) {
        btnToAutorizacion.addEventListener("click", function () {
            var bien = validarSeccionAlumno();
            marcarPaso("tab-alumno", bien);
            if (bien) mostrarTab("tab-autorizacion");
        });
    }

    if (btnToAlumnoBack) {
        btnToAlumnoBack.addEventListener("click", function () { mostrarTab("tab-alumno"); });
    }

    /* ═══════════════════════════════════════════
       CONSENTIMIENTOS LOPDP OBLIGATORIOS
       El botón de registro no se habilita hasta que ambos estén marcados.
       El servidor lo vuelve a exigir: esto es solo para que se entienda.
    ═══════════════════════════════════════════ */
    var form       = document.getElementById("formRegistro");
    var btnSubmit  = document.getElementById("btn_submit");
    var btnLoading = document.getElementById("btn_loading");

    var consentimientos = document.querySelectorAll(".consentimiento-obligatorio");
    var avisoConsent    = document.getElementById("aviso_consentimientos");

    function revisarConsentimientos() {
        var faltan = 0;
        consentimientos.forEach(function (c) { if (!c.checked) faltan++; });

        if (btnSubmit) btnSubmit.disabled = faltan > 0;

        if (avisoConsent) {
            avisoConsent.classList.toggle("alert-warning", faltan > 0);
            avisoConsent.classList.toggle("alert-success", faltan === 0);
            avisoConsent.querySelector("i").className = faltan > 0
                ? "fas fa-triangle-exclamation mt-1"
                : "fas fa-circle-check mt-1";
            avisoConsent.querySelector("div").innerHTML = faltan > 0
                ? "Debe aceptar <strong>las dos autorizaciones</strong> para poder completar la inscripción."
                : "Autorizaciones aceptadas. Ya puede registrar la inscripción.";
        }
    }

    if (consentimientos.length) {
        consentimientos.forEach(function (c) {
            c.addEventListener("change", revisarConsentimientos);
        });
        revisarConsentimientos();
    }

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            if (!validarSeccionRepresentante()) { mostrarTab("tab-repre");  return; }
            if (!validarSeccionAlumno())        { mostrarTab("tab-alumno"); return; }

            var aceptaDatos  = document.getElementById("acepta_datos");
            var aceptaImagen = document.getElementById("acepta_imagen");

            if (!aceptaDatos || !aceptaDatos.checked) {
                Swal.fire("Autorización requerida", "Debe aceptar el consentimiento para el tratamiento de datos personales conforme a la LOPDP.", "warning");
                return;
            }

            if (!aceptaImagen || !aceptaImagen.checked) {
                Swal.fire("Autorización requerida", "Debe autorizar el uso de la imagen del alumno/a para completar la inscripción.", "warning");
                return;
            }

            if (btnSubmit)  btnSubmit.style.display = "none";
            if (btnLoading) btnLoading.classList.add("active");

            fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                credentials: "same-origin"
            })
            .then(function (response) {
                // El servidor responde JSON incluso en 403/410
                return response.json().catch(function () {
                    throw new Error("HTTP " + response.status);
                });
            })
            .then(function (data) {
                if (btnSubmit)  btnSubmit.style.display = "inline-block";
                if (btnLoading) btnLoading.classList.remove("active");

                if (data.tipo === "exito") {
                    Swal.fire({
                        title: data.titulo,
                        text: data.texto,
                        icon: data.icono,
                        allowOutsideClick: false,
                        confirmButtonColor: "#012144"
                    }).then(function () {
                        document.getElementById("formContainer").style.display = "none";
                        document.getElementById("successContainer").style.display = "block";
                        window.scrollTo(0, 0);
                    });
                } else {
                    Swal.fire({
                        title: data.titulo,
                        text: data.texto,
                        icon: data.icono,
                        confirmButtonColor: "#012144"
                    });
                }
            })
            .catch(function () {
                if (btnSubmit)  btnSubmit.style.display = "inline-block";
                if (btnLoading) btnLoading.classList.remove("active");

                Swal.fire("Error de conexión", "No se pudo enviar el formulario. Verifique su conexión a internet.", "error");
            });
        });
    }

    /* ═══════════════════════════════════════════
       PASOS YA COMPLETADOS
    ═══════════════════════════════════════════
       Sólo se avanza de paso si el anterior valida —eso ya lo hacía
       validarRepresentante() y validarAlumno()—, así que llegar al paso 3
       significa que el 1 y el 2 están correctos. El indicador lo dice: los
       pasos que quedan atrás se marcan con un visto.

       Sin esto, el indicador sólo decía dónde se está, y quien vuelve al
       paso 1 a corregir un dato no sabe si el 2 sigue completo. */
    /*
    | Se marca cuando la validación PASA, no cuando se cambia de pestaña.
    |
    | Las pestañas son botones y se pueden pulsar directamente, así que
    | llegar al paso 3 no demuestra que el 1 y el 2 estén bien: se puede
    | saltar. Marcar por «pestaña mostrada» pondría un visto verde sobre
    | secciones vacías, que es peor que no poner nada —el servidor las
    | rechazaría después y el representante no entendería por qué—.
    |
    | El visto se quita si la validación vuelve a fallar.
    */
    function marcarPaso(idTab, hecho) {
        var b = document.getElementById(idTab);
        if (b) { b.classList.toggle("cz-hecho", !!hecho); }
    }

    /* ═══════════════════════════════════════════
       TEMPORIZADOR DE EXPIRACIÓN
    ═══════════════════════════════════════════ */
    var timerEl = document.getElementById("cz_timer");

    if (timerEl && typeof TOKEN_EXPIRY_TIMESTAMP !== "undefined") {
        var timerInterval = null;

        function actualizarTimer() {
            var resta = TOKEN_EXPIRY_TIMESTAMP - Math.floor(Date.now() / 1000);

            if (resta <= 0) {
                timerEl.className = "cz-timer cz-timer--fin";
                timerEl.setAttribute("aria-live", "assertive");
                timerEl.innerHTML = '<i class="fas fa-clock" aria-hidden="true"></i> Enlace expirado';
                clearInterval(timerInterval);

                if (form) {
                    form.querySelectorAll("input, select, textarea, button")
                        .forEach(function (el) { el.disabled = true; });
                }

                Swal.fire({
                    title: "Enlace expirado",
                    text: "El tiempo para completar el formulario ha finalizado. Solicite un nuevo enlace a la escuela.",
                    icon: "warning",
                    allowOutsideClick: false,
                    confirmButtonColor: "#012144"
                });
                return;
            }

            var dias     = Math.floor(resta / 86400);
            var horas    = Math.floor((resta % 86400) / 3600);
            var minutos  = Math.floor((resta % 3600) / 60);
            var segundos = resta % 60;

            /*
            | El reloj cambia de aspecto según lo que quede.
            |
            | Antes era un texto tenue y del mismo color siempre, y cuando el
            | enlace caducaba a mitad de formulario el representante perdía lo
            | escrito sin haber tenido ningún aviso. Por debajo de quince
            | minutos se pone naranja; por debajo de cinco se anuncia también
            | a los lectores de pantalla, que hasta ese momento lo ignoran a
            | propósito para no leer la hora cada segundo.
            */
            var clase = "cz-timer";
            if (resta < 900) { clase += " cz-timer--pronto"; }
            if (timerEl.className !== clase) { timerEl.className = clase; }

            var viva = resta < 300 ? "assertive" : "off";
            if (timerEl.getAttribute("aria-live") !== viva) {
                timerEl.setAttribute("aria-live", viva);
            }

            timerEl.innerHTML = '<i class="fas fa-clock" aria-hidden="true"></i> ' +
                (dias > 0 ? dias + "d " : "") +
                String(horas).padStart(2, "0") + ":" +
                String(minutos).padStart(2, "0") + ":" +
                String(segundos).padStart(2, "0") +
                '<span class="visually-hidden"> restantes para completar el formulario</span>';
        }

        actualizarTimer();
        timerInterval = setInterval(actualizarTimer, 1000);
    }
});
