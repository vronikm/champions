-- =====================================================================
-- 069 · Parametrización de los ambientes del SRI
-- =====================================================================
-- QUÉ RESUELVE
--
-- Las direcciones de los servicios web del SRI —recepción y autorización,
-- en pruebas y en producción— y sus tiempos estaban escritos en el código
-- (facturasController::sriEstructuraBase, WebServiceSRIService). Si el SRI
-- las cambia, o hay que darle más tiempo a una conexión lenta, había que
-- tocar código en el servidor.
--
-- Ahora viven aquí, una fila por ambiente, y se editan en Core ›
-- Configuración SRI › Ambientes del SRI (sólo el superadministrador). El
-- ambiente EN USO sigue siendo el de facturas_electronicas_config.ambiente.
--
--   ambiente              '1' pruebas · '2' producción (el código del SRI)
--   url_recepcion         RecepcionComprobantesOffline
--   url_autorizacion      AutorizacionComprobantesOffline
--   timeout_conexion      segundos para conectar (antes, 30 fijo)
--   timeout_respuesta     segundos para la respuesta (antes, 60 fijo)
--   intentos_autorizacion consultas de autorización tras enviar (antes, 4)
--   espera_segundos       espera entre consultas (antes, 3)
--
-- Se siembra con los valores que estaban en el código: nada cambia hasta
-- que alguien los edite.
--
-- ES REPETIBLE: CREATE TABLE IF NOT EXISTS e INSERT IGNORE.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 069_sri_ambientes.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS facturas_electronicas_ambiente (
    ambiente              CHAR(1)      NOT NULL,
    nombre                VARCHAR(30)  NOT NULL,
    url_recepcion         VARCHAR(300) NOT NULL,
    url_autorizacion      VARCHAR(300) NOT NULL,
    timeout_conexion      SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    timeout_respuesta     SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    intentos_autorizacion TINYINT UNSIGNED  NOT NULL DEFAULT 4,
    espera_segundos       TINYINT UNSIGNED  NOT NULL DEFAULT 3,
    updated_by            VARCHAR(50)  NULL,
    updated_at            DATETIME     NULL,
    PRIMARY KEY (ambiente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO facturas_electronicas_ambiente
    (ambiente, nombre, url_recepcion, url_autorizacion, timeout_conexion, timeout_respuesta, intentos_autorizacion, espera_segundos)
VALUES
    ('1', 'Pruebas',
     'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
     'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
     30, 60, 4, 3),
    ('2', 'Producción',
     'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
     'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
     30, 60, 4, 3);

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT ambiente, nombre, url_recepcion, timeout_conexion, timeout_respuesta,
       intentos_autorizacion, espera_segundos
  FROM facturas_electronicas_ambiente ORDER BY ambiente;

-- Para revertir:
-- DROP TABLE facturas_electronicas_ambiente;
