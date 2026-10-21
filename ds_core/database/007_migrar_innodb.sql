-- =====================================================================
-- DigiSports · Migración del núcleo heredado a InnoDB
-- =====================================================================
-- Convierte las tablas MyISAM restantes a InnoDB. Todo lo construido
-- después del sistema original (carnets, consentimientos, facturación SRI
-- y Arena) ya estaba en InnoDB; esto uniforma el resto.
--
-- POR QUÉ
--   · Transacciones: una operación que toca varias tablas pasa a ser todo
--     o nada. Hoy, si se corta a medias, queda inconsistente.
--   · Claves foráneas: MyISAM no las admite, así que la integridad
--     dependía por completo de la aplicación.
--   · Bloqueo por fila en vez de por tabla: dos cajeros dejan de esperarse.
--   · Recuperación automática ante caídas.
--
--   Y elimina una frontera real: facturas_electronicas (InnoDB) y
--   alumno_pago (MyISAM) se cruzan en la facturación; una transacción que
--   abarcara ambas sólo revertía la mitad.
--
-- COMPROBACIONES PREVIAS (todas correctas al ejecutar)
--   · Sin índices FULLTEXT ni SPATIAL.
--   · Ningún índice supera el límite de 3072 bytes de InnoDB.
--   · Respaldo completo en c:\wamp64\respaldos\
--
-- Idempotente: convertir una tabla que ya es InnoDB no tiene efecto.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/007_migrar_innodb.sql"
-- =====================================================================

SELECT '--- ANTES ---' AS info;
SELECT engine, COUNT(1) AS tablas FROM information_schema.tables
 WHERE table_schema = DATABASE() AND engine IS NOT NULL GROUP BY engine;

-- ---------------------------------------------------------------------
-- Conversión
-- ---------------------------------------------------------------------
ALTER TABLE alumno_cemergencia            ENGINE=InnoDB;
ALTER TABLE alumno_documentos             ENGINE=InnoDB;
ALTER TABLE alumno_infomedic              ENGINE=InnoDB;
ALTER TABLE alumno_pago                   ENGINE=InnoDB;
ALTER TABLE alumno_pago_descuento         ENGINE=InnoDB;
ALTER TABLE alumno_pago_transaccion       ENGINE=InnoDB;
ALTER TABLE alumno_representante          ENGINE=InnoDB;
ALTER TABLE alumno_representanteconyuge   ENGINE=InnoDB;
ALTER TABLE asistencia_asignahorario      ENGINE=InnoDB;
ALTER TABLE asistencia_asistencia         ENGINE=InnoDB;
ALTER TABLE asistencia_hora               ENGINE=InnoDB;
ALTER TABLE asistencia_horario            ENGINE=InnoDB;
ALTER TABLE asistencia_horario_detalle    ENGINE=InnoDB;
ALTER TABLE asistencia_lugar              ENGINE=InnoDB;
ALTER TABLE balance_egreso                ENGINE=InnoDB;
ALTER TABLE balance_ingreso               ENGINE=InnoDB;
ALTER TABLE empleado_asistencia           ENGINE=InnoDB;
ALTER TABLE empleado_egreso               ENGINE=InnoDB;
ALTER TABLE empleado_egreso_trx           ENGINE=InnoDB;
ALTER TABLE empleado_ingreso              ENGINE=InnoDB;
ALTER TABLE empleado_ingreso_trx          ENGINE=InnoDB;
ALTER TABLE general_agenda                ENGINE=InnoDB;
ALTER TABLE general_escuela               ENGINE=InnoDB;
ALTER TABLE general_sede                  ENGINE=InnoDB;
ALTER TABLE general_tabla                 ENGINE=InnoDB;
ALTER TABLE general_tabla_catalogo        ENGINE=InnoDB;
ALTER TABLE seguridad_menu                ENGINE=InnoDB;
ALTER TABLE seguridad_permiso             ENGINE=InnoDB;
ALTER TABLE seguridad_rol                 ENGINE=InnoDB;
ALTER TABLE seguridad_usuario             ENGINE=InnoDB;
ALTER TABLE seguridad_usuario_sede        ENGINE=InnoDB;
ALTER TABLE sujeto_alumno                 ENGINE=InnoDB;
ALTER TABLE sujeto_empleado               ENGINE=InnoDB;
ALTER TABLE torneo_equipo                 ENGINE=InnoDB;
ALTER TABLE torneo_jugador                ENGINE=InnoDB;
ALTER TABLE torneo_torneo                 ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Verificación
-- ---------------------------------------------------------------------
SELECT '--- DESPUES ---' AS info;
SELECT engine, COUNT(1) AS tablas FROM information_schema.tables
 WHERE table_schema = DATABASE() AND engine IS NOT NULL GROUP BY engine;

SELECT '--- quedan tablas MyISAM? ---' AS info;
SELECT IFNULL(GROUP_CONCAT(table_name), 'ninguna') AS pendientes
  FROM information_schema.tables
 WHERE table_schema = DATABASE() AND engine = 'MyISAM';
