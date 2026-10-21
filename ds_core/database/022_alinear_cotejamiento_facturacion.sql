-- =====================================================================
-- 022 · Alinear el cotejamiento de la familia de facturación
-- =====================================================================
-- La tabla de puntos de emisión (migración 017) se creó con
-- utf8mb4_0900_ai_ci, que es el cotejamiento de las tablas nuevas del
-- ecosistema. Pero se une constantemente con facturas_electronicas y
-- facturas_electronicas_secuenciales, que son utf8mb4_unicode_ci, y MySQL
-- rechaza la comparación:
--
--     ERROR 1267 · Illegal mix of collations for operation '='
--
-- El síntoma fue silencioso: la pantalla de puntos de emisión aparecía
-- vacía en lugar de dar error, porque el helper de consulta devuelve un
-- array vacío cuando la sentencia falla.
--
-- Se alinea con la familia de facturación en lugar de al revés: convertir
-- facturas_electronicas afectaría a datos ya emitidos y firmados, y no
-- hay motivo para asumir ese riesgo por una tabla de configuración que
-- todavía tiene tres filas.
--
-- DEUDA MÁS AMPLIA, QUE ESTA MIGRACIÓN NO TOCA
--
-- La base tiene hoy cuatro cotejamientos repartidos:
--
--     utf8mb4_0900_ai_ci    36 tablas   (Arena, League, núcleo reciente)
--     utf8mb3_spanish2_ci   21 tablas   (Basketball heredado)
--     utf8mb4_unicode_ci    10 tablas   (facturación)
--     utf8mb3_general_ci     1 tabla
--
-- Las 21 en utf8mb3 son el problema de fondo: esa codificación no admite
-- caracteres de cuatro bytes —emoji, y algunos signos— y arrastra este
-- mismo choque a cualquier consulta que las una con una tabla moderna.
-- Unificarlas es una migración aparte, con respaldo y ventana de
-- mantenimiento, no un efecto colateral de esta.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE facturas_electronicas_punto_emision
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- La vista se rehace declarando el mismo cotejamiento.
--
-- dsl_factura sigue en utf8mb4_0900_ai_ci —es una tabla de League, no de
-- la familia de facturación— y por eso el COLLATE se declara columna a
-- columna en los dos lados del UNION. Así la vista no depende del
-- cotejamiento con que esté creada cada tabla.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_comprobante_emitido AS
    SELECT CAST('basketball' AS CHAR(20)) COLLATE utf8mb4_unicode_ci AS origen_modulo,
           F.id                                                      AS origen_id,
           F.clave_acceso           COLLATE utf8mb4_unicode_ci       AS clave_acceso,
           F.tipo_comprobante       COLLATE utf8mb4_unicode_ci       AS tipo_comprobante,
           F.establecimiento        COLLATE utf8mb4_unicode_ci       AS establecimiento,
           F.punto_emision          COLLATE utf8mb4_unicode_ci       AS punto_emision,
           F.secuencial             COLLATE utf8mb4_unicode_ci       AS secuencial,
           F.fecha_emision                                           AS fecha_emision,
           F.cliente_identificacion COLLATE utf8mb4_unicode_ci       AS cliente_identificacion,
           F.cliente_razon_social   COLLATE utf8mb4_unicode_ci       AS cliente_razon_social,
           F.subtotal                                                AS subtotal,
           F.descuento                                               AS descuento,
           F.iva                                                     AS iva,
           F.total                                                   AS total,
           CAST(F.estado_sri AS CHAR(20)) COLLATE utf8mb4_unicode_ci AS estado_sri,
           F.numero_autorizacion    COLLATE utf8mb4_unicode_ci       AS numero_autorizacion,
           F.fecha_autorizacion                                      AS fecha_autorizacion
      FROM facturas_electronicas F

    UNION ALL

    SELECT CAST('league' AS CHAR(20))     COLLATE utf8mb4_unicode_ci,
           L.factura_id,
           L.factura_claveacceso          COLLATE utf8mb4_unicode_ci,
           L.factura_tipocomprobante      COLLATE utf8mb4_unicode_ci,
           L.factura_establecimiento      COLLATE utf8mb4_unicode_ci,
           L.factura_puntoemision         COLLATE utf8mb4_unicode_ci,
           L.factura_secuencial           COLLATE utf8mb4_unicode_ci,
           L.factura_fechaemision,
           L.factura_clienteid_num        COLLATE utf8mb4_unicode_ci,
           L.factura_clienterazon         COLLATE utf8mb4_unicode_ci,
           L.factura_subtotal,
           L.factura_descuento,
           L.factura_iva,
           L.factura_total,
           CAST(L.factura_estadosri AS CHAR(20)) COLLATE utf8mb4_unicode_ci,
           L.factura_numautorizacion      COLLATE utf8mb4_unicode_ci,
           L.factura_fechaautoriza
      FROM dsl_factura L;
