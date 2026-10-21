-- =====================================================================
-- 021 · Menú de Puntos de emisión en el Core
-- =====================================================================
-- Da entrada de menú a la pantalla que asigna un punto de emisión a cada
-- módulo. Se coloca junto a la configuración del SRI, que es donde el
-- administrador ya busca todo lo tributario.
--
-- El permiso de rol no gobierna esta pantalla: la vista comprueba
-- es_superadministrador() por su cuenta y devuelve 403 a cualquier otro,
-- igual que hace facturacionConfigSri. La fila de permiso se crea de
-- todos modos para el rol 1, para que la pantalla de permisos del Core
-- muestre la opción con su estado real en vez de en blanco.
-- =====================================================================

SET NAMES utf8mb4;

-- El menú se cuelga del mismo padre que la configuración del SRI, sea
-- cual sea en esta instalación: se lee en vez de escribirlo a mano.
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid,
        menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'core',
       'Puntos de emisión',
       COALESCE(M.menu_orden, 90) + 1,
       M.menu_padreid,
       'N',
       'puntoEmisionList',
       'fas fa-hashtag',
       'A'
  FROM seguridad_menu M
 WHERE M.menu_modulo = 'core'
   AND M.menu_vista  = 'facturacionConfigSri'
   AND NOT EXISTS (SELECT 1 FROM seguridad_menu X
                    WHERE X.menu_modulo = 'core'
                      AND X.menu_vista  = 'puntoEmisionList')
 LIMIT 1;


INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver,
        permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 1, M.menu_id, 'S', 'S', 'S', 'N', 'A'
  FROM seguridad_menu M
 WHERE M.menu_modulo = 'core'
   AND M.menu_vista  = 'puntoEmisionList'
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso P
                    WHERE P.permiso_rolid  = 1
                      AND P.permiso_menuid = M.menu_id);
