-- =====================================================================
-- 020 · League · Comprobantes electrónicos
-- =====================================================================
-- Tablas propias para los comprobantes emitidos desde League, con el
-- mismo juego de campos del SRI que usa Basketball, de modo que el motor
-- de firma, envío y RIDE pueda procesarlas sin conocer el módulo.
--
-- POR QUÉ TABLAS PROPIAS Y NO LAS DE BASKETBALL
--
-- facturas_electronicas tiene alumno_id y representante_id NOT NULL: no
-- admite un origen que no sea un alumno de la escuela. Lo que League
-- factura es otra cosa —la inscripción de un equipo, una multa, el
-- arbitraje de una jornada— y forzarlo dentro de esa estructura
-- obligaría a inventar alumnos que no existen.
--
-- Separarlas es seguro porque cada módulo emite desde su propio punto
-- (migración 017) y el SRI numera por (tipo, establecimiento, punto). Las
-- dos series no pueden pisarse. Lo que NO se duplica es la identidad
-- tributaria ni el certificado: son del contribuyente y siguen en el
-- Core, uno solo.
--
-- POR QUÉ LOS DATOS DEL CLIENTE SE COPIAN AQUÍ
--
-- Un comprobante es un documento fiscal: debe conservar lo que decía el
-- día que se emitió. Si la dirección o la razón social se leyeran por
-- clave foránea, cambiar el domicilio de un representante reescribiría
-- retroactivamente facturas ya autorizadas por el SRI. Por eso se copian
-- al emitir y no se vuelven a tocar. No es redundancia: es la diferencia
-- entre un documento y una consulta.
-- =====================================================================

