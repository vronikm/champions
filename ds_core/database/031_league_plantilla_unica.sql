-- =====================================================================
-- 031 · League · Una persona no entra dos veces a la misma plantilla
-- =====================================================================
-- dsl_plantilla se creó sin más clave única que su id, de modo que nada
-- impedía añadir al mismo jugador dos veces al mismo equipo. En pantalla
-- aparecería duplicado, contaría dos veces para el mínimo de habilitados
-- y su dorsal chocaría consigo mismo.
--
-- POR QUÉ LA CLAVE INCLUYE LA FECHA DE ALTA
--
-- Lo natural sería (inscripción, persona), pero eso impediría un caso
-- legítimo: alguien se va del equipo a mitad de temporada y vuelve más
-- adelante. Con la baja registrada, esa segunda etapa es una fila nueva
-- —es justo lo que permite saber quién estaba habilitado en cada partido—
-- y con la clave estricta no se podría crear.
--
-- Incluyendo la fecha de alta, el alta repetida del mismo día se rechaza
-- —que es el error de captura real— y una vuelta posterior se admite.
--
-- MySQL no tiene índices parciales, así que no se puede expresar
-- «único mientras la baja sea nula», que sería lo ideal. Esta es la
-- aproximación más cercana sin recurrir a un disparador.
-- =====================================================================

SET NAMES utf8mb4;

-- Si ya hubiera duplicados exactos, se conserva el más antiguo.
DELETE PL FROM dsl_plantilla PL
  JOIN (
        SELECT MIN(plantilla_id) AS conservar,
               plantilla_inscripcionid, plantilla_personaid, plantilla_alta
          FROM dsl_plantilla
         GROUP BY plantilla_inscripcionid, plantilla_personaid, plantilla_alta
        HAVING COUNT(*) > 1
       ) G
    ON G.plantilla_inscripcionid = PL.plantilla_inscripcionid
   AND G.plantilla_personaid     = PL.plantilla_personaid
   AND G.plantilla_alta          = PL.plantilla_alta
 WHERE PL.plantilla_id <> G.conservar;


SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_plantilla'
                AND INDEX_NAME='uk_dslpl_persona');

SET @sql := IF(@hay > 0, 'SELECT ''uk_dslpl_persona ya existe'' AS aviso',
    'ALTER TABLE dsl_plantilla ADD UNIQUE KEY uk_dslpl_persona
        (plantilla_inscripcionid, plantilla_personaid, plantilla_alta)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ---------------------------------------------------------------------
-- El dorsal tampoco puede repetirse dentro de un equipo entre quienes
-- siguen en plantilla.
--
-- Aquí el problema de los índices parciales es peor: los que están de
-- baja liberan su dorsal, y una clave única sobre (inscripción, dorsal)
-- se lo seguiría reservando. Se deja fuera del esquema y se comprueba en
-- el servicio, que es donde se conoce la vigencia.
--
-- Un índice normal, al menos, hace barata esa comprobación.
-- ---------------------------------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dsl_plantilla'
                AND INDEX_NAME='ix_dslpl_dorsal');

SET @sql := IF(@hay > 0, 'SELECT ''ix_dslpl_dorsal ya existe'' AS aviso',
    'ALTER TABLE dsl_plantilla ADD KEY ix_dslpl_dorsal
        (plantilla_inscripcionid, plantilla_dorsal, plantilla_baja)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
