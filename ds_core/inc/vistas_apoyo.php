<?php
/*
|--------------------------------------------------------------------------
| Vistas de apoyo y su pantalla de origen
|--------------------------------------------------------------------------
| Formularios, fichas, recibos y PDF no son ítems de menú, así que Core ›
| Permisos no los lista. Antes eso los dejaba ABIERTOS a cualquier rol con
| el módulo: un profesor podía abrir por URL la ficha de un alumno, un
| recibo o los ingresos de un empleado aunque su rol no tuviera ni la lista
| de alumnos, ni la de pagos, ni la de empleados (barrido de permisos del
| 2026-10-28, pruebas/qa_permisos_barrido.php).
|
| Aquí cada vista de apoyo declara desde qué pantallas se llega a ella, y
| hereda su permiso: se abre si el rol puede VER alguna de ellas. Así se
| administra todo desde Core › Permisos, sin ítems de menú nuevos.
|
|   'modulo' => [ 'vistaDeApoyo' => ['pantallaDeOrigen', ...] ]
|
| Una pantalla de otro módulo se escribe 'modulo:vista'. Una de apoyo puede
| heredar de otra de apoyo (asistenciaHorarioPDF -> asistenciaVerHorario).
|
| Guardar sigue protegido además por el permiso de acción en el servidor
| (ajax_guard / puede()). Una vista que no figura aquí ni en el menú sigue
| abierta (salvo DS_PERMISOS_ESTRICTOS): al crear una vista de apoyo nueva,
| añádala aquí.
*/

return [
    'core' => [
        'usuarioForm' => ['usuarioList'],
        'sedeForm'    => ['sedeList'],
        'menuForm'    => ['menuList'],
    ],

    'basketball' => [
        /* Alumnos */
        'alumnoNew'        => ['alumnoList', 'representanteList'],
        'alumnoProfile'    => ['alumnoList', 'representanteList', 'inscripcionPendientes', 'reporteAsistencia'],
        'alumnoUpdate'     => ['alumnoList', 'representanteList', 'inscripcionPendientes', 'carnetList', 'alumnoFotos'],
        'alumnoListaPDF'   => ['alumnoList'],
        'importarAlumnos'  => ['alumnoList'],

        /* Representantes y autorizaciones */
        'representanteNew'     => ['representanteList'],
        'representanteProfile' => ['representanteList'],
        'representanteUpdate'  => ['representanteList'],
        'representanteVinc'    => ['representanteList'],
        'representanteFLPD'    => ['representanteList'],
        'formularioLPPDF'      => ['representanteFLPD'],
        'reporteRepresentantesSede'      => ['representanteList'],
        'reporteContactosEmergenciaSede' => ['representanteList'],

        /* Pagos y recibos */
        'pagosNew'            => ['pagosList', 'alumnoList'],
        'pagosUpdate'         => ['pagosList'],
        'pagosPendiente'      => ['pagosList', 'cobranzaPension', 'balanceResultados'],   /* el Hub avisa de los pendientes */
        'pagospendienteUpdate'=> ['pagosList'],
        'pagosUniformeUpdate' => ['pagosList'],
        'pagosDescuento'      => ['pagosList'],
        'pagosRecibo'         => ['pagosList', 'torneoList'],
        'pagosReciboPDF'      => ['pagosRecibo'],
        'pagospendienteRecibo'    => ['pagosList'],
        'pagospendienteReciboPDF' => ['pagospendienteRecibo'],
        'reportePendientes'   => ['pagosList', 'cobranzaPension', 'reportePagos', 'balanceResultados'],

        /* Cobranza */
        'cobranzaDetallePension'   => ['cobranzaPension'],
        'cobranzaPensionInactivos' => ['cobranzaPension'],
        'cobranzaDetalleUniforme'  => ['cobranzaUniforme'],

        /* Facturación */
        'facturasNew'                 => ['facturasList'],
        'reporteRepresentanteFactura' => ['facturasList', 'facturasEmitidas'],

        /* Asistencia y horarios: el profesor llega desde su panel y desde Asistencia */
        'asistenciaAlumno'         => ['asistencia', 'dashboard'],
        'asistenciaVerHorario'     => ['asistencia', 'dashboard', 'asistenciaListHorario'],
        'asistenciaHorarioPDF'     => ['asistenciaVerHorario'],
        'asistenciaHorario'        => ['asistenciaListHorario'],
        'asistenciaHorarioLista'   => ['asistenciaListHorario'],
        'asistenciaHorarioJugador' => ['asistenciaListHorario'],
        'horarioListaPDF'          => ['asistenciaHorarioLista'],
        'buscarAsistencia'         => ['reporteAsistencia', 'alumnoList'],

        /* Empleados */
        'empleadoIE'                 => ['empleadoList'],
        'empleadoDescargaEgreso'     => ['empleadoList'],
        'empleadoEgresoUpdate'       => ['empleadoList'],
        'empleadoAsistenciasDetalle' => ['empleadoAsistencias'],

        /* Torneos */
        'equipoList'      => ['torneoList'],
        'jugadorLista'    => ['torneoList'],
        'jugadorNew'      => ['torneoList'],
        'jugadorListaPDF' => ['torneoList'],

        /* Carnets y cumpleaños */
        'carnetFoto'         => ['carnetList'],
        'carnetFotoPDF'      => ['carnetList'],
        'cumpleaniosTarjeta' => ['cumpleaniosList', 'core:tarjetaCumple'],
    ],

    'arena' => [
        'clienteForm'     => ['clienteList', 'reservaList'],
        'instalacionForm' => ['instalacionList'],
        'monederoDetalle' => ['monederoList'],
        'reservaForm'     => ['reservaList', 'horarioList'],
        'reservaDetalle'  => ['reservaList', 'monederoList'],
        'reciboPago'      => ['reservaList', 'monederoList'],
        'reciboPdf'       => ['reciboPago'],
    ],
];
