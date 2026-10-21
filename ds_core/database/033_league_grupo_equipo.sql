-- =====================================================================
-- 033 · League · Pertenencia de un equipo a un grupo
-- =====================================================================
-- La espina creó dsl_grupo —el grupo en sí— pero no dónde se dice QUÉ
-- equipos lo componen. Esa información sólo existía dentro del resultado
-- del sorteo, y eso deja dos huecos:
--
--   1. Sin sorteo no hay grupos. Una liga pequeña que reparte a mano se
--      queda sin forma de expresarlo.
--
--   2. La clasificación por grupo salía mal. tablaPosiciones() tomaba
--      TODOS los equipos de la categoría y sólo los resultados de ese
--      grupo, así que los equipos de los otros grupos aparecían en la
--      tabla con todo a cero. Se ve al mirar una tabla de grupo: sobran
--      filas.
--
-- Ese segundo punto importa más de lo que parece ahora, porque los
-- playoffs siembran el cuadro leyendo la clasificación de cada grupo: un
-- primero mal calculado se arrastra hasta la final.
--
-- SE RELLENA CON LOS SORTEOS YA CELEBRADOS
--
-- Los grupos existentes se reconstruyen desde dsl_sorteo_resultado, que
-- es donde estaba la información hasta ahora. A partir de aquí, el sorteo
-- escribe en ambos sitios: el resultado queda como acta histórica y esta
-- tabla como estado actual.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dsl_grupo_equipo (
    ge_id            INT AUTO_INCREMENT PRIMARY KEY,
    ge_grupoid       INT      NOT NULL,
    ge_inscripcionid INT      NOT NULL,

    -- Posición con la que entró al grupo (la del sorteo). No es la
    -- clasificación: esa se calcula con los resultados.
    ge_orden         SMALLINT NOT NULL DEFAULT 0,

    -- Un equipo pertenece a un solo grupo dentro de una fase. La
    -- restricción se pone sobre el grupo porque cada grupo pertenece ya a
    -- una fase; que un equipo esté en dos grupos de fases distintas es
    -- correcto y frecuente (grupos, luego cuartos).
    UNIQUE KEY uk_dslge (ge_grupoid, ge_inscripcionid),
    KEY ix_dslge_ins (ge_inscripcionid),

    CONSTRAINT fk_dslge_grupo FOREIGN KEY (ge_grupoid)
        REFERENCES dsl_grupo (grupo_id) ON DELETE CASCADE,
    CONSTRAINT fk_dslge_inscripcion FOREIGN KEY (ge_inscripcionid)
        REFERENCES dsl_inscripcion (inscripcion_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Reconstrucción desde los sorteos ya aplicados.
-- ---------------------------------------------------------------------
INSERT INTO dsl_grupo_equipo (ge_grupoid, ge_inscripcionid, ge_orden)
SELECT R.resultado_grupoid, R.resultado_inscripcionid, R.resultado_posicion
  FROM dsl_sorteo_resultado R
  JOIN dsl_sorteo S ON S.sorteo_id = R.resultado_sorteoid
 WHERE R.resultado_grupoid IS NOT NULL
   AND S.sorteo_estado = 'APLICADO'
   AND NOT EXISTS (SELECT 1 FROM dsl_grupo_equipo G
                    WHERE G.ge_grupoid       = R.resultado_grupoid
                      AND G.ge_inscripcionid = R.resultado_inscripcionid);


-- ---------------------------------------------------------------------
-- Estado de la serie
--
-- dsl_serie ya guardaba el ganador, pero no si la eliminatoria sigue
-- abierta. Hace falta para saber cuándo se pueden cancelar los partidos
-- que ya no se van a jugar: en un «al mejor de 5» que va 3-0, los dos
-- encuentros restantes no se disputan, y dejarlos programados deja
-- canchas bloqueadas y árbitros designados para nada.
-- ---------------------------------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_serie'
                AND COLUMN_NAME='serie_estado');

SET @sql := IF(@hay > 0, 'SELECT ''serie_estado ya existe'' AS aviso',
    'ALTER TABLE dsl_serie
       ADD COLUMN serie_estado VARCHAR(20) NOT NULL DEFAULT ''ABIERTA'' AFTER serie_mejorde,
       ADD COLUMN serie_ganadas_local TINYINT NOT NULL DEFAULT 0 AFTER serie_estado,
       ADD COLUMN serie_ganadas_visitante TINYINT NOT NULL DEFAULT 0 AFTER serie_ganadas_local');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
