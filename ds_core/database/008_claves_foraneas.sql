-- =====================================================================
-- DigiSports · Integridad referencial en la base
-- =====================================================================
-- Ahora que toda la base es InnoDB (migración 007) se pueden declarar las
-- relaciones que hasta ahora sólo validaba la aplicación. Pasa de "el
-- código lo comprueba" a "la base no lo permite".
--
-- LIMPIEZA PREVIA
-- El análisis encontró 10 filas huérfanas, prueba de lo que ocurría sin
-- estas restricciones:
--
--   · seguridad_usuario_sede: 4 asignaciones de sede de los usuarios 4 y 6,
--     que ya no existen.
--   · El horario 1 fue eliminado dejando atrás 5 filas de
--     asistencia_horario_detalle y 1 de asistencia_asignahorario
--     (cuyo alumno 119 tampoco existe).
--
-- Son filas que ningún código puede resolver: apuntan a registros
-- inexistentes. Se eliminan antes de crear las restricciones.
--
-- ACCIÓN AL BORRAR EL PADRE
--   RESTRICT  impide el borrado si hay hijos. Es el valor por defecto y
--             se usa para todo lo que representa información propia.
--   CASCADE   arrastra a los hijos. Sólo donde el hijo carece de sentido
--             sin el padre (permisos de un rol, detalles de un alumno).
--   SET NULL  deja la referencia vacía en relaciones opcionales.
--
-- Idempotente: comprueba si la restricción existe antes de crearla.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/008_claves_foraneas.sql"
-- =====================================================================

SELECT '--- huerfanos ANTES ---' AS info;
SELECT
  (SELECT COUNT(1) FROM seguridad_usuario_sede us
     LEFT JOIN seguridad_usuario u ON u.usuario_id = us.usuariosede_usuarioid
    WHERE u.usuario_id IS NULL) AS usuario_sede,
  (SELECT COUNT(1) FROM asistencia_asignahorario ah
     LEFT JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
    WHERE h.horario_id IS NULL) AS asignaciones,
  (SELECT COUNT(1) FROM asistencia_horario_detalle hd
     LEFT JOIN asistencia_horario h ON h.horario_id = hd.detalle_horarioid
    WHERE h.horario_id IS NULL) AS detalles;

-- ---------------------------------------------------------------------
-- 1. Limpieza de huérfanos
-- ---------------------------------------------------------------------
DELETE us FROM seguridad_usuario_sede us
  LEFT JOIN seguridad_usuario u ON u.usuario_id = us.usuariosede_usuarioid
 WHERE u.usuario_id IS NULL;

DELETE ah FROM asistencia_asignahorario ah
  LEFT JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
 WHERE h.horario_id IS NULL;

DELETE ah FROM asistencia_asignahorario ah
  LEFT JOIN sujeto_alumno a ON a.alumno_id = ah.asignahorario_alumnoid
 WHERE a.alumno_id IS NULL;

DELETE hd FROM asistencia_horario_detalle hd
  LEFT JOIN asistencia_horario h ON h.horario_id = hd.detalle_horarioid
 WHERE h.horario_id IS NULL;

-- ---------------------------------------------------------------------
-- 1b. Ceros que significan "sin vínculo"
-- ---------------------------------------------------------------------
-- En las relaciones OPCIONALES, la ausencia de vínculo se guardó como 0
-- en lugar de NULL. Para una clave foránea el 0 no es "vacío": es un id
-- que debe existir en el padre. Se normaliza a NULL, que es lo que
-- realmente significa.
UPDATE seguridad_usuario
   SET usuario_empleadoid = NULL
 WHERE usuario_empleadoid = 0;

-- ---------------------------------------------------------------------
-- 2. Índices en las columnas hijas
-- ---------------------------------------------------------------------
-- InnoDB exige un índice en la columna que referencia; si no existe lo
-- crea solo, pero declararlo deja el esquema explícito.
-- (Se omite: MySQL los genera automáticamente al crear la restricción.)

-- ---------------------------------------------------------------------
-- 3. Restricciones
-- ---------------------------------------------------------------------
-- Procedimiento auxiliar: crea la clave sólo si aún no existe, para que
-- la migración se pueda repetir sin error.
DROP PROCEDURE IF EXISTS ds_crear_fk;
DELIMITER //
CREATE PROCEDURE ds_crear_fk(
    IN p_nombre VARCHAR(64), IN p_hija VARCHAR(64), IN p_col VARCHAR(64),
    IN p_padre VARCHAR(64),  IN p_colpadre VARCHAR(64), IN p_accion VARCHAR(20))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints
                    WHERE constraint_schema = DATABASE()
                      AND constraint_name   = p_nombre
                      AND constraint_type   = 'FOREIGN KEY') THEN
        SET @s = CONCAT('ALTER TABLE `', p_hija, '` ADD CONSTRAINT `', p_nombre,
                        '` FOREIGN KEY (`', p_col, '`) REFERENCES `', p_padre,
                        '` (`', p_colpadre, '`) ON DELETE ', p_accion, ' ON UPDATE CASCADE');
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END //
DELIMITER ;

