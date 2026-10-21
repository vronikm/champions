<?php

namespace league\controllers;

/**
 * Estadisticas.
 *
 * Los tipos viven en un catalogo (dsl_estadistica_tipo) y los datos en
 * una tabla estrecha (partido, persona, tipo, valor). Es lo que permite
 * que un segundo deporte sea una fila y no un cambio de esquema.
 *
 * LAS FORMULAS SE EVALUAN, NO SE EJECUTAN
 *
 * Los puntos, los rebotes totales y la valoracion no se teclean: se
 * calculan de lo capturado, con una formula guardada en el catalogo. Eso
 * exige interpretarla, y ahi hay una tentacion peligrosa: eval().
 *
 * eval() sobre un texto que vive en la base de datos convierte cualquier
 * escritura en esa tabla en ejecucion de codigo. Aunque hoy solo escriba
 * el administrador, es exactamente la clase de puerta que se olvida
 * abierta. Aqui se tokeniza, se comprueba que TODO simbolo sea conocido y
 * se evalua en notacion polaca inversa: lo que no encaja, no se ejecuta,
 * se rechaza.
 */
class estadisticaController extends competenciaController
{
    /*==================================================================
      Catalogo
      ==================================================================*/

    /** Tipos de un deporte, en orden de acta. */
    public function tipos(string $deporte = 'baloncesto', bool $soloCaptura = false): array
    {
        $sql = "SELECT * FROM dsl_estadistica_tipo
                 WHERE tipo_deporte = :d AND tipo_activo = 'S'";

        if ($soloCaptura) { $sql .= " AND tipo_captura = 'S'"; }

        return $this->filas($sql . " ORDER BY tipo_orden", [':d' => $deporte]);
    }

    /** Tipos indexados por codigo, para resolver formulas. */
    public function tiposPorCodigo(string $deporte = 'baloncesto'): array
    {
        $mapa = [];
        foreach ($this->tipos($deporte) as $t) { $mapa[$t['tipo_codigo']] = $t; }
        return $mapa;
    }

    /*==================================================================
      Evaluador de formulas
      ==================================================================*/

    /**
     * Convierte una formula en una lista de simbolos, rechazando todo lo
     * que no sea codigo, numero, operador o parentesis.
     *
     * Es la barrera: si algo no casa con el patron, la formula se
     * descarta entera en vez de intentar interpretarla a medias.
     *
     * @return array|null null si la formula tiene algo desconocido
     */
    private function tokenizar(string $formula): ?array
    {
        $tokens = [];
        $resto  = trim($formula);

        while ($resto !== '') {
            if (preg_match('/^\s+/', $resto, $m)) {
                $resto = substr($resto, strlen($m[0]));
                continue;
            }
            if (preg_match('/^[A-Z][A-Z0-9]{0,9}/', $resto, $m)) {
                $tokens[] = ['codigo', $m[0]];
            } elseif (preg_match('/^\d+(?:\.\d+)?/', $resto, $m)) {
                $tokens[] = ['numero', (float)$m[0]];
            } elseif (preg_match('/^[+\-*\/()]/', $resto, $m)) {
                $tokens[] = ['simbolo', $m[0]];
            } else {
                return null;   // simbolo desconocido: no se interpreta nada
            }
            $resto = substr($resto, strlen($m[0]));
        }

        return $tokens;
    }

