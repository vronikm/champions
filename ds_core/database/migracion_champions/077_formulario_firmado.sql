-- =====================================================================
-- 077 · Formulario de protección de datos en papel: quién y cuándo
-- =====================================================================
-- QUÉ RESUELVE
--
-- El formulario en papel (representanteFLPD) firmado valida las dos
-- autorizaciones LOPDP —tratamiento de datos y uso de imagen— de los
-- alumnos del representante (Fútbol › Consentimientos LOPDP). Hasta ahora
-- sólo se guardaba SI estaba firmado (alumno_representante.repre_firmado).
--
--   repre_firmado_fecha    cuándo se marcó como firmado
--   repre_firmado_usuario  quién lo marcó
--
-- Con eso el historial de cada alumno lo muestra como una autorización más
-- (como la del enlace), y una autorización posterior a una respuesta
-- registrada la reemplaza. Los marcados antes de esta migración quedan sin
-- fecha: valen para lo que no tiene respuesta.
--
-- ES REPETIBLE: cada columna sólo se agrega si falta.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 077_formulario_firmado.sql

SET NAMES utf8mb4;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alumno_representante' AND COLUMN_NAME = 'repre_firmado_fecha');
SET @sql := IF(@falta,
    'ALTER TABLE alumno_representante ADD COLUMN repre_firmado_fecha DATETIME NULL AFTER repre_firmado',
    'SELECT ''repre_firmado_fecha ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alumno_representante' AND COLUMN_NAME = 'repre_firmado_usuario');
SET @sql := IF(@falta,
    'ALTER TABLE alumno_representante ADD COLUMN repre_firmado_usuario VARCHAR(50) NULL AFTER repre_firmado_fecha',
    'SELECT ''repre_firmado_usuario ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alumno_representante' AND COLUMN_NAME LIKE 'repre\_firmado%';

-- Para revertir:
-- ALTER TABLE alumno_representante DROP COLUMN repre_firmado_usuario, DROP COLUMN repre_firmado_fecha;
