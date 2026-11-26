-- =====================================================================
-- 101 · Arena: «Egresos Instalaciones» y Reportes › «Ganancias»
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Los gastos de las instalaciones (luz, agua, mantenimiento de la cancha,
-- arreglo del césped…) no se registraban en ningún lado, así que no había
-- cómo saber la ganancia: lo que entra por reservas menos lo que cuesta
-- mantener las canchas.
--
--
-- QUE HACE ESTA MIGRACION (requiere la 100)
--
-- dsa_egreso:
--   egreso_ambito        B bar (punto de venta, la 100), I instalaciones
--   egreso_instalacionid la cancha del gasto (NULL: de todas / general)
--   Un egreso de instalaciones lleva sólo el detalle y el valor: no se
--   pregunta de dónde salió el dinero (egreso_origen = 'N') y no toca la
--   caja del punto de venta ni el cuadre de Reportes › Pagos.
--
-- Menú «Egresos Instalaciones» (egresoInstalaciones) después de
-- «Monedero» («Punto de Venta y Caja» pasa al orden 9 y «Reportes» al 10),
-- con los permisos que cada rol ya tiene sobre «Monedero».
--
-- Menú Reportes › «Ganancias» (reporteGanancias), con los permisos que
-- cada rol ya tiene sobre Reportes › «Pagos Reservas».
--
-- SE PUEDE REPETIR. Sin la tabla dsa_egreso (sin la 100) no hace nada.
-- Como en la 094: la base de la escuela en @db antes de information_schema.
-- =====================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @hayTabla := (SELECT COUNT(*) > 0 FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_egreso');

-- egreso_ambito y egreso_instalacionid
SET @falta := @hayTabla AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_egreso' AND COLUMN_NAME = 'egreso_ambito');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.dsa_egreso
        ADD COLUMN egreso_ambito CHAR(1) NOT NULL DEFAULT 'B' COMMENT 'B bar (punto de venta), I instalaciones (101)' AFTER egreso_id,
        ADD COLUMN egreso_instalacionid INT NULL DEFAULT NULL COMMENT 'Cancha del gasto; NULL general (101)' AFTER egreso_sedeid,
        ADD KEY ix_egreso_ambito (egreso_ambito, egreso_fecha, egreso_estado)"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Hueco en el menú: «Reportes» 9 → 10 y «Punto de Venta y Caja» 8 → 9 (sólo si siguen ahí).
SET @sql := IF(@hayTabla,
    "UPDATE seguridad_menu SET menu_orden = 10
      WHERE menu_modulo = 'arena' AND menu_padreid = 0 AND menu_hijo = 'S' AND menu_nombre = 'Reportes' AND menu_orden = 9",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF(@hayTabla,
    "UPDATE seguridad_menu SET menu_orden = 9
      WHERE menu_modulo = 'arena' AND menu_padreid = 0 AND menu_hijo = 'S' AND menu_nombre = 'Punto de Venta y Caja' AND menu_orden = 8",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Menú «Egresos Instalaciones», orden 8 (después de «Monedero», 7).
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
     SELECT 'arena', 'Egresos Instalaciones', 8, 0, 'N', 'egresoInstalaciones', 'nav-icon fas fa-file-invoice-dollar', 'A'
       FROM DUAL
      WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu m WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'egresoInstalaciones')",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Menú Reportes › «Ganancias», después de «Ventas» (2).
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
     SELECT 'arena', 'Ganancias', 3, g.menu_id, 'N', 'reporteGanancias', 'nav-icon fas fa-sack-dollar', 'A'
       FROM seguridad_menu g
      WHERE g.menu_modulo = 'arena' AND g.menu_hijo = 'S' AND g.menu_nombre = 'Reportes'
        AND NOT EXISTS (SELECT 1 FROM seguridad_menu m WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'reporteGanancias')",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Permisos: «Egresos Instalaciones» como «Monedero»; «Ganancias» como «Pagos Reservas».
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
                                    permiso_eliminar, permiso_exportar, permiso_estado)
     SELECT pb.permiso_rolid, m.menu_id, pb.permiso_ver, pb.permiso_crear, pb.permiso_editar,
            pb.permiso_eliminar, pb.permiso_exportar, pb.permiso_estado
       FROM seguridad_permiso pb
       JOIN seguridad_menu mb ON mb.menu_id = pb.permiso_menuid AND mb.menu_modulo = 'arena' AND mb.menu_vista = 'monederoList'
       JOIN seguridad_menu m  ON m.menu_modulo = 'arena' AND m.menu_vista = 'egresoInstalaciones'
      WHERE NOT EXISTS (SELECT 1 FROM seguridad_permiso p WHERE p.permiso_rolid = pb.permiso_rolid AND p.permiso_menuid = m.menu_id)",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
                                    permiso_eliminar, permiso_exportar, permiso_estado)
     SELECT pb.permiso_rolid, m.menu_id, pb.permiso_ver, pb.permiso_crear, pb.permiso_editar,
            pb.permiso_eliminar, pb.permiso_exportar, pb.permiso_estado
       FROM seguridad_permiso pb
       JOIN seguridad_menu mb ON mb.menu_id = pb.permiso_menuid AND mb.menu_modulo = 'arena' AND mb.menu_vista = 'reportePagos'
       JOIN seguridad_menu m  ON m.menu_modulo = 'arena' AND m.menu_vista = 'reporteGanancias'
      WHERE NOT EXISTS (SELECT 1 FROM seguridad_permiso p WHERE p.permiso_rolid = pb.permiso_rolid AND p.permiso_menuid = m.menu_id)",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := IF(@hayTabla,
    CONCAT("SELECT (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'dsa_egreso'
                      AND COLUMN_NAME IN ('egreso_ambito','egreso_instalacionid')) AS columnas_101_de_2,
                   (SELECT GROUP_CONCAT(CONCAT(menu_orden, ' ', menu_nombre) ORDER BY menu_orden SEPARATOR ' · ')
                      FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_padreid = 0 AND menu_orden >= 7) AS menu,
                   (SELECT COUNT(*) FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
                     WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'egresoInstalaciones') AS roles_egresos,
                   (SELECT COUNT(*) FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
                     WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'reporteGanancias') AS roles_ganancias"),
    "SELECT 'sin dsa_egreso (aplique antes la 100): nada que hacer' AS aviso");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla (sólo si no hay egresos de instalaciones):
-- DELETE p FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
--  WHERE m.menu_modulo = 'arena' AND m.menu_vista IN ('egresoInstalaciones', 'reporteGanancias');
-- DELETE FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_vista IN ('egresoInstalaciones', 'reporteGanancias');
-- UPDATE seguridad_menu SET menu_orden = 8 WHERE menu_modulo = 'arena' AND menu_padreid = 0 AND menu_nombre = 'Punto de Venta y Caja';
-- UPDATE seguridad_menu SET menu_orden = 9 WHERE menu_modulo = 'arena' AND menu_padreid = 0 AND menu_nombre = 'Reportes';
-- ALTER TABLE dsa_egreso DROP KEY ix_egreso_ambito, DROP COLUMN egreso_instalacionid, DROP COLUMN egreso_ambito;
