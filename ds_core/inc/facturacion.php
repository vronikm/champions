<?php
/*
|--------------------------------------------------------------------------
| Puntos de emisión y numeración de comprobantes
|--------------------------------------------------------------------------
| Servicio compartido del ecosistema. Vive en el núcleo porque la
| numeración de comprobantes es del contribuyente, no de un módulo: si
| cada uno llevara su propia cuenta, dos podrían emitir el mismo número.
|
| EL MODELO EN UNA FRASE
|
| El SRI exige que el secuencial sea único dentro de la terna
| (tipo de comprobante, establecimiento, punto de emisión). Cada módulo
| tiene asignado su propio punto de emisión, de modo que sus rangos no
| pueden solaparse. La tabla facturas_electronicas_punto_emision guarda esa
| asignación con una clave única sobre (establecimiento, punto), que es lo
| que impide asignar el mismo punto dos veces.
|
| La identidad tributaria —RUC, razón social, certificado de firma— NO se
| reparte: sigue siendo una sola y vive en facturas_electronicas_config.
| Un contribuyente, varios puntos de emisión.
*/

if (!function_exists('facturacion_punto_de')) {

    /**
     * Punto de emisión asignado a un módulo.
     *
     * Devuelve [] si el módulo no tiene punto asignado, lo que debe
     * tratarse como «este módulo todavía no puede facturar» y no como un
     * error recuperable: emitir sin punto asignado produciría un
     * comprobante fuera de la numeración declarada.
     */
    function facturacion_punto_de(string $modulo): array
    {
        $con = seguridad_conexion();
        if ($con === null) { return []; }

        try {
            $st = $con->prepare(
                "SELECT punto_id, punto_modulo, punto_establecimiento, punto_codigo,
                        punto_secuencialinicio, punto_estado, punto_descripcion
                   FROM facturas_electronicas_punto_emision
                  WHERE punto_modulo = :m"
            );
            $st->execute([':m' => $modulo]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Todos los puntos, para la pantalla de administración del Core. */
    function facturacion_puntos(): array
    {
        $con = seguridad_conexion();
        if ($con === null) { return []; }

        try {
            return $con->query(
                "SELECT punto_id, punto_modulo, punto_establecimiento, punto_codigo,
                        punto_secuencialinicio, punto_estado, punto_descripcion,
                        punto_fecharegistro
                   FROM facturas_electronicas_punto_emision
                  ORDER BY punto_establecimiento, punto_codigo"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Último secuencial emitido desde un punto, mirando TODAS las tablas
     * de comprobantes del ecosistema.
     *
     * Se consulta la vista consolidada en lugar de una tabla concreta: es
     * lo que permite detectar que un punto ya fue usado por otro módulo,
     * que es justo el caso que no debe pasar desapercibido.
     *
     * Con $ambiente ('1' pruebas, '2' producción) cuenta sólo los de ese
     * ambiente. La vista no lo trae como columna, pero va dentro de la
     * clave de acceso —posición 24, fijada por el SRI—, que todos los
     * módulos guardan.
     */
    function facturacion_ultimo_emitido(string $tipo, string $establecimiento, string $punto,
                                        ?string $ambiente = null): int
    {
        $con = seguridad_conexion();
        if ($con === null) { return 0; }

        try {
            $sql = "SELECT COALESCE(MAX(CAST(secuencial AS UNSIGNED)), 0)
                      FROM v_comprobante_emitido
                     WHERE tipo_comprobante = :t
                       AND establecimiento  = :e
                       AND punto_emision    = :p";
            $par = [':t' => $tipo, ':e' => $establecimiento, ':p' => $punto];
            if ($ambiente !== null) {
                $sql .= " AND SUBSTRING(clave_acceso, 24, 1) = :a";
                $par[':a'] = facturacion_ambiente_valido($ambiente);
            }
            $st = $con->prepare($sql);
            $st->execute($par);
            return (int)$st->fetchColumn();

        } catch (\Throwable $e) {
            return 0;
        }
    }

    /*======================================================================
      Numeración por ambiente
      ======================================================================
      El SRI lleva dos registros separados: lo emitido en pruebas (celcer)
      no existe en producción (cel), y al revés. Con un solo contador, cada
      factura de ensayo gastaba un número de la serie real y dejaba un
      hueco en producción; y al volver a pruebas no había forma de elegir
      con qué número seguir. Por eso el contador
      facturas_electronicas_secuenciales lleva la columna ambiente
      (migración 084_secuencial_por_ambiente).
      ====================================================================*/

    /** '1' o '2'; cualquier otra cosa se trata como pruebas. */
    function facturacion_ambiente_valido(?string $ambiente): string
    {
        return (string)$ambiente === '2' ? '2' : '1';
    }

    /** Ambiente configurado en Core › Facturación electrónica. */
    function facturacion_ambiente_actual(): string
    {
        $con = seguridad_conexion();
        if ($con === null) { return '1'; }
        try {
            $v = $con->query("SELECT ambiente FROM facturas_electronicas_config
                               WHERE config_lock = 'X' LIMIT 1")->fetchColumn();
            return facturacion_ambiente_valido($v === false ? '1' : (string)$v);
        } catch (\Throwable $e) {
            return '1';
        }
    }

    /**
     * El número que llevará el próximo comprobante de la terna en ese
     * ambiente: lo que diga el contador y nunca por debajo de lo emitido.
     * Es exactamente lo que devolvería la próxima reserva (salvo el suelo
     * del punto de emisión, que sólo aplica a los módulos con punto propio).
     */
    function facturacion_siguiente_secuencial(string $tipo, string $establecimiento, string $punto,
                                              string $ambiente): int
    {
        $ambiente = facturacion_ambiente_valido($ambiente);
        $contador = 0;
        $con = seguridad_conexion();
        if ($con !== null) {
            try {
                $st = $con->prepare("SELECT secuencial_actual FROM facturas_electronicas_secuenciales
                                      WHERE tipo_comprobante = :t AND establecimiento = :e
                                        AND punto_emision = :p AND ambiente = :a");
                $st->execute([':t' => $tipo, ':e' => $establecimiento, ':p' => $punto, ':a' => $ambiente]);
                $contador = (int)$st->fetchColumn();
            } catch (\Throwable $e) {
                $contador = 0;
            }
        }
        return max(1, $contador + 1, facturacion_ultimo_emitido($tipo, $establecimiento, $punto, $ambiente) + 1);
    }

    /**
     * Fija el número que llevará el próximo comprobante de la terna en ese
     * ambiente. Devuelve '' si lo guardó, o el motivo si no.
     *
     * Se puede subir o bajar, pero nunca por debajo de lo ya emitido en ese
     * ambiente: ese número ya está en el registro del SRI, o reservado por
     * una factura del sistema, y repetirlo da «SECUENCIAL REGISTRADO».
     */
    function facturacion_fijar_siguiente(string $tipo, string $establecimiento, string $punto,
                                         string $ambiente, int $siguiente): string
    {
        $ambiente = facturacion_ambiente_valido($ambiente);
        if ($siguiente < 1 || $siguiente > 999999999) {
            return 'El secuencial debe estar entre 1 y 999999999.';
        }
        $ultimo = facturacion_ultimo_emitido($tipo, $establecimiento, $punto, $ambiente);
        if ($siguiente <= $ultimo) {
            return 'En ' . ($ambiente === '2' ? 'producción' : 'pruebas') . ' ya se emitió hasta el '
                 . $establecimiento . '-' . $punto . '-' . str_pad((string)$ultimo, 9, '0', STR_PAD_LEFT)
                 . '. El siguiente secuencial debe ser ' . ($ultimo + 1) . ' o mayor.';
        }

        $con = seguridad_conexion();
        if ($con === null) { return 'Sin conexión a la base de datos.'; }
        try {
            $st = $con->prepare(
                "INSERT INTO facturas_electronicas_secuenciales
                        (tipo_comprobante, establecimiento, punto_emision, ambiente, secuencial_actual)
                 VALUES (:t, :e, :p, :a, :s)
                 ON DUPLICATE KEY UPDATE secuencial_actual = VALUES(secuencial_actual)"
            );
            $st->execute([':t' => $tipo, ':e' => $establecimiento, ':p' => $punto,
                          ':a' => $ambiente, ':s' => $siguiente - 1]);
            return '';
        } catch (\Throwable $e) {
            error_log('[secuencial] ' . $e->getMessage());
            return 'No se pudo guardar el secuencial. ¿Se aplicó la migración 084_secuencial_por_ambiente?';
        }
    }

    /**
     * Reserva el siguiente secuencial de un módulo y lo devuelve con nueve
     * dígitos, listo para el comprobante.
     *
     * LA RESERVA NO SE DESHACE
     *
     * Se pide una conexión propia a propósito, fuera de la transacción de
     * quien llama. Si la emisión falla después, el número queda saltado y
     * no se reutiliza. Eso es deliberado: el SRI admite huecos en la
     * numeración, pero no admite repeticiones. Devolver el número al bote
     * abriría la puerta a que dos comprobantes distintos lo tomaran.
     *
     * LA ASIGNACIÓN ES ATÓMICA
     *
     * El UPDATE con LAST_INSERT_ID(GREATEST(...)) bloquea la fila y
     * devuelve el valor nuevo en la misma operación. Dos peticiones
     * simultáneas se serializan sobre esa fila y obtienen números
     * distintos; leer y luego escribir por separado no daría esa garantía.
     *
     * LA NUMERACIÓN ES POR AMBIENTE
     *
     * Pruebas y producción llevan contadores distintos. Sin $ambiente se
     * usa el configurado en Core, que es con el que se arma la clave de
     * acceso del comprobante.
     *
     * @throws RuntimeException si el módulo no tiene punto activo.
     */
    function facturacion_reservar_secuencial(string $modulo, string $tipo = '01', ?string $ambiente = null): string
    {
        $punto = facturacion_punto_de($modulo);

        if (!$punto) {
            throw new \RuntimeException(
                'El módulo «' . $modulo . '» no tiene punto de emisión asignado. '
                . 'Asígnelo en Core → Puntos de emisión antes de facturar.'
            );
        }

        if ($punto['punto_estado'] !== 'A') {
            throw new \RuntimeException(
                'El punto de emisión ' . $punto['punto_establecimiento'] . '-'
                . $punto['punto_codigo'] . ' está inactivo. Actívelo en Core → '
                . 'Puntos de emisión antes de facturar desde este módulo.'
            );
        }

        $con = seguridad_conexion();
        if ($con === null) {
            throw new \RuntimeException('Sin conexión a la base de datos.');
        }

        /* LA RESERVA NO PUEDE IR DENTRO DE UNA TRANSACCIÓN AJENA

           seguridad_conexion() devuelve una conexión COMPARTIDA. Si quien
           llama ya abrió una transacción, el contador se incrementaría
           dentro de ella y un rollback posterior devolvería el número al
           bote — justo lo contrario de lo que promete esta función. Peor:
           fallaría en silencio, y el síntoma aparecería mucho después como
           dos comprobantes con el mismo secuencial.

           Se comprueba en vez de confiar en que el llamador respete el
           orden. Reservar primero, abrir la transacción después. */
        if ($con->inTransaction()) {
            throw new \RuntimeException(
                'El secuencial debe reservarse ANTES de abrir la transacción: '
                . 'dentro de ella, un rollback liberaría el número y dos '
                . 'comprobantes podrían acabar con el mismo.');
        }

        $estab = $punto['punto_establecimiento'];
        $pto   = $punto['punto_codigo'];
        $amb   = $ambiente === null ? facturacion_ambiente_actual() : facturacion_ambiente_valido($ambiente);

        /* El suelo de la numeración: nunca por debajo de lo ya emitido desde
           este punto, en este ambiente, por cualquier módulo. El inicio del
           punto de emisión es el de la serie real: sólo cuenta en
           producción; en pruebas se numera libremente. */
        $suelo = max(
            1,
            $amb === '2' ? (int)$punto['punto_secuencialinicio'] : 1,
            facturacion_ultimo_emitido($tipo, $estab, $pto, $amb) + 1
        );

        try {
            /* La fila del contador puede no existir todavía. INSERT IGNORE
               la crea sin pisar la que ya hubiera. */
            $st = $con->prepare(
                "INSERT IGNORE INTO facturas_electronicas_secuenciales
                        (tipo_comprobante, establecimiento, punto_emision, ambiente, secuencial_actual)
                 VALUES (:t, :e, :p, :a, 0)"
            );
            $st->execute([':t' => $tipo, ':e' => $estab, ':p' => $pto, ':a' => $amb]);

            $st = $con->prepare(
                "UPDATE facturas_electronicas_secuenciales
                    SET secuencial_actual = LAST_INSERT_ID(
                            GREATEST(secuencial_actual + 1, CAST(:suelo AS UNSIGNED)))
                  WHERE tipo_comprobante = :t
                    AND establecimiento  = :e
                    AND punto_emision    = :p
                    AND ambiente         = :a"
            );
            $st->execute([':t' => $tipo, ':e' => $estab, ':p' => $pto, ':a' => $amb, ':suelo' => $suelo]);

            $secuencial = (int)$con->lastInsertId();

            if ($secuencial <= 0) {
                throw new \RuntimeException('No fue posible reservar el secuencial.');
            }

            return str_pad((string)$secuencial, 9, '0', STR_PAD_LEFT);

        } catch (\PDOException $e) {
            throw new \RuntimeException('No fue posible reservar el secuencial: ' . $e->getMessage());
        }
    }

    /**
     * Número completo de un comprobante, en el formato con el que lo lee
     * una persona: 001-003-000000001.
     */
    function facturacion_numero(string $establecimiento, string $punto, string $secuencial): string
    {
        return $establecimiento . '-' . $punto . '-' . $secuencial;
    }
}
