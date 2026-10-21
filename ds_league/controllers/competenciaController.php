<?php

namespace league\controllers;

use PDO;

/**
 * Motor de competencia: fixture y clasificacion.
 *
 * Va aparte de leagueController porque son dos responsabilidades
 * distintas: aquel resuelve estados, auditoria y permisos —lo que todo el
 * modulo necesita—, y este las reglas deportivas.
 */
class competenciaController extends leagueController
{
    /*==================================================================
      Lectura de la jerarquia
      ==================================================================*/

    public function temporadas(int $escuelaid = 0): array
    {
        $sql = "SELECT T.*,
                       (SELECT COUNT(*) FROM dsl_torneo O
                         WHERE O.torneo_temporadaid = T.temporada_id
                           AND O.torneo_estado = 'A') AS torneos
                  FROM dsl_temporada T
                 WHERE T.temporada_estado <> 'E'";
        $par = [];

        if ($escuelaid > 0) {
            $sql .= " AND T.temporada_escuelaid = :esc";
            $par[':esc'] = $escuelaid;
        }

        return $this->filas($sql . " ORDER BY T.temporada_desde DESC", $par);
    }

    public function torneos(int $temporadaid = 0): array
    {
        $sql = "SELECT O.*, T.temporada_nombre,
                       (SELECT COUNT(*) FROM dsl_categoria C
                         WHERE C.categoria_torneoid = O.torneo_id
                           AND C.categoria_estado = 'A') AS categorias
                  FROM dsl_torneo O
                  JOIN dsl_temporada T ON T.temporada_id = O.torneo_temporadaid
                 WHERE O.torneo_estado <> 'E'";
        $par = [];

        if ($temporadaid > 0) {
            $sql .= " AND O.torneo_temporadaid = :t";
            $par[':t'] = $temporadaid;
        }

        return $this->filas($sql . " ORDER BY T.temporada_desde DESC, O.torneo_nombre", $par);
    }

    public function categorias(int $torneoid = 0): array
    {
        $sql = "SELECT C.*, O.torneo_nombre, O.torneo_temporadaid,
                       (SELECT COUNT(*) FROM dsl_inscripcion I
                         JOIN dsl_estado E ON E.estado_id = I.inscripcion_estadoid
                        WHERE I.inscripcion_categoriaid = C.categoria_id
                          AND E.estado_efectivo = 'S') AS equipos
                  FROM dsl_categoria C
                  JOIN dsl_torneo O ON O.torneo_id = C.categoria_torneoid
                 WHERE C.categoria_estado <> 'E'";
        $par = [];

        if ($torneoid > 0) {
            $sql .= " AND C.categoria_torneoid = :o";
            $par[':o'] = $torneoid;
        }

        return $this->filas($sql . " ORDER BY C.categoria_nombre", $par);
    }

    public function categoria(int $id): array
    {
        return $this->fila(
            "SELECT C.*, O.torneo_nombre, T.temporada_nombre
               FROM dsl_categoria C
               JOIN dsl_torneo    O ON O.torneo_id     = C.categoria_torneoid
               JOIN dsl_temporada T ON T.temporada_id  = O.torneo_temporadaid
              WHERE C.categoria_id = :id",
            [':id' => $id]
        );
    }

    /**
     * Equipos que componen un grupo.
     *
     * La pertenencia vive en dsl_grupo_equipo (migracion 033). Antes solo
     * estaba dentro del resultado del sorteo, lo que dejaba sin grupos a
     * las ligas que reparten a mano.
     */
    public function equiposDeGrupo(int $grupoid): array
    {
        return $this->filas(
            "SELECT I.inscripcion_id, I.inscripcion_estadoid, G.ge_orden,
                    Q.equipo_id, Q.equipo_nombre, Q.equipo_corto, Q.equipo_escudo,
                    E.estado_codigo, E.estado_nombre, E.estado_tono, E.estado_efectivo
               FROM dsl_grupo_equipo G
               JOIN dsl_inscripcion I ON I.inscripcion_id = G.ge_inscripcionid
               JOIN dsl_equipo      Q ON Q.equipo_id      = I.inscripcion_equipoid
               JOIN dsl_estado      E ON E.estado_id      = I.inscripcion_estadoid
              WHERE G.ge_grupoid = :g
              ORDER BY G.ge_orden, Q.equipo_nombre",
            [':g' => $grupoid]
        );
    }

    /** Grupos de una fase, con cuantos equipos tiene cada uno. */
    public function gruposDeFase(int $faseid): array
    {
        return $this->filas(
            "SELECT G.*,
                    (SELECT COUNT(*) FROM dsl_grupo_equipo GE
                      WHERE GE.ge_grupoid = G.grupo_id) AS equipos
               FROM dsl_grupo G
              WHERE G.grupo_faseid = :f
              ORDER BY G.grupo_orden, G.grupo_nombre",
            [':f' => $faseid]
        );
    }

    /**
     * Una fase con su categoria y torneo.
     *
     * Va en la clase base y no en un motor concreto: la usan el sorteo,
     * los playoffs y las pantallas. Se expone como metodo con nombre en
     * vez de dejar que cada vista lance su consulta, para que el SQL no
     * acabe repartido por las pantallas.
     */
    public function faseConContexto(int $faseid): array
    {
        return $this->fila(
            "SELECT F.*, C.categoria_id, C.categoria_nombre, C.categoria_torneoid,
                    T.torneo_nombre
               FROM dsl_fase F
               JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
               JOIN dsl_torneo    T ON T.torneo_id    = C.categoria_torneoid
              WHERE F.fase_id = :f",
            [':f' => $faseid]
        );
    }

    /** Cuantos partidos tiene ya la fase. */
    public function partidosEnFase(int $faseid): int
    {
        return (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_partido WHERE partido_faseid = :f",
            [':f' => $faseid]
        );
    }

    /**
     * Crea una fase nueva en una categoria.
     *
     * El orden se calcula, no se pide: las fases van una detras de otra y
     * dejar que el usuario teclee el numero solo produce huecos y
     * duplicados que luego rompen la clave unica.
     */
    public function guardarFase(): string
    {
        if (!puede_crear('playoffPanel')) { return $this->denegado('crear fases'); }

        $categoria = (int)($_POST['categoria_id'] ?? 0);
        $nombre    = trim((string)($_POST['fase_nombre'] ?? ''));
        $tipo      = strtoupper(substr(trim((string)($_POST['fase_tipo'] ?? 'E')), 0, 1));

        if ($categoria <= 0 || $nombre === '') {
            return $this->respuesta('simple', 'Faltan datos',
                'Indique la categoría y el nombre de la fase.', 'error');
        }
        if (!in_array($tipo, ['G', 'E', 'S'], true)) {
            return $this->respuesta('simple', 'Tipo no válido',
                'Elija grupos, eliminación directa o serie.', 'error');
        }

        $orden = 1 + (int)$this->escalar(
            "SELECT COALESCE(MAX(fase_orden), 0) FROM dsl_fase WHERE fase_categoriaid = :c",
            [':c' => $categoria]
        );

        $id = $this->escribir(
            "INSERT INTO dsl_fase (fase_categoriaid, fase_orden, fase_nombre, fase_tipo)
             VALUES (:c, :o, :n, :t)",
            [':c' => $categoria, ':o' => $orden, ':n' => $nombre, ':t' => $tipo]
        );

        if ($id < 0) {
            return $this->respuesta('simple', 'No se pudo crear',
                'La base de datos rechazó la fase.', 'error');
        }

        $this->auditar('fase', $id, 'crear', null,
            ['categoria' => $categoria, 'nombre' => $nombre, 'tipo' => $tipo, 'orden' => $orden]);

        return $this->respuesta('recargar', 'Fase creada', 'Se añadió «' . $nombre . '».');
    }

    /** Fases de una categoria, en su orden. */
    public function fasesDeCategoria(int $categoriaid): array
    {
        return $this->filas(
            "SELECT F.*,
                    (SELECT COUNT(*) FROM dsl_partido P WHERE P.partido_faseid = F.fase_id) AS partidos
               FROM dsl_fase F
              WHERE F.fase_categoriaid = :c AND F.fase_estado = 'A'
              ORDER BY F.fase_orden",
            [':c' => $categoriaid]
        );
    }

    /**
     * Equipos habilitados de una categoria.
     *
     * Se filtra por estado_efectivo y no por el codigo 'HABILITADA': si
     * manana se anade otro estado que tambien permita competir, el
     * fixture lo respeta sin tocar esta consulta.
     */
    public function equiposDeCategoria(int $categoriaid, bool $soloHabilitados = true): array
    {
        $sql = "SELECT I.inscripcion_id, I.inscripcion_estadoid,
                       Q.equipo_id, Q.equipo_nombre, Q.equipo_corto, Q.equipo_escudo,
                       E.estado_codigo, E.estado_nombre, E.estado_tono, E.estado_efectivo
                  FROM dsl_inscripcion I
                  JOIN dsl_equipo Q ON Q.equipo_id = I.inscripcion_equipoid
                  JOIN dsl_estado E ON E.estado_id = I.inscripcion_estadoid
                 WHERE I.inscripcion_categoriaid = :c";

        if ($soloHabilitados) {
            $sql .= " AND E.estado_efectivo = 'S'";
        }

        return $this->filas($sql . " ORDER BY Q.equipo_nombre", [':c' => $categoriaid]);
    }

    /*==================================================================
      Generacion del fixture

      Metodo del circulo: se fija un equipo y los demas rotan. Con n
      equipos salen n-1 jornadas de n/2 partidos, y cada pareja se
      encuentra exactamente una vez.
      ==================================================================*/

    /**
     * Calendario de todos contra todos.
     *
     * Devuelve un array de jornadas, cada una con sus emparejamientos.
     * NO escribe en la base: eso lo hace generarFixture(), que envuelve
     * la escritura en transaccion. Separarlo permite mostrar una vista
     * previa antes de confirmar.
     *
     * @param int[] $equipos ids de inscripcion
     */
    public function calendarioTodosContraTodos(array $equipos, bool $idaVuelta = false): array
    {
        $equipos = array_values(array_unique(array_map('intval', $equipos)));
        $n = count($equipos);

        if ($n < 2) { return []; }

        /* Con un numero impar se anade un hueco: el equipo emparejado con
           el hueco descansa esa jornada. Sin esto, el ultimo se quedaria
           sin rival y la rotacion se descuadra. */
        if ($n % 2 === 1) {
            $equipos[] = 0;
            $n++;
        }

        $jornadas = [];
        $rondas   = $n - 1;
        $mitad    = intdiv($n, 2);

        /* El primero se queda fijo; el resto rota una posicion por ronda. */
        $fijo  = array_shift($equipos);
        $rueda = $equipos;

        for ($r = 0; $r < $rondas; $r++) {
            $orden     = array_merge([$fijo], $rueda);
            $partidos  = [];

            for ($i = 0; $i < $mitad; $i++) {
                $a = $orden[$i];
                $b = $orden[$n - 1 - $i];

                if ($a === 0 || $b === 0) { continue; }   // le toca descansar

                /* Se alterna la localia por ronda para que ningun equipo
                   juegue todos sus partidos fuera de casa. */
                $partidos[] = ($r % 2 === 0)
                    ? ['local' => $a, 'visitante' => $b]
                    : ['local' => $b, 'visitante' => $a];
            }

            $jornadas[] = ['numero' => $r + 1, 'partidos' => $partidos];

            /* Rotacion: el ultimo pasa al principio de la rueda. */
            array_unshift($rueda, array_pop($rueda));
        }

        if ($idaVuelta) {
            $vuelta = [];
            foreach ($jornadas as $j) {
                $inv = [];
                foreach ($j['partidos'] as $p) {
                    $inv[] = ['local' => $p['visitante'], 'visitante' => $p['local']];
                }
                $vuelta[] = ['numero' => $j['numero'] + $rondas, 'partidos' => $inv];
            }
            $jornadas = array_merge($jornadas, $vuelta);
        }

        return $jornadas;
    }

