-- =====================================================================
-- 073 · Redes sociales: configuración y publicación de cumpleaños
-- =====================================================================
-- QUÉ RESUELVE
--
-- Core › Redes sociales configura las redes de la escuela y Fútbol ›
-- Cumpleaños publica desde ahí la tarjeta de felicitación.
--
--   redes_config      fila única (config_lock = 'X'):
--                     · perfiles: direcciones de Facebook, Instagram, TikTok,
--                       YouTube y el WhatsApp de la escuela;
--                     · Facebook (página): id y token de página. El token se
--                       guarda CIFRADO (AES-256, llave derivada de
--                       TOKEN_SECRET): nunca en claro, nunca a la pantalla;
--                     · Instagram (cuenta profesional): id de la cuenta;
--                     · el mensaje de cumpleaños (con {nombre}, {edad}…) y
--                       los hashtags.
--   redes_publicacion qué se publicó, dónde, quién y con qué resultado. Evita
--                     publicar dos veces el mismo cumpleaños en la misma red
--                     y deja rastro de cada publicación.
--
-- Sólo se publica a quien tiene autorizado el uso de imagen (consentimiento
-- LOPDP vigente de tipo IMAGEN). Esa regla está en el código, no aquí.
--
-- Añade «Redes sociales» al menú de Core (sólo superadministrador, como el
-- resto de su configuración).
--
-- ES REPETIBLE: CREATE TABLE IF NOT EXISTS y el menú sólo si falta.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 073_redes_sociales.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS redes_config (
    config_lock        CHAR(1)      NOT NULL DEFAULT 'X',
    url_facebook       VARCHAR(255) NOT NULL DEFAULT '',
    url_instagram      VARCHAR(255) NOT NULL DEFAULT '',
    url_tiktok         VARCHAR(255) NOT NULL DEFAULT '',
    url_youtube        VARCHAR(255) NOT NULL DEFAULT '',
    whatsapp           VARCHAR(20)  NOT NULL DEFAULT '',
    fb_activo          CHAR(1)      NOT NULL DEFAULT 'N',
    fb_pagina_id       VARCHAR(40)  NOT NULL DEFAULT '',
    fb_pagina_nombre   VARCHAR(150) NOT NULL DEFAULT '',
    fb_token_cifrado   TEXT         NULL,
    ig_activo          CHAR(1)      NOT NULL DEFAULT 'N',
    ig_cuenta_id       VARCHAR(40)  NOT NULL DEFAULT '',
    graph_version      VARCHAR(10)  NOT NULL DEFAULT 'v21.0',
    cumple_mensaje     TEXT         NULL,
    cumple_hashtags    VARCHAR(300) NOT NULL DEFAULT '',
    updated_by         VARCHAR(50)  NULL,
    updated_at         DATETIME     NULL,
    PRIMARY KEY (config_lock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS redes_publicacion (
    pub_id          INT          NOT NULL AUTO_INCREMENT,
    pub_tipo        VARCHAR(20)  NOT NULL,              -- CUMPLE
    pub_red         VARCHAR(20)  NOT NULL,              -- facebook · instagram
    pub_alumnoid    INT          NULL,
    pub_anio        SMALLINT     NOT NULL,              -- un cumpleaños por año
    pub_estado      CHAR(2)      NOT NULL,              -- OK · ER
    pub_externo_id  VARCHAR(80)  NULL,                  -- id de la publicación en la red
    pub_mensaje     TEXT         NULL,
    pub_error       VARCHAR(500) NULL,
    pub_usuario     VARCHAR(50)  NOT NULL,
    pub_fecha       DATETIME     NOT NULL,
    PRIMARY KEY (pub_id),
    KEY idx_pub_alumno (pub_tipo, pub_alumnoid, pub_anio, pub_red),
    CONSTRAINT fk_pub_alumno FOREIGN KEY (pub_alumnoid) REFERENCES sujeto_alumno (alumno_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO redes_config (config_lock, cumple_mensaje, cumple_hashtags)
VALUES ('X',
        '¡Feliz cumpleaños, {nombre}! 🎉⚽ Toda la familia de {escuela} te desea un gran día por tus {edad} años. ¡A seguir creciendo dentro y fuera de la cancha!',
        '#FelizCumpleaños');

INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'core', 'Redes sociales', 13, 0, 'N', 'redesSociales', 'nav-icon fas fa-share-nodes', 'A'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu WHERE menu_modulo = 'core' AND menu_vista = 'redesSociales');

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT config_lock, fb_activo, ig_activo, graph_version, LEFT(cumple_mensaje, 40) AS mensaje FROM redes_config;
SELECT menu_id, menu_nombre, menu_vista FROM seguridad_menu WHERE menu_vista = 'redesSociales';

-- Para revertir:
-- DELETE FROM seguridad_menu WHERE menu_modulo = 'core' AND menu_vista = 'redesSociales';
-- DROP TABLE redes_publicacion; DROP TABLE redes_config;
