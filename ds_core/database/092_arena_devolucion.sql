-- =====================================================================
-- 092 · Arena: devoluciones de lo abonado en una reserva
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Una reserva con abonos no se podía cancelar ni pasar a un horario más
-- barato: había que «devolver primero», pero no había dónde registrar esa
-- devolución. Se hacía por fuera y el sistema no se enteraba: el abonado
-- seguía ahí y la caja no cuadraba.
--
-- Pasa cuando la reserva se registró en otro día, otra hora u otro
-- cliente, cuando el cliente pide que le devuelvan, o cuando cambia a una
-- hora de menor valor y hay que devolverle la diferencia.
--
--
-- QUE HACE ESTA MIGRACION
--
--   dsa_devolucion: una fila por devolución, con
--     · la reserva, el valor y el MEDIO por el que se devolvió:
--       EFE efectivo, TRA transferencia (con su referencia) o MON monedero
--       del cliente (enlazada a su movimiento, origen DVR);
--     · el ORIGEN: C al cancelar la reserva, E al editarla por un importe
--       menor a lo abonado;
--     · el motivo, la fecha y el usuario.
--
--   La reserva descuenta lo devuelto de reserva_abonado (lo hace la
--   aplicación, en la misma transacción). Los pagos (dsa_pago) no se
--   tocan: siguen siendo lo que el cliente pagó, con sus recibos.
--
-- Común a los cuatro sistemas (ds_core/database).
--
--
-- SE PUEDE REPETIR
--
-- La tabla se crea sólo si falta, y sólo si existe dsa_reserva.
--
--
-- SI dsa_reserva NO ES InnoDB
--
-- Hay bases en las que las tablas de Arena siguen en MyISAM (2026-11-23:
-- la copia local de ADF Pedro Larrea). MyISAM no admite claves ajenas y
-- la clave hacia dsa_reserva haría fallar toda la migración (error 1824).
-- En ese caso la tabla se crea igual, sin esa clave.
--
--
-- PHPMYADMIN Y information_schema (2026-11-24)
--
-- Al ejecutar un script, phpMyAdmin cambia la base activa a la que nombra
-- cada sentencia: después de una consulta a information_schema, la
-- conexión queda EN information_schema y el CREATE TABLE sin base explícita
-- falla con #1044 (acceso negado). Por eso la base de la escuela se guarda
-- en @db ANTES de mirar information_schema y la tabla se crea como
-- `@db`.dsa_devolucion. Hay que abrir la base de la escuela antes de ejecutar.
-- =====================================================================

SET NAMES utf8mb4;

-- La base de la escuela, antes de nombrar information_schema.
SET @db := DATABASE();
SET @sql := IF(@db IS NULL OR @db = 'information_schema',
    'SELECT * FROM abra_primero_la_base_de_la_escuela',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @falta := (SELECT COUNT(*) = 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_reserva')
          AND (SELECT COUNT(*) = 0 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_devolucion');
SET @innodb := (SELECT COUNT(*) = 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dsa_reserva' AND ENGINE = 'InnoDB');
SET @clave := IF(@innodb,
    CONCAT('CONSTRAINT fk_devolucion_reserva FOREIGN KEY (devolucion_reservaid) REFERENCES `', @db, '`.dsa_reserva (reserva_id),'), '');
SET @sql := IF(@falta,
    CONCAT("CREATE TABLE `", @db, "`.dsa_devolucion (
        devolucion_id           INT           NOT NULL AUTO_INCREMENT,
        devolucion_reservaid    INT           NOT NULL COMMENT 'dsa_reserva.reserva_id',
        devolucion_valor        DECIMAL(10,2) NOT NULL,
        devolucion_medio        CHAR(3)       NOT NULL COMMENT 'EFE efectivo, TRA transferencia, MON monedero del cliente',
        devolucion_referencia   VARCHAR(60)   NULL     COMMENT 'Comprobante de la transferencia',
        devolucion_origen       CHAR(1)       NOT NULL COMMENT 'C al cancelar, E al editar por un importe menor',
        devolucion_motivo       VARCHAR(250)  NOT NULL,
        devolucion_movimientoid INT           NULL     COMMENT 'dsa_monedero_movimiento.movimiento_id si fue al monedero',
        devolucion_fecha        DATE          NOT NULL,
        devolucion_usuarioid    INT           NULL     COMMENT 'seguridad_usuario.usuario_id de quien la registró',
        devolucion_fecharegistro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (devolucion_id),
        KEY ix_devolucion_reserva (devolucion_reservaid),
        KEY ix_devolucion_fecha (devolucion_fecha),
        ", @clave, "
        CONSTRAINT ck_devolucion_valor  CHECK (devolucion_valor > 0),
        CONSTRAINT ck_devolucion_medio  CHECK (devolucion_medio IN ('EFE','TRA','MON')),
        CONSTRAINT ck_devolucion_origen CHECK (devolucion_origen IN ('C','E'))
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
       COMMENT='Arena: devoluciones de lo abonado en una reserva (092)'"),
    'SELECT ''dsa_devolucion ya existe o no hay dsa_reserva'' AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
