<?php

namespace league\controllers;

/**
 * Motor de sorteos.
 *
 * REPRODUCIBILIDAD
 *
 * Todo el azar sale de una semilla que se guarda con el sorteo. Con la
 * misma semilla, la misma configuracion y la misma lista de equipos, el
 * resultado es identico. Eso convierte el registro en algo que un tercero
 * puede verificar, en vez de en un acta que hay que creerse.
 *
 * El barajado se implementa aqui —Fisher-Yates sobre mt_rand()— y NO se
 * usa shuffle(). Dos razones:
 *
 *   · shuffle() es una caja negra cuya implementacion puede cambiar entre
 *     versiones de PHP, y entonces un sorteo de 2026 dejaria de
 *     reproducirse en 2029;
 *   · escrito aqui, el algoritmo se puede leer y comprobar, que es de lo
 *     que trata guardar la semilla.
 */
class sorteoController extends competenciaController
{
    /*==================================================================
      Azar reproducible
      ==================================================================*/

    /**
     * Baraja una lista con la semilla dada.
     *
     * Fisher-Yates: se recorre de atras hacia delante intercambiando cada
     * posicion con otra elegida entre las que quedan. Es el barajado sin
     * sesgo; hacerlo al reves —o elegir sobre todo el rango— favorece unas
     * permutaciones sobre otras.
     *
     * No se llama a mt_srand() aqui: quien ejecuta el sorteo lo hace UNA
     * vez al principio, de modo que todas las extracciones vienen de la
     * misma secuencia. Sembrar en cada barajado daria el mismo orden a
     * todos los bombos.
     */
    private function barajar(array $lista): array
    {
        $n = count($lista);

        for ($i = $n - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            if ($j !== $i) {
                $tmp = $lista[$i];
                $lista[$i] = $lista[$j];
                $lista[$j] = $tmp;
            }
        }

        return $lista;
    }

    /** Semilla nueva, criptograficamente aleatoria. */
    public function semillaNueva(): int
    {
        /* random_int y no mt_rand: la semilla es lo unico que NO debe ser
           predecible. Si alguien pudiera adivinarla, podria calcular el
           resultado del sorteo antes de que se celebre. */
        return random_int(1, PHP_INT_MAX);
    }

    /*==================================================================
      El reparto

      Se recorren los bombos en orden. Dentro de cada uno se baraja y se
      va colocando en los grupos, buscando siempre el que tenga menos
      equipos para que queden parejos.

      Las cabezas de serie salen solas de este esquema: si estan en el
      bombo 1 y hay tantas como grupos, cada una cae en un grupo distinto
      sin necesidad de una regla aparte.
      ==================================================================*/

    /**
     * Calcula el reparto. NO escribe nada.
     *
     * Separarlo de la escritura permite previsualizar el sorteo antes de
     * aplicarlo, que es como se hace en una sala con los delegados
     * delante.
     *
     * @param array $bombos     [numero => [inscripcionid, ...]] en orden de entrada
     * @param int   $grupos     cuantos grupos formar
     * @param array $restringe  parejas [a, b] que no pueden coincidir
     * @return array ['ok'=>bool,'motivo'=>string,'grupos'=>[[insId,...],...]]
     */
    public function repartir(array $bombos, int $grupos, int $semilla, array $restringe = []): array
    {
        if ($grupos < 1) {
            return ['ok' => false, 'motivo' => 'Debe haber al menos un grupo.', 'grupos' => []];
        }

        $total = 0;
        foreach ($bombos as $b) { $total += count($b); }

        if ($total < $grupos) {
            return ['ok' => false, 'grupos' => [],
                    'motivo' => "Hay {$total} equipos para {$grupos} grupos: sobran grupos."];
        }

        /* Índice rápido de restricciones, en ambos sentidos. */
        $veta = [];
        foreach ($restringe as [$a, $b]) {
            $veta[(int)$a][(int)$b] = true;
            $veta[(int)$b][(int)$a] = true;
        }

        mt_srand($semilla, MT_RAND_MT19937);

        $resultado = array_fill(0, $grupos, []);

        ksort($bombos);   // el orden de los bombos es parte de la configuración

        foreach ($bombos as $equipos) {
            foreach ($this->barajar(array_values($equipos)) as $ins) {
                $destino = $this->grupoParaEquipo($resultado, (int)$ins, $veta);

                if ($destino === -1) {
                    return ['ok' => false, 'grupos' => [],
                            'motivo' => 'Las restricciones no se pueden cumplir con esta '
                                      . 'configuración: no queda ningún grupo válido para '
                                      . 'un equipo. Reduzca las restricciones o cambie el '
                                      . 'número de grupos.'];
                }

                $resultado[$destino][] = (int)$ins;
            }
        }

        return ['ok' => true, 'motivo' => '', 'grupos' => $resultado];
    }

