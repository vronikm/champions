-- =====================================================================
-- 024 · League · Espina de competición
-- =====================================================================
-- La jerarquía sobre la que se apoya todo lo demás:
--
--     Temporada → Torneo → Categoría → Fase → (Grupo | Serie) → Partido
--
-- Es lo más caro de cambiar más adelante, porque cada tabla posterior
-- cuelga de ella. Las cuatro decisiones que la sostienen van explicadas
-- en su sitio.
--
-- Este módulo NO crea tabla de escenarios. Los partidos apuntan a
-- dsa_instalacion, la de Arena, para que no existan dos calendarios sobre
-- la misma cancha.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Temporada
--
-- Contenedor temporal de la organización. Un torneo pertenece a una
-- temporada y no al revés, de modo que «la liga 2026» agrupa cuanto se
-- jugó ese año sin que haya que repetir el dato en cada torneo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_temporada (
    temporada_id        INT AUTO_INCREMENT PRIMARY KEY,
    temporada_escuelaid INT          NULL,
    temporada_nombre    VARCHAR(80)  NOT NULL,
    temporada_desde     DATE         NOT NULL,
    temporada_hasta     DATE         NOT NULL,
    temporada_estado    CHAR(1)      NOT NULL DEFAULT 'A',
    temporada_usuarioid INT          NULL,
    temporada_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_dslt_nombre (temporada_escuelaid, temporada_nombre),
    KEY ix_dslt_fechas (temporada_desde, temporada_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Torneo
--
-- «Campeonato» es sinónimo en el habla corriente, no otra entidad: dos
-- tablas para lo mismo obligarían a duplicar fases, equipos y partidos.
--
-- torneo_deporte existe desde el principio aunque hoy sólo haya
-- baloncesto: es lo que permite que las reglas específicas se resuelvan
-- por configuración en lugar de por columnas nuevas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_torneo (
    torneo_id          INT AUTO_INCREMENT PRIMARY KEY,
    torneo_temporadaid INT          NOT NULL,
    torneo_nombre      VARCHAR(120) NOT NULL,
    torneo_deporte     VARCHAR(20)  NOT NULL DEFAULT 'baloncesto',
    torneo_sedeid      INT          NULL,
    torneo_desde       DATE         NULL,
    torneo_hasta       DATE         NULL,
    torneo_estado      CHAR(1)      NOT NULL DEFAULT 'A',
    torneo_usuarioid   INT          NULL,
    torneo_fecharegistro TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_dslto_nombre (torneo_temporadaid, torneo_nombre),
    KEY ix_dslto_estado (torneo_estado),

    CONSTRAINT fk_dslto_temporada FOREIGN KEY (torneo_temporadaid)
        REFERENCES dsl_temporada (temporada_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Categoría
--
-- La división competitiva real: un equipo se inscribe a una CATEGORÍA,
-- no a un torneo. «Sub-14 Masculino» y «Sub-16 Femenino» son categorías
-- del mismo torneo, con equipos, fases y clasificación separadas.
--
-- LA FECHA DE CORTE NO ES OPCIONAL
--
-- «Sub-14» no significa nada sin la fecha a la que se mide la edad. Sin
-- categoria_fechacorte, la elegibilidad de un jugador cambiaría según el
-- día en que se consultara: hoy tiene 13 y en marzo 14, y el sistema le
-- daría dos respuestas distintas a la misma pregunta. Con la fecha de
-- corte, la respuesta es siempre la misma y además es la que dice el
-- reglamento.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_categoria (
    categoria_id        INT AUTO_INCREMENT PRIMARY KEY,
    categoria_torneoid  INT          NOT NULL,
    categoria_nombre    VARCHAR(80)  NOT NULL,

    -- 'M' masculino · 'F' femenino · 'X' mixto
    categoria_genero    CHAR(1)      NOT NULL DEFAULT 'X',

    categoria_edadmin   TINYINT      NULL,
    categoria_edadmax   TINYINT      NULL,
    categoria_fechacorte DATE        NULL,

    -- Cuántos jugadores puede tener una plantilla y cuántos deben estar
    -- habilitados para que el equipo pueda presentarse.
    categoria_maxplantilla TINYINT   NOT NULL DEFAULT 15,
    categoria_minhabilitados TINYINT NOT NULL DEFAULT 5,

    categoria_estado    CHAR(1)      NOT NULL DEFAULT 'A',
    categoria_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_dslc_nombre (categoria_torneoid, categoria_nombre),
    KEY ix_dslc_estado (categoria_estado),

    CONSTRAINT fk_dslc_torneo FOREIGN KEY (categoria_torneoid)
        REFERENCES dsl_torneo (torneo_id),

    -- Un rango de edad invertido es un error de captura que después se
    -- manifiesta como «ningún jugador es elegible», que cuesta mucho más
    -- diagnosticar que un mensaje al guardar.
    CONSTRAINT ck_dslc_edades CHECK (categoria_edadmin IS NULL
                                  OR categoria_edadmax IS NULL
                                  OR categoria_edadmin <= categoria_edadmax)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Fase
--
-- Cada categoría se juega en fases ordenadas: grupos, cuartos, semis,
-- final. El FORMATO vive aquí y no en el torneo, porque cambia de una
-- fase a otra: la primera es todos contra todos y la última eliminación
-- directa.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_fase (
    fase_id          INT AUTO_INCREMENT PRIMARY KEY,
    fase_categoriaid INT          NOT NULL,
    fase_orden       SMALLINT     NOT NULL DEFAULT 1,
    fase_nombre      VARCHAR(60)  NOT NULL,

    -- 'G' todos contra todos en grupos · 'E' eliminación directa
    -- 'S' serie al mejor de N
    fase_tipo        CHAR(1)      NOT NULL DEFAULT 'G',

    fase_idavuelta   CHAR(1)      NOT NULL DEFAULT 'N',

    -- Cuántos equipos pasan de cada grupo a la fase siguiente.
    fase_clasifican  TINYINT      NOT NULL DEFAULT 0,

    fase_estado      CHAR(1)      NOT NULL DEFAULT 'A',

    UNIQUE KEY uk_dslf_orden (fase_categoriaid, fase_orden),

    CONSTRAINT fk_dslf_categoria FOREIGN KEY (fase_categoriaid)
        REFERENCES dsl_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Grupo
--
-- Sólo existe dentro de una fase de tipo grupos. La clasificación se
-- calcula por grupo, no por fase.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_grupo (
    grupo_id     INT AUTO_INCREMENT PRIMARY KEY,
    grupo_faseid INT         NOT NULL,
    grupo_nombre VARCHAR(30) NOT NULL,
    grupo_orden  SMALLINT    NOT NULL DEFAULT 1,

    UNIQUE KEY uk_dslg_nombre (grupo_faseid, grupo_nombre),

    CONSTRAINT fk_dslg_fase FOREIGN KEY (grupo_faseid)
        REFERENCES dsl_fase (fase_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Jornada
--
-- Agrupa los partidos que se juegan en una misma fecha o rango. Sirve
-- para publicar el calendario y para reprogramar en bloque.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_jornada (
    jornada_id     INT AUTO_INCREMENT PRIMARY KEY,
    jornada_faseid INT         NOT NULL,
    jornada_numero SMALLINT    NOT NULL,
    jornada_nombre VARCHAR(60) NOT NULL DEFAULT '',
    jornada_desde  DATE        NULL,
    jornada_hasta  DATE        NULL,

    UNIQUE KEY uk_dslj_numero (jornada_faseid, jornada_numero),

    CONSTRAINT fk_dslj_fase FOREIGN KEY (jornada_faseid)
        REFERENCES dsl_fase (fase_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Equipo
--
-- Persiste entre temporadas. Es lo que permite el histórico: el mismo
-- club en 2025 y 2026, en dos categorías a la vez, sin duplicar la
-- entidad ni perder de vista que es el mismo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_equipo (
    equipo_id        INT AUTO_INCREMENT PRIMARY KEY,
    equipo_escuelaid INT          NULL,
    equipo_nombre    VARCHAR(120) NOT NULL,
    equipo_corto     VARCHAR(20)  NOT NULL DEFAULT '',
    equipo_escudo    VARCHAR(300) NULL,
    equipo_sedeid    INT          NULL,

    -- Contacto responsable del equipo ante la organización.
    equipo_contacto  VARCHAR(150) NOT NULL DEFAULT '',
    equipo_telefono  VARCHAR(30)  NOT NULL DEFAULT '',
    equipo_email     VARCHAR(150) NOT NULL DEFAULT '',

    equipo_estado    CHAR(1)      NOT NULL DEFAULT 'A',
    equipo_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_dsle_nombre (equipo_escuelaid, equipo_nombre),
    KEY ix_dsle_estado (equipo_estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Inscripción de un equipo a una categoría
--
-- La matrícula. Separarla del equipo es lo que hace posible que el mismo
-- club compita en varias categorías y temporadas con historias distintas:
-- aquí puede estar habilitado y allí pendiente de documentos.
--
-- El estado NO es un char suelto: apunta al catálogo de dsl_estado, de
-- modo que las transiciones legales están en un solo sitio.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_inscripcion (
    inscripcion_id          INT AUTO_INCREMENT PRIMARY KEY,
    inscripcion_equipoid    INT          NOT NULL,
    inscripcion_categoriaid INT          NOT NULL,
    inscripcion_estadoid    INT          NOT NULL,

    inscripcion_fecha       DATE         NOT NULL,
    inscripcion_valor       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    inscripcion_descuento   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    inscripcion_recargo     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    inscripcion_observacion VARCHAR(300) NOT NULL DEFAULT '',

    inscripcion_usuarioid   INT          NULL,
    inscripcion_fecharegistro TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Un equipo se inscribe una vez a cada categoría.
    UNIQUE KEY uk_dsli_equipo (inscripcion_equipoid, inscripcion_categoriaid),
    KEY ix_dsli_categoria (inscripcion_categoriaid, inscripcion_estadoid),

    CONSTRAINT fk_dsli_equipo FOREIGN KEY (inscripcion_equipoid)
        REFERENCES dsl_equipo (equipo_id),
    CONSTRAINT fk_dsli_categoria FOREIGN KEY (inscripcion_categoriaid)
        REFERENCES dsl_categoria (categoria_id),
    CONSTRAINT fk_dsli_estado FOREIGN KEY (inscripcion_estadoid)
        REFERENCES dsl_estado (estado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Persona
--
-- Jugadores, entrenadores, delegados, árbitros y oficiales de mesa: una
-- sola tabla, porque la misma persona puede ser dos cosas —un entrenador
-- que además arbitra en otra categoría— y duplicarla haría imposible
-- detectar el conflicto.
--
-- PUENTE CON BASKETBALL
--
-- persona_alumnoid enlaza, si existe, con el alumno de la escuela. Un
-- chico que entrena en la academia y juega la liga es UNA persona, no
-- dos fichas con la misma cédula. Es nullable porque la mayoría de los
-- jugadores de una liga externa no son alumnos.
--
-- PROTECCIÓN DE DATOS
--
-- Se guarda lo mínimo para competir: identificación, nombre, fecha de
-- nacimiento —que es lo que decide la elegibilidad por edad— y una foto
-- para el carné. Los datos de contacto viven en el equipo, no aquí,
-- porque la organización se comunica con el delegado y no con cada
-- jugador. Buena parte de estas personas son menores.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_persona (
    persona_id            INT AUTO_INCREMENT PRIMARY KEY,
    persona_alumnoid      INT          NULL,

    persona_tipoid        VARCHAR(3)   NOT NULL DEFAULT 'CED',
    persona_identificacion VARCHAR(20) NOT NULL,
    persona_nombres       VARCHAR(150) NOT NULL,
    persona_apellidos     VARCHAR(150) NOT NULL,
    persona_fechanac      DATE         NULL,
    persona_genero        CHAR(1)      NOT NULL DEFAULT 'X',
    persona_nacionalidad  VARCHAR(3)   NULL,
    persona_foto          VARCHAR(300) NULL,

    persona_estado        CHAR(1)      NOT NULL DEFAULT 'A',
    persona_fecharegistro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_dslp_identificacion (persona_identificacion),
    KEY ix_dslp_alumno (persona_alumnoid),
    KEY ix_dslp_apellidos (persona_apellidos, persona_nombres)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Plantilla
--
-- Quién pertenece a un equipo inscrito, en qué papel y ENTRE QUÉ FECHAS.
--
-- LAS FECHAS SON LO IMPORTANTE
--
-- Con un simple estado activo/inactivo, una transferencia a mitad de
-- torneo reescribiría la historia: al consultar quién estaba habilitado
-- en un partido ya jugado, se obtendría la plantilla de hoy y no la de
-- aquel día. Eso convierte en indemostrable cualquier reclamo sobre
-- alineación indebida, que es justo el caso en que se consulta.
--
-- Con alta y baja, la pregunta «¿estaba habilitado el 12 de mayo?» tiene
-- una respuesta exacta para siempre.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_plantilla (
    plantilla_id            INT AUTO_INCREMENT PRIMARY KEY,
    plantilla_inscripcionid INT         NOT NULL,
    plantilla_personaid     INT         NOT NULL,

    -- 'J' jugador · 'E' entrenador · 'A' asistente · 'D' delegado
    plantilla_rol           CHAR(1)     NOT NULL DEFAULT 'J',
    plantilla_dorsal        SMALLINT    NULL,

    plantilla_alta          DATE        NOT NULL,
    plantilla_baja          DATE        NULL,

    -- Habilitación documental: se puede estar en plantilla y no poder
    -- jugar todavía.
    plantilla_habilitado    CHAR(1)     NOT NULL DEFAULT 'N',
    plantilla_motivo        VARCHAR(250) NOT NULL DEFAULT '',

    plantilla_usuarioid     INT         NULL,
    plantilla_fecharegistro TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_dslpl_inscripcion (plantilla_inscripcionid, plantilla_rol),
    KEY ix_dslpl_persona (plantilla_personaid),
    KEY ix_dslpl_vigencia (plantilla_inscripcionid, plantilla_alta, plantilla_baja),

    CONSTRAINT fk_dslpl_inscripcion FOREIGN KEY (plantilla_inscripcionid)
        REFERENCES dsl_inscripcion (inscripcion_id) ON DELETE CASCADE,
    CONSTRAINT fk_dslpl_persona FOREIGN KEY (plantilla_personaid)
        REFERENCES dsl_persona (persona_id),

    CONSTRAINT ck_dslpl_fechas CHECK (plantilla_baja IS NULL
                                   OR plantilla_baja >= plantilla_alta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Serie
--
-- El contenedor del «mejor de N».
--
-- ES LA TABLA QUE MÁS SE OLVIDA
--
-- Una serie de playoffs no es un partido con más marcadores: son de uno a
-- N partidos entre los mismos dos equipos, y el que gana la serie no es
-- necesariamente el que más puntos anotó. Sin contenedor, «mejor de 5» no
-- se puede expresar y la eliminatoria acaba modelándose a mano, fuera del
-- sistema.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_serie (
    serie_id        INT AUTO_INCREMENT PRIMARY KEY,
    serie_faseid    INT      NOT NULL,
    serie_orden     SMALLINT NOT NULL DEFAULT 1,
    serie_nombre    VARCHAR(60) NOT NULL DEFAULT '',

    -- Las inscripciones enfrentadas, no los equipos: es lo que ata la
    -- serie a la categoría concreta en que se disputa.
    serie_localid     INT    NULL,
    serie_visitanteid INT    NULL,

    -- Partidos máximos. 1 = eliminatoria a partido único.
    serie_mejorde   TINYINT  NOT NULL DEFAULT 1,
    serie_ganadorid INT      NULL,

    UNIQUE KEY uk_dslse_orden (serie_faseid, serie_orden),

    CONSTRAINT fk_dslse_fase FOREIGN KEY (serie_faseid)
        REFERENCES dsl_fase (fase_id) ON DELETE CASCADE,
    CONSTRAINT fk_dslse_local FOREIGN KEY (serie_localid)
        REFERENCES dsl_inscripcion (inscripcion_id),
    CONSTRAINT fk_dslse_visitante FOREIGN KEY (serie_visitanteid)
        REFERENCES dsl_inscripcion (inscripcion_id),
    CONSTRAINT fk_dslse_ganador FOREIGN KEY (serie_ganadorid)
        REFERENCES dsl_inscripcion (inscripcion_id),

    -- Al mejor de N con N par no decide nada.
    CONSTRAINT ck_dslse_mejorde CHECK (serie_mejorde % 2 = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Partido
--
-- La unidad indivisible.
--
-- EL ESCENARIO ES DE ARENA
--
-- partido_instalacionid apunta a dsa_instalacion. League no tiene tabla
-- de canchas: si la tuviera, dos sistemas reservarían el mismo espacio
-- físico sin verse, y el fallo aparecería el día que un cliente alquila
-- la cancha a la misma hora que el generador programó un partido.
--
-- partido_bloqueoid guarda el bloqueo que se crea en Arena al confirmar,
-- para poder retirarlo si el partido se cancela o se reprograma.
--
-- LOS EQUIPOS SON INSCRIPCIONES, NO EQUIPOS
--
-- Un partido enfrenta a dos equipos EN UNA CATEGORÍA. Apuntar a la
-- inscripción y no al club evita que un club inscrito en Sub-14 y Sub-16
-- pueda quedar programado contra sí mismo por error de captura.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_partido (
    partido_id          INT AUTO_INCREMENT PRIMARY KEY,

    partido_faseid      INT      NOT NULL,
    partido_grupoid     INT      NULL,
    partido_serieid     INT      NULL,
    partido_jornadaid   INT      NULL,

    partido_localid     INT      NOT NULL,
    partido_visitanteid INT      NOT NULL,

    partido_instalacionid INT    NULL,
    partido_bloqueoid   INT      NULL,

    partido_fecha       DATE     NULL,
    partido_hora        TIME     NULL,
    partido_duracion    SMALLINT NOT NULL DEFAULT 90,

    partido_estadoid    INT      NOT NULL,

    partido_puntoslocal     SMALLINT NULL,
    partido_puntosvisitante SMALLINT NULL,

    partido_observacion VARCHAR(300) NOT NULL DEFAULT '',
    partido_motivo      VARCHAR(250) NOT NULL DEFAULT '',

    partido_usuarioid   INT      NULL,
    partido_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    partido_fechacambio TIMESTAMP  NULL ON UPDATE CURRENT_TIMESTAMP,

    KEY ix_dslpa_fase   (partido_faseid, partido_jornadaid),
    KEY ix_dslpa_grupo  (partido_grupoid),
    KEY ix_dslpa_serie  (partido_serieid),
    KEY ix_dslpa_equipos (partido_localid, partido_visitanteid),
    KEY ix_dslpa_estado (partido_estadoid),

    -- El índice del calendario: «qué hay en esta cancha ese día».
    KEY ix_dslpa_agenda (partido_fecha, partido_instalacionid, partido_hora),

    CONSTRAINT fk_dslpa_fase FOREIGN KEY (partido_faseid)
        REFERENCES dsl_fase (fase_id),
    CONSTRAINT fk_dslpa_grupo FOREIGN KEY (partido_grupoid)
        REFERENCES dsl_grupo (grupo_id),
    CONSTRAINT fk_dslpa_serie FOREIGN KEY (partido_serieid)
        REFERENCES dsl_serie (serie_id),
    CONSTRAINT fk_dslpa_jornada FOREIGN KEY (partido_jornadaid)
        REFERENCES dsl_jornada (jornada_id),
    CONSTRAINT fk_dslpa_local FOREIGN KEY (partido_localid)
        REFERENCES dsl_inscripcion (inscripcion_id),
    CONSTRAINT fk_dslpa_visitante FOREIGN KEY (partido_visitanteid)
        REFERENCES dsl_inscripcion (inscripcion_id),
    CONSTRAINT fk_dslpa_estado FOREIGN KEY (partido_estadoid)
        REFERENCES dsl_estado (estado_id),

    -- Un equipo no juega contra sí mismo. Parece obvio y es el error de
    -- captura más frecuente al programar a mano.
    CONSTRAINT ck_dslpa_rivales CHECK (partido_localid <> partido_visitanteid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Designaciones: árbitros y oficiales de mesa
--
-- RESUELVE D4
--
-- El control de acceso del ecosistema llega hasta la acción sobre una
-- vista, pero no hasta el registro. Un árbitro con permiso de lectura
-- sobre «partidos» vería toda la liga.
--
-- Esta tabla es el alcance que faltaba: los controladores filtran por
-- DESIGNACIÓN —un hecho deportivo verificable y auditable— y nunca por
-- el nombre del rol, que es lo que el encargo pedía evitar.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_designacion (
    designacion_id        INT AUTO_INCREMENT PRIMARY KEY,
    designacion_partidoid INT      NOT NULL,
    designacion_usuarioid INT      NULL,
    designacion_personaid INT      NULL,

    -- 'A' árbitro principal · 'X' árbitro auxiliar · 'M' mesa
    -- 'C' comisario
    designacion_funcion   CHAR(1)  NOT NULL DEFAULT 'A',

    designacion_estado    CHAR(1)  NOT NULL DEFAULT 'A',
    designacion_usuarioreg INT     NULL,
    designacion_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Una persona no ocupa dos funciones en el mismo partido.
    UNIQUE KEY uk_dsld_persona (designacion_partidoid, designacion_personaid, designacion_funcion),

    -- El índice que sirve la pregunta del control de acceso: «¿qué
    -- partidos son de este usuario?».
    KEY ix_dsld_usuario (designacion_usuarioid, designacion_estado),

    CONSTRAINT fk_dsld_partido FOREIGN KEY (designacion_partidoid)
        REFERENCES dsl_partido (partido_id) ON DELETE CASCADE,
    CONSTRAINT fk_dsld_persona FOREIGN KEY (designacion_personaid)
        REFERENCES dsl_persona (persona_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
