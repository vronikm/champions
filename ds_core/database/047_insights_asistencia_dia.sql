-- =====================================================================
-- 047 · La asistencia, un dia por fila
-- =====================================================================
-- QUE RESUELVE
--
-- asistencia_asistencia guarda una fila por alumno y MES, con 31 columnas
-- asistencia_D01..D31 y ninguna columna de fecha. Para preguntar cualquier
-- cosa hay que despivotar, y el proyecto ya lo hace: asistenciaController
-- arma un UNION ALL de 31 ramas para pintar el calendario de un alumno.
--
-- El problema no es la velocidad. Se midio: a este volumen el UNION tarda
-- 22 ms y funciona. El problema es que cada informe de asistencia tendria
-- que generar 4.434 caracteres de SQL con 31 ramas, y que NO se puede
-- filtrar por rango de fechas apoyandose en un indice.
--
--
-- POR QUE UNA VISTA Y NO UNA TABLA
--
-- Se probaron las tres y se midieron:
--
--   UNION en cada consulta   22,0 ms   4.434 caracteres   no duplica
--   VISTA                    24,3 ms     427 caracteres   no duplica
--   TABLA derivada           24,1 ms     427 caracteres   duplica 1.243 filas
--
-- La tabla NO es mas rapida. Su unica ventaja aparece al filtrar por rango
-- de fechas (1,4 ms frente a 22,7), y eso solo importara cuando haya
-- volumen. Duplicar datos hoy, sin justificacion de rendimiento ni de
-- historico, seria ir contra la regla del proyecto.
--
-- La vista da la simplicidad sin duplicar nada. Y si algun dia hace falta
-- la tabla, se materializa con el MISMO nombre y las MISMAS columnas: no
-- habria que tocar ni una consulta. La decision queda aplazada sin coste.
--
--
-- EL DIA 31 DE FEBRERO NO EXISTE, Y HAY QUE FILTRARLO
--
-- Todas las filas tienen las 31 columnas, tenga el mes 28 dias o 31.
-- STR_TO_DATE('20260230','%Y%m%d') devuelve NULL, no un error. Sin el
-- filtro final, esos NULL entrarian en la vista como marcas sin fecha y
-- descuadrarian cualquier conteo por periodo.
--
--
-- LAS MARCAS, Y LO QUE SIGNIFICAN
--
--   P  presente        845
--   A  atraso            4    llego tarde, pero asistio
--   F  falta           289
--   J  justificado     105    falto, y el representante aviso
--
-- La vista NO interpreta: entrega la marca cruda. La regla de negocio vive
-- en quien consulta, y esta escrita en MODELO_INSIGHTS.md:
--
--   % asistencia  =  (P + A) / total          -- el justificado es falta
--   % avisadas    =  J / (F + J)              -- indicador propio
--
-- Con los datos de hoy: 68,3 % de asistencia y solo 26,6 % de las
-- inasistencias avisadas por el representante.
-- =====================================================================

DROP VIEW IF EXISTS insights_v_asistencia_dia;

CREATE VIEW insights_v_asistencia_dia AS
SELECT dia_alumnoid, dia_fecha, dia_marca
  FROM (
        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '01'), '%Y%m%d') AS dia_fecha,
               asistencia_D01 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D01 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '02'), '%Y%m%d') AS dia_fecha,
               asistencia_D02 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D02 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '03'), '%Y%m%d') AS dia_fecha,
               asistencia_D03 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D03 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '04'), '%Y%m%d') AS dia_fecha,
               asistencia_D04 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D04 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '05'), '%Y%m%d') AS dia_fecha,
               asistencia_D05 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D05 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '06'), '%Y%m%d') AS dia_fecha,
               asistencia_D06 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D06 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '07'), '%Y%m%d') AS dia_fecha,
               asistencia_D07 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D07 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '08'), '%Y%m%d') AS dia_fecha,
               asistencia_D08 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D08 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '09'), '%Y%m%d') AS dia_fecha,
               asistencia_D09 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D09 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '10'), '%Y%m%d') AS dia_fecha,
               asistencia_D10 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D10 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '11'), '%Y%m%d') AS dia_fecha,
               asistencia_D11 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D11 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '12'), '%Y%m%d') AS dia_fecha,
               asistencia_D12 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D12 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '13'), '%Y%m%d') AS dia_fecha,
               asistencia_D13 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D13 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '14'), '%Y%m%d') AS dia_fecha,
               asistencia_D14 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D14 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '15'), '%Y%m%d') AS dia_fecha,
               asistencia_D15 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D15 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '16'), '%Y%m%d') AS dia_fecha,
               asistencia_D16 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D16 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '17'), '%Y%m%d') AS dia_fecha,
               asistencia_D17 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D17 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '18'), '%Y%m%d') AS dia_fecha,
               asistencia_D18 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D18 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '19'), '%Y%m%d') AS dia_fecha,
               asistencia_D19 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D19 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '20'), '%Y%m%d') AS dia_fecha,
               asistencia_D20 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D20 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '21'), '%Y%m%d') AS dia_fecha,
               asistencia_D21 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D21 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '22'), '%Y%m%d') AS dia_fecha,
               asistencia_D22 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D22 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '23'), '%Y%m%d') AS dia_fecha,
               asistencia_D23 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D23 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '24'), '%Y%m%d') AS dia_fecha,
               asistencia_D24 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D24 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '25'), '%Y%m%d') AS dia_fecha,
               asistencia_D25 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D25 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '26'), '%Y%m%d') AS dia_fecha,
               asistencia_D26 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D26 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '27'), '%Y%m%d') AS dia_fecha,
               asistencia_D27 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D27 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '28'), '%Y%m%d') AS dia_fecha,
               asistencia_D28 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D28 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '29'), '%Y%m%d') AS dia_fecha,
               asistencia_D29 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D29 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '30'), '%Y%m%d') AS dia_fecha,
               asistencia_D30 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D30 IS NOT NULL

        UNION ALL

        SELECT asistencia_alumnoid AS dia_alumnoid,
               STR_TO_DATE(CONCAT(asistencia_aniomes, '31'), '%Y%m%d') AS dia_fecha,
               asistencia_D31 AS dia_marca
          FROM asistencia_asistencia
         WHERE asistencia_D31 IS NOT NULL
  ) x
 WHERE dia_fecha IS NOT NULL;
