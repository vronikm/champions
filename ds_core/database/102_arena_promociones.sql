-- =====================================================================
-- 102 · Arena: «Promociones» (descuentos en reservas, p. ej. Black Friday)
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Las reservas no admitían ningún descuento: el valor era siempre horas ×
-- tarifa. Para una campaña (Black Friday, Navidad…) había que cobrar de
-- menos a mano y el saldo de la reserva no cuadraba.
--
--
-- QUE HACE ESTA MIGRACION
--
-- dsa_promocion (nueva, InnoDB): nombre, tipo (P porcentaje, V valor fijo
--   por reserva), valor y las fechas de JUEGO a las que aplica (desde –
--   hasta). Estado A activa, I inactiva, E eliminada.
--
-- dsa_reserva:
--   reserva_descuento    lo descontado (reserva_total ya es el valor con
--                        descuento: subtotal = total + descuento)
--   reserva_promocionid  la promoción aplicada (NULL: ninguna)
-- dsa_reserva_fija:
--   fija_promocionid     la promoción elegida al crear la serie: se aplica
--                        a cada semana que caiga dentro de sus fechas
--
-- El descuento se congela al aplicarlo: cambiar o desactivar después la
-- promoción no altera las reservas que ya la tienen.
--
-- Menú «Promociones» (promocionList) después de «Reservas»: los que están
-- desde el orden 7 bajan uno. Permisos: los que cada rol ya tiene sobre
-- «Horarios» (donde se fijan las tarifas).
--
-- SE PUEDE REPETIR. En una base sin Arena (sin dsa_reserva) no hace nada.
-- Como en la 094: la base de la escuela en @db antes de information_schema.
-- =====================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @hayTabla := (SELECT COUNT(*) > 0 FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_reserva');

-- dsa_promocion
SET @sql := IF(@hayTabla,
    CONCAT("CREATE TABLE IF NOT EXISTS `", @db, "`.dsa_promocion (
        promocion_id            INT NOT NULL AUTO_INCREMENT,
        promocion_nombre        VARCHAR(80) NOT NULL,
        promocion_tipo          CHAR(1) NOT NULL COMMENT 'P porcentaje, V valor fijo por reserva',
        promocion_valor         DECIMAL(10,2) NOT NULL,
        promocion_desde         DATE NOT NULL COMMENT 'Fechas de juego a las que aplica',
        promocion_hasta         DATE NOT NULL,
        promocion_estado        CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A activa, I inactiva, E eliminada',
        promocion_usuarioid     INT NULL DEFAULT NULL,
        promocion_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (promocion_id),
        KEY ix_promocion_fechas (promocion_estado, promocion_desde, promocion_hasta)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Promociones de reservas (102)'"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- dsa_reserva: reserva_descuento y reserva_promocionid
SET @falta := @hayTabla AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_reserva' AND COLUMN_NAME = 'reserva_descuento');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.dsa_reserva
        ADD COLUMN reserva_descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Descuento aplicado; total = subtotal - descuento (102)' AFTER reserva_valorhora,
        ADD COLUMN reserva_promocionid INT NULL DEFAULT NULL COMMENT 'dsa_promocion (102)' AFTER reserva_descuento,
        ADD KEY ix_reserva_promocion (reserva_promocionid)"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- dsa_reserva_fija: fija_promocionid
SET @falta := (SELECT COUNT(*) > 0 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_reserva_fija')
          AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_reserva_fija' AND COLUMN_NAME = 'fija_promocionid');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.dsa_reserva_fija
        ADD COLUMN fija_promocionid INT NULL DEFAULT NULL COMMENT 'Promoción de la serie: se aplica a las semanas dentro de sus fechas (102)'"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Hueco en el menú, sólo si aún no existe «Promociones»: del orden 7 en adelante, uno más.
SET @faltaMenu := @hayTabla AND (SELECT COUNT(*) = 0 FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_vista = 'promocionList');
SET @sql := IF(@faltaMenu,
    "UPDATE seguridad_menu SET menu_orden = menu_orden + 1
      WHERE menu_modulo = 'arena' AND menu_padreid = 0 AND menu_orden >= 7",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Menú «Promociones», orden 7 (después de «Reservas», 6).
SET @sql := IF(@faltaMenu,
    "INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
     VALUES ('arena', 'Promociones', 7, 0, 'N', 'promocionList', 'nav-icon fas fa-tags', 'A')",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Permisos: los que cada rol ya tiene sobre «Horarios».
SET @sql := IF(@hayTabla,
    "INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
                                    permiso_eliminar, permiso_exportar, permiso_estado)
     SELECT pb.permiso_rolid, m.menu_id, pb.permiso_ver, pb.permiso_crear, pb.permiso_editar,
            pb.permiso_eliminar, pb.permiso_exportar, pb.permiso_estado
       FROM seguridad_permiso pb
       JOIN seguridad_menu mb ON mb.menu_id = pb.permiso_menuid AND mb.menu_modulo = 'arena' AND mb.menu_vista = 'horarioList'
       JOIN seguridad_menu m  ON m.menu_modulo = 'arena' AND m.menu_vista = 'promocionList'
      WHERE NOT EXISTS (SELECT 1 FROM seguridad_permiso p WHERE p.permiso_rolid = pb.permiso_rolid AND p.permiso_menuid = m.menu_id)",
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := IF(@hayTabla,
    CONCAT("SELECT (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'dsa_promocion') AS tabla_promocion,
                   (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '", @db, "'
                      AND ((TABLE_NAME = 'dsa_reserva' AND COLUMN_NAME IN ('reserva_descuento','reserva_promocionid'))
                        OR (TABLE_NAME = 'dsa_reserva_fija' AND COLUMN_NAME = 'fija_promocionid'))) AS columnas_102_de_3,
                   (SELECT GROUP_CONCAT(CONCAT(menu_orden, ' ', menu_nombre) ORDER BY menu_orden SEPARATOR ' · ')
                      FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_padreid = 0 AND menu_orden >= 6) AS menu,
                   (SELECT COUNT(*) FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
                     WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'promocionList') AS roles_con_permiso"),
    "SELECT 'sin Arena: nada que hacer' AS aviso");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla (sólo si ninguna reserva tiene descuento):
-- DELETE p FROM seguridad_permiso p JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'promocionList';
-- DELETE FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_vista = 'promocionList';
-- UPDATE seguridad_menu SET menu_orden = menu_orden - 1 WHERE menu_modulo = 'arena' AND menu_padreid = 0 AND menu_orden >= 8;
-- ALTER TABLE dsa_reserva_fija DROP COLUMN fija_promocionid;
-- ALTER TABLE dsa_reserva DROP KEY ix_reserva_promocion, DROP COLUMN reserva_promocionid, DROP COLUMN reserva_descuento;
-- DROP TABLE dsa_promocion;
