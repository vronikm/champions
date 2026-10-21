-- =====================================================================
-- 027 · League · Menús de la fase 1
-- =====================================================================
-- Registra las cinco pantallas nuevas. En este módulo no es un trámite
-- cosmético: DS_PERMISOS_ESTRICTOS hace que una vista sin fila aquí
-- devuelva 403 aunque el archivo exista y esté en la lista blanca.
--
-- categoriaPanel se registra como OCULTA ('O').
--
-- Es la pantalla de trabajo de una categoría concreta y se abre siempre
-- desde el listado con un id en la URL; como entrada de menú no llevaría
-- a ninguna parte útil. Pero necesita estar registrada, porque de sus
-- permisos dependen la inscripción de equipos, la generación del
-- calendario y la carga de resultados. El estado 'O' resuelve exactamente
-- eso: sujeta a permiso, fuera de la barra lateral.
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid,
        menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT * FROM (
        SELECT 'league' m, 'Temporadas'  n, 20 o, NULL p, 'N' hj, 'temporadaList'  v, 'fas fa-calendar-alt' i, 'A' e
  UNION SELECT 'league', 'Torneos',        30, NULL, 'N', 'torneoList',     'fas fa-trophy',       'A'
  UNION SELECT 'league', 'Categorías',     40, NULL, 'N', 'categoriaList',  'fas fa-layer-group',  'A'
  UNION SELECT 'league', 'Equipos',        50, NULL, 'N', 'equipoList',     'fas fa-users',        'A'
  -- Oculta: sujeta a permiso, sin entrada en la barra lateral.
  UNION SELECT 'league', 'Panel de categoría', 60, NULL, 'N', 'categoriaPanel', 'fas fa-list-ol',  'O'
) T
WHERE NOT EXISTS (
    SELECT 1 FROM seguridad_menu X
     WHERE X.menu_modulo = T.m AND X.menu_vista = T.v
);


-- ---------------------------------------------------------------------
-- Permisos del superadministrador.
--
-- es_superadministrador() ya devuelve true sin consultar esta tabla, así
-- que estas filas no amplían su acceso. Se crean para que la pantalla de
-- permisos del Core muestre las opciones con su estado real, que es lo
-- que hace falta para poder concedérselas a otro rol.
-- ---------------------------------------------------------------------
INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver,
        permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 1, M.menu_id, 'S', 'S', 'S', 'S', 'A'
  FROM seguridad_menu M
 WHERE M.menu_modulo = 'league'
   AND M.menu_vista IN ('temporadaList','torneoList','categoriaList',
                        'equipoList','categoriaPanel')
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso P
                    WHERE P.permiso_rolid  = 1
                      AND P.permiso_menuid = M.menu_id);
