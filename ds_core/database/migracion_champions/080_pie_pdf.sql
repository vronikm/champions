-- =====================================================================
-- 080 · Organización: texto del pie de los PDF
-- =====================================================================
-- QUÉ RESUELVE
--
-- Core › Organización guarda una línea de texto —publicidad, contacto—
-- que sale al final de cada página de todos los reportes y recibos en PDF
-- (2026-10-30). La dibuja ds_pdf_pie() (ds_core/inc/organizacion.php)
-- desde FPDF; los carnés no la llevan.
--
--   escuela_pie_pdf  hasta 200 caracteres · NULL o vacío: sin pie
--
-- ES REPETIBLE: la columna sólo se agrega si falta. Queda vacía: el texto
-- se escribe en Core › Organización.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 080_pie_pdf.sql

SET NAMES utf8mb4;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'general_escuela' AND COLUMN_NAME = 'escuela_pie_pdf');
SET @sql := IF(@falta,
    'ALTER TABLE general_escuela ADD COLUMN escuela_pie_pdf VARCHAR(200) NULL AFTER escuela_firma',
    'SELECT ''escuela_pie_pdf ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT escuela_id, escuela_nombre, escuela_pie_pdf FROM general_escuela ORDER BY escuela_id;

-- Para revertir:
-- ALTER TABLE general_escuela DROP COLUMN escuela_pie_pdf;
