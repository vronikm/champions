-- =====================================================================
-- 075 · Textos de las autorizaciones LOPDP, editables desde Core
-- =====================================================================
-- QUÉ RESUELVE
--
-- Los dos textos que acepta el representante —tratamiento de datos (DATOS)
-- y uso de imagen (IMAGEN)— estaban fijos en ds_form/config/consentimientos.php.
-- Ahora se editan en Core › Textos de autorizaciones, y los usan el
-- formulario de inscripción (Fútbol › Generar enlaces) y el enlace de
-- autorización (Fútbol › Consentimientos LOPDP).
--
-- También el FORMULARIO en papel (Fútbol › Representantes › Formulario LPD
-- y su PDF), que estaba escrito dentro de la vista y del PDF.
--
--   consentimiento_texto  una fila por VERSIÓN de cada texto. Guardar un
--                         cambio agrega una versión; la vigente es la mayor.
--                         Nunca se reescribe una anterior: de cada aceptación
--                         se guarda el hash del texto mostrado, y así sigue
--                         siendo demostrable qué redacción aceptó cada uno.
--
-- Sin filas, vale el texto de siempre, como hasta ahora (mismo hash).
--
-- Añade «Textos de autorizaciones» al menú de Core (sólo superadministrador,
-- como el resto de su configuración).
--
-- ES REPETIBLE: CREATE TABLE IF NOT EXISTS y el menú sólo si falta.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 075_consentimiento_texto.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS consentimiento_texto (
    texto_id         INT          NOT NULL AUTO_INCREMENT,
    texto_tipo       VARCHAR(10)  NOT NULL,             -- DATOS · IMAGEN · FORMULARIO (el de papel)
    texto_version    INT          NOT NULL,             -- 1, 2, 3… por tipo
    texto_titulo     VARCHAR(200) NOT NULL,
    texto_contenido  MEDIUMTEXT   NOT NULL,             -- HTML limitado (p, strong, em, listas)
    texto_casilla    VARCHAR(600) NOT NULL,             -- la casilla de aceptar; en FORMULARIO, las autorizaciones del pie
    texto_usuario    VARCHAR(50)  NOT NULL,
    texto_fecha      DATETIME     NOT NULL,
    PRIMARY KEY (texto_id),
    UNIQUE KEY uk_texto_version (texto_tipo, texto_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'core', 'Textos de autorizaciones', 14, 0, 'N', 'consentimientoTextos', 'nav-icon fas fa-file-signature', 'A'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu WHERE menu_modulo = 'core' AND menu_vista = 'consentimientoTextos');

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT texto_tipo, MAX(texto_version) AS vigente FROM consentimiento_texto GROUP BY texto_tipo;
SELECT menu_id, menu_nombre, menu_vista FROM seguridad_menu WHERE menu_vista = 'consentimientoTextos';

-- Para revertir (vuelve el texto del archivo):
-- DELETE FROM seguridad_menu WHERE menu_modulo = 'core' AND menu_vista = 'consentimientoTextos';
-- DROP TABLE consentimiento_texto;
