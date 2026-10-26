-- =====================================================================
-- 071 · Facturación: estado del servicio, proveedor, teléfono y correo
-- =====================================================================
-- QUÉ RESUELVE
--
-- Core › Facturación electrónica no tenía cuatro bloques que sí tiene la
-- configuración de cdjg. Sus datos van en la fila única de
-- facturas_electronicas_config:
--
--   servicio_activo         1 en servicio · 0 en mantenimiento: con 0 no se
--                           genera ni se envía nada al SRI (el registro de
--                           pagos no se ve afectado)
--   mantenimiento_mensaje   aviso propio para los usuarios (vacío = el de
--                           siempre)
--   telefono                teléfono del emisor, para el RIDE
--   ruc_proveedor           RUC del proveedor del sistema de facturación
--                           (resolución NAC-DGERCGC26-00000027, art. 5);
--                           vacío = el comprobante sale como antes
--   etiqueta_ruc_proveedor  nombre del campoAdicional en el XML
--   correo_from …           remitente de las facturas por correo y, si se
--   smtp_…                  activa, el servidor SMTP. La clave se guarda
--                           cifrada (AES-256), nunca en claro.
--
-- Valores por defecto = el comportamiento de hoy: en servicio, sin
-- proveedor, correo con mail() del servidor.
--
-- ES REPETIBLE: cada columna se añade sólo si no existe (vale en MySQL 8
-- y en MariaDB).
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 071_facturacion_servicio_correo.sql

SET NAMES utf8mb4;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'servicio_activo');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN servicio_activo TINYINT(1) NOT NULL DEFAULT 1 AFTER config_lock', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'mantenimiento_mensaje');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN mantenimiento_mensaje VARCHAR(300) NOT NULL DEFAULT '''' AFTER servicio_activo', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'telefono');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN telefono VARCHAR(50) NOT NULL DEFAULT '''' AFTER direccion_establecimiento', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'ruc_proveedor');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN ruc_proveedor VARCHAR(13) NOT NULL DEFAULT '''' AFTER contribuyente_rimpe', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'etiqueta_ruc_proveedor');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN etiqueta_ruc_proveedor VARCHAR(300) NOT NULL DEFAULT ''RUC Proveedor'' AFTER ruc_proveedor', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'correo_from');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN correo_from VARCHAR(150) NOT NULL DEFAULT '''' AFTER etiqueta_ruc_proveedor', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'correo_from_nombre');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN correo_from_nombre VARCHAR(150) NOT NULL DEFAULT '''' AFTER correo_from', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'smtp_activo');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN smtp_activo TINYINT(1) NOT NULL DEFAULT 0 AFTER correo_from_nombre', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'smtp_host');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN smtp_host VARCHAR(150) NOT NULL DEFAULT '''' AFTER smtp_activo', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'smtp_port');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587 AFTER smtp_host', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'smtp_seguridad');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN smtp_seguridad VARCHAR(10) NOT NULL DEFAULT ''tls'' AFTER smtp_port', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'smtp_usuario');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN smtp_usuario VARCHAR(150) NOT NULL DEFAULT '''' AFTER smtp_seguridad', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config' AND column_name = 'smtp_clave_cifrada');
SET @sql := IF(@existe = 0, 'ALTER TABLE facturas_electronicas_config ADD COLUMN smtp_clave_cifrada TEXT NULL AFTER smtp_usuario', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT column_name, column_type, column_default
  FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'facturas_electronicas_config'
   AND column_name IN ('servicio_activo', 'mantenimiento_mensaje', 'telefono', 'ruc_proveedor', 'etiqueta_ruc_proveedor',
                       'correo_from', 'correo_from_nombre', 'smtp_activo', 'smtp_host', 'smtp_port',
                       'smtp_seguridad', 'smtp_usuario', 'smtp_clave_cifrada')
 ORDER BY ordinal_position;

-- Para revertir:
-- ALTER TABLE facturas_electronicas_config
--   DROP COLUMN servicio_activo, DROP COLUMN mantenimiento_mensaje, DROP COLUMN telefono,
--   DROP COLUMN ruc_proveedor, DROP COLUMN etiqueta_ruc_proveedor, DROP COLUMN correo_from,
--   DROP COLUMN correo_from_nombre, DROP COLUMN smtp_activo, DROP COLUMN smtp_host, DROP COLUMN smtp_port,
--   DROP COLUMN smtp_seguridad, DROP COLUMN smtp_usuario, DROP COLUMN smtp_clave_cifrada;
