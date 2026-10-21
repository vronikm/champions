-- =====================================================================
-- 017 · Puntos de emisión por módulo
-- =====================================================================
-- Hasta hoy la facturación electrónica vivía entera en Basketball, con un
-- único par establecimiento/punto de emisión guardado en la configuración
-- (001-001). Al incorporar League —y más adelante Arena— hacen falta más
-- puntos, y hace falta que cada módulo sepa cuál es el suyo.
--
-- POR QUÉ ESTO NO ES UN CAMPO MÁS EN LA CONFIGURACIÓN
--
-- El SRI numera los comprobantes por la terna
--
--     (tipo de comprobante, establecimiento, punto de emisión)
--
-- y exige que el secuencial sea único dentro de ella. Si dos módulos
-- emitieran desde el mismo punto llevando cada uno su propia cuenta,
-- generarían el mismo número: el SRI responde error 45 «secuencial
-- registrado» y el comprobante ya entregado al cliente no se puede
-- retirar. Es un problema tributario, no un fallo recuperable.
--
-- Dar a cada módulo su propio punto de emisión elimina esa colisión POR
-- CONSTRUCCIÓN: rangos distintos no se pisan. Pero eso sólo se sostiene
-- mientras nadie asigne el mismo punto dos veces, y esa es una decisión
-- que se toma desde una pantalla de administración, a mano, meses después
-- y probablemente por otra persona.
--
-- De ahí la clave única (establecimiento, punto). No es una precaución
-- decorativa: es lo que convierte «cada módulo tiene su punto» en una
-- garantía en vez de en una costumbre. Con ella, el error se detiene en el
-- INSERT; sin ella, aparece semanas más tarde en el portal del SRI.
--
-- LO QUE NO CAMBIA
--
-- La identidad fiscal (RUC, razón social, direcciones, ambiente) sigue
-- siendo única y sigue en facturas_electronicas_config: es del emisor, no
-- del módulo. El certificado de firma tampoco se duplica. Un solo
-- contribuyente, varios puntos de emisión.
--
-- Basketball conserva 001-001, que es desde donde ya emitió. Cambiárselo
-- rompería la continuidad de una serie ya declarada.
-- =====================================================================

-- La conexion del cliente puede llegar en cp850 (consola de Windows). Sin
-- esta linea, un texto con tilde o guion largo se guarda doblemente
-- codificado y se lee como caracteres rotos.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS facturas_electronicas_punto_emision (
    punto_id                INT AUTO_INCREMENT PRIMARY KEY,

    -- Clave del módulo, la misma que usa seguridad_rol_modulo y
    -- seguridad_menu.menu_modulo: 'basketball', 'arena', 'league'.
    punto_modulo            VARCHAR(20)  NOT NULL,

    punto_establecimiento   CHAR(3)      NOT NULL,
    punto_codigo            CHAR(3)      NOT NULL,

    -- Desde qué número empieza a numerar este punto. El asignador nunca
    -- retrocede por debajo de aquí, de modo que se puede continuar una
    -- serie que venía de otro sistema.
    punto_secuencialinicio  INT          NOT NULL DEFAULT 1,

    punto_descripcion       VARCHAR(100) NOT NULL DEFAULT '',
    punto_estado            CHAR(1)      NOT NULL DEFAULT 'A',
    punto_usuarioid         INT          NULL,
    punto_fecharegistro     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    punto_fechacambio       TIMESTAMP    NULL ON UPDATE CURRENT_TIMESTAMP,

    -- El seguro descrito arriba: un punto de emisión pertenece a un solo
    -- módulo. Intentar asignarlo dos veces falla en la base de datos.
    UNIQUE KEY uk_fepe_punto  (punto_establecimiento, punto_codigo),

    -- Y a la inversa: un módulo emite desde un único punto dentro de cada
    -- establecimiento, para que «¿desde dónde factura League?» tenga una
    -- respuesta y no una lista.
    UNIQUE KEY uk_fepe_modulo (punto_modulo, punto_establecimiento),

    KEY ix_fepe_estado (punto_estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Basketball: se toma el punto que YA está en uso, no uno nuevo.
--
-- Se lee de la configuración en lugar de escribir 001-001 a mano, para
-- que la migración siga siendo correcta si en esta instalación el par
-- configurado fuese otro. El secuencial de arranque se calcula como el
-- mayor entre lo configurado y lo realmente emitido: así el punto nunca
-- queda apuntando por debajo de un número ya entregado.
-- ---------------------------------------------------------------------
INSERT INTO facturas_electronicas_punto_emision
       (punto_modulo, punto_establecimiento, punto_codigo,
        punto_secuencialinicio, punto_descripcion)
SELECT 'basketball',
       C.codigo_establecimiento,
       C.punto_emision,
       GREATEST(
           C.secuencial_inicio,
           COALESCE((SELECT MAX(CAST(F.secuencial AS UNSIGNED))
                       FROM facturas_electronicas F
                      WHERE F.establecimiento = C.codigo_establecimiento
                        AND F.punto_emision   = C.punto_emision), 0)
       ),
       'Escuela — pensiones y matrículas'
  FROM facturas_electronicas_config C
 WHERE C.config_lock = 'X'
    ON DUPLICATE KEY UPDATE punto_modulo = punto_modulo;


-- ---------------------------------------------------------------------
-- Arena y League. Se crean inactivos a propósito: reservan el número
-- para que nadie más lo tome, pero no habilitan la emisión hasta que la
-- administración revise la configuración desde el Core.
--
-- El establecimiento se hereda del configurado; sólo cambia el punto.
-- ---------------------------------------------------------------------
INSERT INTO facturas_electronicas_punto_emision
       (punto_modulo, punto_establecimiento, punto_codigo,
        punto_secuencialinicio, punto_descripcion, punto_estado)
SELECT 'arena', C.codigo_establecimiento, '002', 1,
       'Arena — alquiler de instalaciones', 'I'
  FROM facturas_electronicas_config C
 WHERE C.config_lock = 'X'
    ON DUPLICATE KEY UPDATE punto_modulo = punto_modulo;

INSERT INTO facturas_electronicas_punto_emision
       (punto_modulo, punto_establecimiento, punto_codigo,
        punto_secuencialinicio, punto_descripcion, punto_estado)
SELECT 'league', C.codigo_establecimiento, '003', 1,
       'League — inscripciones, arbitraje y multas', 'I'
  FROM facturas_electronicas_config C
 WHERE C.config_lock = 'X'
    ON DUPLICATE KEY UPDATE punto_modulo = punto_modulo;


-- ---------------------------------------------------------------------
-- El índice que faltaba en la tabla de comprobantes.
--
-- La unicidad del secuencial dependía SÓLO del código de la aplicación.
-- Mientras hubo un único emisor eso bastó; con varios módulos, no. Se
-- añade la restricción que el propio SRI impone, para que un duplicado
-- muera en el INSERT y no en la respuesta del organismo.
--
-- Se crea condicionalmente: si esta instalación ya lo tuviera, la
-- migración no debe fallar.
-- ---------------------------------------------------------------------
SET @existe := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'facturas_electronicas'
       AND INDEX_NAME   = 'uk_fe_numero'
);

SET @sql := IF(@existe > 0,
    'SELECT ''uk_fe_numero ya existe'' AS aviso',
    'ALTER TABLE facturas_electronicas
       ADD UNIQUE KEY uk_fe_numero
       (tipo_comprobante, establecimiento, punto_emision, secuencial)');

PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
