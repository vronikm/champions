-- =====================================================================
-- 074 · Enlaces de autorización LOPDP para representantes ya registrados
-- =====================================================================
-- QUÉ RESUELVE
--
-- El formulario de inscripción pide las dos autorizaciones (tratamiento de
-- datos y uso de imagen), pero los representantes que se registraron antes,
-- o desde el sistema, no las tienen. Fútbol › Consentimientos LOPDP les
-- envía ahora, por WhatsApp o por correo, un enlace a una página que muestra
-- SÓLO las dos autorizaciones (ds_form/autorizacion.php).
--
--   consentimiento_enlace  un enlace emitido: para qué representante, quién
--                          lo generó, por qué canal, hasta cuándo vale y
--                          cuándo se respondió. El enlace lleva un código
--                          (enlace_codigo) firmado: sin esta fila no vale,
--                          y se usa UNA sola vez.
--
-- Lo que el representante responde queda, como siempre, en
-- alumno_consentimiento, con origen ENLACE, su IP y el hash del texto.
--
-- ES REPETIBLE: CREATE TABLE IF NOT EXISTS.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 074_consentimiento_enlace.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS consentimiento_enlace (
    enlace_id        INT          NOT NULL AUTO_INCREMENT,
    enlace_repreid   INT          NOT NULL,
    enlace_codigo    CHAR(32)     NOT NULL,             -- va firmado dentro del enlace
    enlace_canal     VARCHAR(10)  NOT NULL,             -- WHATSAPP · CORREO · COPIA
    enlace_usuario   VARCHAR(50)  NOT NULL,             -- quien lo generó
    enlace_fecha     DATETIME     NOT NULL,
    enlace_expira    DATETIME     NOT NULL,
    enlace_usado     DATETIME     NULL,                 -- cuando se respondió (o se anuló)
    enlace_ip        VARCHAR(45)  NULL,                 -- desde dónde se respondió
    PRIMARY KEY (enlace_id),
    UNIQUE KEY uk_enlace_codigo (enlace_codigo),
    KEY ix_enlace_repre (enlace_repreid, enlace_fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