    /**
     * Escribe el calendario en la base.
     *
     * TODO O NADA
     *
     * Va en transaccion porque un fixture a medias es peor que ninguno:
     * quedarian jornadas sin partidos y equipos con distinto numero de
     * encuentros, y la clasificacion saldria mal sin que nada avisara.
     *
     * Se niega a generar si la fase ya tiene partidos: regenerar por
     * encima duplicaria el calendario. Rehacerlo exige borrar antes, y
     * eso es una decision del usuario, no un efecto colateral.
     *
     * @return array ['ok'=>bool, 'mensaje'=>string, 'partidos'=>int, 'jornadas'=>int]
     */
    public function generarFixture(int $faseid, bool $idaVuelta = false): array
    {
        $fase = $this->fila(
            "SELECT F.*, C.categoria_id, C.categoria_nombre
               FROM dsl_fase F
               JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
              WHERE F.fase_id = :f",
            [':f' => $faseid]
        );

        if (!$fase) {
            return ['ok' => false, 'mensaje' => 'La fase no existe.', 'partidos' => 0, 'jornadas' => 0];
        }

        $yaHay = (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_partido WHERE partido_faseid = :f", [':f' => $faseid]);

        if ($yaHay > 0) {
            return ['ok' => false, 'partidos' => 0, 'jornadas' => 0,
                    'mensaje' => "La fase ya tiene {$yaHay} partidos. Elimínelos antes de volver a generar."];
        }

        /* CON GRUPOS, CADA UNO ES UN TORNEO APARTE
           ---------------------------------------------------------------
           Si la fase tiene grupos formados, un equipo sólo juega contra
           los de SU grupo. Generar todos contra todos sobre la categoría
           entera produciría partidos entre equipos de grupos distintos, y
           esos encuentros no cuentan para ninguna clasificación: la tabla
           saldría mal sin que nada avisara.

           Sin grupos, la fase es una liga única y se reparte entera. */
        $grupos = $this->gruposDeFase($faseid);

        $bloques = [];   // [grupoid|0 => [inscripcionid, ...]]

        if ($grupos) {
            foreach ($grupos as $g) {
                $miembros = array_column($this->equiposDeGrupo((int)$g['grupo_id']), 'inscripcion_id');
                if (count($miembros) >= 2) {
                    $bloques[(int)$g['grupo_id']] = $miembros;
                }
            }

            if (!$bloques) {
                return ['ok' => false, 'partidos' => 0, 'jornadas' => 0,
                        'mensaje' => 'Los grupos están formados pero ninguno tiene dos equipos.'];
            }
        } else {
            $equipos = $this->equiposDeCategoria((int)$fase['categoria_id']);

            if (count($equipos) < 2) {
                return ['ok' => false, 'partidos' => 0, 'jornadas' => 0,
                        'mensaje' => 'Hacen falta al menos dos equipos habilitados para generar el calendario.'];
            }

            $bloques[0] = array_column($equipos, 'inscripcion_id');
        }

        /* Los calendarios de todos los grupos se solapan por jornada: la
           jornada 1 es la jornada 1 de cada grupo. Así el calendario se
           publica por fecha y no por grupo, que es como lo lee la gente. */
        $calendario = [];
        foreach ($bloques as $grupoId => $ids) {
            foreach ($this->calendarioTodosContraTodos($ids, $idaVuelta) as $j) {
                $n = $j['numero'];
                foreach ($j['partidos'] as $par) {
                    $par['grupo'] = $grupoId ?: null;
                    $calendario[$n]['numero'] = $n;
                    $calendario[$n]['partidos'][] = $par;
                }
            }
        }
        ksort($calendario);
        $calendario = array_values($calendario);

        $programado = (int)$this->escalar(
            "SELECT estado_id FROM dsl_estado
              WHERE estado_entidad = 'partido' AND estado_codigo = 'PROGRAMADO'");

        if ($programado <= 0) {
            return ['ok' => false, 'partidos' => 0, 'jornadas' => 0,
                    'mensaje' => 'Falta el estado PROGRAMADO en el catálogo.'];
        }

        $con = $this->conexion();
        if ($con === null) {
            return ['ok' => false, 'partidos' => 0, 'jornadas' => 0,
                    'mensaje' => 'Sin conexión a la base de datos.'];
        }

        /* TRANSACCION PROPIA SOLO SI NO HAY UNA ABIERTA
           ---------------------------------------------------------------
           seguridad_conexion() devuelve una conexion compartida, de modo
           que si quien llama ya abrio una transaccion —un proceso de alta
           masiva, una prueba— este metodo NO debe abrir otra: PDO no
           anida, beginTransaction() falla, y el catch acabaria revirtiendo
           el trabajo del llamante en lugar del propio.

           Cuando la transaccion es ajena, un fallo se relanza en vez de
           tragarse: solo quien la abrio sabe hasta donde deshacer. */
        $propia = !$con->inTransaction();

        try {
            if ($propia) { $con->beginTransaction(); }

            $insJornada = $con->prepare(
                "INSERT INTO dsl_jornada (jornada_faseid, jornada_numero, jornada_nombre)
                 VALUES (:f, :n, :nom)");

            $insPartido = $con->prepare(
                "INSERT INTO dsl_partido
                        (partido_faseid, partido_grupoid, partido_jornadaid, partido_localid,
                         partido_visitanteid, partido_estadoid, partido_usuarioid)
                 VALUES (:f, :g, :j, :l, :v, :e, :u)");

            $usuario  = usuario_actual_id() ?: null;
            $partidos = 0;

            foreach ($calendario as $j) {
                $insJornada->execute([
                    ':f' => $faseid, ':n' => $j['numero'], ':nom' => 'Jornada ' . $j['numero']]);
                $jornadaId = (int)$con->lastInsertId();

                foreach ($j['partidos'] as $p) {
                    $insPartido->execute([
                        ':f' => $faseid, ':g' => $p['grupo'] ?? null,
                        ':j' => $jornadaId,
                        ':l' => $p['local'], ':v' => $p['visitante'],
                        ':e' => $programado, ':u' => $usuario,
                    ]);
                    $partidos++;
                }
            }

            if ($propia) { $con->commit(); }

            $this->auditar('fase', $faseid, 'crear', null,
                ['jornadas' => count($calendario), 'partidos' => $partidos,
                 'equipos' => count($ids), 'ida_vuelta' => $idaVuelta],
                'Generación de calendario');

            return ['ok' => true, 'partidos' => $partidos, 'jornadas' => count($calendario),
                    'mensaje' => "Se generaron {$partidos} partidos en " . count($calendario) . ' jornadas.'];

        } catch (\Throwable $e) {
            if ($propia) {
                if ($con->inTransaction()) { $con->rollBack(); }
                return ['ok' => false, 'partidos' => 0, 'jornadas' => 0,
                        'mensaje' => 'No se pudo generar el calendario: ' . $e->getMessage()];
            }

            /* La transacción es de quien llamó: sólo él sabe hasta dónde
               deshacer, así que se le devuelve el fallo en vez de tragarlo
               y devolver un «no se pudo» con su trabajo ya revertido. */
            throw $e;
        }
    }

    /*==================================================================
      Tabla de posiciones

      Se calcula a partir de los resultados; no se guarda (decision D6).
      Solo cuentan los partidos en estado EFECTIVO: finalizado o walkover.
      Un partido cancelado es terminal y no entra en el computo, y esa
      distincion es justo para lo que existe la columna estado_efectivo.
      ==================================================================*/

    /** Partidos que cuentan para la clasificacion de una fase. */
    private function resultadosDeFase(int $faseid, int $grupoid = 0): array
    {
        $sql = "SELECT P.partido_id, P.partido_localid, P.partido_visitanteid,
                       P.partido_puntoslocal, P.partido_puntosvisitante,
                       E.estado_codigo
                  FROM dsl_partido P
                  JOIN dsl_estado E ON E.estado_id = P.partido_estadoid
                 WHERE P.partido_faseid = :f
                   AND E.estado_efectivo = 'S'
                   AND P.partido_puntoslocal IS NOT NULL
                   AND P.partido_puntosvisitante IS NOT NULL";
        $par = [':f' => $faseid];

        if ($grupoid > 0) {
            $sql .= " AND P.partido_grupoid = :g";
            $par[':g'] = $grupoid;
        }

        return $this->filas($sql, $par);
    }

    /**
     * Clasificacion de una fase, ya ordenada y con los desempates
     * aplicados.
     *
     * @return array filas con las claves: inscripcion_id, equipo, pj, pg,
     *               pp, pf, pc, dif, pts, posicion, desempate
     */
    public function tablaPosiciones(int $faseid, int $grupoid = 0): array
    {
        $fase = $this->fila(
            "SELECT F.fase_id, C.*
               FROM dsl_fase F
               JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
              WHERE F.fase_id = :f",
            [':f' => $faseid]
        );

        if (!$fase) { return []; }

        $ptsVic = (int)($fase['categoria_ptsvictoria'] ?? 2);
        $ptsDer = (int)($fase['categoria_ptsderrota']  ?? 1);
        $ptsWo  = (int)($fase['categoria_ptswalkover'] ?? 0);

        /* Con grupo, sólo sus miembros.
           Antes se tomaban todos los equipos de la categoría y sólo los
           resultados del grupo, así que los equipos de los OTROS grupos
           aparecían en la tabla con todo a cero. Se nota al mirar una
           tabla de grupo: sobran filas. Y arrastra: los playoffs siembran
           el cuadro leyendo estas clasificaciones. */
        $equipos = $grupoid > 0
            ? $this->equiposDeGrupo($grupoid)
            : $this->equiposDeCategoria((int)$fase['categoria_id']);

        $resultados = $this->resultadosDeFase($faseid, $grupoid);

        /*----------  Acumulado base  ----------*/
        $tabla = [];
        foreach ($equipos as $e) {
            $tabla[(int)$e['inscripcion_id']] = [
                'inscripcion_id' => (int)$e['inscripcion_id'],
                'equipo'         => $e['equipo_nombre'],
                'corto'          => $e['equipo_corto'],
                'escudo'         => $e['equipo_escudo'],
                'pj' => 0, 'pg' => 0, 'pp' => 0,
                'pf' => 0, 'pc' => 0, 'dif' => 0, 'pts' => 0,
                'desempate' => '',
            ];
        }

        foreach ($resultados as $r) {
            $l  = (int)$r['partido_localid'];
            $v  = (int)$r['partido_visitanteid'];
            $pl = (int)$r['partido_puntoslocal'];
            $pv = (int)$r['partido_puntosvisitante'];

            if (!isset($tabla[$l], $tabla[$v])) { continue; }

            $tabla[$l]['pj']++; $tabla[$v]['pj']++;
            $tabla[$l]['pf'] += $pl; $tabla[$l]['pc'] += $pv;
            $tabla[$v]['pf'] += $pv; $tabla[$v]['pc'] += $pl;

            $wo = $r['estado_codigo'] === 'WALKOVER';

            if ($pl > $pv) {
                $tabla[$l]['pg']++; $tabla[$v]['pp']++;
                $tabla[$l]['pts'] += $ptsVic;
                /* Quien no se presenta no recibe el punto de derrota. */
                $tabla[$v]['pts'] += $wo ? $ptsWo : $ptsDer;
            } elseif ($pv > $pl) {
                $tabla[$v]['pg']++; $tabla[$l]['pp']++;
                $tabla[$v]['pts'] += $ptsVic;
                $tabla[$l]['pts'] += $wo ? $ptsWo : $ptsDer;
            } else {
                /* En baloncesto no hay empate; si aparece, se trata como
                   dato erroneo y no se reparten puntos, para que el
                   problema se vea en la tabla en vez de disolverse. */
                $tabla[$l]['desempate'] = $tabla[$v]['desempate'] = 'resultado empatado, revisar acta';
            }
        }

        foreach ($tabla as &$t) { $t['dif'] = $t['pf'] - $t['pc']; }
        unset($t);

        /*----------  Orden y desempates  ----------*/
        $criterios = array_filter(array_map('trim',
            explode(',', (string)($fase['categoria_desempate'] ?? 'DIRECTO,DIFDIR,DIF,PF'))));

        $ordenada = $this->ordenarConDesempates(array_values($tabla), $resultados, $criterios);

        $pos = 0;
        foreach ($ordenada as &$f) { $f['posicion'] = ++$pos; }
        unset($f);

        return $ordenada;
    }

    /**
     * Ordena por puntos y resuelve los empates aplicando los criterios en
     * el orden configurado.
     *
     * EL DESEMPATE ES RECURSIVO
     *
     * El enfrentamiento directo no se resuelve mirando un partido: se
     * construye una mini-tabla SOLO con los partidos entre los equipos
     * empatados. Y esa mini-tabla puede volver a empatar, en cuyo caso
     * hay que pasar al criterio siguiente dentro del subconjunto que
     * sigue igualado, no dentro del grupo original.
     *
     * Aplicarlo de otro modo —ordenar por cuatro columnas de golpe— da
     * resultados distintos y es el error habitual: con tres equipos
     * empatados, la diferencia general puede favorecer a uno que perdio
     * los dos enfrentamientos directos.
     */
    private function ordenarConDesempates(array $filas, array $resultados, array $criterios): array
    {
        /* Primero por puntos, que es el criterio que nadie discute. */
        usort($filas, fn($a, $b) => $b['pts'] <=> $a['pts']);

        $salida = [];
        $i = 0;
        $n = count($filas);

        while ($i < $n) {
            /* Se agrupan los que llevan los mismos puntos. */
            $j = $i;
            while ($j + 1 < $n && $filas[$j + 1]['pts'] === $filas[$i]['pts']) { $j++; }

            $bloque = array_slice($filas, $i, $j - $i + 1);

            if (count($bloque) > 1) {
                $bloque = $this->desempatar($bloque, $resultados, $criterios, 0);
            }

            foreach ($bloque as $f) { $salida[] = $f; }
            $i = $j + 1;
        }

        return $salida;
    }

    /** Aplica el criterio $k al bloque empatado y recurre sobre lo que siga igualado. */
    private function desempatar(array $bloque, array $resultados, array $criterios, int $k): array
    {
        if ($k >= count($criterios) || count($bloque) < 2) {
            return $bloque;
        }

        $criterio = strtoupper($criterios[$k]);
        $ids      = array_column($bloque, 'inscripcion_id');
        $valor    = [];

        switch ($criterio) {
            case 'DIRECTO':
            case 'DIFDIR':
                /* Mini-tabla con los partidos entre los empatados. */
                $mini = [];
                foreach ($ids as $id) { $mini[$id] = ['pts' => 0, 'dif' => 0]; }

                foreach ($resultados as $r) {
                    $l = (int)$r['partido_localid'];
                    $v = (int)$r['partido_visitanteid'];
                    if (!isset($mini[$l], $mini[$v])) { continue; }

                    $pl = (int)$r['partido_puntoslocal'];
                    $pv = (int)$r['partido_puntosvisitante'];

                    $mini[$l]['dif'] += $pl - $pv;
                    $mini[$v]['dif'] += $pv - $pl;

                    if ($pl > $pv) { $mini[$l]['pts']++; }
                    elseif ($pv > $pl) { $mini[$v]['pts']++; }
                }

                $clave = $criterio === 'DIRECTO' ? 'pts' : 'dif';
                foreach ($ids as $id) { $valor[$id] = $mini[$id][$clave]; }
                $etiqueta = $criterio === 'DIRECTO' ? 'enfrentamiento directo' : 'diferencia directa';
                break;

            case 'DIF':
                foreach ($bloque as $f) { $valor[$f['inscripcion_id']] = $f['dif']; }
                $etiqueta = 'diferencia general';
                break;

            case 'PF':
                foreach ($bloque as $f) { $valor[$f['inscripcion_id']] = $f['pf']; }
                $etiqueta = 'puntos a favor';
                break;

            default:
                /* Criterio desconocido: se salta en lugar de romper. */
                return $this->desempatar($bloque, $resultados, $criterios, $k + 1);
        }

        usort($bloque, fn($a, $b) => $valor[$b['inscripcion_id']] <=> $valor[$a['inscripcion_id']]);

        /* Se vuelve a agrupar por el valor del criterio: lo que quedo
           igualado pasa al criterio siguiente, y solo eso. */
        $salida = [];
        $i = 0;
        $m = count($bloque);

        while ($i < $m) {
            $j = $i;
            while ($j + 1 < $m
                && $valor[$bloque[$j + 1]['inscripcion_id']] === $valor[$bloque[$i]['inscripcion_id']]) {
                $j++;
            }

            $sub = array_slice($bloque, $i, $j - $i + 1);

            if (count($sub) > 1) {
                $sub = $this->desempatar($sub, $resultados, $criterios, $k + 1);
            } else {
                /* Se deja constancia de por que quedo por delante. */
                $sub[0]['desempate'] = $i > 0 || $j + 1 < $m ? $etiqueta : $sub[0]['desempate'];
            }

            foreach ($sub as $f) { $salida[] = $f; }
            $i = $j + 1;
        }

        return $salida;
    }

    /*==================================================================
      Escritura

      Cada metodo comprueba su propio permiso: el endpoint AJAX verifica
      sesion, origen y acceso al modulo, pero la accion concreta —crear,
      editar— depende de la operacion y solo aqui se sabe cual es.

      Todos devuelven JSON con la forma que espera el front del
      ecosistema: tipo, titulo, texto, icono.
      ==================================================================*/

    protected function respuesta(string $tipo, string $titulo, string $texto,
                                 string $icono = 'success'): string
    {
        return json_encode(
            ['tipo' => $tipo, 'titulo' => $titulo, 'texto' => $texto, 'icono' => $icono],
            JSON_UNESCAPED_UNICODE
        );
    }

    protected function denegado(string $que): string
    {
        return $this->respuesta('simple', 'Acceso denegado',
            'Su rol no puede ' . $que . '.', 'error');
    }

    /**
     * Ejecuta una escritura y devuelve el id afectado.
     *
     * Centraliza el try/catch para que los metodos de arriba se lean como
     * validacion + SQL, y no como cuatro niveles de manejo de errores.
     */
    protected function escribir(string $sql, array $par): int
    {
        $con = $this->conexion();
        if ($con === null) { return -1; }

        try {
            $st = $con->prepare($sql);
            $st->execute($par);
            return isset($par[':id']) && (int)$par[':id'] > 0
                ? (int)$par[':id']
                : (int)$con->lastInsertId();
        } catch (\PDOException $e) {
            return -1;
        }
    }

    /*----------  Temporada  ----------*/

    public function guardarTemporada(): string
    {
        $id = (int)($_POST['temporada_id'] ?? 0);

        if ($id > 0 && !puede_editar('temporadaList')) { return $this->denegado('editar temporadas'); }
        if ($id === 0 && !puede_crear('temporadaList')) { return $this->denegado('crear temporadas'); }

        $nombre = trim((string)($_POST['temporada_nombre'] ?? ''));
        $desde  = trim((string)($_POST['temporada_desde'] ?? ''));
        $hasta  = trim((string)($_POST['temporada_hasta'] ?? ''));

        if ($nombre === '') {
            return $this->respuesta('simple', 'Faltan datos', 'El nombre es obligatorio.', 'error');
        }
        if ($desde === '' || $hasta === '') {
            return $this->respuesta('simple', 'Faltan datos',
                'Indique las fechas de inicio y fin.', 'error');
        }
        if ($hasta < $desde) {
            return $this->respuesta('simple', 'Fechas invertidas',
                'La fecha de fin no puede ser anterior a la de inicio.', 'error');
        }

        $antes = $id > 0
            ? $this->fila("SELECT * FROM dsl_temporada WHERE temporada_id = :id", [':id' => $id])
            : null;

        $sql = $id > 0
            ? "UPDATE dsl_temporada SET temporada_nombre = :n, temporada_desde = :d,
                      temporada_hasta = :h WHERE temporada_id = :id"
            : "INSERT INTO dsl_temporada (temporada_nombre, temporada_desde, temporada_hasta,
                      temporada_usuarioid) VALUES (:n, :d, :h, :u)";

        $par = [':n' => $nombre, ':d' => $desde, ':h' => $hasta];
        if ($id > 0) { $par[':id'] = $id; } else { $par[':u'] = usuario_actual_id() ?: null; }

        $nuevoId = $this->escribir($sql, $par);

        if ($nuevoId < 0) {
            return $this->respuesta('simple', 'No se pudo guardar',
                'Ya existe una temporada con ese nombre.', 'error');
        }

        $this->auditar('temporada', $nuevoId, $id > 0 ? 'editar' : 'crear', $antes,
            ['nombre' => $nombre, 'desde' => $desde, 'hasta' => $hasta]);

        return $this->respuesta('recargar', 'Temporada guardada',
            'Se guardó «' . $nombre . '».');
    }

    /*----------  Torneo  ----------*/

    public function guardarTorneo(): string
    {
        $id = (int)($_POST['torneo_id'] ?? 0);

        if ($id > 0 && !puede_editar('torneoList')) { return $this->denegado('editar torneos'); }
        if ($id === 0 && !puede_crear('torneoList')) { return $this->denegado('crear torneos'); }

        $temporada = (int)($_POST['torneo_temporadaid'] ?? 0);
        $nombre    = trim((string)($_POST['torneo_nombre'] ?? ''));
        $deporte   = trim((string)($_POST['torneo_deporte'] ?? 'baloncesto'));

        if ($temporada <= 0) {
            return $this->respuesta('simple', 'Faltan datos', 'Seleccione la temporada.', 'error');
        }
        if ($nombre === '') {
            return $this->respuesta('simple', 'Faltan datos', 'El nombre es obligatorio.', 'error');
        }

        $antes = $id > 0
            ? $this->fila("SELECT * FROM dsl_torneo WHERE torneo_id = :id", [':id' => $id])
            : null;

        $sql = $id > 0
            ? "UPDATE dsl_torneo SET torneo_temporadaid = :t, torneo_nombre = :n,
                      torneo_deporte = :dep WHERE torneo_id = :id"
            : "INSERT INTO dsl_torneo (torneo_temporadaid, torneo_nombre, torneo_deporte,
                      torneo_usuarioid) VALUES (:t, :n, :dep, :u)";

        $par = [':t' => $temporada, ':n' => $nombre, ':dep' => $deporte];
        if ($id > 0) { $par[':id'] = $id; } else { $par[':u'] = usuario_actual_id() ?: null; }

        $nuevoId = $this->escribir($sql, $par);

        if ($nuevoId < 0) {
            return $this->respuesta('simple', 'No se pudo guardar',
                'Ya existe un torneo con ese nombre en la temporada.', 'error');
        }

        $this->auditar('torneo', $nuevoId, $id > 0 ? 'editar' : 'crear', $antes,
            ['nombre' => $nombre, 'temporada' => $temporada]);

        return $this->respuesta('recargar', 'Torneo guardado', 'Se guardó «' . $nombre . '».');
    }

    /**
     * Publica o retira un torneo del portal público.
     *
     * Va aparte de guardarTorneo() a proposito: publicar no es editar un
     * campo mas. Es la decision de abrir al mundo unos datos que incluyen
     * nombres de menores, y merece su propia accion, su propio permiso y
     * su propio registro de auditoria con quien y cuando.
     */
    public function publicarTorneo(): string
    {
        /* Se exige el permiso de ELIMINAR sobre torneos, que es el mas
           restrictivo de los cuatro: abrir datos al publico es al menos
           tan delicado como borrarlos, y asi no hereda el permiso de
           quien solo edita nombres. */
        if (!puede_eliminar('torneoList')) {
            return $this->denegado('publicar torneos en el portal');
        }

        $id       = (int)($_POST['torneo_id'] ?? 0);
        $publicar = ($_POST['publicar'] ?? '') === 'S';

        $torneo = $this->fila(
            "SELECT torneo_id, torneo_nombre, torneo_publico, torneo_slug
               FROM dsl_torneo WHERE torneo_id = :id",
            [':id' => $id]
        );

        if (!$torneo) {
            return $this->respuesta('simple', 'No encontrado', 'El torneo no existe.', 'error');
        }

        /* El slug puede faltar en torneos creados antes de la migracion:
           se genera al publicar, que es cuando hace falta. */
        $slug = (string)($torneo['torneo_slug'] ?? '');
        if ($slug === '') {
            $slug = $this->slug($torneo['torneo_nombre']) . '-' . $id;
        }

        $ok = $this->escribir(
            "UPDATE dsl_torneo SET torneo_publico = :p, torneo_slug = :s WHERE torneo_id = :id",
            [':p' => $publicar ? 'S' : 'N', ':s' => $slug, ':id' => $id]
        );

        if ($ok < 0) {
            return $this->respuesta('simple', 'No se pudo cambiar',
                'La base de datos rechazó el cambio.', 'error');
        }

        $this->auditar('torneo', $id, 'editar',
            ['publico' => $torneo['torneo_publico']],
            ['publico' => $publicar ? 'S' : 'N', 'slug' => $slug],
            $publicar ? 'Publicado en el portal' : 'Retirado del portal');

        return $this->respuesta('recargar',
            $publicar ? 'Torneo publicado' : 'Torneo retirado',
            $publicar
                ? 'Ya es visible en el portal público. El enlace es /publico/t/' . $slug . '/'
                : 'Deja de ser visible en el portal público.');
    }

    /**
     * Convierte un texto en una parte de URL legible.
     *
     * Se transliteran las vocales acentuadas y la ene en vez de
     * descartarlas: «Categoría Única» debe dar «categoria-unica», no
     * «categor-a-nica», que no se lee.
     */
    protected function slug(string $texto): string
    {
        $de = ['á','à','ä','â','é','è','ë','ê','í','ì','ï','î',
               'ó','ò','ö','ô','ú','ù','ü','û','ñ','ç'];
        $a  = ['a','a','a','a','e','e','e','e','i','i','i','i',
               'o','o','o','o','u','u','u','u','n','c'];

        $t = str_replace($de, $a, mb_strtolower(trim($texto), 'UTF-8'));
        $t = preg_replace('/[^a-z0-9]+/', '-', $t);

        return trim((string)$t, '-') ?: 'torneo';
    }

    /*----------  Categoria  ----------*/

    public function guardarCategoria(): string
    {
        $id = (int)($_POST['categoria_id'] ?? 0);

        if ($id > 0 && !puede_editar('categoriaList')) { return $this->denegado('editar categorías'); }
        if ($id === 0 && !puede_crear('categoriaList')) { return $this->denegado('crear categorías'); }

        $torneo  = (int)($_POST['categoria_torneoid'] ?? 0);
        $nombre  = trim((string)($_POST['categoria_nombre'] ?? ''));
        $genero  = strtoupper(substr(trim((string)($_POST['categoria_genero'] ?? 'X')), 0, 1));
        $edadMin = $_POST['categoria_edadmin'] === '' ? null : (int)$_POST['categoria_edadmin'];
        $edadMax = $_POST['categoria_edadmax'] === '' ? null : (int)$_POST['categoria_edadmax'];
        $corte   = trim((string)($_POST['categoria_fechacorte'] ?? ''));
        $ptsVic  = (int)($_POST['categoria_ptsvictoria'] ?? 2);
        $ptsDer  = (int)($_POST['categoria_ptsderrota'] ?? 1);
        $ptsWo   = (int)($_POST['categoria_ptswalkover'] ?? 0);

        if ($torneo <= 0 || $nombre === '') {
            return $this->respuesta('simple', 'Faltan datos',
                'El torneo y el nombre son obligatorios.', 'error');
        }
        if (!in_array($genero, ['M', 'F', 'X'], true)) {
            return $this->respuesta('simple', 'Género no válido',
                'Seleccione masculino, femenino o mixto.', 'error');
        }
        if ($edadMin !== null && $edadMax !== null && $edadMin > $edadMax) {
            return $this->respuesta('simple', 'Rango de edad invertido',
                'La edad mínima no puede ser mayor que la máxima.', 'error');
        }

        /* Un rango de edad sin fecha de corte es ambiguo: la elegibilidad
           de un jugador cambiaria segun el dia en que se consultara. */
        if (($edadMin !== null || $edadMax !== null) && $corte === '') {
            return $this->respuesta('simple', 'Falta la fecha de corte',
                'Con un rango de edad hay que indicar a qué fecha se mide. '
                . 'Sin ella, «Sub-14» significa una cosa distinta cada mes.', 'error');
        }

        /* No presentarse no puede valer mas que perder jugando. */
        if ($ptsWo > $ptsDer) {
            return $this->respuesta('simple', 'Puntuación incoherente',
                'El walkover no puede otorgar más puntos que una derrota jugada.', 'error');
        }

        $antes = $id > 0
            ? $this->fila("SELECT * FROM dsl_categoria WHERE categoria_id = :id", [':id' => $id])
            : null;

        $sql = $id > 0
            ? "UPDATE dsl_categoria SET categoria_torneoid = :t, categoria_nombre = :n,
                      categoria_genero = :g, categoria_edadmin = :emin, categoria_edadmax = :emax,
                      categoria_fechacorte = :fc, categoria_ptsvictoria = :pv,
                      categoria_ptsderrota = :pd, categoria_ptswalkover = :pw
                WHERE categoria_id = :id"
            : "INSERT INTO dsl_categoria (categoria_torneoid, categoria_nombre, categoria_genero,
                      categoria_edadmin, categoria_edadmax, categoria_fechacorte,
                      categoria_ptsvictoria, categoria_ptsderrota, categoria_ptswalkover)
               VALUES (:t, :n, :g, :emin, :emax, :fc, :pv, :pd, :pw)";

        $par = [':t' => $torneo, ':n' => $nombre, ':g' => $genero,
                ':emin' => $edadMin, ':emax' => $edadMax,
                ':fc' => $corte !== '' ? $corte : null,
                ':pv' => $ptsVic, ':pd' => $ptsDer, ':pw' => $ptsWo];
        if ($id > 0) { $par[':id'] = $id; }

        $nuevoId = $this->escribir($sql, $par);

        if ($nuevoId < 0) {
            return $this->respuesta('simple', 'No se pudo guardar',
                'Ya existe una categoría con ese nombre en el torneo.', 'error');
        }

        /* Una categoria recien creada nace con su fase de grupos.
           Obligar a crearla aparte solo produce categorias inservibles:
           sin fase no se puede generar calendario, y el usuario no tiene
           por que saber que existe ese nivel intermedio para empezar. */
        if ($id === 0) {
            $this->escribir(
                "INSERT INTO dsl_fase (fase_categoriaid, fase_orden, fase_nombre, fase_tipo)
                 VALUES (:c, 1, 'Fase de grupos', 'G')",
                [':c' => $nuevoId]
            );
        }

        $this->auditar('categoria', $nuevoId, $id > 0 ? 'editar' : 'crear', $antes,
            ['nombre' => $nombre, 'genero' => $genero, 'corte' => $corte]);

        return $this->respuesta('recargar', 'Categoría guardada', 'Se guardó «' . $nombre . '».');
    }

    /*----------  Equipo  ----------*/

    public function equipos(): array
    {
        return $this->filas(
            "SELECT Q.*,
                    (SELECT COUNT(*) FROM dsl_inscripcion I
                      WHERE I.inscripcion_equipoid = Q.equipo_id) AS inscripciones
               FROM dsl_equipo Q
              WHERE Q.equipo_estado <> 'E'
              ORDER BY Q.equipo_nombre"
        );
    }

    /** Carpeta absoluta donde viven los escudos. */
    public static function escudosDir(): string
    {
        return dirname(__DIR__) . '/assets/img/escudos/';
    }

    /** URL pública de esa carpeta. */
    public static function escudosUrl(): string
    {
        return APP_URL . 'assets/img/escudos/';
    }

    public function guardarEquipo(): string
    {
        $id = (int)($_POST['equipo_id'] ?? 0);

        if ($id > 0 && !puede_editar('equipoList')) { return $this->denegado('editar equipos'); }
        if ($id === 0 && !puede_crear('equipoList')) { return $this->denegado('crear equipos'); }

        $nombre   = trim((string)($_POST['equipo_nombre'] ?? ''));
        $corto    = trim((string)($_POST['equipo_corto'] ?? ''));
        $contacto = trim((string)($_POST['equipo_contacto'] ?? ''));
        $telefono = trim((string)($_POST['equipo_telefono'] ?? ''));
        $email    = trim((string)($_POST['equipo_email'] ?? ''));
        $quitar   = ($_POST['quitar_escudo'] ?? '') === '1';

        /* Datos tributarios (migración 039). Opcionales: un equipo sin
           ellos es un equipo que todavía no puede facturar, no un error.
           Quien intente emitirle recibe un mensaje que dice qué falta. */
        $idtipo   = trim((string)($_POST['equipo_idtipo'] ?? '04'));
        $ident    = trim((string)($_POST['equipo_identificacion'] ?? ''));
        $razon    = trim((string)($_POST['equipo_razonsocial'] ?? ''));
        $direccion = trim((string)($_POST['equipo_direccion'] ?? ''));

        if ($nombre === '') {
            return $this->respuesta('simple', 'Faltan datos', 'El nombre es obligatorio.', 'error');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respuesta('simple', 'Correo no válido',
                'Revise la dirección de correo.', 'error');
        }
        if (!\in_array($idtipo, ['04', '05', '06', '07'], true)) { $idtipo = '04'; }

        /* Se valida aquí y no al emitir: corregir un dígito con el equipo
           delante es trivial, y descubrirlo cuando el SRI devuelve el
           comprobante obliga a anularlo y reemitir con otro número. */
        if ($ident !== '' && !sri_identificacion_valida($ident, $idtipo)) {
            return $this->respuesta('simple', 'Identificación no válida',
                'El número «' . $ident . '» no es una identificación válida para el tipo '
                . 'elegido: el dígito verificador no cuadra.', 'error');
        }

        $antes = $id > 0
            ? $this->fila("SELECT * FROM dsl_equipo WHERE equipo_id = :id", [':id' => $id])
            : null;

        /* El escudo se resuelve con el servicio del núcleo: valida por
           contenido real, vuelve a dibujar la imagen con GD —lo que
           descarta cualquier carga incrustada— y genera el nombre en el
           servidor. Ningún módulo escribe su propia validación de
           subidas. */
        $escudo = ds_imagen_resolver(
            'equipo_escudo', self::escudosDir(), 'escudo',
            (string)($antes['equipo_escudo'] ?? ''), $quitar
        );

        if ($escudo !== '' && $escudo[0] === '!') {
            return $this->respuesta('simple', 'Escudo no válido', substr($escudo, 1), 'error');
        }

        $sql = $id > 0
            ? "UPDATE dsl_equipo SET equipo_nombre = :n, equipo_corto = :c,
                      equipo_contacto = :ct, equipo_telefono = :t, equipo_email = :e,
                      equipo_escudo = :esc, equipo_idtipo = :it,
                      equipo_identificacion = :ide, equipo_razonsocial = :rz,
                      equipo_direccion = :dir
                WHERE equipo_id = :id"
            : "INSERT INTO dsl_equipo (equipo_nombre, equipo_corto, equipo_contacto,
                      equipo_telefono, equipo_email, equipo_escudo,
                      equipo_idtipo, equipo_identificacion, equipo_razonsocial,
                      equipo_direccion)
               VALUES (:n, :c, :ct, :t, :e, :esc, :it, :ide, :rz, :dir)";

        $par = [':n' => $nombre, ':c' => $corto, ':ct' => $contacto,
                ':t' => $telefono, ':e' => $email, ':esc' => $escudo !== '' ? $escudo : null,
                ':it' => $idtipo, ':ide' => $ident, ':rz' => $razon, ':dir' => $direccion];
        if ($id > 0) { $par[':id'] = $id; }

        $nuevoId = $this->escribir($sql, $par);

        if ($nuevoId < 0) {
            /* Si el registro no entró, la imagen recién subida se queda
               huérfana en disco. Se retira. */
            if ($escudo !== '' && $escudo !== ($antes['equipo_escudo'] ?? '')) {
                ds_imagen_borrar(self::escudosDir(), $escudo);
            }
            return $this->respuesta('simple', 'No se pudo guardar',
                'Ya existe un equipo con ese nombre.', 'error');
        }

        $this->auditar('equipo', $nuevoId, $id > 0 ? 'editar' : 'crear', $antes,
            ['nombre' => $nombre, 'corto' => $corto, 'escudo' => $escudo]);

        return $this->respuesta('recargar', 'Equipo guardado', 'Se guardó «' . $nombre . '».');
    }