    /**
     * Elige grupo para un equipo: el menos poblado entre los que no
     * violan una restriccion.
     *
     * Devuelve -1 si ninguno sirve. Se prefiere fallar a colocar mal: un
     * sorteo que incumple sus propias restricciones es peor que uno que
     * no se puede hacer, porque el segundo se ve y el primero no.
     */
    private function grupoParaEquipo(array $grupos, int $ins, array $veta): int
    {
        $mejor = -1;
        $menos = PHP_INT_MAX;

        foreach ($grupos as $i => $miembros) {
            $choca = false;
            foreach ($miembros as $m) {
                if (isset($veta[$ins][$m])) { $choca = true; break; }
            }
            if ($choca) { continue; }

            if (count($miembros) < $menos) {
                $menos = count($miembros);
                $mejor = $i;
            }
        }

        return $mejor;
    }

    /*==================================================================
      Ejecucion y persistencia
      ==================================================================*/

    /** Sorteos ya celebrados de una fase, del mas reciente al mas antiguo. */
    public function sorteosDeFase(int $faseid): array
    {
        return $this->filas(
            "SELECT S.*,
                    (SELECT COUNT(*) FROM dsl_sorteo_resultado R
                      WHERE R.resultado_sorteoid = S.sorteo_id) AS equipos
               FROM dsl_sorteo S
              WHERE S.sorteo_faseid = :f
              ORDER BY S.sorteo_fecha DESC",
            [':f' => $faseid]
        );
    }

    /** Resultado de un sorteo, agrupado y en orden. */
    public function resultadoSorteo(int $sorteoid): array
    {
        return $this->filas(
            "SELECT R.*, Q.equipo_nombre, Q.equipo_escudo
               FROM dsl_sorteo_resultado R
               JOIN dsl_inscripcion I ON I.inscripcion_id = R.resultado_inscripcionid
               JOIN dsl_equipo      Q ON Q.equipo_id      = I.inscripcion_equipoid
              WHERE R.resultado_sorteoid = :s
              ORDER BY R.resultado_grupo, R.resultado_posicion",
            [':s' => $sorteoid]
        );
    }

