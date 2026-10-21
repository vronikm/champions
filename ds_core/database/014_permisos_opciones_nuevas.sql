-- =====================================================================
-- DigiSports · Permisos para las opciones publicadas en la migración 010
-- =====================================================================
-- Las cuatro funcionalidades que se registraron en el menú de Basketball
-- quedaron sin permisos, así que sólo las veía el superadministrador.
-- Aquí se conceden a los roles que decidió la escuela:
--
--   Facturación electrónica  -> Administrador, Gerente, Asistente
--   Cumpleaños               -> Administrador, Asistente
--   Carnets del mes          -> Administrador, Asistente
--   Generar enlaces          -> Administrador, Asistente
--
-- El alcance se ajusta a lo que hace cada pantalla:
--   · Cumpleaños es un listado sin operaciones: sólo lectura.
--   · Facturación y Carnets escriben (emitir, imprimir, reimprimir):
--     lectura, creación y edición. Eliminar se deja fuera a propósito.
--   · Generar enlaces sólo crea: lectura y creación.
--
-- Ninguno recibe permiso de borrado: si hace falta, se concede después
-- desde Core -> Permisos, que es donde debe decidirse.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/014_permisos_opciones_nuevas.sql"
-- =====================================================================

SELECT '--- ANTES: quien ve cada opcion ---' AS info;
SELECT m.menu_vista,
       IFNULL(GROUP_CONCAT(p.permiso_rolid ORDER BY p.permiso_rolid), '(solo superadmin)') AS roles
  FROM seguridad_menu m
  LEFT JOIN seguridad_permiso p ON p.permiso_menuid = m.menu_id
                               AND p.permiso_ver = 'S' AND p.permiso_estado = 'A'
 WHERE m.menu_modulo = 'basketball'
   AND m.menu_vista IN ('facturasList','cumpleaniosList','carnetList','inscripcionEnlace')
 GROUP BY m.menu_vista;

-- ---------------------------------------------------------------------
-- Concesión
-- ---------------------------------------------------------------------
-- La clave única (permiso_rolid, permiso_menuid) que añadió la migración
-- 002 permite repetir esta ejecución sin duplicar filas.

INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
        permiso_editar, permiso_eliminar, permiso_estado)
SELECT r.rolid, m.menu_id, a.ver, a.crear, a.editar, 'N', 'A'
  FROM (
        SELECT 'facturasList'      AS vista, 'S' ver, 'S' crear, 'S' editar
        UNION ALL SELECT 'cumpleaniosList',   'S', 'N', 'N'
        UNION ALL SELECT 'carnetList',        'S', 'S', 'S'
        UNION ALL SELECT 'inscripcionEnlace', 'S', 'S', 'N'
       ) a
  JOIN seguridad_menu m
       ON m.menu_modulo = 'basketball' AND m.menu_vista = a.vista
  JOIN (
        SELECT 2 AS rolid, 'facturasList'      AS vista
        UNION ALL SELECT 5, 'facturasList'
        UNION ALL SELECT 6, 'facturasList'
        UNION ALL SELECT 2, 'cumpleaniosList'
        UNION ALL SELECT 6, 'cumpleaniosList'
        UNION ALL SELECT 2, 'carnetList'
        UNION ALL SELECT 6, 'carnetList'
        UNION ALL SELECT 2, 'inscripcionEnlace'
        UNION ALL SELECT 6, 'inscripcionEnlace'
       ) r ON r.vista = a.vista
ON DUPLICATE KEY UPDATE
       permiso_ver    = VALUES(permiso_ver),
       permiso_crear  = VALUES(permiso_crear),
       permiso_editar = VALUES(permiso_editar),
       permiso_estado = 'A';

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT '--- DESPUES ---' AS info;
SELECT m.menu_nombre, m.menu_vista, p.permiso_rolid, r.rol_nombre,
       p.permiso_ver, p.permiso_crear, p.permiso_editar, p.permiso_eliminar
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_modulo = 'basketball'
   AND m.menu_vista IN ('facturasList','cumpleaniosList','carnetList','inscripcionEnlace')
 ORDER BY m.menu_vista, p.permiso_rolid;

SELECT '--- opciones que siguen solo para el superadministrador ---' AS info;
SELECT m.menu_nombre, m.menu_vista
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'basketball' AND m.menu_estado = 'A' AND m.menu_vista <> ''
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso p
                    WHERE p.permiso_menuid = m.menu_id AND p.permiso_ver = 'S')
 ORDER BY m.menu_orden;
