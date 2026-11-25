-- =====================================================================
-- 096 · Alumnos de otra sede inscritos en una sede «personalizada»
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- La sede PERSONALIZADO (Champions) es para el mejoramiento físico y
-- deportivo individual: se inscriben ahí alumnos de otras sedes y se les
-- cobra pensión aparte. La pensión, la deuda y la cobranza se calculan por
-- REGISTRO de alumno con la pensión de su sede, y la identificación no se
-- puede repetir: hacía falta un registro propio en esa sede, con un código
-- interno (PA000001…) en lugar de la cédula, y vinculado al original.
--
--
-- QUE HACE ESTA MIGRACION
--
--   1. general_sede.sede_codigoalumno — prefijo del código interno de la
--      sede (p. ej. 'PA'). Una sede con prefijo acepta alumnos de otras
--      sedes (Fútbol › ficha del alumno › «Inscribir en …»). Se pone 'PA'
--      en la sede llamada PERSONALIZADO, si existe; en las demás, NULL.
--   2. sujeto_alumno.alumno_origenid — el alumno del que salió el registro
--      (NULL = alumno normal). Clave ajena con ON DELETE SET NULL cuando
--      la tabla es InnoDB.
--   3. Tipo de documento COD «CÓDIGO INTERNO» en el catálogo tipo_documento
--      (catalogo_valor es clave única de TODO el catálogo: se comprueba
--      que COD esté libre).
--
--
-- SE PUEDE REPETIR
--
-- Cada columna, índice y clave se añade sólo si falta; el prefijo sólo se
-- pone si la sede aún no tiene uno; el catálogo, sólo si COD no existe.
--
--
-- PHPMYADMIN Y information_schema: como en la 094, la base de la escuela
-- se guarda en @db antes de mirar information_schema y todo va con
-- `@db`.tabla. Hay que abrir la base de la escuela antes de ejecutar.
-- =====================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 1. general_sede.sede_codigoalumno
SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'general_sede'
                  AND COLUMN_NAME = 'sede_codigoalumno');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.general_sede
        ADD COLUMN sede_codigoalumno VARCHAR(3) NULL DEFAULT NULL
            COMMENT 'Prefijo del código interno (PA…): la sede acepta alumnos de otras sedes. NULL = sede normal'"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := CONCAT("UPDATE `", @db, "`.general_sede
       SET sede_codigoalumno = 'PA'
     WHERE UPPER(TRIM(sede_nombre)) = 'PERSONALIZADO' AND sede_codigoalumno IS NULL");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. sujeto_alumno.alumno_origenid, su índice y su clave ajena
SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sujeto_alumno'
                  AND COLUMN_NAME = 'alumno_origenid');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.sujeto_alumno
        ADD COLUMN alumno_origenid INT NULL DEFAULT NULL
            COMMENT 'Alumno del que salió este registro (inscripción en una sede con código). NULL = alumno normal'
            AFTER alumno_repreid"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sujeto_alumno'
                  AND INDEX_NAME = 'ix_alumno_origen');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.sujeto_alumno ADD KEY ix_alumno_origen (alumno_origenid)"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @innodb := (SELECT ENGINE = 'InnoDB' FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sujeto_alumno');
SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'sujeto_alumno'
                  AND CONSTRAINT_NAME = 'fk_alumno_origen');
SET @sql := IF(@falta AND @innodb,
    CONCAT("ALTER TABLE `", @db, "`.sujeto_alumno
        ADD CONSTRAINT fk_alumno_origen FOREIGN KEY (alumno_origenid)
            REFERENCES `", @db, "`.sujeto_alumno (alumno_id) ON DELETE SET NULL ON UPDATE CASCADE"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 3. Tipo de documento COD «CÓDIGO INTERNO»
SET @sql := CONCAT("INSERT INTO `", @db, "`.general_tabla_catalogo
        (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
    SELECT 'COD', t.tabla_id, 'CÓDIGO INTERNO', 'A'
      FROM `", @db, "`.general_tabla t
     WHERE t.tabla_nombre = 'tipo_documento'
       AND NOT EXISTS (SELECT 1 FROM `", @db, "`.general_tabla_catalogo WHERE catalogo_valor = 'COD')");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := CONCAT("SELECT s.sede_id, s.sede_nombre, s.sede_codigoalumno,
           (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = '", @db, "' AND TABLE_NAME = 'sujeto_alumno' AND COLUMN_NAME = 'alumno_origenid') AS alumno_origenid,
           (SELECT catalogo_descripcion FROM `", @db, "`.general_tabla_catalogo WHERE catalogo_valor = 'COD') AS tipo_cod
      FROM `", @db, "`.general_sede s ORDER BY s.sede_id");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla:
-- ALTER TABLE sujeto_alumno DROP FOREIGN KEY fk_alumno_origen, DROP KEY ix_alumno_origen, DROP COLUMN alumno_origenid;
-- ALTER TABLE general_sede DROP COLUMN sede_codigoalumno;
-- DELETE FROM general_tabla_catalogo WHERE catalogo_valor = 'COD';
