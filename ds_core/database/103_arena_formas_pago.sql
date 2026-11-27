-- =====================================================================
-- 103 · Arena: formas de pago (transferencias por banco, sin tarjeta)
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Las formas de pago tenían una sola «Transferencia»: al cuadrar con los
-- bancos no se sabía a qué cuenta llegó cada pago. Y «Tarjeta de crédito»
-- ya no se ofrece a los clientes (2026-11-26).
--
--
-- QUE HACE ESTA MIGRACION
--
-- dsa_forma_ingreso (las formas de pago de reservas, abonos y punto de
-- venta):
--   TBL  «Transferencia Banco de Loja», nueva, con comprobante.
--   TBP  «Transferencia Banco Pichincha», nueva, con comprobante.
--   TRA  «Transferencia» queda INACTIVA y con su nombre: los pagos ya
--        registrados por transferencia NO se asignan a ningún banco. En el
--        detalle de cada reserva salen marcados «elija el banco» y se
--        corrigen con «Ajustar» (la forma y el comprobante).
--   TAR  «Tarjeta de crédito» queda INACTIVA.
--   Una forma inactiva no se ofrece en ninguna pantalla ni la acepta el
--   servidor, pero no se borra: los pagos antiguos la conservan en
--   reportes y recibos. Para volver a ofrecerla: forma_estado = 'A'.
--   Orden: Efectivo, Banco de Loja, Banco Pichincha, Monedero (y detrás
--   las inactivas).
--
-- No cambia los depósitos al monedero ni las devoluciones, que tienen
-- sus propios medios (efectivo, transferencia, monedero).
--
-- SE PUEDE REPETIR. Sin dsa_forma_ingreso no hace nada.
-- Como en la 094: la base de la escuela en @db antes de information_schema.
-- =====================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @hayTabla := (SELECT COUNT(*) > 0 FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_forma_ingreso');

-- TRA conserva el nombre «Transferencia» (un borrador anterior de esta
-- migración la había renombrado a Banco de Loja: se deshace).
SET @sql := IF(@hayTabla,
    CONCAT("UPDATE `", @db, "`.dsa_forma_ingreso SET forma_nombre = 'Transferencia'
             WHERE forma_codigo = 'TRA' AND forma_nombre = 'Transferencia Banco de Loja'"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- TBL y TBP, si faltan (con comprobante obligatorio).
SET @sql := IF(@hayTabla,
    CONCAT("INSERT INTO `", @db, "`.dsa_forma_ingreso (forma_codigo, forma_nombre, forma_esmonedero, forma_requiereref, forma_orden, forma_estado)
            SELECT n.codigo, n.nombre, 'N', 'S', n.orden, 'A'
              FROM (SELECT 'TBL' AS codigo, 'Transferencia Banco de Loja' AS nombre, 2 AS orden
                    UNION ALL SELECT 'TBP', 'Transferencia Banco Pichincha', 3) n
             WHERE NOT EXISTS (SELECT 1 FROM `", @db, "`.dsa_forma_ingreso f WHERE f.forma_codigo = n.codigo)"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- TRA y TAR inactivas (los pagos antiguos las conservan).
SET @sql := IF(@hayTabla,
    CONCAT("UPDATE `", @db, "`.dsa_forma_ingreso SET forma_estado = 'I' WHERE forma_codigo IN ('TRA', 'TAR') AND forma_estado = 'A'"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- El orden: las activas primero.
SET @sql := IF(@hayTabla,
    CONCAT("UPDATE `", @db, "`.dsa_forma_ingreso
               SET forma_orden = CASE forma_codigo WHEN 'EFE' THEN 1 WHEN 'TBL' THEN 2 WHEN 'TBP' THEN 3 WHEN 'MON' THEN 4
                                                   WHEN 'TRA' THEN 8 WHEN 'TAR' THEN 9 END
             WHERE forma_codigo IN ('EFE', 'TBL', 'TBP', 'MON', 'TRA', 'TAR')"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := IF(@hayTabla,
    CONCAT("SELECT GROUP_CONCAT(CONCAT(forma_orden, ' ', forma_codigo, ' ', forma_nombre, IF(forma_estado = 'A', '', ' (inactiva)'))
                                ORDER BY forma_orden SEPARATOR ' · ') AS formas
              FROM `", @db, "`.dsa_forma_ingreso"),
    "SELECT 'sin Arena: nada que hacer' AS aviso");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla (sólo si ningún pago usa TBL ni TBP):
-- UPDATE dsa_forma_ingreso SET forma_estado = 'A' WHERE forma_codigo IN ('TRA', 'TAR');
-- DELETE FROM dsa_forma_ingreso WHERE forma_codigo IN ('TBL', 'TBP');
-- UPDATE dsa_forma_ingreso SET forma_orden = CASE forma_codigo WHEN 'EFE' THEN 1 WHEN 'TRA' THEN 2 WHEN 'TAR' THEN 3 WHEN 'MON' THEN 4 END
--  WHERE forma_codigo IN ('EFE', 'TRA', 'TAR', 'MON');
