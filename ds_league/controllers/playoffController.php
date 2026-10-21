<?php

namespace league\controllers;

/**
 * Eliminatorias.
 *
 * UNA SERIE NO ES UN PARTIDO CON MAS MARCADORES
 *
 * Es un contenedor de 1 a N encuentros entre los mismos dos equipos, y
 * quien gana la serie no es necesariamente quien mas puntos anoto: se
 * cuentan PARTIDOS ganados. Sin ese contenedor, «al mejor de 5» no se
 * puede expresar y la eliminatoria acaba llevandose a mano fuera del
 * sistema.
 *
 * LA REGLA QUE MAS SE FALLA
 *
 * Una serie termina en cuanto alguien alcanza el umbral, y los partidos
 * que quedaban NO se juegan. En un «al mejor de 5» que va 3-0, los dos
 * restantes se cancelan: dejarlos programados mantiene canchas bloqueadas
 * en Arena y arbitros designados para encuentros que no existiran.
 */
class playoffController extends competenciaController
{
    /*==================================================================
      Lectura
      ==================================================================*/

    /** Series de una fase, con sus equipos y el marcador de la serie. */
    public function seriesDeFase(int $faseid): array
    {
        return $this->filas(
            "SELECT S.*,
                    L.equipo_nombre AS local,      L.equipo_escudo AS escudo_local,
                    V.equipo_nombre AS visitante,  V.equipo_escudo AS escudo_visitante,
                    W.equipo_nombre AS ganador,
                    (SELECT COUNT(*) FROM dsl_partido P
                      WHERE P.partido_serieid = S.serie_id) AS partidos
               FROM dsl_serie S
               LEFT JOIN dsl_inscripcion IL ON IL.inscripcion_id = S.serie_localid
               LEFT JOIN dsl_equipo      L  ON L.equipo_id  = IL.inscripcion_equipoid
               LEFT JOIN dsl_inscripcion IV ON IV.inscripcion_id = S.serie_visitanteid
               LEFT JOIN dsl_equipo      V  ON V.equipo_id  = IV.inscripcion_equipoid
               LEFT JOIN dsl_inscripcion IW ON IW.inscripcion_id = S.serie_ganadorid
               LEFT JOIN dsl_equipo      W  ON W.equipo_id  = IW.inscripcion_equipoid
              WHERE S.serie_faseid = :f
              ORDER BY S.serie_orden",
            [':f' => $faseid]
        );
    }

    /** Partidos de una serie, en orden. */
    public function partidosDeSerie(int $serieid): array
    {
        return $this->filas(
            "SELECT P.*, E.estado_codigo, E.estado_nombre, E.estado_tono, E.estado_efectivo,
                    L.equipo_nombre AS local, V.equipo_nombre AS visitante
               FROM dsl_partido P
               JOIN dsl_estado E       ON E.estado_id = P.partido_estadoid
               JOIN dsl_inscripcion IL ON IL.inscripcion_id = P.partido_localid
               JOIN dsl_equipo L       ON L.equipo_id = IL.inscripcion_equipoid
               JOIN dsl_inscripcion IV ON IV.inscripcion_id = P.partido_visitanteid
               JOIN dsl_equipo V       ON V.equipo_id = IV.inscripcion_equipoid
              WHERE P.partido_serieid = :s
              ORDER BY P.partido_id",
            [':s' => $serieid]
        );
    }

    /** Partidos necesarios para ganar un «al mejor de N». */
    public function umbral(int $mejorDe): int
    {
        return intdiv(max(1, $mejorDe), 2) + 1;
    }

    /*==================================================================
      Siembra del cuadro
      ==================================================================*/

