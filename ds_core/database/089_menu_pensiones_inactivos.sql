-- =====================================================================
-- 089 · Fútbol: «Pensiones Inactivos» en Gestión de cobranza
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- La cobranza de pensiones (cobranzaPension) lista sólo alumnos activos.
-- Los inactivos que dejaron pensiones sin pagar tenían una pantalla vieja
-- (cobranzaPensionInactivos) que no estaba en el menú: sólo se llegaba
-- escribiendo la dirección.
--
--
-- QUE HACE ESTA MIGRACION
--
--   1. Menú: «Pensiones Inactivos» (vista cobranzaPensionInactivos) en el
--      grupo de «Pensiones» (Gestión de cobranza), justo debajo. Las
--      entradas que venían después (Uniformes) bajan un lugar.
--   2. Permisos: cada rol recibe sobre la entrada nueva lo mismo que ya
--      tiene sobre «Pensiones». Hasta hoy esa pantalla heredaba el permiso
--      de «Pensiones» (ds_core/inc/vistas_apoyo.php); así nadie gana ni
--      pierde acceso, y desde Core › Permisos se puede ajustar aparte. El
--      Super Administrador tiene acceso total por definición.
--
-- Común a los cuatro sistemas (ds_core/database).
--
--
-- SE PUEDE REPETIR
--
-- El corrimiento del orden y el INSERT del menú sólo ocurren si la entrada
-- aún no existe; los permisos llevan NOT EXISTS.
-- =====================================================================

SET NAMES utf8mb4;

SET @m_pension = (SELECT menu_id FROM seguridad_menu
                   WHERE menu_modulo = 'basketball' AND menu_vista = 'cobranzaPension' LIMIT 1);
SET @g_cobranza = (SELECT menu_padreid FROM seguridad_menu WHERE menu_id = @m_pension);
SET @o_pension  = (SELECT menu_orden   FROM seguridad_menu WHERE menu_id = @m_pension);
SET @existe = (SELECT COUNT(*) FROM seguridad_menu
                WHERE menu_modulo = 'basketball' AND menu_vista = 'cobranzaPensionInactivos');

-- ---------------------------------------------------------------------
-- 1. Menú
-- ---------------------------------------------------------------------
-- Hueco debajo de «Pensiones»: lo que venía después baja un lugar.
UPDATE seguridad_menu
   SET menu_orden = menu_orden + 1
 WHERE @existe = 0 AND @m_pension IS NOT NULL
   AND menu_modulo = 'basketball'
   AND menu_padreid = @g_cobranza
   AND menu_orden > @o_pension;

INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Pensiones Inactivos', @o_pension + 1, @g_cobranza, 'N',
       'cobranzaPensionInactivos', 'nav-icon far fa-circle text-info', 'A'
  FROM DUAL
 WHERE @existe = 0 AND @m_pension IS NOT NULL;

-- ---------------------------------------------------------------------
-- 2. Permisos: lo mismo que cada rol tiene sobre «Pensiones»
-- ---------------------------------------------------------------------
INSERT INTO seguridad_permiso
    (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
     permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
SELECT p.permiso_rolid, destino.menu_id, p.permiso_ver, p.permiso_crear,
       p.permiso_editar, p.permiso_eliminar, p.permiso_exportar, 'A'
  FROM seguridad_permiso p
 CROSS JOIN (SELECT menu_id FROM seguridad_menu
              WHERE menu_modulo = 'basketball'
                AND menu_vista  = 'cobranzaPensionInactivos' LIMIT 1) AS destino
 WHERE p.permiso_menuid = @m_pension
   AND p.permiso_estado = 'A'
   AND NOT EXISTS (SELECT 1 FROM (SELECT permiso_rolid, permiso_menuid FROM seguridad_permiso) ya
                    WHERE ya.permiso_rolid = p.permiso_rolid
                      AND ya.permiso_menuid = destino.menu_id);

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT m.menu_id, m.menu_nombre, m.menu_orden, m.menu_vista
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'basketball' AND m.menu_padreid = @g_cobranza
 ORDER BY m.menu_orden;

SELECT r.rol_nombre, p.permiso_ver AS ver, p.permiso_editar AS editar
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_vista = 'cobranzaPensionInactivos'
 ORDER BY r.rol_id;
