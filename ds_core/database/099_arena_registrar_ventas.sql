-- =====================================================================
-- 099 · Arena › Punto de Venta: «Registrar ventas» (ventas ya hechas)
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Lo vendido antes de usar el punto de venta (o un día en que no se usó)
-- no se podía ingresar: «Vender» exige una caja abierta, cobra con la
-- fecha y hora de ese momento y sólo con los productos del catálogo.
--
--
-- QUE HACE ESTA MIGRACION
--
-- dsa_venta:
--   venta_cajaid    pasa a admitir NULL: una venta registrada después no
--                   pertenece a ninguna caja (no altera ningún arqueo)
--   venta_sedeid    la sede de una venta sin caja (para su comprobante)
--   venta_origen    P punto de venta (Vender), R registrada después
--   venta_registro  cuándo se registró (venta_fecharegistro es la fecha
--                   de la venta, que puede ser anterior)
--
-- Menú «Registrar ventas» (posRegistro) antes de «Vender», con los
-- permisos que cada rol ya tiene sobre «Vender».
--
-- SE PUEDE REPETIR. En una base sin el punto de venta no hace nada.
-- Como en la 094: la base de la escuela en @db antes de information_schema.
-- =====================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @hayTabla := (SELECT COUNT(*) > 0 FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_venta');

-- venta_cajaid admite NULL
SET @falta := @hayTabla AND (SELECT COUNT(*) > 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_venta' AND COLUMN_NAME = 'venta_cajaid' AND IS_NULLABLE = 'NO');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.dsa_venta MODIFY venta_cajaid INT NULL COMMENT 'NULL: registrada después, sin caja (099)'"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- venta_sedeid, venta_origen, venta_registro
SET @falta := @hayTabla AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_venta' AND COLUMN_NAME = 'venta_origen');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.dsa_venta
        ADD COLUMN venta_sedeid INT NULL DEFAULT NULL COMMENT 'Sede de una venta sin caja (099)' AFTER venta_cajaid,
        ADD COLUMN venta_origen CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P punto de venta, R registrada después (099)' AFTER venta_estado,
        ADD COLUMN venta_registro DATETIME NULL DEFAULT NULL COMMENT 'Cuándo se registró una venta R (099)' AFTER venta_origen"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Menú «Registrar ventas», antes de «Vender» (orden 1) en «Punto de Venta y Caja».
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
     SELECT 'arena', 'Registrar ventas', 0, g.menu_id, 'N', 'posRegistro', 'nav-icon fas fa-file-pen', 'A'
       FROM seguridad_menu g
      WHERE g.menu_modulo = 'arena' AND g.menu_hijo = 'S' AND g.menu_nombre = 'Punto de Venta y Caja'
        AND NOT EXISTS (SELECT 1 FROM seguridad_menu m WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'posRegistro')",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Permisos: los mismos que cada rol ya tiene sobre «Vender».
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
                                    permiso_eliminar, permiso_exportar, permiso_estado)
     SELECT pv.permiso_rolid, m.menu_id, pv.permiso_ver, pv.permiso_crear, pv.permiso_editar,
            pv.permiso_eliminar, pv.permiso_exportar, pv.permiso_estado
       FROM seguridad_permiso pv
       JOIN seguridad_menu mv ON mv.menu_id = pv.permiso_menuid AND mv.menu_modulo = 'arena' AND mv.menu_vista = 'posVenta'
       JOIN seguridad_menu m  ON m.menu_modulo = 'arena' AND m.menu_vista = 'posRegistro'
      WHERE NOT EXISTS (SELECT 1 FROM seguridad_permiso p WHERE p.permiso_rolid = pv.permiso_rolid AND p.permiso_menuid = m.menu_id)",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := IF(@hayTabla,
    CONCAT("SELECT (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'dsa_venta'
                      AND COLUMN_NAME IN ('venta_sedeid','venta_origen','venta_registro')) AS columnas_099_de_3,
                   (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'dsa_venta'
                      AND COLUMN_NAME = 'venta_cajaid') AS cajaid_admite_null,
                   (SELECT COUNT(*) FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
                     WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'posRegistro') AS roles_con_permiso"),
    "SELECT 'sin punto de venta: nada que hacer' AS aviso");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla (sólo si no hay ventas registradas sin caja):
-- DELETE p FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'posRegistro';
-- DELETE FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_vista = 'posRegistro';
-- ALTER TABLE dsa_venta DROP COLUMN venta_registro, DROP COLUMN venta_origen, DROP COLUMN venta_sedeid,
--   MODIFY venta_cajaid INT NOT NULL;
