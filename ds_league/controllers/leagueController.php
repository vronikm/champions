<?php

namespace league\controllers;

use PDO;

/**
 * DigiSports League.
 *
 * Torneos y ligas. Las tablas propias llevan prefijo dsl_ y son InnoDB, lo
 * que permite envolver en transaccion las tres operaciones donde una
 * escritura a medias deja datos incoherentes: generacion de fixture,
 * ejecucion de sorteo y registro de obligaciones economicas.
 *
 * Este modulo NO administra escenarios. Los consume de Arena (dsa_*) para
 * que no existan dos calendarios sobre la misma cancha.
 */
class leagueController
{
    /*==================================================================
      Acceso a datos
      ==================================================================*/

    protected function con(): ?PDO
    {
        return seguridad_conexion();
    }

    protected function filas(string $sql, array $params = []): array
    {
        try {
            $con = $this->con();
            if ($con === null) { return []; }
            $stmt = $con->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function fila(string $sql, array $params = []): array
    {
        $f = $this->filas($sql, $params);
        return $f[0] ?? [];
    }

    protected function escalar(string $sql, array $params = [], $porDefecto = 0)
    {
        try {
            $con = $this->con();
            if ($con === null) { return $porDefecto; }
            $stmt = $con->prepare($sql);
            $stmt->execute($params);
            $v = $stmt->fetchColumn();
            return $v === false ? $porDefecto : $v;
        } catch (\Throwable $e) {
            return $porDefecto;
        }
    }

    /*==================================================================
      Estados y transiciones

      La regla de que movimiento es legal vive en dsl_estado_transicion,
      no repartida por los controladores. Un partido finalizado no vuelve
      a programado porque no existe la fila, no porque alguien se acordo
      de comprobarlo.
      ==================================================================*/

    /** Estados de una entidad, en su orden de catalogo. */
    public function estados(string $entidad): array
    {
        return $this->filas(
            "SELECT estado_id, estado_codigo, estado_nombre, estado_tono,
                    estado_final, estado_efectivo
               FROM dsl_estado
              WHERE estado_entidad = :e AND estado_activo = 'S'
              ORDER BY estado_orden",
            [':e' => $entidad]
        );
    }

    /** Un estado concreto por su codigo. Devuelve [] si no existe. */
    public function estado(string $entidad, string $codigo): array
    {
        return $this->fila(
            "SELECT estado_id, estado_codigo, estado_nombre, estado_tono,
                    estado_final, estado_efectivo
               FROM dsl_estado
              WHERE estado_entidad = :e AND estado_codigo = :c",
            [':e' => $entidad, ':c' => $codigo]
        );
    }

    /**
     * Movimientos legales desde un estado, para pintar los botones de
     * accion sin que la vista tenga que saberse las reglas.
     */
    public function transicionesDesde(string $entidad, string $codigoDesde): array
    {
        return $this->filas(
            "SELECT H.estado_codigo AS hacia, H.estado_nombre, H.estado_tono,
                    T.trans_accion  AS accion,
                    T.trans_motivo  AS exige_motivo
               FROM dsl_estado_transicion T
               JOIN dsl_estado D ON D.estado_id = T.trans_desde
               JOIN dsl_estado H ON H.estado_id = T.trans_hasta
              WHERE T.trans_entidad = :e
                AND T.trans_activo  = 'S'
                AND D.estado_codigo = :d
              ORDER BY H.estado_orden",
            [':e' => $entidad, ':d' => $codigoDesde]
        );
    }

    /**
     * Comprueba si un movimiento esta permitido.
     *
     * Se consulta ANTES de escribir, no despues: es la unica forma de que
     * la transaccion que cambia el estado pueda abortar limpia.
     */
    public function transicionPermitida(string $entidad, string $desde, string $hasta): bool
    {
        return (int)$this->escalar(
            "SELECT COUNT(*)
               FROM dsl_estado_transicion T
               JOIN dsl_estado D ON D.estado_id = T.trans_desde
               JOIN dsl_estado H ON H.estado_id = T.trans_hasta
              WHERE T.trans_entidad = :e AND T.trans_activo = 'S'
                AND D.estado_codigo = :d AND H.estado_codigo = :h",
            [':e' => $entidad, ':d' => $desde, ':h' => $hasta]
        ) > 0;
    }

    /*==================================================================
      Auditoria

      Un solo punto de escritura. Los controladores llaman aqui; no
      escriben en dsl_auditoria por su cuenta.

      Se guardan los valores, no una frase: "se cambio el resultado" es
      justo lo que no sirve cuando hay que demostrar cual era el marcador
      anterior.
      ==================================================================*/

    public function auditar(string $entidad, int $entidadId, string $accion,
                            ?array $antes = null, ?array $despues = null,
                            string $nota = ''): bool
    {
        try {
            $con = $this->con();
            if ($con === null) { return false; }

            $stmt = $con->prepare(
                "INSERT INTO dsl_auditoria
                        (audit_entidad, audit_entidadid, audit_accion,
                         audit_usuarioid, audit_usuario, audit_ip,
                         audit_antes, audit_despues, audit_nota)
                 VALUES (:ent, :entid, :acc, :uid, :usr, :ip, :antes, :desp, :nota)"
            );

            /* JSON_UNESCAPED_UNICODE para que un nombre con tilde se lea
               tal cual en la consulta, y no como á. */
            $aJson = static function (?array $v): ?string {
                return $v === null ? null
                     : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            };

            return $stmt->execute([
                ':ent'   => $entidad,
                ':entid' => $entidadId,
                ':acc'   => $accion,
                ':uid'   => usuario_actual_id() ?: null,
                ':usr'   => substr(ds_nombre_usuario(), 0, 20),
                /* Se reutiliza el helper del nucleo en vez de repetirlo:
                   ya valida la direccion y usa REMOTE_ADDR y nunca
                   X-Forwarded-For, que es la parte que importa. */
                ':ip'    => intentos_ip_binaria(),
                ':antes' => $aJson($antes),
                ':desp'  => $aJson($despues),
                ':nota'  => substr($nota, 0, 250),
            ]);

        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Historial de una entidad, del cambio mas reciente al mas antiguo. */
    public function historial(string $entidad, int $entidadId, int $limite = 50): array
    {
        $limite = max(1, min(500, $limite));
        return $this->filas(
            "SELECT audit_id, audit_accion, audit_usuario, audit_nota,
                    audit_antes, audit_despues, audit_fecha,
                    INET6_NTOA(audit_ip) AS audit_ip
               FROM dsl_auditoria
              WHERE audit_entidad = :e AND audit_entidadid = :id
              ORDER BY audit_fecha DESC, audit_id DESC
              LIMIT $limite",
            [':e' => $entidad, ':id' => $entidadId]
        );
    }

    /*==================================================================
      Facturacion: el punto de emision de este modulo

      La identidad fiscal (RUC, razon social, certificado) es del
      contribuyente y vive en el Core. Lo propio de League es el punto de
      emision, y que sea distinto del de Basketball es lo que impide que
      las dos series de secuenciales colisionen.
      ==================================================================*/

    public function puntoEmision(): array
    {
        return $this->fila(
            "SELECT punto_establecimiento, punto_codigo,
                    punto_secuencialinicio, punto_estado, punto_descripcion
               FROM facturas_electronicas_punto_emision
              WHERE punto_modulo = :m",
            [':m' => DS_MODULO]
        );
    }

    /*==================================================================
      Estado de los cimientos

      Lo que el panel necesita saber mientras el modulo no tiene datos de
      negocio: si la instalacion quedo completa.
      ==================================================================*/

    public function diagnostico(): array
    {
        $punto = $this->puntoEmision();

        return [
            'estados' => [
                'etiqueta' => 'Estados catalogados',
                'valor'    => (int)$this->escalar("SELECT COUNT(*) FROM dsl_estado"),
                'detalle'  => (int)$this->escalar("SELECT COUNT(*) FROM dsl_estado_transicion")
                              . ' transiciones permitidas',
                'ok'       => (int)$this->escalar("SELECT COUNT(*) FROM dsl_estado") > 0,
            ],
            'permisos' => [
                'etiqueta' => 'Permisos',
                'valor'    => permisos_estrictos() ? 'Estrictos' : 'Permisivos',
                'detalle'  => permisos_estrictos()
                              ? 'Una vista no registrada se deniega'
                              : 'Una vista no registrada quedaria abierta',
                'ok'       => permisos_estrictos(),
            ],
            'facturacion' => [
                'etiqueta' => 'Punto de emisión',
                'valor'    => $punto
                              ? $punto['punto_establecimiento'] . '-' . $punto['punto_codigo']
                              : 'Sin asignar',
                'detalle'  => $punto
                              ? ($punto['punto_estado'] === 'A'
                                 ? 'Activo, numera desde ' . (int)$punto['punto_secuencialinicio']
                                 : 'Reservado, pendiente de activar en el Core')
                              : 'Debe asignarse antes de facturar',
                'ok'       => $punto && $punto['punto_estado'] === 'A',
            ],
            'escenarios' => [
                'etiqueta' => 'Escenarios (Arena)',
                'valor'    => (int)$this->escalar(
                                  "SELECT COUNT(*) FROM dsa_instalacion WHERE instalacion_estado = 'A'"),
                'detalle'  => 'Se consumen de Arena, no se duplican aquí',
                'ok'       => (int)$this->escalar(
                                  "SELECT COUNT(*) FROM dsa_instalacion WHERE instalacion_estado = 'A'") > 0,
            ],
            'auditoria' => [
                'etiqueta' => 'Auditoría',
                'valor'    => (int)$this->escalar("SELECT COUNT(*) FROM dsl_auditoria"),
                'detalle'  => 'Registros acumulados',
                'ok'       => true,
            ],
        ];
    }


    /*==================================================================
      Lo que el panel necesita saber

      DIFERENCIA CON diagnostico()

      diagnostico() responde «¿quedó bien instalado el módulo?». Es una
      pregunta que se hace UNA VEZ, el día del montaje. Lo que sigue
      responde «¿qué pasa hoy en la competencia y qué necesita mi
      atención?», que es la pregunta de todos los demás días.

      Ninguna de estas cifras se guarda. Todas se derivan de los partidos,
      las inscripciones y los cobros, que es donde viven de verdad: un
      contador almacenado se desincroniza el día que alguien corrige un
      marcador a mano.

      LOS ESTADOS NO SE NOMBRAN POR SU CÓDIGO

      Se filtra por estado_final y estado_efectivo, que es justo para lo
      que existen esas columnas. «Partido jugado» no es «FINALIZADO»: un
      walkover también cuenta y también está jugado. Si mañana se agrega
      un estado nuevo que cuente para la clasificación, estas cifras lo
      recogen solas y nadie tiene que acordarse de venir a tocarlas.
      ==================================================================*/

    /** Avance de la competencia. Se deriva; no hay contadores guardados. */
    public function resumenCompetencia(): array
    {
        $f = $this->fila(
            "SELECT
                COUNT(*)                                                  AS total,
                SUM(E.estado_efectivo = 'S')                              AS jugados,
                SUM(E.estado_final    = 'N')                              AS porJugar,
                SUM(E.estado_final = 'N' AND P.partido_fecha < CURDATE())  AS vencidos
               FROM dsl_partido P
               JOIN dsl_estado E ON E.estado_id = P.partido_estadoid"
        );

        $total   = (int)($f['total']   ?? 0);
        $jugados = (int)($f['jugados'] ?? 0);

        return [
            'total'     => $total,
            'jugados'   => $jugados,
            'porJugar'  => (int)($f['porJugar'] ?? 0),
            'vencidos'  => (int)($f['vencidos'] ?? 0),
            'avance'    => $total > 0 ? (int)round($jugados * 100 / $total) : 0,
            'torneos'   => (int)$this->escalar(
                "SELECT COUNT(*) FROM dsl_torneo WHERE torneo_estado = 'A'"),
            'equipos'   => (int)$this->escalar(
                "SELECT COUNT(DISTINCT I.inscripcion_equipoid)
                   FROM dsl_inscripcion I
                   JOIN dsl_estado E ON E.estado_id = I.inscripcion_estadoid
                  WHERE E.estado_efectivo = 'S'"),
            'jugadores' => (int)$this->escalar(
                "SELECT COUNT(*) FROM dsl_plantilla
                  WHERE plantilla_habilitado = 'S' AND plantilla_baja IS NULL"),
        ];
    }

    /**
     * La próxima fecha con partidos, y cuántos hay.
     *
     * Se busca la primera fecha CON partidos en lugar de mirar «hoy»: en
     * un torneo que juega los sábados, un panel que sólo sepa mirar hoy
     * está vacío seis días de cada siete.
     */
    public function proximaJornada(): array
    {
        return $this->fila(
            "SELECT P.partido_fecha AS fecha, COUNT(*) AS partidos,
                    MIN(P.partido_hora) AS desde
               FROM dsl_partido P
               JOIN dsl_estado E ON E.estado_id = P.partido_estadoid
              WHERE E.estado_final = 'N' AND P.partido_fecha >= CURDATE()
              GROUP BY P.partido_fecha
              ORDER BY P.partido_fecha
              LIMIT 1"
        );
    }

    /**
     * Lo accionable. Cada línea es algo que alguien tiene que ir a hacer.
     *
     * No se incluye nada que no tenga arreglo desde una pantalla del
     * módulo: un panel que informa de un problema sin decir dónde se
     * resuelve sólo genera trabajo de búsqueda.
     */
    public function requiereAtencion(): array
    {
        /* Partidos cuya fecha pasó y que siguen sin resolverse: los
           suspendidos que nadie reprogramó y los que se jugaron pero
           nadie cerró. Es la deuda que descuadra la clasificación. */
        $vencidos = (int)$this->escalar(
            "SELECT COUNT(*)
               FROM dsl_partido P
               JOIN dsl_estado E ON E.estado_id = P.partido_estadoid
              WHERE E.estado_final = 'N' AND P.partido_fecha < CURDATE()");

        /* Partidos que YA cuentan para la clasificación pero de los que no
           hay ni una línea de estadística: el marcador está, el acta no, y
           sin acta no hay líderes ni ficha de jugador. */
        $sinActa = (int)$this->escalar(
            "SELECT COUNT(*)
               FROM dsl_partido P
               JOIN dsl_estado E ON E.estado_id = P.partido_estadoid
              WHERE E.estado_efectivo = 'S'
                AND NOT EXISTS (SELECT 1 FROM dsl_partido_stat S
                                 WHERE S.stat_partidoid = P.partido_id)");

        /* Jugadores en plantilla vigente que NO están habilitados. El
           porqué vive en motivosNoHabilitable(); aquí sólo se cuenta
           para poder avisar. */
        $noHabilitados = (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_plantilla
              WHERE plantilla_habilitado = 'N' AND plantilla_baja IS NULL");

        /* Sin dorsal no se puede anotar una canasta en el acta. */
        $sinDorsal = (int)$this->escalar(
            "SELECT COUNT(*) FROM dsl_plantilla
              WHERE plantilla_baja IS NULL
                AND (plantilla_dorsal IS NULL OR plantilla_dorsal = '')");

        return [
            'vencidos'      => $vencidos,
            'sinActa'       => $sinActa,
            'noHabilitados' => $noHabilitados,
            'sinDorsal'     => $sinDorsal,
        ];
    }

    /**
     * Cobranza en cuatro cifras.
     *
     * NO se llama desde la vista sin comprobar permiso antes. El dinero es
     * información restringida y el panel es la pantalla que todo el mundo
     * abre al entrar.
     */
    public function economia(): array
    {
        /*
        | LO VENCIDO ES EL SALDO, NO EL VALOR DE LA OBLIGACIÓN
        |
        | La primera versión de esta consulta sumaba el importe íntegro de
        | cada obligación vencida y no pagada. Con los datos de hoy daba 775
        | cuando lo realmente adeudado son 475: cuatro de las seis vencidas
        | son PARCIALes que ya tienen la mitad abonada, y contarlas enteras
        | infla la mora un 63 %.
        |
        | Un panel que exagera la deuda se deja de mirar en cuanto alguien
        | contrasta la cifra con la pantalla de cobranza. Así que se resta
        | lo abonado, obligación a obligación.
        |
        | El estado no decide el importe, sólo excluye lo anulado: quién
        | está al día se deduce del saldo, que es el dato que no se puede
        | desincronizar.
        */
        $f = $this->fila(
            "SELECT
                COALESCE(SUM(D.cargo), 0)                                  AS emitido,
                COALESCE(SUM(D.abonado), 0)                                AS cobrado,
                COALESCE(SUM(CASE WHEN D.vence < CURDATE() AND D.cargo > D.abonado
                                  THEN D.cargo - D.abonado END), 0)        AS vencido,
                COALESCE(SUM(D.vence < CURDATE() AND D.cargo > D.abonado), 0) AS nVencidas
               FROM (
                    SELECT O.obligacion_vence AS vence,
                           O.obligacion_valor + O.obligacion_recargo
                             - O.obligacion_descuento AS cargo,
                           COALESCE((SELECT SUM(A.abono_valor)
                                       FROM dsl_abono A
                                      WHERE A.abono_obligacionid = O.obligacion_id
                                        AND A.abono_anulado = 'N'), 0) AS abonado
                      FROM dsl_obligacion O
                     WHERE O.obligacion_estado <> 'ANULADA'
               ) D"
        );

        $emitido = (float)($f['emitido'] ?? 0);
        $cobrado = (float)($f['cobrado'] ?? 0);

        return [
            'emitido'   => $emitido,
            'cobrado'   => $cobrado,
            'saldo'     => $emitido - $cobrado,
            'vencido'   => (float)($f['vencido'] ?? 0),
            'nVencidas' => (int)($f['nVencidas'] ?? 0),
            'avance'    => $emitido > 0 ? (int)round($cobrado * 100 / $emitido) : 0,
        ];
    }

    /**
     * Los próximos partidos, con equipos y cancha.
     *
     * El límite se acota en PHP y se interpola ya convertido a entero:
     * LIMIT no admite parámetro en una sentencia preparada de MySQL, y la
     * alternativa —concatenar lo que llegue— es justo lo que no se hace.
     */
    public function proximosPartidos(int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));

        return $this->filas(
            "SELECT P.partido_id, P.partido_fecha, P.partido_hora,
                    L.equipo_nombre AS local, V.equipo_nombre AS visitante,
                    E.estado_nombre, E.estado_tono,
                    C.categoria_nombre,
                    A.instalacion_nombre AS cancha
               FROM dsl_partido P
               JOIN dsl_inscripcion IL   ON IL.inscripcion_id = P.partido_localid
               JOIN dsl_equipo L         ON L.equipo_id = IL.inscripcion_equipoid
               JOIN dsl_inscripcion IV   ON IV.inscripcion_id = P.partido_visitanteid
               JOIN dsl_equipo V         ON V.equipo_id = IV.inscripcion_equipoid
               JOIN dsl_estado E         ON E.estado_id = P.partido_estadoid
               LEFT JOIN dsl_categoria C   ON C.categoria_id = IL.inscripcion_categoriaid
               LEFT JOIN dsa_instalacion A ON A.instalacion_id = P.partido_instalacionid
              WHERE E.estado_final = 'N' AND P.partido_fecha >= CURDATE()
              ORDER BY P.partido_fecha, P.partido_hora
              LIMIT " . $limite
        );
    }

    /**
     * Los últimos resultados con marcador.
     *
     * SÓLO PARTIDOS CUYA FECHA YA PASÓ
     *
     * La primera versión ordenaba por fecha descendente sin más, y el
     * primer «último resultado» que salió fue uno del 19 de septiembre
     * —futuro— que estaba marcado como finalizado con 45-85. Un partido
     * no puede haber producido un resultado antes de jugarse: esa fila es
     * un error de captura, y colarla arriba de la lista hace que la
     * pantalla se lea como si estuviera rota.
     *
     * Se filtra por fecha, no por estado, porque el estado ahí ya es el
     * correcto; lo que está mal es la fecha o el momento en que se cerró.
     */
    public function ultimosResultados(int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));

        return $this->filas(
            "SELECT P.partido_id, P.partido_fecha,
                    P.partido_puntoslocal AS pl, P.partido_puntosvisitante AS pv,
                    L.equipo_nombre AS local, V.equipo_nombre AS visitante,
                    E.estado_nombre, E.estado_tono
               FROM dsl_partido P
               JOIN dsl_inscripcion IL ON IL.inscripcion_id = P.partido_localid
               JOIN dsl_equipo L       ON L.equipo_id = IL.inscripcion_equipoid
               JOIN dsl_inscripcion IV ON IV.inscripcion_id = P.partido_visitanteid
               JOIN dsl_equipo V       ON V.equipo_id = IV.inscripcion_equipoid
               JOIN dsl_estado E       ON E.estado_id = P.partido_estadoid
              WHERE E.estado_efectivo = 'S' AND P.partido_fecha <= CURDATE()
              ORDER BY P.partido_fecha DESC, P.partido_id DESC
              LIMIT " . $limite
        );
    }

    /*==================================================================
      Menu lateral
      ==================================================================*/

    /**
     * Solo se pintan los menus con estado 'A'. Los registrados como 'O'
     * (ocultos) estan sujetos a permiso pero no aparecen aqui: son las
     * vistas de apoyo que el modo estricto obliga a registrar y que no
     * tienen sentido como entrada de menu.
     */
    public function menuLateral(string $vistaActual): string
    {
        $sql = "SELECT m.menu_vista, m.menu_nombre, m.menu_icono
                  FROM seguridad_menu m
                 WHERE m.menu_estado = 'A' AND m.menu_modulo = :modulo";

        $params = [':modulo' => DS_MODULO];

        if (!es_superadministrador()) {
            $sql .= " AND EXISTS (SELECT 1 FROM seguridad_permiso p
                                   WHERE p.permiso_menuid = m.menu_id
                                     AND p.permiso_rolid  = :rol
                                     AND p.permiso_estado = 'A'
                                     AND p.permiso_ver    = 'S')";
            $params[':rol'] = rol_actual();
        }

        $sql .= " ORDER BY m.menu_orden";

        /* Marcado de AdminLTE 4. La diferencia con v3 es la clase
           nav-icon en el icono: sin ella el texto se pega al borde y los
           iconos no quedan alineados entre si. */
        $html = '';
        foreach ($this->filas($sql, $params) as $f) {
            $activo = ($f['menu_vista'] === $vistaActual) ? ' active' : '';
            $html .= '<li class="nav-item">'
                   . '<a href="' . APP_URL . $f['menu_vista'] . '/" class="nav-link' . $activo . '">'
                   . '<i class="nav-icon ' . htmlspecialchars($f['menu_icono'], ENT_QUOTES, 'UTF-8') . '"></i>'
                   . '<p>' . htmlspecialchars($f['menu_nombre'], ENT_QUOTES, 'UTF-8') . '</p>'
                   . '</a></li>';
        }

        return $html;
    }
}
