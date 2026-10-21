-- =====================================================================
-- 034 · League · Estadísticas por catálogo
-- =====================================================================
-- El encargo pide evitar el acoplamiento rígido al baloncesto y, a la
-- vez, enumera once estadísticas de baloncesto. Con once columnas,
-- atender a otro deporte sería un cambio de esquema; con un catálogo, es
-- una fila.
--
-- LA TABLA DE DATOS ES ESTRECHA A PROPÓSITO
--
-- (partido, persona, tipo, valor). Un ranking —máximos anotadores, más
-- rebotes— es un SUM ... GROUP BY sobre el índice (tipo, persona), que
-- escala bien hasta millones de filas. Es la consulta que más se va a
-- ejecutar, porque alimenta el portal público.
--
-- EL COSTE, DICHO CLARAMENTE
--
-- Leer el acta completa de un partido deja de ser una fila y pasa a ser
-- un pivote. Es un coste real y conocido; se paga porque la alternativa
-- —once columnas— hace imposible el segundo deporte. Si algún día pesa,
-- se materializa una fila por partido y jugador, pero cuando esté medido.
--
-- HAY ESTADÍSTICAS QUE NO SE CAPTURAN: SE CALCULAN
--
-- Los puntos son tiros libres + dobles×2 + triples×3. Pedirlos aparte
-- invita a que el acta diga una cosa y los tiros otra, y entonces no hay
-- forma de saber cuál es la buena. tipo_formula guarda esa relación, de
-- modo que la regla vive en el catálogo y cambia por configuración.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Catálogo de tipos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_estadistica_tipo (
    tipo_id        INT AUTO_INCREMENT PRIMARY KEY,

    -- Deporte al que pertenece. Es lo que permite que League sirva a otra
    -- disciplina sin tocar el esquema.
    tipo_deporte   VARCHAR(20) NOT NULL DEFAULT 'baloncesto',

    tipo_codigo    VARCHAR(10) NOT NULL,
    tipo_nombre    VARCHAR(60) NOT NULL,
    tipo_abrev     VARCHAR(6)  NOT NULL,

    -- Fórmula en códigos del propio catálogo, o NULL si se captura.
    -- Ejemplo: 'T1A + T2A*2 + T3A*3'
    tipo_formula   VARCHAR(200) NULL,

    -- Si aparece en el acta que se teclea. Las derivadas no.
    tipo_captura   CHAR(1)  NOT NULL DEFAULT 'S',

    -- Si tiene sentido sumarla en un ranking. Los minutos sí; un
    -- porcentaje, no —habría que recalcularlo, no sumarlo—.
    tipo_agregable CHAR(1)  NOT NULL DEFAULT 'S',

    tipo_orden     SMALLINT NOT NULL DEFAULT 0,
    tipo_activo    CHAR(1)  NOT NULL DEFAULT 'S',

    UNIQUE KEY uk_dslet (tipo_deporte, tipo_codigo),
    KEY ix_dslet_orden (tipo_deporte, tipo_activo, tipo_orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Los datos
--
-- Se guarda la inscripción además de la persona: un jugador puede
-- cambiar de equipo dentro de la misma temporada, y sin este campo sus
-- estadísticas se atribuirían al equipo actual en vez de a aquel con el
-- que las hizo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_partido_stat (
    stat_id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    stat_partidoid   INT NOT NULL,
    stat_personaid   INT NOT NULL,
    stat_inscripcionid INT NOT NULL,
    stat_tipoid      INT NOT NULL,

    -- DECIMAL y no INT: los minutos jugados admiten fracción y un
    -- entero obligaría a redondear cada cambio.
    stat_valor       DECIMAL(8,2) NOT NULL DEFAULT 0,

    UNIQUE KEY uk_dslps (stat_partidoid, stat_personaid, stat_tipoid),

    -- El índice del ranking: sumar un tipo agrupando por persona. Es la
    -- consulta que alimenta el portal público, así que es la que manda en
    -- el diseño de índices.
    KEY ix_dslps_ranking (stat_tipoid, stat_personaid, stat_valor),

    -- Y el del acta: todo lo de un partido de una vez.
    KEY ix_dslps_acta (stat_partidoid, stat_personaid),
    KEY ix_dslps_equipo (stat_inscripcionid, stat_tipoid),

    CONSTRAINT fk_dslps_partido FOREIGN KEY (stat_partidoid)
        REFERENCES dsl_partido (partido_id) ON DELETE CASCADE,
    CONSTRAINT fk_dslps_persona FOREIGN KEY (stat_personaid)
        REFERENCES dsl_persona (persona_id),
    CONSTRAINT fk_dslps_tipo FOREIGN KEY (stat_tipoid)
        REFERENCES dsl_estadistica_tipo (tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =====================================================================
-- Catálogo de baloncesto
--
-- Los tiros van en pares anotados/intentados porque es como se lleva un
-- acta: sin los intentos no hay porcentaje de acierto, y el porcentaje
-- es lo que de verdad se mira.
-- =====================================================================
INSERT INTO dsl_estadistica_tipo
       (tipo_deporte, tipo_codigo, tipo_nombre, tipo_abrev, tipo_formula,
        tipo_captura, tipo_agregable, tipo_orden)
VALUES
    ('baloncesto', 'MIN', 'Minutos jugados',        'MIN', NULL, 'S', 'S', 10),

    ('baloncesto', 'T1A', 'Tiros libres anotados',  'TLA', NULL, 'S', 'S', 20),
    ('baloncesto', 'T1I', 'Tiros libres intentados','TLI', NULL, 'S', 'S', 21),
    ('baloncesto', 'T2A', 'Tiros de dos anotados',  'T2A', NULL, 'S', 'S', 30),
    ('baloncesto', 'T2I', 'Tiros de dos intentados','T2I', NULL, 'S', 'S', 31),
    ('baloncesto', 'T3A', 'Triples anotados',       'T3A', NULL, 'S', 'S', 40),
    ('baloncesto', 'T3I', 'Triples intentados',     'T3I', NULL, 'S', 'S', 41),

    -- Los puntos NO se teclean: se calculan de los tiros. Pedirlos aparte
    -- permite que el acta se contradiga a sí misma.
    ('baloncesto', 'PTS', 'Puntos', 'PTS', 'T1A + T2A*2 + T3A*3', 'N', 'S', 50),

    ('baloncesto', 'REO', 'Rebotes ofensivos',      'REO', NULL, 'S', 'S', 60),
    ('baloncesto', 'RED', 'Rebotes defensivos',     'RED', NULL, 'S', 'S', 61),
    ('baloncesto', 'REB', 'Rebotes totales',        'REB', 'REO + RED', 'N', 'S', 62),

    ('baloncesto', 'AST', 'Asistencias',            'AST', NULL, 'S', 'S', 70),
    ('baloncesto', 'ROB', 'Robos',                  'ROB', NULL, 'S', 'S', 80),
    ('baloncesto', 'TAP', 'Bloqueos',               'TAP', NULL, 'S', 'S', 90),
    ('baloncesto', 'PER', 'Pérdidas',               'PER', NULL, 'S', 'S', 100),
    ('baloncesto', 'FAL', 'Faltas',                 'FAL', NULL, 'S', 'S', 110),

    -- Valoración FIBA: lo que aporta menos lo que falla. Es la fórmula
    -- estándar y por eso viene configurada, no incrustada en el código.
    ('baloncesto', 'VAL', 'Valoración', 'VAL',
     'PTS + REB + AST + ROB + TAP - (T1I - T1A) - (T2I - T2A) - (T3I - T3A) - PER - FAL',
     'N', 'S', 120)

ON DUPLICATE KEY UPDATE
    tipo_nombre  = VALUES(tipo_nombre),
    tipo_abrev   = VALUES(tipo_abrev),
    tipo_formula = VALUES(tipo_formula),
    tipo_captura = VALUES(tipo_captura),
    tipo_orden   = VALUES(tipo_orden);