    /**
     * Valor de un codigo para un jugador en un partido.
     *
     * Si el tipo se captura, se devuelve lo registrado. Si es derivado, se
     * evalua su formula, que a su vez puede referirse a otras derivadas
     * —VAL usa PTS, y PTS usa los tiros—.
     *
     * $visitando corta los ciclos: una formula que se referencie a si
     * misma, directa o indirectamente, colgaria el proceso. Se devuelve 0
     * y se sigue, en vez de reventar la pantalla entera del acta.
     */
    public function valorDe(string $codigo, array $capturados, array $tipos,
                            array $visitando = []): float
    {
        if (isset($visitando[$codigo])) { return 0.0; }   // ciclo
        if (!isset($tipos[$codigo]))    { return 0.0; }   // codigo inexistente

        $tipo = $tipos[$codigo];

        if (empty($tipo['tipo_formula'])) {
            return (float)($capturados[$codigo] ?? 0);
        }

        $visitando[$codigo] = true;

        $tokens = $this->tokenizar((string)$tipo['tipo_formula']);
        if ($tokens === null) { return 0.0; }

        /* Los codigos se sustituyen por su valor ANTES de evaluar, de modo
           que la evaluacion solo ve numeros y operadores. */
        $rpn = $this->aPolacaInversa($tokens);
        if ($rpn === null) { return 0.0; }

        $pila = [];

        foreach ($rpn as [$clase, $valor]) {
            if ($clase === 'numero') {
                $pila[] = (float)$valor;
            } elseif ($clase === 'codigo') {
                $pila[] = $this->valorDe((string)$valor, $capturados, $tipos, $visitando);
            } else {
                $b = array_pop($pila);
                $a = array_pop($pila);
                if ($a === null || $b === null) { return 0.0; }

                $pila[] = match ($valor) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    /* Division por cero: se devuelve 0 en vez de dejar
                       INF, que despues se imprime como "INF" en el acta. */
                    '/' => abs($b) < 1e-9 ? 0.0 : $a / $b,
                    default => 0.0,
                };
            }
        }

