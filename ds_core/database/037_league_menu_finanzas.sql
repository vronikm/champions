-- =====================================================================
-- 037 · League · Menú y permisos de finanzas
-- =====================================================================
-- El módulo corre en modo estricto (DS_PERMISOS_ESTRICTOS), así que una
-- vista sólo existe si está en las TRES listas: config/vistas.php, el
-- archivo views/x-view.php y esta tabla. Faltando cualquiera, la pantalla
-- se deniega en lugar de mostrarse a medias.
--
-- Ambas entradas van visibles ('A'): son pantallas de menú, no vistas de
-- apoyo a las que se llega desde otra.
--
-- NO SE CONCEDE EL PERMISO A NINGÚN ROL SALVO AL SUPERADMINISTRADOR
--
-- Quién cobra es una decisión de la organización, no del instalador. El
-- rol 1 lo recibe para que la opción quede administrable desde Core; el
-- resto se otorga desde la pantalla de permisos, que es donde queda
-- registrado quién lo dio.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Cobranza
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid,
        menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'league', 'Cobranza', 120, NULL, 'N', 'cobranzaPanel',
       'fas fa-file-invoice-dollar', 'A'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu
                    WHERE menu_modulo = 'league' AND menu_vista = 'cobranzaPanel');


-- ---------------------------------------------------------------------
-- Conceptos cobrables
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid,
        menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'league', 'Conceptos cobrables', 130, NULL, 'N', 'conceptoList',
       'fas fa-tags', 'A'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu
                    WHERE menu_modulo = 'league' AND menu_vista = 'conceptoList');


-- ---------------------------------------------------------------------
-- Permiso del superadministrador sobre ambas.
-- ---------------------------------------------------------------------
INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver,
        permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 1, M.menu_id, 'S', 'S', 'S', 'S', 'A'
  FROM seguridad_menu M
 WHERE M.menu_modulo = 'league'
   AND M.menu_vista IN ('cobranzaPanel', 'conceptoList')
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso P
                    WHERE P.permiso_rolid  = 1
                      AND P.permiso_menuid = M.menu_id);
