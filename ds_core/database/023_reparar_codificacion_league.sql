-- =====================================================================
-- 023 · Reparar los textos mal codificados de las migraciones 017 y 018
-- =====================================================================
-- Las migraciones 017 y 018 se escribieron sin SET NAMES. Al aplicarlas
-- desde la consola de Windows, el cliente conectó en cp850: los bytes
-- UTF-8 de las tildes y del guion largo se interpretaron como cp850 y se
-- volvieron a codificar, quedando doblemente codificados.
--
--     «Escuela — pensiones»   se guardó como   «Escuela ÔÇö pensiones»
--     «revisión»              se guardó como   «revisi├│n»
--
-- Aquellas migraciones ya llevan el SET NAMES, de modo que una
-- instalación nueva sale correcta. Ésta repara la que ya las ejecutó.
--
-- POR QUÉ SE REESCRIBEN LOS VALORES Y NO SE «DESCODIFICAN»
--
-- Deshacer una doble codificación con CONVERT es posible pero delicado:
-- basta un carácter que no exista en cp850 para que la conversión pierda
-- información en silencio, y el resultado se parece lo bastante al
-- original como para que nadie lo note. Los textos afectados son cinco y
-- se conocen: reescribirlos es exacto y verificable.
--
-- El WHERE localiza las filas por su clave, no por el texto roto, para
-- que la migración sea idempotente y no dependa de cómo lea el cliente.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Descripciones de los puntos de emisión (017)
-- ---------------------------------------------------------------------
UPDATE facturas_electronicas_punto_emision
   SET punto_descripcion = 'Escuela — pensiones y matrículas'
 WHERE punto_modulo = 'basketball';

UPDATE facturas_electronicas_punto_emision
   SET punto_descripcion = 'Arena — alquiler de instalaciones'
 WHERE punto_modulo = 'arena';

UPDATE facturas_electronicas_punto_emision
   SET punto_descripcion = 'League — inscripciones, arbitraje y multas'
 WHERE punto_modulo = 'league';


-- ---------------------------------------------------------------------
-- Catálogo de estados y transiciones (018)
-- ---------------------------------------------------------------------
UPDATE dsl_estado
   SET estado_nombre = 'Enviada a revisión'
 WHERE estado_entidad = 'inscripcion' AND estado_codigo = 'ENVIADA';

UPDATE dsl_estado_transicion T
  JOIN dsl_estado D ON D.estado_id = T.trans_desde
  JOIN dsl_estado H ON H.estado_id = T.trans_hasta
   SET T.trans_accion = 'Enviar a revisión'
 WHERE T.trans_entidad = 'inscripcion'
   AND D.estado_codigo = 'BORRADOR'
   AND H.estado_codigo = 'ENVIADA';


-- ---------------------------------------------------------------------
-- Comprobación: no debe quedar ningún texto con la firma de la doble
-- codificación. E294 y C394 son los prefijos que aparecen al releer en
-- cp850 un guion largo o una vocal acentuada.
-- ---------------------------------------------------------------------
SELECT 'facturas_electronicas_punto_emision' AS tabla, COUNT(*) AS pendientes
  FROM facturas_electronicas_punto_emision
 WHERE HEX(punto_descripcion) REGEXP 'E294|C394'
UNION ALL
SELECT 'dsl_estado', COUNT(*)
  FROM dsl_estado
 WHERE HEX(estado_nombre) REGEXP 'E294|C394'
UNION ALL
SELECT 'dsl_estado_transicion', COUNT(*)
  FROM dsl_estado_transicion
 WHERE HEX(trans_accion) REGEXP 'E294|C394';
