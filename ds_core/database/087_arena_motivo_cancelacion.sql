-- =====================================================================
-- 087 · Arena: motivo, fecha y usuario de cada cancelación de reserva
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Una reserva cancelada no decía por qué. Al revisar el mes no se sabía
-- si la canceló el cliente, la lluvia, un mantenimiento o un error de
-- digitación, ni quién lo hizo. La reserva fija guardaba un motivo, pero
-- era opcional y no pasaba a sus semanas.
--
--
-- QUE HACE ESTA MIGRACION
--
--   1. dsa_reserva: reserva_motivocancelacion (obligatorio al cancelar
--      desde la aplicación), reserva_fechacancelacion y
--      reserva_usuariocancelacion. Al reactivar una reserva se vacían.
--   2. dsa_reserva_fija: fija_usuariocancelacion (motivo y fecha ya
--      existían, migración 081).
--   3. Las semanas que ya se cancelaron junto con una reserva fija toman
--      el motivo y la fecha de la serie, si la serie los tiene.
--
-- Común a los cuatro sistemas (ds_core/database).
--
--
-- SE PUEDE REPETIR
--
-- Cada columna se agrega sólo si falta (y sólo si la tabla existe); el
-- paso 3 sólo rellena las semanas que siguen sin motivo.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. dsa_reserva
-- ---------------------------------------------------------------------
SET @falta := (SELECT COUNT(*) = 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva')
          AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva'
                  AND COLUMN_NAME  = 'reserva_motivocancelacion');
SET @sql := IF(@falta,
    "ALTER TABLE dsa_reserva
        ADD COLUMN reserva_motivocancelacion  VARCHAR(250) NULL COMMENT 'Por qué se canceló (obligatorio al cancelar)' AFTER reserva_observacion,
        ADD COLUMN reserva_fechacancelacion   DATETIME     NULL COMMENT 'Cuándo se canceló' AFTER reserva_motivocancelacion,
        ADD COLUMN reserva_usuariocancelacion INT          NULL COMMENT 'seguridad_usuario.usuario_id de quien la canceló' AFTER reserva_fechacancelacion",
    'SELECT ''dsa_reserva: columnas de cancelación ya existen o no hay tabla'' AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 2. dsa_reserva_fija
-- ---------------------------------------------------------------------
SET @falta := (SELECT COUNT(*) = 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva_fija')
          AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva_fija'
                  AND COLUMN_NAME  = 'fija_usuariocancelacion');
SET @sql := IF(@falta,
    "ALTER TABLE dsa_reserva_fija
        ADD COLUMN fija_usuariocancelacion INT NULL COMMENT 'seguridad_usuario.usuario_id de quien la canceló' AFTER fija_fechacancelacion",
    'SELECT ''dsa_reserva_fija: fija_usuariocancelacion ya existe o no hay tabla'' AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 3. Semanas ya canceladas con su serie: heredan su motivo
-- ---------------------------------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva'
                AND COLUMN_NAME  = 'reserva_motivocancelacion')
          * (SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva_fija');
SET @sql := IF(@hay > 0,
    "UPDATE dsa_reserva r
       JOIN dsa_reserva_fija f ON f.fija_id = r.reserva_fijaid
        SET r.reserva_motivocancelacion = LEFT(CONCAT('Reserva fija ', f.fija_codigo, ' cancelada: ', f.fija_motivocancelacion), 250),
            r.reserva_fechacancelacion  = f.fija_fechacancelacion
      WHERE r.reserva_estado = 'X' AND f.fija_estado = 'X'
        AND r.reserva_motivocancelacion IS NULL
        AND f.fija_motivocancelacion IS NOT NULL AND f.fija_motivocancelacion <> ''
        AND r.reserva_fecha > f.fija_hasta",
    'SELECT ''sin semanas que completar'' AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
