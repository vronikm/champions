<?php

namespace league\controllers;

/**
 * Obligaciones economicas y cobros de League.
 *
 * EL SALDO SE DERIVA, EL ESTADO SE RECALCULA
 *
 * El saldo sale de la vista v_dsl_saldo: valor + recargo - descuento
 * menos los abonos vigentes. Nunca se guarda, porque un saldo almacenado
 * se desincroniza en cuanto se anula un pago y entonces el sistema afirma
 * una deuda inexistente con la misma seguridad con que afirmaria una
 * correcta.
 *
 * El estado (PENDIENTE / PARCIAL / PAGADA) si se guarda —los listados de
 * cobranza filtran por el y recalcularlo por fila en mil obligaciones no
 * sale gratis— pero lo escribe SIEMPRE este servicio a partir de los
 * abonos, y nunca se edita a mano.
 */
class finanzaController extends competenciaController
{
    /*==================================================================
      Catalogo
      ==================================================================*/

    public function conceptos(string $ambito = ''): array
    {
        $sql = "SELECT * FROM dsl_concepto WHERE concepto_activo = 'S'";
        $par = [];

        if ($ambito !== '') {
            $sql .= " AND concepto_ambito = :a";
            $par[':a'] = $ambito;
        }

        return $this->filas($sql . " ORDER BY concepto_orden, concepto_nombre", $par);
    }

    public function guardarConcepto(): string
    {
        if (!puede_crear('conceptoList') && !puede_editar('conceptoList')) {
            return $this->denegado('administrar conceptos');
        }

        $id     = (int)($_POST['concepto_id'] ?? 0);
        $codigo = strtoupper(trim((string)($_POST['concepto_codigo'] ?? '')));
        $nombre = trim((string)($_POST['concepto_nombre'] ?? ''));
        $ambito = strtoupper(trim((string)($_POST['concepto_ambito'] ?? 'INSCRIPCION')));
        $valor  = round((float)str_replace(',', '.', (string)($_POST['concepto_valor'] ?? '0')), 2);

        if ($codigo === '' || $nombre === '') {
            return $this->respuesta('simple', 'Faltan datos',
                'El código y el nombre son obligatorios.', 'error');
        }
        if (!preg_match('/^[A-Z0-9_]{2,20}$/', $codigo)) {
            return $this->respuesta('simple', 'Código no válido',
                'Use sólo letras mayúsculas, números y guión bajo.', 'error');
        }
        if (!\in_array($ambito, ['INSCRIPCION', 'EQUIPO', 'PERSONA', 'PARTIDO'], true)) {
            return $this->respuesta('simple', 'Ámbito no válido',
                'Elija a qué se asocia el concepto.', 'error');
        }
        if ($valor < 0) {
            return $this->respuesta('simple', 'Valor no válido',
                'El valor no puede ser negativo.', 'error');
        }

        $sql = $id > 0
            ? "UPDATE dsl_concepto SET concepto_codigo = :c, concepto_nombre = :n,
                      concepto_ambito = :a, concepto_valor = :v WHERE concepto_id = :id"
            : "INSERT INTO dsl_concepto (concepto_codigo, concepto_nombre,
                      concepto_ambito, concepto_valor) VALUES (:c, :n, :a, :v)";

        $par = [':c' => $codigo, ':n' => $nombre, ':a' => $ambito, ':v' => $valor];
        if ($id > 0) { $par[':id'] = $id; }

        $nuevo = $this->escribir($sql, $par);

        if ($nuevo < 0) {
            return $this->respuesta('simple', 'Código repetido',
                'Ya existe un concepto con ese código.', 'error');
        }

        $this->auditar('concepto', $nuevo, $id > 0 ? 'editar' : 'crear', null,
            ['codigo' => $codigo, 'nombre' => $nombre, 'valor' => $valor]);

