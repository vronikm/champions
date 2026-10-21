-- =====================================================================
-- 042 · Basketball · Menús con el mismo nombre
-- =====================================================================
-- POR QUÉ ES UN PROBLEMA Y NO UN DETALLE
--
-- La pantalla de permisos lista las opciones por su nombre. Cuando dos
-- filas se llaman igual, quien asigna permisos no puede saber cuál está
-- marcando: concede el acceso a una creyendo que es la otra, y el error
-- no se nota hasta que alguien ve —o deja de ver— lo que no debía. Un
-- nombre repetido en una pantalla de control de acceso no es cosmética.
--
-- Se renombran los DOS casos que había, eligiendo el nombre a partir de
-- lo que la vista hace de verdad, no de lo que sugiere su ruta.
--
--
-- CASO 1 · «Registrar asistencia» aparecía dos veces
--
--   id  8  Asistencia alumnos → asistencia        (alumnos que asisten a clase)
--   id 16  Empleados          → empleadoEntrada   (marcaciones de entrada y salida)
--
-- La de empleados no registra una asistencia: registra marcaciones de
-- reloj —la vista alterna entre «Entrada» y «Salida» y lista las
-- marcaciones del día—. Se renombra a lo que hace. La de alumnos se
-- queda como está, porque ahí el nombre sí es exacto.
--
--
-- CASO 2 · «Horarios» era a la vez el grupo y una opción dentro de él
--
--   id 10  Horarios  (grupo, sin vista)
--     └── id 13  Horarios → asistenciaListHorario
--
-- En la barra lateral se leía «Horarios ▸ Horarios». La vista es un
-- listado con buscador, así que se nombra por eso y el grupo conserva su
-- nombre.
--
--
-- SE IDENTIFICAN POR VISTA, NO POR NOMBRE
--
-- El WHERE va contra menu_vista, que es única y estable. Filtrar por el
-- nombre que se quiere cambiar haría la migración no repetible: a la
-- segunda pasada no encontraría nada, y peor, si alguien ya lo renombró a
-- mano tocaría la fila equivocada.
-- =====================================================================

SET NAMES utf8mb4;

UPDATE seguridad_menu
   SET menu_nombre = 'Marcar entrada y salida'
 WHERE menu_modulo = 'basketball'
   AND menu_vista  = 'empleadoEntrada';

UPDATE seguridad_menu
   SET menu_nombre = 'Listado de horarios'
 WHERE menu_modulo = 'basketball'
   AND menu_vista  = 'asistenciaListHorario';
