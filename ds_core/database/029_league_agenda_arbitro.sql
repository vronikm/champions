-- =====================================================================
-- 029 · League · Agenda del árbitro y perfiles de campo
-- =====================================================================
-- Registra la vista «Mis partidos» y crea los dos roles que el encargo
-- pedía: Árbitro y Oficial de mesa.
--
-- EL ALCANCE POR FILA NO SE CONCEDE AQUÍ, SE DERIVA
--
-- A estos roles NO se les da permiso sobre categoriaPanel, que es la
-- pantalla que gestiona la competencia entera. Se les da sólo sobre
-- partidoAgenda, cuya consulta filtra por la designación de quien la
-- abre.
--
-- Con eso quedan limitados a sus propios partidos sin que ninguna línea
-- de código pregunte «¿es árbitro?». Si mañana el rol se llama de otra
-- forma, o se crea un tercero con el mismo alcance, no hay nada que
-- tocar: basta con concederle esta vista y no la otra. Es lo que pedía el
-- encargo al prohibir atar funcionalidades a nombres de rol.
--
-- El servidor no confía en esta configuración por sí sola:
-- guardarResultado() comprueba permiso de gestión O designación en el
-- partido concreto antes de aceptar un marcador, de modo que ocultar un
-- formulario no es la defensa.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- La vista
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid,
        menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'league', 'Mis partidos', 15, NULL, 'N', 'partidoAgenda', 'fas fa-clipboard-check', 'A'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu
                    WHERE menu_modulo = 'league' AND menu_vista = 'partidoAgenda');


-- ---------------------------------------------------------------------
-- Los perfiles de campo
-- ---------------------------------------------------------------------
INSERT INTO seguridad_rol (rol_nombre, rol_estado)
SELECT * FROM (SELECT 'Árbitro' n, 'A' e
         UNION SELECT 'Oficial de mesa', 'A') T
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_rol R WHERE R.rol_nombre = T.n);


-- Acceso al módulo League, y sólo a ése.
INSERT INTO seguridad_rol_modulo (rolmod_rolid, rolmod_modulo, rolmod_estado)
SELECT R.rol_id, 'league', 'A'
  FROM seguridad_rol R
 WHERE R.rol_nombre IN ('Árbitro', 'Oficial de mesa')
   AND NOT EXISTS (SELECT 1 FROM seguridad_rol_modulo M
                    WHERE M.rolmod_rolid  = R.rol_id
                      AND M.rolmod_modulo = 'league');


-- ---------------------------------------------------------------------
-- Permiso: SÓLO sobre la agenda propia.
--
-- Ver y editar —necesita cargar el resultado de sus partidos—, pero ni
-- crear ni eliminar. Y ninguna fila para categoriaPanel: es lo que lo
-- deja fuera de la gestión de la competencia.
-- ---------------------------------------------------------------------
INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver,
        permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT R.rol_id, M.menu_id, 'S', 'N', 'S', 'N', 'A'
  FROM seguridad_rol R
  JOIN seguridad_menu M ON M.menu_modulo = 'league' AND M.menu_vista = 'partidoAgenda'
 WHERE R.rol_nombre IN ('Árbitro', 'Oficial de mesa')
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso P
                    WHERE P.permiso_rolid  = R.rol_id
                      AND P.permiso_menuid = M.menu_id);


-- El superadministrador, para que la opción aparezca administrable.
INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver,
        permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 1, M.menu_id, 'S', 'S', 'S', 'S', 'A'
  FROM seguridad_menu M
 WHERE M.menu_modulo = 'league' AND M.menu_vista = 'partidoAgenda'
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso P
                    WHERE P.permiso_rolid = 1 AND P.permiso_menuid = M.menu_id);


-- ---------------------------------------------------------------------
-- Comprobación: estos roles NO deben tener acceso a la gestión.
-- Si esta consulta devuelve filas, el aislamiento está roto.
-- ---------------------------------------------------------------------
SELECT R.rol_nombre, M.menu_vista, 'NO DEBERÍA TENER ESTE PERMISO' AS aviso
  FROM seguridad_permiso P
  JOIN seguridad_rol  R ON R.rol_id  = P.permiso_rolid
  JOIN seguridad_menu M ON M.menu_id = P.permiso_menuid
 WHERE R.rol_nombre IN ('Árbitro', 'Oficial de mesa')
   AND M.menu_vista <> 'partidoAgenda';
