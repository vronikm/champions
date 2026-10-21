-- =====================================================================
-- 026 · League · Reglas de clasificación por categoría
-- =====================================================================
-- La tabla de posiciones se calcula, no se guarda (decisión D6). Pero el
-- cálculo necesita saber qué vale cada resultado, y eso cambia según la
-- competencia:
--
--   · FIBA reparte 2 puntos por victoria y 1 por derrota jugada, de modo
--     que no presentarse cuesta más que perder;
--   · muchas ligas locales usan 2 / 0;
--   · algunas usan 3 / 1 / 0.
--
-- Con los valores incrustados en el código, atender a la segunda liga
-- obliga a tocar el cálculo. Aquí son configuración de la categoría, que
-- es el nivel donde realmente se decide.
--
-- EL ORDEN DE DESEMPATE TAMBIÉN ES CONFIGURACIÓN
--
-- categoria_desempate guarda la secuencia de criterios como una lista de
-- códigos separados por coma. El valor por omisión es el habitual en
-- baloncesto:
--
--   DIRECTO  · enfrentamiento directo entre los empatados
--   DIFDIR   · diferencia de puntos en esos enfrentamientos
--   DIF      · diferencia general de puntos
--   PF       · puntos a favor
--
-- Se guarda como texto y no como tabla aparte porque es una secuencia
-- corta que se lee entera o no se lee: normalizarla obligaría a un JOIN
-- ordenado para leer cuatro palabras.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE dsl_categoria
    ADD COLUMN categoria_ptsvictoria  TINYINT NOT NULL DEFAULT 2
        AFTER categoria_minhabilitados,

    ADD COLUMN categoria_ptsderrota   TINYINT NOT NULL DEFAULT 1
        AFTER categoria_ptsvictoria,

    -- No presentarse debe costar más que perder jugando; si valiera lo
    -- mismo, retirarse de un partido perdido no tendría penalización
    -- deportiva.
    ADD COLUMN categoria_ptswalkover  TINYINT NOT NULL DEFAULT 0
        AFTER categoria_ptsderrota,

    ADD COLUMN categoria_desempate    VARCHAR(60) NOT NULL DEFAULT 'DIRECTO,DIFDIR,DIF,PF'
        AFTER categoria_ptswalkover;
