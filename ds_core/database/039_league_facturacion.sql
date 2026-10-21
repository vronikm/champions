-- =====================================================================
-- 039 · League · Habilitar la emisión de comprobantes
-- =====================================================================
-- Dos cambios, y el segundo no es opcional: sin él, activar el punto de
-- emisión no serviría de nada porque no habría a quién emitirle.
--
--
-- 1. SE ACTIVA EL PUNTO DE EMISIÓN 003-003
--
-- Estaba reservado en estado 'I' desde la migración 021. Mientras siga
-- inactivo, facturacion_reservar_secuencial('league') lanza excepción a
-- propósito: emitir desde un punto no declarado produce comprobantes fuera
-- de la numeración informada al SRI.
--
--
-- 2. dsl_equipo NECESITA IDENTIFICACIÓN TRIBUTARIA
--
-- Una factura electrónica exige del comprador: tipo y número de
-- identificación, razón social y dirección. El equipo sólo tenía un
-- contacto, un teléfono y un correo —datos de coordinación deportiva, no
-- tributarios— así que no había con qué llenar el comprobante.
--
-- Se guardan en el equipo y no se piden en cada emisión porque el club
-- factura siempre al mismo RUC: retecleárlo cada vez es la forma más
-- eficaz de que el SRI devuelva el comprobante por un dígito cambiado.
-- La factura los copia igualmente (dsl_factura ya tiene sus campos
-- factura_cliente*), de modo que un cambio de RUC del club no reescribe
-- el histórico.
--
--
-- POR QUÉ NO SE FACTURA A LA PERSONA
--
-- Los conceptos de ámbito PERSONA —carné, inscripción de jugador— se
-- facturan AL EQUIPO al que pertenece esa persona, no a ella. Dos razones,
-- y ambas pesan: en una liga de base la mayoría de jugadores son menores,
-- que ni tienen RUC ni deben aparecer como sujeto de un comprobante; y el
-- dinero lo paga el club, que es quien necesita la factura para su propia
-- contabilidad. Emitir a nombre de un menor sería incorrecto tributaria y
-- jurídicamente a la vez.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. El punto de emisión de League queda activo.
-- ---------------------------------------------------------------------
UPDATE facturas_electronicas_punto_emision
   SET punto_estado = 'A'
 WHERE punto_modulo = 'league'
   AND punto_estado <> 'A';


-- ---------------------------------------------------------------------
-- 2. Identificación tributaria del equipo.
--
-- Todo con DEFAULT '' y no NULL: un equipo sin datos fiscales es un
-- equipo que aún no puede facturar, no un error. La comprobación de si
-- están completos la hace el servicio antes de emitir, con un mensaje que
-- dice qué falta.
-- ---------------------------------------------------------------------
ALTER TABLE dsl_equipo
    -- Código del catálogo del SRI: 04 RUC, 05 cédula, 06 pasaporte,
    -- 07 consumidor final. Se guarda el CÓDIGO y no la palabra porque es
    -- lo que viaja en el XML.
    ADD COLUMN equipo_idtipo        CHAR(2)      NOT NULL DEFAULT '04' AFTER equipo_escudo,
    ADD COLUMN equipo_identificacion VARCHAR(20) NOT NULL DEFAULT ''   AFTER equipo_idtipo,
    ADD COLUMN equipo_razonsocial   VARCHAR(300) NOT NULL DEFAULT ''   AFTER equipo_identificacion,
    ADD COLUMN equipo_direccion     VARCHAR(300) NOT NULL DEFAULT ''   AFTER equipo_razonsocial;


-- ---------------------------------------------------------------------
-- Índice sobre la identificación.
--
-- NO es único: dos equipos del mismo club pueden compartir RUC, que es
-- justo lo que pasa cuando una institución inscribe su Sub-12 y su Sub-14.
-- Sirve para buscar «todo lo facturado a este RUC».
-- ---------------------------------------------------------------------
CREATE INDEX ix_dsle_identificacion ON dsl_equipo (equipo_identificacion);
