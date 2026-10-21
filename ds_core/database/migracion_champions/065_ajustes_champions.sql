-- Ajustes posteriores para alinear la base de champions con el esquema de barcelona.
SET NAMES utf8mb4;
-- Barcelona admite categorias de texto libre ("Sub 15", "2013-2014").
ALTER TABLE torneo_equipo MODIFY equipo_categoria VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;
-- Facturacion antigua de SoccerEasy: vacia y sustituida por facturas_electronicas.
DROP TABLE IF EXISTS facturacion_factura_pago;
DROP TABLE IF EXISTS facturacion_factura;
-- Menus que barcelona registro fuera de las migraciones (carnets, LOPDP,
-- inscripciones pendientes y vistas ocultas de League). Mismos ids.
INSERT INTO seguridad_menu
SELECT b.* FROM digitech_barcelona.seguridad_menu b
 WHERE b.menu_id IN (43, 44, 908, 969, 970, 971, 972, 973)
   AND NOT EXISTS (SELECT 1 FROM seguridad_menu c WHERE c.menu_id = b.menu_id);
INSERT IGNORE INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
        permiso_eliminar, permiso_exportar, permiso_estado)
SELECT r.rol_id, b.permiso_menuid, b.permiso_ver, b.permiso_crear, b.permiso_editar,
       b.permiso_eliminar, b.permiso_exportar, b.permiso_estado
  FROM digitech_barcelona.seguridad_permiso b
  JOIN digitech_barcelona.seguridad_rol br ON br.rol_id = b.permiso_rolid
  JOIN seguridad_rol r ON r.rol_nombre = br.rol_nombre  -- los ids de rol difieren entre bases
 WHERE b.permiso_menuid IN (43, 44, 908, 969, 970, 971, 972, 973);
-- Como en barcelona: Facturacion no se concede al Administrador mientras
-- no exista configuracion del SRI para esta escuela.
DELETE p FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE p.permiso_rolid = 2 AND m.menu_vista = 'facturasList';
-- Logo de Champions en la carpeta de marca de Core (el archivo anterior no existia).
UPDATE general_escuela SET escuela_logo = 'organizacion_champions.png' WHERE escuela_id = 1;
