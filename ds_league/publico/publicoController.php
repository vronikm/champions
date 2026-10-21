<?php

namespace league\publico;

use PDO;

/**
 * Datos del portal público.
 *
 * NO EXTIENDE NINGUN CONTROLADOR DE ADMINISTRACION, A PROPOSITO
 *
 * Esta es la unica superficie de League sin autenticacion. Heredar de
 * competenciaController traeria consigo guardarResultado(), inscribir(),
 * habilitarPlantilla()… metodos publicos que, aunque el front controller
 * no los invoque, quedarian a un descuido de distancia de ser alcanzables.
 * Una clase aparte con acceso de solo lectura hace que ese camino no
 * exista.
 *
 * QUE NO SALE DE AQUI
 *
 * Ninguna consulta selecciona persona_identificacion ni
 * persona_fechanac. No es una omision que haya que recordar: es la
 * defensa. Un filtro se olvida; una columna que no esta en el SELECT no
 * se puede filtrar mal.
 *
 * Y todo cuelga de torneo_publico = 'S'. Un torneo que nadie publico no
 * existe para el portal, ni siquiera conociendo su identificador.
 */
class publicoController
{
    /*==================================================================
      Acceso a datos · solo lectura
      ==================================================================*/

    private function filas(string $sql, array $par = []): array
    {
        try {
            $con = seguridad_conexion();
            if ($con === null) { return []; }
            $st = $con->prepare($sql);
            $st->execute($par);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fila(string $sql, array $par = []): array
    {
        return $this->filas($sql, $par)[0] ?? [];
    }

    /*==================================================================
      Torneos publicados
      ==================================================================*/

    /** Torneos abiertos al público, del más reciente al más antiguo. */
    public function torneos(): array
    {
        return $this->filas(
            "SELECT T.torneo_id, T.torneo_nombre, T.torneo_slug, T.torneo_deporte,
                    S.temporada_nombre, S.temporada_desde, S.temporada_hasta,
                    (SELECT COUNT(*) FROM dsl_categoria C
                      WHERE C.categoria_torneoid = T.torneo_id
                        AND C.categoria_estado = 'A') AS categorias
               FROM dsl_torneo T
               JOIN dsl_temporada S ON S.temporada_id = T.torneo_temporadaid
              WHERE T.torneo_publico = 'S' AND T.torneo_estado = 'A'
              ORDER BY S.temporada_desde DESC, T.torneo_nombre"
        );
    }

    /**
     * Un torneo por su slug.
     *
     * Se busca por slug y no por id para que la URL sea legible y
     * compartible. El id sigue existiendo dentro, pero no en el enlace.
     */
    public function torneo(string $slug): array
    {
        return $this->fila(
            "SELECT T.torneo_id, T.torneo_nombre, T.torneo_slug, T.torneo_deporte,
                    S.temporada_nombre
               FROM dsl_torneo T
               JOIN dsl_temporada S ON S.temporada_id = T.torneo_temporadaid
              WHERE T.torneo_slug = :s AND T.torneo_publico = 'S' AND T.torneo_estado = 'A'",
            [':s' => $slug]
        );
    }

    /** Categorías de un torneo publicado. */
    public function categorias(int $torneoid): array
    {
        return $this->filas(
            "SELECT C.categoria_id, C.categoria_nombre, C.categoria_genero,
                    (SELECT COUNT(*) FROM dsl_inscripcion I
                      JOIN dsl_estado E ON E.estado_id = I.inscripcion_estadoid
                     WHERE I.inscripcion_categoriaid = C.categoria_id
                       AND E.estado_efectivo = 'S') AS equipos
               FROM dsl_categoria C
               JOIN dsl_torneo T ON T.torneo_id = C.categoria_torneoid
              WHERE C.categoria_torneoid = :t
                AND C.categoria_estado = 'A'
                AND T.torneo_publico = 'S'
              ORDER BY C.categoria_nombre",
            [':t' => $torneoid]
        );
    }

    /**
     * Una categoría, comprobando que su torneo esté publicado.
     *
     * La comprobación va en la consulta y no en la vista: si estuviera
     * fuera, bastaría con abrir la URL de una categoría de un torneo
     * privado para verla.
     */
    public function categoria(int $id): array
    {
        return $this->fila(
            "SELECT C.categoria_id, C.categoria_nombre, C.categoria_genero,
                    C.categoria_ptsvictoria, C.categoria_ptsderrota,
                    T.torneo_id, T.torneo_nombre, T.torneo_slug, T.torneo_deporte,
                    S.temporada_nombre
               FROM dsl_categoria C
               JOIN dsl_torneo    T ON T.torneo_id    = C.categoria_torneoid
               JOIN dsl_temporada S ON S.temporada_id = T.torneo_temporadaid
              WHERE C.categoria_id = :c
                AND C.categoria_estado = 'A'
                AND T.torneo_publico = 'S'",
            [':c' => $id]
        );
    }

    /*==================================================================
      Calendario y resultados
      ==================================================================*/

    /**
     * Partidos de una categoría.
     *
     * @param string $cuando 'proximos' | 'jugados' | 'todos'
     */
    public function partidos(int $categoriaid, string $cuando = 'todos', int $limite = 100): array
    {
        $filtro = match ($cuando) {
            'proximos' => " AND E.estado_efectivo = 'N' AND E.estado_final = 'N'",
            'jugados'  => " AND E.estado_efectivo = 'S'",
            default    => '',
        };

        $orden = $cuando === 'jugados'
            ? "P.partido_fecha DESC, P.partido_hora DESC"
            : "P.partido_fecha IS NULL, P.partido_fecha, P.partido_hora";

        $limite = max(1, min(300, $limite));

        return $this->filas(
            "SELECT P.partido_id, P.partido_fecha, P.partido_hora,
                    P.partido_puntoslocal, P.partido_puntosvisitante,
                    L.equipo_nombre AS local,     L.equipo_escudo AS escudo_local,
                    V.equipo_nombre AS visitante, V.equipo_escudo AS escudo_visitante,
                    E.estado_codigo, E.estado_nombre, E.estado_tono, E.estado_efectivo,
                    G.grupo_nombre, J.jornada_numero,
                    F.fase_nombre, F.fase_tipo,
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
               LEFT JOIN dsl_grupo G   ON G.grupo_id = P.partido_grupoid
               LEFT JOIN dsl_jornada J ON J.jornada_id = P.partido_jornadaid
               LEFT JOIN dsa_instalacion A ON A.instalacion_id = P.partido_instalacionid
              WHERE C.categoria_id = :c
                AND T.torneo_publico = 'S'
                {$filtro}
              ORDER BY {$orden}
              LIMIT {$limite}",
            [':c' => $categoriaid]
        );
    }

    /*==================================================================
      Clasificación

      Se recalcula aqui en vez de reutilizar competenciaController para
      no acoplar el portal a una clase con metodos de escritura. El
      duplicado es la lectura, no la regla: los puntos por victoria y el
      orden de desempate salen de la misma configuracion de la categoria.
      ==================================================================*/

    public function tabla(int $categoriaid, int $grupoid = 0): array
    {
        $cat = $this->categoria($categoriaid);
        if (!$cat) { return []; }

        $ptsVic = (int)$cat['categoria_ptsvictoria'];
        $ptsDer = (int)$cat['categoria_ptsderrota'];

        $par = [':c' => $categoriaid];
        $filtroGrupo = '';
        if ($grupoid > 0) {
            $filtroGrupo = " AND P.partido_grupoid = :g";
            $par[':g'] = $grupoid;
        }

        /* Equipos: los del grupo si se pidió uno, o los habilitados de la
           categoría. */
        $equipos = $grupoid > 0
            ? $this->filas(
                "SELECT I.inscripcion_id, Q.equipo_nombre, Q.equipo_escudo
                   FROM dsl_grupo_equipo GE
                   JOIN dsl_inscripcion I ON I.inscripcion_id = GE.ge_inscripcionid
                   JOIN dsl_equipo Q      ON Q.equipo_id = I.inscripcion_equipoid
                  WHERE GE.ge_grupoid = :g", [':g' => $grupoid])
            : $this->filas(
                "SELECT I.inscripcion_id, Q.equipo_nombre, Q.equipo_escudo
                   FROM dsl_inscripcion I
                   JOIN dsl_equipo Q ON Q.equipo_id = I.inscripcion_equipoid
                   JOIN dsl_estado E ON E.estado_id = I.inscripcion_estadoid
                  WHERE I.inscripcion_categoriaid = :c AND E.estado_efectivo = 'S'",
                [':c' => $categoriaid]);

        $resultados = $this->filas(
            "SELECT P.partido_localid, P.partido_visitanteid,
                    P.partido_puntoslocal, P.partido_puntosvisitante, E.estado_codigo
               FROM dsl_partido P
               JOIN dsl_fase F      ON F.fase_id = P.partido_faseid
               JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
               JOIN dsl_torneo T    ON T.torneo_id = C.categoria_torneoid
               JOIN dsl_estado E    ON E.estado_id = P.partido_estadoid
              WHERE C.categoria_id = :c
                AND T.torneo_publico = 'S'
                AND E.estado_efectivo = 'S'
                AND P.partido_puntoslocal IS NOT NULL
                {$filtroGrupo}",
            $par
        );

        $tabla = [];
        foreach ($equipos as $e) {
            $tabla[(int)$e['inscripcion_id']] = [
                'inscripcion_id' => (int)$e['inscripcion_id'],
                'equipo' => $e['equipo_nombre'], 'escudo' => $e['equipo_escudo'],
                'pj'=>0,'pg'=>0,'pp'=>0,'pf'=>0,'pc'=>0,'dif'=>0,'pts'=>0,
            ];
        }

        foreach ($resultados as $r) {
            $l = (int)$r['partido_localid'];  $v = (int)$r['partido_visitanteid'];
            $pl = (int)$r['partido_puntoslocal']; $pv = (int)$r['partido_puntosvisitante'];
            if (!isset($tabla[$l], $tabla[$v])) { continue; }

            $tabla[$l]['pj']++; $tabla[$v]['pj']++;
            $tabla[$l]['pf'] += $pl; $tabla[$l]['pc'] += $pv;
            $tabla[$v]['pf'] += $pv; $tabla[$v]['pc'] += $pl;

            if ($pl > $pv) {
                $tabla[$l]['pg']++; $tabla[$v]['pp']++;
                $tabla[$l]['pts'] += $ptsVic; $tabla[$v]['pts'] += $ptsDer;
            } elseif ($pv > $pl) {
                $tabla[$v]['pg']++; $tabla[$l]['pp']++;
                $tabla[$v]['pts'] += $ptsVic; $tabla[$l]['pts'] += $ptsDer;
            }
        }

        foreach ($tabla as &$t) { $t['dif'] = $t['pf'] - $t['pc']; }
        unset($t);

        $orden = array_values($tabla);
        usort($orden, static function ($a, $b) {
            return [$b['pts'], $b['dif'], $b['pf']] <=> [$a['pts'], $a['dif'], $a['pf']];
        });

        $pos = 0;
        foreach ($orden as &$f) { $f['posicion'] = ++$pos; }
        unset($f);

        return $orden;
    }

    /** Grupos de una categoría publicada. */
    public function grupos(int $categoriaid): array
    {
        return $this->filas(
            "SELECT G.grupo_id, G.grupo_nombre, F.fase_nombre
               FROM dsl_grupo G
               JOIN dsl_fase F      ON F.fase_id = G.grupo_faseid
               JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
               JOIN dsl_torneo T    ON T.torneo_id = C.categoria_torneoid
              WHERE C.categoria_id = :c AND T.torneo_publico = 'S'
              ORDER BY F.fase_orden, G.grupo_orden",
            [':c' => $categoriaid]
        );
    }

    /*==================================================================
      Eliminatorias
      ==================================================================*/

    public function llaves(int $categoriaid): array
    {
        return $this->filas(
            "SELECT S.serie_id, S.serie_nombre, S.serie_mejorde, S.serie_estado,
                    S.serie_ganadas_local, S.serie_ganadas_visitante,
                    F.fase_nombre,
                    L.equipo_nombre AS local,     L.equipo_escudo AS escudo_local,
                    V.equipo_nombre AS visitante, V.equipo_escudo AS escudo_visitante,
                    W.equipo_nombre AS ganador
               FROM dsl_serie S
               JOIN dsl_fase F      ON F.fase_id = S.serie_faseid
               JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
               JOIN dsl_torneo T    ON T.torneo_id = C.categoria_torneoid
               LEFT JOIN dsl_inscripcion IL ON IL.inscripcion_id = S.serie_localid
               LEFT JOIN dsl_equipo L  ON L.equipo_id = IL.inscripcion_equipoid
               LEFT JOIN dsl_inscripcion IV ON IV.inscripcion_id = S.serie_visitanteid
               LEFT JOIN dsl_equipo V  ON V.equipo_id = IV.inscripcion_equipoid
               LEFT JOIN dsl_inscripcion IW ON IW.inscripcion_id = S.serie_ganadorid
               LEFT JOIN dsl_equipo W  ON W.equipo_id = IW.inscripcion_equipoid
              WHERE C.categoria_id = :c AND T.torneo_publico = 'S'
              ORDER BY F.fase_orden, S.serie_orden",
            [':c' => $categoriaid]
        );
    }

    /*==================================================================
      Plantilla · aqui es donde la privacidad se decide
      ==================================================================*/

    /**
     * Plantilla publicable de un equipo.
     *
     * NO SE SELECCIONAN persona_identificacion NI persona_fechanac.
     *
     * No es un descuido que haya que recordar corregir: es la defensa. Un
     * filtro en la vista se olvida al copiar una plantilla; una columna
     * que no esta en el SELECT no puede escaparse.
     *
     * La foto sale unicamente si hay consentimiento registrado; si no, la
     * vista pinta las iniciales.
     */
    public function plantilla(int $inscripcionid): array
    {
        return $this->filas(
            "SELECT PL.plantilla_dorsal, PL.plantilla_rol,
                    P.persona_nombres, P.persona_apellidos,
                    CASE WHEN P.persona_publicarfoto = 'S' THEN P.persona_foto ELSE NULL END AS foto
               FROM dsl_plantilla PL
               JOIN dsl_persona P     ON P.persona_id = PL.plantilla_personaid
               JOIN dsl_inscripcion I ON I.inscripcion_id = PL.plantilla_inscripcionid
               JOIN dsl_categoria C   ON C.categoria_id = I.inscripcion_categoriaid
               JOIN dsl_torneo T      ON T.torneo_id = C.categoria_torneoid
              WHERE PL.plantilla_inscripcionid = :i
                AND PL.plantilla_baja IS NULL
                AND PL.plantilla_habilitado = 'S'
                AND T.torneo_publico = 'S'
              ORDER BY PL.plantilla_rol, PL.plantilla_dorsal, P.persona_apellidos",
            [':i' => $inscripcionid]
        );
    }

    /** Equipos de una categoría, con su inscripción para enlazar la plantilla. */
    public function equipos(int $categoriaid): array
    {
        return $this->filas(
            "SELECT I.inscripcion_id, Q.equipo_nombre, Q.equipo_corto, Q.equipo_escudo,
                    (SELECT COUNT(*) FROM dsl_plantilla PL
                      WHERE PL.plantilla_inscripcionid = I.inscripcion_id
                        AND PL.plantilla_baja IS NULL
                        AND PL.plantilla_habilitado = 'S') AS jugadores
               FROM dsl_inscripcion I
               JOIN dsl_equipo Q    ON Q.equipo_id = I.inscripcion_equipoid
               JOIN dsl_estado E    ON E.estado_id = I.inscripcion_estadoid
               JOIN dsl_categoria C ON C.categoria_id = I.inscripcion_categoriaid
               JOIN dsl_torneo T    ON T.torneo_id = C.categoria_torneoid
              WHERE I.inscripcion_categoriaid = :c
                AND E.estado_efectivo = 'S'
                AND T.torneo_publico = 'S'
              ORDER BY Q.equipo_nombre",
            [':c' => $categoriaid]
        );
    }

    /*==================================================================
      Lideres
      ==================================================================*/

    /**
     * Máximos de una estadística. Tampoco aquí sale dato personal alguno
     * más allá del nombre.
     */
    public function lideres(int $categoriaid, string $codigo, int $limite = 5): array
    {
        $limite = max(1, min(50, $limite));

        return $this->filas(
            "SELECT P.persona_nombres, P.persona_apellidos,
                    Q.equipo_nombre,
                    SUM(S.stat_valor) AS total,
                    COUNT(DISTINCT S.stat_partidoid) AS partidos,
                    ROUND(SUM(S.stat_valor) / COUNT(DISTINCT S.stat_partidoid), 1) AS promedio
               FROM dsl_partido_stat S
               JOIN dsl_estadistica_tipo TP ON TP.tipo_id = S.stat_tipoid
               JOIN dsl_persona P     ON P.persona_id = S.stat_personaid
               JOIN dsl_inscripcion I ON I.inscripcion_id = S.stat_inscripcionid
               JOIN dsl_equipo Q      ON Q.equipo_id = I.inscripcion_equipoid
               JOIN dsl_categoria C   ON C.categoria_id = I.inscripcion_categoriaid
               JOIN dsl_torneo T      ON T.torneo_id = C.categoria_torneoid
              WHERE I.inscripcion_categoriaid = :c
                AND TP.tipo_codigo = :k
                AND T.torneo_publico = 'S'
              GROUP BY P.persona_id, P.persona_nombres, P.persona_apellidos, Q.equipo_nombre
              ORDER BY total DESC
              LIMIT {$limite}",
            [':c' => $categoriaid, ':k' => $codigo]
        );
    }

    /*==================================================================
      Campeones
      ==================================================================*/

    /** Ganadores de las eliminatorias ya cerradas. */
    public function campeones(int $torneoid): array
    {
        return $this->filas(
            "SELECT C.categoria_nombre, F.fase_nombre,
                    W.equipo_nombre AS campeon, W.equipo_escudo
               FROM dsl_serie S
               JOIN dsl_fase F      ON F.fase_id = S.serie_faseid
               JOIN dsl_categoria C ON C.categoria_id = F.fase_categoriaid
               JOIN dsl_torneo T    ON T.torneo_id = C.categoria_torneoid
               JOIN dsl_inscripcion IW ON IW.inscripcion_id = S.serie_ganadorid
               JOIN dsl_equipo W    ON W.equipo_id = IW.inscripcion_equipoid
              WHERE C.categoria_torneoid = :t
                AND T.torneo_publico = 'S'
                AND S.serie_estado = 'CERRADA'
                /* La última fase de cada categoría es la que corona. */
                AND F.fase_orden = (SELECT MAX(F2.fase_orden) FROM dsl_fase F2
                                     WHERE F2.fase_categoriaid = C.categoria_id)
              ORDER BY C.categoria_nombre",
            [':t' => $torneoid]
        );
    }
}
