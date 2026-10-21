-- =====================================================================
-- 046 · Fotografía mensual de la cartera, para poder compararla
-- =====================================================================
-- QUÉ RESUELVE
--
-- La cartera de Basketball no está almacenada: se calcula como
--
--     meses desde el último pago × (pensión actual − descuento actual)
--
-- Los tres factores son del PRESENTE. Subir la pensión un dólar infla la
-- cartera histórica en 217 sin que nadie pague ni deje de pagar. Por eso
-- comparar la cartera de marzo con la de agosto no medía nada: eran dos
-- proyecciones hechas desde el mismo instante.
--
-- Esta tabla guarda el valor tal como se vio en su momento. Es la
-- excepción que el propio encargo admite en su §41: no se duplica un dato
-- calculable porque sí, se conserva un histórico que de otro modo se
-- pierde.
--
--
-- DOS CARTERAS, Y NO SON LA MISMA
--
-- Al diseñar esto apareció que en Basketball hay dos cifras distintas que
-- se llaman igual, y difieren en un factor de 124:
--
--   REGISTRADA   SUM(alumno_pago.pago_saldo)          =    35,00
--                Lo que consta como pendiente en pagos ya emitidos.
--                Es un HECHO: está escrito en la fila.
--
--   PROYECTADA   meses transcurridos × pensión         = 4 340,02
--                Lo que se infiere que deberían haber pagado y no pagaron.
--                Es una ESTIMACIÓN, y depende del precio de hoy.
--
-- Guardar sólo una habría escondido la otra, y presentarlas mezcladas sería
-- repetir el error que corrigió la migración 044: confundir un hecho con
-- una derivación del presente. La columna snapshot_tipo las separa.
--
-- Arena y League sólo tienen la registrada —reserva_saldo y obligación
-- menos abonos son hechos almacenados— y así se graban.
--
--
-- LA SEDE PUEDE SER NULA, Y ES CORRECTO
--
-- League no tiene sede: sus torneos pueden organizarse fuera de las
-- instalaciones del club. No es un hueco que rellenar, es cómo funciona el
-- negocio. Sus filas van con snapshot_sedeid nulo y los informes por sede
-- lo rotulan «fuera de sede» en vez de repartirlo a prorrateo.
--
--
-- EL PASADO NO SE PUEDE RECONSTRUIR
--
-- Para Arena y League sí: sus saldos están fechados. Para la proyectada de
-- Basketball no, porque la fórmula mira a CURDATE(). La serie de esa cifra
-- empieza el día que se capture por primera vez, y conviene saberlo antes
-- de dibujar un gráfico con un solo punto.
-- =====================================================================

CREATE TABLE IF NOT EXISTS insights_cartera_snapshot (
    snapshot_id        INT AUTO_INCREMENT PRIMARY KEY,

    snapshot_periodo   CHAR(7)      NOT NULL
        COMMENT 'AAAA-MM del mes retratado',

    snapshot_modulo    VARCHAR(20)  NOT NULL
        COMMENT 'basketball | arena | league',

    snapshot_tipo      VARCHAR(12)  NOT NULL
        COMMENT 'REGISTRADA (saldo almacenado) | PROYECTADA (estimacion desde el presente)',

    snapshot_sedeid    INT          NULL
        COMMENT 'Nulo cuando el modulo no tiene sede. League nunca la tiene.',

    snapshot_valor     DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    snapshot_deudores  INT          NOT NULL DEFAULT 0
        COMMENT 'Cuantos deben. Sin esto, no se distingue una deuda grande de muchas pequenas.',

    snapshot_tomada    DATETIME     NOT NULL
        COMMENT 'Cuando se hizo la foto. No es lo mismo que el periodo retratado.',

    snapshot_usuarioid INT          NULL,

    /* Una sola fila por periodo, modulo, tipo y sede: volver a capturar el
       mismo mes actualiza en vez de duplicar.

       OJO CON EL NULO. En MySQL dos NULL no colisionan en un indice unico,
       asi que la clave sobre snapshot_sedeid NO protegia a League, que
       nunca tiene sede: se comprobo y admitia dos filas identicas, con lo
       que cualquier informe habria duplicado su cartera.

       Se resuelve con una columna generada que convierte el nulo en 0, y
       es la clave la que va sobre ella. Es la misma tecnica que ya usa
       League en sus columnas *_estadoentidad. */
    snapshot_sedeclave INT AS (COALESCE(snapshot_sedeid, 0)) STORED
        COMMENT 'Solo para el indice unico: el nulo de League se vuelve 0.',

    UNIQUE KEY uq_snapshot (snapshot_periodo, snapshot_modulo, snapshot_tipo, snapshot_sedeclave),

    KEY ix_snapshot_periodo (snapshot_periodo),

    CONSTRAINT fk_snapshot_sede FOREIGN KEY (snapshot_sedeid)
        REFERENCES general_sede (sede_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
