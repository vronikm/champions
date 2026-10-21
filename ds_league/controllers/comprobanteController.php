<?php

namespace league\controllers;

/**
 * Emision de comprobantes electronicos desde League.
 *
 * UNA FACTURA, UN COMPRADOR
 *
 * Se pueden agrupar varias obligaciones en un mismo comprobante —es lo
 * normal: inscripcion, carnes y arbitraje del mes— pero todas tienen que
 * ser del mismo equipo. Un comprobante lleva una sola identificacion de
 * comprador, asi que mezclar deudores produciria una factura emitida a
 * nombre de quien no debe la mitad de lo que dice.
 *
 * SE FACTURA AL EQUIPO, NUNCA A LA PERSONA
 *
 * Los conceptos de ambito PERSONA se facturan al equipo de esa persona.
 * En una liga de base la mayoria de jugadores son menores: no tienen RUC,
 * no deben figurar como sujeto de un comprobante, y ademas quien paga es
 * el club. Ver migracion 039.
 *
 * EL NUMERO SE RESERVA ANTES DE LA TRANSACCION
 *
 * Y no se devuelve si algo falla despues. El SRI admite huecos en la
 * numeracion; lo que no admite es repeticiones.
 */
class comprobanteController extends finanzaController
{
    /*==================================================================
      Consulta
      ==================================================================*/

    /**
     * Obligaciones de una categoria que se pueden facturar.
     *
     * Facturable = no anulada, sin comprobante previo y con un equipo al
     * que emitirle. Las de origen PERSONA entran por el equipo de esa
     * persona, no por ella.
     */
    public function facturables(int $categoriaid): array
    {
        return $this->filas(
            "SELECT S.obligacion_id, S.obligacion_deudor, S.obligacion_detalle,
                    S.concepto_nombre, S.total, S.abonado, S.saldo,
                    C.concepto_codigo, C.concepto_ivacodigo,
                    COALESCE(S.obligacion_equipoid, PQ.equipo_id) AS equipoid,
                    Q.equipo_nombre, Q.equipo_idtipo, Q.equipo_identificacion,
                    Q.equipo_razonsocial, Q.equipo_direccion, Q.equipo_email,
                    Q.equipo_telefono
               FROM v_dsl_saldo   S
               JOIN dsl_concepto  C ON C.concepto_id = S.obligacion_conceptoid
               /* El equipo de la persona, para los conceptos de ámbito
                  PERSONA: se factura al club, no al jugador. */
               LEFT JOIN (SELECT PL.plantilla_personaid, I.inscripcion_equipoid AS equipo_id,
                                 I.inscripcion_categoriaid
                            FROM dsl_plantilla   PL
                            JOIN dsl_inscripcion I ON I.inscripcion_id = PL.plantilla_inscripcionid) PQ
                      ON PQ.plantilla_personaid  = S.obligacion_personaid
                     AND PQ.inscripcion_categoriaid = S.obligacion_categoriaid
               LEFT JOIN dsl_equipo Q
                      ON Q.equipo_id = COALESCE(S.obligacion_equipoid, PQ.equipo_id)
              WHERE S.obligacion_categoriaid = :c
                AND S.obligacion_estado <> 'ANULADA'
                AND S.obligacion_facturaid IS NULL
              ORDER BY Q.equipo_nombre, S.obligacion_id",
            [':c' => $categoriaid]
        );
    }

    /** Comprobantes emitidos desde League. */
    public function comprobantes(int $categoriaid = 0, int $limite = 100): array
    {
        $limite = max(1, min(500, $limite));

        $sql = "SELECT F.*,
                       (SELECT COUNT(*) FROM dsl_obligacion O
                         WHERE O.obligacion_facturaid = F.factura_id) AS obligaciones
                  FROM dsl_factura F";
        $par = [];

        if ($categoriaid > 0) {
            $sql .= " WHERE EXISTS (SELECT 1 FROM dsl_obligacion O
                                     WHERE O.obligacion_facturaid = F.factura_id
                                       AND O.obligacion_categoriaid = :c)";
            $par[':c'] = $categoriaid;
        }

