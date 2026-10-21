-- =====================================================================
-- 054 · Los umbrales del centro de atencion
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- «Requiere tu atencion» avisa hoy con la condicion mas simple que existe:
-- mayor que cero. Un partido sin resultado, un alumno sin pagar, un dolar
-- pendiente en Arena — todo dispara.
--
-- En una escuela con 266 alumnos eso significa que el panel avisa SIEMPRE, y
-- un panel que avisa siempre no avisa de nada: se aprende a no mirarlo. El
-- aviso util es el que aparece cuando algo se sale de lo normal, y «lo
-- normal» lo sabe la escuela, no el codigo.
--
--
-- POR QUE UNA TABLA Y NO UNA CONSTANTE
--
-- Porque el umbral correcto cambia con el tamanio y con la temporada, y
-- porque quien lo sabe no edita PHP. Ademas queda registro de quien lo movio
-- y cuando: un aviso que deja de aparecer sin explicacion es peor que uno
-- que molesta.
--
--
-- ES UNA TABLA DE INSIGHTS Y POR ESO SE PUEDE ESCRIBIR
--
-- El candado de InsightsConexion rechaza toda escritura fuera de insights_*.
-- Esta cae dentro, que es justo el caso para el que se dejo la excepcion: el
-- modulo administra SU configuracion y no toca el dato de nadie.
--
--
-- EL UMBRAL NO APAGA EL CALCULO, SOLO EL AVISO
--
-- Subir un umbral no borra la deuda ni oculta las cifras: las pantallas
-- siguen mostrandolo todo. Lo unico que cambia es cuando el panel llama la
-- atencion. Conviene tenerlo claro antes de ponerlos altos.
-- =====================================================================

-- El cliente de linea de comandos de MySQL en Windows lee el archivo con la
-- pagina de codigos de la CONSOLA, no en UTF-8. Sin esta linea, «pensión» se
-- guarda como «pensi├│n»: bytes UTF-8 validos, texto equivocado. Comprobarlo
-- con mb_check_encoding() no lo detecta —esa funcion mira que los bytes esten
-- bien formados, no que digan lo que deben—.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS insights_umbral (
    umbral_id          INT AUTO_INCREMENT PRIMARY KEY,

    -- El codigo lo usa el controlador para buscar el suyo. No se traduce ni
    -- se renombra: si cambia, el aviso se queda sin umbral y vuelve al
    -- comportamiento de avisar siempre.
    umbral_codigo      VARCHAR(40)  NOT NULL,

    umbral_nombre      VARCHAR(120) NOT NULL,
    umbral_ayuda       VARCHAR(255) NOT NULL DEFAULT '',

    -- 'CANTIDAD' cuenta cosas; 'DINERO' cuenta dolares. La pantalla lo usa
    -- para poner el simbolo y los decimales correctos.
    umbral_unidad      VARCHAR(10)  NOT NULL DEFAULT 'CANTIDAD',

    -- Avisa cuando el valor medido es MAYOR O IGUAL que esto.
    umbral_valor       DECIMAL(12,2) NOT NULL DEFAULT 1,

    -- 'A' activo, 'I' el aviso no se muestra nunca. Desactivar no es lo
    -- mismo que poner un umbral altisimo: esto se lee como una decision.
    umbral_estado      CHAR(1)      NOT NULL DEFAULT 'A',

    umbral_orden       INT          NOT NULL DEFAULT 0,

    -- Quien lo movio y cuando. Sin esto, un aviso que deja de salir es un
    -- misterio que nadie puede resolver.
    umbral_usuarioid   INT          NULL,
    umbral_modificado  DATETIME     NULL,

    UNIQUE KEY uk_umbral_codigo (umbral_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Los seis avisos que existen hoy, con el umbral que reproduce EXACTAMENTE
-- el comportamiento actual: valor 1, es decir «avisa si hay al menos uno».
-- Se siembra asi a proposito. Cambiar el comportamiento y anadir la tabla en
-- la misma migracion mezcla dos cosas: primero se hace configurable, despues
-- la escuela decide sus numeros.
INSERT INTO insights_umbral
    (umbral_codigo, umbral_nombre, umbral_ayuda, umbral_unidad, umbral_valor, umbral_orden)
VALUES
    ('alumnos_sin_pago',   'Alumnos sin ningún pago de pensión',
     'Alumnos activos que no tienen registrado ni un pago de pensión.',
     'CANTIDAD', 1, 1),

    ('cartera_arena',      'Cartera pendiente de Arena',
     'Saldo vivo en reservas no canceladas.',
     'DINERO',   1, 2),

    ('pagos_pendientes',   'Pagos de Basketball con saldo',
     'Cuotas registradas que quedaron con saldo por cobrar.',
     'CANTIDAD', 1, 3),

    ('becas_completas',    'Becas del 100 %',
     'Se avisa porque su importe se guarda como 0,00 y no aparece en ninguna suma de descuentos.',
     'CANTIDAD', 1, 4),

    ('partidos_sin_resultado', 'Partidos finalizados sin resultado',
     'Partidos marcados como finalizados a los que les falta el marcador.',
     'CANTIDAD', 1, 5),

    ('obligaciones_vencidas',  'Obligaciones de League vencidas',
     'Obligaciones pasadas de su fecha de vencimiento y sin pagar.',
     'CANTIDAD', 1, 6)
ON DUPLICATE KEY UPDATE umbral_nombre = VALUES(umbral_nombre);
