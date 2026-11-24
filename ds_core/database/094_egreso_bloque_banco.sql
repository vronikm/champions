-- =====================================================================
-- 094 · Egresos: a qué bloque del reporte pertenecen y por qué banco salen
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- El reporte mensual de la escuela (Insights › Ingresos del mes) pone cada
-- egreso bajo su bloque —el pago de los profesores de una sede bajo sus
-- pensiones, la confección de polines bajo «Kit entrenamiento», la de
-- uniformes bajo «Uniformes»— y en la columna del banco por el que salió.
-- Un egreso (balance_egreso) sólo guardaba sede, concepto y «Transferencia»,
-- así que no había de dónde sacar ni el bloque ni el banco.
--
--
-- QUE HACE ESTA MIGRACION
--
--   1. balance_egreso.egreso_rubroid — el bloque: el rubro de los pagos de
--      alumnos (RPC campeonato, RIN inscripción, RKE kit, RNU uniforme,
--      ROT otros, RPE pensiones). NULL = sin asignar: el reporte lo muestra
--      en «Egresos generales».
--   2. balance_egreso.egreso_lugarid — sólo para pensiones: el lugar de
--      entrenamiento (asistencia_lugar) cuando la sede tiene más de uno.
--      NULL = toda la sede.
--   3. Dos formas de entrega nuevas en el catálogo «forma_entregaingreso»:
--      EBL Transferencia Banco de Loja y EBP Transferencia Banco Pichincha.
--      «Transferencia» (EIT) se conserva para lo ya registrado.
--
-- No asigna bloque a los egresos que ya existen: eso lo decide la escuela
-- al editarlos en Fútbol › Balance › Egresos.
--
--
-- SE PUEDE REPETIR
--
-- Cada columna se añade sólo si falta y cada valor del catálogo sólo si no
-- existe. Sin claves ajenas a propósito: balance_egreso puede estar en
-- MyISAM en alguna base, y una clave haría fallar toda la migración.
-- =====================================================================

SET NAMES utf8mb4;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'balance_egreso'
                  AND COLUMN_NAME = 'egreso_rubroid');
SET @sql := IF(@falta,
    "ALTER TABLE balance_egreso
        ADD COLUMN egreso_rubroid CHAR(3) NULL DEFAULT NULL
            COMMENT 'Bloque del reporte Ingresos del mes: rubro (RPC, RIN, RKE, RNU, ROT, RPE). NULL = egresos generales'
            AFTER egreso_concepto",
    "SELECT 'egreso_rubroid ya existe' AS info");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'balance_egreso'
                  AND COLUMN_NAME = 'egreso_lugarid');
SET @sql := IF(@falta,
    "ALTER TABLE balance_egreso
        ADD COLUMN egreso_lugarid INT NULL DEFAULT NULL
            COMMENT 'Sólo pensiones (RPE): asistencia_lugar.lugar_id. NULL = toda la sede'
            AFTER egreso_rubroid",
    "SELECT 'egreso_lugarid ya existe' AS info");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

INSERT INTO general_tabla_catalogo (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
SELECT 'EBL', t.tabla_id, 'Transferencia Banco de Loja', 'A'
  FROM general_tabla t
 WHERE t.tabla_nombre = 'forma_entregaingreso'
   AND NOT EXISTS (SELECT 1 FROM general_tabla_catalogo WHERE catalogo_valor = 'EBL');

INSERT INTO general_tabla_catalogo (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
SELECT 'EBP', t.tabla_id, 'Transferencia Banco Pichincha', 'A'
  FROM general_tabla t
 WHERE t.tabla_nombre = 'forma_entregaingreso'
   AND NOT EXISTS (SELECT 1 FROM general_tabla_catalogo WHERE catalogo_valor = 'EBP');

-- Comprobación
SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'balance_egreso'
   AND COLUMN_NAME IN ('egreso_rubroid', 'egreso_lugarid');
SELECT c.catalogo_valor, c.catalogo_descripcion
  FROM general_tabla_catalogo c JOIN general_tabla t ON t.tabla_id = c.catalogo_tablaid
 WHERE t.tabla_nombre = 'forma_entregaingreso' ORDER BY 1;
