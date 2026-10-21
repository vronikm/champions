-- =====================================================================
-- DigiSports · La configuración de carnets pasa a Core
-- =====================================================================
-- Mismo criterio que la facturación electrónica (migración 011): lo que
-- es configuración del sistema se administra en Core y sólo lo toca el
-- superadministrador; lo que es operación diaria se concede por rol.
--
--   · Configuración de carnets  -> Core / carnetConfig, superadministrador.
--   · Imprimir carnets del mes  -> Basketball / carnetList, por permisos.
--
-- El motivo de restringirla: el color de un mes queda bloqueado en cuanto
-- se emite el primer carnet, porque cambiarlo dejaría en circulación
-- carnets de un color que ya no coincide con el del sistema.
--
-- Al llevarse la configuración, el grupo «Carnets» de Basketball se queda
-- con una sola entrada, así que se aplana igual que se hizo con el de
-- facturación.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/012_config_carnets_al_core.sql"
-- =====================================================================

SELECT '--- ANTES ---' AS info;
SELECT menu_id, menu_modulo, menu_orden, menu_nombre, menu_vista, menu_padreid, menu_hijo
  FROM seguridad_menu
 WHERE menu_vista IN ('carnetConf', 'carnetConfig', 'carnetList')
    OR (menu_modulo = 'basketball' AND menu_nombre = 'Carnets')
 ORDER BY menu_modulo, menu_orden;

-- ---------------------------------------------------------------------
-- 1. Fuera de Basketball
-- ---------------------------------------------------------------------
DELETE p FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'carnetConf';

DELETE FROM seguridad_menu
 WHERE menu_modulo = 'basketball' AND menu_vista = 'carnetConf';

-- ---------------------------------------------------------------------
-- 2. Dentro de Core
-- ---------------------------------------------------------------------
-- Sin permisos asociados a propósito: la vista se defiende sola con
-- es_superadministrador(), y el superadministrador no usa esta tabla.
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'core', 'Carnets', 11, 0, 'N',
       'carnetConfig', 'nav-icon far fa-address-card', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'core'
                      AND M.menu_vista  = 'carnetConfig');

-- ---------------------------------------------------------------------
-- 3. El grupo «Carnets» de Basketball se queda con una sola entrada
-- ---------------------------------------------------------------------
UPDATE seguridad_menu
   SET menu_padreid = 0, menu_orden = 18, menu_nombre = 'Carnets del mes'
 WHERE menu_modulo = 'basketball' AND menu_vista = 'carnetList';

DELETE FROM seguridad_menu
 WHERE menu_modulo = 'basketball'
   AND menu_hijo   = 'S'
   AND menu_nombre = 'Carnets'
   AND NOT EXISTS (SELECT 1 FROM (SELECT menu_padreid FROM seguridad_menu) H
                    WHERE H.menu_padreid = seguridad_menu.menu_id);

-- ---------------------------------------------------------------------
-- 4. Comprobación
-- ---------------------------------------------------------------------
SELECT '--- DESPUES: menus de Core ---' AS info;
SELECT menu_id, menu_orden, menu_nombre, menu_vista, menu_estado
  FROM seguridad_menu
 WHERE menu_modulo = 'core'
 ORDER BY menu_orden;

SELECT '--- DESPUES: primer nivel de Basketball ---' AS info;
SELECT menu_id, menu_orden, menu_nombre, CONCAT('[', menu_vista, ']') AS vista, menu_hijo
  FROM seguridad_menu
 WHERE menu_modulo = 'basketball' AND menu_padreid = 0 AND menu_estado = 'A'
 ORDER BY menu_orden;

SELECT '--- carnetConf ya no debe aparecer ---' AS info;
SELECT COUNT(*) AS restantes FROM seguridad_menu WHERE menu_vista = 'carnetConf';
