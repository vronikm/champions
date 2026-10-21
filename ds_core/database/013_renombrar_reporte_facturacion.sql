-- =====================================================================
-- DigiSports · Se desambigua el menú de facturación en Basketball
-- =====================================================================
-- Tras publicar la emisión de comprobantes (migración 010) el menú tenía
-- dos entradas con el mismo texto:
--
--   · «Facturación electrónica» -> facturasList
--     Emisión de comprobantes con validez tributaria.
--   · «Facturación» (en Reportes) -> reporteRepresentanteFactura
--     Informe de facturación agrupado por representante.
--
-- Son cosas distintas, así que la del informe pasa a llamarse por lo que
-- realmente muestra.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/013_renombrar_reporte_facturacion.sql"
-- =====================================================================

SELECT '--- ANTES ---' AS info;
SELECT menu_id, menu_nombre, menu_vista, menu_padreid
  FROM seguridad_menu
 WHERE menu_modulo = 'basketball'
   AND menu_vista IN ('facturasList', 'reporteRepresentanteFactura')
 ORDER BY menu_id;

UPDATE seguridad_menu
   SET menu_nombre = 'Facturación por representante'
 WHERE menu_modulo = 'basketball'
   AND menu_vista  = 'reporteRepresentanteFactura';

SELECT '--- DESPUES ---' AS info;
SELECT menu_id, menu_nombre, menu_vista, menu_padreid
  FROM seguridad_menu
 WHERE menu_modulo = 'basketball'
   AND menu_vista IN ('facturasList', 'reporteRepresentanteFactura')
 ORDER BY menu_id;

SELECT '--- textos repetidos que queden en el menu ---' AS info;
SELECT menu_nombre, COUNT(*) AS veces
  FROM seguridad_menu
 WHERE menu_modulo = 'basketball' AND menu_estado = 'A' AND menu_vista <> ''
 GROUP BY menu_nombre
HAVING COUNT(*) > 1;