        return $this->filas($sql . " ORDER BY F.factura_id DESC LIMIT " . $limite, $par);
    }

    public function comprobante(int $id): array
    {
        $f = $this->fila("SELECT * FROM dsl_factura WHERE factura_id = :id", [':id' => $id]);
        if (!$f) { return []; }

        $f['detalle'] = $this->filas(
            "SELECT * FROM dsl_factura_detalle WHERE detalle_facturaid = :id
              ORDER BY detalle_orden, detalle_id", [':id' => $id]);

        return $f;
    }

    /*==================================================================
      Emision
      ==================================================================*/

    public function emitir(): string
    {
        if (!puede_crear('cobranzaPanel')) { return $this->denegado('emitir comprobantes'); }

        /* ------- 1. Qué se factura ------- */
        $ids = array_values(array_unique(array_filter(array_map(
            'intval', explode(',', (string)($_POST['obligaciones'] ?? ''))))));

        if (!$ids) {
            return $this->respuesta('simple', 'Nada que facturar',
                'Seleccione al menos una obligación.', 'error');
        }
        if (count($ids) > 50) {
            return $this->respuesta('simple', 'Demasiadas líneas',
                'Un comprobante admite hasta 50 líneas. Divida la emisión.', 'error');
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));

        $filas = $this->filas(
            "SELECT O.obligacion_id, O.obligacion_estado, O.obligacion_facturaid,
                    O.obligacion_deudor, O.obligacion_detalle, O.obligacion_categoriaid,
                    (O.obligacion_valor + O.obligacion_recargo - O.obligacion_descuento) AS total,
                    C.concepto_codigo, C.concepto_nombre, C.concepto_ivacodigo,
                    COALESCE(O.obligacion_equipoid, PQ.equipo_id) AS equipoid
               FROM dsl_obligacion O
               JOIN dsl_concepto   C ON C.concepto_id = O.obligacion_conceptoid
               LEFT JOIN (SELECT PL.plantilla_personaid, I.inscripcion_equipoid AS equipo_id,
                                 I.inscripcion_categoriaid
                            FROM dsl_plantilla   PL
                            JOIN dsl_inscripcion I ON I.inscripcion_id = PL.plantilla_inscripcionid) PQ
                      ON PQ.plantilla_personaid     = O.obligacion_personaid
                     AND PQ.inscripcion_categoriaid = O.obligacion_categoriaid
              WHERE O.obligacion_id IN ({$marcas})
              ORDER BY O.obligacion_id",
            $ids
        );

        if (count($filas) !== count($ids)) {
            return $this->respuesta('simple', 'Obligación no encontrada',
                'Alguna de las obligaciones seleccionadas ya no existe. Recargue la pantalla.',
                'error');
        }

        /* ------- 2. Lo que impide emitir ------- */
        $equipos = [];
        foreach ($filas as $f) {
            if ($f['obligacion_estado'] === 'ANULADA') {
                return $this->respuesta('simple', 'Hay una obligación anulada',
                    '«' . $f['concepto_nombre'] . '» está anulada y no puede facturarse.', 'error');
            }
            if ($f['obligacion_facturaid'] !== null) {
                return $this->respuesta('simple', 'Ya facturada',
                    '«' . $f['concepto_nombre'] . '» ya tiene comprobante. Emitir otro '
                    . 'duplicaría el ingreso.', 'error');
            }
            if (!$f['equipoid']) {
                return $this->respuesta('simple', 'Sin equipo al que emitir',
                    '«' . $f['concepto_nombre'] . '» no está asociada a ningún equipo, y un '
                    . 'comprobante necesita un comprador.', 'error');
            }
            $equipos[(int)$f['equipoid']] = true;
        }

        if (count($equipos) > 1) {
            return $this->respuesta('simple', 'Deudores distintos',
                'Las obligaciones seleccionadas son de equipos diferentes. Un comprobante '
                . 'lleva una sola identificación de comprador: emita uno por equipo.', 'error');
        }

        $equipoId = (int)array_key_first($equipos);
        $comprador = $this->compradorDe($equipoId);

        if (!$comprador['ok']) {
            return $this->respuesta('simple', 'Faltan datos tributarios del equipo',
                $comprador['motivo'], 'error');
        }

        $listo = sri_disponible();
        if (!$listo['ok']) {
            return $this->respuesta('simple', 'Facturación no configurada',
                'No se puede emitir: ' . implode('; ', $listo['faltan']) . '.', 'error');
        }

        /* ------- 3. Los importes ------- */
        $cfg        = sri_config();
        $incluyeIva = !empty($cfg['valores_incluyen_iva']);

        $lineas = []; $subtotalIva = 0.0; $subtotal0 = 0.0; $ivaTotal = 0.0; $total = 0.0;
        $orden = 0;

        foreach ($filas as $f) {
            $iva   = sri_tarifa_por_codigo((string)$f['concepto_ivacodigo']);
            $linea = round((float)$f['total'], 2);

            if ($iva['porcentaje'] > 0) {
                if ($incluyeIva) {
                    /* El importe pactado ya lleva IVA: se desagrega hacia
                       atrás para no cobrarlo dos veces. */
                    $base     = round($linea / (1 + $iva['porcentaje'] / 100), 2);
                    $ivaLinea = round($linea - $base, 2);
                } else {
                    $base     = $linea;
                    $ivaLinea = round($base * $iva['porcentaje'] / 100, 2);
                    $linea    = round($base + $ivaLinea, 2);
                }
                $subtotalIva += $base;
            } else {
                $base = $linea; $ivaLinea = 0.0; $subtotal0 += $base;
            }

            $total    += $linea;
            $ivaTotal += $ivaLinea;

            $descripcion = $f['concepto_nombre']
                         . ($f['obligacion_detalle'] !== '' ? ' — ' . $f['obligacion_detalle'] : '')
                         . ' · ' . $f['obligacion_deudor'];

            $lineas[] = [
                'obligacion_id' => (int)$f['obligacion_id'],
                'codigo'        => sri_texto((string)$f['concepto_codigo'], 25),
                'descripcion'   => sri_texto($descripcion, 300),
                'cantidad'      => 1.0,
                'preciounit'    => $base,
                'descuento'     => 0.0,
                'subtotal'      => $base,
                'ivacodigo'     => $iva['codigo_porcentaje'],
                'ivatarifa'     => $iva['porcentaje'],
                'ivavalor'      => $ivaLinea,
                'orden'         => ++$orden,
                'impuestos'     => [[
                    'codigo'            => $iva['codigo'],
                    'codigo_porcentaje' => $iva['codigo_porcentaje'],
                    'tarifa'            => $iva['porcentaje'],
                    'base_imponible'    => $base,
                    'valor'             => $ivaLinea,
                ]],
            ];
        }

        $subtotal    = round($subtotalIva + $subtotal0, 2);
        $ivaTotal    = round($ivaTotal, 2);
        $total       = round($total, 2);
        $subtotalIva = round($subtotalIva, 2);
        $subtotal0   = round($subtotal0, 2);

        if ($total <= 0) {
            return $this->respuesta('simple', 'Total cero',
                'No se emite un comprobante por cero.', 'error');
        }

        $forma = substr(trim((string)($_POST['forma'] ?? '')), 0, 2);
        if (!isset($cfg['formas_pago'][$forma])) {
            $forma = (string)($cfg['forma_pago_default'] ?? '20');
        }

        /* ------- 4. El número, ANTES de la transacción ------- */
        $punto = facturacion_punto_de('league');

        try {
            $secuencial = facturacion_reservar_secuencial('league', '01');
        } catch (\Throwable $e) {
            return $this->respuesta('simple', 'No se pudo reservar el número',
                $e->getMessage(), 'error');
        }

        /* ------- 5. Clave de acceso y XML ------- */
        try {
            $srv  = sri_servicio();
            $ruc  = preg_replace('/\D+/', '', (string)$cfg['emisor']['ruc']);
            $codigoNumerico = $srv->generarCodigoNumerico();

            $claveAcceso = $srv->generarClaveAcceso(
                date('dmY'), '01', $ruc, (string)$cfg['ambiente'],
                $punto['punto_establecimiento'] . $punto['punto_codigo'],
                $secuencial, $codigoNumerico, (string)$cfg['tipo_emision']
            );

            $detallesXml = [];
            foreach ($lineas as $l) {
                $detallesXml[] = [
                    'codigo'                    => $l['codigo'],
                    'descripcion'               => $l['descripcion'],
                    'cantidad'                  => $l['cantidad'],
                    'precio_unitario'           => $l['preciounit'],
                    'descuento'                 => $l['descuento'],
                    'precio_total_sin_impuesto' => $l['subtotal'],
                    'iva_tarifa'                => $l['ivatarifa'],
                    'iva_codigo_porcentaje'     => $l['ivacodigo'],
                    'iva_valor'                 => $l['ivavalor'],
                    'impuestos'                 => $l['impuestos'],
                ];
            }

            $xml = $srv->generarXMLFactura([
                'clave_acceso'    => $claveAcceso,
                'establecimiento' => $punto['punto_establecimiento'],
                'punto_emision'   => $punto['punto_codigo'],
                'secuencial'      => $secuencial,
                'fecha_emision'   => date('d/m/Y'),
                'cliente' => [
                    'tipo_identificacion' => $comprador['idtipo'],
                    'identificacion'      => $comprador['identificacion'],
                    'razon_social'        => $comprador['razonsocial'],
                    'direccion'           => $comprador['direccion'],
                ],
                'totales' => [
                    'subtotal'  => $subtotal,
                    'descuento' => 0.00,
                    'total'     => $total,
                    'impuestos' => $this->impuestosAgrupados($lineas),
                ],
                'detalles' => $detallesXml,
                'pagos'    => [['forma_pago' => $forma, 'total' => $total]],
                'info_adicional' => array_filter([
                    'Email'       => $comprador['email'],
                    'Telefono'    => $comprador['telefono'],
                    'Generado por' => sri_texto(ds_nombre_usuario(), 60),
                ]),
            ]);

            $validacion = $srv->validarXML($xml);
            if (empty($validacion['valido'])) {
                throw new \RuntimeException('El XML no es válido: '
                    . implode('; ', $validacion['errores'] ?? []));
            }

            $rutaXml = $srv->guardarXML($xml, $claveAcceso, 'generados');

        } catch (\Throwable $e) {
            /* El secuencial ya está consumido y NO se devuelve. Se deja
               constancia para que el hueco en la numeración tenga
               explicación cuando alguien lo audite. */
            $this->auditar('factura', 0, 'crear', null,
                ['secuencial' => $secuencial, 'error' => $e->getMessage()],
                'Secuencial reservado y no usado');

            return $this->respuesta('simple', 'No se pudo generar el comprobante',
                $e->getMessage() . ' El número ' . $secuencial . ' queda sin usar: el SRI '
                . 'admite huecos en la numeración, pero no repeticiones.', 'error');
        }

        /* ------- 6. Persistir ------- */
        $con = $this->conexion();
        if ($con === null) {
            return $this->respuesta('simple', 'Sin conexión', 'No hay base de datos.', 'error');
        }

        try {
            $con->beginTransaction();

            $st = $con->prepare(
                "INSERT INTO dsl_factura
                        (factura_origentipo, factura_origenid, factura_concepto,
                         factura_puntoid, factura_claveacceso, factura_tipocomprobante,
                         factura_establecimiento, factura_puntoemision, factura_secuencial,
                         factura_fechaemision, factura_ambiente, factura_tipoemision,
                         factura_clienteidtipo, factura_clienteid_num, factura_clienterazon,
                         factura_clientedir, factura_clienteemail, factura_clientetel,
                         factura_subtotaliva, factura_subtotal0, factura_subtotalnoobj,
                         factura_subtotalexento, factura_subtotal, factura_descuento,
                         factura_iva, factura_total, factura_estadosri,
                         factura_xmlgenerado, factura_usuarioid)
                 VALUES ('OBLIGACION', :oid, :conc, :pid, :clave, '01',
                         :est, :pto, :sec, :fecha, :amb, :temi,
                         :ctipo, :cnum, :crazon, :cdir, :cmail, :ctel,
                         :siva, :s0, 0, 0, :sub, 0, :iva, :tot, 'GENERADA',
                         :xml, :uid)");

            $st->execute([
                ':oid'    => $lineas[0]['obligacion_id'],
                ':conc'   => sri_texto('League · ' . count($lineas) . ' concepto'
                             . (count($lineas) === 1 ? '' : 's'), 250),
                ':pid'    => (int)$punto['punto_id'],
                ':clave'  => $claveAcceso,
                ':est'    => $punto['punto_establecimiento'],
                ':pto'    => $punto['punto_codigo'],
                ':sec'    => $secuencial,
                ':fecha'  => date('Y-m-d'),
                ':amb'    => (string)$cfg['ambiente'],
                ':temi'   => (string)$cfg['tipo_emision'],
                ':ctipo'  => $comprador['idtipo'],
                ':cnum'   => $comprador['identificacion'],
                ':crazon' => $comprador['razonsocial'],
                ':cdir'   => $comprador['direccion'],
                ':cmail'  => $comprador['email'],
                ':ctel'   => $comprador['telefono'],
                ':siva'   => $subtotalIva,
                ':s0'     => $subtotal0,
                ':sub'    => $subtotal,
                ':iva'    => $ivaTotal,
                ':tot'    => $total,
                ':xml'    => $rutaXml,
                ':uid'    => usuario_actual_id() ?: null,
            ]);

            $facturaId = (int)$con->lastInsertId();

            $stD = $con->prepare(
                "INSERT INTO dsl_factura_detalle
                        (detalle_facturaid, detalle_codigo, detalle_descripcion,
                         detalle_cantidad, detalle_preciounit, detalle_descuento,
                         detalle_subtotal, detalle_ivacodigo, detalle_ivatarifa,
                         detalle_ivavalor, detalle_orden)
                 VALUES (:f, :c, :d, :cant, :pu, :desc, :sub, :ic, :it, :iv, :o)");

            $stO = $con->prepare(
                "UPDATE dsl_obligacion SET obligacion_facturaid = :f
                  WHERE obligacion_id = :o AND obligacion_facturaid IS NULL");

            foreach ($lineas as $l) {
                $stD->execute([
                    ':f' => $facturaId, ':c' => $l['codigo'], ':d' => $l['descripcion'],
                    ':cant' => $l['cantidad'], ':pu' => $l['preciounit'],
                    ':desc' => $l['descuento'], ':sub' => $l['subtotal'],
                    ':ic' => $l['ivacodigo'], ':it' => $l['ivatarifa'],
                    ':iv' => $l['ivavalor'], ':o' => $l['orden'],
                ]);

                $stO->execute([':f' => $facturaId, ':o' => $l['obligacion_id']]);

                /* El WHERE ... IS NULL es la última defensa contra dos
                   emisiones simultáneas sobre la misma obligación: si otra
                   petición la facturó entre la comprobación y este UPDATE,
                   no afecta filas y se deshace todo. */
                if ($stO->rowCount() !== 1) {
                    throw new \RuntimeException(
                        'La obligación ' . $l['obligacion_id'] . ' fue facturada por otra '
                        . 'operación mientras se emitía este comprobante.');
                }
            }

            $con->prepare("INSERT INTO dsl_factura_pago
                                  (pago_facturaid, pago_forma, pago_valor)
                           VALUES (:f, :fo, :v)")
                ->execute([':f' => $facturaId, ':fo' => $forma, ':v' => $total]);

            $con->commit();

        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }

            $this->auditar('factura', 0, 'crear', null,
                ['secuencial' => $secuencial, 'error' => $e->getMessage()],
                'Secuencial reservado y no usado');

            return $this->respuesta('simple', 'No se pudo registrar el comprobante',
                $e->getMessage() . ' El número ' . $secuencial . ' queda sin usar.', 'error');
        }

        $numero = facturacion_numero($punto['punto_establecimiento'],
                                     $punto['punto_codigo'], $secuencial);

        $this->auditar('factura', $facturaId, 'crear', null,
            ['numero' => $numero, 'clave' => $claveAcceso, 'total' => $total,
             'comprador' => $comprador['identificacion'],
             'obligaciones' => array_column($lineas, 'obligacion_id')]);

        return $this->respuesta('recargar', 'Comprobante generado',
            $numero . ' · ' . $comprador['razonsocial'] . ' · ' . number_format($total, 2)
            . '. Queda en estado GENERADA: falta firmarlo y enviarlo al SRI.');
    }

    /*==================================================================
      Auxiliares
      ==================================================================*/

    /**
     * Datos tributarios del comprador, ya validados.
     *
     * Se valida el digito verificador y no solo la longitud: un digito
     * cambiado produce un comprobante que el SRI devuelve, y devolverlo
     * obliga a anular y reemitir con otro secuencial.
     */
    public function compradorDe(int $equipoid): array
    {
        $q = $this->fila(
            "SELECT equipo_nombre, equipo_idtipo, equipo_identificacion,
                    equipo_razonsocial, equipo_direccion, equipo_email, equipo_telefono
               FROM dsl_equipo WHERE equipo_id = :e", [':e' => $equipoid]);

        if (!$q) {
            return ['ok' => false, 'motivo' => 'Ese equipo no existe.'];
        }

        $falta = [];

        if (trim((string)$q['equipo_identificacion']) === '') {
            $falta[] = 'el RUC o cédula';
        } elseif (!sri_identificacion_valida($q['equipo_identificacion'], $q['equipo_idtipo'])) {
            return ['ok' => false, 'motivo' =>
                'La identificación «' . $q['equipo_identificacion'] . '» de «'
                . $q['equipo_nombre'] . '» no es válida: el dígito verificador no cuadra. '
                . 'Corríjala en Equipos antes de emitir; el SRI devolvería el comprobante '
                . 'y habría que anularlo y reemitirlo con otro número.'];
        }

        if (trim((string)$q['equipo_razonsocial']) === '') { $falta[] = 'la razón social'; }
        if (trim((string)$q['equipo_direccion']) === '')   { $falta[] = 'la dirección'; }

        if ($falta) {
            return ['ok' => false, 'motivo' =>
                'A «' . $q['equipo_nombre'] . '» le falta ' . implode(', ', $falta)
                . '. Complételo en Equipos: son datos obligatorios del comprobante.'];
        }

        return [
            'ok'             => true,
            'idtipo'         => sri_tipo_identificacion($q['equipo_idtipo'], $q['equipo_identificacion']),
            'identificacion' => sri_texto((string)$q['equipo_identificacion'], 20),
            'razonsocial'    => sri_texto((string)$q['equipo_razonsocial'], 300),
            'direccion'      => sri_texto((string)$q['equipo_direccion'], 300),
            'email'          => sri_texto((string)$q['equipo_email'], 150),
            'telefono'       => sri_texto((string)$q['equipo_telefono'], 30),
        ];
    }

    /**
     * Impuestos del comprobante, sumados por tarifa.
     *
     * El XML lleva un bloque por tarifa, no uno por linea: repetir la
     * misma tarifa en varios bloques hace que el SRI devuelva el
     * comprobante.
     */
    private function impuestosAgrupados(array $lineas): array
    {
        $por = [];

        foreach ($lineas as $l) {
            $clave = $l['impuestos'][0]['codigo'] . '|' . $l['ivacodigo'];

            if (!isset($por[$clave])) {
                $por[$clave] = [
                    'codigo'            => $l['impuestos'][0]['codigo'],
                    'codigo_porcentaje' => $l['ivacodigo'],
                    'tarifa'            => $l['ivatarifa'],
                    'base_imponible'    => 0.0,
                    'valor'             => 0.0,
                ];
            }

            $por[$clave]['base_imponible'] = round($por[$clave]['base_imponible'] + $l['subtotal'], 2);
            $por[$clave]['valor']          = round($por[$clave]['valor'] + $l['ivavalor'], 2);
        }

        return array_values($por);
    }
}
