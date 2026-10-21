/**
 * SCRIPT SIMPLIFICADO PARA SELECCIÓN DE CARNETS
 * Compatible con todos los navegadores
 * Guardar como: app/views/dist/js/carnet-seleccion.js
 */

// Variable global para almacenar selecciones
var carnetSeleccionados = [];

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Cargar selecciones guardadas
    cargarSelecciones();

    // Restaurar checkboxes
    restaurarCheckboxes();

    // Limpiar IDs obsoletos (de alumnos que ya no están en la lista)
    limpiarIdsObsoletos();

    // Configurar event listeners
    configurarEventos();

    // Actualizar contador inicial
    actualizarContador();
});

/**
 * Cargar selecciones desde localStorage
 */
function cargarSelecciones() {
    try {
        var stored = localStorage.getItem('carnet_reimpresion_ids');
        if (stored) {
            carnetSeleccionados = JSON.parse(stored);
        } else {
            carnetSeleccionados = [];
        }
    } catch(e) {
        console.error('Error cargando selecciones:', e);
        carnetSeleccionados = [];
    }
}

/**
 * Guardar selecciones en localStorage
 */
function guardarSelecciones() {
    try {
        localStorage.setItem('carnet_reimpresion_ids', JSON.stringify(carnetSeleccionados));
        actualizarContador();
    } catch(e) {
        console.error('Error guardando selecciones:', e);
    }
}

/**
 * Restaurar checkboxes marcados
 */
function restaurarCheckboxes() {
    var checkboxes = document.querySelectorAll('.chk-reimpresion');

    checkboxes.forEach(function(checkbox) {
        var alumnoId = checkbox.value;
        var estaSeleccionado = carnetSeleccionados.indexOf(alumnoId) !== -1;
        checkbox.checked = estaSeleccionado;
    });

    // ✅ Verificar estado del checkbox "seleccionar todos"
    verificarSeleccionTodos();
}

/**
 * Configurar todos los event listeners
 */
function configurarEventos() {
    // Checkboxes de carnets
    var checkboxes = document.querySelectorAll('.chk-reimpresion');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            manejarCheckbox(this);
        });
    });

    // Botón de reimprimir
    var btnReimprimir = document.getElementById('btn-reimprimir-carnets');
    if (btnReimprimir) {
        btnReimprimir.addEventListener('click', function(e) {
            e.preventDefault();
            procesarReimpresion();
        });
    } else {
        console.warn('Botón reimprimir NO encontrado');
    }

    // Botón de limpiar
    var btnLimpiar = document.getElementById('btn-limpiar-seleccion');
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function(e) {
            e.preventDefault();
            limpiarSelecciones();
        });
    }

    // Checkbox seleccionar todos (en header de tabla)
    var chkTodos = document.getElementById('seleccionarTodos');
    if (chkTodos) {
        chkTodos.addEventListener('change', function() {
            seleccionarTodosPagina(this.checked);
        });
    }
}

/**
 * Manejar cambio en checkbox individual
 */
function manejarCheckbox(checkbox) {
    var alumnoId = checkbox.value;

    if (checkbox.checked) {
        // Agregar si no existe
        if (carnetSeleccionados.indexOf(alumnoId) === -1) {
            carnetSeleccionados.push(alumnoId);
        }
    } else {
        // Remover
        var index = carnetSeleccionados.indexOf(alumnoId);
        if (index !== -1) {
            carnetSeleccionados.splice(index, 1);
        }
    }

    guardarSelecciones();
    verificarSeleccionTodos();
}

/**
 * Seleccionar/deseleccionar todos de la página actual
 */
function seleccionarTodosPagina(seleccionar) {
    var checkboxes = document.querySelectorAll('.chk-reimpresion');

    checkboxes.forEach(function(checkbox) {
        checkbox.checked = seleccionar;
        var alumnoId = checkbox.value;

        if (seleccionar) {
            if (carnetSeleccionados.indexOf(alumnoId) === -1) {
                carnetSeleccionados.push(alumnoId);
            }
        } else {
            var index = carnetSeleccionados.indexOf(alumnoId);
            if (index !== -1) {
                carnetSeleccionados.splice(index, 1);
            }
        }
    });

    guardarSelecciones();
}

