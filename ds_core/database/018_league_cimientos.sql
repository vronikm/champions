-- =====================================================================
-- 018 · League · Cimientos: estados, transiciones y auditoría
-- =====================================================================
-- Primera migración del módulo. No crea ninguna pantalla ni ninguna
-- entidad de negocio: crea las dos cosas que todas las demás van a dar
-- por supuestas.
--
-- POR QUÉ UN CATÁLOGO DE ESTADOS Y NO UN char(1) EN CADA TABLA
--
-- El resto del sistema usa 'A' / 'I' / 'E' incrustados en cada consulta.
-- Funciona mientras los estados sean dos y las transiciones obvias. Un
-- partido tiene ocho —programado, confirmado, en juego, finalizado,
-- suspendido, reprogramado, cancelado, walkover— y las transiciones NO
-- son libres: de «finalizado» no se vuelve a «programado», y sólo un
-- estado final permite que el resultado cuente para la tabla.
--
-- Con el estado disperso, esa regla se reescribe en cada controlador y
-- basta con que uno la olvide para que un partido cerrado se reabra y la
-- clasificación cambie sin que nadie sepa por qué. Aquí la regla es una
-- fila: si la transición no está en la tabla, no ocurre.
--
-- POR QUÉ LA AUDITORÍA VA ANTES QUE LAS PANTALLAS
--
-- Lo que hay que auditar en una liga —resultados, inscripciones, pagos,
-- sorteos, reprogramaciones, sanciones, cambios de plantilla— es
-- exactamente lo que se discute cuando algo se impugna. Añadir la
-- auditoría después obliga a recorrer cada controlador ya escrito; el
-- registro que falta es siempre el del cambio que interesa.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Catálogo de estados
--
-- La clave natural es (entidad, código). El id numérico existe para que
-- las tablas de negocio referencien un entero, no una cadena repetida.
--
-- estado_final marca los estados desde los que ya no se sale, y es lo
-- que consultan los cálculos: sólo un partido en estado final aporta
-- puntos a la clasificación.
-- ---------------------------------------------------------------------
-- La conexion del cliente puede llegar en cp850 (consola de Windows). Sin
-- esta linea, un texto con tilde o guion largo se guarda doblemente
-- codificado y se lee como caracteres rotos.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dsl_estado (
    estado_id       INT AUTO_INCREMENT PRIMARY KEY,

    -- Entidad a la que pertenece: 'partido', 'inscripcion', 'serie',
    -- 'sorteo', 'obligacion', 'comprobante'.
    estado_entidad  VARCHAR(30)  NOT NULL,

    estado_codigo   VARCHAR(20)  NOT NULL,
    estado_nombre   VARCHAR(60)  NOT NULL,

    -- Color semántico para la interfaz. Se guarda el NOMBRE del token
    -- ('exito', 'aviso', 'peligro', 'neutro'), no un hexadecimal: el
    -- valor concreto lo decide la hoja de estilos y así un cambio de
    -- identidad visual no obliga a un UPDATE.
    estado_tono     VARCHAR(20)  NOT NULL DEFAULT 'neutro',

    estado_orden    SMALLINT     NOT NULL DEFAULT 0,

    -- Terminal: no existe ninguna transición que salga de aquí.
    estado_final    CHAR(1)      NOT NULL DEFAULT 'N',

    -- Efectivo: la entidad SURTE EFECTO en este estado. Es una pregunta
    -- distinta de la anterior y por eso son dos columnas y no una. Un
    -- partido CANCELADO es terminal y no cuenta para la clasificación;
    -- uno declarado WALKOVER también es terminal y sí cuenta. Una
    -- inscripción HABILITADA permite jugar y sin embargo no es terminal,
    -- porque el equipo todavía puede retirarse.
    estado_efectivo CHAR(1)      NOT NULL DEFAULT 'N',

    estado_activo   CHAR(1)      NOT NULL DEFAULT 'S',

    UNIQUE KEY uk_dsl_estado (estado_entidad, estado_codigo),
    KEY ix_dsl_estado_ent (estado_entidad, estado_orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Transiciones permitidas
--
-- Una fila = un movimiento legal. Lo que no está, no se puede hacer.
--
-- trans_motivo obliga a justificar el cambio cuando la transición lo
-- exige: suspender o cancelar un partido sin motivo escrito es
-- precisamente lo que luego no se puede explicar.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_estado_transicion (
    trans_id        INT AUTO_INCREMENT PRIMARY KEY,
    trans_entidad   VARCHAR(30) NOT NULL,
    trans_desde     INT         NOT NULL,
    trans_hasta     INT         NOT NULL,

    -- Etiqueta de la acción tal como se le muestra al usuario:
    -- «Confirmar», «Suspender», «Dar por finalizado».
    trans_accion    VARCHAR(60) NOT NULL,

    trans_motivo    CHAR(1)     NOT NULL DEFAULT 'N',
    trans_activo    CHAR(1)     NOT NULL DEFAULT 'S',

    UNIQUE KEY uk_dsl_trans (trans_desde, trans_hasta),
    KEY ix_dsl_trans_ent (trans_entidad),

    CONSTRAINT fk_dsl_trans_desde FOREIGN KEY (trans_desde)
        REFERENCES dsl_estado (estado_id),
    CONSTRAINT fk_dsl_trans_hasta FOREIGN KEY (trans_hasta)
        REFERENCES dsl_estado (estado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Auditoría
--
-- Una sola tabla para todo el módulo, escrita por un servicio y nunca
-- por los controladores directamente.
--
-- Se guardan los valores ANTES y DESPUÉS en JSON, no una frase
-- descriptiva: una frase responde «se cambió el resultado», que es justo
-- lo que no sirve cuando hay que demostrar cuál era el marcador anterior.
--
-- audit_ip es VARBINARY(16) para admitir IPv6 y guardarse con INET6_ATON,
-- igual que en seguridad_intento_acceso.
--
-- No lleva clave foránea al usuario a propósito: un registro de auditoría
-- debe sobrevivir al borrado de la cuenta que lo originó.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dsl_auditoria (
    audit_id        BIGINT AUTO_INCREMENT PRIMARY KEY,

    audit_entidad   VARCHAR(30)  NOT NULL,
    audit_entidadid INT          NOT NULL,

    -- 'crear' | 'editar' | 'eliminar' | 'estado' | 'sortear' |
    -- 'reprogramar' | 'facturar'
    audit_accion    VARCHAR(20)  NOT NULL,

    audit_usuarioid INT          NULL,
    audit_usuario   VARCHAR(20)  NOT NULL DEFAULT '',
    audit_ip        VARBINARY(16) NULL,

    audit_antes     JSON         NULL,
    audit_despues   JSON         NULL,
    audit_nota      VARCHAR(250) NOT NULL DEFAULT '',

    audit_fecha     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- El índice que sirve a la pregunta habitual: «qué le pasó a este
    -- partido», en orden cronológico inverso.
    KEY ix_dsl_audit_ent  (audit_entidad, audit_entidadid, audit_fecha),
    KEY ix_dsl_audit_user (audit_usuarioid, audit_fecha),
    KEY ix_dsl_audit_fecha (audit_fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =====================================================================
-- Estados del partido y sus transiciones
-- =====================================================================
-- WALKOVER es efectivo: el partido no se jugó, pero se adjudica y suma.
-- CANCELADO no lo es: desaparece del cómputo.
INSERT INTO dsl_estado (estado_entidad, estado_codigo, estado_nombre, estado_tono, estado_orden, estado_final, estado_efectivo) VALUES
    ('partido', 'PROGRAMADO',   'Programado',    'neutro',  10, 'N', 'N'),
    ('partido', 'CONFIRMADO',   'Confirmado',    'info',    20, 'N', 'N'),
    ('partido', 'EN_JUEGO',     'En juego',      'aviso',   30, 'N', 'N'),
    ('partido', 'FINALIZADO',   'Finalizado',    'exito',   40, 'S', 'S'),
    ('partido', 'SUSPENDIDO',   'Suspendido',    'aviso',   50, 'N', 'N'),
    ('partido', 'REPROGRAMADO', 'Reprogramado',  'neutro',  60, 'N', 'N'),
    ('partido', 'CANCELADO',    'Cancelado',     'peligro', 70, 'S', 'N'),
    ('partido', 'WALKOVER',     'Walkover',      'peligro', 80, 'S', 'S')
ON DUPLICATE KEY UPDATE estado_nombre    = VALUES(estado_nombre),
                        estado_final     = VALUES(estado_final),
                        estado_efectivo  = VALUES(estado_efectivo);


-- Las transiciones se insertan por CÓDIGO, no por id: los ids dependen
-- del orden de inserción y esta migración debe poder correr sobre una
-- base donde el catálogo ya exista parcialmente.
INSERT INTO dsl_estado_transicion (trans_entidad, trans_desde, trans_hasta, trans_accion, trans_motivo)
SELECT 'partido', D.estado_id, H.estado_id, T.accion, T.motivo
  FROM (
        SELECT 'PROGRAMADO'   AS de, 'CONFIRMADO'   AS a, 'Confirmar'            AS accion, 'N' AS motivo
  UNION SELECT 'PROGRAMADO',        'REPROGRAMADO',      'Reprogramar',               'S'
  UNION SELECT 'PROGRAMADO',        'CANCELADO',         'Cancelar',                  'S'
  UNION SELECT 'CONFIRMADO',        'EN_JUEGO',          'Iniciar',                   'N'
  UNION SELECT 'CONFIRMADO',        'REPROGRAMADO',      'Reprogramar',               'S'
  UNION SELECT 'CONFIRMADO',        'SUSPENDIDO',        'Suspender',                 'S'
  UNION SELECT 'CONFIRMADO',        'CANCELADO',         'Cancelar',                  'S'
  UNION SELECT 'CONFIRMADO',        'WALKOVER',          'Declarar walkover',         'S'
  UNION SELECT 'EN_JUEGO',          'FINALIZADO',        'Dar por finalizado',        'N'
  UNION SELECT 'EN_JUEGO',          'SUSPENDIDO',        'Suspender',                 'S'
  UNION SELECT 'SUSPENDIDO',        'REPROGRAMADO',      'Reprogramar',               'S'
  UNION SELECT 'SUSPENDIDO',        'CANCELADO',         'Cancelar',                  'S'
  UNION SELECT 'SUSPENDIDO',        'WALKOVER',          'Declarar walkover',         'S'
  UNION SELECT 'REPROGRAMADO',      'CONFIRMADO',        'Confirmar nueva fecha',     'N'
  UNION SELECT 'REPROGRAMADO',      'CANCELADO',         'Cancelar',                  'S'
       ) T
  JOIN dsl_estado D ON D.estado_entidad = 'partido' AND D.estado_codigo = T.de
  JOIN dsl_estado H ON H.estado_entidad = 'partido' AND H.estado_codigo = T.a
ON DUPLICATE KEY UPDATE trans_accion = VALUES(trans_accion);


-- =====================================================================
-- Estados de la inscripción
--
-- Separa la revisión documental del pago a propósito: un equipo puede
-- tener los papeles en regla y no haber pagado, o al revés, y el torneo
-- decide cuál de las dos cosas habilita a competir.
-- =====================================================================
-- HABILITADA es la única que permite competir, y NO es terminal: el
-- equipo puede retirarse con el torneo empezado.
INSERT INTO dsl_estado (estado_entidad, estado_codigo, estado_nombre, estado_tono, estado_orden, estado_final, estado_efectivo) VALUES
    ('inscripcion', 'BORRADOR',   'Borrador',              'neutro',  10, 'N', 'N'),
    ('inscripcion', 'ENVIADA',    'Enviada a revisión',    'info',    20, 'N', 'N'),
    ('inscripcion', 'OBSERVADA',  'Con observaciones',     'aviso',   30, 'N', 'N'),
    ('inscripcion', 'APROBADA',   'Aprobada',              'exito',   40, 'N', 'N'),
    ('inscripcion', 'HABILITADA', 'Habilitada para jugar', 'exito',   50, 'N', 'S'),
    ('inscripcion', 'RECHAZADA',  'Rechazada',             'peligro', 60, 'S', 'N'),
    ('inscripcion', 'RETIRADA',   'Retirada',              'peligro', 70, 'S', 'N')
ON DUPLICATE KEY UPDATE estado_nombre    = VALUES(estado_nombre),
                        estado_final     = VALUES(estado_final),
                        estado_efectivo  = VALUES(estado_efectivo);

INSERT INTO dsl_estado_transicion (trans_entidad, trans_desde, trans_hasta, trans_accion, trans_motivo)
SELECT 'inscripcion', D.estado_id, H.estado_id, T.accion, T.motivo
  FROM (
        SELECT 'BORRADOR'  AS de, 'ENVIADA'    AS a, 'Enviar a revisión'   AS accion, 'N' AS motivo
  UNION SELECT 'ENVIADA',        'OBSERVADA',      'Observar',                 'S'
  UNION SELECT 'ENVIADA',        'APROBADA',       'Aprobar',                  'N'
  UNION SELECT 'ENVIADA',        'RECHAZADA',      'Rechazar',                 'S'
  UNION SELECT 'OBSERVADA',      'ENVIADA',        'Reenviar corregida',       'N'
  UNION SELECT 'OBSERVADA',      'RECHAZADA',      'Rechazar',                 'S'
  UNION SELECT 'APROBADA',       'HABILITADA',     'Habilitar',                'N'
  UNION SELECT 'APROBADA',       'RETIRADA',       'Retirar',                  'S'
  UNION SELECT 'HABILITADA',     'RETIRADA',       'Retirar',                  'S'
       ) T
  JOIN dsl_estado D ON D.estado_entidad = 'inscripcion' AND D.estado_codigo = T.de
  JOIN dsl_estado H ON H.estado_entidad = 'inscripcion' AND H.estado_codigo = T.a
ON DUPLICATE KEY UPDATE trans_accion = VALUES(trans_accion);
