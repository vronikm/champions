-- =====================================================================
-- 062 · El color de cada mes pasa a ser POR AÑO
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- El color del carnet identifica el mes: se mira un carnet y se sabe de
-- que mes es. Para que eso funcione, un color que ya circula no se puede
-- cambiar, y el sistema lo impedia.
--
-- Pero lo impedia PARA SIEMPRE. El bloqueo se calculaba asi, en los cuatro
-- sitios donde vive:
--
--     SELECT COUNT(*) FROM alumno_carnet WHERE carnet_mes = <mes>
--
-- Sin anio. De modo que en cuanto abril de 2026 tuvo su primer carnet,
-- abril quedo bloqueado tambien para 2027, 2028 y los que vengan. Una
-- escuela no puede estrenar paleta de colores nunca mas.
--
--
-- POR QUE NO BASTABA CON FILTRAR POR ANIO
--
-- carnet_mes_color no tenia columna de anio: la asignacion mes->color era
-- UNA para toda la historia. Y al imprimir se resolvia asi:
--
--     BuscarColorPorMes($mes)      -- sin anio: la asignacion VIGENTE
--
-- Eso funcionaba por accidente, porque los colores no cambiaban nunca. Con
-- el bloqueo liberado por anio, y sin esta migracion, pasaria esto:
--
--   1. en 2027 se cambia abril de Verde Menta a otro tono;
--   2. alguien REIMPRIME un carnet de abril de 2026;
--   3. sale con el color de 2027.
--
-- Y ademas se perderia el registro de que abril de 2026 fue Verde Menta:
-- los 529 carnets ya emitidos quedarian sin forma de saber su color.
--
-- Por eso la asignacion pasa a ser por anio. Con eso el bloqueo por anio
-- es correcto Y la historia queda guardada.
--
--
-- LAS DOS CLAVES UNICAS CAMBIAN DE SIGNIFICADO
--
--   mcolor_mes            UNIQUE  ->  (mcolor_mes, mcolor_anio)
--       «un mes tiene un color» pasa a «un mes tiene un color CADA ANIO».
--
--   unique_color_asignado UNIQUE  ->  (mcolor_catcolorid, mcolor_anio)
--       «un color esta en un solo mes» pasa a «en un solo mes DE CADA
--       ANIO». Repetir un color de un anio a otro queda permitido, que es
--       justo lo que se quiere: la paleta se reutiliza, no se agota.
--
-- Comprobado sobre un clon antes de escribir esto: insertar el mismo color
-- en otro anio se acepta, y dos veces en el mismo anio da error 1062.
--
--
-- DE QUE ANIO SON LAS DOCE FILAS QUE YA EXISTEN
--
-- De 2026: es el unico anio con carnets emitidos (529, de abril a agosto) y
-- es tambien el anio en curso. Se rellena con ese valor y no con
-- YEAR(CURDATE()) para que la migracion diga lo mismo el dia que se aplique
-- en produccion, sea cual sea la fecha.
-- =====================================================================

-- Comprobacion previa: de que anios hay carnets. Deberia decir 2026.
SELECT MIN(carnet_anio) AS primer_anio,
       MAX(carnet_anio) AS ultimo_anio,
       COUNT(*)         AS carnets
  FROM alumno_carnet;

ALTER TABLE carnet_mes_color
  ADD COLUMN mcolor_anio SMALLINT NOT NULL DEFAULT 0
  COMMENT 'Anio al que corresponde esta asignacion de color'
  AFTER mcolor_mes;

-- mcolor_fechamod se asigna a sí misma a propósito: es ON UPDATE
-- CURRENT_TIMESTAMP, y sin esto los doce meses quedaban «modificados hoy»
-- aunque nadie hubiera tocado su color. Asignarla explícitamente es la forma
-- documentada de que MySQL no la actualice (visto al migrar producción).
UPDATE carnet_mes_color SET mcolor_anio = 2026, mcolor_fechamod = mcolor_fechamod WHERE mcolor_anio = 0;

ALTER TABLE carnet_mes_color
  DROP INDEX mcolor_mes,
  DROP INDEX unique_color_asignado;

ALTER TABLE carnet_mes_color
  ADD UNIQUE KEY uk_mcolor_mes_anio   (mcolor_mes, mcolor_anio),
  ADD UNIQUE KEY uk_mcolor_color_anio (mcolor_catcolorid, mcolor_anio);

-- Comprobacion posterior: doce filas, todas de 2026, y las dos claves
-- nuevas en su sitio.
SELECT mcolor_anio, COUNT(*) AS meses FROM carnet_mes_color GROUP BY mcolor_anio;

SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME   = 'carnet_mes_color'
   AND NON_UNIQUE   = 0
 GROUP BY INDEX_NAME;

-- ---------------------------------------------------------------------
-- Para revertir (requiere devolver antes el codigo a la version sin anio,
-- y BORRAR las asignaciones de anios distintos de 2026, o las claves
-- unicas viejas no se podran crear):
--
-- DELETE FROM carnet_mes_color WHERE mcolor_anio <> 2026;
-- ALTER TABLE carnet_mes_color
--   DROP INDEX uk_mcolor_mes_anio, DROP INDEX uk_mcolor_color_anio;
-- ALTER TABLE carnet_mes_color
--   ADD UNIQUE KEY mcolor_mes (mcolor_mes),
--   ADD UNIQUE KEY unique_color_asignado (mcolor_catcolorid);
-- ALTER TABLE carnet_mes_color DROP COLUMN mcolor_anio;
-- ---------------------------------------------------------------------
