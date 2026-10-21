-- =====================================================================
-- 058 · Varias plantillas de tarjeta, y una sola activa de verdad
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- La 056 dejo la tabla preparada para varias plantillas, pero la pantalla
-- solo sabia editar una: subir un marco SUSTITUIA el archivo de esa fila. En
-- cuanto se uso de verdad paso lo previsible:
--
--   * se subio el marco nuevo sobre la fila sembrada,
--   * la geometria del marco clasico se perdio de la base,
--   * y la fila siguio llamandose «Clasica» conteniendo otro marco, con lo
--     que el nombre dejo de significar nada.
--
-- Esta migracion repara esos tres danos y añade la garantia que faltaba.
--
--
-- POR QUE UNA COLUMNA GENERADA Y NO SOLO CODIGO
--
-- «Solo una plantilla activa» es una regla de datos, no de pantalla. Si vive
-- unicamente en el controlador, cualquier UPDATE hecho a mano —una
-- restauracion parcial, un arreglo urgente por consola— puede dejar dos filas
-- en 'A'. Y entonces el generador, que hace «WHERE plantilla_estado='A' LIMIT
-- 1», imprime con la que el optimizador decida ese dia. Un fallo que aparece
-- meses despues, en las tarjetas, sin nada en los registros.
--
-- La columna generada vale 1 cuando la fila esta activa y NULL cuando no. El
-- indice unico admite tantos NULL como haga falta pero solo un 1, asi que la
-- base rechaza la segunda activa venga de donde venga.
--
--
-- ESTA MIGRACION NO ES REPETIBLE
--
-- ADD COLUMN y ADD UNIQUE fallan si ya existen. Se ejecuta una vez. Los dos
-- pasos de datos si son idempotentes, para que un reintento tras un fallo a
-- mitad no duplique nada.
--
-- Aplicar con:  mysql -u root digitech_barcelona < 058_tarjeta_cumple_varias.sql

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. La fila sembrada ya no contiene el marco clasico: que lo diga
-- ---------------------------------------------------------------------
-- Solo se renombra si SIGUE llamandose «Clasica» y apunta a otro archivo. Si
-- alguien ya le puso un nombre propio, se respeta.
UPDATE cumple_plantilla
   SET plantilla_nombre = 'Marco 2026'
 WHERE plantilla_nombre = 'Clásica'
   AND plantilla_archivo <> 'marco.png';

-- ---------------------------------------------------------------------
-- 2. Devolver la plantilla clasica, con la geometria que tenia
-- ---------------------------------------------------------------------
-- Los numeros son los mismos que sembro la 056: son las fracciones que el
-- codigo antiguo llevaba cableadas, medidas contra marco.png. Entra INACTIVA:
-- recuperar una plantilla no es cambiar la tarjeta que la escuela imprime.
INSERT INTO cumple_plantilla
    (plantilla_nombre, plantilla_archivo, plantilla_estado,
     foto_x, foto_y, foto_ancho, foto_alto,
     nombre_x, nombre_y, nombre_ancho, nombre_tam, nombre_color, nombre_alinea,
     edad_x, edad_y, edad_ancho, edad_tam, edad_color, edad_alinea, edad_texto,
     franja_activa, franja_alto, franja_color, franja_opacidad, angulo)
SELECT
    'Clásica', 'marco.png', 'I',
     21.778, 17.562, 55.778, 46.250,
     21.778, 58.812, 55.778, 3.000, '#FFFFFF', 'C',
     21.778, 61.562, 55.778, 2.222, '#FFE001', 'C', '{edad} AÑOS',
     'S', 8.312, '#054017', 70, -2.50
  FROM DUAL
 WHERE NOT EXISTS (
     SELECT 1 FROM (SELECT 1 FROM cumple_plantilla
                     WHERE plantilla_archivo = 'marco.png' LIMIT 1) AS ya);

-- ---------------------------------------------------------------------
-- 3. La base garantiza que solo hay una activa
-- ---------------------------------------------------------------------
-- Si esto falla con «Duplicate entry '1'», es que ya hay dos filas en 'A'.
-- Deje una sola activa y vuelva a ejecutar:
--     UPDATE cumple_plantilla SET plantilla_estado='I'
--      WHERE plantilla_estado='A' AND plantilla_id <> <la que se imprime>;
ALTER TABLE cumple_plantilla
  ADD COLUMN plantilla_activa TINYINT UNSIGNED
      GENERATED ALWAYS AS (CASE WHEN plantilla_estado = 'A' THEN 1 END) VIRTUAL
      COMMENT '1 si es la plantilla que se imprime; NULL si no. Solo para el indice unico.',
  ADD UNIQUE KEY uq_cumple_plantilla_activa (plantilla_activa);

-- ---------------------------------------------------------------------
-- Comprobacion
-- ---------------------------------------------------------------------
-- Deben salir dos filas y exactamente una con plantilla_activa = 1.
SELECT plantilla_id, plantilla_nombre, plantilla_archivo,
       plantilla_estado, plantilla_activa
  FROM cumple_plantilla
 ORDER BY plantilla_estado DESC, plantilla_nombre;
