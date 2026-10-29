-- =====================================================================
-- 078 · Sedes: activa o inactiva
-- =====================================================================
-- QUÉ RESUELVE
--
-- Core › Sedes (sedeList) permite inactivar una sede que deja de operar sin
-- borrarla (no se puede: tiene alumnos, pagos e historial).
--
--   sede_estado  A activa (por defecto) · I inactiva
--
-- Una sede inactiva no se ofrece para lo NUEVO (alumnos, empleados,
-- horarios, lugares, equipos, ingresos y egresos, sedes de los usuarios) y
-- su enlace de inscripción en línea deja de recibir inscripciones. Lo que ya
-- es de ella se sigue viendo y editando, y los reportes la incluyen.
-- La regla vive en sql_sede_activa() (ds_core/inc/seguridad.php).
--
-- ES REPETIBLE: la columna sólo se agrega si falta. Todas quedan activas.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 078_sede_estado.sql

SET NAMES utf8mb4;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'general_sede' AND COLUMN_NAME = 'sede_estado');
SET @sql := IF(@falta,
    'ALTER TABLE general_sede ADD COLUMN sede_estado CHAR(1) NOT NULL DEFAULT ''A'' AFTER sede_firma',
    'SELECT ''sede_estado ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT sede_id, sede_nombre, sede_estado FROM general_sede ORDER BY sede_id;

-- Para revertir:
-- ALTER TABLE general_sede DROP COLUMN sede_estado;
