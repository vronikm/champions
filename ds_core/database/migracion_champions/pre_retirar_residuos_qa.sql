-- Antes de 000: retira de la copia de SoccerEasy lo que dejó en ella el
-- arnés de pruebas de DigiSports.
--
-- El 2026-10-21 el arnés corrió por error contra digitech_migra_champions_cf
-- (la copia de SoccerEasy a migrar) y dejó en ella:
--   · alumnos y representante de prueba, con identificación 99000000xx;
--     el código de provincia 99 no existe en Ecuador, así que la marca no
--     puede coincidir con una persona real;
--   · su contacto de emergencia, ficha médica y asignación de horario;
--   · el usuario «qa2fatester» (rol superadministrador);
--   · la tabla carnet_configuracion, que el sistema crea solo la primera vez
--     que se abre la pantalla de carnés. SoccerEasy no la tiene: la crea 000
--     con el cotejamiento correcto y, si ya existe, 000 aborta.
--
-- Sobre una copia limpia de SoccerEasy no encuentra nada y no cambia nada.
SET NAMES utf8mb4;

DELETE FROM alumno_cemergencia
 WHERE cemer_alumnoid IN (SELECT alumno_id FROM sujeto_alumno WHERE alumno_identificacion LIKE '99000000%');
DELETE FROM alumno_infomedic
 WHERE infomedic_alumnoid IN (SELECT alumno_id FROM sujeto_alumno WHERE alumno_identificacion LIKE '99000000%');
DELETE FROM asistencia_asignahorario
 WHERE asignahorario_alumnoid IN (SELECT alumno_id FROM sujeto_alumno WHERE alumno_identificacion LIKE '99000000%');
DELETE FROM asistencia_asistencia
 WHERE asistencia_alumnoid IN (SELECT alumno_id FROM sujeto_alumno WHERE alumno_identificacion LIKE '99000000%');
DELETE FROM alumno_pago
 WHERE pago_alumnoid IN (SELECT alumno_id FROM sujeto_alumno WHERE alumno_identificacion LIKE '99000000%');
DELETE FROM sujeto_alumno         WHERE alumno_identificacion LIKE '99000000%';
DELETE FROM alumno_representante  WHERE repre_identificacion  LIKE '99000000%';

DELETE FROM seguridad_usuario_sede
 WHERE usuariosede_usuarioid IN (SELECT usuario_id FROM seguridad_usuario WHERE usuario_usuario = 'qa2fatester');
DELETE FROM seguridad_usuario WHERE usuario_usuario = 'qa2fatester';

DROP TABLE IF EXISTS carnet_configuracion;
