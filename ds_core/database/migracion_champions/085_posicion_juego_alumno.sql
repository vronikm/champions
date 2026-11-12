-- 085_posicion_juego_alumno.sql
-- Posición de juego preferida del alumno.
--
-- sujeto_alumno.alumno_posicionid existía desde v1, pero el formulario
-- nunca la pedía y el alta la guardaba vacía (o la dejaba como venía del sistema anterior).
-- Ahora se elige en el alta y en la edición del alumno con el
-- catálogo «posicion_juego» (Core › Catálogos), para trabajo diferenciado,
-- armar equipos y horarios.
--
-- Qué hace:
--   1. Asegura «No definido» (3ND) en el catálogo posicion_juego.
--   2. Lo asigna a todo alumno sin posición o con un código que no está en
--      ese catálogo.
--   3. La columna pasa a NOT NULL DEFAULT '3ND': cualquier alta que no la
--      envíe (formulario público, importación) queda «No definido».
--
-- Idempotente: repetirla no cambia nada.
SET NAMES utf8mb4;

/* ---------- 1. «No definido» en el catálogo ---------- */

INSERT INTO general_tabla_catalogo
       (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
SELECT '3ND', T.tabla_id, 'No definido', 'A'
  FROM general_tabla T
 WHERE T.tabla_nombre = 'posicion_juego'
   AND NOT EXISTS (SELECT 1 FROM general_tabla_catalogo WHERE catalogo_valor = '3ND');

/* ---------- 2. Alumnos sin posición válida ---------- */

UPDATE sujeto_alumno A
   SET A.alumno_posicionid = '3ND'
 WHERE A.alumno_posicionid IS NULL
    OR NOT EXISTS (SELECT 1
                     FROM general_tabla_catalogo C
                     INNER JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid
                    WHERE T.tabla_nombre   = 'posicion_juego'
                      AND C.catalogo_valor = A.alumno_posicionid);

/* ---------- 3. Valor por defecto ---------- */

ALTER TABLE sujeto_alumno
    MODIFY alumno_posicionid VARCHAR(3)
           CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '3ND';
