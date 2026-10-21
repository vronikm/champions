-- =====================================================================
-- DigiSports · Integridad de seguridad_permiso
-- =====================================================================
-- La tabla no tenia clave unica sobre (rol, menu), asi que un mismo rol
-- podia acumular varias filas para la misma vista con acciones distintas.
-- El permiso efectivo quedaba entonces a merced del orden de lectura.
--
-- Se consolidan los duplicados quedandose con la fila MAS PERMISIVA (para
-- no retirar accesos que alguien ya tuviera) y se anade la restriccion.
--
-- Idempotente.
-- =====================================================================

SELECT '--- duplicados ANTES ---' AS info;
SELECT COUNT(1) AS grupos_duplicados FROM (
    SELECT permiso_rolid, permiso_menuid
      FROM seguridad_permiso
     GROUP BY permiso_rolid, permiso_menuid
    HAVING COUNT(1) > 1
) d;

-- ---------------------------------------------------------------------
-- 1. Consolidar: la fila superviviente absorbe la union de las acciones
-- ---------------------------------------------------------------------
CREATE TEMPORARY TABLE tmp_permiso_union AS
SELECT MIN(permiso_id)          AS conservar,
       permiso_rolid,
       permiso_menuid,
       MAX(permiso_ver      = 'S') AS ver,
       MAX(permiso_crear    = 'S') AS crear,
       MAX(permiso_editar   = 'S') AS editar,
       MAX(permiso_eliminar = 'S') AS eliminar,
       MAX(permiso_estado   = 'A') AS activo
  FROM seguridad_permiso
 GROUP BY permiso_rolid, permiso_menuid;

UPDATE seguridad_permiso p
  JOIN tmp_permiso_union u ON u.conservar = p.permiso_id
   SET p.permiso_ver      = IF(u.ver      = 1, 'S', 'N'),
       p.permiso_crear    = IF(u.crear    = 1, 'S', 'N'),
       p.permiso_editar   = IF(u.editar   = 1, 'S', 'N'),
       p.permiso_eliminar = IF(u.eliminar = 1, 'S', 'N'),
       p.permiso_estado   = IF(u.activo   = 1, 'A', 'I');

-- ---------------------------------------------------------------------
-- 2. Eliminar las filas sobrantes
-- ---------------------------------------------------------------------
DELETE p
  FROM seguridad_permiso p
  LEFT JOIN tmp_permiso_union u ON u.conservar = p.permiso_id
 WHERE u.conservar IS NULL;

DROP TEMPORARY TABLE tmp_permiso_union;

-- ---------------------------------------------------------------------
-- 3. Restriccion que impide que vuelva a ocurrir
-- ---------------------------------------------------------------------
SET @existe := (SELECT COUNT(1) FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name   = 'seguridad_permiso'
                   AND index_name   = 'uq_permiso_rol_menu');

SET @sql := IF(@existe = 0,
    'ALTER TABLE seguridad_permiso
       ADD UNIQUE KEY uq_permiso_rol_menu (permiso_rolid, permiso_menuid)',
    'SELECT ''uq_permiso_rol_menu ya existe'' AS aviso');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- Verificacion
-- ---------------------------------------------------------------------
SELECT '--- duplicados DESPUES ---' AS info;
SELECT COUNT(1) AS grupos_duplicados FROM (
    SELECT permiso_rolid, permiso_menuid
      FROM seguridad_permiso
     GROUP BY permiso_rolid, permiso_menuid
    HAVING COUNT(1) > 1
) d;

SELECT '--- permisos por rol ---' AS info;
SELECT permiso_rolid,
       COUNT(1)                    AS vistas,
       SUM(permiso_crear    = 'S') AS con_crear,
       SUM(permiso_editar   = 'S') AS con_editar,
       SUM(permiso_eliminar = 'S') AS con_eliminar
  FROM seguridad_permiso
 WHERE permiso_estado = 'A'
 GROUP BY permiso_rolid;
