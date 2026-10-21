-- =====================================================================
-- 052 · La vista de exportacion, registrada en el menu
-- =====================================================================
-- No aparece en el menu lateral —no es una pantalla que se visite— pero SI
-- tiene que estar registrada: con DS_PERMISOS_ESTRICTOS activo, una vista
-- que no esta en seguridad_menu se DENIEGA, y sin esta fila nadie podria
-- exportar nunca.
--
-- Se registra con menu_estado = 'O': activa a efectos de permisos pero
-- oculta en el menu. Es el mismo valor que permisos_de_la_sesion() ya
-- acepta junto a 'A'.
-- =====================================================================

DELETE p FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE m.menu_modulo = 'insights' AND m.menu_vista = 'exportar';

DELETE FROM seguridad_menu WHERE menu_modulo = 'insights' AND menu_vista = 'exportar';

INSERT INTO seguridad_menu
    (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
VALUES
    ('insights', 'Exportar', 99, 0, 'N', 'exportar', 'nav-icon fas fa-download', 'O');
