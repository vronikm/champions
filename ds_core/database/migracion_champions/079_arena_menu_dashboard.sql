-- =====================================================================
-- 079 · Arena: la entrada «Panel» del menú pasa a llamarse «Dashboard»
-- =====================================================================
-- QUÉ RESUELVE
--
-- Fútbol llama «Dashboard» a su pantalla de entrada; Arena la llamaba
-- «Panel». Se unifica el nombre (2026-10-30). Sólo cambia el rótulo: la
-- dirección sigue siendo panel/ y el permiso de cada rol no se toca.
--
-- ES REPETIBLE: sólo renombra si aún se llama «Panel».
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 079_arena_menu_dashboard.sql

SET NAMES utf8mb4;

UPDATE seguridad_menu
   SET menu_nombre = 'Dashboard'
 WHERE menu_modulo = 'arena'
   AND menu_vista  = 'panel'
   AND menu_nombre = 'Panel';

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT menu_id, menu_modulo, menu_nombre, menu_vista
  FROM seguridad_menu
 WHERE menu_modulo = 'arena' AND menu_vista = 'panel';

-- Para revertir:
-- UPDATE seguridad_menu SET menu_nombre = 'Panel'
--  WHERE menu_modulo = 'arena' AND menu_vista = 'panel' AND menu_nombre = 'Dashboard';
