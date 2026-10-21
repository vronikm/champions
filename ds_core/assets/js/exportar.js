/*
| Las librerias de exportar, solo cuando se exporta.
|
| LO QUE SE MIDIO
|
| Cada pantalla de listado descargaba 2.685 KB de JavaScript. De ellos:
|
|     pdfmake.min.js    1.317 KB
|     vfs_fonts.js        793 KB   (las tipografias que usa pdfmake)
|     jszip.min.js         94 KB   (lo necesita el boton de Excel)
|     ------------------------
|                       2.204 KB   el 82% del total
|
| Todo eso existe para dos botones —PDF y Excel— que la mayoria de las veces
| nadie pulsa. El resto de la pantalla pesa 481 KB.
|
| POR QUE ESTO Y NO «defer»
|
| defer cambia CUANDO se descarga, no SI se descarga: los dos megas siguen
| viajando. Cargarlo al pulsar los quita del arranque por completo. Y ademas
| no obliga a tocar los bloques de codigo en linea de las vistas, que es la
| parte delicada de defer.
|
| COMO FUNCIONA
|
| DataTables Buttons no necesita pdfMake hasta el momento de generar el
| archivo: lo busca en window cuando actua. Asi que se sustituye la accion de
| los dos botones por una que primero se asegura de tener la libreria y
| despues llama a la accion original. El usuario nota una espera la primera
| vez que exporta, y ninguna en las siguientes.
|
| Se carga despues de dataTables.buttons.min.js y antes de inicializar las
| tablas.
*/
(function () {
    'use strict';

    var RAIZ = (function () {
        /* La ruta del propio archivo, para deducir donde estan las demas. */
        var s = document.currentScript;
        if (s && s.src) { return s.src.replace(/ds_core\/assets\/js\/exportar\.js.*$/, ''); }
        /* Sin currentScript: la raiz es lo que precede al primer modulo
           ds_* de la URL. Antes era '/champions_cf_new/', que en el dominio
           de produccion no existe. */
        return location.pathname.replace(/ds_[a-z]+\/.*$/, '');
    })();

    var LIBRERIAS = {
        pdf:   [RAIZ + 'ds_futbol/app/views/dist/plugins/pdfmake/pdfmake.min.js',
                RAIZ + 'ds_futbol/app/views/dist/plugins/pdfmake/vfs_fonts.js'],
        excel: [RAIZ + 'ds_futbol/app/views/dist/plugins/jszip/jszip.min.js'],
    };

    var pedidas = {};

    /* Se cargan EN ORDEN: vfs_fonts se registra sobre pdfMake, asi que
       lanzarlas en paralelo funciona unas veces y otras no. */
    function cargar(urls) {
        var clave = urls.join('|');
        if (pedidas[clave]) { return pedidas[clave]; }

        pedidas[clave] = urls.reduce(function (cadena, url) {
            return cadena.then(function () {
                return new Promise(function (ok, mal) {
                    var s = document.createElement('script');
                    s.src = url;
                    s.onload = ok;
                    s.onerror = function () { mal(new Error('no se pudo cargar ' + url)); };
                    document.head.appendChild(s);
                });
            });
        }, Promise.resolve());

        return pedidas[clave];
    }

    function avisar(texto) {
        if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'No se pudo exportar', text: texto,
                        confirmButtonText: 'Aceptar' });
        }
    }

    /*
    | DEVUELVE SI DE VERDAD PARCHEO LOS DOS BOTONES.
    |
    | La primera version devolvia «hecho» en cuanto encontraba el registro de
    | botones, y eso pasa ya con dataTables.buttons.min.js. Pero pdfHtml5 y
    | excelHtml5 los define buttons.html5.min.js, que se carga DESPUES. Asi
    | que se daba por parcheado sin haber tocado nada, no reintentaba, y los
    | dos botones desaparecian de la barra.
    */
    function parchear() {
        var DT = window.DataTable || (window.jQuery && jQuery.fn.dataTable);
        if (!DT || !DT.ext || !DT.ext.buttons) { return false; }
        if (!DT.ext.buttons.pdfHtml5 || !DT.ext.buttons.excelHtml5) { return false; }

        [['pdfHtml5', 'pdf'], ['excelHtml5', 'excel']].forEach(function (par) {
            var nombre = par[0], grupo = par[1];
            var boton = DT.ext.buttons[nombre];
            if (!boton || boton._dsDiferido) { return; }

            /*
            | PRIMERO HAY QUE DECIRLE QUE EL BOTON ESTA DISPONIBLE.
            |
            | Cada uno de estos botones trae una comprobacion available() que
            | mira si pdfMake o JSZip existen en window, y si no, DataTables
            | NI SIQUIERA LO DIBUJA. Con la carga bajo demanda no existen al
            | arrancar, asi que al aplicar solo el parche de la accion los dos
            | botones desaparecieron de la barra: quedaron Copiar, CSV,
            | Imprimir y Column visibility. Se vio al pulsarlos en la prueba;
            | mirando el codigo no se habria notado.
            |
            | Se declara disponible siempre: la libreria llegara cuando se
            | pulse, que es cuando de verdad hace falta.
            */
            boton.available = function () { return true; };

            var original = boton.action;
            boton.action = function (e, dt, node, config) {
                var self = this, args = arguments;

                /* Si ya esta cargada, se actua sin esperar. */
                var falta = (grupo === 'pdf')
                    ? typeof window.pdfMake === 'undefined'
                    : typeof window.JSZip === 'undefined';

                if (!falta) { return original.apply(self, args); }

                /* Aviso de que se esta trayendo: son dos megas y en una red
                   lenta el silencio se interpreta como que no funciona. */
                if (window.Swal) {
                    Swal.fire({ title: 'Preparando la exportación…',
                                text: 'Se descarga la primera vez que se usa.',
                                allowOutsideClick: false,
                                didOpen: function () { Swal.showLoading(); } });
                }

                cargar(LIBRERIAS[grupo]).then(function () {
                    if (window.Swal) { Swal.close(); }
                    original.apply(self, args);
                }).catch(function (err) {
                    if (window.Swal) { Swal.close(); }
                    avisar(err.message);
                });
            };
            boton._dsDiferido = true;
        });

        return true;
    }

    /* Buttons puede cargarse despues que este archivo. Se intenta ya y, si no
       esta, se reintenta al terminar de leer el documento. */
    if (!parchear()) {
        document.addEventListener('DOMContentLoaded', parchear);
    }
})();
