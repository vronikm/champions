-- =====================================================================
-- DigiSports · Seguridad del ecosistema
-- =====================================================================
-- Lleva el modelo de permisos de "un solo sistema" a "varios modulos":
--
--   1. Modulo  -> seguridad_rol_modulo   (que apps ve el rol)
--   2. Vista   -> seguridad_menu.menu_modulo + seguridad_permiso
--   3. Accion  -> permiso_ver / crear / editar / eliminar
--
-- Principio de migracion: NADIE pierde capacidades. Los permisos que hoy
-- existen se convierten en acceso completo (las cuatro acciones), porque
-- hasta ahora tener el menu equivalia a poder todo en esa pantalla. A
-- partir de aqui el administrador restringe desde el modulo Core.
--
-- Idempotente: puede ejecutarse mas de una vez sin efectos adversos.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Cada menu pertenece a un modulo
-- ---------------------------------------------------------------------
SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = 'seguridad_menu'
                   AND column_name  = 'menu_modulo');

SET @sql := IF(@existe = 0,
    'ALTER TABLE seguridad_menu
       ADD COLUMN menu_modulo VARCHAR(20) NOT NULL DEFAULT ''basketball'' AFTER menu_id,
       ADD INDEX idx_menu_modulo (menu_modulo)',
    'SELECT ''seguridad_menu.menu_modulo ya existe'' AS aviso');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 2. Permisos por accion
-- ---------------------------------------------------------------------
SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = 'seguridad_permiso'
                   AND column_name  = 'permiso_ver');

SET @sql := IF(@existe = 0,
    'ALTER TABLE seguridad_permiso
       ADD COLUMN permiso_ver      CHAR(1) NOT NULL DEFAULT ''S'' AFTER permiso_menuid,
       ADD COLUMN permiso_crear    CHAR(1) NOT NULL DEFAULT ''N'' AFTER permiso_ver,
       ADD COLUMN permiso_editar   CHAR(1) NOT NULL DEFAULT ''N'' AFTER permiso_crear,
       ADD COLUMN permiso_eliminar CHAR(1) NOT NULL DEFAULT ''N'' AFTER permiso_editar',
    'SELECT ''seguridad_permiso ya tiene columnas de accion'' AS aviso');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Los permisos preexistentes conservan el alcance que tenian: todo.
UPDATE seguridad_permiso
   SET permiso_ver      = 'S',
       permiso_crear    = 'S',
       permiso_editar   = 'S',
       permiso_eliminar = 'S'
 WHERE permiso_estado = 'A'
   AND permiso_crear  = 'N'
   AND permiso_editar = 'N'
   AND permiso_eliminar = 'N';

