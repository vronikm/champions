-- =====================================================================
-- 072 · Sedes de cada usuario: una, varias o todas
-- =====================================================================
-- QUÉ RESUELVE
--
-- Un usuario ve sólo la información de sus sedes (seguridad_usuario_sede,
-- una fila por sede). Hasta ahora:
--   · Core › Usuarios no permitía asignarlas: no había campo;
--   · «ver todas» dependía del rol (1 y 2 fijos en el código), y cada
--     pantalla aplicaba su propia regla —en unas «sin sede» era «todas», en
--     otras «ninguna»—.
--
-- Desde esta migración la regla es UNA (sedes_de_la_sesion(), en
-- ds_core/inc/seguridad.php):
--   · el superadministrador ve todas;
--   · usuario_todassedes = 'S' ve todas, también las que se creen después;
--   · el resto ve sólo sus sedes asignadas; sin ninguna, nada.
--
-- PARA QUE NADIE PIERDA ACCESO AL APLICARLA
--   · Superadministradores y Administradores (roles 1 y 2), que hasta hoy
--     veían todas en Fútbol, quedan con «todas». Se puede cambiar después
--     en Core › Usuarios.
--   · Un usuario de otro rol sin ninguna sede recibe la de su ficha de
--     empleado, si la tiene.
--
-- ES REPETIBLE: la columna y el índice sólo se crean si faltan; los
-- rellenos sólo actúan sobre lo que aún no tiene valor.
--
-- Aplicar con:  mysql -u USUARIO -p BASE < 072_usuario_sedes.sql

SET NAMES utf8mb4;

-- 1. La marca «todas las sedes».
SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'seguridad_usuario' AND column_name = 'usuario_todassedes');
SET @sql := IF(@existe = 0,
    'ALTER TABLE seguridad_usuario ADD COLUMN usuario_todassedes CHAR(1) NOT NULL DEFAULT ''N'' AFTER usuario_rolid',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 1b. Sólo al crearla: roles 1 y 2 conservan el acceso a todas.
SET @sql := IF(@existe = 0,
    'UPDATE seguridad_usuario SET usuario_todassedes = ''S'' WHERE usuario_rolid IN (1, 2)',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Una sede, una vez por usuario: se quitan duplicados y se impide que
--    vuelvan. La tabla no tiene clave primaria, así que dos filas iguales no
--    se distinguen: si hay duplicados, se vuelca sin ellos y se rellena.
CREATE TEMPORARY TABLE tmp072_usuario_sede AS
  SELECT DISTINCT usuariosede_usuarioid, usuariosede_sedeid FROM seguridad_usuario_sede;
SET @dups := (SELECT COUNT(*) FROM seguridad_usuario_sede) - (SELECT COUNT(*) FROM tmp072_usuario_sede);
SET @sql := IF(@dups > 0, 'DELETE FROM seguridad_usuario_sede', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF(@dups > 0, 'INSERT INTO seguridad_usuario_sede (usuariosede_usuarioid, usuariosede_sedeid) SELECT usuariosede_usuarioid, usuariosede_sedeid FROM tmp072_usuario_sede', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
DROP TEMPORARY TABLE tmp072_usuario_sede;

SET @existe := (SELECT COUNT(1) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'seguridad_usuario_sede' AND index_name = 'uk_usuario_sede');
SET @sql := IF(@existe = 0,
    'ALTER TABLE seguridad_usuario_sede ADD UNIQUE KEY uk_usuario_sede (usuariosede_usuarioid, usuariosede_sedeid)',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Usuarios sin ninguna sede ni «todas»: la sede de su ficha de empleado.
INSERT INTO seguridad_usuario_sede (usuariosede_usuarioid, usuariosede_sedeid)
SELECT u.usuario_id, e.empleado_sedeid
  FROM seguridad_usuario u
  JOIN sujeto_empleado e ON e.empleado_id = u.usuario_empleadoid
  JOIN general_sede s    ON s.sede_id     = e.empleado_sedeid
 WHERE u.usuario_estado <> 'E'
   AND u.usuario_todassedes = 'N'
   AND NOT EXISTS (SELECT 1 FROM seguridad_usuario_sede us WHERE us.usuariosede_usuarioid = u.usuario_id);

-- ---------------------------------------------------------------------
-- Comprobación: cada usuario con su alcance
-- ---------------------------------------------------------------------
SELECT u.usuario_usuario, u.usuario_rolid,
       IF(u.usuario_rolid = 1 OR u.usuario_todassedes = 'S', 'Todas',
          IFNULL(GROUP_CONCAT(s.sede_nombre ORDER BY s.sede_nombre SEPARATOR ', '), '(ninguna)')) AS sedes
  FROM seguridad_usuario u
  LEFT JOIN seguridad_usuario_sede us ON us.usuariosede_usuarioid = u.usuario_id
  LEFT JOIN general_sede s ON s.sede_id = us.usuariosede_sedeid
 WHERE u.usuario_estado <> 'E'
 GROUP BY u.usuario_id
 ORDER BY u.usuario_usuario;

-- Para revertir:
-- ALTER TABLE seguridad_usuario_sede DROP INDEX uk_usuario_sede;
-- ALTER TABLE seguridad_usuario DROP COLUMN usuario_todassedes;
