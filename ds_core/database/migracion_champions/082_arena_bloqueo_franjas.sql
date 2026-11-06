-- =====================================================================
-- 082 · Arena: bloqueos por días de la semana y franjas
-- =====================================================================
-- QUÉ RESUELVE
--
-- Hasta ahora un bloqueo era un periodo continuo: «del 6 nov 18:00 al
-- 11 nov 19:00» cerraba la cancha 5 días y 1 hora seguidos. Lo que hacía
-- falta muchas veces era otra cosa: «del 6 al 11 de noviembre, de lunes a
-- viernes, de 18:00 a 19:00» (2026-11-06).
--
--   dsa_bloqueo_grupo   la regla tal como se escribió: fechas, días
--                       (1=lunes … 7=domingo, como dsa_horario) y franjas
--                       ('18:00-19:00,20:00-21:00', o vacío = todo el día).
--   bloqueo_grupoid     en dsa_bloqueo: cada día y tramo de horas de la
--                       regla es un bloqueo normal, de modo que la
--                       disponibilidad, las reservas fijas y League los leen
--                       igual que antes. Al editar la regla se rehacen; al
--                       borrarla se van con ella (ON DELETE CASCADE).
--
-- Los bloqueos ya existentes quedan como periodos continuos (grupo nulo).
--
-- ES REPETIBLE: la tabla, la columna y la clave ajena sólo se crean si faltan.
--
-- Aplicar con:  mysql -u USUARIO -p --default-character-set=utf8mb4 BASE < 082_arena_bloqueo_franjas.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dsa_bloqueo_grupo (
    grupo_id             INT NOT NULL AUTO_INCREMENT,
    grupo_instalacionid  INT          NOT NULL,
    grupo_tipo           CHAR(1)      NOT NULL DEFAULT 'M',
    grupo_fechadesde     DATE         NOT NULL,
    grupo_fechahasta     DATE         NOT NULL,
    grupo_dias           VARCHAR(13)  NOT NULL,
    grupo_franjas        VARCHAR(1000) NOT NULL DEFAULT '',
    grupo_motivo         VARCHAR(150) NULL,
    grupo_usuarioid      INT          NULL,
    grupo_fecharegistro  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (grupo_id),
    KEY idx_grupo_instalacion (grupo_instalacionid, grupo_fechadesde),
    CONSTRAINT fk_grupo_instalacion FOREIGN KEY (grupo_instalacionid) REFERENCES dsa_instalacion (instalacion_id) ON DELETE CASCADE,
    CONSTRAINT ck_grupo_fechas CHECK (grupo_fechahasta >= grupo_fechadesde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_bloqueo' AND COLUMN_NAME = 'bloqueo_grupoid');
SET @sql := IF(@falta,
    'ALTER TABLE dsa_bloqueo ADD COLUMN bloqueo_grupoid INT NULL AFTER bloqueo_instalacionid',
    'SELECT ''bloqueo_grupoid ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_bloqueo' AND CONSTRAINT_NAME = 'fk_bloqueo_grupo');
SET @sql := IF(@falta,
    'ALTER TABLE dsa_bloqueo ADD CONSTRAINT fk_bloqueo_grupo FOREIGN KEY (bloqueo_grupoid) REFERENCES dsa_bloqueo_grupo (grupo_id) ON DELETE CASCADE',
    'SELECT ''fk_bloqueo_grupo ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS reglas_de_bloqueo FROM dsa_bloqueo_grupo;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_bloqueo' AND COLUMN_NAME = 'bloqueo_grupoid';

-- Para revertir (los bloqueos de las reglas se borran con ellas):
-- DELETE FROM dsa_bloqueo_grupo;
-- ALTER TABLE dsa_bloqueo DROP FOREIGN KEY fk_bloqueo_grupo;
-- ALTER TABLE dsa_bloqueo DROP COLUMN bloqueo_grupoid;
-- DROP TABLE dsa_bloqueo_grupo;
