<?php
/*
|--------------------------------------------------------------------------
| Vistas navegables del módulo Basketball
|--------------------------------------------------------------------------
| Fuente única de verdad sobre qué rutas existen en este módulo. La usan:
|
|   - viewsModel, para resolver una URL a su archivo de vista.
|   - DigiSports Core, para validar que un menú apunte a una vista real
|     (ds_core/vistas.php).
|
| Añadir una vista nueva se reduce a crear su archivo -view.php y añadir su
| nombre aquí.
*/

/*
| RETIRADAS: la configuración y la seguridad las administra DigiSports Core
| (ds_core/admin/), y este módulo sólo consume esos datos. Se quitaron de
| aquí a propósito —no basta con desactivar su menú—: una vista que no
| figura en seguridad_menu NO se restringe, así que dejarlas en la lista
| las habría hecho accesibles por URL para cualquier usuario.
|
|   sedes      -> Core · Sedes          escuela/tablas/catálogos -> Core · Catálogos
|   usuarios   -> Core · Usuarios       roles/menús/permisos     -> Core
*/

return [
    "dashboard",
    "logOut", "alumnoList", "alumnoNew", "alumnoUpdate",
    "alumnoProfile", "pagosList", "pagosNew", "pagosUpdate",
    "pagosPendiente", "pagospendienteUpdate", "pagosRecibo",
    "pagospendienteRecibo", "pagosDescuento", "pagosReciboPDF", "pagospendienteReciboPDF",
    "pagosReciboEnvio", "reportePagos", "reportePendientes", "pagospendienteReciboEnvio",
    "asistenciaHora", "asistenciaLugar", "asistenciaHorario",
    "asistenciaListHorario", "representanteList", "representanteNew",
    "representanteProfile", "representanteUpdate", "representanteVinc",
    "empleadoList", "torneoList", "equipoList", "asistenciaVerHorario", "asistenciaHorarioPDF",
    "jugadorLista", "jugadorNew", "asistenciaHorarioJugador", "cobranzaPension",
    "cobranzaUniforme", "cobranzaDetallePension", "cobranzaDetalleUniforme",
    "jugadorListaPDF", "empleadoIE", "empleadoDescargaEgreso", "pagosUniformeUpdate",
    "reporteRubros", "reportePagosReceptadosResumen", "alumnoListaPDF", "reportePendientesPDF", "reportePagosPDF", "ingresoList",
    "reporteRepresentantesSede", "reporteContactosEmergenciaSede",
    "reporteRepresentanteFactura", "asistenciaHorarioLista", "empleadoEgresoUpdate",
    "asistencia", "asistenciaAlumno", "egresoList", "balanceResultados",
    "reporteAsistencia", "buscarAsistencia", "horarioListaPDF", "representanteFLPD",
    "formularioLPPDF", "empleadoEntrada",
    "empleadoAsistencias", "agenda", "empleadoAsistenciasDetalle", "cobranzaPensionInactivos",
    "ingresosLugarEntrenamiento", "estadisticas", "reporteIngresosMorames", "facturasList",
    "facturasNew", "facturasEmitidas", "carnetList", "carnetFoto", "carnetPDF",
    "carnetFotoPDF", "cumpleaniosList", "cumpleaniosTarjeta",
    "alumnoFotos",
    "importarAlumnos", "inscripcionEnlace", "consentimientoList", "inscripcionPendientes",
];
