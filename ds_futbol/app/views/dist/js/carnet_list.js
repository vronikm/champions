$(function () {
    /* Tabla en escritorio, fichas en el teléfono (futbol.css, .ds-crud).
       Primero los que faltan por imprimir; «Mostrar N» se recuerda. */
    var CLAVE_LARGO = 'ds-carnets-por-pagina', LARGOS = [10, 25, 50, 100, -1], largo = 25;
    try { var g = parseInt(localStorage.getItem(CLAVE_LARGO), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

    var tablaCarnets = new DataTable("#example1", {
        autoWidth: false,
        pageLength: largo,
        lengthMenu: [LARGOS, [10, 25, 50, 100, 'Todos']],
        order: [[3, 'asc'], [0, 'asc']],
        columnDefs: [{ targets: 4, orderable: false, searchable: false }],
        layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: { paging: { firstLast: false } } },
        language: {
            // Los alumnos sin horario no se listan: los avisa la vista, encima.
            emptyTable: "No hay alumnos con pago de pensión y horario este mes",
            info: "_START_ a _END_ de _TOTAL_ alumnos",
            infoEmpty: "Sin alumnos",
            infoFiltered: "(de _MAX_)",
            lengthMenu: "Mostrar _MENU_",
            search: "",
            searchPlaceholder: "Buscar nombre o cédula…",
            zeroRecords: "Ningún alumno coincide",
            paginate: { next: "Siguiente", previous: "Anterior" }
        }
    });
    tablaCarnets.on('length', function (e, s, nuevo) {
        try { localStorage.setItem(CLAVE_LARGO, String(nuevo)); } catch (err) {}
    });
    /* «Marcar los impresos de esta página» refleja la página que se ve:
       al paginar o buscar cambian las filas (carnet_seleccion.js). */
    tablaCarnets.on('draw', function () {
        if (typeof verificarSeleccionTodos === 'function') { verificarSeleccionTodos(); }
    });

    /* La sede se aplica al elegirla: recarga la lista y el alcance de la emisión. */
    $('[data-autoenvio]').on('change', function () { this.form.submit(); });

    /* Sede de la emisión (0 = todas las del usuario) y su nombre, para confirmar. */
    var SEDE = (typeof SEDE_CARNET !== 'undefined') ? SEDE_CARNET : 0;
    var ALCANCE = $('<span>').text((typeof SEDE_NOMBRE !== 'undefined') ? SEDE_NOMBRE : 'Todas las sedes').html();

    // ✅ FUNCIÓN PARA CONSULTAR CARNETS PENDIENTES VÍA AJAX
    function consultarCarnetsPendientes(callback) {
        $.ajax({
            url: APP_URL + 'app/ajax/carnetAjax.php',
            type: 'POST',
            data: {
                modulo_carnet: 'imprimir_carnetspendientes',
                sede: SEDE
            },
            dataType: 'json',
            success: function(response) {
                if(response.tipo === 'success') {
                    callback(response.total);
                } else {
                    console.error('Error al obtener carnets pendientes');
                    callback(0);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                callback(0);
            }
        });
    }
    
    // ✅ ACTUALIZAR CONTADOR AL CARGAR LA PÁGINA
    consultarCarnetsPendientes(function(total) {
        $('#contadorCarnets').html(total);
    });
    
    // ✅ BOTÓN IMPRIMIR TODOS CON CONSULTA AJAX
    $('#btnImprimirTodos').on('click', function() {
        var btn = $(this);
        var badge = $('#contadorCarnets');
        
        // Deshabilitar botón mientras consulta
        btn.prop('disabled', true);
        badge.html('<i class="fas fa-spinner fa-spin"></i>');
        
        // Consultar carnets pendientes en tiempo real
        consultarCarnetsPendientes(function(totalPendientes) {
            // Actualizar badge
            badge.html(totalPendientes);
            
            // Habilitar botón
            btn.prop('disabled', false);
            
            // Validar si hay carnets
            if(totalPendientes === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin carnets pendientes',
                    html: `
                        <div style="padding: 15px;">
                            <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745;"></i>
                            <p style="margin-top: 15px; font-size: 16px;">
                                No hay carnets pendientes de impresión este mes
                            </p>
                            <p style="color: #6c757d; font-size: 14px;">
                                Todos los carnets ya han sido generados
                            </p>
                        </div>
                    `,
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            // Mostrar modal de confirmación
            Swal.fire({
            title: '¿Imprimir carnets?',
            html: `
                <div style="text-align: left; padding: 12px;">
                    <p style="margin-bottom: 10px;">
                        <i class="fas fa-print" style="color: #28a745; font-size: 18px;"></i> 
                        <strong>Carnets pendientes de impresión:</strong>
                    </p>

                    <p style="text-align: center; margin: 12px 0;">
                        <span style="color: #28a745; font-size: 42px; font-weight: bold;">${totalPendientes}</span>
                    </p>

                    <hr style="margin: 12px 0;">

                    <p style="margin-bottom: 6px;">
                        <i class="fas fa-file-pdf" style="color: #dc3545;"></i> 
                        Se generará un archivo PDF
                    </p>
                    <p style="margin-bottom: 6px;">
                        <i class="fas fa-layer-group" style="color: #17a2b8;"></i> 
                        Formato: <strong>10 carnets por hoja A4</strong>
                    </p>
                   <p style="margin-bottom: 6px;">
                        <i class="fas fa-calendar-alt" style="color: #ffc107;"></i>
                        Mes: <strong>${MES_ACTUAL}</strong>
                    </p>
                    <p style="margin-bottom: 0;">
                        <i class="fas fa-map-marker-alt" style="color: #6c757d;"></i>
                        Sede: <strong>${ALCANCE}</strong>
                    </p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Sí, imprimir',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            customClass: {
                confirmButton: 'btn btn-success btn-sm mx-1',
                cancelButton: 'btn btn-secondary btn-sm mx-1'
            },
            buttonsStyling: false,
            width: '420px',
            padding: '1em'

            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar mensaje de generación
                    Swal.fire({
                        title: 'Generando PDF...',
                        html: `
                            <div style="padding: 20px;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #28a745;"></i>
                                <p style="margin-top: 15px; font-size: 16px;">
                                    Preparando <strong>${totalPendientes}</strong> carnets para impresión
                                </p>
                                <p style="color: #6c757d; font-size: 14px; margin-top: 10px;">
                                    Por favor espere...
                                </p>
                            </div>
                        `,
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Preparar lote exacto y abrir PDF en nueva ventana
                    var ventanaPDF = window.open('', '_blank');
                    if(ventanaPDF) {
                        ventanaPDF.document.write('<p style="font-family: Arial; padding: 20px;">Preparando carnets...</p>');
                    }

                    $.ajax({
                        url: APP_URL + 'app/ajax/carnetAjax.php',
                        type: 'POST',
                        data: {
                            modulo_carnet: 'preparar_impresion_mensual',
                            sede: SEDE
                        },
                        dataType: 'json',
                        success: function(response) {
                            if(response.tipo === 'redireccionar' && response.url) {
                                if(ventanaPDF) {
                                    ventanaPDF.location.href = response.url;
                                } else {
                                    window.open(response.url, '_blank');
                                }

                                Swal.fire({
                                    icon: 'success',
                                    title: 'PDF Generado',
                                    html: `
                                        <div style="padding: 15px;">
                                            <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745;"></i>
                                            <p style="margin-top: 15px; font-size: 16px;">
                                                Los carnets se han enviado a impresion
                                            </p>
                                        </div>
                                    `,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                if(ventanaPDF) {
                                    ventanaPDF.close();
                                }

                                Swal.fire({
                                    icon: response.icono || 'info',
                                    title: response.titulo || 'Aviso',
                                    text: response.texto || 'No hay carnets para imprimir',
                                    confirmButtonColor: '#3085d6'
                                }).then(() => {
                                    btn.prop('disabled', false);
                                });
                            }
                        },
                        error: function() {
                            if(ventanaPDF) {
                                ventanaPDF.close();
                            }

                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'No se pudo preparar el PDF de carnets',
                                confirmButtonColor: '#3085d6'
                            }).then(() => {
                                btn.prop('disabled', false);
                            });
                        }
                    });
                }
            });
        });
    });

    /* ================= Impresión atrasada (modal #modalAtrasada) =================
       Antes era un SweetAlert con estilos en línea: en el teléfono el
       formulario quedaba estrecho y los avisos del servidor llegaban en una
       sola línea separada por «;». Ahora: pantalla completa en el teléfono,
       las cédulas se cuentan al escribirlas y los avisos son una lista. */
    var modalAtrasadaEl = document.getElementById('modalAtrasada');
    var modalAtrasada = modalAtrasadaEl ? bootstrap.Modal.getOrCreateInstance(modalAtrasadaEl) : null;
    var $cedulas = $('#atrasadaCedulas'), $detectadas = $('#atrasadaDetectadas'),
        $errorAt = $('#atrasadaError'), $resultado = $('#atrasadaResultado'), $enviar = $('#atrasadaEnviar');
    var escapar = function (t) { return $('<span>').text(t == null ? '' : String(t)).html(); };

    /* Lo mismo que extrae el servidor: grupos de 6 a 20 cifras, sin repetir. */
    function cedulasEscritas() {
        var vistas = {}, lista = [];
        ($cedulas.val().match(/\d{6,20}/g) || []).forEach(function (c) { if (!vistas[c]) { vistas[c] = 1; lista.push(c); } });
        return lista;
    }

    function pintarDetectadas() {
        var lista = cedulasEscritas();
        $errorAt.prop('hidden', true);
        $cedulas.removeClass('is-invalid');
        if (!lista.length) { $detectadas.prop('hidden', true).empty(); return; }
        var html = '<span class="ds-atrasada__cuenta">' + lista.length + (lista.length === 1 ? ' cédula' : ' cédulas') + '</span>';
        lista.slice(0, 12).forEach(function (c) { html += '<span class="badge text-bg-secondary">' + c + '</span>'; });
        if (lista.length > 12) { html += '<span class="small text-body-secondary">y ' + (lista.length - 12) + ' más</span>'; }
        $detectadas.html(html).prop('hidden', false);
    }

    function pintarResultado(r, ok) {
        var avisos = Array.isArray(r.avisos) ? r.avisos : [];
        var html = '<div class="alert ' + (ok ? (avisos.length ? 'alert-warning' : 'alert-success') : 'alert-warning') + ' mb-0">'
            + '<strong>' + escapar(ok ? 'Se prepararon ' + (r.total || '') + ' carnets' : (r.titulo || 'Sin carnets para imprimir')) + '</strong>';
        if (ok) { html += '<div class="small">El PDF se abrió en otra pestaña.</div>'; }
        if (!ok && !avisos.length && r.texto) { html += '<div class="small">' + escapar(r.texto) + '</div>'; }
        if (avisos.length) {
            html += '<div class="small mt-2">' + (ok ? 'No se incluyeron:' : 'Revise:') + '</div><ul class="small mb-0 ps-3">';
            avisos.forEach(function (a) { html += '<li>' + escapar(a) + '</li>'; });
            html += '</ul>';
        }
        $resultado.html(html + '</div>').prop('hidden', false);
        if ($resultado[0].scrollIntoView) { $resultado[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
    }

    $('#btn-impresion-atrasada').on('click', function () {
        $resultado.prop('hidden', true).empty();
        pintarDetectadas();
        if (modalAtrasada) { modalAtrasada.show(); }
    });
    if (modalAtrasadaEl) {
        /* En el teléfono el teclado tapaba el campo si se enfocaba antes de abrir. */
        /* En el siguiente ciclo: en el mismo evento «shown» algo posterior (el
           foco de Bootstrap) lo llevaba a la «×» y había que tocar el campo. */
        modalAtrasadaEl.addEventListener('shown.bs.modal', function () { setTimeout(function () { $cedulas[0].focus(); }, 0); });
    }
    $cedulas.on('input', function () { $resultado.prop('hidden', true); pintarDetectadas(); });

    $('#formAtrasada').on('submit', function (e) {
        e.preventDefault();
        var lista = cedulasEscritas();
        if (!lista.length) {
            $cedulas.addClass('is-invalid').trigger('focus');
            $errorAt.text('Escriba al menos una cédula (6 a 20 cifras).').prop('hidden', false);
            return;
        }

        /* La pestaña del PDF se abre AQUÍ, con el clic: abrirla después de la
           respuesta la bloquearía el navegador del teléfono. */
        var ventanaPDF = window.open('', '_blank');
        if (ventanaPDF) { ventanaPDF.document.write('<p style="font-family: Arial; padding: 20px;">Preparando carnets atrasados…</p>'); }

        var textoBoton = $enviar.html();
        $enviar.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Preparando…');
        $resultado.prop('hidden', true).empty();

        $.ajax({
            url: APP_URL + 'app/ajax/carnetAjax.php',
            type: 'POST',
            data: { modulo_carnet: 'preparar_impresion_atrasada', cedulas: lista.join(','), mes: $('#atrasadaMes').val(), anio: $('#atrasadaAnio').val() },
            dataType: 'json'
        }).done(function (r) {
            if (r.tipo === 'redireccionar' && r.url) {
                if (ventanaPDF) { ventanaPDF.location.href = r.url; } else { window.open(r.url, '_blank'); }
                pintarResultado(r, true);
            } else {
                if (ventanaPDF) { ventanaPDF.close(); }
                pintarResultado(r, false);
            }
        }).fail(function () {
            if (ventanaPDF) { ventanaPDF.close(); }
            pintarResultado({ titulo: 'No se pudo preparar la impresión', texto: 'Revise la conexión e inténtelo de nuevo.' }, false);
        }).always(function () {
            $enviar.prop('disabled', false).html(textoBoton);
        });
    });
    
    // Seleccionar/deseleccionar todos
    $('#seleccionarTodos').on('change', function() {
        $('.chk-reimpresion').prop('checked', $(this).prop('checked'));
    });
    
    // Actualizar checkbox principal si se deselecciona alguno
    $('.chk-reimpresion').on('change', function() {
        if(!$(this).prop('checked')) {
            $('#seleccionarTodos').prop('checked', false);
        }
        
        // Si todos están marcados, marcar el principal
        if($('.chk-reimpresion:checked').length === $('.chk-reimpresion').length) {
            $('#seleccionarTodos').prop('checked', true);
        }
    });
});
