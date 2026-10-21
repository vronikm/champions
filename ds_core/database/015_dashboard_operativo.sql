-- =====================================================================
-- DigiSports · El Dashboard se abre a los roles operativos
-- =====================================================================
-- Profesor y Asistente no tenían permiso sobre el Dashboard, así que al
-- entrar recibían «Acceso denegado» antes de ver nada.
--
-- Ahora el Dashboard muestra dos bloques distintos según a quién sirve:
--
--   · Operativo  -> horarios a cargo, alumnos y días del mes en que se
--     registró la asistencia. Se muestra a quien tiene ficha de empleado.
--   · Gerencial  -> alumnos, recaudación y mora por sede. Requiere ver el
--     balance del mes (balanceResultados), que es lo que ya distingue a
--     quien mira la caja.
--
-- Por eso basta con conceder LECTURA: el propio Dashboard decide qué
-- enseña. Un profesor no verá recaudación aunque entre.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/015_dashboard_operativo.sql"
-- =====================================================================

SELECT '--- ANTES: quien ve el Dashboard ---' AS info;
SELECT p.permiso_rolid, r.rol_nombre, p.permiso_ver
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'dashboard'
 ORDER BY p.permiso_rolid;

-- ---------------------------------------------------------------------
-- Lectura del Dashboard para Profesor (3) y Asistente (6)
-- ---------------------------------------------------------------------
INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
        permiso_editar, permiso_eliminar, permiso_estado)
SELECT r.rolid, m.menu_id, 'S', 'N', 'N', 'N', 'A'
  FROM seguridad_menu m
  JOIN (SELECT 3 AS rolid UNION ALL SELECT 6) r
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'dashboard'
ON DUPLICATE KEY UPDATE permiso_ver = 'S', permiso_estado = 'A';

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT '--- DESPUES ---' AS info;
SELECT p.permiso_rolid, r.rol_nombre, p.permiso_ver, p.permiso_crear,
       p.permiso_editar, p.permiso_eliminar
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'dashboard'
 ORDER BY p.permiso_rolid;

SELECT '--- quien vera el bloque gerencial (permiso sobre balanceResultados) ---' AS info;
SELECT p.permiso_rolid, r.rol_nombre
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'balanceResultados'
   AND p.permiso_ver = 'S' AND p.permiso_estado = 'A'
 ORDER BY p.permiso_rolid;

SELECT '--- usuarios con ficha de empleado (veran el bloque operativo) ---' AS info;
SELECT u.usuario_usuario, u.usuario_rolid, u.usuario_empleadoid, e.empleado_nombre,
       (SELECT COUNT(DISTINCT d.detalle_horarioid)
          FROM asistencia_horario_detalle d
         WHERE d.detalle_profesorid = u.usuario_empleadoid) AS horarios_propios
  FROM seguridad_usuario u
  LEFT JOIN sujeto_empleado e ON e.empleado_id = u.usuario_empleadoid
 WHERE u.usuario_empleadoid IS NOT NULL AND u.usuario_empleadoid > 0
 ORDER BY u.usuario_rolid;
