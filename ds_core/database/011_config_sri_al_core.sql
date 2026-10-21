-- =====================================================================
-- DigiSports · La configuración del SRI pasa a Core
-- =====================================================================
-- Principio ya establecido: Core administra la configuración y los demás
-- módulos la consumen. La configuración de facturación electrónica —RUC
-- emisor, ambiente, certificado de firma— define en nombre de quién se
-- emiten comprobantes con validez tributaria, así que:
--
--   · La pantalla se traslada al módulo Core (facturacionConfigSri).
--   · Sólo el superadministrador entra, sin importar los permisos del rol.
--   · Emitir facturas sigue siendo un permiso de rol sobre facturasList,
--     en Basketball.
--
-- Además se normalizan las cabeceras de grupo: hasta ahora llevaban el
-- relleno 'No' en menu_vista. Ahora Core puede crear y editar grupos, y
-- para esos el campo va vacío.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/011_config_sri_al_core.sql"
-- =====================================================================

SELECT '--- ANTES ---' AS info;
SELECT menu_id, menu_modulo, menu_nombre, menu_vista, menu_padreid, menu_hijo
  FROM seguridad_menu
 WHERE menu_vista IN ('facturacionConfig', 'facturacionConfigSri')
    OR menu_hijo = 'S'
 ORDER BY menu_modulo, menu_orden;

-- ---------------------------------------------------------------------
-- 1. Fuera de Basketball
-- ---------------------------------------------------------------------
-- Se borran también los permisos concedidos: la vista ya no existe en
-- ese módulo y dejarlos sería ruido con apariencia de acceso.
DELETE p FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'facturacionConfig';

DELETE FROM seguridad_menu
 WHERE menu_modulo = 'basketball' AND menu_vista = 'facturacionConfig';

-- ---------------------------------------------------------------------
-- 2. Dentro de Core
-- ---------------------------------------------------------------------
-- Sin permisos asociados a propósito: la vista se defiende sola con
-- es_superadministrador(), y el superadministrador no usa esta tabla.
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'core', 'Facturación electrónica', 60, 0, 'N',
       'facturacionConfigSri', 'nav-icon fas fa-file-invoice-dollar', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'core'
                      AND M.menu_vista  = 'facturacionConfigSri');

-- ---------------------------------------------------------------------
-- 2b. Organización: la pantalla existía sin entrada de menú
-- ---------------------------------------------------------------------
-- Se llegaba a ella sólo escribiendo la URL. Se registra y se reordena
-- el bloque de configuración para que siga una secuencia lógica:
-- primero quién es la organización, luego sus sedes y sus catálogos.
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'core', 'Organización', 7, 0, 'N',
       'organizacionForm', 'nav-icon fas fa-landmark', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'core'
                      AND M.menu_vista  = 'organizacionForm');

UPDATE seguridad_menu SET menu_orden = 8  WHERE menu_modulo='core' AND menu_vista='sedeList';
UPDATE seguridad_menu SET menu_orden = 9  WHERE menu_modulo='core' AND menu_vista='catalogoList';
UPDATE seguridad_menu SET menu_orden = 10 WHERE menu_modulo='core' AND menu_vista='facturacionConfigSri';

-- ---------------------------------------------------------------------
-- 2c. El grupo «Facturación electrónica» se queda con una sola entrada
-- ---------------------------------------------------------------------
-- Al llevarse la configuración, agrupar una única entrada sólo añade un
-- desplegable de más: «Facturas emitidas» sube a primer nivel.
-- El nombre lleva el apellido «electrónica» a propósito: en Reportes ya
-- hay una «Facturación» (reporteRepresentanteFactura) y dos entradas con
-- el mismo texto en el mismo menú se confunden.
UPDATE seguridad_menu
   SET menu_padreid = 0, menu_orden = 17, menu_nombre = 'Facturación electrónica'
 WHERE menu_modulo = 'basketball' AND menu_vista = 'facturasList';

DELETE FROM seguridad_menu
 WHERE menu_modulo = 'basketball'
   AND menu_hijo   = 'S'
   AND menu_nombre = 'Facturación electrónica'
   AND NOT EXISTS (SELECT 1 FROM (SELECT menu_padreid FROM seguridad_menu) H
                    WHERE H.menu_padreid = seguridad_menu.menu_id);

-- ---------------------------------------------------------------------
-- 3. Cabeceras de grupo: menu_vista vacío en lugar del relleno 'No'
-- ---------------------------------------------------------------------
UPDATE seguridad_menu
   SET menu_vista = ''
 WHERE menu_hijo = 'S' AND menu_padreid = 0
   AND UPPER(menu_vista) IN ('NO', 'N/A', '-', '#');

-- Y al revés: cualquier menú con hijos que no estuviera marcado como
-- grupo. Core ahora depende de menu_hijo para ofrecer los grupos.
UPDATE seguridad_menu m
   SET m.menu_hijo = 'S'
 WHERE m.menu_padreid = 0
   AND m.menu_hijo <> 'S'
   AND EXISTS (SELECT 1 FROM (SELECT menu_padreid FROM seguridad_menu) H
                WHERE H.menu_padreid = m.menu_id);

-- ---------------------------------------------------------------------
-- 4. Comprobación
-- ---------------------------------------------------------------------
SELECT '--- DESPUES: menus de Core ---' AS info;
SELECT menu_id, menu_orden, menu_nombre, menu_vista, menu_estado
  FROM seguridad_menu
 WHERE menu_modulo = 'core'
 ORDER BY menu_orden;

SELECT '--- DESPUES: cabeceras de grupo ---' AS info;
SELECT menu_id, menu_modulo, menu_nombre, CONCAT('[', menu_vista, ']') AS vista, menu_hijo
  FROM seguridad_menu
 WHERE menu_hijo = 'S'
 ORDER BY menu_modulo, menu_orden;

SELECT '--- facturacionConfig ya no debe aparecer ---' AS info;
SELECT COUNT(*) AS restantes
  FROM seguridad_menu WHERE menu_vista = 'facturacionConfig';
