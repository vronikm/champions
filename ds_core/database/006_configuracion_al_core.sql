-- =====================================================================
-- DigiSports · La configuración pasa a Core
-- =====================================================================
-- Principio: Core administra lo compartido (sedes, catálogos, usuarios,
-- roles, menús) y los demás módulos sólo lo consumen.
--
-- Movimientos:
--   · Sedes y Catálogos: se registran como vistas de Core.
--   · Usuarios, Roles y Menús: Core ya los tenía; se desactivan los
--     duplicados de Basketball.
--   · Escuela y Tablas: se desactivan en Basketball; su administración
--     queda cubierta por Catálogos y por la ficha de sede.
--
-- Los menús de Basketball se marcan como INACTIVOS en lugar de borrarse:
-- así se conserva el histórico de permisos por si hay que revertir.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/006_configuracion_al_core.sql"
-- =====================================================================

SELECT '--- ANTES: menus de configuracion en Basketball ---' AS info;
SELECT menu_id, menu_modulo, menu_nombre, menu_vista, menu_estado
  FROM seguridad_menu
 WHERE menu_vista IN ('sedeList','escuelaNew','tablasNew','catalogosNew','userList','roList','userMenu')
 ORDER BY menu_id;

-- ---------------------------------------------------------------------
-- 1. Nuevas vistas de configuración en Core
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
    (menu_id, menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
VALUES
    (906, 'core', 'Sedes',     7, 0, 'N', 'sedeList',     'nav-icon fas fa-map-marker-alt', 'A'),
    (907, 'core', UNHEX('436174C3A16C6F676F73'), 8, 0, 'N', 'catalogoList', 'nav-icon fas fa-list-ul', 'A')  -- Catálogos
ON DUPLICATE KEY UPDATE
    menu_modulo = VALUES(menu_modulo),
    menu_nombre = VALUES(menu_nombre),
    menu_orden  = VALUES(menu_orden),
    menu_vista  = VALUES(menu_vista),
    menu_icono  = VALUES(menu_icono),
    menu_estado = 'A';

-- El rol 2 (Administrador) recibe acceso completo a las vistas nuevas.
INSERT IGNORE INTO seguridad_permiso
    (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 2, m.menu_id, 'S', 'S', 'S', 'S', 'A'
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'core' AND m.menu_vista IN ('sedeList', 'catalogoList');

-- ---------------------------------------------------------------------
-- 2. Se retiran de Basketball las pantallas de configuración
-- ---------------------------------------------------------------------
-- Quedan inactivas: desaparecen del menú y del control de acceso, pero la
-- fila se conserva junto con sus permisos históricos.
UPDATE seguridad_menu
   SET menu_estado = 'I'
 WHERE menu_modulo = 'basketball'
   AND menu_vista IN ('sedeList', 'escuelaNew', 'tablasNew', 'catalogosNew',
                      'userList', 'roList', 'userMenu');

-- ---------------------------------------------------------------------
-- Verificacion
-- ---------------------------------------------------------------------
SELECT '--- DESPUES: menus de Core ---' AS info;
SELECT menu_orden AS orden, menu_nombre, menu_vista
  FROM seguridad_menu WHERE menu_modulo = 'core' AND menu_estado = 'A'
 ORDER BY menu_orden;

SELECT '--- Basketball: configuracion retirada ---' AS info;
SELECT menu_nombre, menu_vista, menu_estado
  FROM seguridad_menu
 WHERE menu_modulo = 'basketball'
   AND menu_vista IN ('sedeList','escuelaNew','tablasNew','catalogosNew','userList','roList','userMenu')
 ORDER BY menu_nombre;

SELECT '--- menus activos por modulo ---' AS info;
SELECT menu_modulo, COUNT(1) AS activos
  FROM seguridad_menu WHERE menu_estado = 'A'
 GROUP BY menu_modulo ORDER BY menu_modulo;
