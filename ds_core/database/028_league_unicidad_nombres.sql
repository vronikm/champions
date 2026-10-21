-- =====================================================================
-- 028 · League · Que la unicidad de nombres funcione de verdad
-- =====================================================================
-- La espina (024) declaró estas dos claves únicas:
--
--     dsl_temporada  UNIQUE (temporada_escuelaid, temporada_nombre)
--     dsl_equipo     UNIQUE (equipo_escuelaid,    equipo_nombre)
--
-- y ambas columnas de organización admiten NULL. En MySQL, dos filas con
-- NULL en una columna de una clave única NO colisionan: el estándar trata
-- NULL como «desconocido», y dos desconocidos no son iguales entre sí.
--
-- Consecuencia: en una instalación de una sola organización —donde ese
-- campo se deja vacío— la restricción no se aplicaba nunca. Se podían
-- crear dos temporadas «Temporada 2026» y dos equipos con el mismo
-- nombre, y el sistema los aceptaba en silencio.
--
-- Lo detectó una prueba de extremo a extremo que creaba una temporada
-- repetida a propósito y esperaba el rechazo.
--
-- LA CORRECCIÓN
--
-- El campo pasa a NOT NULL con 0 como valor de «sin organización». Es un
-- centinela, no una clave foránea: no hay FK contra general_escuela en
-- estas tablas, así que 0 no apunta a ninguna fila inexistente. Con un
-- valor concreto en lugar de NULL, la clave única vuelve a comparar y
-- hace su trabajo.
--
-- Se convierten primero los datos y después la columna: al revés, MySQL
-- rechazaría el ALTER si ya hubiera filas con NULL.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Temporada
-- ---------------------------------------------------------------------
UPDATE dsl_temporada SET temporada_escuelaid = 0 WHERE temporada_escuelaid IS NULL;

ALTER TABLE dsl_temporada
    MODIFY temporada_escuelaid INT NOT NULL DEFAULT 0;

-- ---------------------------------------------------------------------
-- Equipo
-- ---------------------------------------------------------------------
UPDATE dsl_equipo SET equipo_escuelaid = 0 WHERE equipo_escuelaid IS NULL;

ALTER TABLE dsl_equipo
    MODIFY equipo_escuelaid INT NOT NULL DEFAULT 0;


-- ---------------------------------------------------------------------
-- Comprobación: no debe quedar ningún nombre repetido dentro de la misma
-- organización. Si esta consulta devuelve filas, hay que resolverlas a
-- mano antes de confiar en la restricción.
-- ---------------------------------------------------------------------
SELECT 'dsl_temporada' AS tabla, temporada_escuelaid AS org,
       temporada_nombre AS nombre, COUNT(*) AS repetidos
  FROM dsl_temporada
 GROUP BY 2, 3 HAVING COUNT(*) > 1

UNION ALL

SELECT 'dsl_equipo', equipo_escuelaid, equipo_nombre, COUNT(*)
  FROM dsl_equipo
 GROUP BY 2, 3 HAVING COUNT(*) > 1;