    /*----------  Inscripcion  ----------*/

    public function inscribirEquipo(): string
    {
        if (!puede_crear('categoriaPanel')) { return $this->denegado('inscribir equipos'); }

        $equipo    = (int)($_POST['inscripcion_equipoid'] ?? 0);
        $categoria = (int)($_POST['inscripcion_categoriaid'] ?? 0);
        $valor     = round((float)str_replace(',', '.', (string)($_POST['inscripcion_valor'] ?? '0')), 2);

        if ($equipo <= 0 || $categoria <= 0) {
            return $this->respuesta('simple', 'Faltan datos',
                'Seleccione el equipo y la categoría.', 'error');
        }
        if ($valor < 0) {
            return $this->respuesta('simple', 'Importe no válido',
                'El valor de inscripción no puede ser negativo.', 'error');
        }

        /* Una inscripcion nace en BORRADOR, no habilitada: hay que revisar
           documentos y cobrar antes de que el equipo pueda competir. */
        $estado = (int)$this->escalar(
            "SELECT estado_id FROM dsl_estado
              WHERE estado_entidad = 'inscripcion' AND estado_codigo = 'BORRADOR'");

        if ($estado <= 0) {
            return $this->respuesta('simple', 'Catálogo incompleto',
                'Falta el estado BORRADOR de inscripción.', 'error');
        }

        $nuevoId = $this->escribir(
            "INSERT INTO dsl_inscripcion (inscripcion_equipoid, inscripcion_categoriaid,
                    inscripcion_estadoid, inscripcion_fecha, inscripcion_valor, inscripcion_usuarioid)
             VALUES (:e, :c, :s, CURDATE(), :v, :u)",
            [':e' => $equipo, ':c' => $categoria, ':s' => $estado,
             ':v' => $valor, ':u' => usuario_actual_id() ?: null]
        );

        if ($nuevoId < 0) {
            return $this->respuesta('simple', 'Equipo ya inscrito',
                'Ese equipo ya está inscrito en esta categoría.', 'error');
        }

        $this->auditar('inscripcion', $nuevoId, 'crear', null,
            ['equipo' => $equipo, 'categoria' => $categoria, 'valor' => $valor]);

        return $this->respuesta('recargar', 'Equipo inscrito',
            'La inscripción queda en borrador hasta que se revise la documentación.');
    }

