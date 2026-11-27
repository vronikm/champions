-- =====================================================================
-- 104 · Arena › Registrar ventas: líneas sin producto del catálogo
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- En «Registrar ventas», lo que no estaba en el catálogo se creaba como
-- producto nuevo. Pero muchas veces no es un producto: «Ventas realizadas
-- el 26/11/2026» es el total del día, y quedaba como un «servicio» más en
-- Productos y en «Vender».
--
--
-- QUE HACE ESTA MIGRACION
--
-- dsa_venta_detalle.detalle_productoid admite NULL: la línea guarda sólo
-- su descripción (detalle_nombre), cantidad y precio, y no crea nada en
-- Productos ni mueve inventario. Los reportes de ventas la cuentan igual
-- (por su nombre).
--
-- No borra productos ya creados así: si alguno sobra, se desactiva o se
-- elimina desde Productos.
--
-- SE PUEDE REPETIR. Sin el punto de venta no hace nada.
-- Como en la 094: la base de la escuela en @db antes de information_schema.
-- =====================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @falta := (SELECT COUNT(*) > 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_venta_detalle' AND COLUMN_NAME = 'detalle_productoid' AND IS_NULLABLE = 'NO');
SET @sql := IF(@falta,
    CONCAT("ALTER TABLE `", @db, "`.dsa_venta_detalle
        MODIFY detalle_productoid INT NULL DEFAULT NULL COMMENT 'NULL: línea sin producto del catálogo, sólo su descripción (104)'"),
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Comprobación
SET @sql := CONCAT("SELECT COALESCE((SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '", @db, "'
                       AND TABLE_NAME = 'dsa_venta_detalle' AND COLUMN_NAME = 'detalle_productoid'), 'sin punto de venta') AS productoid_admite_null");
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Para deshacerla (sólo si no hay líneas sin producto):
-- ALTER TABLE dsa_venta_detalle MODIFY detalle_productoid INT NOT NULL;
