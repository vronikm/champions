-- =====================================================================
-- 081 · Arena: reservas fijas (un día de la semana, a la misma hora)
-- =====================================================================
-- QUÉ RESUELVE
--
-- Un cliente que alquila la misma cancha todas las semanas —«los martes de
-- 19:00 a 21:00»— hasta que él o el recinto decidan dejarlo (2026-11-02).
--
--   dsa_reserva_fija   la serie: cliente, instalación, día (1=lunes …
--                      7=domingo, como dsa_horario), horas, desde cuándo
--                      y, al cancelarla, hasta cuándo.
--   reserva_fijaid     en dsa_reserva: la semana concreta pertenece a esa
--                      serie.
--
-- Las próximas semanas existen como reservas normales (se cobran antes o
-- después de usar la cancha, se cancelan sueltas, tienen recibo). Más allá,
-- la serie sigue apartando la franja: verificarDisponibilidad() la consulta
-- para las fechas que todavía no tienen su reserva (después de
-- fija_generadahasta).
--
--   fija_estado   A activa · X cancelada
--
-- ES REPETIBLE: la tabla, la columna, el índice y la clave ajena sólo se
-- crean si faltan.
--
-- Aplicar con:  mysql -u USUARIO -p --default-character-set=utf8mb4 BASE < 081_arena_reserva_fija.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dsa_reserva_fija (
    fija_id                 INT NOT NULL AUTO_INCREMENT,
    fija_codigo             VARCHAR(20)  NOT NULL,
    fija_clienteid          INT          NOT NULL,
    fija_instalacionid      INT          NOT NULL,
    fija_sedeid             INT          NOT NULL,
    fija_dia                TINYINT      NOT NULL,
    fija_horainicio         TIME         NOT NULL,
    fija_horafin            TIME         NOT NULL,
    fija_desde              DATE         NOT NULL,
    fija_hasta              DATE         NULL,
    fija_generadahasta      DATE         NULL,
    fija_estado             CHAR(1)      NOT NULL DEFAULT 'A',
    fija_observacion        VARCHAR(250) NULL,
    fija_motivocancelacion  VARCHAR(250) NULL,
    fija_usuarioid          INT          NULL,
    fija_fecharegistro      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fija_fechacancelacion   TIMESTAMP    NULL,
    PRIMARY KEY (fija_id),
    UNIQUE KEY uq_fija_codigo (fija_codigo),
    KEY idx_fija_agenda (fija_instalacionid, fija_dia, fija_estado),
    KEY idx_fija_cliente (fija_clienteid),
    CONSTRAINT fk_fija_cliente     FOREIGN KEY (fija_clienteid)     REFERENCES dsa_cliente (cliente_id),
    CONSTRAINT fk_fija_instalacion FOREIGN KEY (fija_instalacionid) REFERENCES dsa_instalacion (instalacion_id),
    CONSTRAINT fk_fija_sede        FOREIGN KEY (fija_sedeid)        REFERENCES general_sede (sede_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_fija_rango CHECK (fija_horafin > fija_horainicio),
    CONSTRAINT ck_fija_dia   CHECK (fija_dia BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva' AND COLUMN_NAME = 'reserva_fijaid');
SET @sql := IF(@falta,
    'ALTER TABLE dsa_reserva ADD COLUMN reserva_fijaid INT NULL AFTER reserva_sedeid',
    'SELECT ''reserva_fijaid ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva' AND INDEX_NAME = 'idx_reserva_fija');
SET @sql := IF(@falta,
    'ALTER TABLE dsa_reserva ADD INDEX idx_reserva_fija (reserva_fijaid, reserva_fecha)',
    'SELECT ''idx_reserva_fija ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva' AND CONSTRAINT_NAME = 'fk_reserva_fija');
SET @sql := IF(@falta,
    'ALTER TABLE dsa_reserva ADD CONSTRAINT fk_reserva_fija FOREIGN KEY (reserva_fijaid) REFERENCES dsa_reserva_fija (fija_id)',
    'SELECT ''fk_reserva_fija ya existe'' AS aviso');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS reservas_fijas FROM dsa_reserva_fija;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_reserva' AND COLUMN_NAME = 'reserva_fijaid';

-- Para revertir:
-- ALTER TABLE dsa_reserva DROP FOREIGN KEY fk_reserva_fija;
-- ALTER TABLE dsa_reserva DROP INDEX idx_reserva_fija;
-- ALTER TABLE dsa_reserva DROP COLUMN reserva_fijaid;
-- DROP TABLE dsa_reserva_fija;