    /**
     * Cambia el estado de una inscripcion respetando las transiciones.
     *
     * La comprobacion NO esta aqui: esta en dsl_estado_transicion. Este
     * metodo solo pregunta si el movimiento existe.
     */
    public function cambiarEstadoInscripcion(): string
    {
        if (!puede_editar('categoriaPanel')) { return $this->denegado('cambiar el estado'); }

        $id    = (int)($_POST['inscripcion_id'] ?? 0);
        $hacia = trim((string)($_POST['hacia'] ?? ''));
        $motivo = trim((string)($_POST['motivo'] ?? ''));

        $actual = $this->fila(
            "SELECT I.inscripcion_id, E.estado_codigo, E.estado_nombre
               FROM dsl_inscripcion I
               JOIN dsl_estado E ON E.estado_id = I.inscripcion_estadoid
              WHERE I.inscripcion_id = :id",
            [':id' => $id]
        );

        if (!$actual) {
            return $this->respuesta('simple', 'No encontrada',
                'La inscripción no existe.', 'error');
        }

        if (!$this->transicionPermitida('inscripcion', $actual['estado_codigo'], $hacia)) {
            return $this->respuesta('simple', 'Movimiento no permitido',
                'Desde «' . $actual['estado_nombre'] . '» no se puede pasar a ese estado.', 'error');
        }

        /* Las transiciones marcadas exigen justificacion escrita: es lo que
           luego permite explicar por que un equipo quedo fuera. */
        $exige = $this->fila(
            "SELECT T.trans_motivo
               FROM dsl_estado_transicion T
               JOIN dsl_estado D ON D.estado_id = T.trans_desde
               JOIN dsl_estado H ON H.estado_id = T.trans_hasta
              WHERE T.trans_entidad = 'inscripcion'
                AND D.estado_codigo = :d AND H.estado_codigo = :h",
            [':d' => $actual['estado_codigo'], ':h' => $hacia]
        );

        if (($exige['trans_motivo'] ?? 'N') === 'S' && $motivo === '') {
            return $this->respuesta('simple', 'Falta el motivo',
                'Este cambio necesita una justificación escrita.', 'error');
        }

        $destino = $this->estado('inscripcion', $hacia);

        $ok = $this->escribir(
            "UPDATE dsl_inscripcion SET inscripcion_estadoid = :s,
                    inscripcion_observacion = :o WHERE inscripcion_id = :id",
            [':s' => $destino['estado_id'], ':o' => $motivo, ':id' => $id]
        );

        if ($ok < 0) {
            return $this->respuesta('simple', 'No se pudo guardar',
                'La base de datos rechazó el cambio.', 'error');
        }

        $this->auditar('inscripcion', $id, 'estado',
            ['estado' => $actual['estado_codigo']],
            ['estado' => $hacia],
            $motivo);

        return $this->respuesta('recargar', 'Estado actualizado',
            'La inscripción pasó a «' . $destino['estado_nombre'] . '».');
    }

