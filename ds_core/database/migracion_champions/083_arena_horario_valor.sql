-- =====================================================================
-- 083 · Arena: valor por hora propio de cada franja del horario
-- =====================================================================
-- QUÉ RESUELVE
--
-- El valor por hora de una instalación no es el mismo a todas horas: la
-- noche o el fin de semana pueden costar más (2026-11-06).
--
--   horario_valorhora   en dsa_horario: lo que cuesta la hora en esa
--                       franja. NULL = el valor de la instalación
--                       (instalacion_valorhora), de modo que si éste cambia
--                       en Instalaciones, las franjas sin valor propio lo
--                       siguen.
--
-- Al reservar, el valor se busca así: tarifa especial (dsa_tarifa) →
-- valor de la franja → valor de la instalación. Una reserva no salta de
-- una franja a otra, así que una reserva tiene un solo valor por hora.
-- Las reservas ya hechas conservan el valor con el que se registraron.
--
-- ES REPETIBLE: la columna sólo se crea si falta.
--
-- Aplicar con:  mysql -u USUARIO -p --default-character-set=utf8mb4 BASE < 083_arena_horario_valor.sql

SET NAMES utf8mb4;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_horario' AND COLUMN_NAME = 'horario_valorhora');
SET @sql := IF(@falta,
    'ALTER TABLE dsa_horario ADD COLUMN horario_valorhora DECIMAL(10,2) NULL AFTER horario_hasta',
    'SELECT ''horario_valorhora ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS franjas, SUM(horario_valorhora IS NOT NULL) AS con_valor_propio FROM dsa_horario;

-- Para revertir:
-- ALTER TABLE dsa_horario DROP COLUMN horario_valorhora;
