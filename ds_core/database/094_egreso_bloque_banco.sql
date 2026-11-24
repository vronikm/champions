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
--
--
-- PHPMYADMIN Y information_schema (2026-11-24)
--
-- Al ejecutar un script, phpMyAdmin cambia la base activa a la que nombra
-- cada sentencia: después de una consulta a information_schema, la
-- conexión queda EN information_schema y lo que sigue sin base explícita
-- falla (#1044 acceso negado al ALTER, #1109 tabla desconocida al SELECT).
-- Por eso la base de la escuela se guarda en @db ANTES de mirar
-- information_schema, y todo lo que toca sus tablas va con `@db`.tabla
-- en SQL dinámico. Hay que abrir la base de la escuela antes de ejecutar.
-- =====================================================================

SET NAMES utf8mb4;

-- La base de la escuela, antes de nombrar information_schema.
SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 1. egreso_rubroid
SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'balance_egreso'
                  AND COLUMN_NAME = 'egreso_rubroid');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.balance_egreso
        ADD COLUMN egreso_rubroid CHAR(3) NULL DEFAULT NULL
            COMMENT 'Bloque del reporte Ingresos del mes: rubro (RPC, RIN, RKE, RNU, ROT, RPE). NULL = egresos generales'
            AFTER egreso_concepto"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. egreso_lugarid
SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'balance_egreso'
                  AND COLUMN_NAME = 'egreso_lugarid');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.balance_egreso
        ADD COLUMN egreso_lugarid INT NULL DEFAULT NULL
            COMMENT 'Sólo pensiones (RPE): asistencia_lugar.lugar_id. NULL = toda la sede'
            AFTER egreso_rubroid"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 3. Los dos bancos
SET @sql := CONCAT("INSERT INTO `", @db, "`.general_tabla_catalogo
        (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
    SELECT 'EBL', t.tabla_id, 'Transferencia Banco de Loja', 'A'
      FROM `", @db, "`.general_tabla t
     WHERE t.tabla_nombre = 'forma_entregaingreso'
       AND NOT EXISTS (SELECT 1 FROM `", @db, "`.general_tabla_catalogo WHERE catalogo_valor = 'EBL')");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := CONCAT("INSERT INTO `", @db, "`.general_tabla_catalogo
        (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
    SELECT 'EBP', t.tabla_id, 'Transferencia Banco Pichincha', 'A'
      FROM `", @db, "`.general_tabla t
     WHERE t.tabla_nombre = 'forma_entregaingreso'
       AND NOT EXISTS (SELECT 1 FROM `", @db, "`.general_tabla_catalogo WHERE catalogo_valor = 'EBP')");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación: las dos columnas y las formas de entrega.
SET @sql := CONCAT("SELECT c.catalogo_valor, c.catalogo_descripcion,
           (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'balance_egreso'
               AND COLUMN_NAME IN ('egreso_rubroid', 'egreso_lugarid')) AS columnas_nuevas_de_2
      FROM `", @db, "`.general_tabla_catalogo c
      JOIN `", @db, "`.general_tabla t ON t.tabla_id = c.catalogo_tablaid
     WHERE t.tabla_nombre = 'forma_entregaingreso' ORDER BY 1");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
