-- =====================================================================
-- 061 · El saldo de una reserva deja de guardarse a mano
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- dsa_reserva.reserva_saldo guardaba un numero que ya estaba en la misma
-- fila: total menos abonado. Es informacion calculable almacenada sin
-- necesidad, y eso siempre acaba igual: el dia que una escritura toque
-- reserva_total o reserva_abonado sin acordarse de recalcular el saldo, la
-- columna miente.
--
-- Y miente en el peor sitio. reserva_saldo alimenta:
--
--   * el panel de Arena y el aviso de cobros pendientes,
--   * la lista de reservas y su pie de totales,
--   * seis consultas de DigiSports Insights, que solo lee y por tanto no
--     puede detectar ni corregir el descuadre.
--
-- Hoy no hay ni un descuadre en 6.006 filas. Esta migracion no repara un
-- dano: impide que ocurra.
--
--
-- POR QUE GENERADA Y NO UN DISPARADOR O UNA REGLA EN EL CODIGO
--
-- Un disparador hay que mantenerlo y se puede desactivar. Una regla en el
-- controlador solo alcanza a lo que pasa por el controlador: no cubre una
-- correccion por consola, una restauracion parcial ni la semilla de pruebas.
--
-- Una columna generada lo cubre TODO, porque no es una comprobacion sino una
-- imposibilidad: MySQL rechaza cualquier escritura sobre ella con el error
-- 3105. Comprobado sobre un clon de la tabla antes de escribir esto.
--
--
-- POR QUE STORED Y NO VIRTUAL
--
-- La columna se FILTRA, no solo se muestra: «WHERE reserva_saldo > 0»
-- aparece en la lista, en el panel y en Insights. VIRTUAL la calcularia en
-- cada lectura de cada fila y no admite indice; STORED se materializa una
-- vez al escribir y deja la puerta abierta a indexarla si algun dia hace
-- falta.
--
--
-- QUE HAY QUE CAMBIAR EN EL CODIGO (ya hecho)
--
-- Tres escrituras dejaban de funcionar con esta migracion, y las tres se
-- corrigieron ANTES de aplicarla:
--
--   * guardarReserva()  INSERT  · se quita la columna y el parametro :t2
--   * guardarReserva()  UPDATE  · idem
--   * guardarPago()     UPDATE  · solo suma reserva_abonado; el saldo se
--                                 recalcula solo en el mismo UPDATE
--   * pruebas/semilla_arena.php · deja de sembrar la columna
--
--
-- REVERSIBLE
--
-- Al final del archivo queda el ALTER que la devuelve a columna normal
-- conservando los valores. Comentado, porque revertir exige tambien
-- devolver las escrituras al codigo.
-- =====================================================================

-- Comprobacion previa: si hubiera descuadres, conviene mirarlos antes de
-- que la conversion los borre recalculando. Debe devolver 0.
SELECT COUNT(*) AS descuadres_antes
  FROM dsa_reserva
 WHERE ABS(reserva_saldo - (reserva_total - reserva_abonado)) > 0.005;

ALTER TABLE dsa_reserva
  MODIFY reserva_saldo DECIMAL(10,2)
  GENERATED ALWAYS AS (reserva_total - reserva_abonado) STORED;

-- Comprobacion posterior: la columna existe, es generada y cuadra.
SELECT column_name, extra, generation_expression
  FROM information_schema.columns
 WHERE table_schema = DATABASE()
   AND table_name   = 'dsa_reserva'
   AND column_name  = 'reserva_saldo';

SELECT COUNT(*) AS filas,
       SUM(ABS(reserva_saldo - (reserva_total - reserva_abonado)) > 0.005) AS descuadres_despues
  FROM dsa_reserva;

-- ---------------------------------------------------------------------
-- Para revertir (requiere devolver antes las escrituras al codigo):
--
-- ALTER TABLE dsa_reserva
--   MODIFY reserva_saldo DECIMAL(10,2) NOT NULL DEFAULT 0.00;
-- UPDATE dsa_reserva SET reserva_saldo = reserva_total - reserva_abonado;
-- ---------------------------------------------------------------------
