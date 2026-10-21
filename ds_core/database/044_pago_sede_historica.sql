-- =====================================================================
-- 044 · La sede del pago deja de depender de dónde esté hoy el alumno
-- =====================================================================
-- QUÉ RESUELVE
--
-- Hasta ahora «ingresos por sede» no era un dato: era una consulta que
-- llegaba a la sede por el alumno.
--
--     SELECT SUM(p.pago_valor)
--       FROM alumno_pago p
--       JOIN sujeto_alumno a ON a.alumno_id = p.pago_alumnoid
--      GROUP BY a.alumno_sedeid
--
-- El problema es que alumno_sedeid es el PRESENTE del alumno, y la suma de
-- pagos es el PASADO. Si un alumno se traslada de La Salle a Cariamanga, la
-- consulta no mueve al alumno: mueve sus veinte pagos de los últimos ocho
-- meses. Un cierre de mes ya impreso deja de cuadrar sin que nadie haya
-- tocado un solo pago.
--
-- Medido sobre los datos reales en el momento de escribir esto: el alumno
-- con más historial arrastraría 200,00 consigo; la media por alumno es
-- 57,42; y 442,00 pertenecen a alumnos ya inactivos, que son precisamente
-- los que alguien podría reasignar o depurar sin pensar en la contabilidad.
--
--
-- POR QUÉ SE CONGELA Y NO SE DERIVA
--
-- Un pago es un hecho ocurrido en un sitio y en una fecha. Como el importe
-- o el rubro, la sede forma parte de lo que pasó, no de lo que es cierto
-- hoy. Guardarla en la fila es lo mismo que ya hace el resto del sistema:
--
--     balance_egreso.egreso_sedeid
--     dsa_reserva.reserva_sedeid
--
-- alumno_pago era la excepción, no la regla.
--
--
-- LO QUE NO CAMBIA, Y ES DELIBERADO
--
-- Los listados de alumnos por sede SIGUEN usando alumno_sedeid. No es una
-- omisión: son preguntas distintas.
--
--     «¿Cuántos alumnos tiene La Salle?»   → sede ACTUAL del alumno
--     «¿Cuánto recaudó La Salle en marzo?» → sede CONGELADA del pago
--
-- Confundirlas es el error que esta migración corrige; unificarlas sería
-- cometerlo al revés.
--
--
-- EL RELLENO HISTÓRICO ES UNA APROXIMACIÓN, Y HAY QUE SABERLO
--
-- No existe ningún registro de los cambios de sede que hayan ocurrido: no
-- hay tabla de historial ni auditoría sobre sujeto_alumno. Por tanto la
-- única fuente disponible para las 669 filas existentes es la sede actual
-- de cada alumno.
--
-- Si alguien ya se trasladó, su historial anterior queda atribuido a la
-- sede nueva. Eso no se puede reconstruir: la información no se guardó
-- nunca. A partir de esta migración sí queda registrada, y el dato es
-- exacto de aquí en adelante.
--
--
-- POR QUÉ NOT NULL Y SIN VALOR POR DEFECTO
--
-- Hay cuatro caminos que crean pagos hoy (tres en pagosController y uno en
-- carnetController) y habrá más. Si la columna admitiera NULL, un camino
-- nuevo que olvidara rellenarla insertaría en silencio, y el informe
-- volvería a estar mal sin que nadie se enterase.
--
-- Declarada NOT NULL sin valor por defecto, ese olvido FALLA en la primera
-- prueba en vez de corromper los datos durante meses. Es la misma idea que
-- un disparador, pero declarativa: no hay lógica escondida en la base.
--
-- Se hace en tres pasos —añadir admitiendo nulos, rellenar, endurecer—
-- porque una columna NOT NULL no se puede añadir a una tabla con 669 filas
-- sin darles antes un valor.
--
--
-- ÍNDICES
--
-- La clave foránea crea su propio índice sobre pago_sedeid, y con eso basta
-- hoy: son 669 filas. NO se añade el compuesto (pago_sedeid, pago_fecha)
-- que pedirá el informe de ingresos por sede y periodo, porque a este
-- volumen no lo usaría el optimizador. Cuando Insights tenga consultas
-- reales que medir con EXPLAIN, se decide entonces.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Paso 1 · Añadir la columna admitiendo nulos
-- ---------------------------------------------------------------------
ALTER TABLE alumno_pago
    ADD COLUMN pago_sedeid INT NULL
    COMMENT 'Sede en la que se registro el pago. Se congela al crearlo: NO sigue los traslados del alumno.'
    AFTER pago_alumnoid;

-- ---------------------------------------------------------------------
-- Paso 2 · Rellenar el historial con la única fuente disponible
-- ---------------------------------------------------------------------
UPDATE alumno_pago p
  JOIN sujeto_alumno a ON a.alumno_id = p.pago_alumnoid
   SET p.pago_sedeid = a.alumno_sedeid
 WHERE p.pago_sedeid IS NULL;

-- ---------------------------------------------------------------------
-- Paso 3 · Endurecer, para que un camino de escritura que la olvide falle
-- ---------------------------------------------------------------------
ALTER TABLE alumno_pago
    MODIFY COLUMN pago_sedeid INT NOT NULL
    COMMENT 'Sede en la que se registro el pago. Se congela al crearlo: NO sigue los traslados del alumno.';

ALTER TABLE alumno_pago
    ADD CONSTRAINT fk_pago_sede
    FOREIGN KEY (pago_sedeid) REFERENCES general_sede (sede_id)
    ON DELETE RESTRICT ON UPDATE RESTRICT;