/**
 * Verificar si todos los checkboxes de la página actual están marcados
 * y actualizar el checkbox "seleccionar todos" en consecuencia
 */
function verificarSeleccionTodos() {
    var checkboxes = document.querySelectorAll('.chk-reimpresion');
    var todosSeleccionados = true;
    var alguno = false;
    var idsEnPagina = [];

    checkboxes.forEach(function(checkbox) {
        idsEnPagina.push(checkbox.value);
        if (!checkbox.checked) {
            todosSeleccionados = false;
        }
        if (checkbox.checked) {
            alguno = true;
        }
    });

    var idsLimpios = [];
    carnetSeleccionados.forEach(function(id) {
        // Solo mantener si existe en la página actual O si no está en la página
        // (puede estar en otra página de la paginación)
        if (idsEnPagina.indexOf(id) === -1 ||
            document.querySelector('.chk-reimpresion[value="' + id + '"]')) {
            idsLimpios.push(id);
        }
    });

    // Si hay diferencia, actualizar
    if (idsLimpios.length !== carnetSeleccionados.length) {
        carnetSeleccionados = idsLimpios;
        localStorage.setItem('carnet_reimpresion_ids', JSON.stringify(carnetSeleccionados));
    }

    var chkTodos = document.getElementById('seleccionarTodos');
    if (chkTodos) {
        // Solo marcar si hay checkboxes Y todos están marcados
        chkTodos.checked = (checkboxes.length > 0 && todosSeleccionados && alguno);
    }
}

/**
 * Actualizar contador y botones
 */
function actualizarContador() {
    var total = carnetSeleccionados.length;

    // Actualizar contador
    var contador = document.getElementById('contador-seleccion');
    if (contador) {
        if (total > 0) {
            contador.textContent = total + ' carnet' + (total > 1 ? 's' : '') + ' seleccionado' + (total > 1 ? 's' : '');
            contador.style.display = 'inline-block';
        } else {
            contador.style.display = 'none';
        }
    }

    // Habilitar/deshabilitar botón reimprimir
    var btnReimprimir = document.getElementById('btn-reimprimir-carnets');
    if (btnReimprimir) {
        btnReimprimir.disabled = (total === 0);
    }

    // Mostrar/ocultar botón limpiar
    var btnLimpiar = document.getElementById('btn-limpiar-seleccion');
    if (btnLimpiar) {
        btnLimpiar.style.display = total > 0 ? 'inline-block' : 'none';
    }
}

/**
 * Limpiar todas las selecciones
 */
function limpiarSelecciones() {
    carnetSeleccionados = [];
    localStorage.removeItem('carnet_reimpresion_ids');

    // Desmarcar todos los checkboxes
    var checkboxes = document.querySelectorAll('.chk-reimpresion');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });

    // Desmarcar checkbox "seleccionar todos" del header de tabla
    var chkTodos = document.getElementById('seleccionarTodos');
    if (chkTodos) {
        chkTodos.checked = false;
    }

    actualizarContador();

    // Mostrar mensaje
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'info',
            title: 'Selección limpiada',
            text: 'Se han deseleccionado todos los carnets',
            timer: 2000,
            showConfirmButton: false
        });
    } else {
        alert('Selección limpiada');
    }
}

/**
 * Procesar reimpresión
 */
