-- =====================================================================
-- 040 · League · El origen de la factura es la obligación
-- =====================================================================
-- La 020 creó dsl_factura antes de que existiera el modelo de
-- obligaciones, y su factura_origentipo enumeraba conceptos:
-- 'INSCRIPCION','MULTA','ARBITRAJE','ESCENARIO','OTRO'. Eso era razonable
-- entonces y hoy es incorrecto por dos motivos.
--
-- DUPLICA LO QUE YA DICE EL CATÁLOGO
--
-- Qué se cobra lo dice dsl_concepto, y ahí es donde se mantiene. Tenerlo
-- también en la factura crea dos verdades que se separan en cuanto alguien
-- añade un concepto nuevo, porque el ENUM no crece solo.
--
-- Y NO SABE REPRESENTAR UNA FACTURA DE VARIAS LÍNEAS
--
-- Un comprobante agrupa varias obligaciones —inscripción, carnés y
-- arbitraje del mes—, que pueden ser de conceptos distintos. Un único
-- valor no puede describir eso: elegiría uno y callaría los demás.
--
-- El vínculo de verdad va en el otro sentido: dsl_obligacion.
-- obligacion_facturaid, que admite N obligaciones por comprobante. Lo que
-- queda aquí es un puntero de conveniencia a la primera línea, y así se
-- documenta para que nadie lo trate como la relación.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE dsl_factura
    MODIFY COLUMN factura_origentipo
        ENUM('OBLIGACION','INSCRIPCION','MULTA','ARBITRAJE','ESCENARIO','OTRO')
        NOT NULL DEFAULT 'OBLIGACION'
        COMMENT 'Siempre OBLIGACION en las emisiones nuevas. El vínculo real, que admite varias obligaciones por comprobante, es dsl_obligacion.obligacion_facturaid; factura_origenid sólo apunta a la primera línea.';