        return $this->respuesta('recargar', 'Concepto guardado', $nombre);
    }

    /*==================================================================
      Listas para elegir a quien se le cobra

      Todas se acotan por categoria. Sin ese limite, el selector de
      personas de una liga con veinte equipos son cuatrocientos nombres en
      un desplegable, que es la forma mas comoda de cobrarle al que no era.
      ==================================================================*/

    /** Personas de la categoria, con su equipo. */
    public function personasDeCategoria(int $categoriaid): array
    {
        return $this->filas(
            "SELECT PE.persona_id, PE.persona_nombres, PE.persona_apellidos,
                    PE.persona_identificacion, Q.equipo_nombre, PL.plantilla_dorsal
               FROM dsl_plantilla   PL
               JOIN dsl_inscripcion I  ON I.inscripcion_id = PL.plantilla_inscripcionid
               JOIN dsl_persona     PE ON PE.persona_id    = PL.plantilla_personaid
               JOIN dsl_equipo      Q  ON Q.equipo_id      = I.inscripcion_equipoid
              WHERE I.inscripcion_categoriaid = :c
                AND PL.plantilla_baja IS NULL
              ORDER BY Q.equipo_nombre, PE.persona_apellidos, PE.persona_nombres",
            [':c' => $categoriaid]
        );
    }

    /** ¿Este equipo esta inscrito en esta categoria? */
    public function equipoJuegaEn(int $equipoid, int $categoriaid): bool
    {
        return (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_inscripcion
              WHERE inscripcion_equipoid = :e AND inscripcion_categoriaid = :c",
            [':e' => $equipoid, ':c' => $categoriaid]
        ) > 0;
    }

    /** Partidos de la categoria, con los dos equipos. */
    public function partidosDeCategoria(int $categoriaid): array
    {
        return $this->filas(
            "SELECT P.partido_id, P.partido_fecha, P.partido_hora,
                    P.partido_localid, P.partido_visitanteid,
                    QL.equipo_id AS local_equipoid,     QL.equipo_nombre AS local_nombre,
                    QV.equipo_id AS visitante_equipoid, QV.equipo_nombre AS visitante_nombre
               FROM dsl_partido     P
               JOIN dsl_fase        F  ON F.fase_id = P.partido_faseid
               JOIN dsl_inscripcion IL ON IL.inscripcion_id = P.partido_localid
               JOIN dsl_inscripcion IV ON IV.inscripcion_id = P.partido_visitanteid
               JOIN dsl_equipo      QL ON QL.equipo_id = IL.inscripcion_equipoid
               JOIN dsl_equipo      QV ON QV.equipo_id = IV.inscripcion_equipoid
              WHERE F.fase_categoriaid = :c
              ORDER BY P.partido_fecha IS NULL, P.partido_fecha, P.partido_hora, P.partido_id",
            [':c' => $categoriaid]
        );
    }

    /*==================================================================
      Obligaciones
      ==================================================================*/

    /**
     * Obligaciones con su saldo.
     *
     * @param array $filtros categoria, equipo, estado, vencidas
     */
    public function obligaciones(array $filtros = []): array
    {
        $sql = "SELECT S.*, Q.equipo_nombre, F.factura_secuencial, F.factura_estadosri
                  FROM v_dsl_saldo S
                  LEFT JOIN dsl_equipo  Q ON Q.equipo_id  = S.obligacion_equipoid
                  LEFT JOIN dsl_factura F ON F.factura_id = S.obligacion_facturaid
                 WHERE 1 = 1";
        $par = [];

        if (!empty($filtros['equipo'])) {
            $sql .= " AND S.obligacion_equipoid = :e";
            $par[':e'] = (int)$filtros['equipo'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND S.obligacion_estado = :s";
            $par[':s'] = $filtros['estado'];
        }
        if (!empty($filtros['vencidas'])) {
            $sql .= " AND S.dias_vencido > 0";
        }
        if (!empty($filtros['categoria'])) {
            /* Por la columna, no deduciendo el origen: una multa a un
               equipo o un carne de un jugador tienen origen EQUIPO o
               PERSONA, y con el filtro deducido no aparecian en ninguna
               pantalla. Ver migracion 038. */
            $sql .= " AND S.obligacion_categoriaid = :c";
            $par[':c'] = (int)$filtros['categoria'];
        }

        return $this->filas($sql . " ORDER BY S.obligacion_vence IS NULL,
                                             S.obligacion_vence, S.obligacion_id DESC", $par);
    }

    /** Una obligacion con su saldo y sus abonos. */
    public function obligacion(int $id): array
    {
        $o = $this->fila(
            "SELECT S.*, Q.equipo_nombre
               FROM v_dsl_saldo S
               LEFT JOIN dsl_equipo Q ON Q.equipo_id = S.obligacion_equipoid
              WHERE S.obligacion_id = :id",
            [':id' => $id]
        );

        if (!$o) { return []; }

        $o['abonos'] = $this->filas(
            "SELECT * FROM dsl_abono
              WHERE abono_obligacionid = :id
              ORDER BY abono_fecha DESC, abono_id DESC",
            [':id' => $id]
        );

        return $o;
    }

    /**
     * Abonos de varias obligaciones, agrupados por obligacion.
     *
     * En una consulta y no una por fila: el listado ya trae hasta unos
     * cientos de obligaciones, y pedir sus abonos de uno en uno convierte
     * una pantalla en trescientas consultas.
     *
     * @param int[] $ids
     * @return array<int, array<int, array>>
     */
    public function abonosDe(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return []; }

        /* Los marcadores se generan a partir del NÚMERO de ids, no de su
           contenido: lo que se interpola es «?, ?, ?», nunca un valor. */
        $marcas = implode(',', array_fill(0, count($ids), '?'));

        $filas = $this->filas(
            "SELECT * FROM dsl_abono
              WHERE abono_obligacionid IN ({$marcas})
              ORDER BY abono_fecha DESC, abono_id DESC",
            $ids
        );

        $porObligacion = [];
        foreach ($filas as $f) {
            $porObligacion[(int)$f['abono_obligacionid']][] = $f;
        }

        return $porObligacion;
    }

    /** Resumen de cobranza, para el panel. */
    public function resumenCobranza(int $categoriaid = 0): array
    {
        $filtro = $categoriaid > 0 ? " AND S.obligacion_categoriaid = :c" : '';
        $par = $categoriaid > 0 ? [':c' => $categoriaid] : [];

        $f = $this->fila(
            "SELECT COUNT(*) AS obligaciones,
                    COALESCE(SUM(S.total), 0)   AS facturado,
                    COALESCE(SUM(S.abonado), 0) AS cobrado,
                    COALESCE(SUM(S.saldo), 0)   AS pendiente,
                    COALESCE(SUM(CASE WHEN S.dias_vencido > 0 THEN S.saldo ELSE 0 END), 0) AS vencido
               FROM v_dsl_saldo S
              WHERE S.obligacion_estado <> 'ANULADA' {$filtro}",
            $par
        );

        return $f ?: ['obligaciones'=>0,'facturado'=>0,'cobrado'=>0,'pendiente'=>0,'vencido'=>0];
    }

    /**
     * Crea una obligacion.
     *
     * El deudor se copia al crearla: un recibo debe seguir diciendo a
     * quien se emitio aunque el equipo se renombre despues.
     */
    public function guardarObligacion(): string
    {
        if (!puede_crear('cobranzaPanel')) { return $this->denegado('crear obligaciones'); }

        $concepto  = (int)($_POST['concepto_id'] ?? 0);
        $origenTipo = strtoupper(trim((string)($_POST['origen_tipo'] ?? '')));
        $origenId  = (int)($_POST['origen_id'] ?? 0);
        $valor     = round((float)str_replace(',', '.', (string)($_POST['valor'] ?? '0')), 2);
        $descuento = round((float)str_replace(',', '.', (string)($_POST['descuento'] ?? '0')), 2);
        $recargo   = round((float)str_replace(',', '.', (string)($_POST['recargo'] ?? '0')), 2);
        $detalle   = trim((string)($_POST['detalle'] ?? ''));
        $vence     = trim((string)($_POST['vence'] ?? ''));

        if ($concepto <= 0 || $origenId <= 0) {
            return $this->respuesta('simple', 'Faltan datos',
                'Indique el concepto y a qué se aplica.', 'error');
        }
        if ($valor <= 0) {
            return $this->respuesta('simple', 'Valor no válido',
                'Una obligación de cero no tiene sentido.', 'error');
        }
        if ($descuento > $valor) {
            return $this->respuesta('simple', 'Descuento excesivo',
                'El descuento no puede superar el valor: dejaría una deuda negativa.', 'error');
        }
        if ($descuento < 0 || $recargo < 0) {
            return $this->respuesta('simple', 'Importe no válido',
                'Descuento y recargo no pueden ser negativos.', 'error');
        }
        if ($vence !== '' && $vence < date('Y-m-d')) {
            return $this->respuesta('simple', 'Vencimiento en el pasado',
                'La fecha de vencimiento ya pasó. Revísela.', 'error');
        }

        /* De dónde salen el equipo, la categoría y el nombre del deudor
           depende del origen.

           LA CATEGORÍA SE GUARDA, NO SE DEDUCE

           El panel de cobranza trabaja por categoría. Deducirla del origen
           sólo funciona para las inscripciones: una multa a un equipo o el
           carné de un jugador tienen origen EQUIPO o PERSONA y quedaban
           fuera de todos los listados, es decir, deuda que nadie ve ni
           cobra. Y no basta con añadir más subconsultas: un equipo puede
           jugar varias categorías a la vez, así que «la categoría de este
           equipo» no es una sola y la multa saldría repetida en cada una.
           Es una decisión de quien genera el cobro, y por eso se guarda.
           Ver migración 038. */
        $equipoId = null; $personaId = null; $deudor = '';
        $categoriaId = (int)($_POST['categoria_id'] ?? 0) ?: null;

        if ($origenTipo === 'INSCRIPCION') {
            $d = $this->fila(
                "SELECT I.inscripcion_equipoid, I.inscripcion_categoriaid,
                        Q.equipo_nombre, C.categoria_nombre
                   FROM dsl_inscripcion I
                   JOIN dsl_equipo Q    ON Q.equipo_id = I.inscripcion_equipoid
                   JOIN dsl_categoria C ON C.categoria_id = I.inscripcion_categoriaid
                  WHERE I.inscripcion_id = :i", [':i' => $origenId]);

            if (!$d) {
                return $this->respuesta('simple', 'No encontrada',
                    'Esa inscripción no existe.', 'error');
            }
            $equipoId    = (int)$d['inscripcion_equipoid'];
            /* La inscripción ya dice de qué categoría es; lo que venga en
               el formulario no manda sobre eso. */
            $categoriaId = (int)$d['inscripcion_categoriaid'];
            $deudor      = $d['equipo_nombre'] . ' · ' . $d['categoria_nombre'];

        } elseif ($origenTipo === 'EQUIPO') {
            $d = $this->fila("SELECT equipo_nombre FROM dsl_equipo WHERE equipo_id = :e",
                             [':e' => $origenId]);
            if (!$d) {
                return $this->respuesta('simple', 'No encontrado', 'Ese equipo no existe.', 'error');
            }
            $equipoId = $origenId;
            $deudor   = $d['equipo_nombre'];

            /* Aquí la categoría viene del formulario, y se comprueba que
               el equipo juegue de verdad en ella: cargarle a la Sub-14 una
               multa de la Sub-16 descuadra dos competencias a la vez. */
            if ($categoriaId !== null && !$this->equipoJuegaEn($origenId, $categoriaId)) {
                return $this->respuesta('simple', 'Equipo ajeno a la categoría',
                    'Ese equipo no está inscrito en esta categoría.', 'error');
            }

        } elseif ($origenTipo === 'PERSONA') {
            $d = $this->fila(
                "SELECT PE.persona_nombres, PE.persona_apellidos,
                        (SELECT I.inscripcion_categoriaid
                           FROM dsl_plantilla PL
                           JOIN dsl_inscripcion I ON I.inscripcion_id = PL.plantilla_inscripcionid
                          WHERE PL.plantilla_personaid = PE.persona_id
                            /* Dos marcadores para el mismo valor: PDO sólo
                               admite repetir un nombre con emulación de
                               preparadas activada, y eso es una opción de
                               conexión que nadie debería tener que
                               mantener para que esta consulta funcione. */
                            AND (:c1 IS NULL OR I.inscripcion_categoriaid = :c2)
                          ORDER BY PL.plantilla_id DESC LIMIT 1) AS categoriaid
                   FROM dsl_persona PE WHERE PE.persona_id = :p",
                [':p' => $origenId, ':c1' => $categoriaId, ':c2' => $categoriaId]);

            if (!$d) {
                return $this->respuesta('simple', 'No encontrada', 'Esa persona no existe.', 'error');
            }
            if ($d['categoriaid'] === null) {
                return $this->respuesta('simple', 'Persona ajena a la categoría',
                    'Esa persona no figura en ninguna plantilla de esta categoría.', 'error');
            }
            $personaId   = $origenId;
            $categoriaId = (int)$d['categoriaid'];
            $deudor      = $d['persona_apellidos'] . ' ' . $d['persona_nombres'];

        } elseif ($origenTipo === 'PARTIDO') {
            /* Un partido no debe dinero: lo deben los equipos que lo
               juegan. Por eso aquí hace falta decir CUÁL de los dos, y se
               comprueba que sea uno de ellos: cobrar el arbitraje de un
               partido a un tercero es un error que nadie detecta después. */
            $equipoId = (int)($_POST['equipo_id'] ?? 0);

            $d = $this->fila(
                "SELECT QL.equipo_id AS local, QL.equipo_nombre AS local_nombre,
                        QV.equipo_id AS visitante, QV.equipo_nombre AS visitante_nombre,
                        P.partido_fecha, F.fase_categoriaid
                   FROM dsl_partido     P
                   JOIN dsl_fase        F  ON F.fase_id = P.partido_faseid
                   JOIN dsl_inscripcion IL ON IL.inscripcion_id = P.partido_localid
                   JOIN dsl_inscripcion IV ON IV.inscripcion_id = P.partido_visitanteid
                   JOIN dsl_equipo      QL ON QL.equipo_id = IL.inscripcion_equipoid
                   JOIN dsl_equipo      QV ON QV.equipo_id = IV.inscripcion_equipoid
                  WHERE P.partido_id = :p", [':p' => $origenId]);

            if (!$d) {
                return $this->respuesta('simple', 'No encontrado', 'Ese partido no existe.', 'error');
            }
            if ($equipoId !== (int)$d['local'] && $equipoId !== (int)$d['visitante']) {
                return $this->respuesta('simple', 'Equipo ajeno al partido',
                    'Indique a cuál de los dos equipos que juegan este partido se le cobra.', 'error');
            }

            /* El partido ya dice de qué categoría es, por su fase. */
            $categoriaId = (int)$d['fase_categoriaid'];

            $deudor = ($equipoId === (int)$d['local'] ? $d['local_nombre'] : $d['visitante_nombre'])
                    . ' · ' . $d['local_nombre'] . ' vs ' . $d['visitante_nombre']
                    . ($d['partido_fecha'] ? ' (' . $d['partido_fecha'] . ')' : '');

        } else {
            return $this->respuesta('simple', 'Origen no válido',
                'Indique si la obligación es de una inscripción, un equipo, una persona '
                . 'o un partido.', 'error');
        }

        /* La columna admite 150 caracteres. Recortar aquí y no confiar en
           que MySQL lo haga: en modo estricto no recorta, rechaza. */
        $deudor = mb_substr($deudor, 0, 150);

        $id = $this->escribir(
            "INSERT INTO dsl_obligacion
                    (obligacion_conceptoid, obligacion_origentipo, obligacion_origenid,
                     obligacion_categoriaid, obligacion_equipoid, obligacion_personaid,
                     obligacion_deudor,
                     obligacion_detalle, obligacion_fecha, obligacion_vence,
                     obligacion_valor, obligacion_descuento, obligacion_recargo,
                     obligacion_usuarioid)
             VALUES (:c, :ot, :oi, :cat, :eq, :pe, :de, :det, CURDATE(), :ven, :v, :d, :r, :u)",
            [':c' => $concepto, ':ot' => $origenTipo, ':oi' => $origenId,
             ':cat' => $categoriaId,
             ':eq' => $equipoId, ':pe' => $personaId, ':de' => $deudor,
             ':det' => $detalle, ':ven' => $vence !== '' ? $vence : null,
             ':v' => $valor, ':d' => $descuento, ':r' => $recargo,
             ':u' => usuario_actual_id() ?: null]
        );

        if ($id < 0) {
            return $this->respuesta('simple', 'No se pudo crear',
                'La base de datos rechazó la obligación.', 'error');
        }

        $this->auditar('obligacion', $id, 'crear', null,
            ['concepto' => $concepto, 'deudor' => $deudor,
             'valor' => $valor, 'descuento' => $descuento, 'recargo' => $recargo]);

        return $this->respuesta('recargar', 'Obligación registrada',
            $deudor . ' · ' . number_format($valor + $recargo - $descuento, 2));
    }

    /*==================================================================
      Abonos
      ==================================================================*/

    /**
     * Registra un abono.
     *
     * UN ABONO NUNCA PUEDE SUPERAR EL SALDO
     *
     * Es la regla que impide que una obligacion quede «pagada de mas» y
     * que los totales de caja dejen de cuadrar. Se comprueba contra el
     * saldo VIVO, leido en el momento, no contra un valor que la pantalla
     * traiga: entre que se pinto el formulario y se envio, otro usuario
     * puede haber cobrado.
     */
    public function guardarAbono(): string
    {
        if (!puede_crear('cobranzaPanel')) { return $this->denegado('registrar cobros'); }

        $obligacion = (int)($_POST['obligacion_id'] ?? 0);
        $valor      = round((float)str_replace(',', '.', (string)($_POST['valor'] ?? '0')), 2);
        $fecha      = trim((string)($_POST['fecha'] ?? date('Y-m-d')));
        $forma      = substr(trim((string)($_POST['forma'] ?? '01')), 0, 2);
        $referencia = trim((string)($_POST['referencia'] ?? ''));

        if ($valor <= 0) {
            return $this->respuesta('simple', 'Importe no válido',
                'El abono debe ser mayor que cero.', 'error');
        }
        if ($fecha > date('Y-m-d')) {
            return $this->respuesta('simple', 'Fecha futura',
                'No se puede registrar un cobro con fecha futura.', 'error');
        }

        $o = $this->fila(
            "SELECT obligacion_id, obligacion_estado, obligacion_deudor, saldo
               FROM v_dsl_saldo WHERE obligacion_id = :id",
            [':id' => $obligacion]
        );

        if (!$o) {
            return $this->respuesta('simple', 'No encontrada',
                'Esa obligación no existe.', 'error');
        }
        if ($o['obligacion_estado'] === 'ANULADA') {
            return $this->respuesta('simple', 'Obligación anulada',
                'No se puede cobrar sobre una obligación anulada.', 'error');
        }

        $saldo = round((float)$o['saldo'], 2);

        if ($saldo <= 0) {
            return $this->respuesta('simple', 'Ya está pagada',
                'Esta obligación no tiene saldo pendiente.', 'error');
        }
        if ($valor > $saldo) {
            return $this->respuesta('simple', 'Abono mayor que el saldo',
                'El saldo pendiente es ' . number_format($saldo, 2)
                . '. Un abono mayor dejaría la caja descuadrada.', 'error');
        }

        $con = $this->conexion();
        if ($con === null) {
            return $this->respuesta('simple', 'Sin conexión', 'No hay base de datos.', 'error');
        }

        $propia = !$con->inTransaction();

        try {
            if ($propia) { $con->beginTransaction(); }

            $st = $con->prepare(
                "INSERT INTO dsl_abono (abono_obligacionid, abono_fecha, abono_valor,
                        abono_forma, abono_referencia, abono_observacion, abono_usuarioid)
                 VALUES (:o, :f, :v, :fo, :r, :ob, :u)");
            $st->execute([
                ':o' => $obligacion, ':f' => $fecha, ':v' => $valor,
                ':fo' => $forma, ':r' => $referencia,
                ':ob' => trim((string)($_POST['observacion'] ?? '')),
                ':u' => usuario_actual_id() ?: null,
            ]);

            $abonoId = (int)$con->lastInsertId();
            $this->refrescarEstado($obligacion, $con);

            if ($propia) { $con->commit(); }

        } catch (\Throwable $e) {
            if ($propia) {
                if ($con->inTransaction()) { $con->rollBack(); }
                return $this->respuesta('simple', 'No se pudo registrar',
                    $e->getMessage(), 'error');
            }
            throw $e;
        }

        $this->auditar('obligacion', $obligacion, 'editar',
            ['saldo' => $saldo],
            ['abono' => $abonoId, 'valor' => $valor, 'saldo' => round($saldo - $valor, 2)],
            'Cobro registrado');

        $nuevo = round($saldo - $valor, 2);

        return $this->respuesta('recargar', 'Cobro registrado',
            $nuevo <= 0
                ? 'La obligación queda saldada.'
                : 'Queda un saldo de ' . number_format($nuevo, 2) . '.');
    }

    /**
     * Anula un abono.
     *
     * Se MARCA en vez de borrarse: el dinero entro y salio, y borrar la
     * fila haria desaparecer un movimiento que la caja de ese dia si
     * registro. Anular exige motivo por la misma razon.
     */
    public function anularAbono(): string
    {
        if (!puede_eliminar('cobranzaPanel')) { return $this->denegado('anular cobros'); }

        $id     = (int)($_POST['abono_id'] ?? 0);
        $motivo = trim((string)($_POST['motivo'] ?? ''));

        if ($motivo === '') {
            return $this->respuesta('simple', 'Falta el motivo',
                'Anular un cobro registrado necesita una justificación escrita.', 'error');
        }

        $a = $this->fila("SELECT * FROM dsl_abono WHERE abono_id = :id", [':id' => $id]);

        if (!$a) {
            return $this->respuesta('simple', 'No encontrado', 'Ese cobro no existe.', 'error');
        }
        if ($a['abono_anulado'] === 'S') {
            return $this->respuesta('simple', 'Ya estaba anulado',
                'Ese cobro ya figura como anulado.', 'error');
        }

        $con = $this->conexion();
        if ($con === null) {
            return $this->respuesta('simple', 'Sin conexión', 'No hay base de datos.', 'error');
        }

        $propia = !$con->inTransaction();

        try {
            if ($propia) { $con->beginTransaction(); }

            $con->prepare("UPDATE dsl_abono SET abono_anulado = 'S', abono_motivoanula = :m
                            WHERE abono_id = :id")
                ->execute([':m' => $motivo, ':id' => $id]);

            $this->refrescarEstado((int)$a['abono_obligacionid'], $con);

            if ($propia) { $con->commit(); }

        } catch (\Throwable $e) {
            if ($propia) {
                if ($con->inTransaction()) { $con->rollBack(); }
                return $this->respuesta('simple', 'No se pudo anular', $e->getMessage(), 'error');
            }
            throw $e;
        }

        $this->auditar('obligacion', (int)$a['abono_obligacionid'], 'editar',
            ['abono' => $id, 'valor' => $a['abono_valor'], 'anulado' => 'N'],
            ['abono' => $id, 'anulado' => 'S'],
            $motivo);

        return $this->respuesta('recargar', 'Cobro anulado',
            'El saldo de la obligación vuelve a incluir ' . number_format((float)$a['abono_valor'], 2) . '.');
    }

    /**
     * Recalcula el estado de una obligacion desde sus abonos.
     *
     * Se llama despues de cada movimiento. Nunca se escribe el estado a
     * mano desde otro sitio: un estado que no derive de los abonos acaba
     * diciendo «pagada» sobre una deuda viva.
     */
    protected function refrescarEstado(int $obligacionid, \PDO $con): void
    {
        $st = $con->prepare(
            "SELECT (O.obligacion_valor + O.obligacion_recargo - O.obligacion_descuento) AS total,
                    COALESCE((SELECT SUM(A.abono_valor) FROM dsl_abono A
                               WHERE A.abono_obligacionid = O.obligacion_id
                                 AND A.abono_anulado = 'N'), 0) AS abonado,
                    O.obligacion_estado
               FROM dsl_obligacion O WHERE O.obligacion_id = :id");
        $st->execute([':id' => $obligacionid]);
        $f = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$f || $f['obligacion_estado'] === 'ANULADA') { return; }

        $total   = round((float)$f['total'], 2);
        $abonado = round((float)$f['abonado'], 2);

        $estado = $abonado <= 0        ? 'PENDIENTE'
                : ($abonado >= $total  ? 'PAGADA' : 'PARCIAL');

        $con->prepare("UPDATE dsl_obligacion SET obligacion_estado = :e WHERE obligacion_id = :id")
            ->execute([':e' => $estado, ':id' => $obligacionid]);
    }

    /**
     * Anula una obligacion entera.
     *
     * Solo si no tiene cobros vigentes: anular algo ya cobrado dejaria el
     * dinero sin respaldo. Primero se anulan los abonos, uno a uno y con
     * su motivo, y despues la obligacion.
     */
    public function anularObligacion(): string
    {
        if (!puede_eliminar('cobranzaPanel')) { return $this->denegado('anular obligaciones'); }

        $id     = (int)($_POST['obligacion_id'] ?? 0);
        $motivo = trim((string)($_POST['motivo'] ?? ''));

        if ($motivo === '') {
            return $this->respuesta('simple', 'Falta el motivo',
                'Anular una obligación necesita una justificación escrita.', 'error');
        }

        $o = $this->fila("SELECT * FROM v_dsl_saldo WHERE obligacion_id = :id", [':id' => $id]);

        if (!$o) {
            return $this->respuesta('simple', 'No encontrada', 'No existe.', 'error');
        }
        if ((float)$o['abonado'] > 0) {
            return $this->respuesta('simple', 'Tiene cobros registrados',
                'Se cobraron ' . number_format((float)$o['abonado'], 2) . '. Anule primero '
                . 'esos cobros, uno a uno y con su motivo, para que quede constancia de '
                . 'qué pasó con el dinero.', 'error');
        }
        if ($o['obligacion_facturaid'] !== null) {
            return $this->respuesta('simple', 'Ya está facturada',
                'Esta obligación tiene un comprobante emitido. Anular el comprobante es '
                . 'un trámite tributario y se hace desde facturación.', 'error');
        }

        $ok = $this->escribir(
            "UPDATE dsl_obligacion SET obligacion_estado = 'ANULADA',
                    obligacion_detalle = CONCAT(obligacion_detalle, ' · ANULADA: ', :m)
              WHERE obligacion_id = :id",
            [':m' => $motivo, ':id' => $id]
        );

        if ($ok < 0) {
            return $this->respuesta('simple', 'No se pudo anular',
                'La base de datos rechazó el cambio.', 'error');
        }

        $this->auditar('obligacion', $id, 'eliminar',
            ['estado' => $o['obligacion_estado']], ['estado' => 'ANULADA'], $motivo);

        return $this->respuesta('recargar', 'Obligación anulada', $o['obligacion_deudor']);
    }
}
