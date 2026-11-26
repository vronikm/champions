-- =====================================================================
-- 098 · Arena › Reserva: anular un abono registrado por error
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Un abono de una reserva (dsa_pago) registrado con la forma de pago
-- equivocada —una transferencia que en realidad se descontó del monedero,
-- un importe mal digitado— no se podía corregir: el reporte de pagos lo
-- contaba como dinero que entró y lo abonado de la reserva quedaba así.
--
--
-- QUE HACE ESTA MIGRACION
--
-- dsa_pago ya tiene pago_estado (A vigente, X anulado). Añade:
--   pago_anulamotivo    la justificación (obligatoria al anular)
--   pago_anulausuario   quién lo anuló
--   pago_anulafecha     cuándo
--
-- Anular (arenaController::anularPagoReserva) NO borra nada: el abono
-- queda en estado X con la justificación, lo abonado de la reserva baja y,
-- si se pagó con monedero, el saldo vuelve al monedero (movimiento ANU);
-- si dejó un vuelto en el monedero, ese vuelto se descuenta. Después se
-- registra el abono correcto. Todas las cifras (reporte de pagos, caja,
-- Insights, recibos) ya cuentan sólo los abonos en estado A.
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
                   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_pago');

SET @falta := @hayTabla AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_pago' AND COLUMN_NAME = 'pago_anulamotivo');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.dsa_pago
        ADD COLUMN pago_anulamotivo VARCHAR(250) NULL DEFAULT NULL COMMENT 'Justificación de la anulación (098)' AFTER pago_estado,
        ADD COLUMN pago_anulausuario INT NULL DEFAULT NULL COMMENT 'Quién lo anuló (098)' AFTER pago_anulamotivo,
        ADD COLUMN pago_anulafecha DATETIME NULL DEFAULT NULL COMMENT 'Cuándo se anuló (098)' AFTER pago_anulausuario"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := IF(@hayTabla,
    CONCAT("SELECT COUNT(*) AS columnas_098_de_3 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'dsa_pago'
               AND COLUMN_NAME IN ('pago_anulamotivo','pago_anulausuario','pago_anulafecha')"),
    "SELECT 'sin Arena: nada que hacer' AS aviso");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla:
-- ALTER TABLE dsa_pago DROP COLUMN pago_anulafecha, DROP COLUMN pago_anulausuario, DROP COLUMN pago_anulamotivo;