    /*----------  Fixture y resultados  ----------*/

    public function generarFixtureAjax(): string
    {
        if (!puede_crear('categoriaPanel')) { return $this->denegado('generar el calendario'); }

        $fase      = (int)($_POST['fase_id'] ?? 0);
        $idaVuelta = ($_POST['ida_vuelta'] ?? '') === 'S';

        $r = $this->generarFixture($fase, $idaVuelta);

        return $this->respuesta(
            $r['ok'] ? 'recargar' : 'simple',
            $r['ok'] ? 'Calendario generado' : 'No se generó',
            $r['mensaje'],
            $r['ok'] ? 'success' : 'error'
        );
    }

    /** Partidos de una fase, con equipos y estado. */
    public function partidosDeFase(int $faseid): array
    {
        /* La cancha se lee de Arena con un LEFT JOIN: si la instalacion se
           diera de baja, el partido sigue apareciendo sin nombre de cancha
           en lugar de desaparecer del calendario. */
        return $this->filas(
            "SELECT P.*, J.jornada_numero,
                    L.equipo_nombre AS local, V.equipo_nombre AS visitante,
                    E.estado_codigo, E.estado_nombre, E.estado_tono, E.estado_efectivo,
                    CONCAT(A.instalacion_codigo, ' · ', A.instalacion_nombre) AS cancha
               FROM dsl_partido P
               JOIN dsl_jornada J     ON J.jornada_id = P.partido_jornadaid
               LEFT JOIN dsa_instalacion A ON A.instalacion_id = P.partido_instalacionid
               JOIN dsl_inscripcion IL ON IL.inscripcion_id = P.partido_localid
               JOIN dsl_equipo L      ON L.equipo_id = IL.inscripcion_equipoid
               JOIN dsl_inscripcion IV ON IV.inscripcion_id = P.partido_visitanteid
               JOIN dsl_equipo V      ON V.equipo_id = IV.inscripcion_equipoid
               JOIN dsl_estado E      ON E.estado_id = P.partido_estadoid
              WHERE P.partido_faseid = :f
              ORDER BY J.jornada_numero, P.partido_id",
            [':f' => $faseid]
        );
    }

    /**
     * Registra el marcador y da el partido por finalizado.
     *
     * El resultado y el estado se escriben JUNTOS a proposito: un partido
     * finalizado sin marcador, o un marcador en un partido que sigue
     * programado, son dos formas de que la clasificacion salga mal.
     */
    public function guardarResultado(): string
    {
        $id = (int)($_POST['partido_id'] ?? 0);

        /* ALCANCE POR FILA · D4
           ---------------------------------------------------------------
           Se acepta el resultado por una de dos vias:

             · permiso de gestion sobre la categoria —quien administra la
               competencia puede corregir cualquier acta—, o
             · estar DESIGNADO a este partido concreto.

           La comprobacion es del servidor y no de la pantalla: ocultar el
           formulario no impide un POST directo, y un arbitro que pudiera
           cambiar el marcador de un encuentro en el que no estuvo es
           exactamente el agujero que D4 existe para cerrar.

           En ningun punto se pregunta por el nombre del rol: se pregunta
           por un permiso y por un hecho deportivo verificable. */
        $gestiona  = puede_editar('categoriaPanel');
        $designado = $this->estaDesignado($id, usuario_actual_id() ?: 0);

        if (!$gestiona && !$designado) {
            return $this->denegado('registrar el resultado de este partido');
        }
        $pl = $_POST['puntos_local'] ?? '';
        $pv = $_POST['puntos_visitante'] ?? '';

        if ($pl === '' || $pv === '' || !ctype_digit((string)$pl) || !ctype_digit((string)$pv)) {
            return $this->respuesta('simple', 'Marcador incompleto',
                'Indique los puntos de ambos equipos.', 'error');
        }

        $pl = (int)$pl; $pv = (int)$pv;

        if ($pl === $pv) {
            return $this->respuesta('simple', 'Resultado empatado',
                'En baloncesto no hay empates. Revise el acta.', 'error');
        }

        $actual = $this->fila(
            "SELECT P.partido_id, P.partido_puntoslocal, P.partido_puntosvisitante,
                    P.partido_serieid, E.estado_codigo
               FROM dsl_partido P
               JOIN dsl_estado E ON E.estado_id = P.partido_estadoid
              WHERE P.partido_id = :id",
            [':id' => $id]
        );

        if (!$actual) {
            return $this->respuesta('simple', 'No encontrado', 'El partido no existe.', 'error');
        }

        /* Un partido finalizado no se reabre cargando otro marcador: para
           corregirlo hay que pasar por una transicion, que queda auditada. */
        if ($actual['estado_codigo'] === 'FINALIZADO') {
            return $this->respuesta('simple', 'Partido cerrado',
                'Ya está finalizado. Para corregir el marcador hay que reabrirlo primero.', 'error');
        }

        $destino = $this->estado('partido', 'FINALIZADO');

        /* Se llega a FINALIZADO desde EN_JUEGO. Si el partido sigue
           programado, se le hace pasar por los estados intermedios en vez
           de saltarselos: asi la transicion sigue siendo legal y el
           historial refleja lo que ocurrio. */
        $camino = [];
        foreach (['CONFIRMADO', 'EN_JUEGO', 'FINALIZADO'] as $paso) {
            if ($actual['estado_codigo'] === $paso) { $camino = []; continue; }
            $camino[] = $paso;
        }

        $desde = $actual['estado_codigo'];
        foreach ($camino as $paso) {
            if (!$this->transicionPermitida('partido', $desde, $paso)) {
                return $this->respuesta('simple', 'Movimiento no permitido',
                    'Desde «' . $desde . '» no se puede llegar a finalizado.', 'error');
            }
            $desde = $paso;
        }

        $ok = $this->escribir(
            "UPDATE dsl_partido SET partido_puntoslocal = :pl, partido_puntosvisitante = :pv,
                    partido_estadoid = :e WHERE partido_id = :id",
            [':pl' => $pl, ':pv' => $pv, ':e' => $destino['estado_id'], ':id' => $id]
        );

        if ($ok < 0) {
            return $this->respuesta('simple', 'No se pudo guardar',
                'La base de datos rechazó el cambio.', 'error');
        }

        $this->auditar('partido', $id, 'estado',
            ['estado' => $actual['estado_codigo'],
             'local'  => $actual['partido_puntoslocal'],
             'visitante' => $actual['partido_puntosvisitante']],
            ['estado' => 'FINALIZADO', 'local' => $pl, 'visitante' => $pv],
            'Registro de resultado');

        /* Si el partido pertenece a una eliminatoria, se recalcula. Puede
           haberla decidido, y entonces los encuentros que quedaban se
           cancelan y sus canchas se liberan en Arena. */
        $extra = '';
        $serieId = (int)($actual['partido_serieid'] ?? 0);

        if ($serieId > 0) {
            $r = (new playoffController())->resolverSerie($serieId);

            if (($r['ok'] ?? false) && ($r['ganador'] ?? null) !== null) {
                $extra = ' La serie queda decidida ('
                       . $r['local'] . '-' . $r['visitante'] . ')';
                $extra .= $r['cancelados'] > 0
                    ? ' y se cancelaron ' . $r['cancelados'] . ' partidos que ya no hacen falta.'
                    : '.';
            } elseif ($r['ok'] ?? false) {
                $extra = ' Serie ' . $r['local'] . '-' . $r['visitante']
                       . ' (se gana con ' . $r['umbral'] . ').';
            }
        }

        return $this->respuesta('recargar', 'Resultado registrado',
            'Marcador ' . $pl . ' – ' . $pv . '.' . $extra);
    }
    /*==================================================================
      Programacion de partidos · D2

      League no administra escenarios: los consume de Arena. Al confirmar
      un partido se escribe un bloqueo en la agenda de Arena, de modo que
      esa cancha deja de ofrecerse en alquiler a esa hora.

      Es la unica forma de que no existan dos calendarios sobre el mismo
      espacio fisico. Sin esto, el fallo aparece el dia que un cliente
      alquila la cancha 1 a las 10:00 y el generador programo ahi un
      partido de la misma jornada.
      ==================================================================*/

