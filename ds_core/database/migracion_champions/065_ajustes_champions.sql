-- Ajustes posteriores para alinear la base de champions con el esquema de barcelona.
--
-- AUTOSUFICIENTE. La primera versión copiaba menús y permisos con
-- «SELECT ... FROM digitech_barcelona...»: sólo funcionaba en un servidor
-- donde existiera esa base, y en producción no existe. Los valores se
-- escriben ahora aquí; son los mismos que aquella copia tomaba (comprobado
-- contra digitech_barcelona el 2026-10-21) y el resultado es idéntico.
--
-- Idempotente: INSERT IGNORE sobre claves que ya existan.
SET NAMES utf8mb4;

-- Barcelona admite categorias de texto libre ("Sub 15", "2013-2014").
ALTER TABLE torneo_equipo MODIFY equipo_categoria VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;

-- Facturacion antigua de SoccerEasy: vacia y sustituida por facturas_electronicas.
DROP TABLE IF EXISTS facturacion_factura_pago;
DROP TABLE IF EXISTS facturacion_factura;

-- Menus que barcelona registro fuera de las migraciones (carnets, LOPDP,
-- inscripciones pendientes y vistas ocultas de League). Mismos ids.
INSERT IGNORE INTO seguridad_menu
       (menu_id, menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
VALUES (43,  'basketball', 'Consentimientos LOPDP',    3,   927,  'N', 'consentimientoList',    'nav-icon fas fa-user-shield text-warning', 'A'),
       (44,  'basketball', 'Inscripciones pendientes', 2,   927,  'N', 'inscripcionPendientes', 'nav-icon fas fa-user-clock text-warning',  'A'),
       (908, 'basketball', 'Carnets del mes',          18,  0,    'N', 'carnetList',            'nav-icon far fa-address-card',             'A'),
       (969, 'league',     'Plantilla',                70,  NULL, 'N', 'plantillaPanel',        'fas fa-id-card',                           'O'),
       (970, 'league',     'Sorteo',                   80,  NULL, 'N', 'sorteoPanel',           'fas fa-random',                            'O'),
       (971, 'league',     'Eliminatorias',            90,  NULL, 'N', 'playoffPanel',          'fas fa-sitemap',                           'O'),
       (972, 'league',     'Acta del partido',         100, NULL, 'N', 'actaPartido',           'fas fa-clipboard-list',                    'O'),
       (973, 'league',     'Líderes',                  110, NULL, 'N', 'rankingPanel',          'fas fa-medal',                             'O');

-- Sus permisos, por NOMBRE de rol: los ids de rol difieren entre bases. Un
-- rol que esta escuela no tenga («Asistente») simplemente no se cruza.
INSERT IGNORE INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
        permiso_eliminar, permiso_exportar, permiso_estado)
SELECT r.rol_id, v.menuid, v.ver, v.crear, v.editar, v.eliminar, v.exportar, v.estado
  FROM (          SELECT 'Administrador'       rol, 43  menuid, 'S' ver, 'S' crear, 'S' editar, 'S' eliminar, 'N' exportar, 'A' estado
        UNION ALL SELECT 'Asistente',               43,  'S', 'S', 'S', 'S', 'N', 'A'
        UNION ALL SELECT 'Gerente',                 43,  'S', 'S', 'S', 'S', 'N', 'A'
        UNION ALL SELECT 'Administrador',           44,  'S', 'S', 'S', 'S', 'N', 'A'
        UNION ALL SELECT 'Administrador',           908, 'S', 'S', 'S', 'N', 'N', 'A'
        UNION ALL SELECT 'Asistente',               908, 'S', 'S', 'S', 'N', 'N', 'A'
        UNION ALL SELECT 'Super Administrador',     969, 'S', 'S', 'S', 'S', 'N', 'A'
        UNION ALL SELECT 'Super Administrador',     970, 'S', 'S', 'S', 'S', 'N', 'A'
        UNION ALL SELECT 'Super Administrador',     971, 'S', 'S', 'S', 'S', 'N', 'A'
        UNION ALL SELECT 'Árbitro',                 972, 'S', 'N', 'S', 'N', 'N', 'A'
        UNION ALL SELECT 'Oficial de mesa',         972, 'S', 'N', 'S', 'N', 'N', 'A'
        UNION ALL SELECT 'Super Administrador',     972, 'S', 'S', 'S', 'S', 'N', 'A'
        UNION ALL SELECT 'Super Administrador',     973, 'S', 'S', 'S', 'S', 'N', 'A') v
  JOIN seguridad_rol r ON r.rol_nombre = v.rol;

-- Como en barcelona: Facturacion no se concede al Administrador mientras
-- no exista configuracion del SRI para esta escuela.
DELETE p FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 WHERE p.permiso_rolid = 2 AND m.menu_vista = 'facturasList';

-- Logo de Champions en la carpeta de marca de Core (el archivo anterior no existia).
UPDATE general_escuela SET escuela_logo = 'organizacion_champions.png' WHERE escuela_id = 1;
