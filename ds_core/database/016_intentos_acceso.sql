-- =====================================================================
-- 016 · Bitácora de intentos de acceso
-- =====================================================================
-- El login no tenía freno alguno: se midieron 25 intentos fallidos
-- seguidos sin una sola negativa, a un ritmo sostenido de unos 500 por
-- minuto. Con eso, una clave débil cae en horas.
--
-- Esta tabla es la memoria que faltaba. Se registra CADA intento, con
-- éxito o sin él, y el login cuenta los fallos recientes por usuario y
-- por IP antes de comprobar la contraseña.
--
-- Se guarda el usuario TECLEADO, exista o no: los intentos contra
-- cuentas inexistentes son justamente la señal de un barrido.
--
-- El freno es por VENTANA DE TIEMPO, no una marca permanente. Un bloqueo
-- que hubiera que levantar a mano convertiría el ataque en algo peor:
-- cualquiera podría dejar fuera al administrador fallando cinco veces a
-- propósito. Pasada la ventana, la cuenta vuelve sola.
-- =====================================================================

CREATE TABLE IF NOT EXISTS seguridad_intento_acceso (
    intento_id      BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Lo que se tecleó en el formulario, exista o no esa cuenta.
    intento_usuario VARCHAR(20)   NOT NULL,

    -- inet6_aton: 4 bytes en IPv4, 16 en IPv6. Cabe cualquiera de las dos.
    intento_ip      VARBINARY(16) NULL,

    intento_exito   TINYINT(1)    NOT NULL DEFAULT 0,
    intento_fecha   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Las dos consultas del freno son "fallos de X en los últimos N
    -- minutos": el índice lleva primero el sujeto y después la fecha.
    KEY idx_intento_usuario (intento_usuario, intento_fecha),
    KEY idx_intento_ip      (intento_ip, intento_fecha),
    KEY idx_intento_fecha   (intento_fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
