-- =====================================================================
-- 100 · Arena › Punto de Venta y Caja: «Egresos» del bar
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Los gastos del bar (insumos, hielo, gas, limpieza, ayudantes…) sólo se
-- podían anotar como egreso de una caja abierta. Muchos se pagan con
-- dinero de quien atiende o por transferencia, o se anotan otro día, y
-- así no había cómo saber, al final del día, la semana o el mes, cuánto
-- se vendió frente a cuánto se gastó.
--
--
-- QUE HACE ESTA MIGRACION
--
-- dsa_egreso (nueva, InnoDB): fecha, categoría, concepto y valor. La
--   pantalla sólo pide el detalle y el valor (egreso_origen = 'N'); las
--   columnas de origen (C caja con su movimiento EGR, P dinero propio,
--   O otro medio), egreso_cajaid, egreso_pagadopor y egreso_forma quedan
--   por si se vuelve a preguntar. Se anula con justificación, quién y
--   cuándo. El efectivo que sale de una caja se sigue anotando en Caja.
--
-- Menú «Egresos» (posEgresos) después de «Productos» (Compras pasa al
-- orden 6), con los permisos que cada rol ya tiene sobre «Caja».
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

-- dsa_egreso
SET @sql := IF(@hayTabla,
    CONCAT("CREATE TABLE IF NOT EXISTS `", @db, "`.dsa_egreso (
        egreso_id            INT NOT NULL AUTO_INCREMENT,
        egreso_fecha         DATE NOT NULL COMMENT 'Día del gasto (puede ser anterior al registro)',
        egreso_categoria     VARCHAR(60) NOT NULL,
        egreso_concepto      VARCHAR(120) NOT NULL,
        egreso_valor         DECIMAL(10,2) NOT NULL,
        egreso_origen        CHAR(1) NOT NULL COMMENT 'C efectivo de la caja, P dinero propio, O otro medio, N sin indicar (instalaciones, 101)',
        egreso_forma         CHAR(3) NULL DEFAULT NULL COMMENT 'dsa_forma_ingreso.forma_codigo (O)',
        egreso_pagadopor     VARCHAR(80) NULL DEFAULT NULL COMMENT 'Quién puso el dinero (P)',
        egreso_referencia    VARCHAR(60) NULL DEFAULT NULL COMMENT 'Factura, nota o comprobante',
        egreso_cajaid        INT NULL DEFAULT NULL COMMENT 'Caja de la que salió (C del mismo día)',
        egreso_movimientoid  INT NULL DEFAULT NULL COMMENT 'dsa_caja_movimiento (origen EGR)',
        egreso_sedeid        INT NULL DEFAULT NULL,
        egreso_estado        CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A vigente, X anulado',
        egreso_anulamotivo   VARCHAR(200) NULL DEFAULT NULL,
        egreso_anulausuario  INT NULL DEFAULT NULL,
        egreso_anulafecha    DATETIME NULL DEFAULT NULL,
        egreso_usuarioid     INT NULL DEFAULT NULL,
        egreso_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (egreso_id),
        KEY ix_egreso_fecha (egreso_fecha, egreso_estado),
        KEY ix_egreso_caja (egreso_cajaid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Egresos del bar (100)'"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Menú «Egresos» después de «Productos» (orden 4); «Compras» pasa al 6.
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
     SELECT 'arena', 'Egresos', 5, g.menu_id, 'N', 'posEgresos', 'nav-icon fas fa-money-bill-transfer', 'A'
       FROM seguridad_menu g
      WHERE g.menu_modulo = 'arena' AND g.menu_hijo = 'S' AND g.menu_nombre = 'Punto de Venta y Caja'
        AND NOT EXISTS (SELECT 1 FROM seguridad_menu m WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'posEgresos')",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(@hayTabla,
    "UPDATE seguridad_menu SET menu_orden = 6 WHERE menu_modulo = 'arena' AND menu_vista = 'posCompras' AND menu_orden = 5",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Permisos: los mismos que cada rol ya tiene sobre «Caja».
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
                                    permiso_eliminar, permiso_exportar, permiso_estado)
     SELECT pc.permiso_rolid, m.menu_id, pc.permiso_ver, pc.permiso_crear, pc.permiso_editar,
            pc.permiso_eliminar, pc.permiso_exportar, pc.permiso_estado
       FROM seguridad_permiso pc
       JOIN seguridad_menu mc ON mc.menu_id = pc.permiso_menuid AND mc.menu_modulo = 'arena' AND mc.menu_vista = 'posCaja'
       JOIN seguridad_menu m  ON m.menu_modulo = 'arena' AND m.menu_vista = 'posEgresos'
      WHERE NOT EXISTS (SELECT 1 FROM seguridad_permiso p WHERE p.permiso_rolid = pc.permiso_rolid AND p.permiso_menuid = m.menu_id)",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := IF(@hayTabla,
    CONCAT("SELECT (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'dsa_egreso') AS tabla_egreso,
                   (SELECT menu_orden FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_vista = 'posEgresos') AS orden_egresos,
                   (SELECT menu_orden FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_vista = 'posCompras') AS orden_compras,
                   (SELECT COUNT(*) FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
                     WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'posEgresos') AS roles_con_permiso"),
    "SELECT 'sin punto de venta: nada que hacer' AS aviso");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla (sólo si no hay egresos registrados):
-- DELETE p FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'posEgresos';
-- DELETE FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_vista = 'posEgresos';
-- UPDATE seguridad_menu SET menu_orden = 5 WHERE menu_modulo = 'arena' AND menu_vista = 'posCompras';
-- DROP TABLE dsa_egreso;
