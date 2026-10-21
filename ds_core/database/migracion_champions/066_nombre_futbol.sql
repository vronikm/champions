-- Champions es una escuela de futbol: el nombre visible del modulo pasa a
-- "Fútbol". La clave interna 'basketball' (menu_modulo, rolmod_modulo,
-- punto_modulo, origen de comprobantes) NO cambia: el codigo la usa como
-- identificador. Bytes UTF-8 explicitos para no depender del cliente mysql.
SET NAMES utf8mb4;
UPDATE seguridad_menu
   SET menu_nombre = UNHEX('46C3BA74626F6C')   -- Fútbol
 WHERE menu_modulo = 'insights' AND menu_vista = 'basketball' AND menu_nombre = 'Basketball';
-- Umbral de Insights cuyo nombre se muestra en Configuracion.
UPDATE insights_umbral
   SET umbral_nombre = CONCAT('Pagos de ', UNHEX('46C3BA74626F6C'), ' con saldo')
 WHERE umbral_codigo = 'pagos_pendientes' AND umbral_nombre = 'Pagos de Basketball con saldo';
