-- =====================================================================
-- 030 · League · La designación es única por usuario y función
-- =====================================================================
-- La espina (024) declaró la clave única sobre
--
--     (designacion_partidoid, designacion_personaid, designacion_funcion)
--
-- y designacion_personaid admite NULL. Con dos NULL, MySQL no considera
-- que las filas colisionen, de modo que un mismo árbitro podía quedar
-- designado varias veces al mismo partido con la misma función. El
-- síntoma: su agenda mostraba el encuentro repetido.
--
-- PERO EL PROBLEMA DE FONDO ES DE MODELADO, NO DE NULL
--
-- Esta tabla existe para el control de acceso: es lo que responde «¿puede
-- este usuario cargar el acta de este partido?» (decisión D4). Su clave
-- natural es por tanto el USUARIO, no la persona.
--
-- Registrar a un oficial que no tiene cuenta en el sistema es otra
-- necesidad —un padrón de árbitros— y merece su propia tabla el día que
-- haga falta. Mezclar las dos cosas aquí es lo que dejó la unicidad
-- colgando de una columna que puede ir vacía.
--
-- designacion_personaid se conserva como referencia opcional: la usa la
-- comprobación de conflicto de interés, que mira si esa persona figura en
-- la plantilla de alguno de los dos equipos.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Antes de imponer la restricción hay que retirar los duplicados que ya
-- existieran, conservando el más antiguo de cada grupo.
-- ---------------------------------------------------------------------
DELETE D FROM dsl_designacion D
  JOIN (
        SELECT MIN(designacion_id) AS conservar,
               designacion_partidoid, designacion_usuarioid, designacion_funcion
          FROM dsl_designacion
         WHERE designacion_usuarioid IS NOT NULL
         GROUP BY designacion_partidoid, designacion_usuarioid, designacion_funcion
        HAVING COUNT(*) > 1
       ) G
    ON G.designacion_partidoid = D.designacion_partidoid
   AND G.designacion_usuarioid = D.designacion_usuarioid
   AND G.designacion_funcion   = D.designacion_funcion
 WHERE D.designacion_id <> G.conservar;


-- Sin usuario no hay control de acceso posible: esas filas no cumplen la
-- función de la tabla.
DELETE FROM dsl_designacion WHERE designacion_usuarioid IS NULL;


ALTER TABLE dsl_designacion
    MODIFY designacion_usuarioid INT NOT NULL;


-- ---------------------------------------------------------------------
-- EL ORDEN IMPORTA
--
-- La clave foránea a dsl_persona se apoya en uk_dsld_persona: intentar
-- retirarlo primero devuelve «Cannot drop index: needed in a foreign key
-- constraint». Hay que darle antes otro índice donde apoyarse.
--
-- Cada paso se comprueba antes de ejecutarse, para que la migración pueda
-- volver a correrse sobre una base donde ya se aplicó parcialmente.
-- ---------------------------------------------------------------------

-- 1. El índice sustituto para la clave foránea.
SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_designacion'
                AND INDEX_NAME='ix_dsld_persona');
SET @sql := IF(@hay > 0, 'SELECT ''ix_dsld_persona ya existe'' AS aviso',
    'ALTER TABLE dsl_designacion ADD KEY ix_dsld_persona (designacion_personaid)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. El único nuevo, ANTES de retirar el viejo.
--
--    De uk_dsld_persona no colgaba sólo la foránea a dsl_persona: la
--    foránea al partido también lo usaba, porque designacion_partidoid es
--    su primera columna, y un índice sólo sirve a una foránea si la
--    columna es su prefijo por la izquierda. Por eso MySQL seguía
--    respondiendo «needed in a foreign key constraint» después de soltar
--    la de persona.
--
--    El índice nuevo empieza igualmente por partido, así que al crearlo
--    primero la foránea del partido pasa a apoyarse en él y el viejo
--    queda libre.
SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_designacion'
                AND INDEX_NAME='uk_dsld_usuario');
SET @sql := IF(@hay > 0, 'SELECT ''uk_dsld_usuario ya existe'' AS aviso',
    'ALTER TABLE dsl_designacion ADD UNIQUE KEY uk_dsld_usuario
        (designacion_partidoid, designacion_usuarioid, designacion_funcion)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Ahora sí, retirar el antiguo.
SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_designacion'
                AND INDEX_NAME='uk_dsld_persona');
SET @sql := IF(@hay = 0, 'SELECT ''uk_dsld_persona ya no está'' AS aviso',
    'ALTER TABLE dsl_designacion DROP INDEX uk_dsld_persona');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Restablecer la foránea a persona, que se soltó para poder maniobrar.
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_designacion'
               AND CONSTRAINT_NAME='fk_dsld_persona' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk > 0, 'SELECT ''fk_dsld_persona ya está'' AS aviso',
    'ALTER TABLE dsl_designacion
       ADD CONSTRAINT fk_dsld_persona FOREIGN KEY (designacion_personaid)
           REFERENCES dsl_persona (persona_id)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
