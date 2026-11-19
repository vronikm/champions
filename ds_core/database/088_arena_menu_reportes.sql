-- =====================================================================
-- 088 · Arena: grupo «Reportes» en el menú y su reporte «Pagos»
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Al final del día no había dónde ver cuánto se cobró, en qué forma y a
-- qué hora para cuadrar la caja: había que abrir reserva por reserva.
--
--
-- QUE HACE ESTA MIGRACION
--
--   1. Menú de Arena: el grupo «Reportes» (menu_hijo = 'S', sin vista),
--      después de «Monedero».
--   2. Dentro, «Pagos» (vista reportePagos, ds_arena/views/reportePagos-
--      view.php): lo recaudado por forma de pago y por hora, el cuadre del
--      efectivo y el detalle de cada cobro.
--
-- NO concede permisos a ningún rol: el reporte muestra dinero y se asigna
-- a mano en Core › Permisos (módulo Arena, grupo «Reportes»). El Super
-- Administrador tiene acceso total por definición.
--
-- El menú de Arena era plano; desde esta versión arenaController::
-- menuLateral() dibuja los grupos y muestra «Reportes» sólo a quien puede
-- ver al menos una de sus pantallas.
--
-- Común a los cuatro sistemas (ds_core/database).
--
--
-- SE PUEDE REPETIR
--
-- Los dos INSERT llevan NOT EXISTS.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. El grupo «Reportes»
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'arena', 'Reportes', 8, 0, 'S', '', 'nav-icon fas fa-chart-column', 'A'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'arena' AND M.menu_hijo = 'S'
                      AND M.menu_nombre = 'Reportes');

SET @g_reportes = (SELECT menu_id FROM seguridad_menu
                    WHERE menu_modulo = 'arena' AND menu_hijo = 'S'
                      AND menu_nombre = 'Reportes' LIMIT 1);

-- ---------------------------------------------------------------------
-- 2. «Pagos», dentro del grupo
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'arena', 'Pagos', 1, @g_reportes, 'N', 'reportePagos', 'nav-icon fas fa-cash-register', 'A'
  FROM DUAL
 WHERE @g_reportes IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'arena' AND M.menu_vista = 'reportePagos');

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT m.menu_id, m.menu_nombre, m.menu_orden, m.menu_padreid, m.menu_hijo, m.menu_vista
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'arena'
   AND (m.menu_nombre = 'Reportes' OR m.menu_vista = 'reportePagos');
