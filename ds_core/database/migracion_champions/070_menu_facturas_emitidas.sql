-- =====================================================================
-- 070 · Menú «Facturación»: emitir facturas y facturas emitidas
-- =====================================================================
-- QUÉ RESUELVE
--
-- Fútbol tenía una sola entrada, «Facturación electrónica» (facturasList),
-- para elegir al alumno y emitir. No había dónde ver las facturas ya
-- emitidas, su estado en el SRI ni volver a enviarlas o consultarlas.
--
-- Ahora es un grupo «Facturación» con dos opciones:
--
--   Emitir facturas    facturasList       (la entrada de siempre: conserva
--                                          su menu_id y sus permisos)
--   Facturas emitidas  facturasEmitidas   (nueva; nace con los mismos
--                                          permisos que facturasList)
--
-- La configuración del SRI sigue en Core › Facturación electrónica.
--
-- ES REPETIBLE: cada paso comprueba si ya está hecho.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 070_menu_facturas_emitidas.sql

SET NAMES utf8mb4;

-- 1. El grupo, en el lugar que ocupaba la entrada.
INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Facturación', m.menu_orden, 0, 'S', '', 'nav-icon fas fa-file-invoice-dollar', 'A'
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'facturasList'
   AND NOT EXISTS (SELECT 1 FROM seguridad_menu g
                    WHERE g.menu_modulo = 'basketball' AND g.menu_padreid = 0
                      AND g.menu_hijo = 'S' AND g.menu_nombre = 'Facturación')
 LIMIT 1;

SET @grupo := (SELECT menu_id FROM seguridad_menu
                WHERE menu_modulo = 'basketball' AND menu_padreid = 0
                  AND menu_hijo = 'S' AND menu_nombre = 'Facturación'
                ORDER BY menu_id LIMIT 1);

-- 2. La entrada de siempre pasa al grupo (mismo id: los permisos siguen valiendo).
UPDATE seguridad_menu
   SET menu_padreid = @grupo, menu_orden = 1, menu_nombre = 'Emitir facturas',
       menu_icono = 'nav-icon far fa-circle text-info'
 WHERE menu_modulo = 'basketball' AND menu_vista = 'facturasList' AND @grupo IS NOT NULL;

-- 3. La vista nueva.
INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Facturas emitidas', 2, @grupo, 'N', 'facturasEmitidas', 'nav-icon far fa-circle text-info', 'A'
  FROM DUAL
 WHERE @grupo IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM seguridad_menu WHERE menu_modulo = 'basketball' AND menu_vista = 'facturasEmitidas');

SET @emitidas := (SELECT menu_id FROM seguridad_menu
                   WHERE menu_modulo = 'basketball' AND menu_vista = 'facturasEmitidas' LIMIT 1);

-- 4. Quien podía emitir, puede ver lo emitido (los mismos permisos).
INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
                               permiso_eliminar, permiso_exportar, permiso_estado)
SELECT p.permiso_rolid, @emitidas, p.permiso_ver, p.permiso_crear, p.permiso_editar,
       p.permiso_eliminar, p.permiso_exportar, p.permiso_estado
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'facturasList'
   AND @emitidas IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso q
                    WHERE q.permiso_rolid = p.permiso_rolid AND q.permiso_menuid = @emitidas);

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT m.menu_id, m.menu_nombre, m.menu_orden, m.menu_padreid, m.menu_vista,
       (SELECT COUNT(*) FROM seguridad_permiso p WHERE p.permiso_menuid = m.menu_id) AS permisos
  FROM seguridad_menu m
 WHERE m.menu_id = @grupo OR m.menu_padreid = @grupo
 ORDER BY m.menu_padreid, m.menu_orden;

-- Para revertir:
-- DELETE FROM seguridad_permiso WHERE permiso_menuid = (SELECT menu_id FROM seguridad_menu WHERE menu_vista = 'facturasEmitidas');
-- DELETE FROM seguridad_menu WHERE menu_vista = 'facturasEmitidas';
-- UPDATE seguridad_menu SET menu_padreid = 0, menu_orden = 17, menu_nombre = 'Facturación electrónica',
--        menu_icono = 'nav-icon fas fa-file-invoice' WHERE menu_vista = 'facturasList';
-- DELETE FROM seguridad_menu WHERE menu_padreid = 0 AND menu_hijo = 'S' AND menu_nombre = 'Facturación';