function procesarReimpresion() {
    if (carnetSeleccionados.length === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Sin selección',
                text: 'Debe seleccionar al menos un carnet para reimprimir'
            });
        } else {
            alert('Debe seleccionar al menos un carnet para reimprimir');
        }
        return;
    }
    var cobraReimpresion = (typeof COBRAR_REIMPRESION === 'undefined' || COBRAR_REIMPRESION);
    var textoProcesando = cobraReimpresion ? 'Generando pagos de reimpresion' : 'Preparando reimpresion sin cargo';


    // Mostrar loading
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Procesando...',
            text: textoProcesando,
            allowOutsideClick: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });
    }

    // Crear FormData
    var formData = new FormData();
    formData.append('modulo_carnet', 'procesar_reimpresion');

    // Agregar cada ID
    carnetSeleccionados.forEach(function(id) {
        formData.append('pagos_seleccionados[]', id);
    });

    // Enviar petición
    var ajaxUrl = (typeof APP_URL !== 'undefined' ? APP_URL : '../') + 'app/ajax/carnetAjax.php';
    fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.tipo === 'redireccionar') {
            carnetSeleccionados = [];
            localStorage.removeItem('carnet_reimpresion_ids');

            // Desmarcar todos los checkboxes visualmente
            var checkboxes = document.querySelectorAll('.chk-reimpresion');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });

            // Desmarcar checkbox "seleccionar todos"
            var chkTodos = document.getElementById('seleccionarTodos');
            if (chkTodos) {
                chkTodos.checked = false;
            }

            // Ocultar contador y botón limpiar
            var contador = document.getElementById('contador-seleccion');
            if (contador) {
                contador.style.display = 'none';
            }

            var btnLimpiar = document.getElementById('btn-limpiar-seleccion');
            if (btnLimpiar) {
                btnLimpiar.style.display = 'none';
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: data.icono || 'success',
                    title: data.titulo || 'Éxito',
                    text: data.texto || 'Abriendo PDF...',
                    timer: 1500,
                    timerProgressBar: true
                }).then(function() {
                    window.open(data.url, '_blank');
                });
            } else {
                alert(data.texto);
                window.open(data.url, '_blank');
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: data.icono || 'info',
                    title: data.titulo || 'Aviso',
                    text: data.texto || 'Operación completada'
                });
            } else {
                alert(data.texto || 'Operación completada');
            }
        }
    })
    .catch(function(error) {
        console.error('Error en la petición:', error);

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al procesar la reimpresión'
            });
        } else {
            alert('Ocurrió un error al procesar la reimpresión');
        }
    });
}

/**
 * Limpiar IDs obsoletos (alumnos que ya no están en ninguna página de la tabla)
 * Esto puede pasar después de reimprimir carnets, cuando ya tienen carnet del mes
 */
function limpiarIdsObsoletos() {
    if (carnetSeleccionados.length === 0) {
        return;
    }

    var checkboxesEnPagina = document.querySelectorAll('.chk-reimpresion');
    var idsDisponibles = [];

    checkboxesEnPagina.forEach(function(checkbox) {
        idsDisponibles.push(checkbox.value);
    });

    // Si hay IDs seleccionados que SÍ están en la página pero el checkbox no existe,
    // significa que ese alumno ya no está disponible para reimpresión
    var idsActualizados = [];
    var seEliminaron = false;

    carnetSeleccionados.forEach(function(id) {
        // Verificar si el ID existe en los checkboxes disponibles
        var checkboxExiste = document.querySelector('.chk-reimpresion[value="' + id + '"]');

        if (checkboxExiste) {
            // El checkbox existe, mantener el ID
            idsActualizados.push(id);
        } else if (idsDisponibles.indexOf(id) === -1) {
            // El ID no está en la página actual, asumir que está en otra página
            idsActualizados.push(id);
        } else
            seEliminaron = true;
    });

    if (seEliminaron) {
        carnetSeleccionados = idsActualizados;
        guardarSelecciones();
    }

}

// Exponer funciones globalmente para debug
window.CarnetDebug = {
    verSeleccionados: function() {
        return carnetSeleccionados;
    },
    limpiar: function() {
        limpiarSelecciones();
    },
    actualizar: function() {
        actualizarContador();
    }
};
