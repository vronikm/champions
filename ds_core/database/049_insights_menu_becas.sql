-- =====================================================================
-- 049 · La vista de becas y descuentos entra en el menu de Insights
-- =====================================================================
-- Va como entrada propia y no dentro de «Financiero» porque responde a una
-- pregunta distinta y se concede por separado: quien puede ver cuanto entra
-- no necesariamente debe ver a que alumno se le perdona la cuota.
--
-- Idempotente: se puede aplicar dos veces sin duplicar.
-- =====================================================================

DELETE p FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE m.menu_modulo = 'insights' AND m.menu_vista = 'becas';

DELETE FROM seguridad_menu WHERE menu_modulo = 'insights' AND menu_vista = 'becas';

/* Detras de «Cartera», que es el otro indicador de dinero que no entra. */
UPDATE seguridad_menu SET menu_orden = menu_orden + 1
 WHERE menu_modulo = 'insights' AND menu_orden >= 7;

INSERT INTO seguridad_menu
    (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
VALUES
    ('insights', 'Becas y descuentos', 7, 0, 'N', 'becas', 'nav-icon fas fa-user-graduate', 'A');