-- --- Seguridad ---
CALL ds_crear_fk('fk_usuario_rol',        'seguridad_usuario',    'usuario_rolid',         'seguridad_rol',        'rol_id',      'RESTRICT');
CALL ds_crear_fk('fk_usuario_empleado',   'seguridad_usuario',    'usuario_empleadoid',    'sujeto_empleado',      'empleado_id', 'SET NULL');
CALL ds_crear_fk('fk_permiso_rol',        'seguridad_permiso',    'permiso_rolid',         'seguridad_rol',        'rol_id',      'CASCADE');
CALL ds_crear_fk('fk_permiso_menu',       'seguridad_permiso',    'permiso_menuid',        'seguridad_menu',       'menu_id',     'CASCADE');
CALL ds_crear_fk('fk_rolmodulo_rol',      'seguridad_rol_modulo', 'rolmod_rolid',          'seguridad_rol',        'rol_id',      'CASCADE');
CALL ds_crear_fk('fk_usuariosede_usuario','seguridad_usuario_sede','usuariosede_usuarioid','seguridad_usuario',    'usuario_id',  'CASCADE');
CALL ds_crear_fk('fk_usuariosede_sede',   'seguridad_usuario_sede','usuariosede_sedeid',   'general_sede',         'sede_id',     'CASCADE');

-- --- Configuración ---
CALL ds_crear_fk('fk_sede_escuela',       'general_sede',           'sede_escuelaid',   'general_escuela', 'escuela_id', 'RESTRICT');
CALL ds_crear_fk('fk_catalogo_tabla',     'general_tabla_catalogo', 'catalogo_tablaid', 'general_tabla',   'tabla_id',   'RESTRICT');

-- --- Escuela ---
CALL ds_crear_fk('fk_alumno_sede',        'sujeto_alumno',        'alumno_sedeid',       'general_sede',         'sede_id',    'RESTRICT');
CALL ds_crear_fk('fk_alumno_representante','sujeto_alumno',       'alumno_repreid',      'alumno_representante', 'repre_id',   'RESTRICT');
CALL ds_crear_fk('fk_empleado_sede',      'sujeto_empleado',      'empleado_sedeid',     'general_sede',         'sede_id',    'RESTRICT');
CALL ds_crear_fk('fk_pago_alumno',        'alumno_pago',          'pago_alumnoid',       'sujeto_alumno',        'alumno_id',  'RESTRICT');
CALL ds_crear_fk('fk_cemergencia_alumno', 'alumno_cemergencia',   'cemer_alumnoid',      'sujeto_alumno',        'alumno_id',  'CASCADE');
CALL ds_crear_fk('fk_infomedic_alumno',   'alumno_infomedic',     'infomedic_alumnoid',  'sujeto_alumno',        'alumno_id',  'CASCADE');
CALL ds_crear_fk('fk_consentimiento_alumno','alumno_consentimiento','consent_alumnoid',  'sujeto_alumno',        'alumno_id',  'CASCADE');

-- --- Asistencia ---
CALL ds_crear_fk('fk_horario_sede',       'asistencia_horario',        'horario_sedeid',          'general_sede',       'sede_id',    'RESTRICT');
CALL ds_crear_fk('fk_asigna_alumno',      'asistencia_asignahorario',  'asignahorario_alumnoid',  'sujeto_alumno',      'alumno_id',  'CASCADE');
CALL ds_crear_fk('fk_asigna_horario',     'asistencia_asignahorario',  'asignahorario_horarioid', 'asistencia_horario', 'horario_id', 'RESTRICT');
CALL ds_crear_fk('fk_detalle_horario',    'asistencia_horario_detalle','detalle_horarioid',       'asistencia_horario', 'horario_id', 'CASCADE');

-- --- Arena: las que no se pudieron crear cuando general_sede era MyISAM ---
CALL ds_crear_fk('fk_instalacion_sede',   'dsa_instalacion', 'instalacion_sedeid', 'general_sede', 'sede_id', 'RESTRICT');
CALL ds_crear_fk('fk_reserva_sede',       'dsa_reserva',     'reserva_sedeid',     'general_sede', 'sede_id', 'RESTRICT');

DROP PROCEDURE IF EXISTS ds_crear_fk;

-- ---------------------------------------------------------------------
-- Verificación
-- ---------------------------------------------------------------------
SELECT '--- huerfanos DESPUES ---' AS info;
SELECT
  (SELECT COUNT(1) FROM seguridad_usuario_sede us
     LEFT JOIN seguridad_usuario u ON u.usuario_id = us.usuariosede_usuarioid
    WHERE u.usuario_id IS NULL) AS usuario_sede,
  (SELECT COUNT(1) FROM asistencia_asignahorario ah
     LEFT JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
    WHERE h.horario_id IS NULL) AS asignaciones,
  (SELECT COUNT(1) FROM asistencia_horario_detalle hd
     LEFT JOIN asistencia_horario h ON h.horario_id = hd.detalle_horarioid
    WHERE h.horario_id IS NULL) AS detalles;

SELECT '--- claves foraneas declaradas ---' AS info;
SELECT COUNT(1) AS total FROM information_schema.table_constraints
 WHERE constraint_schema = DATABASE() AND constraint_type = 'FOREIGN KEY';

SELECT table_name AS tabla, constraint_name AS restriccion
  FROM information_schema.table_constraints
 WHERE constraint_schema = DATABASE() AND constraint_type = 'FOREIGN KEY'
 ORDER BY table_name, constraint_name;