-- La conexion del cliente puede venir en cp850 (consola de Windows), y
-- entonces un literal de texto no admite un cotejamiento utf8mb4. Se fija
-- aqui para que la vista del final pueda declararlo.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS dsl_factura (
    factura_id              INT AUTO_INCREMENT PRIMARY KEY,

    -- ----- Qué se está cobrando -----
    -- El origen es polimórfico DENTRO de League: una inscripción de
    -- equipo, una multa, el arbitraje de una jornada, el uso de un
    -- escenario. Sin clave foránea porque apunta a tablas distintas; la
    -- integridad la garantiza el servicio que emite.
    factura_origentipo      ENUM('INSCRIPCION','MULTA','ARBITRAJE','ESCENARIO','OTRO')
                            NOT NULL DEFAULT 'OTRO',
    factura_origenid        INT          NULL,
    factura_concepto        VARCHAR(250) NOT NULL DEFAULT '',

    -- Punto de emisión desde el que salió. Se guarda el id además del
    -- par establecimiento/punto para que renumerar un punto no deje
    -- huérfanos los comprobantes ya emitidos desde él.
    factura_puntoid         INT          NULL,

    -- ----- Numeración del SRI -----
    factura_claveacceso     VARCHAR(49)  NOT NULL,
    factura_tipocomprobante CHAR(2)      NOT NULL DEFAULT '01',
    factura_establecimiento CHAR(3)      NOT NULL,
    factura_puntoemision    CHAR(3)      NOT NULL,
    factura_secuencial      CHAR(9)      NOT NULL,
    factura_fechaemision    DATE         NOT NULL,
    factura_ambiente        CHAR(1)      NOT NULL DEFAULT '1',
    factura_tipoemision     CHAR(1)      NOT NULL DEFAULT '1',

    -- ----- Cliente, congelado al emitir -----
    factura_clienteidtipo   CHAR(2)      NOT NULL DEFAULT '05',
    factura_clienteid_num   VARCHAR(20)  NOT NULL,
    factura_clienterazon    VARCHAR(300) NOT NULL,
    factura_clientedir      VARCHAR(300) NOT NULL DEFAULT '',
    factura_clienteemail    VARCHAR(200) NOT NULL DEFAULT '',
    factura_clientetel      VARCHAR(50)  NOT NULL DEFAULT '',

    -- ----- Importes -----
    factura_subtotaliva     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    factura_subtotal0       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    factura_subtotalnoobj   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    factura_subtotalexento  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    factura_subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    factura_descuento       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    factura_iva             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    factura_total           DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    -- ----- Ciclo del SRI -----
    -- Los mismos valores que usa Basketball, para que el motor de envío
    -- no tenga que traducir estados según de dónde venga el comprobante.
    factura_estadosri       ENUM('PENDIENTE','GENERADA','FIRMADA','ENVIADA','RECIBIDA',
                                 'DEVUELTA','AUTORIZADO','NO_AUTORIZADO','ERROR','ANULADA')
                            NOT NULL DEFAULT 'PENDIENTE',
    factura_xmlgenerado     MEDIUMTEXT   NULL,
    factura_xmlfirmado      MEDIUMTEXT   NULL,
    factura_xmlautorizado   MEDIUMTEXT   NULL,
    factura_ridehtml        MEDIUMTEXT   NULL,
    factura_numautorizacion VARCHAR(49)  NULL,
    factura_fechaautoriza   DATETIME     NULL,
    factura_mensajeerror    TEXT         NULL,

    factura_usuarioid       INT          NULL,
    factura_fecharegistro   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    factura_fechacambio     TIMESTAMP    NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_dslf_clave  (factura_claveacceso),

    -- La misma restricción que impone el SRI, aplicada aquí para que un
    -- duplicado muera en el INSERT y no en la respuesta del organismo.
    UNIQUE KEY uk_dslf_numero (factura_tipocomprobante, factura_establecimiento,
                               factura_puntoemision, factura_secuencial),

    KEY ix_dslf_origen (factura_origentipo, factura_origenid),
    KEY ix_dslf_estado (factura_estadosri, factura_fechaemision),
    KEY ix_dslf_cliente (factura_clienteid_num),
    KEY ix_dslf_fecha  (factura_fechaemision),

    CONSTRAINT fk_dslf_punto FOREIGN KEY (factura_puntoid)
        REFERENCES facturas_electronicas_punto_emision (punto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Detalle
--
-- Los importes se guardan por línea y no se recalculan al leer: el total
-- del comprobante es el que se firmó y envió, aunque la tarifa del IVA
-- cambie después.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_factura_detalle (
    detalle_id            INT AUTO_INCREMENT PRIMARY KEY,
    detalle_facturaid     INT          NOT NULL,

    detalle_codigo        VARCHAR(25)  NOT NULL DEFAULT '',
    detalle_descripcion   VARCHAR(300) NOT NULL,
    detalle_cantidad      DECIMAL(14,6) NOT NULL DEFAULT 1.000000,
    detalle_preciounit    DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
    detalle_descuento     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    detalle_subtotal      DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    -- Código de porcentaje de IVA del SRI (0 = 0%, 2 = 12%, 4 = 15%...).
    detalle_ivacodigo     CHAR(1)      NOT NULL DEFAULT '0',
    detalle_ivatarifa     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    detalle_ivavalor      DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    detalle_orden         SMALLINT     NOT NULL DEFAULT 0,

    KEY ix_dslfd_factura (detalle_facturaid, detalle_orden),

    CONSTRAINT fk_dslfd_factura FOREIGN KEY (detalle_facturaid)
        REFERENCES dsl_factura (factura_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Formas de pago declaradas en el comprobante
--
-- Es un dato del XML del SRI, no el registro contable del cobro: una
-- factura puede declarar dos formas de pago y cobrarse en tres momentos.
-- El cobro real vivirá en las obligaciones de League, que llegan con el
-- subsistema financiero.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_factura_pago (
    pago_id         INT AUTO_INCREMENT PRIMARY KEY,
    pago_facturaid  INT           NOT NULL,

    -- Código del catálogo de formas de pago del SRI (01, 20, 19...).
    pago_forma      CHAR(2)       NOT NULL DEFAULT '20',
    pago_valor      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    pago_plazo      INT           NULL,
    pago_unidad     VARCHAR(10)   NULL,

    KEY ix_dslfp_factura (pago_facturaid),

    CONSTRAINT fk_dslfp_factura FOREIGN KEY (pago_facturaid)
        REFERENCES dsl_factura (factura_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =====================================================================
-- Vista consolidada de comprobantes del ecosistema
--
-- Separar las tablas por módulo tiene un coste: «¿cuánto facturó la
-- organización este mes?» deja de ser una consulta y pasa a ser una
-- unión. Esta vista paga ese coste una sola vez y en un solo sitio, para
-- que Insights y el área financiera no tengan que conocer cuántos
-- módulos emiten ni cómo se llaman sus tablas.
--
-- Es de sólo lectura por definición: cada módulo escribe en la suya.
--
-- EL COTEJAMIENTO SE FUERZA EN CADA COLUMNA DE TEXTO
--
-- facturas_electronicas es utf8mb4_unicode_ci y las tablas nuevas son
-- utf8mb4_0900_ai_ci. Un UNION entre columnas de cotejamientos distintos
-- no resuelve: MySQL responde «Illegal mix of collations». Se declara
-- uno explícito en ambos lados en lugar de igualar las tablas, para que
-- la vista siga funcionando aunque una de ellas cambie de cotejamiento
-- más adelante.
-- =====================================================================
CREATE OR REPLACE VIEW v_comprobante_emitido AS
    SELECT CAST('basketball' AS CHAR(20))  COLLATE utf8mb4_0900_ai_ci AS origen_modulo,
           F.id                                                       AS origen_id,
           F.clave_acceso           COLLATE utf8mb4_0900_ai_ci        AS clave_acceso,
           F.tipo_comprobante       COLLATE utf8mb4_0900_ai_ci        AS tipo_comprobante,
           F.establecimiento        COLLATE utf8mb4_0900_ai_ci        AS establecimiento,
           F.punto_emision          COLLATE utf8mb4_0900_ai_ci        AS punto_emision,
           F.secuencial             COLLATE utf8mb4_0900_ai_ci        AS secuencial,
           F.fecha_emision                                            AS fecha_emision,
           F.cliente_identificacion COLLATE utf8mb4_0900_ai_ci        AS cliente_identificacion,
           F.cliente_razon_social   COLLATE utf8mb4_0900_ai_ci        AS cliente_razon_social,
           F.subtotal                                                 AS subtotal,
           F.descuento                                                AS descuento,
           F.iva                                                      AS iva,
           F.total                                                    AS total,
           CAST(F.estado_sri AS CHAR(20)) COLLATE utf8mb4_0900_ai_ci  AS estado_sri,
           F.numero_autorizacion    COLLATE utf8mb4_0900_ai_ci        AS numero_autorizacion,
           F.fecha_autorizacion                                       AS fecha_autorizacion
      FROM facturas_electronicas F

    UNION ALL

    SELECT CAST('league' AS CHAR(20))      COLLATE utf8mb4_0900_ai_ci,
           L.factura_id,
           L.factura_claveacceso           COLLATE utf8mb4_0900_ai_ci,
           L.factura_tipocomprobante       COLLATE utf8mb4_0900_ai_ci,
           L.factura_establecimiento       COLLATE utf8mb4_0900_ai_ci,
           L.factura_puntoemision          COLLATE utf8mb4_0900_ai_ci,
           L.factura_secuencial            COLLATE utf8mb4_0900_ai_ci,
           L.factura_fechaemision,
           L.factura_clienteid_num         COLLATE utf8mb4_0900_ai_ci,
           L.factura_clienterazon          COLLATE utf8mb4_0900_ai_ci,
           L.factura_subtotal,
           L.factura_descuento,
           L.factura_iva,
           L.factura_total,
           CAST(L.factura_estadosri AS CHAR(20)) COLLATE utf8mb4_0900_ai_ci,
           L.factura_numautorizacion       COLLATE utf8mb4_0900_ai_ci,
           L.factura_fechaautoriza
      FROM dsl_factura L;