    protected function puente(): arenaPuente
    {
        static $p = null;
        if ($p === null) { $p = new arenaPuente(); }
        return $p;
    }

    /** Canchas que ofrece Arena, para el desplegable de programacion. */
    public function instalacionesDisponibles(): array
    {
        return $this->puente()->instalaciones();
    }

    /**
     * Fija fecha, hora y cancha de un partido, y reserva la franja.
     *
     * TODO O NADA
     *
     * La comprobacion de disponibilidad, la escritura del bloqueo y el
     * cambio de estado van en una transaccion. Si el bloqueo fallara
     * despues de haber programado el partido, quedaria un encuentro con
     * cancha asignada que Arena sigue ofreciendo en alquiler: exactamente
     * el doble uso que esto existe para impedir.
     */
    public function programarPartido(): string
    {
        if (!puede_editar('categoriaPanel')) { return $this->denegado('programar partidos'); }

        $id          = (int)($_POST['partido_id'] ?? 0);
        $instalacion = (int)($_POST['instalacion_id'] ?? 0);
        $fecha       = trim((string)($_POST['fecha'] ?? ''));
        $hora        = trim((string)($_POST['hora'] ?? ''));
        $duracion    = max(15, min(300, (int)($_POST['duracion'] ?? 90)));

        if ($fecha === '' || $hora === '') {
            return $this->respuesta('simple', 'Faltan datos',
                'Indique la fecha y la hora del partido.', 'error');
        }

        $partido = $this->fila(
            "SELECT P.*, E.estado_codigo,
                    L.equipo_nombre AS local, V.equipo_nombre AS visitante
               FROM dsl_partido P
               JOIN dsl_estado E       ON E.estado_id = P.partido_estadoid
               JOIN dsl_inscripcion IL ON IL.inscripcion_id = P.partido_localid
               JOIN dsl_equipo L       ON L.equipo_id = IL.inscripcion_equipoid
               JOIN dsl_inscripcion IV ON IV.inscripcion_id = P.partido_visitanteid
               JOIN dsl_equipo V       ON V.equipo_id = IV.inscripcion_equipoid
              WHERE P.partido_id = :id",
            [':id' => $id]
        );

        if (!$partido) {
            return $this->respuesta('simple', 'No encontrado', 'El partido no existe.', 'error');
        }

        /* Un partido ya jugado no se reprograma: para moverlo hay que
           pasar antes por una transicion, que queda auditada. */
        if (in_array($partido['estado_codigo'], ['FINALIZADO', 'WALKOVER', 'CANCELADO'], true)) {
            return $this->respuesta('simple', 'Partido cerrado',
                'Un partido en estado «' . $partido['estado_codigo'] . '» no se reprograma.', 'error');
        }

        $inicio = substr($hora, 0, 5) . ':00';
        $fin    = date('H:i:s', strtotime($fecha . ' ' . $inicio) + $duracion * 60);

        /* La regla de disponibilidad la pone Arena, no League. */
        $libre = $this->puente()->disponible($instalacion, $fecha, $inicio, $fin);

        if (!$libre['ok']) {
            return $this->respuesta('simple', 'Cancha no disponible', $libre['motivo'], 'error');
        }

        $confirmado = $this->estado('partido', 'CONFIRMADO');
        if (!$confirmado) {
            return $this->respuesta('simple', 'Catálogo incompleto',
                'Falta el estado CONFIRMADO.', 'error');
        }

        /* Solo se exige la transicion si el partido cambia de estado. Al
           reprogramar uno ya confirmado, sigue confirmado. */
        if ($partido['estado_codigo'] !== 'CONFIRMADO'
            && !$this->transicionPermitida('partido', $partido['estado_codigo'], 'CONFIRMADO')) {
            return $this->respuesta('simple', 'Movimiento no permitido',
                'Desde «' . $partido['estado_codigo'] . '» no se puede confirmar.', 'error');
        }

        $con = $this->conexion();
        if ($con === null) {
            return $this->respuesta('simple', 'Sin conexión',
                'No fue posible conectar con la base de datos.', 'error');
        }

        $propia = !$con->inTransaction();

        try {
            if ($propia) { $con->beginTransaction(); }

            /* Si ya tenía una franja reservada, se libera antes de tomar
               la nueva: si no, quedaría bloqueada una cancha que el
               partido ya no usa. */
            $anterior = (int)($partido['partido_bloqueoid'] ?? 0);
            if ($anterior > 0) {
                $this->puente()->liberarBloqueo($con, $anterior);
            }

            $rotulo  = $partido['local'] . ' vs ' . $partido['visitante'];
            $bloqueo = $this->puente()->bloquearParaPartido(
                $con, $id, $instalacion, $fecha, $inicio, $fin, $rotulo);

            if ($bloqueo < 0) {
                throw new \RuntimeException('No se pudo reservar la franja en Arena.');
            }

            $st = $con->prepare(
                "UPDATE dsl_partido
                    SET partido_instalacionid = :i, partido_fecha = :f, partido_hora = :h,
                        partido_duracion = :d, partido_bloqueoid = :b, partido_estadoid = :e
                  WHERE partido_id = :id"
            );
            $st->execute([':i' => $instalacion, ':f' => $fecha, ':h' => $inicio,
                          ':d' => $duracion, ':b' => $bloqueo,
                          ':e' => $confirmado['estado_id'], ':id' => $id]);

            if ($propia) { $con->commit(); }

        } catch (\Throwable $e) {
            if ($propia) {
                if ($con->inTransaction()) { $con->rollBack(); }
                return $this->respuesta('simple', 'No se pudo programar',
                    $e->getMessage(), 'error');
            }
            throw $e;
        }

        $this->auditar('partido', $id, 'reprogramar',
            ['fecha' => $partido['partido_fecha'], 'hora' => $partido['partido_hora'],
             'instalacion' => $partido['partido_instalacionid']],
            ['fecha' => $fecha, 'hora' => $inicio, 'instalacion' => $instalacion,
             'bloqueo_arena' => $bloqueo],
            'Programación · franja reservada en Arena');

        return $this->respuesta('recargar', 'Partido programado',
            'Queda confirmado el ' . $fecha . ' a las ' . substr($inicio, 0, 5)
            . '. La cancha queda bloqueada en Arena.');
    }

    /*==================================================================
      Designaciones y alcance por fila · D4

      El control de acceso del ecosistema llega hasta la accion sobre una
      vista, pero no hasta el registro: un arbitro con permiso de lectura
      sobre partidos veria toda la liga.

      COMO SE RESUELVE SIN ATAR NADA AL NOMBRE DEL ROL

      No se pregunta "es arbitro". Se separan dos vistas:

        · categoriaPanel  gestiona la competencia entera;
        · partidoAgenda   muestra los partidos de QUIEN LA ABRE.

      La segunda esta filtrada por designacion para todo el mundo, el
      administrador incluido: el alcance es intrinseco a lo que la
      pantalla ES, no una comprobacion de rol colada en la consulta. A un
      arbitro se le concede permiso solo sobre esa, y con eso queda
      limitado sin que ninguna linea de codigo mencione su rol.

      El servidor no se fia de que el boton este oculto: antes de aceptar
      un resultado comprueba o bien permiso de gestion, o bien designacion
      en ESE partido.
      ==================================================================*/

    /** Funciones que puede desempenar alguien en un partido. */
    public function funcionesDesignacion(): array
    {
        return [
            'A' => 'Árbitro principal',
            'X' => 'Árbitro auxiliar',
            'M' => 'Mesa de control',
            'C' => 'Comisario',
        ];
    }

    /** Designaciones de un partido. */
    public function designaciones(int $partidoid): array
    {
        return $this->filas(
            "SELECT D.*, U.usuario_usuario,
                    COALESCE(NULLIF(TRIM(P.empleado_nombre), ''), U.usuario_usuario) AS nombre
               FROM dsl_designacion D
               LEFT JOIN seguridad_usuario U ON U.usuario_id = D.designacion_usuarioid
               LEFT JOIN sujeto_empleado P   ON P.empleado_id = U.usuario_empleadoid
              WHERE D.designacion_partidoid = :p AND D.designacion_estado = 'A'
              ORDER BY D.designacion_funcion",
            [':p' => $partidoid]
        );
    }

    /**
     * Usuarios a los que se puede designar.
     *
     * Se ofrecen los que tienen acceso al modulo, sin mirar su rol: quien
     * decide a quien se designa es la organizacion, y atarlo a un nombre
     * de rol obligaria a tocar el codigo el dia que se llame de otra
     * forma.
     */
    public function designables(): array
    {
        return $this->filas(
            "SELECT U.usuario_id, U.usuario_usuario, R.rol_nombre,
                    COALESCE(NULLIF(TRIM(P.empleado_nombre), ''), U.usuario_usuario) AS nombre
               FROM seguridad_usuario U
               LEFT JOIN sujeto_empleado P ON P.empleado_id = U.usuario_empleadoid
               JOIN seguridad_rol_modulo M ON M.rolmod_rolid = U.usuario_rolid
                                          AND M.rolmod_modulo = :mod
                                          AND M.rolmod_estado = 'A'
               LEFT JOIN seguridad_rol R ON R.rol_id = U.usuario_rolid
              WHERE U.usuario_estado = 'A'
              ORDER BY nombre",
            [':mod' => DS_MODULO]
        );
    }

    /**
     * Comprueba si un usuario esta designado a un partido.
     *
     * Es la pieza que hace efectivo D4: no se consulta el rol, se
     * consulta un hecho deportivo verificable y auditable.
     */
    public function estaDesignado(int $partidoid, int $usuarioid): bool
    {
        if ($usuarioid <= 0) { return false; }

        return (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_designacion
              WHERE designacion_partidoid = :p
                AND designacion_usuarioid = :u
                AND designacion_estado    = 'A'",
            [':p' => $partidoid, ':u' => $usuarioid]
        ) > 0;
    }

