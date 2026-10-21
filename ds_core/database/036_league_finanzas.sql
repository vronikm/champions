-- =====================================================================
-- 036 · League · Obligaciones económicas y cobros
-- =====================================================================
-- Lo que League cobra no es una mensualidad: son inscripciones de equipo,
-- multas, arbitraje, uso de escenario y servicios sueltos. Cosas de
-- naturaleza distinta que comparten un ciclo común —se generan, se
-- cobran a plazos, dejan saldo y acaban en un comprobante— y por eso van
-- en una sola tabla con origen polimórfico, no en cinco.
--
-- UNA OBLIGACIÓN NO ES UN PAGO
--
-- Se separan a propósito. La obligación es lo que se debe; los pagos son
-- los abonos que la van cubriendo, y pueden ser varios, en fechas y
-- formas distintas. Guardar sólo un campo «pagado» impide responder «qué
-- se abonó el 12 de mayo», que es justo lo que se pregunta cuando alguien
-- reclama.
--
-- EL SALDO NO SE ALMACENA
--
-- Se deriva de valor + recargo − descuento − abonos. Un saldo guardado se
-- desincroniza en cuanto un pago se anula, y entonces el sistema afirma
-- una deuda que no existe con la misma seguridad con que afirmaría una
-- correcta. Se calcula, y se indexa lo que hace falta para que sea
-- barato.
--
-- LA FACTURA ES OTRA COSA
--
-- Una obligación cobrada puede facturarse o no, y una factura puede
-- cubrir varias obligaciones. Por eso el enlace va de la obligación a la
-- factura (dsl_factura, migración 020) y no al revés, y admite nulo: hay
-- cobros que no generan comprobante electrónico.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Conceptos cobrables
--
-- Catálogo en vez de un ENUM: una liga que empieza a cobrar «carné de
-- jugador» no debería necesitar una migración para hacerlo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_concepto (
    concepto_id       INT AUTO_INCREMENT PRIMARY KEY,
    concepto_codigo   VARCHAR(20)  NOT NULL,
    concepto_nombre   VARCHAR(80)  NOT NULL,

    -- A qué se asocia: 'INSCRIPCION' | 'EQUIPO' | 'PERSONA' | 'PARTIDO'
    -- Decide contra qué se puede emitir y qué formulario se ofrece.
    concepto_ambito   VARCHAR(20)  NOT NULL DEFAULT 'INSCRIPCION',

    concepto_valor    DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    -- Si el concepto lleva IVA. Se guarda el CÓDIGO del SRI, no el
    -- porcentaje: la tarifa cambia por ley y el código no.
    concepto_ivacodigo CHAR(1)     NOT NULL DEFAULT '0',

    concepto_activo   CHAR(1)      NOT NULL DEFAULT 'S',
    concepto_orden    SMALLINT     NOT NULL DEFAULT 0,

    UNIQUE KEY uk_dslc_codigo (concepto_codigo),
    KEY ix_dslc_ambito (concepto_ambito, concepto_activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Obligaciones
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_obligacion (
    obligacion_id        INT AUTO_INCREMENT PRIMARY KEY,
    obligacion_conceptoid INT     NOT NULL,

    -- Origen polimórfico: a qué se refiere esta deuda. Sin clave foránea
    -- porque apunta a tablas distintas según el ámbito; la integridad la
    -- garantiza el servicio que la crea.
    obligacion_origentipo VARCHAR(20) NOT NULL,
    obligacion_origenid   INT         NOT NULL,

    -- A quién se le cobra. Se guarda el nombre además del vínculo porque
    -- un recibo debe seguir diciendo a quién se emitió aunque el equipo
    -- se renombre después.
    obligacion_equipoid  INT          NULL,
    obligacion_personaid INT          NULL,
    obligacion_deudor    VARCHAR(150) NOT NULL DEFAULT '',

    obligacion_detalle   VARCHAR(250) NOT NULL DEFAULT '',
    obligacion_fecha     DATE         NOT NULL,
    obligacion_vence     DATE         NULL,

    obligacion_valor     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    obligacion_descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    obligacion_recargo   DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    -- 'PENDIENTE' | 'PARCIAL' | 'PAGADA' | 'ANULADA'
    -- Se guarda aunque sea derivable de los abonos: es lo que se filtra en
    -- los listados de cobranza, y recalcularlo en cada fila de un listado
    -- de mil obligaciones no sale gratis. Lo recalcula el servicio en cada
    -- pago, nunca se edita a mano.
    obligacion_estado    VARCHAR(12)  NOT NULL DEFAULT 'PENDIENTE',

    -- Comprobante que la respalda, si se emitió.
    obligacion_facturaid INT          NULL,

    obligacion_usuarioid INT          NULL,
    obligacion_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_dslo_origen  (obligacion_origentipo, obligacion_origenid),
    KEY ix_dslo_estado  (obligacion_estado, obligacion_vence),
    KEY ix_dslo_equipo  (obligacion_equipoid),
    KEY ix_dslo_factura (obligacion_facturaid),

    CONSTRAINT fk_dslo_concepto FOREIGN KEY (obligacion_conceptoid)
        REFERENCES dsl_concepto (concepto_id),
    CONSTRAINT fk_dslo_equipo FOREIGN KEY (obligacion_equipoid)
        REFERENCES dsl_equipo (equipo_id),
    CONSTRAINT fk_dslo_persona FOREIGN KEY (obligacion_personaid)
        REFERENCES dsl_persona (persona_id),
    CONSTRAINT fk_dslo_factura FOREIGN KEY (obligacion_facturaid)
        REFERENCES dsl_factura (factura_id),

    -- Un descuento mayor que el valor deja una deuda negativa, que no
    -- significa nada y rompe cualquier suma posterior.
    CONSTRAINT ck_dslo_descuento CHECK (obligacion_descuento <= obligacion_valor),
    CONSTRAINT ck_dslo_positivos CHECK (obligacion_valor >= 0
                                    AND obligacion_descuento >= 0
                                    AND obligacion_recargo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Abonos
--
-- Varios por obligación. Anular un pago no lo borra: se marca, para que
-- el histórico siga contando lo que pasó.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_abono (
    abono_id           INT AUTO_INCREMENT PRIMARY KEY,
    abono_obligacionid INT           NOT NULL,

    abono_fecha        DATE          NOT NULL,
    abono_valor        DECIMAL(10,2) NOT NULL,

    -- Código del catálogo de formas de pago del SRI (01 efectivo,
    -- 20 transferencia…). Se reutiliza el del ecosistema en vez de
    -- inventar otro.
    abono_forma        CHAR(2)       NOT NULL DEFAULT '01',
    abono_referencia   VARCHAR(60)   NOT NULL DEFAULT '',
    abono_observacion  VARCHAR(250)  NOT NULL DEFAULT '',

    abono_anulado      CHAR(1)       NOT NULL DEFAULT 'N',
    abono_motivoanula  VARCHAR(250)  NOT NULL DEFAULT '',

    abono_usuarioid    INT           NULL,
    abono_fecharegistro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_dsla_obligacion (abono_obligacionid, abono_anulado),
    KEY ix_dsla_fecha (abono_fecha),

    CONSTRAINT fk_dsla_obligacion FOREIGN KEY (abono_obligacionid)
        REFERENCES dsl_obligacion (obligacion_id) ON DELETE CASCADE,

    -- Un abono de cero o negativo no es un abono.
    CONSTRAINT ck_dsla_valor CHECK (abono_valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Conceptos de arranque para baloncesto.
-- ---------------------------------------------------------------------
INSERT INTO dsl_concepto (concepto_codigo, concepto_nombre, concepto_ambito,
                          concepto_valor, concepto_orden) VALUES
    ('INSC_EQUIPO', 'Inscripción de equipo',   'INSCRIPCION', 0.00, 10),
    ('INSC_JUGADOR','Inscripción de jugador',  'PERSONA',     0.00, 20),
    ('CARNE',       'Carné de jugador',        'PERSONA',     0.00, 30),
    ('ARBITRAJE',   'Arbitraje',               'PARTIDO',     0.00, 40),
    ('ESCENARIO',   'Uso de escenario',        'PARTIDO',     0.00, 50),
    ('MULTA',       'Multa',                   'EQUIPO',      0.00, 60),
    ('OTRO',        'Otro concepto',           'EQUIPO',      0.00, 90)
ON DUPLICATE KEY UPDATE concepto_nombre = VALUES(concepto_nombre);


-- ---------------------------------------------------------------------
-- Vista de saldos.
--
-- El saldo se deriva aquí y no se guarda: valor + recargo − descuento
-- menos los abonos que no estén anulados. Tenerlo en una vista permite
-- consultarlo sin repetir la fórmula en cada consulta, que es como una
-- de las copias acaba quedándose atrás.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_dsl_saldo AS
    SELECT O.obligacion_id,
           O.obligacion_conceptoid,
           C.concepto_nombre,
           O.obligacion_origentipo,
           O.obligacion_origenid,
           O.obligacion_equipoid,
           O.obligacion_deudor,
           O.obligacion_detalle,
           O.obligacion_fecha,
           O.obligacion_vence,
           O.obligacion_estado,
           O.obligacion_facturaid,
           (O.obligacion_valor + O.obligacion_recargo - O.obligacion_descuento) AS total,
           COALESCE(A.abonado, 0) AS abonado,
           (O.obligacion_valor + O.obligacion_recargo - O.obligacion_descuento)
             - COALESCE(A.abonado, 0) AS saldo,
           CASE WHEN O.obligacion_vence IS NOT NULL
                 AND O.obligacion_vence < CURDATE()
                 AND O.obligacion_estado IN ('PENDIENTE', 'PARCIAL')
                THEN DATEDIFF(CURDATE(), O.obligacion_vence) ELSE 0 END AS dias_vencido
      FROM dsl_obligacion O
      JOIN dsl_concepto   C ON C.concepto_id = O.obligacion_conceptoid
      LEFT JOIN (SELECT abono_obligacionid, SUM(abono_valor) AS abonado
                   FROM dsl_abono
                  WHERE abono_anulado = 'N'
                  GROUP BY abono_obligacionid) A
             ON A.abono_obligacionid = O.obligacion_id;