    /**
     * Ordena a los clasificados de una fase de grupos para formar el
     * cuadro.
     *
     * SIEMBRA CRUZADA
     *
     * El primero de un grupo se enfrenta al segundo de OTRO, no al de su
     * mismo grupo: acaban de jugar entre ellos y repetirlo de inmediato
     * vacia la fase de grupos de sentido. Con un solo grupo se cruzan
     * primero contra ultimo clasificado, que es el otro esquema habitual.
     *
     * @return array lista de parejas [insA, insB], mejor sembrado primero
     */
    public function sembrar(int $faseGrupos, int $clasificanPorGrupo): array
    {
        $grupos = $this->gruposDeFase($faseGrupos);

        /* Sin grupos: una sola tabla, se cruza 1-N, 2-(N-1)... */
        if (!$grupos) {
            $tabla = $this->tablaPosiciones($faseGrupos);
            $ids   = array_column(array_slice($tabla, 0, $clasificanPorGrupo), 'inscripcion_id');
            return $this->cruzarLista($ids);
        }

        /* Con grupos: se toma la posicion N de cada grupo. */
        $porPosicion = [];   // [posicion => [insId por grupo]]

        foreach ($grupos as $g) {
            $tabla = $this->tablaPosiciones($faseGrupos, (int)$g['grupo_id']);
            foreach (array_slice($tabla, 0, $clasificanPorGrupo) as $i => $fila) {
                $porPosicion[$i + 1][] = (int)$fila['inscripcion_id'];
            }
        }

        if (count($grupos) === 1) {
            return $this->cruzarLista($porPosicion[1] ?? []);
        }

        /* Primeros contra segundos, en cruce: 1ºA-2ºB, 1ºB-2ºA... */
        $parejas = [];
        $primeros = $porPosicion[1] ?? [];
        $segundos = $porPosicion[2] ?? [];

        if (!$segundos) {
            return $this->cruzarLista($primeros);
        }

        $n = count($primeros);
        foreach ($primeros as $i => $p) {
            /* El desplazamiento evita que un primero se cruce con el
               segundo de su propio grupo. */
            $rival = $segundos[($i + 1) % max(1, count($segundos))] ?? null;
            if ($rival !== null && $rival !== $p) {
                $parejas[] = [$p, $rival];
            }
        }

        return $parejas;
    }

    /** Cruza una lista ordenada: 1-N, 2-(N-1)... */
    private function cruzarLista(array $ids): array
    {
        $parejas = [];
        $n = count($ids);

        for ($i = 0; $i < intdiv($n, 2); $i++) {
            $parejas[] = [$ids[$i], $ids[$n - 1 - $i]];
        }

        return $parejas;
    }

    /*==================================================================
      Generacion
      ==================================================================*/

