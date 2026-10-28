-- =====================================================================
-- 076 · Equipos de torneo: valor de inscripción y «todas las sedes»
-- =====================================================================
-- QUÉ RESUELVE
--
-- Fútbol › Torneos › Equipos (equipoList):
--
--   equipo_valorinscripcion  lo que se cobra a cada alumno que juega el
--                            torneo con ese equipo (USD). Vacío = sin definir.
--
--   «Todas las sedes»        un equipo con niños de todas las sedes se guarda
--                            con equipo_sedeid = 0 (la columna es NOT NULL y
--                            no hay sede 0). No necesita cambio de esquema; las
--                            consultas ya unen la sede con LEFT JOIN.
--
-- ES REPETIBLE: la columna sólo se agrega si falta.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 076_equipo_valor_inscripcion.sql

SET NAMES utf8mb4;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'torneo_equipo' AND COLUMN_NAME = 'equipo_valorinscripcion');
SET @sql := IF(@falta,
    'ALTER TABLE torneo_equipo ADD COLUMN equipo_valorinscripcion DECIMAL(10,2) NULL AFTER equipo_categoria',
    'SELECT ''equipo_valorinscripcion ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'torneo_equipo' AND COLUMN_NAME = 'equipo_valorinscripcion';

-- Para revertir:
-- ALTER TABLE torneo_equipo DROP COLUMN equipo_valorinscripcion;
