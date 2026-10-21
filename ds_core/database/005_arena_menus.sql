-- =====================================================================
-- DigiSports Arena · Menús y acceso al módulo
-- =====================================================================
-- Registra las vistas de Arena en seguridad_menu para que aparezcan en el
-- menú lateral del módulo y en la matriz de permisos de Core.
--
-- Los ids 920+ evitan choque con Basketball (1-99) y Core (900-905).
-- Idempotente.
--
-- Ejecutar dejando que mysql lea el archivo, para no corromper acentos:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/005_arena_menus.sql"
-- =====================================================================

INSERT INTO seguridad_menu
    (menu_id, menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
VALUES
    (920, 'arena', 'Panel',          1, 0, 'N', 'panel',           'nav-icon fas fa-tachometer-alt', 'A'),
    (921, 'arena', 'Instalaciones',  2, 0, 'N', 'instalacionList', 'nav-icon fas fa-warehouse',      'A'),
    (922, 'arena', 'Horarios',       3, 0, 'N', 'horarioList',     'nav-icon fas fa-clock',          'A'),
    (923, 'arena', 'Mantenimiento',  4, 0, 'N', 'bloqueoList',     'nav-icon fas fa-tools',          'A'),
    (924, 'arena', 'Clientes',       5, 0, 'N', 'clienteList',     'nav-icon fas fa-user-friends',   'A'),
    (925, 'arena', 'Reservas',       6, 0, 'N', 'reservaList',     'nav-icon fas fa-calendar-check', 'A'),
    (926, 'arena', 'Monedero',       7, 0, 'N', 'monederoList',    'nav-icon fas fa-wallet',         'A')
ON DUPLICATE KEY UPDATE
    menu_modulo = VALUES(menu_modulo),
    menu_nombre = VALUES(menu_nombre),
    menu_orden  = VALUES(menu_orden),
    menu_vista  = VALUES(menu_vista),
    menu_icono  = VALUES(menu_icono),
    menu_estado = VALUES(menu_estado);

-- Los roles administrativos acceden al módulo. El rol 1 (Super
-- Administrador) no necesita filas de permiso: pasa por encima.
INSERT IGNORE INTO seguridad_rol_modulo (rolmod_rolid, rolmod_modulo, rolmod_estado)
VALUES (1, 'arena', 'A'), (2, 'arena', 'A');

-- El rol 2 (Administrador) recibe acceso completo a las vistas de Arena.
INSERT IGNORE INTO seguridad_permiso
    (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 2, m.menu_id, 'S', 'S', 'S', 'S', 'A'
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'arena';

-- ---------------------------------------------------------------------
-- Verificacion
-- ---------------------------------------------------------------------
SELECT '--- menus de Arena ---' AS info;
SELECT menu_id, menu_nombre, menu_vista, menu_orden
  FROM seguridad_menu WHERE menu_modulo = 'arena' ORDER BY menu_orden;

SELECT '--- acceso a modulos por rol ---' AS info;
SELECT r.rol_id, r.rol_nombre,
       GROUP_CONCAT(rm.rolmod_modulo ORDER BY rm.rolmod_modulo) AS modulos
  FROM seguridad_rol r
  LEFT JOIN seguridad_rol_modulo rm ON rm.rolmod_rolid = r.rol_id AND rm.rolmod_estado = 'A'
 GROUP BY r.rol_id, r.rol_nombre ORDER BY r.rol_id;