    /**
     * Ejecuta el sorteo y lo aplica: crea los grupos y coloca los equipos.
     *
     * TODO O NADA
     *
     * La escritura va en transaccion. Un sorteo aplicado a medias dejaria
     * unos equipos con grupo y otros sin el, y el fixture posterior
     * saldria mal sin que nada avisara.
     *
     * Se NIEGA si la fase ya tiene partidos: cambiar los grupos con el
     * calendario hecho invalidaria los encuentros ya programados. Rehacer
     * el sorteo exige borrar antes el fixture, y eso es una decision del
     * usuario.
     */
    public function ejecutarSorteo(): string
    {
        if (!puede_crear('sorteoPanel')) { return $this->denegado('ejecutar sorteos'); }

        $faseid  = (int)($_POST['fase_id'] ?? 0);
        $grupos  = max(1, min(32, (int)($_POST['grupos'] ?? 2)));
        $cabezas = array_values(array_filter(array_map('intval',
                        (array)($_POST['cabezas'] ?? []))));
        $observa = trim((string)($_POST['observacion'] ?? ''));

        /* Una semilla dada a mano permite REPRODUCIR un sorteo anterior.
           Es la funcion que hace verificable el registro. */
        $semilla = ($_POST['semilla'] ?? '') !== ''
                 ? (int)$_POST['semilla']
                 : $this->semillaNueva();

        $fase = $this->fila(
            "SELECT F.*, C.categoria_id, C.categoria_nombre
               FROM dsl_fase F
               JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
              WHERE F.fase_id = :f",
            [':f' => $faseid]
        );

        if (!$fase) {
            return $this->respuesta('simple', 'Fase no encontrada',
                'La fase indicada no existe.', 'error');
        }

        $conPartidos = (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_partido WHERE partido_faseid = :f", [':f' => $faseid]);

        if ($conPartidos > 0) {
            return $this->respuesta('simple', 'La fase ya tiene calendario',
                "Hay {$conPartidos} partidos generados. Cambiar los grupos ahora dejaría "
                . 'encuentros programados que ya no corresponden. Elimine el calendario '
                . 'antes de volver a sortear.', 'error');
        }

        $equipos = $this->equiposDeCategoria((int)$fase['categoria_id']);

        if (count($equipos) < 2) {
            return $this->respuesta('simple', 'Faltan equipos',
                'Hacen falta al menos dos equipos habilitados.', 'error');
        }

        /* Bombos: el 1 lleva las cabezas de serie; el 2, el resto. El
           orden dentro de cada bombo es el de inscripcion, que es estable
           y por tanto reproducible. */
        $bombos = [1 => [], 2 => []];
        foreach ($equipos as $e) {
            $id = (int)$e['inscripcion_id'];
            $bombos[in_array($id, $cabezas, true) ? 1 : 2][] = $id;
        }
        if (!$bombos[1]) { unset($bombos[1]); }

        /* Restricciones enviadas como pares "a-b". */
        $restringe = [];
        foreach ((array)($_POST['restricciones'] ?? []) as $par) {
            $p = array_map('intval', explode('-', (string)$par));
            if (count($p) === 2 && $p[0] > 0 && $p[1] > 0 && $p[0] !== $p[1]) {
                $restringe[] = [min($p), max($p)];
            }
        }

        $reparto = $this->repartir($bombos, $grupos, $semilla, $restringe);

        if (!$reparto['ok']) {
            return $this->respuesta('simple', 'No se pudo sortear', $reparto['motivo'], 'error');
        }

        $con = $this->conexion();
        if ($con === null) {
            return $this->respuesta('simple', 'Sin conexión', 'No hay base de datos.', 'error');
        }

        $propia = !$con->inTransaction();

        try {
            if ($propia) { $con->beginTransaction(); }

            /* Los grupos anteriores se retiran: el sorteo los redefine.
               No hay partidos —se comprobo arriba—, asi que nada queda
               apuntando a ellos. */
            $con->prepare("DELETE FROM dsl_grupo WHERE grupo_faseid = :f")
                ->execute([':f' => $faseid]);

            $stSorteo = $con->prepare(
                "INSERT INTO dsl_sorteo (sorteo_faseid, sorteo_semilla, sorteo_config,
                        sorteo_estado, sorteo_observacion, sorteo_usuarioid, sorteo_usuario)
                 VALUES (:f, :s, :c, 'APLICADO', :o, :u, :un)"
            );

            $config = [
                'grupos'   => $grupos,
                'cabezas'  => $cabezas,
                'bombos'   => $bombos,
                'restricciones' => $restringe,
                'algoritmo' => 'fisher-yates/mt19937',
            ];

            $stSorteo->execute([
                ':f' => $faseid, ':s' => $semilla,
                ':c' => json_encode($config, JSON_UNESCAPED_UNICODE),
                ':o' => $observa,
                ':u' => usuario_actual_id() ?: null,
                ':un' => substr(ds_nombre_usuario(), 0, 20),
            ]);

            $sorteoId = (int)$con->lastInsertId();

            /* Los bombos, tal como entraron. */
            $stBombo = $con->prepare(
                "INSERT INTO dsl_sorteo_bombo (bombo_sorteoid, bombo_inscripcionid,
                        bombo_numero, bombo_orden) VALUES (:s, :i, :b, :o)");
            foreach ($bombos as $num => $lista) {
                foreach ($lista as $orden => $ins) {
                    $stBombo->execute([':s' => $sorteoId, ':i' => $ins,
                                       ':b' => $num, ':o' => $orden]);
                }
            }

            $stRestr = $con->prepare(
                "INSERT INTO dsl_sorteo_restriccion (restriccion_sorteoid,
                        restriccion_menorid, restriccion_mayorid) VALUES (:s, :a, :b)");
            foreach ($restringe as [$a, $b]) {
                $stRestr->execute([':s' => $sorteoId, ':a' => $a, ':b' => $b]);
            }

            /* Grupos y resultado. */
            $stGrupo = $con->prepare(
                "INSERT INTO dsl_grupo (grupo_faseid, grupo_nombre, grupo_orden)
                 VALUES (:f, :n, :o)");
            $stMiembro = $con->prepare(
                "INSERT INTO dsl_grupo_equipo (ge_grupoid, ge_inscripcionid, ge_orden)
                 VALUES (:g, :i, :o)");
            $stRes = $con->prepare(
                "INSERT INTO dsl_sorteo_resultado (resultado_sorteoid, resultado_inscripcionid,
                        resultado_grupoid, resultado_grupo, resultado_posicion, resultado_bombo)
                 VALUES (:s, :i, :g, :gn, :p, :b)");

            $deBombo = [];
            foreach ($bombos as $num => $lista) {
                foreach ($lista as $ins) { $deBombo[$ins] = $num; }
            }

            foreach ($reparto['grupos'] as $i => $miembros) {
                $nombre = 'Grupo ' . chr(65 + $i);
                $stGrupo->execute([':f' => $faseid, ':n' => $nombre, ':o' => $i + 1]);
                $grupoId = (int)$con->lastInsertId();

                foreach ($miembros as $pos => $ins) {
                    $stRes->execute([':s' => $sorteoId, ':i' => $ins, ':g' => $grupoId,
                                     ':gn' => $nombre, ':p' => $pos + 1,
                                     ':b' => $deBombo[$ins] ?? 1]);

                    /* Se escribe en los dos sitios a proposito: el resultado
                       del sorteo es el acta historica —lo que paso aquel dia—
                       y dsl_grupo_equipo es el estado actual, que es lo que
                       consultan la clasificacion y el fixture. */
                    $stMiembro->execute([':g' => $grupoId, ':i' => $ins, ':o' => $pos + 1]);
                }
            }

            if ($propia) { $con->commit(); }

        } catch (\Throwable $e) {
            if ($propia) {
                if ($con->inTransaction()) { $con->rollBack(); }
                return $this->respuesta('simple', 'No se pudo aplicar el sorteo',
                    $e->getMessage(), 'error');
            }
            throw $e;
        }

        $this->auditar('fase', $faseid, 'sortear', null,
            ['sorteo' => $sorteoId, 'semilla' => $semilla, 'grupos' => $grupos,
             'cabezas' => $cabezas, 'restricciones' => count($restringe)],
            $observa !== '' ? $observa : 'Sorteo de grupos');

        return $this->respuesta('recargar', 'Sorteo realizado',
            'Se formaron ' . $grupos . ' grupos. La semilla ' . $semilla
            . ' queda guardada: con ella se puede reproducir este mismo sorteo.');
    }
}