    /**
     * Crea las series de una fase eliminatoria y sus partidos.
     *
     * TODO O NADA. Un cuadro a medias deja equipos clasificados sin rival
     * y otros con dos series abiertas.
     */
    public function generarLlaves(): string
    {
        if (!puede_crear('playoffPanel')) { return $this->denegado('generar eliminatorias'); }

        $faseDestino = (int)($_POST['fase_id'] ?? 0);
        $faseOrigen  = (int)($_POST['fase_origen'] ?? 0);
        $clasifican  = max(1, min(8, (int)($_POST['clasifican'] ?? 2)));
        $mejorDe     = max(1, min(9, (int)($_POST['mejor_de'] ?? 1)));

        if ($mejorDe % 2 === 0) {
            return $this->respuesta('simple', 'Número par',
                'Una serie al mejor de un número par no decide nada. Use 1, 3, 5, 7 o 9.', 'error');
        }

        $destino = $this->faseConContexto($faseDestino);
        if (!$destino) {
            return $this->respuesta('simple', 'Fase no encontrada', 'No existe.', 'error');
        }

        $yaHay = (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_serie WHERE serie_faseid = :f", [':f' => $faseDestino]);

        if ($yaHay > 0) {
            return $this->respuesta('simple', 'La fase ya tiene cuadro',
                "Hay {$yaHay} series creadas. Elimínelas antes de volver a generar.", 'error');
        }

        $parejas = $this->sembrar($faseOrigen, $clasifican);

        if (!$parejas) {
            return $this->respuesta('simple', 'No hay clasificados',
                'La fase de origen no tiene resultados suficientes para sembrar el cuadro. '
                . 'Compruebe que los partidos estén finalizados.', 'error');
        }

        $programado = (int)$this->escalar(
            "SELECT estado_id FROM dsl_estado
              WHERE estado_entidad = 'partido' AND estado_codigo = 'PROGRAMADO'");

        $con = $this->conexion();
        if ($con === null) {
            return $this->respuesta('simple', 'Sin conexión', 'No hay base de datos.', 'error');
        }

        $propia = !$con->inTransaction();

        try {
            if ($propia) { $con->beginTransaction(); }

            $stSerie = $con->prepare(
                "INSERT INTO dsl_serie (serie_faseid, serie_orden, serie_nombre,
                        serie_localid, serie_visitanteid, serie_mejorde, serie_estado)
                 VALUES (:f, :o, :n, :l, :v, :m, 'ABIERTA')");

            $stPartido = $con->prepare(
                "INSERT INTO dsl_partido (partido_faseid, partido_serieid, partido_localid,
                        partido_visitanteid, partido_estadoid, partido_usuarioid)
                 VALUES (:f, :s, :l, :v, :e, :u)");

            $usuario = usuario_actual_id() ?: null;
            $total   = 0;

            foreach ($parejas as $i => [$a, $b]) {
                $stSerie->execute([':f' => $faseDestino, ':o' => $i + 1,
                                   ':n' => 'Llave ' . ($i + 1),
                                   ':l' => $a, ':v' => $b, ':m' => $mejorDe]);
                $serieId = (int)$con->lastInsertId();

                /* Los N partidos, alternando la localía. El mejor sembrado
                   abre en casa y cierra en casa, que es la ventaja que da
                   haber quedado primero. */
                for ($j = 0; $j < $mejorDe; $j++) {
                    $enCasa = ($j % 2 === 0);
                    $stPartido->execute([
                        ':f' => $faseDestino, ':s' => $serieId,
                        ':l' => $enCasa ? $a : $b,
                        ':v' => $enCasa ? $b : $a,
                        ':e' => $programado, ':u' => $usuario,
                    ]);
                    $total++;
                }
            }

            if ($propia) { $con->commit(); }

        } catch (\Throwable $e) {
            if ($propia) {
                if ($con->inTransaction()) { $con->rollBack(); }
                return $this->respuesta('simple', 'No se pudo generar el cuadro',
                    $e->getMessage(), 'error');
            }
            throw $e;
        }

        $this->auditar('fase', $faseDestino, 'crear', null,
            ['series' => count($parejas), 'partidos' => $total,
             'mejor_de' => $mejorDe, 'desde_fase' => $faseOrigen],
            'Generación de cuadro eliminatorio');

        return $this->respuesta('recargar', 'Cuadro generado',
            count($parejas) . ' llaves al mejor de ' . $mejorDe . '. Los partidos que no '
            . 'hagan falta se cancelarán solos cuando la serie se decida.');
    }

    /*==================================================================
      Resolucion
      ==================================================================*/

    /**
     * Recalcula el marcador de una serie y, si esta decidida, la cierra y
     * cancela los partidos sobrantes.
     *
     * Se llama despues de cada resultado. No se fia de un contador
     * incremental: recuenta desde los partidos, que es la fuente de
     * verdad. Un contador que se desincroniza da un campeon equivocado y
     * nadie sabe por que.
     */
    public function resolverSerie(int $serieid): array
    {
        $serie = $this->fila(
            "SELECT * FROM dsl_serie WHERE serie_id = :s", [':s' => $serieid]);

        if (!$serie) { return ['ok' => false, 'motivo' => 'La serie no existe.']; }

        $local     = (int)$serie['serie_localid'];
        $visitante = (int)$serie['serie_visitanteid'];
        $umbral    = $this->umbral((int)$serie['serie_mejorde']);

        /* Solo cuentan los partidos en estado efectivo: finalizado o
           walkover. Un cancelado no se juega y no suma a nadie. */
        $jugados = $this->filas(
            "SELECT P.partido_id, P.partido_localid, P.partido_visitanteid,
                    P.partido_puntoslocal, P.partido_puntosvisitante
               FROM dsl_partido P
               JOIN dsl_estado E ON E.estado_id = P.partido_estadoid
              WHERE P.partido_serieid = :s
                AND E.estado_efectivo = 'S'
                AND P.partido_puntoslocal IS NOT NULL
                AND P.partido_puntosvisitante IS NOT NULL",
            [':s' => $serieid]
        );

        $gana = [$local => 0, $visitante => 0];

        foreach ($jugados as $p) {
            $pl = (int)$p['partido_puntoslocal'];
            $pv = (int)$p['partido_puntosvisitante'];
            if ($pl === $pv) { continue; }

            $ganador = $pl > $pv ? (int)$p['partido_localid'] : (int)$p['partido_visitanteid'];
            if (isset($gana[$ganador])) { $gana[$ganador]++; }
        }

        $ganadorSerie = null;
        if ($gana[$local] >= $umbral)          { $ganadorSerie = $local; }
        elseif ($gana[$visitante] >= $umbral)  { $ganadorSerie = $visitante; }

        $con = $this->conexion();
        if ($con === null) { return ['ok' => false, 'motivo' => 'Sin conexión.']; }

        $cancelados = 0;

        try {
            $st = $con->prepare(
                "UPDATE dsl_serie SET serie_ganadas_local = :gl, serie_ganadas_visitante = :gv,
                        serie_ganadorid = :w, serie_estado = :e
                  WHERE serie_id = :s");
            $st->execute([
                ':gl' => $gana[$local], ':gv' => $gana[$visitante],
                ':w'  => $ganadorSerie,
                ':e'  => $ganadorSerie !== null ? 'CERRADA' : 'ABIERTA',
                ':s'  => $serieid,
            ]);

            /* LA REGLA: decidida la serie, lo que quedaba no se juega. */
            if ($ganadorSerie !== null) {
                $cancelado = (int)$this->escalar(
                    "SELECT estado_id FROM dsl_estado
                      WHERE estado_entidad = 'partido' AND estado_codigo = 'CANCELADO'");

                $st = $con->prepare(
                    "UPDATE dsl_partido P
                       JOIN dsl_estado E ON E.estado_id = P.partido_estadoid
                        SET P.partido_estadoid = :c,
                            P.partido_motivo = 'Serie decidida: no hace falta jugarlo'
                      WHERE P.partido_serieid = :s
                        AND E.estado_efectivo = 'N'
                        AND E.estado_final    = 'N'");
                $st->execute([':c' => $cancelado, ':s' => $serieid]);
                $cancelados = $st->rowCount();

                /* Los bloqueos de cancha de esos partidos se liberan: si
                   no, Arena seguiria sin ofrecer horas que ya no se usan. */
                $puente = $this->puente();
                foreach ($this->filas(
                    "SELECT partido_id, partido_bloqueoid FROM dsl_partido
                      WHERE partido_serieid = :s AND partido_bloqueoid IS NOT NULL",
                    [':s' => $serieid]) as $p) {

                    $est = $this->fila(
                        "SELECT E.estado_codigo FROM dsl_partido P
                           JOIN dsl_estado E ON E.estado_id = P.partido_estadoid
                          WHERE P.partido_id = :p", [':p' => $p['partido_id']]);

                    if (($est['estado_codigo'] ?? '') === 'CANCELADO') {
                        $puente->liberarBloqueo($con, (int)$p['partido_bloqueoid']);
                        $con->prepare("UPDATE dsl_partido SET partido_bloqueoid = NULL
                                        WHERE partido_id = :p")
                            ->execute([':p' => $p['partido_id']]);
                    }
                }

                $this->auditar('serie', $serieid, 'estado',
                    ['estado' => 'ABIERTA'],
                    ['estado' => 'CERRADA', 'ganador' => $ganadorSerie,
                     'marcador' => $gana[$local] . '-' . $gana[$visitante],
                     'partidos_cancelados' => $cancelados],
                    'Serie decidida');
            }

        } catch (\Throwable $e) {
            return ['ok' => false, 'motivo' => $e->getMessage()];
        }

        return ['ok' => true, 'motivo' => '',
                'ganador' => $ganadorSerie, 'umbral' => $umbral,
                'local' => $gana[$local], 'visitante' => $gana[$visitante],
                'cancelados' => $cancelados];
    }
}
