-- =====================================================================
-- 056 · La plantilla de la tarjeta de cumpleanos, configurable
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- cumpleaniosTarjeta-view.php dibuja la tarjeta con GD y TODO esta cableado
-- en el codigo: el marco es una ruta fija, y las areas son fracciones magicas
-- escritas a mano —0.218, 0.176, 0.558, 0.463— con el comentario «ajustar
-- estos valores si se cambia la plantilla».
--
-- Es decir: cambiar el marco exige editar PHP y adivinar cuatro numeros
-- probando una y otra vez. Quien decide como se ve la tarjeta no es quien
-- edita PHP.
--
--
-- POR QUE EN PORCENTAJES Y NO EN PIXELES
--
-- Porque el marco se puede sustituir por otro de distinta resolucion, y con
-- pixeles habria que rehacer todas las coordenadas. En porcentaje del marco,
-- una plantilla de 900x1600 y otra de 1800x3200 usan los MISMOS numeros.
--
-- El codigo actual ya lo hacia asi, en fracciones. Aqui se guarda en 0-100
-- con tres decimales, que es como se piensa y como se muestra en pantalla.
--
--
-- POR QUE UNA FILA POR PLANTILLA Y NO CLAVE/VALOR
--
-- carnet_configuracion usa clave/valor y funciona, pero ahi son cuatro
-- ajustes sueltos. Aqui son veintitantos campos que describen UNA cosa, y
-- ademas se quiere poder guardar varias plantillas y cambiar entre ellas sin
-- perder la anterior. Una fila por plantilla lo dice mejor y permite volver
-- atras.
--
--
-- LA FRANJA ES OPCIONAL, Y AHI ESTA EL MOTIVO
--
-- El marco actual no tiene sitio para el nombre: el codigo dibuja una franja
-- inclinada semitransparente sobre la foto y escribe encima. Un marco que YA
-- traiga sus casillas de «NOMBRE:» y «EDAD:» no la necesita, y dibujarla
-- encima seria estropearlo.
--
-- Por eso franja_activa. Se siembra en 'S' para reproducir exactamente la
-- tarjeta de hoy; una plantilla nueva la apaga.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cumple_plantilla (
    plantilla_id        INT AUTO_INCREMENT PRIMARY KEY,

    plantilla_nombre    VARCHAR(80)  NOT NULL,

    -- Solo el nombre del archivo. La carpeta la pone el codigo, para que no
    -- se pueda apuntar a cualquier sitio del disco desde la base.
    plantilla_archivo   VARCHAR(120) NOT NULL,

    -- 'A' es la que se usa. Solo una a la vez; lo garantiza el controlador,
    -- que apaga las demas al activar una.
    plantilla_estado    CHAR(1)      NOT NULL DEFAULT 'I',

    -- ---- El area de la foto, en % del marco ----
    -- La foto se dibuja AQUI y el marco va encima: por eso el marco necesita
    -- su ventana transparente, y por eso la foto asoma recortada a la forma
    -- que tenga esa ventana sin que el codigo sepa nada de su contorno.
    foto_x              DECIMAL(6,3) NOT NULL DEFAULT 21.800,
    foto_y              DECIMAL(6,3) NOT NULL DEFAULT 17.600,
    foto_ancho          DECIMAL(6,3) NOT NULL DEFAULT 55.800,
    foto_alto           DECIMAL(6,3) NOT NULL DEFAULT 46.300,

    -- ---- El nombre ----
    -- x/y/ancho definen la caja; el texto se alinea dentro segun alinea, y
    -- se encoge solo si no cabe. Sin la caja habria que centrar a ojo.
    nombre_x            DECIMAL(6,3) NOT NULL DEFAULT 21.800,
    nombre_y            DECIMAL(6,3) NOT NULL DEFAULT 55.000,
    nombre_ancho        DECIMAL(6,3) NOT NULL DEFAULT 55.800,
    nombre_tam          DECIMAL(6,3) NOT NULL DEFAULT 5.500,   -- % del ancho del marco
    nombre_color        CHAR(7)      NOT NULL DEFAULT '#FFFFFF',
    nombre_alinea       CHAR(1)      NOT NULL DEFAULT 'C',     -- I izquierda, C centro, D derecha

    -- ---- La edad ----
    edad_x              DECIMAL(6,3) NOT NULL DEFAULT 21.800,
    edad_y              DECIMAL(6,3) NOT NULL DEFAULT 60.000,
    edad_ancho          DECIMAL(6,3) NOT NULL DEFAULT 55.800,
    edad_tam            DECIMAL(6,3) NOT NULL DEFAULT 4.000,
    edad_color          CHAR(7)      NOT NULL DEFAULT '#FFE001',
    edad_alinea         CHAR(1)      NOT NULL DEFAULT 'C',

    -- Con {edad} donde va el numero. Asi se puede poner «{edad} AÑOS»,
    -- «Cumple {edad}» o lo que la escuela prefiera sin tocar codigo.
    edad_texto          VARCHAR(40)  NOT NULL DEFAULT '{edad} AÑOS',

    -- ---- La franja bajo la foto, para marcos que no traen casillas ----
    franja_activa       CHAR(1)      NOT NULL DEFAULT 'N',
    franja_alto         DECIMAL(6,3) NOT NULL DEFAULT 8.300,   -- % del alto del marco
    franja_color        CHAR(7)      NOT NULL DEFAULT '#054017',
    franja_opacidad     TINYINT      NOT NULL DEFAULT 70,      -- 0-100

    -- Inclinacion en grados, para marcos girados. 0 = recto.
    angulo              DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    plantilla_usuarioid INT          NULL,
    plantilla_modificado DATETIME    NULL,

    UNIQUE KEY uk_cumple_plantilla_nombre (plantilla_nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- La plantilla que reproduce EXACTAMENTE la tarjeta de hoy, con los numeros
-- que estaban en el codigo. Se siembra asi a proposito: primero se hace
-- configurable sin cambiar nada de lo que se ve, y despues la escuela ajusta.
--
-- Los valores salen de cumpleaniosTarjeta-view.php:
-- No son las fracciones redondeadas: son los PIXELES que el codigo viejo
-- producia sobre el marco de 900x1600, devueltos a porcentaje. La
-- diferencia importa: el codigo hacia (int) en cada paso y el nombre caia
-- en y=941, que es 58,812 % y no el 57,4 % que sale de leer las fracciones
-- de arriba. Comprobado: con estos numeros las tarjetas salen byte a byte
-- iguales a las de antes.
INSERT INTO cumple_plantilla
    (plantilla_nombre, plantilla_archivo, plantilla_estado,
     foto_x, foto_y, foto_ancho, foto_alto,
     nombre_x, nombre_y, nombre_ancho, nombre_tam, nombre_color, nombre_alinea,
     edad_x, edad_y, edad_ancho, edad_tam, edad_color, edad_alinea, edad_texto,
     franja_activa, franja_alto, franja_color, franja_opacidad, angulo)
VALUES
    ('Clásica', 'marco.png', 'A',
     21.778, 17.562, 55.778, 46.250,
     21.778, 58.812, 55.778, 3.000, '#FFFFFF', 'C',
     21.778, 61.562, 55.778, 2.222, '#FFE001', 'C', '{edad} AÑOS',
     'S', 8.312, '#054017', 70, -2.50)
ON DUPLICATE KEY UPDATE plantilla_nombre = VALUES(plantilla_nombre);
