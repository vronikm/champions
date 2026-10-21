-- =====================================================================
-- 032 · League · Sorteos
-- =====================================================================
-- El encargo pide saber, de cada sorteo, cuándo se hizo, quién lo hizo,
-- con qué configuración y qué resultado dio.
--
-- SE GUARDA ADEMÁS LA SEMILLA, Y ESO CAMBIA LA NATURALEZA DEL REGISTRO
--
-- Un acta que dice «el equipo A quedó en el grupo 1» sólo se puede creer.
-- Guardando la semilla del generador, cualquiera con la misma semilla, la
-- misma configuración y la misma lista de equipos obtiene EXACTAMENTE el
-- mismo resultado. El sorteo deja de ser un hecho que hay que aceptar y
-- pasa a ser uno que se puede comprobar.
--
-- Eso importa el día que un club impugna: la respuesta no es «confíe en
-- el sistema», es «aquí está la semilla, reprodúzcalo usted».
--
-- Por eso el resultado también se almacena aunque sea derivable: si el
-- algoritmo cambiara en una versión futura, el resultado guardado sigue
-- siendo el que se aplicó, y la semilla permite detectar la discrepancia
-- en lugar de ocultarla.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- El sorteo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_sorteo (
    sorteo_id          INT AUTO_INCREMENT PRIMARY KEY,
    sorteo_faseid      INT          NOT NULL,

    -- Semilla del generador. BIGINT porque mt_srand admite el rango
    -- entero completo y recortarlo reduciría el espacio de sorteos
    -- posibles sin ninguna ganancia.
    sorteo_semilla     BIGINT       NOT NULL,

    -- Parámetros con los que se ejecutó: número de grupos, modo,
    -- cabezas de serie. En JSON porque es una fotografía de la
    -- configuración, no datos que se consulten por separado.
    sorteo_config      JSON         NOT NULL,

    -- 'BORRADOR' se puede repetir cuantas veces haga falta; 'APLICADO'
    -- ya asignó los grupos y no se rehace sin dejar constancia.
    sorteo_estado      VARCHAR(20)  NOT NULL DEFAULT 'BORRADOR',

    sorteo_observacion VARCHAR(300) NOT NULL DEFAULT '',
    sorteo_usuarioid   INT          NULL,
    sorteo_usuario     VARCHAR(20)  NOT NULL DEFAULT '',
    sorteo_fecha       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_dsls_fase (sorteo_faseid, sorteo_fecha),

    CONSTRAINT fk_dsls_fase FOREIGN KEY (sorteo_faseid)
        REFERENCES dsl_fase (fase_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Bombos
--
-- Qué equipo entró en qué bombo. Es parte de la configuración del
-- sorteo, no del resultado: cambiar los bombos cambia el sorteo aunque
-- la semilla sea la misma, así que hay que guardarlos para poder
-- reproducirlo.
--
-- El bombo 1 es el de las cabezas de serie por convención.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_sorteo_bombo (
    bombo_id           INT AUTO_INCREMENT PRIMARY KEY,
    bombo_sorteoid     INT      NOT NULL,
    bombo_inscripcionid INT     NOT NULL,
    bombo_numero       TINYINT  NOT NULL DEFAULT 1,

    -- Orden dentro del bombo. Fija la lista de entrada del algoritmo:
    -- sin un orden estable, la misma semilla podría dar resultados
    -- distintos según cómo ordenara la base de datos.
    bombo_orden        SMALLINT NOT NULL DEFAULT 0,

    UNIQUE KEY uk_dslsb (bombo_sorteoid, bombo_inscripcionid),
    KEY ix_dslsb_bombo (bombo_sorteoid, bombo_numero, bombo_orden),

    CONSTRAINT fk_dslsb_sorteo FOREIGN KEY (bombo_sorteoid)
        REFERENCES dsl_sorteo (sorteo_id) ON DELETE CASCADE,
    CONSTRAINT fk_dslsb_inscripcion FOREIGN KEY (bombo_inscripcionid)
        REFERENCES dsl_inscripcion (inscripcion_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Restricciones
--
-- Parejas que no pueden caer en el mismo grupo. El caso real: dos
-- equipos del mismo club, o dos que comparten cancha y no pueden jugar
-- la misma jornada en casa.
--
-- Se guarda siempre con el id menor primero, para que la pareja (A,B) y
-- la (B,A) sean la misma fila y la clave única funcione.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_sorteo_restriccion (
    restriccion_id      INT AUTO_INCREMENT PRIMARY KEY,
    restriccion_sorteoid INT     NOT NULL,
    restriccion_menorid INT      NOT NULL,
    restriccion_mayorid INT      NOT NULL,
    restriccion_motivo  VARCHAR(150) NOT NULL DEFAULT '',

    UNIQUE KEY uk_dslsr (restriccion_sorteoid, restriccion_menorid, restriccion_mayorid),

    CONSTRAINT fk_dslsr_sorteo FOREIGN KEY (restriccion_sorteoid)
        REFERENCES dsl_sorteo (sorteo_id) ON DELETE CASCADE,

    -- Una restricción de un equipo consigo mismo no significa nada.
    CONSTRAINT ck_dslsr_distintos CHECK (restriccion_menorid < restriccion_mayorid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Resultado
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_sorteo_resultado (
    resultado_id       INT AUTO_INCREMENT PRIMARY KEY,
    resultado_sorteoid INT      NOT NULL,
    resultado_inscripcionid INT NOT NULL,
    resultado_grupoid  INT      NULL,

    -- Nombre del grupo tal como salió, guardado además del id: si el
    -- grupo se renombra después, el acta del sorteo debe seguir diciendo
    -- lo que dijo aquel día.
    resultado_grupo    VARCHAR(30) NOT NULL DEFAULT '',
    resultado_posicion SMALLINT NOT NULL DEFAULT 0,
    resultado_bombo    TINYINT  NOT NULL DEFAULT 1,

    UNIQUE KEY uk_dslsres (resultado_sorteoid, resultado_inscripcionid),
    KEY ix_dslsres_grupo (resultado_sorteoid, resultado_grupoid, resultado_posicion),

    CONSTRAINT fk_dslsres_sorteo FOREIGN KEY (resultado_sorteoid)
        REFERENCES dsl_sorteo (sorteo_id) ON DELETE CASCADE,
    CONSTRAINT fk_dslsres_inscripcion FOREIGN KEY (resultado_inscripcionid)
        REFERENCES dsl_inscripcion (inscripcion_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
