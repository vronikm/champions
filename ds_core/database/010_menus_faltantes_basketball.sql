-- =====================================================================
-- DigiSports · Menús que le faltaban al módulo Basketball
-- =====================================================================
-- Cumpleaños, Facturación electrónica, Carnets e Inscripción Online
-- estaban desarrollados (vistas, controladores y lista blanca) pero sin
-- registro en seguridad_menu, así que no aparecían en el menú lateral.
--
-- Registrarlos hace dos cosas a la vez:
--   1. Los publica en el menú, con permiso por rol desde Core.
--   2. Los somete al control de acceso. Una vista NO registrada queda
--      SIN restricción, de modo que hasta ahora cualquier usuario
--      autenticado podía abrir la configuración de facturación (RUC,
--      certificado y ambiente del SRI) escribiendo la URL a mano.
--
-- Las pantallas auxiliares que se abren desde un listado
-- (cumpleaniosTarjeta, carnetFoto, carnetPDF, carnetFotoPDF) siguen sin
-- registrar, igual que pagosRecibo y compañía: es la convención del
-- módulo para las vistas de apoyo.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/010_menus_faltantes_basketball.sql"
-- =====================================================================

SELECT '--- ANTES: menus de primer nivel en Basketball ---' AS info;
SELECT menu_id, menu_orden, menu_nombre, menu_vista, menu_estado
  FROM seguridad_menu
 WHERE menu_modulo = 'basketball' AND menu_padreid = 0
 ORDER BY menu_orden;

-- ---------------------------------------------------------------------
-- 1. Grupos que quedaron vacíos al pasar la configuración a Core
-- ---------------------------------------------------------------------
-- Sus cuatro hijos ya están inactivos; el grupo sin hijos sólo aportaba
-- un enlace muerto en el menú del superadministrador.
UPDATE seguridad_menu
   SET menu_estado = 'I'
 WHERE menu_modulo = 'basketball'
   AND menu_id IN (30, 34)
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) H
                    WHERE H.menu_padreid = seguridad_menu.menu_id
                      AND H.menu_estado  = 'A');

-- ---------------------------------------------------------------------
-- 2. Inscripción Online
-- ---------------------------------------------------------------------
-- Agrupa lo que ya existía suelto (Consentimientos LOPDP e
-- Inscripciones pendientes) con la generación de enlaces, que no tenía
-- menú. Los dos menús que se reubican conservan su menu_id, así que los
-- permisos ya concedidos siguen vigentes.

INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Inscripción Online', 16, 0, 'S',
       'No', 'nav-icon fas fa-user-plus text-warning', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'basketball'
                      AND M.menu_nombre = 'Inscripción Online');

SET @g_inscripcion = (SELECT menu_id FROM seguridad_menu
                       WHERE menu_modulo = 'basketball'
                         AND menu_nombre = 'Inscripción Online' LIMIT 1);

INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Generar enlaces', 1, @g_inscripcion, 'N',
       'inscripcionEnlace', 'nav-icon fas fa-link', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'basketball'
                      AND M.menu_vista  = 'inscripcionEnlace');

UPDATE seguridad_menu SET menu_padreid = @g_inscripcion, menu_orden = 2
 WHERE menu_modulo = 'basketball' AND menu_vista = 'inscripcionPendientes';

UPDATE seguridad_menu SET menu_padreid = @g_inscripcion, menu_orden = 3
 WHERE menu_modulo = 'basketball' AND menu_vista = 'consentimientoList';

-- ---------------------------------------------------------------------
-- 3. Facturación electrónica
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Facturación electrónica', 17, 0, 'S',
       'No', 'nav-icon fas fa-file-invoice-dollar', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'basketball'
                      AND M.menu_nombre = 'Facturación electrónica');

SET @g_facturacion = (SELECT menu_id FROM seguridad_menu
                       WHERE menu_modulo = 'basketball'
                         AND menu_nombre = 'Facturación electrónica' LIMIT 1);

-- «Nueva factura» (facturasNew) NO se registra: exige un alumno en la
-- URL y se abre desde el listado, igual que pagosNew o alumnoNew. Su
-- control de acceso se resuelve en la propia vista, que exige permiso
-- de creación sobre facturasList.
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT * FROM (
    SELECT 'basketball' m, 'Facturas emitidas' n, 1 o, @g_facturacion p, 'N' h,
           'facturasList' v, 'nav-icon fas fa-file-invoice' i, 'A' e
    UNION ALL
    SELECT 'basketball', 'Configuración SRI', 2, @g_facturacion, 'N',
           'facturacionConfig', 'nav-icon fas fa-cogs', 'A'
) nuevos
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'basketball' AND M.menu_vista = nuevos.v);

-- ---------------------------------------------------------------------
-- 4. Carnets
-- ---------------------------------------------------------------------
-- «Carnets del mes» ya existía suelto al final del menú; se agrupa con
-- su configuración, que no tenía entrada.
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Carnets', 18, 0, 'S',
       'No', 'nav-icon far fa-address-card', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'basketball'
                      AND M.menu_nombre = 'Carnets'
                      AND M.menu_padreid = 0
                      AND M.menu_hijo = 'S');

SET @g_carnets = (SELECT menu_id FROM seguridad_menu
                   WHERE menu_modulo = 'basketball'
                     AND menu_nombre = 'Carnets'
                     AND menu_padreid = 0 AND menu_hijo = 'S' LIMIT 1);

UPDATE seguridad_menu
   SET menu_padreid = @g_carnets, menu_orden = 1, menu_nombre = 'Carnets del mes'
 WHERE menu_modulo = 'basketball' AND menu_vista = 'carnetList';

INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Configuración', 2, @g_carnets, 'N',
       'carnetConf', 'nav-icon fas fa-sliders-h', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'basketball'
                      AND M.menu_vista  = 'carnetConf');

-- ---------------------------------------------------------------------
-- 5. Cumpleaños
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT 'basketball', 'Cumpleaños', 19, 0, 'N',
       'cumpleaniosList', 'nav-icon fas fa-birthday-cake text-info', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'basketball'
                      AND M.menu_vista  = 'cumpleaniosList');

-- ---------------------------------------------------------------------
-- 6. Comprobación
-- ---------------------------------------------------------------------
SELECT '--- DESPUES: estructura de Basketball ---' AS info;
SELECT p.menu_id  AS grupo_id,
       COALESCE(p.menu_nombre, '(primer nivel)') AS grupo,
       m.menu_id, m.menu_orden, m.menu_nombre, m.menu_vista, m.menu_estado
  FROM seguridad_menu m
  LEFT JOIN seguridad_menu p ON p.menu_id = m.menu_padreid
 WHERE m.menu_modulo = 'basketball' AND m.menu_estado = 'A'
 ORDER BY COALESCE(p.menu_orden, m.menu_orden), m.menu_padreid, m.menu_orden;

SELECT '--- vistas nuevas que pasan a estar restringidas ---' AS info;
SELECT menu_id, menu_nombre, menu_vista
  FROM seguridad_menu
 WHERE menu_modulo = 'basketball'
   AND menu_vista IN ('inscripcionEnlace','facturasList',
                      'facturacionConfig','carnetConf','cumpleaniosList')
 ORDER BY menu_id;
