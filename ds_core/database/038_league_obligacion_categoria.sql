-- =====================================================================
-- 038 · League · A qué categoría pertenece cada obligación
-- =====================================================================
-- CORRIGE UN AGUJERO DE LA 036: DEUDA INVISIBLE
--
-- La 036 dejó el origen polimórfico pero no registró la competencia. El
-- panel de cobranza trabaja por categoría, así que resolvía la pertenencia
-- deduciéndola: «las obligaciones cuyo origen sea una inscripción DE esta
-- categoría». Eso funciona para las inscripciones y para nada más. Una
-- multa a un equipo, un carné de un jugador o el arbitraje de un partido
-- tienen origen EQUIPO / PERSONA / PARTIDO, no casaban con ese filtro y
-- desaparecían de todas las pantallas: dinero adeudado que ningún listado
-- muestra y que nadie va a cobrar.
--
-- Deducirlo tampoco se arregla añadiendo tres subconsultas más. Un equipo
-- puede jugar varias categorías a la vez, así que «la categoría de este
-- equipo» no es una sola: la multa aparecería repetida en cada una y el
-- resumen la sumaría tantas veces como competencias juegue.
--
-- Por eso se GUARDA. No es un dato derivable: es una decisión de quien
-- genera el cobro —«esta multa es de la Sub-14»— y sólo esa persona la
-- conoce. Admite NULL para las obligaciones que de verdad no pertenezcan a
-- ninguna competencia.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE dsl_obligacion
    ADD COLUMN obligacion_categoriaid INT NULL AFTER obligacion_origenid,
    ADD KEY ix_dslo_categoria (obligacion_categoriaid, obligacion_estado),
    ADD CONSTRAINT fk_dslo_categoria FOREIGN KEY (obligacion_categoriaid)
        REFERENCES dsl_categoria (categoria_id);


-- ---------------------------------------------------------------------
-- Las que ya existen y vienen de una inscripción sí se pueden deducir:
-- la inscripción dice exactamente a qué categoría pertenece.
-- ---------------------------------------------------------------------
UPDATE dsl_obligacion O
  JOIN dsl_inscripcion I ON I.inscripcion_id = O.obligacion_origenid
   SET O.obligacion_categoriaid = I.inscripcion_categoriaid
 WHERE O.obligacion_origentipo = 'INSCRIPCION'
   AND O.obligacion_categoriaid IS NULL;


-- ---------------------------------------------------------------------
-- La vista expone la columna para que el panel filtre por ella en vez de
-- por cuatro subconsultas.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_dsl_saldo AS
    SELECT O.obligacion_id,
           O.obligacion_conceptoid,
           C.concepto_nombre,
           O.obligacion_origentipo,
           O.obligacion_origenid,
           O.obligacion_categoriaid,
           O.obligacion_equipoid,
           O.obligacion_personaid,
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
