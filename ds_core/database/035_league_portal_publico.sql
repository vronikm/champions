-- =====================================================================
-- 035 · League · Portal público: qué se publica y qué no
-- =====================================================================
-- El portal público es la única superficie de League sin autenticación:
-- lo lee cualquiera, incluido quien no tiene nada que ver con la liga. Y
-- lo que hay al otro lado de dsl_persona son cédulas, fechas de
-- nacimiento y fotografías, en buena parte de MENORES DE EDAD.
--
-- Publicarlo tal cual no sería un descuido de configuración: sería una
-- infracción de la LOPDP servida en HTML indexable por buscadores.
--
-- TRES DECISIONES, EN ORDEN DE IMPORTANCIA
--
-- 1. LA CÉDULA Y LA FECHA DE NACIMIENTO NO SE PUBLICAN NUNCA.
--
--    No se resuelve con un interruptor, porque un interruptor se puede
--    encender por error. Se resuelve en las CONSULTAS del portal, que no
--    seleccionan esas columnas: aunque alguien quisiera mostrarlas, no
--    están en el resultado. La categoría —«Sub-14»— ya dice lo que el
--    público necesita saber sobre la edad.
--
-- 2. UN TORNEO ES PRIVADO HASTA QUE SE DECIDE PUBLICARLO.
--
--    torneo_publico nace en 'N'. Privacidad por diseño significa que el
--    estado seguro es el de partida, no el que hay que acordarse de
--    activar. Publicar es un acto deliberado y auditable.
--
-- 3. LA FOTOGRAFÍA EXIGE CONSENTIMIENTO EXPLÍCITO.
--
--    persona_publicarfoto también nace en 'N'. La foto de un menor es el
--    dato más sensible de los que hay aquí, y su tratamiento en un sitio
--    abierto necesita el consentimiento de quien ejerce la representación
--    legal. Sin ese consentimiento registrado, el portal muestra las
--    iniciales.
--
-- Lo que sí se publica: nombre y apellidos, dorsal, equipo y estadísticas
-- deportivas. Es lo que hace falta para que una liga se pueda seguir, y
-- es lo que cualquier acta impresa ya contiene.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Publicación del torneo. Opt-in.
-- ---------------------------------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_torneo'
                AND COLUMN_NAME='torneo_publico');

SET @sql := IF(@hay > 0, 'SELECT ''torneo_publico ya existe'' AS aviso',
    'ALTER TABLE dsl_torneo
       ADD COLUMN torneo_publico CHAR(1) NOT NULL DEFAULT ''N'' AFTER torneo_estado,
       ADD COLUMN torneo_slug VARCHAR(80) NULL AFTER torneo_publico');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ---------------------------------------------------------------------
-- El slug: la parte legible de la URL compartible.
--
-- Se guarda en vez de calcularse al vuelo para que el enlace que alguien
-- compartió en un grupo de mensajería siga funcionando aunque el torneo
-- se renombre. Un enlace roto es la forma más rápida de que la gente deje
-- de compartir.
-- ---------------------------------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_torneo'
                AND INDEX_NAME='uk_dslto_slug');

SET @sql := IF(@hay > 0, 'SELECT ''uk_dslto_slug ya existe'' AS aviso',
    'ALTER TABLE dsl_torneo ADD UNIQUE KEY uk_dslto_slug (torneo_slug)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ---------------------------------------------------------------------
-- Consentimiento de imagen. Opt-in.
--
-- Se registra también QUIÉN y CUÁNDO lo autorizó: un consentimiento sin
-- trazabilidad no sirve para demostrar que se obtuvo.
-- ---------------------------------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_persona'
                AND COLUMN_NAME='persona_publicarfoto');

SET @sql := IF(@hay > 0, 'SELECT ''persona_publicarfoto ya existe'' AS aviso',
    'ALTER TABLE dsl_persona
       ADD COLUMN persona_publicarfoto CHAR(1) NOT NULL DEFAULT ''N'' AFTER persona_foto,
       ADD COLUMN persona_consentfecha DATETIME NULL AFTER persona_publicarfoto,
       ADD COLUMN persona_consentusuario INT NULL AFTER persona_consentfecha');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ---------------------------------------------------------------------
-- Slug para los torneos que ya existen.
--
-- Se construye del nombre, en minúsculas y sin acentos, y se le añade el
-- id para garantizar unicidad sin tener que resolver colisiones a mano.
-- ---------------------------------------------------------------------
UPDATE dsl_torneo
   SET torneo_slug = CONCAT(
        LOWER(REGEXP_REPLACE(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                REPLACE(REPLACE(torneo_nombre, 'á','a'), 'é','e'),
                'í','i'), 'ó','o'), 'ú','u'), 'ñ','n'),
                'Á','A'), 'É','E'), 'Í','I'), 'Ó','O'),
            '[^a-zA-Z0-9]+', '-')),
        '-', torneo_id)
 WHERE torneo_slug IS NULL;


-- ---------------------------------------------------------------------
-- Comprobación: nada debe quedar publicado por accidente tras migrar.
-- ---------------------------------------------------------------------
SELECT 'torneos públicos'   AS concepto, COUNT(*) AS n FROM dsl_torneo  WHERE torneo_publico = 'S'
UNION ALL
SELECT 'fotos publicables',  COUNT(*) FROM dsl_persona WHERE persona_publicarfoto = 'S';
