-- =====================================================================
-- 041 · Un solo juego de caracteres y un solo cotejamiento
-- =====================================================================
-- POR QUÉ IMPORTA MÁS DE LO QUE PARECE
--
-- La base tenía cuatro cotejamientos conviviendo. Cuando una consulta une
-- dos tablas que no coinciden, MySQL lanza «Illegal mix of collations».
-- Eso ya pasó una vez con la pantalla de puntos de emisión, y el síntoma
-- no fue un error visible: fue una pantalla VACÍA, porque el helper que
-- ejecuta la consulta se traga la excepción y devuelve un array vacío.
-- Una pantalla vacía se lee como «no hay datos», que es la conclusión
-- equivocada. La migración 022 tapó ese caso concreto; ésta quita la
-- causa.
--
-- EL DESTINO ES utf8mb4_0900_ai_ci
--
-- Es el de las 58 tablas mayoritarias y el predeterminado de MySQL 8.
-- Convergen en él las 22 en utf8mb3 y las 11 en utf8mb4_unicode_ci:
-- dejar estas últimas fuera cambiaría un desajuste por otro del mismo
-- tipo.
--
-- QUÉ CAMBIA EN EL COMPORTAMIENTO, Y POR QUÉ ES ACEPTABLE
--
-- utf8mb3_spanish2_ci trata la «ñ» como letra propia: 'Peña' y 'Pena' son
-- distintas. utf8mb4_0900_ai_ci es insensible a acentos y las considera
-- iguales. Eso afecta al ORDER BY y, sobre todo, a los índices ÚNICOS.
--
-- Se comprobó antes de escribir esto: se recorrieron los 13 índices
-- únicos con columnas de texto fuera del destino, agrupando por el valor
-- YA CONVERTIDO, y ninguno produce duplicados. La conversión no puede
-- fallar por clave duplicada.
--
-- También se verificó que ningún VARCHAR se pasa de 65535 bytes al contar
-- 4 por carácter (MySQL lo degradaría a TEXT en silencio) y que ninguna
-- columna TEXT tiene datos por encima de los 16383 caracteres que caben
-- en utf8mb4.
--
-- LAS CLAVES FORÁNEAS NO ESTORBAN
--
-- Las 8 que cruzan la frontera son todas sobre enteros, y el cotejamiento
-- no se aplica a enteros. No hace falta desactivar la comprobación.
--
-- SIN PÉRDIDA DE DATOS
--
-- utf8mb4 contiene a utf8mb3: todo carácter representable antes lo sigue
-- siendo. La conversión sólo añade lo que antes no cabía (4 bytes).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- El valor por omisión de la base, para que lo que se cree de aquí en
-- adelante nazca ya alineado y no haya que acordarse de ponerlo.
-- ---------------------------------------------------------------------
ALTER DATABASE digitech_barcelona
    CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Núcleo: seguridad y organización.
-- ---------------------------------------------------------------------
ALTER TABLE `seguridad_usuario`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `seguridad_usuario_sede` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `seguridad_rol`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `seguridad_menu`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `seguridad_permiso`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

ALTER TABLE `general_escuela`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `general_sede`           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `general_tabla`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `general_tabla_catalogo` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Sujetos.
--
-- sujeto_empleado ya declaraba utf8mb4 en la tabla pero conservaba nueve
-- columnas en utf8mb3: el caso más traicionero, porque a simple vista
-- parece convertida. CONVERT TO CHARACTER SET arregla tabla y columnas.
-- ---------------------------------------------------------------------
ALTER TABLE `sujeto_alumno`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `sujeto_empleado` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Alumnado y sus datos personales.
-- ---------------------------------------------------------------------
ALTER TABLE `alumno_cemergencia`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `alumno_consentimiento`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `alumno_documentos`           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `alumno_infomedic`            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `alumno_representante`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `alumno_representanteconyuge` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `alumno_carnet`               CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Cobros de Basketball.
-- ---------------------------------------------------------------------
ALTER TABLE `alumno_pago`             CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `alumno_pago_descuento`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `alumno_pago_transaccion` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Asistencia.
-- ---------------------------------------------------------------------
ALTER TABLE `asistencia_asistencia`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `asistencia_horario_detalle` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Carnés.
-- ---------------------------------------------------------------------
ALTER TABLE `carnet_configuracion` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `carnet_catcolor`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `carnet_mes_color`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- Facturación electrónica.
--
-- Es la familia donde el desajuste ya causó un fallo real, así que va
-- entera.
-- ---------------------------------------------------------------------
ALTER TABLE `facturas_electronicas`               CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `facturas_electronicas_detalle`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `facturas_electronicas_pagos`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `facturas_electronicas_config`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `facturas_electronicas_certificado`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `facturas_electronicas_forma_pago`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `facturas_electronicas_punto_emision` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `facturas_electronicas_secuenciales`  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------
-- La vista consolidada de comprobantes, sin los COLLATE de emergencia.
--
-- Se creó llena de «COLLATE utf8mb4_unicode_ci» precisamente para forzar
-- la unión entre dos tablas que no coincidían. Ahora coinciden, y esos
-- casts sobran: peor aún, forzarían el resultado de vuelta al
-- cotejamiento antiguo y recrearían el desajuste que esta migración
-- elimina.
--
-- De paso se corrigen los alias, que en la segunda rama de la UNION
-- habían quedado con la expresión entera como nombre de columna.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_comprobante_emitido AS
    SELECT 'basketball'            AS origen_modulo,
           F.id                    AS origen_id,
           F.clave_acceso          AS clave_acceso,
           F.tipo_comprobante      AS tipo_comprobante,
           F.establecimiento       AS establecimiento,
           F.punto_emision         AS punto_emision,
           F.secuencial            AS secuencial,
           F.fecha_emision         AS fecha_emision,
           F.cliente_identificacion AS cliente_identificacion,
           F.cliente_razon_social  AS cliente_razon_social,
           F.subtotal              AS subtotal,
           F.descuento             AS descuento,
           F.iva                   AS iva,
           F.total                 AS total,
           F.estado_sri            AS estado_sri,
           F.numero_autorizacion   AS numero_autorizacion,
           F.fecha_autorizacion    AS fecha_autorizacion
      FROM facturas_electronicas F

    UNION ALL

    SELECT 'league',
           L.factura_id,
           L.factura_claveacceso,
           L.factura_tipocomprobante,
           L.factura_establecimiento,
           L.factura_puntoemision,
           L.factura_secuencial,
           L.factura_fechaemision,
           L.factura_clienteid_num,
           L.factura_clienterazon,
           L.factura_subtotal,
           L.factura_descuento,
           L.factura_iva,
           L.factura_total,
           L.factura_estadosri,
           L.factura_numautorizacion,
           L.factura_fechaautoriza
      FROM dsl_factura L;
