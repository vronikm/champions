-- =====================================================================
-- 091 · DigiCheck: alta del módulo en el menú y los permisos
-- =====================================================================
-- QUÉ RESUELVE
--
-- DigiCheck (ds_check/) tiene DS_PERMISOS_ESTRICTOS = true: una vista que
-- no esté en seguridad_menu se DENIEGA. Sin estas filas el módulo no
-- dejaría entrar a nadie salvo al Super Administrador.
--
--   escanear   la pantalla de la cancha (escaneo del QR, sin conexión)
--   carnets    los carnets con su QR (ver, guardar imagen, imprimir)
--
--
-- A QUIÉN SE LE DA ACCESO, Y POR QUÉ
--
-- A nadie nuevo: a los mismos que ya hacen eso en Fútbol.
--
--   escanear → los roles con «ver» sobre la toma de asistencia de Fútbol
--              (basketball/asistencia). Las marcas de DigiCheck se envían
--              por ese mismo camino y con ese mismo permiso
--              (asistenciaOfflineAjax.php): darlo a otro rol no le
--              serviría de nada.
--   carnets  → los roles con «ver» sobre el listado de alumnos
--              (basketball/alumnoList): el carnet muestra lo mismo
--              (nombre, cédula, categoría, sede).
--
-- Y el módulo «check» (seguridad_rol_modulo) a los roles que reciben
-- alguna de las dos. Desde Core › Permisos se ajusta después.
--
--
-- SE PUEDE REPETIR
--
-- El menú se inserta sólo si falta; el módulo y los permisos llevan NOT
-- EXISTS. Común a los cuatro sistemas (ds_core/database).
-- =====================================================================

SET NAMES utf8mb4;

/*----------  Las dos vistas en el menú  ----------*/
INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'check', 'Escanear', 1, 0, 'N', 'escanear', 'nav-icon fas fa-qrcode', 'A'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu WHERE menu_modulo = 'check' AND menu_vista = 'escanear');

INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'check', 'Carnets', 2, 0, 'N', 'carnets', 'nav-icon fas fa-id-card', 'A'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu WHERE menu_modulo = 'check' AND menu_vista = 'carnets');

/*----------  Permisos: los de Fútbol que ya hacen eso  ----------*/
SET @m_escanear = (SELECT menu_id FROM seguridad_menu WHERE menu_modulo = 'check' AND menu_vista = 'escanear' LIMIT 1);
SET @m_carnets  = (SELECT menu_id FROM seguridad_menu WHERE menu_modulo = 'check' AND menu_vista = 'carnets'  LIMIT 1);

INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
SELECT DISTINCT p.permiso_rolid, @m_escanear, 'S', 'S', 'N', 'N', 'N', 'A'
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'asistencia'
   AND p.permiso_ver = 'S' AND p.permiso_estado = 'A'
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso x WHERE x.permiso_rolid = p.permiso_rolid AND x.permiso_menuid = @m_escanear);

INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
SELECT DISTINCT p.permiso_rolid, @m_carnets, 'S', 'N', 'N', 'N', 'N', 'A'
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE m.menu_modulo = 'basketball' AND m.menu_vista = 'alumnoList'
   AND p.permiso_ver = 'S' AND p.permiso_estado = 'A'
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso x WHERE x.permiso_rolid = p.permiso_rolid AND x.permiso_menuid = @m_carnets);

/*----------  El módulo, a quien recibió alguna de las dos  ----------*/
INSERT INTO seguridad_rol_modulo (rolmod_rolid, rolmod_modulo, rolmod_estado)
SELECT DISTINCT p.permiso_rolid, 'check', 'A'
  FROM seguridad_permiso p
 WHERE p.permiso_menuid IN (@m_escanear, @m_carnets) AND p.permiso_ver = 'S' AND p.permiso_estado = 'A'
   AND NOT EXISTS (SELECT 1 FROM seguridad_rol_modulo r WHERE r.rolmod_rolid = p.permiso_rolid AND r.rolmod_modulo = 'check');
