-- =====================================================================
-- 090 · Insights: el aviso «Becas del 100 %» explica bien por qué avisa
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- La ayuda del umbral «becas_completas» (Insights › Configuración) decía
-- que se avisa «porque su importe se guarda como 0,00 y no aparece en
-- ninguna suma de descuentos». Era una lectura equivocada del campo: en
-- alumno_pago_descuento, descuento_valor es la PENSION QUE PAGA el alumno
-- con el beneficio, así que la beca del 100 % guarda 0,00 porque paga 0.
-- Insights ya calcula lo que se deja de cobrar como pensión de la sede
-- menos lo que paga (insightsController::beneficios y descuentos).
--
--
-- QUE HACE ESTA MIGRACION
--
-- Sólo cambia el texto de ayuda de ese umbral. No toca su valor ni si
-- está activo.
--
-- Común a los cuatro sistemas (ds_core/database).
--
--
-- SE PUEDE REPETIR
--
-- Es un UPDATE por código: repetirlo deja el mismo texto.
-- =====================================================================

SET NAMES utf8mb4;

UPDATE insights_umbral
   SET umbral_ayuda = 'Cuántas becas del 100 % tienen los alumnos activos y cuánto se deja de cobrar al mes por ellas (la pensión de su sede, porque no pagan).'
 WHERE umbral_codigo = 'becas_completas';