-- ---------------------------------------------------------------------
-- 3. Acceso a modulos por rol
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seguridad_rol_modulo (
    rolmod_id     INT AUTO_INCREMENT PRIMARY KEY,
    rolmod_rolid  INT         NOT NULL,
    rolmod_modulo VARCHAR(20) NOT NULL,
    rolmod_estado CHAR(1)     NOT NULL DEFAULT 'A',
    UNIQUE KEY uq_rol_modulo (rolmod_rolid, rolmod_modulo),
    KEY idx_rolmod_rol (rolmod_rolid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Todo rol con permisos vigentes conserva acceso al modulo Basketball.
INSERT IGNORE INTO seguridad_rol_modulo (rolmod_rolid, rolmod_modulo, rolmod_estado)
SELECT DISTINCT p.permiso_rolid, 'basketball', 'A'
  FROM seguridad_permiso p
 WHERE p.permiso_estado = 'A';

-- Los roles administrativos (1 y 2) acceden a Basketball y a Core.
INSERT IGNORE INTO seguridad_rol_modulo (rolmod_rolid, rolmod_modulo, rolmod_estado)
SELECT r.rol_id, m.modulo, 'A'
  FROM seguridad_rol r
  CROSS JOIN (SELECT 'basketball' AS modulo UNION ALL SELECT 'core') m
 WHERE r.rol_id IN (1, 2);

-- ---------------------------------------------------------------------
-- 4. Menus propios del modulo Core
-- ---------------------------------------------------------------------
-- Se insertan con ids altos para no chocar con los de Basketball.
-- Los nombres con tilde se insertan como bytes UTF-8 explicitos: si el
-- cliente mysql se conecta en latin1 (lo hace por defecto en algunos
-- entornos), un literal 'Menús' quedaria doblemente codificado y se veria
-- como "MenÃºs". UNHEX evita depender de la codificacion del cliente.
INSERT INTO seguridad_menu
    (menu_id, menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
VALUES
    (900, 'core', 'Panel',                              1, 0, 'N', 'panel',        'nav-icon fas fa-tachometer-alt', 'A'),
    (901, 'core', 'Usuarios',                           2, 0, 'N', 'usuarioList',  'nav-icon fas fa-users',        'A'),
    (902, 'core', 'Roles',                              3, 0, 'N', 'rolList',      'nav-icon fas fa-user-shield',  'A'),
    (903, 'core', 'Permisos',                           4, 0, 'N', 'permisoRol',   'nav-icon fas fa-key',          'A'),
    (904, 'core', UNHEX('4D656EC3BA73'),                5, 0, 'N', 'menuList',     'nav-icon fas fa-bars',         'A'),  -- Menús
    (905, 'core', UNHEX('4DC3B364756C6F73'),            6, 0, 'N', 'moduloRol',    'nav-icon fas fa-th-large',     'A')   -- Módulos
ON DUPLICATE KEY UPDATE
    menu_modulo = VALUES(menu_modulo),
    menu_nombre = VALUES(menu_nombre),
    menu_vista  = VALUES(menu_vista),
    menu_icono  = VALUES(menu_icono),
    menu_estado = VALUES(menu_estado);

-- El rol 2 (Administrador) recibe acceso completo a las vistas de Core.
-- El rol 1 (Super Administrador) no necesita filas: pasa por encima de todo.
INSERT IGNORE INTO seguridad_permiso
    (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 2, m.menu_id, 'S', 'S', 'S', 'S', 'A'
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'core';

-- ---------------------------------------------------------------------
-- 5. El rol 2 deja de tener acceso implicito
-- ---------------------------------------------------------------------
-- Hasta ahora los roles 1 y 2 pasaban por encima del control de acceso.
-- A partir de aqui SOLO el rol 1 (Super Administrador) lo hace. Para que
-- el rol 2 (Administrador) no pierda nada, se le otorgan explicitamente
-- todas las vistas de Basketball con las cuatro acciones.
INSERT IGNORE INTO seguridad_permiso
    (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 2, m.menu_id, 'S', 'S', 'S', 'S', 'A'
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'basketball'
   AND m.menu_estado = 'A'
   AND m.menu_hijo  <> 'S'
   AND m.menu_vista NOT IN ('', 'No');

-- ---------------------------------------------------------------------
-- Verificacion
-- ---------------------------------------------------------------------
SELECT '--- menus por modulo ---' AS info;
SELECT menu_modulo, COUNT(1) AS menus FROM seguridad_menu GROUP BY menu_modulo;

SELECT '--- acceso a modulos por rol ---' AS info;
SELECT r.rol_id, r.rol_nombre, GROUP_CONCAT(rm.rolmod_modulo ORDER BY rm.rolmod_modulo) AS modulos
  FROM seguridad_rol r
  LEFT JOIN seguridad_rol_modulo rm ON rm.rolmod_rolid = r.rol_id AND rm.rolmod_estado = 'A'
 GROUP BY r.rol_id, r.rol_nombre;

SELECT '--- permisos por accion ---' AS info;
SELECT permiso_rolid,
       COUNT(1)                              AS vistas,
       SUM(permiso_crear    = 'S')           AS con_crear,
       SUM(permiso_editar   = 'S')           AS con_editar,
       SUM(permiso_eliminar = 'S')           AS con_eliminar
  FROM seguridad_permiso
 WHERE permiso_estado = 'A'
 GROUP BY permiso_rolid;
