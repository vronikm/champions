-- =====================================================================
-- 025 · League · Que el estado sea el de SU entidad
-- =====================================================================
-- La espina (024) ata partido e inscripción al catálogo dsl_estado con
-- una clave foránea sobre estado_id. Eso garantiza que el estado exista,
-- pero no que sea el correcto: como todos los estados viven en la misma
-- tabla, nada impedía que un partido quedara en «Enviada a revisión», que
-- es un estado de inscripción.
--
-- No es una hipótesis de laboratorio. Los ids se pasan por formulario y
-- por AJAX, y un desplegable mal filtrado o un id copiado de otra
-- pantalla produce exactamente eso. El síntoma sería un partido que
-- desaparece de la clasificación sin motivo aparente, porque su estado no
-- está marcado como efectivo para partidos.
--
-- CÓMO SE CIERRA
--
-- Se añade a cada tabla una columna generada con el nombre de su entidad
-- —un valor constante, no un dato que nadie teclee— y la clave foránea
-- pasa a ser compuesta: (estado_id, entidad). Así el catálogo sólo acepta
-- la fila cuyo estado_entidad coincide.
--
-- La columna es STORED y no VIRTUAL porque una clave foránea necesita un
-- índice, y MySQL no indexa columnas virtuales para este uso. El coste es
-- un byte por fila; el beneficio es que la regla deja de depender de que
-- todos los formularios filtren bien.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- El catálogo necesita una clave sobre la que apoyar la FK compuesta.
-- ---------------------------------------------------------------------
SET @existe := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'dsl_estado'
       AND INDEX_NAME   = 'uk_dsl_estado_entidad'
);

SET @sql := IF(@existe > 0,
    'SELECT ''uk_dsl_estado_entidad ya existe'' AS aviso',
    'ALTER TABLE dsl_estado
       ADD UNIQUE KEY uk_dsl_estado_entidad (estado_id, estado_entidad)');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;


-- ---------------------------------------------------------------------
-- Partido
-- ---------------------------------------------------------------------
ALTER TABLE dsl_partido
    DROP FOREIGN KEY fk_dslpa_estado;

ALTER TABLE dsl_partido
    ADD COLUMN partido_estadoentidad VARCHAR(30)
        GENERATED ALWAYS AS ('partido') STORED AFTER partido_estadoid;

ALTER TABLE dsl_partido
    ADD CONSTRAINT fk_dslpa_estado
        FOREIGN KEY (partido_estadoid, partido_estadoentidad)
        REFERENCES dsl_estado (estado_id, estado_entidad);


-- ---------------------------------------------------------------------
-- Inscripción
-- ---------------------------------------------------------------------
ALTER TABLE dsl_inscripcion
    DROP FOREIGN KEY fk_dsli_estado;

ALTER TABLE dsl_inscripcion
    ADD COLUMN inscripcion_estadoentidad VARCHAR(30)
        GENERATED ALWAYS AS ('inscripcion') STORED AFTER inscripcion_estadoid;

ALTER TABLE dsl_inscripcion
    ADD CONSTRAINT fk_dsli_estado
        FOREIGN KEY (inscripcion_estadoid, inscripcion_estadoentidad)
        REFERENCES dsl_estado (estado_id, estado_entidad);
