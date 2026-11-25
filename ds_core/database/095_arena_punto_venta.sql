-- =====================================================================
-- 095 · Arena: Punto de Venta y Caja
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- En el establecimiento se venden productos y servicios (agua, gaseosas,
-- bebidas hidratantes, alimentos, accesorios) y se mueve dinero que hoy no
-- queda en el sistema: no se sabe qué se vendió, cuánto queda en bodega ni
-- si el efectivo del cajón cuadra al final del turno.
--
--
-- QUE HACE ESTA MIGRACION
--
--   Productos e inventario
--     dsa_producto_categoria  Bebidas, alimentos, accesorios…
--     dsa_producto            código único, precio de compra y de venta,
--                             stock actual y mínimo, si controla vencimiento,
--                             imagen; P producto (lleva stock) o S servicio.
--     dsa_kardex              cada entrada y salida de stock con el saldo
--                             antes y después (INI inicial, COM compra, VEN
--                             venta, ANV anulación de venta, AJE/AJS ajuste,
--                             VEC baja por vencimiento, ANC anulación de compra).
--
--   Compras (separadas de la caja)
--     dsa_compra, dsa_compra_detalle  lo que entra a bodega, con su costo y,
--                             si corresponde, su fecha de vencimiento.
--     dsa_compra_pago         lo que se paga de la compra, cuando se paga:
--                             una compra puede quedar por pagar.
--
--   Caja (una por usuario)
--     dsa_caja                apertura con saldo inicial, cierre con arqueo:
--                             esperado, contado y diferencia. Un usuario no
--                             puede tener dos cajas abiertas (lo impide la
--                             clave única sobre caja_abierta).
--     dsa_caja_movimiento     ingresos y egresos adicionales, pagos de
--                             compras y devoluciones por ventas anuladas.
--
--   Ventas
--     dsa_venta, dsa_venta_detalle, dsa_venta_pago  la nota de venta, sus
--                             líneas (con el precio y el costo de ese día) y
--                             cómo se pagó. El cliente es opcional. El
--                             descuento y la anulación guardan quién los
--                             autorizó y por qué.
--
--   Menú: «Punto de Venta y Caja» después de «Monedero», con Vender,
--   Ventas, Caja, Productos y Compras, y la nota de venta (oculta). Cada rol
--   recibe sobre ellas los permisos que ya tiene sobre «Monedero».
--
--
-- SE PUEDE REPETIR
--
-- Tablas con IF NOT EXISTS; menú y permisos con NOT EXISTS. No consulta
-- information_schema: se puede ejecutar desde phpMyAdmin sin el problema de
-- la base activa (ver 092 y 094). Todas las tablas son InnoDB; las claves
-- ajenas son sólo entre ellas.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dsa_producto_categoria (
    categoria_id     INT          NOT NULL AUTO_INCREMENT,
    categoria_nombre VARCHAR(60)  NOT NULL,
    categoria_orden  INT          NOT NULL DEFAULT 0,
    categoria_estado CHAR(1)      NOT NULL DEFAULT 'A' COMMENT 'A activa, I inactiva',
    PRIMARY KEY (categoria_id),
    UNIQUE KEY uq_categoria_nombre (categoria_nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: categorías de productos (095)';

CREATE TABLE IF NOT EXISTS dsa_producto (
    producto_id           INT           NOT NULL AUTO_INCREMENT,
    producto_codigo       VARCHAR(30)   NOT NULL COMMENT 'Identificador único (también el código de barras)',
    producto_nombre       VARCHAR(100)  NOT NULL,
    producto_categoriaid  INT           NULL,
    producto_tipo         CHAR(1)       NOT NULL DEFAULT 'P' COMMENT 'P producto (lleva stock), S servicio',
    producto_preciocompra DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo: el de la última compra',
    producto_precioventa  DECIMAL(10,2) NOT NULL,
    producto_stock        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    producto_stockminimo  DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Alerta de reposición',
    producto_vence        CHAR(1)       NOT NULL DEFAULT 'N' COMMENT 'S si se controla su fecha de vencimiento',
    producto_imagen       VARCHAR(80)   NULL,
    producto_estado       CHAR(1)       NOT NULL DEFAULT 'A' COMMENT 'A activo, I inactivo',
    producto_usuarioid    INT           NULL,
    producto_fecharegistro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    producto_fechacambio  TIMESTAMP     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (producto_id),
    UNIQUE KEY uq_producto_codigo (producto_codigo),
    KEY ix_producto_categoria (producto_categoriaid),
    CONSTRAINT fk_producto_categoria FOREIGN KEY (producto_categoriaid) REFERENCES dsa_producto_categoria (categoria_id),
    CONSTRAINT ck_producto_tipo   CHECK (producto_tipo IN ('P','S')),
    CONSTRAINT ck_producto_precio CHECK (producto_precioventa >= 0 AND producto_preciocompra >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: productos y servicios (095)';

CREATE TABLE IF NOT EXISTS dsa_caja (
    caja_id           INT           NOT NULL AUTO_INCREMENT,
    caja_usuarioid    INT           NOT NULL COMMENT 'Responsable: quien la abrió',
    caja_sedeid       INT           NULL,
    caja_apertura     DATETIME      NOT NULL,
    caja_saldoinicial DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    caja_cierre       DATETIME      NULL,
    caja_esperado     DECIMAL(10,2) NULL COMMENT 'Efectivo que debía haber al cerrar',
    caja_contado      DECIMAL(10,2) NULL COMMENT 'Efectivo contado al cerrar',
    caja_diferencia   DECIMAL(10,2) NULL COMMENT 'contado − esperado: + sobra, − falta',
    caja_conteo       VARCHAR(600)  NULL COMMENT 'Billetes y monedas contados (JSON)',
    caja_resumen      TEXT          NULL COMMENT 'Las cifras de la caja al cerrarla (JSON)',
    caja_observacion  VARCHAR(250)  NULL,
    caja_estado       CHAR(1)       NOT NULL DEFAULT 'A' COMMENT 'A abierta, C cerrada',
    caja_abierta      INT GENERATED ALWAYS AS (IF(caja_estado = 'A', caja_usuarioid, NULL)) STORED
                      COMMENT 'Una sola caja abierta por usuario',
    PRIMARY KEY (caja_id),
    UNIQUE KEY uq_caja_abierta (caja_abierta),
    KEY ix_caja_usuario (caja_usuarioid, caja_apertura),
    CONSTRAINT ck_caja_estado CHECK (caja_estado IN ('A','C'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: cajas, una abierta por usuario (095)';

CREATE TABLE IF NOT EXISTS dsa_compra (
    compra_id          INT           NOT NULL AUTO_INCREMENT,
    compra_proveedor   VARCHAR(120)  NOT NULL,
    compra_documento   VARCHAR(40)   NULL COMMENT 'Factura o nota del proveedor',
    compra_fecha       DATE          NOT NULL,
    compra_total       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    compra_pagado      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    compra_saldo       DECIMAL(10,2) GENERATED ALWAYS AS (compra_total - compra_pagado) STORED,
    compra_observacion VARCHAR(250)  NULL,
    compra_estado      CHAR(1)       NOT NULL DEFAULT 'A' COMMENT 'A registrada, X anulada',
    compra_usuarioid   INT           NULL,
    compra_fecharegistro TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (compra_id),
    KEY ix_compra_fecha (compra_fecha),
    CONSTRAINT ck_compra_estado CHECK (compra_estado IN ('A','X'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: compras y abastecimiento (095)';

CREATE TABLE IF NOT EXISTS dsa_compra_detalle (
    detalle_id         INT           NOT NULL AUTO_INCREMENT,
    detalle_compraid   INT           NOT NULL,
    detalle_productoid INT           NOT NULL,
    detalle_cantidad   DECIMAL(10,2) NOT NULL,
    detalle_costo      DECIMAL(10,2) NOT NULL COMMENT 'Costo unitario',
    detalle_subtotal   DECIMAL(10,2) NOT NULL,
    detalle_vence      DATE          NULL COMMENT 'Fecha de vencimiento del lote',
    PRIMARY KEY (detalle_id),
    KEY ix_cdet_compra (detalle_compraid),
    KEY ix_cdet_producto (detalle_productoid, detalle_vence),
    CONSTRAINT fk_cdet_compra   FOREIGN KEY (detalle_compraid)   REFERENCES dsa_compra (compra_id),
    CONSTRAINT fk_cdet_producto FOREIGN KEY (detalle_productoid) REFERENCES dsa_producto (producto_id),
    CONSTRAINT ck_cdet_cantidad CHECK (detalle_cantidad > 0 AND detalle_costo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: líneas de cada compra (095)';

CREATE TABLE IF NOT EXISTS dsa_caja_movimiento (
    movimiento_id        INT           NOT NULL AUTO_INCREMENT,
    movimiento_cajaid    INT           NOT NULL,
    movimiento_tipo      CHAR(1)       NOT NULL COMMENT 'I ingreso, E egreso',
    movimiento_origen    CHAR(3)       NOT NULL DEFAULT 'MAN' COMMENT 'MAN manual, COM pago de compra, ANV devolución de venta anulada',
    movimiento_concepto  VARCHAR(120)  NOT NULL,
    movimiento_forma     CHAR(3)       NOT NULL DEFAULT 'EFE' COMMENT 'dsa_forma_ingreso.forma_codigo',
    movimiento_valor     DECIMAL(10,2) NOT NULL,
    movimiento_compraid  INT           NULL,
    movimiento_ventaid   INT           NULL,
    movimiento_detalle   VARCHAR(200)  NULL,
    movimiento_usuarioid INT           NULL,
    movimiento_estado    CHAR(1)       NOT NULL DEFAULT 'A',
    movimiento_fecharegistro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (movimiento_id),
    KEY ix_cmov_caja (movimiento_cajaid),
    CONSTRAINT fk_cmov_caja FOREIGN KEY (movimiento_cajaid) REFERENCES dsa_caja (caja_id),
    CONSTRAINT ck_cmov_tipo  CHECK (movimiento_tipo IN ('I','E')),
    CONSTRAINT ck_cmov_valor CHECK (movimiento_valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: ingresos y egresos de la caja (095)';

CREATE TABLE IF NOT EXISTS dsa_compra_pago (
    pago_id         INT           NOT NULL AUTO_INCREMENT,
    pago_compraid   INT           NOT NULL,
    pago_formaid    INT           NOT NULL COMMENT 'dsa_forma_ingreso.forma_id',
    pago_valor      DECIMAL(10,2) NOT NULL,
    pago_referencia VARCHAR(60)   NULL,
    pago_fecha      DATE          NOT NULL,
    pago_cajaid     INT           NULL COMMENT 'Si salió del efectivo de una caja',
    pago_usuarioid  INT           NULL,
    pago_estado     CHAR(1)       NOT NULL DEFAULT 'A',
    pago_fecharegistro TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pago_id),
    KEY ix_cpago_compra (pago_compraid),
    CONSTRAINT fk_cpago_compra FOREIGN KEY (pago_compraid) REFERENCES dsa_compra (compra_id),
    CONSTRAINT ck_cpago_valor  CHECK (pago_valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: pagos de las compras (095)';

CREATE TABLE IF NOT EXISTS dsa_venta (
    venta_id                INT           NOT NULL AUTO_INCREMENT,
    venta_numero            VARCHAR(20)   NULL COMMENT 'Nota de venta, p. ej. NV-000123',
    venta_cajaid            INT           NOT NULL,
    venta_clienteid         INT           NULL COMMENT 'Opcional: dsa_cliente',
    venta_subtotal          DECIMAL(10,2) NOT NULL,
    venta_descuento         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    venta_total             DECIMAL(10,2) NOT NULL,
    venta_descuentomotivo   VARCHAR(150)  NULL,
    venta_descuentoautoriza INT           NULL COMMENT 'Usuario que autorizó el descuento',
    venta_estado            CHAR(1)       NOT NULL DEFAULT 'A' COMMENT 'A vigente, X anulada',
    venta_anulamotivo       VARCHAR(200)  NULL,
    venta_anulaautoriza     INT           NULL COMMENT 'Usuario que autorizó la anulación',
    venta_anulausuario      INT           NULL COMMENT 'Usuario que la anuló',
    venta_anulafecha        DATETIME      NULL,
    venta_usuarioid         INT           NOT NULL,
    venta_fecharegistro     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (venta_id),
    UNIQUE KEY uq_venta_numero (venta_numero),
    KEY ix_venta_caja (venta_cajaid),
    KEY ix_venta_fecha (venta_fecharegistro),
    CONSTRAINT fk_venta_caja   FOREIGN KEY (venta_cajaid) REFERENCES dsa_caja (caja_id),
    CONSTRAINT ck_venta_estado CHECK (venta_estado IN ('A','X')),
    CONSTRAINT ck_venta_montos CHECK (venta_descuento >= 0 AND venta_total >= 0 AND venta_descuento <= venta_subtotal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: notas de venta (095)';

CREATE TABLE IF NOT EXISTS dsa_venta_detalle (
    detalle_id         INT           NOT NULL AUTO_INCREMENT,
    detalle_ventaid    INT           NOT NULL,
    detalle_productoid INT           NOT NULL,
    detalle_nombre     VARCHAR(100)  NOT NULL COMMENT 'El nombre de ese día',
    detalle_cantidad   DECIMAL(10,2) NOT NULL,
    detalle_precio     DECIMAL(10,2) NOT NULL COMMENT 'Precio de venta de ese día',
    detalle_costo      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo de ese día (para el margen)',
    detalle_subtotal   DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (detalle_id),
    KEY ix_vdet_venta (detalle_ventaid),
    KEY ix_vdet_producto (detalle_productoid),
    CONSTRAINT fk_vdet_venta    FOREIGN KEY (detalle_ventaid)    REFERENCES dsa_venta (venta_id),
    CONSTRAINT fk_vdet_producto FOREIGN KEY (detalle_productoid) REFERENCES dsa_producto (producto_id),
    CONSTRAINT ck_vdet_cantidad CHECK (detalle_cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: líneas de cada venta (095)';

CREATE TABLE IF NOT EXISTS dsa_venta_pago (
    pago_id         INT           NOT NULL AUTO_INCREMENT,
    pago_ventaid    INT           NOT NULL,
    pago_formaid    INT           NOT NULL COMMENT 'dsa_forma_ingreso.forma_id',
    pago_valor      DECIMAL(10,2) NOT NULL COMMENT 'Lo que se aplica a la venta',
    pago_recibido   DECIMAL(10,2) NULL     COMMENT 'Efectivo entregado por el cliente',
    pago_cambio     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pago_referencia VARCHAR(60)   NULL,
    PRIMARY KEY (pago_id),
    KEY ix_vpago_venta (pago_ventaid),
    CONSTRAINT fk_vpago_venta FOREIGN KEY (pago_ventaid) REFERENCES dsa_venta (venta_id),
    CONSTRAINT ck_vpago_valor CHECK (pago_valor >= 0 AND pago_cambio >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: cómo se pagó cada venta (095)';

CREATE TABLE IF NOT EXISTS dsa_kardex (
    kardex_id            INT           NOT NULL AUTO_INCREMENT,
    kardex_productoid    INT           NOT NULL,
    kardex_tipo          CHAR(1)       NOT NULL COMMENT 'E entrada, S salida',
    kardex_origen        CHAR(3)       NOT NULL COMMENT 'INI, COM, VEN, ANV, AJE, AJS, VEC, ANC',
    kardex_cantidad      DECIMAL(10,2) NOT NULL,
    kardex_costo         DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo unitario del movimiento',
    kardex_stockanterior DECIMAL(10,2) NOT NULL,
    kardex_stocknuevo    DECIMAL(10,2) NOT NULL,
    kardex_ventaid       INT           NULL,
    kardex_compraid      INT           NULL,
    kardex_detalle       VARCHAR(200)  NULL,
    kardex_usuarioid     INT           NULL,
    kardex_fecharegistro TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (kardex_id),
    KEY ix_kardex_producto (kardex_productoid, kardex_fecharegistro),
    CONSTRAINT fk_kardex_producto FOREIGN KEY (kardex_productoid) REFERENCES dsa_producto (producto_id),
    CONSTRAINT ck_kardex_tipo     CHECK (kardex_tipo IN ('E','S')),
    CONSTRAINT ck_kardex_cantidad CHECK (kardex_cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Arena · Punto de venta: kardex de inventario (095)';

-- Categorías de partida (se pueden cambiar en Productos).
INSERT INTO dsa_producto_categoria (categoria_nombre, categoria_orden)
SELECT x.n, x.o FROM (SELECT 'Bebidas' n, 1 o UNION ALL SELECT 'Alimentos', 2
                      UNION ALL SELECT 'Accesorios', 3 UNION ALL SELECT 'Servicios', 4) x
 WHERE NOT EXISTS (SELECT 1 FROM dsa_producto_categoria);

-- ---------------------------------------------------------------------
-- Menú: el grupo después de «Monedero»; «Reportes» pasa detrás.
-- ---------------------------------------------------------------------
UPDATE seguridad_menu SET menu_orden = 9
 WHERE menu_modulo = 'arena' AND menu_hijo = 'S' AND menu_nombre = 'Reportes' AND menu_orden = 8;

INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'arena', 'Punto de Venta y Caja', 8, 0, 'S', '', 'nav-icon fas fa-cash-register', 'A'
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu WHERE menu_modulo = 'arena' AND menu_hijo = 'S' AND menu_nombre = 'Punto de Venta y Caja');

INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'arena', x.nombre, x.orden, g.menu_id, 'N', x.vista, x.icono, x.estado
  FROM (SELECT 'Vender' nombre, 1 orden, 'posVenta' vista, 'nav-icon fas fa-basket-shopping' icono, 'A' estado
        UNION ALL SELECT 'Caja',       3, 'posCaja',      'nav-icon fas fa-cash-register',    'A'
        UNION ALL SELECT 'Productos',  4, 'posProductos', 'nav-icon fas fa-boxes-stacked',    'A'
        UNION ALL SELECT 'Compras',    5, 'posCompras',   'nav-icon fas fa-truck-ramp-box',   'A'
        UNION ALL SELECT 'Nota de venta', 99, 'posTicket', 'nav-icon fas fa-ticket',          'O') x
  JOIN seguridad_menu g ON g.menu_modulo = 'arena' AND g.menu_hijo = 'S' AND g.menu_nombre = 'Punto de Venta y Caja'
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu m WHERE m.menu_modulo = 'arena' AND m.menu_vista = x.vista);

-- «Ventas» (el listado del día) va en «Reportes», después de «Pagos Reservas».
-- El UPDATE la mueve donde la 095 ya la había creado dentro del punto de
-- venta; los permisos van por menu_id y no cambian.
INSERT INTO seguridad_menu (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'arena', 'Ventas', 2, r.menu_id, 'N', 'posVentas', 'nav-icon fas fa-receipt', 'A'
  FROM seguridad_menu r
 WHERE r.menu_modulo = 'arena' AND r.menu_hijo = 'S' AND r.menu_nombre = 'Reportes'
   AND NOT EXISTS (SELECT 1 FROM seguridad_menu m WHERE m.menu_modulo = 'arena' AND m.menu_vista = 'posVentas');

UPDATE seguridad_menu v
  JOIN seguridad_menu r ON r.menu_modulo = 'arena' AND r.menu_hijo = 'S' AND r.menu_nombre = 'Reportes'
   SET v.menu_padreid = r.menu_id, v.menu_orden = 2
 WHERE v.menu_modulo = 'arena' AND v.menu_vista = 'posVentas';

-- Permisos: los mismos que cada rol ya tiene sobre «Monedero».
INSERT INTO seguridad_permiso (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear, permiso_editar,
                               permiso_eliminar, permiso_exportar, permiso_estado)
SELECT pm.permiso_rolid, m.menu_id, pm.permiso_ver, pm.permiso_crear, pm.permiso_editar,
       pm.permiso_eliminar, pm.permiso_exportar, pm.permiso_estado
  FROM seguridad_permiso pm
  JOIN seguridad_menu mm ON mm.menu_id = pm.permiso_menuid AND mm.menu_modulo = 'arena' AND mm.menu_vista = 'monederoList'
  JOIN seguridad_menu m  ON m.menu_modulo = 'arena' AND m.menu_vista IN ('posVenta','posVentas','posCaja','posProductos','posCompras','posTicket')
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_permiso p WHERE p.permiso_rolid = pm.permiso_rolid AND p.permiso_menuid = m.menu_id);

-- Comprobación
SELECT m.menu_nombre, m.menu_vista, m.menu_orden, m.menu_estado,
       (SELECT COUNT(*) FROM seguridad_permiso p WHERE p.permiso_menuid = m.menu_id) AS roles
  FROM seguridad_menu m
 WHERE m.menu_modulo = 'arena' AND (m.menu_vista LIKE 'pos%' OR m.menu_nombre = 'Punto de Venta y Caja')
 ORDER BY m.menu_orden;
