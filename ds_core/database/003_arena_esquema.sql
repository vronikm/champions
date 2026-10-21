-- =====================================================================
-- DigiSports Arena · Esquema de instalaciones, reservas y monedero
-- =====================================================================
-- Todas las tablas propias del modulo llevan el prefijo dsa_ para que
-- queden agrupadas en la base. Se reutilizan las tablas compartidas del
-- ecosistema: general_sede (sedes) y seguridad_usuario (auditoria).
--
-- Modelo:
--   sede -> instalacion -> horario / bloqueo / tarifa
--   cliente -> reserva -> pago
--   cliente -> monedero -> movimiento
--
-- NOTA SOBRE INTEGRIDAD REFERENCIAL
-- Las tablas heredadas del sistema (general_sede, seguridad_usuario,
-- alumno_representante...) son MyISAM, motor que no admite claves
-- foraneas ni transacciones. Por eso:
--   · Las tablas dsa_ son InnoDB y SI tienen FK entre ellas: el monedero
--     exige que actualizar el saldo y registrar el movimiento sean
--     atomicos, y eso requiere transacciones.
--   · Las referencias hacia tablas heredadas (sedeid, usuarioid, repreid)
--     quedan como indices y se validan en la aplicacion.
-- Migrar el resto del sistema a InnoDB es una decision aparte.
--
-- Idempotente.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. general_sede: para que sirve cada sede
-- ---------------------------------------------------------------------
-- Distingue las sedes de la escuela de formacion, las que solo se
-- alquilan, y las que hacen ambas cosas. Arena sólo ofrece las que
-- incluyen alquiler.
--
-- El campo sigue el prefijo sede_ del resto de la tabla. NO se llama
-- tipo_ingreso a secas porque ya existe un catalogo con ese nombre
-- (general_tabla id 11) que significa otra cosa: el tipo de ingreso de
-- un empleado (honorarios, horas extras, reconocimiento).

SET @existe := (SELECT COUNT(1) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = 'general_sede'
                   AND column_name  = 'sede_tipoingreso');

SET @sql := IF(@existe = 0,
    'ALTER TABLE general_sede
       ADD COLUMN sede_tipoingreso CHAR(3) NOT NULL DEFAULT ''STF'' AFTER sede_nombre',
    'SELECT ''general_sede.sede_tipoingreso ya existe'' AS aviso');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Catalogo del nuevo campo, dentro del mecanismo general del sistema
-- (general_tabla + general_tabla_catalogo), para que se administre desde
-- la pantalla de Catalogos que ya existe.
INSERT INTO general_tabla (tabla_nombre, tabla_estado)
SELECT 'sede_tipoingreso', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM general_tabla WHERE tabla_nombre = 'sede_tipoingreso');

SET @tabla := (SELECT tabla_id FROM general_tabla WHERE tabla_nombre = 'sede_tipoingreso');