    public function guardarDesignacion(): string
    {
        if (!puede_editar('categoriaPanel')) { return $this->denegado('designar'); }

        $partido = (int)($_POST['partido_id'] ?? 0);
        $usuario = (int)($_POST['usuario_id'] ?? 0);
        $funcion = strtoupper(substr(trim((string)($_POST['funcion'] ?? 'A')), 0, 1));

        if ($partido <= 0 || $usuario <= 0) {
            return $this->respuesta('simple', 'Faltan datos',
                'Seleccione el partido y la persona.', 'error');
        }
        if (!array_key_exists($funcion, $this->funcionesDesignacion())) {
            return $this->respuesta('simple', 'Función no válida',
                'Elija una función del catálogo.', 'error');
        }

        /* Conflicto de interes: nadie arbitra un partido de su propio
           equipo.

           Se comprueba contra la PERSONA designada, cuando se indica.
           Cruzar por numero de identificacion entre seguridad_usuario y
           dsl_persona seria adivinar: un arbitro no tiene por que estar
           dado de alta como empleado, y un cruce que falla en silencio es
           peor que no comprobar, porque da una seguridad que no existe.
           Sin persona vinculada, el conflicto no se puede detectar
           automaticamente y queda a criterio de quien designa. */
        $persona = (int)($_POST['persona_id'] ?? 0);

        $conflicto = $persona > 0 ? (int)$this->escalar(
            "SELECT COUNT(*)
               FROM dsl_partido P
               JOIN dsl_plantilla PL
                 ON PL.plantilla_inscripcionid IN (P.partido_localid, P.partido_visitanteid)
              WHERE P.partido_id = :p
                AND PL.plantilla_personaid = :pe
                AND PL.plantilla_baja IS NULL",
            [':p' => $partido, ':pe' => $persona]
        ) : 0;

        if ($conflicto > 0) {
            return $this->respuesta('simple', 'Conflicto de interés',
                'Esa persona figura en la plantilla de uno de los dos equipos.', 'error');
        }

        $ok = $this->escribir(
            "INSERT INTO dsl_designacion
                    (designacion_partidoid, designacion_usuarioid, designacion_personaid,
                     designacion_funcion, designacion_usuarioreg)
             VALUES (:p, :u, :pe, :f, :r)",
            [':p' => $partido, ':u' => $usuario, ':pe' => $persona ?: null,
             ':f' => $funcion, ':r' => usuario_actual_id() ?: null]
        );

        if ($ok < 0) {
            return $this->respuesta('simple', 'Designación repetida',
                'Esa persona ya tiene esa función en este partido.', 'error');
        }

        $this->auditar('partido', $partido, 'editar', null,
            ['designado' => $usuario, 'funcion' => $funcion], 'Designación');

        return $this->respuesta('recargar', 'Designación registrada',
            'Queda asignada la función en este partido.');
    }

    public function eliminarDesignacion(): string
    {
        if (!puede_eliminar('categoriaPanel')) { return $this->denegado('retirar designaciones'); }

        $id = (int)($_POST['designacion_id'] ?? 0);

        $d = $this->fila(
            "SELECT designacion_partidoid, designacion_usuarioid, designacion_funcion
               FROM dsl_designacion WHERE designacion_id = :id",
            [':id' => $id]
        );

        if (!$d) {
            return $this->respuesta('simple', 'No encontrada',
                'La designación no existe.', 'error');
        }

        $this->escribir("DELETE FROM dsl_designacion WHERE designacion_id = :id", [':id' => $id]);

        $this->auditar('partido', (int)$d['designacion_partidoid'], 'editar',
            ['designado' => $d['designacion_usuarioid'], 'funcion' => $d['designacion_funcion']],
            null, 'Designación retirada');

        return $this->respuesta('recargar', 'Designación retirada', 'Ya no figura en el partido.');
    }

    /**
     * Partidos de QUIEN CONSULTA.
     *
     * Filtrado por designacion, siempre y para cualquiera. No hay un
     * parametro para "ver los de otro": la vista es la agenda propia, y
     * quien necesite verlo todo usa la pantalla de gestion, que es otra y
     * exige otro permiso.
     */
    public function misPartidos(int $limite = 60): array
    {
        $usuario = usuario_actual_id() ?: 0;
        if ($usuario <= 0) { return []; }

        $limite = max(1, min(200, $limite));

        return $this->filas(
            "SELECT P.partido_id, P.partido_fecha, P.partido_hora,
                    P.partido_puntoslocal, P.partido_puntosvisitante,
                    D.designacion_funcion,
                    L.equipo_nombre AS local, V.equipo_nombre AS visitante,
                    C.categoria_nombre, T.torneo_nombre,
                    E.estado_codigo, E.estado_nombre, E.estado_tono, E.estado_efectivo,
                    CONCAT(A.instalacion_codigo, ' · ', A.instalacion_nombre) AS cancha
               FROM dsl_designacion D
               JOIN dsl_partido P      ON P.partido_id = D.designacion_partidoid
               JOIN dsl_fase F         ON F.fase_id = P.partido_faseid
               JOIN dsl_categoria C    ON C.categoria_id = F.fase_categoriaid
               JOIN dsl_torneo T       ON T.torneo_id = C.categoria_torneoid
               JOIN dsl_inscripcion IL ON IL.inscripcion_id = P.partido_localid
               JOIN dsl_equipo L       ON L.equipo_id = IL.inscripcion_equipoid
               JOIN dsl_inscripcion IV ON IV.inscripcion_id = P.partido_visitanteid
               JOIN dsl_equipo V       ON V.equipo_id = IV.inscripcion_equipoid
               JOIN dsl_estado E       ON E.estado_id = P.partido_estadoid
               LEFT JOIN dsa_instalacion A ON A.instalacion_id = P.partido_instalacionid
              WHERE D.designacion_usuarioid = :u
                AND D.designacion_estado    = 'A'
              ORDER BY P.partido_fecha IS NULL, P.partido_fecha, P.partido_hora
              LIMIT $limite",
            [':u' => $usuario]
        );
    }
    /*==================================================================
      Plantillas

      Quien pertenece a un equipo inscrito, en que papel y ENTRE QUE
      FECHAS. Las fechas son lo que permite responder "¿estaba habilitado
      el 12 de mayo?" con exactitud despues de una transferencia; con un
      simple activo/inactivo, mover a un jugador reescribiria quien podia
      jugar en partidos ya disputados.

      PROTECCION DE DATOS

      Buena parte de estas personas son menores. Se guarda lo minimo para
      competir: identificacion, nombre, fecha de nacimiento —que es lo que
      decide la elegibilidad por edad— y foto para el carne. Los datos de
      contacto viven en el equipo, porque la organizacion se comunica con
      el delegado y no con cada jugador.
      ==================================================================*/

    public function rolesPlantilla(): array
    {
        return ['J' => 'Jugador', 'E' => 'Entrenador', 'A' => 'Asistente', 'D' => 'Delegado'];
    }

    /**
     * Una inscripcion con el equipo y la categoria a la que pertenece.
     *
     * La pantalla de plantilla necesita las reglas de la categoria —rango
     * de edad, fecha de corte, genero, minimo de habilitados— para poder
     * decir por que alguien no puede ser habilitado. Se traen de una vez
     * en lugar de con cuatro consultas encadenadas.
     */
    public function inscripcionConContexto(int $inscripcionid): array
    {
        return $this->fila(
            "SELECT I.inscripcion_id, I.inscripcion_estadoid,
                    Q.equipo_id, Q.equipo_nombre, Q.equipo_corto, Q.equipo_escudo,
                    C.categoria_id, C.categoria_nombre, C.categoria_torneoid,
                    C.categoria_genero, C.categoria_edadmin, C.categoria_edadmax,
                    C.categoria_fechacorte, C.categoria_maxplantilla,
                    C.categoria_minhabilitados,
                    T.torneo_nombre,
                    E.estado_codigo, E.estado_nombre, E.estado_tono
               FROM dsl_inscripcion I
               JOIN dsl_equipo    Q ON Q.equipo_id    = I.inscripcion_equipoid
               JOIN dsl_categoria C ON C.categoria_id = I.inscripcion_categoriaid
               JOIN dsl_torneo    T ON T.torneo_id    = C.categoria_torneoid
               JOIN dsl_estado    E ON E.estado_id    = I.inscripcion_estadoid
              WHERE I.inscripcion_id = :i",
            [':i' => $inscripcionid]
        );
    }

