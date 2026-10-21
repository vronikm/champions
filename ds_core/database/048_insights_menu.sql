-- =====================================================================
-- 048 · Alta de DigiSports Insights en el menu y los permisos
-- =====================================================================
-- QUE RESUELVE
--
-- Insights tiene DS_PERMISOS_ESTRICTOS = true: una vista que NO este
-- registrada en seguridad_menu se DENIEGA. Sin estas filas el modulo
-- arrancaria y no dejaria entrar a nadie, ni siquiera con el modulo
-- concedido — salvo al rol 1, que pasa por encima de toda la matriz.
--
-- Es deliberado que sea asi y no al reves: en un modulo donde toda vista es
-- informacion gerencial, olvidar registrar una no puede significar dejarla
-- abierta.
--
--
-- LAS NUEVE VISTAS, Y POR QUE ESTAN SEPARADAS
--
-- El §9 del encargo pide quince permisos con nombre. Aqui son filas, y cada
-- una se concede por separado porque responden a preguntas distintas:
--
--   dashboard        el consolidado ejecutivo
--   basketball       analitica de la escuela
--   arena            ocupacion, reservas, mapa de calor
--   league           torneos, participacion, recaudacion
--   financiero       ingresos consolidados     -> INSIGHTS_VER_INGRESOS
--   cartera          lo que se debe            -> INSIGHTS_VER_CARTERA
--   reporteList      catalogo de reportes
--   transacciones    el detalle del drill-down, pago a pago
--   configuracion    indicadores y umbrales
--
-- «financiero» y «cartera» van separadas a proposito: ver cuanto entra y ver
-- quien debe no son el mismo permiso. Y «transacciones» es la unica que
-- muestra movimientos individuales en vez de agregados, asi que tambien va
-- suelta: es donde el dato deja de ser anonimo.
--
--
-- NO SE CONCEDE NADA A NADIE
--
-- Esta migracion registra las vistas; no da acceso. Quien deba entrar se
-- habilita desde Core: primero el modulo en seguridad_rol_modulo, luego las
-- vistas en la matriz de permisos. Menor privilegio, y ademas deja rastro de
-- quien lo concedio.
--
-- Es idempotente: se puede aplicar dos veces sin duplicar.
-- =====================================================================

/* Se borra antes de insertar para poder reaplicar la migracion sin duplicar
   filas. No toca permisos concedidos de otros modulos. */
DELETE p FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE m.menu_modulo = 'insights';

DELETE FROM seguridad_menu WHERE menu_modulo = 'insights';

INSERT INTO seguridad_menu
    (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
VALUES
    ('insights', 'Panel',              1, 0, 'N', 'dashboard',     'nav-icon fas fa-chart-line',   'A'),
    ('insights', 'Basketball',         2, 0, 'N', 'basketball',    'nav-icon fas fa-basketball-ball', 'A'),
    ('insights', 'Arena',              3, 0, 'N', 'arena',         'nav-icon fas fa-warehouse',    'A'),
    ('insights', 'League',             4, 0, 'N', 'league',        'nav-icon fas fa-trophy',       'A'),
    ('insights', 'Financiero',         5, 0, 'N', 'financiero',    'nav-icon fas fa-dollar-sign',  'A'),
    ('insights', 'Cartera',            6, 0, 'N', 'cartera',       'nav-icon fas fa-hand-holding-usd', 'A'),
    ('insights', 'Centro de reportes', 7, 0, 'N', 'reporteList',   'nav-icon fas fa-file-alt',     'A'),
    ('insights', 'Transacciones',      8, 0, 'N', 'transacciones', 'nav-icon fas fa-list-ul',      'A'),
    ('insights', 'Indicadores',        9, 0, 'N', 'configuracion', 'nav-icon fas fa-sliders-h',    'A');