INSERT INTO general_tabla_catalogo (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
VALUES ('STF', @tabla, 'Formativa',            'A'),
       ('STA', @tabla, 'Alquiler',             'A'),
       ('STM', @tabla, 'Formativa y Alquiler', 'A')
ON DUPLICATE KEY UPDATE catalogo_descripcion = VALUES(catalogo_descripcion),
                        catalogo_tablaid     = VALUES(catalogo_tablaid),
                        catalogo_estado      = 'A';

-- ---------------------------------------------------------------------
-- 1. Catalogos propios de Arena
-- ---------------------------------------------------------------------

-- Origen del dinero de un pago. Se separa de general forma_pago porque
-- Arena necesita el Monedero como origen y no usa los valores propios de
-- las pensiones de la escuela.
CREATE TABLE IF NOT EXISTS dsa_forma_ingreso (
    forma_id        INT AUTO_INCREMENT PRIMARY KEY,
    forma_codigo    CHAR(3)      NOT NULL,
    forma_nombre    VARCHAR(40)  NOT NULL,
    -- 'S' marca la forma que descuenta del saldo del monedero del cliente
    forma_esmonedero CHAR(1)     NOT NULL DEFAULT 'N',
    forma_requiereref CHAR(1)    NOT NULL DEFAULT 'N',
    forma_orden     INT          NOT NULL DEFAULT 0,
    forma_estado    CHAR(1)      NOT NULL DEFAULT 'A',
    UNIQUE KEY uq_forma_codigo (forma_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO dsa_forma_ingreso (forma_codigo, forma_nombre, forma_esmonedero, forma_requiereref, forma_orden)
VALUES ('EFE', 'Efectivo',            'N', 'N', 1),
       ('TRA', 'Transferencia',       'N', 'S', 2),
       ('TAR', 'Tarjeta de crédito',  'N', 'S', 3),
       ('MON', 'Monedero',            'S', 'N', 4)
ON DUPLICATE KEY UPDATE forma_nombre      = VALUES(forma_nombre),
                        forma_esmonedero  = VALUES(forma_esmonedero),
                        forma_requiereref = VALUES(forma_requiereref),
                        forma_orden       = VALUES(forma_orden);

-- Tipo de piso de las canchas.
CREATE TABLE IF NOT EXISTS dsa_tipo_piso (
    piso_id     INT AUTO_INCREMENT PRIMARY KEY,
    piso_nombre VARCHAR(40) NOT NULL,
    piso_detalle VARCHAR(120) NULL,
    piso_estado CHAR(1)     NOT NULL DEFAULT 'A',
    UNIQUE KEY uq_piso_nombre (piso_nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO dsa_tipo_piso (piso_nombre, piso_detalle)
VALUES ('Duela de madera',   'Parquet deportivo, uso profesional'),
       ('Sintético',         'Superficie sintética de alto tráfico'),
       ('Cemento pulido',    'Hormigón pulido para exteriores'),
       ('Caucho',            'Superficie de caucho amortiguada')
ON DUPLICATE KEY UPDATE piso_detalle = VALUES(piso_detalle);

-- ---------------------------------------------------------------------
-- 2. Instalaciones
-- ---------------------------------------------------------------------
-- Una instalacion es cualquier recurso alquilable: una cancha o una
-- residencia. Los campos de cancha (cubierta, piso) quedan nulos en las
-- residencias.
CREATE TABLE IF NOT EXISTS dsa_instalacion (
    instalacion_id        INT AUTO_INCREMENT PRIMARY KEY,
    instalacion_sedeid    INT           NOT NULL,
    -- 'C' cancha · 'R' residencia
    instalacion_clase     CHAR(1)       NOT NULL DEFAULT 'C',
    instalacion_codigo    VARCHAR(20)   NOT NULL,
    instalacion_nombre    VARCHAR(80)   NOT NULL,
    -- Sólo canchas: 'S' cubierta · 'N' descubierta
    instalacion_cubierta  CHAR(1)       NULL,
    instalacion_pisoid    INT           NULL,
    instalacion_capacidad INT           NULL,
    -- Tarifa base por hora; dsa_tarifa puede sobreescribirla por franja
    instalacion_valorhora DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    instalacion_detalle   VARCHAR(250)  NULL,
    instalacion_foto      VARCHAR(80)   NULL,
    instalacion_estado    CHAR(1)       NOT NULL DEFAULT 'A',
    instalacion_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_instalacion_codigo (instalacion_sedeid, instalacion_codigo),
    -- general_sede es MyISAM: la referencia se valida en la aplicacion
    KEY idx_instalacion_sede (instalacion_sedeid),
    CONSTRAINT fk_instalacion_piso FOREIGN KEY (instalacion_pisoid)
        REFERENCES dsa_tipo_piso (piso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. Disponibilidad semanal
-- ---------------------------------------------------------------------
-- Franjas en las que la instalacion se ofrece al cliente. Fuera de estas
-- franjas no se puede reservar.
CREATE TABLE IF NOT EXISTS dsa_horario (
    horario_id           INT AUTO_INCREMENT PRIMARY KEY,
    horario_instalacionid INT    NOT NULL,
    -- 1 = lunes … 7 = domingo (ISO-8601)
    horario_dia          TINYINT NOT NULL,
    horario_desde        TIME    NOT NULL,
    horario_hasta        TIME    NOT NULL,
    horario_estado       CHAR(1) NOT NULL DEFAULT 'A',
    KEY idx_horario_instalacion (horario_instalacionid, horario_dia),
    CONSTRAINT fk_horario_instalacion FOREIGN KEY (horario_instalacionid)
        REFERENCES dsa_instalacion (instalacion_id) ON DELETE CASCADE,
    CONSTRAINT ck_horario_dia   CHECK (horario_dia BETWEEN 1 AND 7),
    CONSTRAINT ck_horario_rango CHECK (horario_hasta > horario_desde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. Bloqueos (mantenimiento y otros)
-- ---------------------------------------------------------------------
-- Intervalos concretos en los que la instalacion NO se puede reservar,
-- aunque caigan dentro de su horario habitual.
CREATE TABLE IF NOT EXISTS dsa_bloqueo (
    bloqueo_id            INT AUTO_INCREMENT PRIMARY KEY,
    bloqueo_instalacionid INT       NOT NULL,
    -- 'M' mantenimiento · 'E' evento propio · 'O' otro
    bloqueo_tipo          CHAR(1)   NOT NULL DEFAULT 'M',
    bloqueo_inicio        DATETIME  NOT NULL,
    bloqueo_fin           DATETIME  NOT NULL,
    bloqueo_motivo        VARCHAR(150) NULL,
    bloqueo_usuarioid     INT       NULL,
    bloqueo_estado        CHAR(1)   NOT NULL DEFAULT 'A',
    bloqueo_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bloqueo_instalacion (bloqueo_instalacionid, bloqueo_inicio, bloqueo_fin),
    CONSTRAINT fk_bloqueo_instalacion FOREIGN KEY (bloqueo_instalacionid)
        REFERENCES dsa_instalacion (instalacion_id) ON DELETE CASCADE,
    CONSTRAINT ck_bloqueo_rango CHECK (bloqueo_fin > bloqueo_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. Tarifas por franja (opcional)
-- ---------------------------------------------------------------------
-- Sobreescribe instalacion_valorhora en dias y horas concretos. Si no hay
-- tarifa aplicable se usa la tarifa base de la instalacion.
CREATE TABLE IF NOT EXISTS dsa_tarifa (
    tarifa_id            INT AUTO_INCREMENT PRIMARY KEY,
    tarifa_instalacionid INT           NOT NULL,
    tarifa_nombre        VARCHAR(60)   NOT NULL,
    tarifa_dia           TINYINT       NULL,   -- NULL = todos los días
    tarifa_desde         TIME          NOT NULL,
    tarifa_hasta         TIME          NOT NULL,
    tarifa_valorhora     DECIMAL(10,2) NOT NULL,
    tarifa_vigenciadesde DATE          NULL,
    tarifa_vigenciahasta DATE          NULL,
    tarifa_estado        CHAR(1)       NOT NULL DEFAULT 'A',
    KEY idx_tarifa_instalacion (tarifa_instalacionid, tarifa_dia),
    CONSTRAINT fk_tarifa_instalacion FOREIGN KEY (tarifa_instalacionid)
        REFERENCES dsa_instalacion (instalacion_id) ON DELETE CASCADE,
    CONSTRAINT ck_tarifa_rango CHECK (tarifa_hasta > tarifa_desde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. Clientes
-- ---------------------------------------------------------------------
-- Arena alquila al publico, no solo a representantes de la escuela. Si el
-- cliente ya es representante se enlaza para no duplicar sus datos.
CREATE TABLE IF NOT EXISTS dsa_cliente (
    cliente_id        INT AUTO_INCREMENT PRIMARY KEY,
    cliente_repreid   INT          NULL,
    cliente_tipoid    CHAR(3)      NOT NULL DEFAULT 'TDC',
    cliente_identificacion VARCHAR(20) NOT NULL,
    cliente_nombre    VARCHAR(120) NOT NULL,
    cliente_correo    VARCHAR(80)  NULL,
    cliente_celular   VARCHAR(20)  NULL,
    cliente_direccion VARCHAR(200) NULL,
    cliente_estado    CHAR(1)      NOT NULL DEFAULT 'A',
    cliente_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cliente_identificacion (cliente_identificacion),
    KEY idx_cliente_repre (cliente_repreid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. Reservas
-- ---------------------------------------------------------------------
-- El importe se congela al reservar (valorhora y total) para que un cambio
-- posterior de tarifa no altere reservas ya pactadas.
CREATE TABLE IF NOT EXISTS dsa_reserva (
    reserva_id            INT AUTO_INCREMENT PRIMARY KEY,
    reserva_codigo        VARCHAR(20)   NOT NULL,
    reserva_clienteid     INT           NOT NULL,
    reserva_instalacionid INT           NOT NULL,
    reserva_sedeid        INT           NOT NULL,
    reserva_fecha         DATE          NOT NULL,
    reserva_horainicio    TIME          NOT NULL,
    reserva_horafin       TIME          NOT NULL,
    reserva_horas         DECIMAL(5,2)  NOT NULL,
    reserva_valorhora     DECIMAL(10,2) NOT NULL,
    reserva_total         DECIMAL(10,2) NOT NULL,
    -- Se mantienen al registrar cada pago; saldo = total - abonado
    reserva_abonado       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reserva_saldo         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    -- 'P' pendiente · 'C' confirmada · 'U' cumplida · 'X' cancelada
    reserva_estado        CHAR(1)       NOT NULL DEFAULT 'P',
    reserva_observacion   VARCHAR(250)  NULL,
    reserva_usuarioid     INT           NULL,
    reserva_fecharegistro TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reserva_codigo (reserva_codigo),
    KEY idx_reserva_agenda (reserva_instalacionid, reserva_fecha, reserva_horainicio),
    KEY idx_reserva_cliente (reserva_clienteid),
    KEY idx_reserva_sede (reserva_sedeid, reserva_fecha),
    CONSTRAINT fk_reserva_cliente FOREIGN KEY (reserva_clienteid)
        REFERENCES dsa_cliente (cliente_id),
    CONSTRAINT fk_reserva_instalacion FOREIGN KEY (reserva_instalacionid)
        REFERENCES dsa_instalacion (instalacion_id),
    -- reserva_sedeid apunta a general_sede (MyISAM): se valida en la aplicacion
    CONSTRAINT ck_reserva_rango CHECK (reserva_horafin > reserva_horainicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. Pagos y abonos de la reserva
-- ---------------------------------------------------------------------
-- Cada fila es un abono. La suma de abonos de una reserva no puede
-- superar su total; el control se lleva en reserva_abonado / reserva_saldo.
CREATE TABLE IF NOT EXISTS dsa_pago (
    pago_id          INT AUTO_INCREMENT PRIMARY KEY,
    pago_reservaid   INT           NOT NULL,
    pago_formaid     INT           NOT NULL,
    pago_valor       DECIMAL(10,2) NOT NULL,
    -- Efectivo recibido y vuelto entregado; si el vuelto queda en el
    -- monedero se registra ademas en dsa_monedero_movimiento.
    pago_recibido    DECIMAL(10,2) NULL,
    pago_vuelto      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pago_vueltoamonedero CHAR(1)   NOT NULL DEFAULT 'N',
    pago_referencia  VARCHAR(60)   NULL,
    pago_fecha       DATE          NOT NULL,
    pago_observacion VARCHAR(200)  NULL,
    pago_usuarioid   INT           NULL,
    pago_estado      CHAR(1)       NOT NULL DEFAULT 'A',
    pago_fecharegistro TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pago_reserva (pago_reservaid),
    CONSTRAINT fk_pago_reserva FOREIGN KEY (pago_reservaid)
        REFERENCES dsa_reserva (reserva_id),
    CONSTRAINT fk_pago_forma FOREIGN KEY (pago_formaid)
        REFERENCES dsa_forma_ingreso (forma_id),
    CONSTRAINT ck_pago_valor CHECK (pago_valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. Monedero del cliente
-- ---------------------------------------------------------------------
-- Saldo a favor. Se alimenta de vueltos que el cliente decide no llevarse
-- y de transferencias anticipadas; se consume al pagar reservas o cuando
-- el cliente pide que se le devuelva (egreso).
CREATE TABLE IF NOT EXISTS dsa_monedero (
    monedero_id        INT AUTO_INCREMENT PRIMARY KEY,
    monedero_clienteid INT           NOT NULL,
    monedero_saldo     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monedero_estado    CHAR(1)       NOT NULL DEFAULT 'A',
    monedero_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_monedero_cliente (monedero_clienteid),
    CONSTRAINT fk_monedero_cliente FOREIGN KEY (monedero_clienteid)
        REFERENCES dsa_cliente (cliente_id),
    CONSTRAINT ck_monedero_saldo CHECK (monedero_saldo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Libro mayor del monedero: cada fila deja el saldo antes y despues, de
-- modo que el saldo actual siempre se puede auditar contra el historico.
CREATE TABLE IF NOT EXISTS dsa_monedero_movimiento (
    movimiento_id         INT AUTO_INCREMENT PRIMARY KEY,
    movimiento_monederoid INT           NOT NULL,
    -- 'I' ingreso al monedero · 'E' egreso del monedero
    movimiento_tipo       CHAR(1)       NOT NULL,
    -- VUE vuelto · TRA transferencia · RES aplicado a reserva
    -- DEV devolucion al cliente · AJU ajuste manual
    movimiento_origen     CHAR(3)       NOT NULL,
    movimiento_valor      DECIMAL(10,2) NOT NULL,
    movimiento_saldoanterior DECIMAL(10,2) NOT NULL,
    movimiento_saldonuevo    DECIMAL(10,2) NOT NULL,
    movimiento_reservaid  INT           NULL,
    movimiento_pagoid     INT           NULL,
    movimiento_referencia VARCHAR(60)   NULL,
    movimiento_detalle    VARCHAR(200)  NULL,
    movimiento_usuarioid  INT           NULL,
    movimiento_fecha      DATE          NOT NULL,
    movimiento_fecharegistro TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_movimiento_monedero (movimiento_monederoid, movimiento_fecha),
    CONSTRAINT fk_movimiento_monedero FOREIGN KEY (movimiento_monederoid)
        REFERENCES dsa_monedero (monedero_id),
    CONSTRAINT fk_movimiento_reserva FOREIGN KEY (movimiento_reservaid)
        REFERENCES dsa_reserva (reserva_id),
    CONSTRAINT fk_movimiento_pago FOREIGN KEY (movimiento_pagoid)
        REFERENCES dsa_pago (pago_id),
    CONSTRAINT ck_movimiento_valor CHECK (movimiento_valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Verificacion
-- ---------------------------------------------------------------------
SELECT '--- tablas dsa_ creadas ---' AS info;
SELECT table_name, table_rows
  FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_name LIKE 'dsa\_%'
 ORDER BY table_name;

SELECT '--- catalogo sede_tipoingreso ---' AS info;
SELECT c.catalogo_valor, c.catalogo_descripcion
  FROM general_tabla_catalogo c
  JOIN general_tabla t ON t.tabla_id = c.catalogo_tablaid
 WHERE t.tabla_nombre = 'sede_tipoingreso';

SELECT '--- formas de ingreso de Arena ---' AS info;
SELECT forma_codigo, forma_nombre, forma_esmonedero, forma_requiereref
  FROM dsa_forma_ingreso ORDER BY forma_orden;

SELECT '--- sedes y su tipo ---' AS info;
SELECT s.sede_id, s.sede_nombre, s.sede_tipoingreso, c.catalogo_descripcion
  FROM general_sede s
  LEFT JOIN general_tabla_catalogo c ON c.catalogo_valor = s.sede_tipoingreso
  LEFT JOIN general_tabla t ON t.tabla_id = c.catalogo_tablaid AND t.tabla_nombre = 'sede_tipoingreso'
 ORDER BY s.sede_id;
