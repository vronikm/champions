-- =====================================================================
-- 097 · Arena › Monedero: anular un ingreso o un egreso registrado por error
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Un movimiento del monedero (dsa_monedero_movimiento) registrado por
-- error —un egreso que el cliente no pidió, un ingreso duplicado— no se
-- podía deshacer: sólo quedaba registrar otro movimiento a mano, sin dejar
-- dicho cuál corregía ni por qué.
--
--
-- QUE HACE ESTA MIGRACION
--
-- Añade a dsa_monedero_movimiento:
--   movimiento_estado        A vigente, X anulado
--   movimiento_anulaid       en el contra-asiento: el movimiento que anula
--   movimiento_anulamotivo   la justificación (obligatoria al anular)
--   movimiento_anulausuario  quién lo anuló
--   movimiento_anulafecha    cuándo
--
-- Anular (arenaController::anularMovimientoMonedero) NO borra nada: marca
-- el movimiento como anulado y registra un contra-asiento (origen ANU) que
-- devuelve el saldo, así el libro mayor sigue cuadrando. Sólo los
-- movimientos manuales (ingreso TRA/EFE/AJU, egreso DEV): los de un cobro,
-- un vuelto, una devolución de reserva o una venta se anulan en su origen.
--
-- SE PUEDE REPETIR. En una base sin Arena (sin la tabla) no hace nada.
-- Como en la 094: la base de la escuela en @db antes de information_schema.
-- =====================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @hayTabla := (SELECT COUNT(*) > 0 FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_monedero_movimiento');

-- movimiento_estado
SET @falta := @hayTabla AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_monedero_movimiento' AND COLUMN_NAME = 'movimiento_estado');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.dsa_monedero_movimiento
        ADD COLUMN movimiento_estado CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A vigente, X anulado (097)' AFTER movimiento_fecha,
        ADD COLUMN movimiento_anulaid INT NULL DEFAULT NULL COMMENT 'Contra-asiento: el movimiento que anula (097)' AFTER movimiento_estado,
        ADD COLUMN movimiento_anulamotivo VARCHAR(250) NULL DEFAULT NULL COMMENT 'Justificación de la anulación (097)' AFTER movimiento_anulaid,
        ADD COLUMN movimiento_anulausuario INT NULL DEFAULT NULL COMMENT 'Quién lo anuló (097)' AFTER movimiento_anulamotivo,
        ADD COLUMN movimiento_anulafecha DATETIME NULL DEFAULT NULL COMMENT 'Cuándo se anuló (097)' AFTER movimiento_anulausuario"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := IF(@hayTabla,
    CONCAT("SELECT COUNT(*) AS columnas_097_de_5 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'dsa_monedero_movimiento'
               AND COLUMN_NAME IN ('movimiento_estado','movimiento_anulaid','movimiento_anulamotivo','movimiento_anulausuario','movimiento_anulafecha')"),
    "SELECT 'sin Arena: nada que hacer' AS aviso");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla:
-- ALTER TABLE dsa_monedero_movimiento DROP COLUMN movimiento_anulafecha, DROP COLUMN movimiento_anulausuario,
--   DROP COLUMN movimiento_anulamotivo, DROP COLUMN movimiento_anulaid, DROP COLUMN movimiento_estado;
