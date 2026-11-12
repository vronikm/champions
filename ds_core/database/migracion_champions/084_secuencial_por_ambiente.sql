-- 084_secuencial_por_ambiente.sql
-- Numeración de comprobantes separada por ambiente del SRI.
--
-- El SRI lleva dos registros independientes: lo emitido en pruebas
-- (celcer.sri.gob.ec) no existe en producción (cel.sri.gob.ec). El sistema
-- tenía UN contador por (tipo, establecimiento, punto), así que cada factura
-- de ensayo gastaba un número de la serie real y dejaba un hueco en
-- producción; y la clave única del comprobante no dejaba repetir en
-- producción un número ya usado en pruebas.
--
-- Qué hace:
--   1. facturas_electronicas_secuenciales: columna `ambiente` y clave única
--      (tipo, establecimiento, punto, ambiente). Cada contador existente
--      queda en el ambiente de la última factura emitida desde su punto (es
--      el que lo hizo avanzar); sin facturas, en el configurado HOY en
--      facturas_electronicas_config.
--   2. facturas_electronicas (Fútbol) y dsl_factura (League): el ambiente
--      entra en la clave única del número.
--   3. Fútbol toma ahora establecimiento y punto de Core › Puntos de emisión
--      y no factura sin un punto asignado. Si el módulo no tiene ninguno, se
--      crea desde facturas_electronicas_config, como hizo la 017.
--
-- El «Siguiente secuencial» de cada ambiente se define en Core › Puntos de
-- emisión y se escribe en este contador (ds_core/inc/facturacion.php).
--
-- Idempotente: cada paso comprueba el esquema antes de tocarlo.
SET NAMES utf8mb4;

/* ---------- 1. Contador por ambiente ---------- */

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'facturas_electronicas_secuenciales'
                AND COLUMN_NAME = 'ambiente');
SET @sql := IF(@hay = 0,
    "ALTER TABLE facturas_electronicas_secuenciales
        ADD COLUMN ambiente CHAR(1) NOT NULL DEFAULT '1' AFTER punto_emision",
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Sólo la primera vez (columna recién creada): cada contador que había
-- pertenece al ambiente de la última factura emitida desde su punto (el
-- dígito 24 de la clave de acceso). Sin facturas, al ambiente configurado.
SET @amb := COALESCE((SELECT IF(ambiente = '2', '2', '1') FROM facturas_electronicas_config
                       WHERE config_lock = 'X' LIMIT 1), '1');
SET @sql := IF(@hay = 0,
    CONCAT("UPDATE facturas_electronicas_secuenciales S
               SET S.ambiente = COALESCE(
                   (SELECT IF(SUBSTRING(V.clave_acceso, 24, 1) = '2', '2', '1')
                      FROM v_comprobante_emitido V
                     WHERE V.tipo_comprobante = S.tipo_comprobante
                       AND V.establecimiento  = S.establecimiento
                       AND V.punto_emision    = S.punto_emision
                     ORDER BY CAST(V.secuencial AS UNSIGNED) DESC
                     LIMIT 1),
                   '", @amb, "')"),
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @cols := (SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
                FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'facturas_electronicas_secuenciales'
                 AND INDEX_NAME = 'uk_fe_secuencial');
SET @sql := IF(@cols IS NULL,
    'ALTER TABLE facturas_electronicas_secuenciales
        ADD UNIQUE KEY uk_fe_secuencial (tipo_comprobante, establecimiento, punto_emision, ambiente)',
    IF(@cols <> 'tipo_comprobante,establecimiento,punto_emision,ambiente',
       'ALTER TABLE facturas_electronicas_secuenciales
           DROP INDEX uk_fe_secuencial,
           ADD UNIQUE KEY uk_fe_secuencial (tipo_comprobante, establecimiento, punto_emision, ambiente)',
       'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* ---------- 2. Número único por ambiente: Fútbol ---------- */

SET @cols := (SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
                FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'facturas_electronicas'
                 AND INDEX_NAME = 'uk_fe_numero');
SET @sql := IF(@cols IS NULL,
    'ALTER TABLE facturas_electronicas
        ADD UNIQUE KEY uk_fe_numero (tipo_comprobante, establecimiento, punto_emision, ambiente, secuencial)',
    IF(@cols <> 'tipo_comprobante,establecimiento,punto_emision,ambiente,secuencial',
       'ALTER TABLE facturas_electronicas
           DROP INDEX uk_fe_numero,
           ADD UNIQUE KEY uk_fe_numero (tipo_comprobante, establecimiento, punto_emision, ambiente, secuencial)',
       'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* ---------- 3. Número único por ambiente: League ---------- */

SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsl_factura');
SET @cols := (SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
                FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'dsl_factura'
                 AND INDEX_NAME = 'uk_dslf_numero');
SET @nuevo := 'factura_tipocomprobante,factura_establecimiento,factura_puntoemision,factura_ambiente,factura_secuencial';
SET @sql := IF(@tabla = 0, 'DO 0',
    IF(@cols IS NULL,
       'ALTER TABLE dsl_factura
           ADD UNIQUE KEY uk_dslf_numero (factura_tipocomprobante, factura_establecimiento,
                                          factura_puntoemision, factura_ambiente, factura_secuencial)',
       IF(@cols <> @nuevo,
          'ALTER TABLE dsl_factura
              DROP INDEX uk_dslf_numero,
              ADD UNIQUE KEY uk_dslf_numero (factura_tipocomprobante, factura_establecimiento,
                                             factura_puntoemision, factura_ambiente, factura_secuencial)',
          'DO 0')));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* ---------- 4. Punto de emisión de Fútbol ---------- */

-- Fútbol (clave 'basketball' en ds_core/modulos.php) ya no emite con el par
-- de facturas_electronicas_config sino con su punto de Core › Puntos de
-- emisión. Si una instalación no lo tiene (la 017 corrió antes de cargar la
-- configuración), se crea con ese mismo par para que siga facturando igual.
-- El inicio es el siguiente libre: nunca por debajo de lo ya emitido.
-- Si otro módulo ocupa ya ese par, no se hace nada: se asigna a mano.
INSERT INTO facturas_electronicas_punto_emision
       (punto_modulo, punto_establecimiento, punto_codigo,
        punto_secuencialinicio, punto_descripcion)
SELECT 'basketball',
       C.codigo_establecimiento,
       C.punto_emision,
       GREATEST(
           COALESCE(C.secuencial_inicio, 1),
           COALESCE((SELECT MAX(CAST(F.secuencial AS UNSIGNED))
                       FROM facturas_electronicas F
                      WHERE F.establecimiento = C.codigo_establecimiento
                        AND F.punto_emision   = C.punto_emision
                        AND F.ambiente        = '2'), 0) + 1
       ),
       'Escuela — pensiones y matrículas'
  FROM facturas_electronicas_config C
 WHERE C.config_lock = 'X'
   AND C.codigo_establecimiento REGEXP '^[0-9]{3}$' AND C.codigo_establecimiento <> '000'
   AND C.punto_emision          REGEXP '^[0-9]{3}$' AND C.punto_emision          <> '000'
   AND NOT EXISTS (SELECT 1 FROM facturas_electronicas_punto_emision P
                    WHERE P.punto_modulo = 'basketball')
    ON DUPLICATE KEY UPDATE punto_modulo = punto_modulo;