    /**
     * Plantilla de una inscripcion.
     *
     * La edad se calcula a la FECHA DE CORTE de la categoria, no a hoy:
     * es la unica forma de que "Sub-14" signifique siempre lo mismo. Si la
     * categoria no tiene corte, se usa la fecha actual y la vista lo
     * advierte.
     */
    public function plantilla(int $inscripcionid, bool $incluirBajas = false): array
    {
        $sql = "SELECT PL.*, PE.persona_id, PE.persona_identificacion, PE.persona_nombres,
                       PE.persona_apellidos, PE.persona_fechanac, PE.persona_genero,
                       PE.persona_foto, PE.persona_alumnoid,
                       PE.persona_publicarfoto, PE.persona_consentfecha,
                       TIMESTAMPDIFF(YEAR, PE.persona_fechanac,
                           COALESCE(C.categoria_fechacorte, CURDATE())) AS edad,
                       C.categoria_edadmin, C.categoria_edadmax, C.categoria_genero,
                       C.categoria_fechacorte
                  FROM dsl_plantilla PL
                  JOIN dsl_persona     PE ON PE.persona_id = PL.plantilla_personaid
                  JOIN dsl_inscripcion I  ON I.inscripcion_id = PL.plantilla_inscripcionid
                  JOIN dsl_categoria   C  ON C.categoria_id = I.inscripcion_categoriaid
                 WHERE PL.plantilla_inscripcionid = :i";

        if (!$incluirBajas) {
            $sql .= " AND PL.plantilla_baja IS NULL";
        }

        return $this->filas($sql . " ORDER BY PL.plantilla_rol, PL.plantilla_dorsal,
                                             PE.persona_apellidos", [':i' => $inscripcionid]);
    }

    /**
     * Motivos por los que una persona NO puede ser habilitada.
     *
     * Devuelve la lista completa en vez del primero: al revisar
     * documentacion conviene ver todo lo que falta de una vez, no
     * descubrirlo de uno en uno.
     */
    public function motivosNoHabilitable(array $fila): array
    {
        $faltas = [];

        if (empty($fila['persona_fechanac'])) {
            $faltas[] = 'sin fecha de nacimiento';
        } else {
            $edad = (int)$fila['edad'];
            $min  = $fila['categoria_edadmin'];
            $max  = $fila['categoria_edadmax'];

            if ($min !== null && $edad < (int)$min) { $faltas[] = "edad {$edad} menor que el mínimo {$min}"; }
            if ($max !== null && $edad > (int)$max) { $faltas[] = "edad {$edad} mayor que el máximo {$max}"; }
        }

        /* La categoria mixta ('X') no restringe genero. */
        $gCat = (string)($fila['categoria_genero'] ?? 'X');
        $gPer = (string)($fila['persona_genero'] ?? 'X');
        if ($gCat !== 'X' && $gPer !== 'X' && $gCat !== $gPer) {
            $faltas[] = 'género distinto al de la categoría';
        }

        if (trim((string)$fila['persona_identificacion']) === '') {
            $faltas[] = 'sin número de identificación';
        }

        return $faltas;
    }

    /** Carpeta y URL de las fotos de personas. */
    public static function fotosDir(): string { return dirname(__DIR__) . '/assets/img/personas/'; }
    public static function fotosUrl(): string { return APP_URL . 'assets/img/personas/'; }

    /**
     * Alta o edicion de una persona.
     *
     * Si la identificacion ya existe se REUTILIZA la ficha en lugar de
     * crear otra: la misma persona puede jugar en dos categorias o
     * cambiar de club, y duplicarla haria imposible detectar que es la
     * misma.
     */
    public function guardarPersona(): string
    {
        if (!puede_crear('plantillaPanel') && !puede_editar('plantillaPanel')) {
            return $this->denegado('registrar personas');
        }

        $id     = (int)($_POST['persona_id'] ?? 0);
        $ident  = trim((string)($_POST['persona_identificacion'] ?? ''));
        $nombres = trim((string)($_POST['persona_nombres'] ?? ''));
        $apell  = trim((string)($_POST['persona_apellidos'] ?? ''));
        $nac    = trim((string)($_POST['persona_fechanac'] ?? ''));
        $genero = strtoupper(substr(trim((string)($_POST['persona_genero'] ?? 'X')), 0, 1));
        $quitar = ($_POST['quitar_foto'] ?? '') === '1';

        if ($ident === '' || $nombres === '' || $apell === '') {
            return $this->respuesta('simple', 'Faltan datos',
                'La identificación, los nombres y los apellidos son obligatorios.', 'error');
        }
        if (!in_array($genero, ['M', 'F', 'X'], true)) {
            return $this->respuesta('simple', 'Género no válido', 'Revise el género.', 'error');
        }
        if ($nac !== '' && $nac > date('Y-m-d')) {
            return $this->respuesta('simple', 'Fecha imposible',
                'La fecha de nacimiento no puede ser futura.', 'error');
        }

        /* Si esa identificacion ya existe, se edita esa ficha. */
        if ($id === 0) {
            $existe = (int)$this->escalar(
                "SELECT persona_id FROM dsl_persona WHERE persona_identificacion = :i",
                [':i' => $ident]
            );
            if ($existe > 0) { $id = $existe; }
        }

        $antes = $id > 0
            ? $this->fila("SELECT * FROM dsl_persona WHERE persona_id = :id", [':id' => $id])
            : null;

        $foto = ds_imagen_resolver('persona_foto', self::fotosDir(), 'persona',
                                   (string)($antes['persona_foto'] ?? ''), $quitar);

        if ($foto !== '' && $foto[0] === '!') {
            return $this->respuesta('simple', 'Foto no válida', substr($foto, 1), 'error');
        }

        $sql = $id > 0
            ? "UPDATE dsl_persona SET persona_identificacion = :i, persona_nombres = :n,
                      persona_apellidos = :a, persona_fechanac = :f, persona_genero = :g,
                      persona_foto = :fo
                WHERE persona_id = :id"
            : "INSERT INTO dsl_persona (persona_identificacion, persona_nombres,
                      persona_apellidos, persona_fechanac, persona_genero, persona_foto)
               VALUES (:i, :n, :a, :f, :g, :fo)";

        $par = [':i' => $ident, ':n' => $nombres, ':a' => $apell,
                ':f' => $nac !== '' ? $nac : null, ':g' => $genero,
                ':fo' => $foto !== '' ? $foto : null];
        if ($id > 0) { $par[':id'] = $id; }

        $nuevoId = $this->escribir($sql, $par);

        if ($nuevoId < 0) {
            if ($foto !== '' && $foto !== ($antes['persona_foto'] ?? '')) {
                ds_imagen_borrar(self::fotosDir(), $foto);
            }
            return $this->respuesta('simple', 'No se pudo guardar',
                'Ya existe otra persona con esa identificación.', 'error');
        }

        $this->auditar('persona', $nuevoId, $id > 0 ? 'editar' : 'crear', $antes,
            ['identificacion' => $ident, 'nombres' => $nombres, 'apellidos' => $apell]);

        /* Si venia con inscripcion, se incorpora a la plantilla de una vez:
           registrar a alguien y luego tener que buscarlo para anadirlo es
           un paso de mas en el flujo habitual. */
        $inscripcion = (int)($_POST['inscripcion_id'] ?? 0);
        if ($inscripcion > 0) {
            $this->escribir(
                "INSERT IGNORE INTO dsl_plantilla
                        (plantilla_inscripcionid, plantilla_personaid, plantilla_rol,
                         plantilla_dorsal, plantilla_alta, plantilla_usuarioid)
                 VALUES (:i, :p, :r, :d, CURDATE(), :u)",
                [':i' => $inscripcion, ':p' => $nuevoId,
                 ':r' => strtoupper(substr((string)($_POST['plantilla_rol'] ?? 'J'), 0, 1)),
                 ':d' => ($_POST['plantilla_dorsal'] ?? '') !== ''
                         ? (int)$_POST['plantilla_dorsal'] : null,
                 ':u' => usuario_actual_id() ?: null]
            );
        }

        return $this->respuesta('recargar', 'Persona guardada',
            $apell . ' ' . $nombres . ' quedó registrada.');
    }

    /**
     * Registra o retira la autorizacion para publicar la fotografia.
     *
     * POR QUE ES UNA ACCION APARTE
     *
     * Podria ser una casilla mas en el formulario de la persona, pero
     * entonces se marcaria de pasada al corregir un apellido. Autorizar la
     * publicacion de la imagen de un menor en un sitio abierto es una
     * decision con consecuencias legales, y merece su propio gesto y su
     * propio registro de quien y cuando.
     *
     * Se guarda la fecha y el usuario porque un consentimiento sin
     * trazabilidad no sirve para demostrar que se obtuvo, que es
     * justamente para lo que se pide.
     */
    public function consentimientoImagen(): string
    {
        if (!puede_editar('plantillaPanel')) {
            return $this->denegado('registrar autorizaciones de imagen');
        }

        $id       = (int)($_POST['persona_id'] ?? 0);
        $autoriza = ($_POST['autoriza'] ?? '') === 'S';

        $persona = $this->fila(
            "SELECT persona_id, persona_nombres, persona_apellidos,
                    persona_foto, persona_publicarfoto
               FROM dsl_persona WHERE persona_id = :id",
            [':id' => $id]
        );

        if (!$persona) {
            return $this->respuesta('simple', 'No encontrada',
                'Esa persona no existe.', 'error');
        }

        /* Autorizar la publicacion de una foto que no existe no significa
           nada, y deja el registro diciendo que hay algo publicable donde
           no lo hay. */
        if ($autoriza && empty($persona['persona_foto'])) {
            return $this->respuesta('simple', 'No hay fotografía',
                'Esta persona no tiene fotografía cargada, así que no hay nada '
                . 'que autorizar. Suba primero la imagen.', 'error');
        }

        $ok = $this->escribir(
            "UPDATE dsl_persona
                SET persona_publicarfoto   = :p,
                    persona_consentfecha   = :f,
                    persona_consentusuario = :u
              WHERE persona_id = :id",
            [':p'  => $autoriza ? 'S' : 'N',
             ':f'  => $autoriza ? date('Y-m-d H:i:s') : null,
             ':u'  => $autoriza ? (usuario_actual_id() ?: null) : null,
             ':id' => $id]
        );

        if ($ok < 0) {
            return $this->respuesta('simple', 'No se pudo guardar',
                'La base de datos rechazó el cambio.', 'error');
        }

        $this->auditar('persona', $id, 'editar',
            ['publicarfoto' => $persona['persona_publicarfoto']],
            ['publicarfoto' => $autoriza ? 'S' : 'N'],
            $autoriza
                ? 'Autorización de imagen registrada'
                : 'Autorización de imagen retirada');

        return $this->respuesta('recargar',
            $autoriza ? 'Autorización registrada' : 'Autorización retirada',
            $persona['persona_apellidos'] . ' ' . $persona['persona_nombres'] . ': '
            . ($autoriza
                ? 'su fotografía podrá mostrarse en el portal público.'
                : 'su fotografía deja de mostrarse en el portal público.'));
    }

    /**
     * Habilita o inhabilita a alguien de la plantilla.
     *
     * Habilitar comprueba los requisitos y se NIEGA si no se cumplen: es
     * la regla que el encargo pedia —«no permitir la habilitacion de
     * jugadores que incumplan requisitos obligatorios»— y tiene que estar
     * en el servidor, no en el color de un boton.
     */
    public function habilitarPlantilla(): string
    {
        if (!puede_editar('plantillaPanel')) { return $this->denegado('habilitar jugadores'); }

        $id      = (int)($_POST['plantilla_id'] ?? 0);
        $activar = ($_POST['habilitar'] ?? '') === 'S';

        $fila = $this->fila(
            "SELECT PL.plantilla_id, PL.plantilla_inscripcionid, PL.plantilla_habilitado,
                    PE.persona_identificacion, PE.persona_nombres, PE.persona_apellidos,
                    PE.persona_fechanac, PE.persona_genero,
                    TIMESTAMPDIFF(YEAR, PE.persona_fechanac,
                        COALESCE(C.categoria_fechacorte, CURDATE())) AS edad,
                    C.categoria_edadmin, C.categoria_edadmax, C.categoria_genero
               FROM dsl_plantilla PL
               JOIN dsl_persona     PE ON PE.persona_id = PL.plantilla_personaid
               JOIN dsl_inscripcion I  ON I.inscripcion_id = PL.plantilla_inscripcionid
               JOIN dsl_categoria   C  ON C.categoria_id = I.inscripcion_categoriaid
              WHERE PL.plantilla_id = :id",
            [':id' => $id]
        );

        if (!$fila) {
            return $this->respuesta('simple', 'No encontrado',
                'Esa línea de plantilla no existe.', 'error');
        }

        if ($activar) {
            $faltas = $this->motivosNoHabilitable($fila);

            if ($faltas) {
                return $this->respuesta('simple', 'No cumple los requisitos',
                    ucfirst(implode('; ', $faltas)) . '.', 'error');
            }
        }

        $this->escribir(
            "UPDATE dsl_plantilla SET plantilla_habilitado = :h, plantilla_motivo = :m
              WHERE plantilla_id = :id",
            [':h' => $activar ? 'S' : 'N',
             ':m' => trim((string)($_POST['motivo'] ?? '')),
             ':id' => $id]
        );

        $this->auditar('plantilla', $id, 'estado',
            ['habilitado' => $fila['plantilla_habilitado']],
            ['habilitado' => $activar ? 'S' : 'N'],
            trim((string)($_POST['motivo'] ?? '')));

        return $this->respuesta('recargar',
            $activar ? 'Jugador habilitado' : 'Habilitación retirada',
            $fila['persona_apellidos'] . ' ' . $fila['persona_nombres']);
    }

    /**
     * Da de baja a alguien de la plantilla.
     *
     * Se marca la FECHA DE BAJA en vez de borrar la fila: quien jugo un
     * partido lo jugo, y borrarlo dejaria actas apuntando a nadie.
     */
    public function bajaPlantilla(): string
    {
        if (!puede_eliminar('plantillaPanel')) { return $this->denegado('dar de baja'); }

        $id = (int)($_POST['plantilla_id'] ?? 0);

        $fila = $this->fila(
            "SELECT PL.*, PE.persona_nombres, PE.persona_apellidos
               FROM dsl_plantilla PL
               JOIN dsl_persona PE ON PE.persona_id = PL.plantilla_personaid
              WHERE PL.plantilla_id = :id",
            [':id' => $id]
        );

        if (!$fila) {
            return $this->respuesta('simple', 'No encontrado', 'No existe.', 'error');
        }
        if ($fila['plantilla_baja'] !== null) {
            return $this->respuesta('simple', 'Ya estaba de baja',
                'Esa persona ya no figura en la plantilla.', 'error');
        }

        $this->escribir(
            "UPDATE dsl_plantilla SET plantilla_baja = CURDATE(), plantilla_habilitado = 'N',
                    plantilla_motivo = :m WHERE plantilla_id = :id",
            [':m' => trim((string)($_POST['motivo'] ?? '')), ':id' => $id]
        );

        $this->auditar('plantilla', $id, 'eliminar',
            ['alta' => $fila['plantilla_alta'], 'baja' => null],
            ['baja' => date('Y-m-d')],
            trim((string)($_POST['motivo'] ?? '')));

        return $this->respuesta('recargar', 'Baja registrada',
            $fila['persona_apellidos'] . ' ' . $fila['persona_nombres']
            . ' deja la plantilla. Los partidos ya jugados conservan su alineación.');
    }
    /*==================================================================
      Acceso a la conexion para las transacciones

      leagueController mantiene privados sus helpers a proposito; aqui
      hace falta la conexion desnuda para envolver el fixture en una
      transaccion, asi que se expone solo eso.
      ==================================================================*/

    protected function conexion(): ?PDO
    {
        return seguridad_conexion();
    }
}
