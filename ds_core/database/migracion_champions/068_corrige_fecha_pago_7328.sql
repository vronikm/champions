-- Pago 7328 (alumno 508): se registró con el año mal tecleado.
--
-- Fecha 0025-02-05 y periodo «Febrero / 25». Es el segundo de un lote de
-- tres cuotas que se cobraron juntas el 2026-01-09 —enero, febrero y marzo
-- de 2025, recibos 7710, 7711 y 7712—, así que el año es 2025.
--
-- No es cosmético: Insights y los reportes agrupan por año, y una cuota del
-- año 25 aparece como un ingreso de hace dos mil años y descuadra 2025.
--
-- Idempotente: sólo actúa si encuentra el valor erróneo.
SET NAMES utf8mb4;

UPDATE alumno_pago
   SET pago_fecha   = '2025-02-05',
       pago_periodo = 'Febrero / 2025'
 WHERE pago_id = 7328
   AND pago_fecha = '0025-02-05';

-- Para revertir:
-- UPDATE alumno_pago SET pago_fecha = '0025-02-05', pago_periodo = 'Febrero / 25'
--  WHERE pago_id = 7328 AND pago_fecha = '2025-02-05';
