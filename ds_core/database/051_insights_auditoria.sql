-- =====================================================================
-- 051 · Auditoria de Insights
-- =====================================================================
-- QUE RESUELVE
--
-- El §45 del encargo pide registrar quien vio que y quien se llevo que. En
-- un modulo cuyas pantallas son ingresos, cartera y datos de alumnos, saber
-- que una exportacion salio y de la mano de quien no es burocracia: es lo
-- unico que queda si esa informacion aparece donde no debe.
--
-- No existia auditoria transversal en el ecosistema. Solo dsl_auditoria, que
-- es de League. Esta es propia de Insights.
--
--
-- QUE SE GUARDA Y QUE NO, QUE ES LO IMPORTANTE
--
-- El encargo pide registrar «filtros relevantes». Guardarlos en texto libre
-- seria un error: un filtro puede llevar el nombre o la identificacion de un
-- alumno, y entonces la tabla de auditoria se convierte en un almacen
-- paralelo de datos personales que nadie vigila. Choca de frente con la
-- minimizacion que exige la LOPDP.
--
-- Asi que se guardan los filtros ESTRUCTURADOS y acotados: periodo y sede.
-- Con eso se reconstruye el alcance de lo que alguien vio sin copiar ni un
-- dato de nadie.
--
-- Tampoco se guarda el resultado de la consulta, ni cuantas filas salieron
-- con nombre: solo cuantas. El «que» se sabe por el reporte; el «quien
-- aparecia» no hace falta para auditar.
--
--
-- LA IP VA BINARIA
--
-- VARBINARY(16) admite IPv4 e IPv6 con INET6_ATON, ocupa menos y no invita a
-- buscar por LIKE. Es el mismo criterio que ya usa seguridad_intento_acceso.
-- =====================================================================

CREATE TABLE IF NOT EXISTS insights_auditoria (
    auditoria_id        BIGINT AUTO_INCREMENT PRIMARY KEY,

    auditoria_usuarioid INT          NOT NULL,
    auditoria_rolid     INT          NULL
        COMMENT 'El rol en el momento del hecho: los roles cambian y el registro no debe cambiar con ellos',

    auditoria_accion    VARCHAR(24)  NOT NULL
        COMMENT 'VER_VISTA | EXPORTAR_CSV | EXPORTAR_PDF | IMPRIMIR | ACCESO_DENEGADO',

    auditoria_objeto    VARCHAR(40)  NOT NULL
        COMMENT 'Vista o clave de reporte sobre la que se actuo',

    /* Filtros ACOTADOS. Nada de texto libre: ver la cabecera. */
    auditoria_desde     DATE         NULL,
    auditoria_hasta     DATE         NULL,
    auditoria_sedeid    INT          NULL,

    auditoria_filas     INT          NULL
        COMMENT 'Cuantas filas salieron. El volumen importa para auditar; el contenido no',

    auditoria_ok        CHAR(1)      NOT NULL DEFAULT 'S'
        COMMENT 'S si la accion se completo, N si se denego',

    auditoria_ip        VARBINARY(16) NULL,
    auditoria_fecha     DATETIME     NOT NULL,

    KEY ix_auditoria_usuario (auditoria_usuarioid, auditoria_fecha),
    KEY ix_auditoria_accion  (auditoria_accion, auditoria_fecha),
    KEY ix_auditoria_fecha   (auditoria_fecha),

    CONSTRAINT fk_auditoria_sede FOREIGN KEY (auditoria_sedeid)
        REFERENCES general_sede (sede_id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
