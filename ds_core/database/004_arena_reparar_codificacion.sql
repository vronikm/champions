-- =====================================================================
-- DigiSports Arena · Reparación de acentos doblemente codificados
-- =====================================================================
-- Si la migración 003 se ejecuta canalizando el archivo por PowerShell,
-- los acentos llegan doblemente codificados ("crÃ©dito" en vez de
-- "crédito"): Get-Content lee UTF-8 sin BOM como ANSI.
--
-- La forma correcta de ejecutar cualquier migración de este proyecto es
-- dejando que el propio cliente lea el archivo:
--
--     mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--           -e "source ds_core/database/003_arena_esquema.sql"
--
-- Esta migración repara lo ya guardado. Es idempotente: sobre texto bien
-- codificado la conversión no lo altera, porque sólo revierte la doble
-- codificación cuando existe.
-- =====================================================================

SELECT '--- ANTES ---' AS info;
SELECT forma_codigo, forma_nombre FROM dsa_forma_ingreso ORDER BY forma_orden;

-- Reparación estándar de doble codificación UTF-8: se reinterpretan los
-- bytes como latin1 y se vuelven a leer como utf8mb4.
UPDATE dsa_forma_ingreso
   SET forma_nombre = CONVERT(BINARY(CONVERT(forma_nombre USING latin1)) USING utf8mb4)
 WHERE HEX(forma_nombre) LIKE '%C383C2%';

UPDATE dsa_tipo_piso
   SET piso_nombre = CONVERT(BINARY(CONVERT(piso_nombre USING latin1)) USING utf8mb4)
 WHERE HEX(piso_nombre) LIKE '%C383C2%';

UPDATE dsa_tipo_piso
   SET piso_detalle = CONVERT(BINARY(CONVERT(piso_detalle USING latin1)) USING utf8mb4)
 WHERE HEX(piso_detalle) LIKE '%C383C2%';

SELECT '--- DESPUES ---' AS info;
SELECT forma_codigo, forma_nombre, HEX(forma_nombre) AS bytes
  FROM dsa_forma_ingreso ORDER BY forma_orden;

SELECT piso_nombre, piso_detalle FROM dsa_tipo_piso ORDER BY piso_id;

SELECT '--- quedan dobles codificaciones? ---' AS info;
SELECT (SELECT COUNT(1) FROM dsa_forma_ingreso WHERE HEX(forma_nombre) LIKE '%C383C2%')
     + (SELECT COUNT(1) FROM dsa_tipo_piso     WHERE HEX(piso_nombre)  LIKE '%C383C2%')
     + (SELECT COUNT(1) FROM dsa_tipo_piso     WHERE HEX(piso_detalle) LIKE '%C383C2%') AS pendientes;
