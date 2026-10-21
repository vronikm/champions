-- =====================================================================
-- 043 · Segundo factor de autenticación (TOTP)
-- =====================================================================
-- QUÉ RESUELVE
--
-- Una contraseña robada sirve para entrar. Da igual lo larga que sea, lo
-- bien que esté guardada con bcrypt o cuántos intentos se frenen: si el
-- usuario la escribió en un sitio equivocado o la reutilizó de otro
-- servicio que se filtró, quien la tenga entra. El segundo factor añade
-- algo que no viaja: un código que sólo puede calcular el teléfono que se
-- vinculó una vez.
--
-- TOTP Y NO SMS
--
-- El SMS se intercepta con un duplicado de tarjeta, que es el fraude más
-- común en la región; y el correo suele estar detrás de la misma
-- contraseña que se intenta reforzar. TOTP no viaja por ningún canal: se
-- calcula a la vez en el teléfono y en el servidor.
--
--
-- EL SECRETO SE GUARDA EN CLARO, Y HAY QUE SABERLO
--
-- No es un descuido. El servidor NECESITA el secreto para calcular el
-- código, así que no puede guardarse como un hash igual que una
-- contraseña. Es la limitación conocida de TOTP: quien lea esta tabla
-- puede generar códigos válidos. Por eso el segundo factor COMPLEMENTA a
-- la contraseña y no la sustituye, y por eso el acceso a la base de datos
-- sigue siendo la frontera que más importa.
--
--
-- TRES ESTADOS, NO UN SÍ/NO
--
--   'N'  sin configurar
--   'P'  secreto generado pero AÚN NO confirmado con un código
--   'A'  activo
--
-- El estado intermedio existe porque activar el segundo factor sin
-- comprobar antes que el teléfono genera el código correcto deja al
-- usuario fuera de su propia cuenta. Con 'P', mientras no demuestre que
-- puede generar códigos, entra sólo con la contraseña.
--
--
-- LOS CÓDIGOS DE RECUPERACIÓN NO SON OPCIONALES
--
-- Sin ellos, perder el teléfono cierra la cuenta para siempre, y lo que
-- ocurre en la práctica es que alguien desactiva el segundo factor de
-- todo el sistema para poder entrar. Con ellos hay una salida que no
-- obliga a bajar la seguridad de los demás.
--
-- Se guarda el HASH, no el código: quien lea la tabla no debe poder
-- entrar con lo que encuentre. Y bcrypt, no sha1: ocho caracteres se
-- recorren enteros con un diccionario si el hash es rápido.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE seguridad_usuario
    ADD COLUMN usuario_2fa_estado   CHAR(1)     NOT NULL DEFAULT 'N'
        COMMENT 'N sin configurar, P pendiente de confirmar, A activo'
        AFTER usuario_tienebloqueo,
    ADD COLUMN usuario_2fa_secreto  VARCHAR(64) NOT NULL DEFAULT ''
        COMMENT 'Secreto TOTP en base32. En claro por necesidad del algoritmo.'
        AFTER usuario_2fa_estado,
    ADD COLUMN usuario_2fa_activado DATETIME    NULL
        AFTER usuario_2fa_secreto;


-- ---------------------------------------------------------------------
-- Códigos de recuperación.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seguridad_2fa_recuperacion (
    rec_id         INT AUTO_INCREMENT PRIMARY KEY,
    rec_usuarioid  INT          NOT NULL,

    -- bcrypt del código normalizado (mayúsculas, sin guión).
    rec_hash       VARCHAR(255) NOT NULL,

    -- Un código sirve UNA vez. Se marca en lugar de borrarse, para que
    -- «¿cuántos me quedan?» y «¿cuándo usé uno?» tengan respuesta.
    rec_usado      CHAR(1)      NOT NULL DEFAULT 'N',
    rec_fechauso   DATETIME     NULL,

    rec_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_2farec_usuario (rec_usuarioid, rec_usado),

    CONSTRAINT fk_2farec_usuario FOREIGN KEY (rec_usuarioid)
        REFERENCES seguridad_usuario (usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Registro de lo que pasa con el segundo factor.
--
-- Aparte de la auditoría general: activar, desactivar o restablecer el
-- segundo factor de otra persona es exactamente el movimiento que haría
-- quien quiere entrar en una cuenta ajena, y tiene que quedar escrito
-- quién lo hizo y sobre quién.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seguridad_2fa_evento (
    ev_id        INT AUTO_INCREMENT PRIMARY KEY,
    ev_usuarioid INT         NOT NULL,

    -- 'ACTIVAR' | 'DESACTIVAR' | 'RESTABLECER' | 'RECUPERACION'
    -- | 'CODIGOS_NUEVOS' | 'FALLO'
    ev_accion    VARCHAR(20) NOT NULL,

    -- Quién lo hizo: puede no ser el mismo usuario si un administrador
    -- restablece el factor de otro.
    ev_autorid   INT         NULL,
    ev_autor     VARCHAR(20) NOT NULL DEFAULT '',

    ev_ip        VARBINARY(16) NULL,
    ev_nota      VARCHAR(250)  NOT NULL DEFAULT '',
    ev_fecha     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_2faev_usuario (ev_usuarioid, ev_fecha),

    CONSTRAINT fk_2faev_usuario FOREIGN KEY (ev_usuarioid)
        REFERENCES seguridad_usuario (usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