        return count($pila) === 1 ? (float)$pila[0] : 0.0;
    }

    /**
     * Comprueba que la secuencia de simbolos forme una expresion.
     *
     * Que todos los simbolos sean conocidos NO basta: «PTS ** 2» y
     * «PTS +» estan hechos de simbolos validos y no son expresiones. Sin
     * esta comprobacion pasaban el filtro y luego se evaluaban a un numero
     * cualquiera —lo que es peor que un error, porque el acta mostraria
     * una cifra plausible y equivocada sin avisar—.
     *
     * La regla es simple: se alternan operandos y operadores. Un operando
     * es un numero, un codigo o un parentesis abierto; despues de uno
     * cerrado se espera operador.
     */
    private function secuenciaValida(array $tokens): bool
    {
        if (!$tokens) { return false; }

        $esperaOperando = true;
        $abiertos = 0;

        foreach ($tokens as [$clase, $valor]) {
            if ($clase === 'numero' || $clase === 'codigo') {
                if (!$esperaOperando) { return false; }
                $esperaOperando = false;
                continue;
            }

            if ($valor === '(') {
                if (!$esperaOperando) { return false; }
                $abiertos++;
                continue;
            }

            if ($valor === ')') {
                /* Un parentesis cerrado tiene que venir despues de un
                   operando: «(3 + )» no es nada. */
                if ($esperaOperando || $abiertos === 0) { return false; }
                $abiertos--;
                continue;
            }

            /* Operador: sólo puede seguir a un operando. Con esto «**»
               —que se tokeniza como dos por separado— queda descartado. */
            if ($esperaOperando) { return false; }
            $esperaOperando = true;
        }

        /* Al terminar no puede quedarse esperando un operando —«PTS +»— ni
           con parentesis sin cerrar. */
        return !$esperaOperando && $abiertos === 0;
    }

    /**
     * Paso de infija a polaca inversa (algoritmo de la playa de maniobras).
     *
     * Devuelve null si los parentesis no casan: una formula mal escrita se
     * rechaza entera, no se evalua a medias.
     */
    private function aPolacaInversa(array $tokens): ?array
    {
        if (!$this->secuenciaValida($tokens)) { return null; }

        $prioridad = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        $salida = [];
        $ops    = [];

        foreach ($tokens as [$clase, $valor]) {
            if ($clase === 'numero' || $clase === 'codigo') {
                $salida[] = [$clase, $valor];
                continue;
            }

            if ($valor === '(') { $ops[] = $valor; continue; }

            if ($valor === ')') {
                while ($ops && end($ops) !== '(') { $salida[] = ['simbolo', array_pop($ops)]; }
                if (!$ops) { return null; }      // parentesis sin abrir
                array_pop($ops);
                continue;
            }

            while ($ops && end($ops) !== '('
                   && ($prioridad[end($ops)] ?? 0) >= ($prioridad[$valor] ?? 0)) {
                $salida[] = ['simbolo', array_pop($ops)];
            }
            $ops[] = $valor;
        }

        while ($ops) {
            $op = array_pop($ops);
            if ($op === '(') { return null; }    // parentesis sin cerrar
            $salida[] = ['simbolo', $op];
        }

        return $salida;
    }

    /** Comprueba que una formula sea evaluable. Para validar el catalogo. */
    public function formulaValida(string $formula, array $tipos): bool
    {
        $tokens = $this->tokenizar($formula);
        if ($tokens === null) { return false; }

        foreach ($tokens as [$clase, $valor]) {
            if ($clase === 'codigo' && !isset($tipos[$valor])) { return false; }
        }

        return $this->aPolacaInversa($tokens) !== null;
    }

    /*==================================================================
      Acta del partido
      ==================================================================*/

    /**
     * Un partido con todo lo que el acta necesita para pintarse.
     *
     * Se trae de una consulta en vez de encadenar cinco: la pantalla del
     * acta se abre en la mesa, muchas veces desde un movil con mala
     * conexion, y cada ida y vuelta se nota.
     */
    public function partidoConContexto(int $partidoid): array
    {
        return $this->fila(
            "SELECT P.*,
                    IL.inscripcion_id AS ins_local, IV.inscripcion_id AS ins_visitante,
                    L.equipo_nombre AS local,     L.equipo_escudo AS escudo_local,
                    V.equipo_nombre AS visitante, V.equipo_escudo AS escudo_visitante,
                    C.categoria_id, C.categoria_nombre, C.categoria_torneoid,
                    T.torneo_nombre, T.torneo_deporte,
                    E.estado_codigo, E.estado_nombre, E.estado_tono, E.estado_efectivo,
                    E.estado_admiteacta,
                    CONCAT(A.instalacion_codigo, ' · ', A.instalacion_nombre) AS cancha
               FROM dsl_partido P
               JOIN dsl_fase F         ON F.fase_id = P.partido_faseid
               JOIN dsl_categoria C    ON C.categoria_id = F.fase_categoriaid
               JOIN dsl_torneo T       ON T.torneo_id = C.categoria_torneoid
               JOIN dsl_inscripcion IL ON IL.inscripcion_id = P.partido_localid
               JOIN dsl_equipo L       ON L.equipo_id = IL.inscripcion_equipoid
               JOIN dsl_inscripcion IV ON IV.inscripcion_id = P.partido_visitanteid
               JOIN dsl_equipo V       ON V.equipo_id = IV.inscripcion_equipoid
               JOIN dsl_estado E       ON E.estado_id = P.partido_estadoid
               LEFT JOIN dsa_instalacion A ON A.instalacion_id = P.partido_instalacionid
              WHERE P.partido_id = :p",
            [':p' => $partidoid]
        );
    }

    /**
     * Jugadores habilitados de un equipo, con lo ya cargado en el acta.
     *
     * Solo los HABILITADOS: cargar estadisticas de quien no podia jugar es
     * justo lo que se impugna despues, y la pantalla no debe ofrecerlo.
     */
    public function convocables(int $inscripcionid, int $partidoid): array
    {
        $filas = $this->filas(
            "SELECT PL.plantilla_id, PL.plantilla_dorsal, PL.plantilla_rol,
                    PE.persona_id, PE.persona_nombres, PE.persona_apellidos,
                    PE.persona_identificacion, PE.persona_foto
               FROM dsl_plantilla PL
               JOIN dsl_persona PE ON PE.persona_id = PL.plantilla_personaid
              WHERE PL.plantilla_inscripcionid = :i
                AND PL.plantilla_baja IS NULL
                AND PL.plantilla_habilitado = 'S'
                AND PL.plantilla_rol = 'J'
              ORDER BY PL.plantilla_dorsal, PE.persona_apellidos",
            [':i' => $inscripcionid]
        );

        /* Lo ya cargado, para rellenar el formulario. */
        $cargado = [];
        foreach ($this->filas(
            "SELECT S.stat_personaid, T.tipo_codigo, S.stat_valor
               FROM dsl_partido_stat S
               JOIN dsl_estadistica_tipo T ON T.tipo_id = S.stat_tipoid
              WHERE S.stat_partidoid = :p AND S.stat_inscripcionid = :i",
            [':p' => $partidoid, ':i' => $inscripcionid]) as $s) {
            $cargado[(int)$s['stat_personaid']][$s['tipo_codigo']] = (float)$s['stat_valor'];
        }

        foreach ($filas as &$f) {
            $f['stats'] = $cargado[(int)$f['persona_id']] ?? [];
        }
        unset($f);

        return $filas;
    }

    /**
     * Box score: una fila por jugador con todos los tipos resueltos.
     *
     * Aqui se paga el coste de la tabla estrecha: lo que en un esquema de
     * once columnas seria un SELECT, aqui es un pivote. Se hace en PHP y
     * no en SQL para no escribir una consulta con un CASE por tipo, que
     * habria que tocar cada vez que se anade uno —justo lo que el catalogo
     * viene a evitar—.
     */
    public function acta(int $partidoid, string $deporte = 'baloncesto'): array
    {
        $tipos = $this->tiposPorCodigo($deporte);

        $filas = $this->filas(
            "SELECT S.stat_personaid, S.stat_inscripcionid, T.tipo_codigo, S.stat_valor,
                    P.persona_nombres, P.persona_apellidos, P.persona_identificacion,
                    PL.plantilla_dorsal, Q.equipo_nombre
               FROM dsl_partido_stat S
               JOIN dsl_estadistica_tipo T ON T.tipo_id = S.stat_tipoid
               JOIN dsl_persona P          ON P.persona_id = S.stat_personaid
               JOIN dsl_inscripcion I      ON I.inscripcion_id = S.stat_inscripcionid
               JOIN dsl_equipo Q           ON Q.equipo_id = I.inscripcion_equipoid
               LEFT JOIN dsl_plantilla PL  ON PL.plantilla_inscripcionid = S.stat_inscripcionid
                                          AND PL.plantilla_personaid     = S.stat_personaid
                                          AND PL.plantilla_baja IS NULL
              WHERE S.stat_partidoid = :p
              ORDER BY Q.equipo_nombre, PL.plantilla_dorsal, P.persona_apellidos",
            [':p' => $partidoid]
        );

        /* Agrupado por jugador. */
        $jugadores = [];
        foreach ($filas as $f) {
            $k = (int)$f['stat_personaid'];

            if (!isset($jugadores[$k])) {
                $jugadores[$k] = [
                    'persona_id'   => $k,
                    'inscripcion'  => (int)$f['stat_inscripcionid'],
                    'equipo'       => $f['equipo_nombre'],
                    'dorsal'       => $f['plantilla_dorsal'],
                    'nombre'       => $f['persona_apellidos'] . ' ' . $f['persona_nombres'],
                    'identificacion' => $f['persona_identificacion'],
                    'capturado'    => [],
                    'valores'      => [],
                ];
            }

            $jugadores[$k]['capturado'][$f['tipo_codigo']] = (float)$f['stat_valor'];
        }

        /* Y ahora se resuelven TODOS los tipos, incluidos los derivados. */
        foreach ($jugadores as &$j) {
            foreach ($tipos as $codigo => $t) {
                $j['valores'][$codigo] = $this->valorDe($codigo, $j['capturado'], $tipos);
            }
        }
        unset($j);

        return array_values($jugadores);
    }

    /** Totales del acta por equipo. */
    public function totalesActa(array $acta, array $tipos): array
    {
        $totales = [];

        foreach ($acta as $j) {
            $eq = $j['equipo'];
            if (!isset($totales[$eq])) {
                $totales[$eq] = array_fill_keys(array_keys($tipos), 0.0);
                $totales[$eq]['_jugadores'] = 0;
            }
            $totales[$eq]['_jugadores']++;

            foreach ($tipos as $codigo => $t) {
                if ($t['tipo_agregable'] === 'S') {
                    $totales[$eq][$codigo] += $j['valores'][$codigo] ?? 0;
                }
            }
        }

        return $totales;
    }

    /*==================================================================
      Rankings
      ==================================================================*/

    /**
     * Lideres de una estadistica en un torneo.
     *
     * Para un tipo capturado es un SUM sobre el indice (tipo, persona):
     * la consulta rapida para la que se diseno la tabla.
     *
     * Para uno derivado no se puede sumar en SQL —la formula vive en el
     * catalogo—, asi que se traen los capturados por jugador y se resuelve
     * en PHP. Es mas caro y por eso conviene saberlo: un ranking de
     * valoracion cuesta mas que uno de rebotes.
     */
    public function lideres(int $torneoid, string $codigo, int $limite = 10,
                            string $deporte = 'baloncesto'): array
    {
        $tipos = $this->tiposPorCodigo($deporte);
        if (!isset($tipos[$codigo])) { return []; }

        $limite = max(1, min(100, $limite));
        $tipo   = $tipos[$codigo];

        /* Solo cuentan los partidos que surtieron efecto. */
        $base = "FROM dsl_partido_stat S
                 JOIN dsl_partido P   ON P.partido_id = S.stat_partidoid
                 JOIN dsl_estado  E   ON E.estado_id  = P.partido_estadoid
                 JOIN dsl_fase    F   ON F.fase_id    = P.partido_faseid
                 JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
                 JOIN dsl_estadistica_tipo T ON T.tipo_id = S.stat_tipoid
                 JOIN dsl_persona PE  ON PE.persona_id = S.stat_personaid
                 JOIN dsl_inscripcion I ON I.inscripcion_id = S.stat_inscripcionid
                 JOIN dsl_equipo  Q   ON Q.equipo_id = I.inscripcion_equipoid
                WHERE C.categoria_torneoid = :t
                  AND E.estado_efectivo = 'S'";

        if (empty($tipo['tipo_formula'])) {
            return $this->filas(
                "SELECT PE.persona_id, PE.persona_nombres, PE.persona_apellidos,
                        Q.equipo_nombre,
                        SUM(S.stat_valor) AS total,
                        COUNT(DISTINCT S.stat_partidoid) AS partidos,
                        ROUND(SUM(S.stat_valor) / COUNT(DISTINCT S.stat_partidoid), 2) AS promedio
                 $base AND T.tipo_codigo = :c
                 GROUP BY PE.persona_id, PE.persona_nombres, PE.persona_apellidos, Q.equipo_nombre
                 ORDER BY total DESC
                 LIMIT $limite",
                [':t' => $torneoid, ':c' => $codigo]
            );
        }

        /* Derivada: se traen todos los capturados y se resuelve por
           jugador. La formula no se puede trasladar a SQL sin generar la
           consulta desde el catalogo, y generar SQL desde datos es lo que
           esta arquitectura evita en todas partes. */
        $crudo = $this->filas(
            "SELECT PE.persona_id, PE.persona_nombres, PE.persona_apellidos,
                    Q.equipo_nombre, T.tipo_codigo, S.stat_partidoid,
                    SUM(S.stat_valor) AS total
             $base
             GROUP BY PE.persona_id, PE.persona_nombres, PE.persona_apellidos,
                      Q.equipo_nombre, T.tipo_codigo, S.stat_partidoid",
            [':t' => $torneoid]
        );

        $porJugador = [];
        foreach ($crudo as $r) {
            $k = (int)$r['persona_id'];
            if (!isset($porJugador[$k])) {
                $porJugador[$k] = [
                    'persona_id' => $k,
                    'persona_nombres'   => $r['persona_nombres'],
                    'persona_apellidos' => $r['persona_apellidos'],
                    'equipo_nombre'     => $r['equipo_nombre'],
                    'capturado' => [], 'partidos' => [],
                ];
            }
            $c = $r['tipo_codigo'];
            $porJugador[$k]['capturado'][$c] = ($porJugador[$k]['capturado'][$c] ?? 0) + (float)$r['total'];
            $porJugador[$k]['partidos'][$r['stat_partidoid']] = true;
        }

        $salida = [];
        foreach ($porJugador as $j) {
            $n = count($j['partidos']);
            $total = $this->valorDe($codigo, $j['capturado'], $tipos);
            $salida[] = [
                'persona_id'        => $j['persona_id'],
                'persona_nombres'   => $j['persona_nombres'],
                'persona_apellidos' => $j['persona_apellidos'],
                'equipo_nombre'     => $j['equipo_nombre'],
                'total'             => $total,
                'partidos'          => $n,
                'promedio'          => $n > 0 ? round($total / $n, 2) : 0,
            ];
        }

        usort($salida, fn($a, $b) => $b['total'] <=> $a['total']);

        return array_slice($salida, 0, $limite);
    }

    /*==================================================================
      Captura
      ==================================================================*/

    /**
     * Guarda el acta de un jugador en un partido.
     *
     * TODO O NADA por jugador: si una linea falla, no se deja media
     * cargada, porque un acta con la mitad de los tiros da porcentajes
     * absurdos y nadie sabe que falta.
     *
     * Solo se aceptan los tipos de CAPTURA: intentar mandar PTS se ignora,
     * porque los puntos salen de los tiros y admitir ambos permitiria que
     * el acta se contradijera.
     */
    public function guardarActa(): string
    {
        $partido = (int)($_POST['partido_id'] ?? 0);
        $persona = (int)($_POST['persona_id'] ?? 0);
        $inscrip = (int)($_POST['inscripcion_id'] ?? 0);
        $datos   = (array)($_POST['stat'] ?? []);

        if ($partido <= 0 || $persona <= 0 || $inscrip <= 0) {
            return $this->respuesta('simple', 'Faltan datos',
                'Indique el partido y el jugador.', 'error');
        }

        /* ALCANCE POR FILA · D4
           ---------------------------------------------------------------
           puede_editar('actaPartido') NO basta: el rol de árbitro tiene esa
           acción concedida para poder cargar el acta de SUS partidos, y con
           sólo esa comprobación podría cargar la de cualquiera.

           Se exige una de dos cosas: permiso de gestión sobre la categoría,
           o estar designado a ESTE partido. Es la misma regla que
           guardarResultado(), y va aquí porque el servidor no puede fiarse
           de que la pantalla no se haya abierto a mano. */
        $gestiona  = puede_editar('categoriaPanel');
        $designado = $this->estaDesignado($partido, usuario_actual_id() ?: 0);

        if (!$gestiona && !$designado) {
            return $this->denegado('cargar el acta de este partido');
        }

        /* EL ESTADO DEL PARTIDO MANDA, Y LO DICE EL CATALOGO
           ---------------------------------------------------------------
           Esta comprobacion vivia SOLO en la vista, que ocultaba los campos
           cuando el partido estaba cancelado. Pero el acta se guarda por
           fetch, campo a campo: repitiendo la peticion se cargaban
           estadisticas en un partido cancelado. Medido antes de corregirlo.

           La regla no se escribe aqui: se pregunta a dsl_estado. Asi el dia
           que la liga decida que un partido SUSPENDIDO tampoco admite carga
           se cambia una fila y no se despliega codigo. Ver migracion 064. */
        $admite = (string)$this->escalar(
            "SELECT E.estado_admiteacta
               FROM dsl_partido P
               JOIN dsl_estado  E ON E.estado_id = P.partido_estadoid
              WHERE P.partido_id = :p",
            [':p' => $partido]
        );

        if ($admite === '') {
            return $this->respuesta('simple', 'Partido inexistente',
                'Ese partido no está en el sistema.', 'error');
        }

        if ($admite !== 'S') {
            return $this->respuesta('simple', 'Acta cerrada',
                'El estado del partido no admite carga de estadísticas.', 'error');
        }

        /* El jugador tiene que estar en la plantilla de ese equipo y sin
           baja. Cargar estadisticas de alguien que no jugaba es
           exactamente lo que se impugna despues. */
        $enPlantilla = (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_plantilla
              WHERE plantilla_inscripcionid = :i AND plantilla_personaid = :p
                AND plantilla_baja IS NULL",
            [':i' => $inscrip, ':p' => $persona]
        );

        if ($enPlantilla === 0) {
            return $this->respuesta('simple', 'No está en la plantilla',
                'Esa persona no figura en la plantilla vigente del equipo.', 'error');
        }

        $tipos = $this->tiposPorCodigo();
        $con   = $this->conexion();

        if ($con === null) {
            return $this->respuesta('simple', 'Sin conexión', 'No hay base de datos.', 'error');
        }

        $propia = !$con->inTransaction();

        try {
            if ($propia) { $con->beginTransaction(); }

            $st = $con->prepare(
                "INSERT INTO dsl_partido_stat
                        (stat_partidoid, stat_personaid, stat_inscripcionid, stat_tipoid, stat_valor)
                 VALUES (:pa, :pe, :in, :ti, :va)
                 ON DUPLICATE KEY UPDATE stat_valor = VALUES(stat_valor)");

            $guardados = 0;

            foreach ($datos as $codigo => $valor) {
                $codigo = strtoupper(trim((string)$codigo));

                if (!isset($tipos[$codigo]))              { continue; }
                if ($tipos[$codigo]['tipo_captura'] !== 'S') { continue; }

                $v = (float)str_replace(',', '.', (string)$valor);
                if ($v < 0) {
                    throw new \RuntimeException('Los valores no pueden ser negativos.');
                }

                $st->execute([':pa' => $partido, ':pe' => $persona, ':in' => $inscrip,
                              ':ti' => $tipos[$codigo]['tipo_id'], ':va' => $v]);
                $guardados++;
            }

            /* Coherencia: no se puede anotar mas de lo que se intenta. */
            $lee = static fn($c) => (float)str_replace(',', '.', (string)($datos[$c] ?? 0));
            foreach ([['T1A','T1I','tiros libres'], ['T2A','T2I','tiros de dos'],
                      ['T3A','T3I','triples']] as [$a, $i, $etiqueta]) {
                if ($lee($a) > $lee($i)) {
                    throw new \RuntimeException(
                        "Hay más {$etiqueta} anotados que intentados.");
                }
            }

            if ($propia) { $con->commit(); }

        } catch (\Throwable $e) {
            if ($propia) {
                if ($con->inTransaction()) { $con->rollBack(); }
                return $this->respuesta('simple', 'Acta no válida', $e->getMessage(), 'error');
            }
            throw $e;
        }

        $this->auditar('partido', $partido, 'editar', null,
            ['persona' => $persona, 'estadisticas' => $guardados], 'Carga de acta');

        /*
        | LA RESPUESTA NO PIDE RECARGAR
        |
        | Devolvía 'recargar', y con eso cada línea guardada volvía a pedir la
        | página entera. En una mesa de anotación eso es inaceptable: se anota
        | sobre la marcha, línea a línea, y recargar pierde el foco, cierra el
        | teclado del teléfono y devuelve al principio de la tabla.
        |
        | Ahora devuelve lo que la pantalla necesita para pintarse sola: las
        | derivadas de ESE jugador y los totales de su equipo.
        |
        | LAS DERIVADAS LAS CALCULA EL SERVIDOR, NO EL NAVEGADOR
        |
        | La fórmula de PTS, REB y VAL vive en dsl_estadistica_tipo. Repetirla
        | en JavaScript sería una segunda copia que el día que alguien cambie
        | la valoración se quedaría atrás, y la pantalla mostraría un número
        | que la base no reconoce. Se recalcula aquí y se manda ya resuelto.
        */
        $tipos    = $this->tiposPorCodigo();
        $acta     = $this->acta($partido);
        $totales  = $this->totalesActa($acta, $tipos);

        /* La línea de este jugador dentro del acta recién leída. */
        $derivadas = [];
        $equipo    = '';
        foreach ($acta as $j) {
            if ((int)$j['persona_id'] !== $persona) { continue; }
            $equipo = $j['equipo'];
            foreach ($tipos as $codigo => $t) {
                if ($t['tipo_captura'] === 'N') {
                    $derivadas[$codigo] = (int)round($j['valores'][$codigo] ?? 0);
                }
            }
            break;
        }

        /* Sólo los códigos que se pintan en el pie, ya redondeados. */
        $totalEquipo = [];
        if ($equipo !== '' && isset($totales[$equipo])) {
            foreach ($tipos as $codigo => $t) {
                $totalEquipo[$codigo] = (int)round($totales[$equipo][$codigo] ?? 0);
            }
        }

        return json_encode([
            'tipo'       => 'acta',
            'icono'      => 'success',
            'titulo'     => 'Línea guardada',
            'texto'      => $guardados . ' estadísticas registradas.',
            'persona'    => $persona,
            'equipo'     => $equipo,
            'derivadas'  => $derivadas,
            'totales'    => $totalEquipo,
            'puntosActa' => array_map(
                static fn($t) => (int)round($t['PTS'] ?? 0), $totales),
        ], JSON_UNESCAPED_UNICODE);
    }
}
