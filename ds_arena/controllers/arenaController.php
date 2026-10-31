<?php

namespace arena\controllers;

use PDO;

/**
 * DigiSports Arena.
 *
 * Instalaciones, disponibilidad, reservas y monedero. Se apoya en la
 * conexion del nucleo; las tablas propias llevan prefijo dsa_ y son
 * InnoDB, lo que permite envolver en transaccion las operaciones que
 * tocan saldo (pagos y monedero).
 */
class arenaController
{
    /**
     * Filas por página en los listados.
     *
     * La lista de reservas entregaba las 5.851 de golpe y la página pesaba
     * 18,7 MB: en un teléfono con datos móviles no es lenta, es que no
     * carga. El primer arreglo fue un tope de 500 con aviso; honesto, pero
     * dejaba fuera de alcance todo lo que hubiera detrás.
     *
     * Con paginación de verdad, 50 es el tamaño que cabe en una pantalla
     * sin obligar a desplazarse en exceso y mantiene la página en unos
     * pocos cientos de KB.
     */
    public const POR_PAGINA = 50;

    /*----------  Acceso a datos  ----------*/

    private function con(): ?PDO
    {
        return seguridad_conexion();
    }

    private function filas(string $sql, array $params = []): array
    {
        try {
            $con = $this->con();
            if ($con === null) return [];
            $stmt = $con->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function escalar(string $sql, array $params = [])
    {
        try {
            $con = $this->con();
            if ($con === null) return 0;
            $stmt = $con->prepare($sql);
            $stmt->execute($params);
            $f = $stmt->fetch(PDO::FETCH_NUM);
            return $f === false ? 0 : $f[0];
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function escribir(string $sql, array $params = []): int
    {
        try {
            $con = $this->con();
            if ($con === null) return -1;
            $stmt = $con->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return -1;
        }
    }

    public function alerta(string $tipo, string $titulo, string $texto, string $icono = 'success', string $url = ''): string
    {
        $a = ['tipo' => $tipo, 'titulo' => $titulo, 'texto' => $texto, 'icono' => $icono];
        if ($url !== '') $a['url'] = $url;
        return json_encode($a, JSON_UNESCAPED_UNICODE);
    }

    /*======================================================================
      Sedes: sólo las que ofrecen alquiler
      ====================================================================*/

    /**
     * general_sede es compartida con la escuela. Arena sólo administra las
     * sedes cuyo tipo incluye alquiler (catálogo sede_tipoingreso).
     */
    public function sedesAlquiler(): array
    {
        $codigos = ARENA_SEDES_ALQUILER;
        $marcas  = implode(',', array_fill(0, count($codigos), '?'));

        return $this->filas(
            "SELECT sede_id, sede_nombre, sede_tipoingreso
               FROM general_sede
              WHERE sede_tipoingreso IN ($marcas)
              ORDER BY sede_nombre",
            $codigos
        );
    }

    public function sedeOfreceAlquiler(int $sedeid): bool
    {
        $tipo = (string)$this->escalar(
            "SELECT sede_tipoingreso FROM general_sede WHERE sede_id = :id",
            [':id' => $sedeid]
        );

        return in_array($tipo, ARENA_SEDES_ALQUILER, true);
    }

    /*======================================================================
      Catálogos
      ====================================================================*/

    public function tiposPiso(): array
    {
        return $this->filas(
            "SELECT piso_id, piso_nombre, piso_detalle
               FROM dsa_tipo_piso WHERE piso_estado = 'A' ORDER BY piso_nombre"
        );
    }

    public function formasIngreso(): array
    {
        return $this->filas(
            "SELECT forma_id, forma_codigo, forma_nombre, forma_esmonedero, forma_requiereref
               FROM dsa_forma_ingreso WHERE forma_estado = 'A' ORDER BY forma_orden"
        );
    }

    /*======================================================================
      Instalaciones
      ====================================================================*/

    public function instalaciones(int $sedeid = 0, string $clase = ''): array
    {
        $sql = "SELECT i.*, s.sede_nombre, p.piso_nombre,
                       (SELECT COUNT(1) FROM dsa_horario h
                         WHERE h.horario_instalacionid = i.instalacion_id
                           AND h.horario_estado = 'A') AS franjas,
                       (SELECT COUNT(1) FROM dsa_reserva r
                         WHERE r.reserva_instalacionid = i.instalacion_id
                           AND r.reserva_estado IN ('P','C')) AS reservas
                  FROM dsa_instalacion i
                  LEFT JOIN general_sede  s ON s.sede_id = i.instalacion_sedeid
                  LEFT JOIN dsa_tipo_piso p ON p.piso_id = i.instalacion_pisoid
                 WHERE i.instalacion_estado <> 'E'";

        $params = [];
        if ($sedeid > 0) { $sql .= " AND i.instalacion_sedeid = :sede"; $params[':sede'] = $sedeid; }
        if ($clase !== '') { $sql .= " AND i.instalacion_clase = :clase"; $params[':clase'] = $clase; }

        $sql .= " ORDER BY s.sede_nombre, i.instalacion_clase, i.instalacion_nombre";

        return $this->filas($sql, $params);
    }

    public function instalacion(int $id): ?array
    {
        $r = $this->filas("SELECT * FROM dsa_instalacion WHERE instalacion_id = :id", [':id' => $id]);
        return $r[0] ?? null;
    }

    /**
     * Prefijo de la sede para el código: "Sauces" -> SAU, "La Salle" -> LAS.
     *
     * Si dos sedes comparten las tres primeras letras se alarga a cuatro, y
     * si aún así coinciden se añade el id. Sin eso "SAU-C01" podría nombrar
     * dos canchas distintas, que es justo lo que el código debe evitar
     * cuando alguien lo dice por teléfono o por radio.
     */
    private function prefijoSede(int $sedeid): string
    {
        static $cache = null;

        if ($cache === null) {
            $cache  = [];
            $usados = [];

            foreach ($this->filas("SELECT sede_id, sede_nombre FROM general_sede ORDER BY sede_id") as $s) {
                $letras = strtoupper(preg_replace('/[^A-Za-z]/', '', \ds_sin_tildes((string)$s['sede_nombre'])));
                if ($letras === '') { $letras = 'SEDE'; }

                $p = substr($letras, 0, 3);
                if (isset($usados[$p])) { $p = substr($letras, 0, 4); }
                if (isset($usados[$p])) { $p = substr($letras, 0, 3) . $s['sede_id']; }

                $usados[$p] = true;
                $cache[(int)$s['sede_id']] = $p;
            }
        }

        return $cache[$sedeid] ?? ('S' . $sedeid);
    }

    /**
     * Propone el código de una instalación: PREFIJO-CLASE + consecutivo.
     * Ej.: SAU-C01 (cancha 1 de Sauces), LAS-R02 (residencia 2 de La Salle).
     *
     * El código sólo servía para impedir duplicados dentro de la sede, pero
     * se pedía a mano, así que cada alta inventaba su propia convención.
     * Generándolo aquí la restricción sigue en pie y el usuario deja de
     * cargar con ella.
     */
    public function codigoSugerido(int $sedeid, string $clase, int $excluirId = 0): string
    {
        $clase = ($clase === 'R') ? 'R' : 'C';
        $raiz  = $this->prefijoSede($sedeid) . '-' . $clase;

        /* Se parte del mayor consecutivo ya usado con esa raíz, no del
           número de filas: si se da de baja una instalación intermedia el
           conteo se repetiría y chocaría con la clave única. */
        $mayor = (int)$this->escalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(instalacion_codigo, :largo) AS UNSIGNED)), 0)
               FROM dsa_instalacion
              WHERE instalacion_sedeid = :s
                AND instalacion_codigo LIKE :raiz",
            [':largo' => strlen($raiz) + 1, ':s' => $sedeid, ':raiz' => $raiz . '%']
        );

        /* Aun así se comprueba hueco por hueco: pueden convivir códigos
           escritos a mano que no sigan el patrón y ocupen el sitio. La
           consulta NO filtra por estado, porque la clave única tampoco lo
           hace: una instalación dada de baja sigue reservando su código. */
        for ($n = $mayor + 1; $n <= $mayor + 999; $n++) {
            $codigo = $raiz . str_pad((string)$n, 2, '0', STR_PAD_LEFT);

            $ocupado = (int)$this->escalar(
                "SELECT COUNT(1) FROM dsa_instalacion
                  WHERE instalacion_sedeid = :s AND instalacion_codigo = :c
                    AND instalacion_id <> :id",
                [':s' => $sedeid, ':c' => $codigo, ':id' => $excluirId]
            );
            if ($ocupado === 0) { return $codigo; }
        }

        /* Mil huecos ocupados seguidos no debería ocurrir; antes que
           devolver vacío y dejar el alta sin código, se desempata. */
        return $raiz . substr((string)time(), -4);
    }

    /**
     * Versión AJAX de codigoSugerido(), para que el formulario muestre la
     * propuesta en cuanto se eligen sede y tipo.
     */
    public function sugerirCodigo(): string
    {
        if (!puede_crear('instalacionList') && !puede_editar('instalacionList')) {
            return json_encode(['codigo' => '']);
        }

        $sedeid = (int)($_POST['instalacion_sedeid'] ?? 0);
        $clase  = (string)($_POST['instalacion_clase'] ?? 'C');
        $id     = (int)($_POST['instalacion_id'] ?? 0);

        if ($sedeid <= 0 || !$this->sedeOfreceAlquiler($sedeid)) {
            return json_encode(['codigo' => '']);
        }

        return json_encode(['codigo' => $this->codigoSugerido($sedeid, $clase, $id)]);
    }

    public function guardarInstalacion(): string
    {
        $id = (int)($_POST['instalacion_id'] ?? 0);

        if ($id > 0 && !puede_editar('instalacionList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar instalaciones.', 'error');
        }
        if ($id === 0 && !puede_crear('instalacionList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear instalaciones.', 'error');
        }

        $sedeid    = (int)($_POST['instalacion_sedeid'] ?? 0);
        $clase     = ($_POST['instalacion_clase'] ?? 'C') === 'R' ? 'R' : 'C';
        $codigo    = trim((string)($_POST['instalacion_codigo'] ?? ''));
        $nombre    = trim((string)($_POST['instalacion_nombre'] ?? ''));
        $cubierta  = ($_POST['instalacion_cubierta'] ?? '') === 'S' ? 'S' : 'N';
        $pisoid    = (int)($_POST['instalacion_pisoid'] ?? 0);
        $capacidad = (int)($_POST['instalacion_capacidad'] ?? 0);
        $valorhora = (float)str_replace(',', '.', (string)($_POST['instalacion_valorhora'] ?? '0'));
        $detalle   = trim((string)($_POST['instalacion_detalle'] ?? ''));
        $estado    = ($_POST['instalacion_estado'] ?? 'A') === 'A' ? 'A' : 'I';

        /* El código ya no se exige: si llega en blanco se genera más abajo,
           en cuanto la sede esté validada. */
        if ($nombre === '') {
            return $this->alerta('simple', 'Faltan datos', 'El nombre es obligatorio.', 'error');
        }

        /* general_sede es MyISAM y no admite clave foránea: la referencia
           se valida aquí, junto con la regla de negocio. */
        if ($sedeid <= 0 || !$this->sedeOfreceAlquiler($sedeid)) {
            return $this->alerta('simple', 'Sede no válida',
                'La sede no existe o no está marcada como sede de alquiler. Cámbielo en el catálogo de sedes.', 'error');
        }

        if ($valorhora < 0) {
            return $this->alerta('simple', 'Tarifa no válida', 'El valor por hora no puede ser negativo.', 'error');
        }

        /* Las residencias no tienen piso ni condición de cubierta. */
        if ($clase === 'R') { $cubierta = null; $pisoid = 0; }

        /* Código en blanco: se genera aquí, no en el navegador. El
           formulario sólo muestra la propuesta; si alguien envía la
           petición sin pasar por él, el código se asigna igual. */
        if ($codigo === '') {
            $codigo = $this->codigoSugerido($sedeid, $clase, $id);
        }

        $duplicado = (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_instalacion
              WHERE instalacion_sedeid = :s AND instalacion_codigo = :c
                AND instalacion_id <> :id",
            [':s' => $sedeid, ':c' => $codigo, ':id' => $id]
        );
        /* Antes se excluían las bajas ('E'), pero la clave única de la
           tabla no las excluye: el código de una instalación dada de baja
           sigue ocupado. La comprobación decía "libre", el INSERT moría
           contra la clave y el usuario sólo veía "No fue posible crear la
           instalación", sin saber por qué. */
        if ($duplicado > 0) {
            return $this->alerta('simple', 'Código repetido',
                "Ya existe una instalación con el código {$codigo} en esa sede. "
                . "Puede ser una instalación dada de baja: el código le sigue perteneciendo. "
                . "Deje el campo en blanco y el sistema asignará el siguiente libre.", 'error');
        }

        $params = [
            ':s'  => $sedeid,     ':cl' => $clase,     ':co' => $codigo,
            ':n'  => $nombre,     ':cu' => $cubierta,  ':p'  => $pisoid > 0 ? $pisoid : null,
            ':ca' => $capacidad > 0 ? $capacidad : null,
            ':v'  => $valorhora,  ':d'  => $detalle !== '' ? $detalle : null,
            ':e'  => $estado,
        ];

        if ($id > 0) {
            $params[':id'] = $id;
            $n = $this->escribir(
                "UPDATE dsa_instalacion
                    SET instalacion_sedeid = :s, instalacion_clase = :cl,
                        instalacion_codigo = :co, instalacion_nombre = :n,
                        instalacion_cubierta = :cu, instalacion_pisoid = :p,
                        instalacion_capacidad = :ca, instalacion_valorhora = :v,
                        instalacion_detalle = :d, instalacion_estado = :e
                  WHERE instalacion_id = :id", $params
            );

            return $n >= 0
                ? $this->alerta('redireccionar', 'Instalación actualizada',
                    "{$nombre} se actualizó correctamente.", 'success', APP_URL . 'instalacionList/')
                : $this->alerta('simple', 'Error', 'No fue posible actualizar la instalación.', 'error');
        }

        $n = $this->escribir(
            "INSERT INTO dsa_instalacion
                (instalacion_sedeid, instalacion_clase, instalacion_codigo, instalacion_nombre,
                 instalacion_cubierta, instalacion_pisoid, instalacion_capacidad,
                 instalacion_valorhora, instalacion_detalle, instalacion_estado)
             VALUES (:s, :cl, :co, :n, :cu, :p, :ca, :v, :d, :e)", $params
        );

        return $n > 0
            ? $this->alerta('redireccionar', 'Instalación creada',
                "{$nombre} quedó registrada. Defina su disponibilidad en Horarios.",
                'success', APP_URL . 'instalacionList/')
            : $this->alerta('simple', 'Error', 'No fue posible crear la instalación.', 'error');
    }

    public function eliminarInstalacion(): string
    {
        if (!puede_eliminar('instalacionList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar instalaciones.', 'error');
        }

        $id = (int)($_POST['instalacion_id'] ?? 0);

        $reservas = (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_reserva
              WHERE reserva_instalacionid = :id AND reserva_estado IN ('P','C')",
            [':id' => $id]
        );
        if ($reservas > 0) {
            return $this->alerta('simple', 'Instalación con reservas',
                "No se puede eliminar: tiene {$reservas} reserva(s) vigente(s). Cancélelas o desactive la instalación.", 'error');
        }

        /* Baja lógica: conserva el histórico de reservas ya cumplidas. */
        $n = $this->escribir(
            "UPDATE dsa_instalacion SET instalacion_estado = 'E' WHERE instalacion_id = :id",
            [':id' => $id]
        );

        return $n > 0
            ? $this->alerta('recargar', 'Instalación eliminada', 'La instalación fue dada de baja.')
            : $this->alerta('simple', 'Error', 'No fue posible eliminar la instalación.', 'error');
    }

    /*======================================================================
      Disponibilidad semanal
      ====================================================================*/

    /** Nombre de los días, indexado 1=lunes … 7=domingo (ISO-8601). */

    /*======================================================================
      Estados de una reserva — EL catálogo, no una copia más

      Estaban escritos a mano en cinco sitios: reservaList, reservaDetalle,
      el panel, el mensaje de cambiarEstado() y la lista de validación. Cinco
      copias de la misma verdad significan que añadir un estado obliga a
      acordarse de cinco archivos, y que renombrar uno deja los otros cuatro
      mintiendo.

      Aquí no hay tabla de estados como en League —dsa_reserva guarda el
      código en un char(1) y cambiarlo sería una migración—, así que el
      catálogo vive en el controlador, que es lo más cerca del dato que se
      puede llegar sin tocar el esquema.

      Quien quiera el nombre en minúscula para una frase usa
      estadoReservaNombre(); quien quiera pintar una insignia usa el tono.
      ======================================================================*/

    public function estadosReserva(): array
    {
        return [
            'P' => ['nombre' => 'Pendiente',  'tono' => 'warning'],
            'C' => ['nombre' => 'Confirmada', 'tono' => 'info'],
            'U' => ['nombre' => 'Cumplida',   'tono' => 'success'],
            'X' => ['nombre' => 'Cancelada',  'tono' => 'secondary'],
        ];
    }

    /** Sólo los códigos, para validar lo que llega por POST. */
    public function codigosEstadoReserva(): array
    {
        return array_keys($this->estadosReserva());
    }

    /** El nombre en minúscula, para incrustarlo en una frase. */
    public function estadoReservaNombre(string $codigo, bool $minuscula = false): string
    {
        $n = $this->estadosReserva()[$codigo]['nombre'] ?? $codigo;
        return $minuscula ? mb_strtolower($n, 'UTF-8') : $n;
    }
    public function dias(): array
    {
        return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    }

    public function horarios(int $instalacionid): array
    {
        return $this->filas(
            "SELECT h.*, i.instalacion_nombre
               FROM dsa_horario h
               JOIN dsa_instalacion i ON i.instalacion_id = h.horario_instalacionid
              WHERE h.horario_instalacionid = :i AND h.horario_estado = 'A'
              ORDER BY h.horario_dia, h.horario_desde",
            [':i' => $instalacionid]
        );
    }

    public function guardarHorario(): string
    {
        if (!puede_crear('horarioList') && !puede_editar('horarioList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede administrar horarios.', 'error');
        }

        $id           = (int)($_POST['horario_id'] ?? 0);
        $instalacion  = (int)($_POST['horario_instalacionid'] ?? 0);
        $desde        = trim((string)($_POST['horario_desde'] ?? ''));
        $hasta        = trim((string)($_POST['horario_hasta'] ?? ''));
        $dias         = $_POST['horario_dia'] ?? [];

        if ($id > 0 && !puede_editar('horarioList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar horarios.', 'error');
        }
        if ($id === 0 && !puede_crear('horarioList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear horarios.', 'error');
        }

        if ($instalacion <= 0 || $this->instalacion($instalacion) === null) {
            return $this->alerta('simple', 'Instalación no válida', 'Seleccione una instalación.', 'error');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $desde) || !preg_match('/^\d{2}:\d{2}$/', $hasta)) {
            return $this->alerta('simple', 'Horas no válidas', 'Indique hora de inicio y fin.', 'error');
        }
        if ($hasta <= $desde) {
            return $this->alerta('simple', 'Rango no válido', 'La hora de fin debe ser posterior a la de inicio.', 'error');
        }

        /* Al editar se toca un solo día; al crear se aceptan varios de una vez. */
        $seleccion = $id > 0 ? [(int)($_POST['horario_diaunico'] ?? 0)]
                             : array_map('intval', (array)$dias);
        $seleccion = array_values(array_filter($seleccion, fn($d) => $d >= 1 && $d <= 7));

        if (!$seleccion) {
            return $this->alerta('simple', 'Sin días', 'Seleccione al menos un día de la semana.', 'error');
        }

        $nombres    = $this->dias();
        $solapados  = [];
        $guardados  = 0;

        foreach ($seleccion as $dia) {

            /* Dos franjas del mismo día no pueden cruzarse: harían ambigua
               la disponibilidad. Se solapan si empiezan antes de que la
               otra termine y terminan después de que la otra empiece. */
            $choca = (int)$this->escalar(
                "SELECT COUNT(1) FROM dsa_horario
                  WHERE horario_instalacionid = :i AND horario_dia = :d
                    AND horario_estado = 'A' AND horario_id <> :id
                    AND horario_desde < :hasta AND horario_hasta > :desde",
                [':i' => $instalacion, ':d' => $dia, ':id' => $id,
                 ':desde' => $desde, ':hasta' => $hasta]
            );

            if ($choca > 0) {
                $solapados[] = $nombres[$dia];
                continue;
            }

            if ($id > 0) {
                $this->escribir(
                    "UPDATE dsa_horario
                        SET horario_dia = :d, horario_desde = :desde, horario_hasta = :hasta
                      WHERE horario_id = :id",
                    [':d' => $dia, ':desde' => $desde, ':hasta' => $hasta, ':id' => $id]
                );
            } else {
                $this->escribir(
                    "INSERT INTO dsa_horario
                        (horario_instalacionid, horario_dia, horario_desde, horario_hasta, horario_estado)
                     VALUES (:i, :d, :desde, :hasta, 'A')",
                    [':i' => $instalacion, ':d' => $dia, ':desde' => $desde, ':hasta' => $hasta]
                );
            }

            $guardados++;
        }

        if ($guardados === 0) {
            return $this->alerta('simple', 'Franja solapada',
                'Ya existe disponibilidad que se cruza con ese rango en: ' . implode(', ', $solapados) . '.', 'error');
        }

        $texto = $guardados . ' franja(s) guardada(s).';
        if ($solapados) {
            $texto .= ' Se omitieron por solaparse: ' . implode(', ', $solapados) . '.';
        }

        return $this->alerta('recargar', 'Disponibilidad actualizada', $texto,
            $solapados ? 'warning' : 'success');
    }

    public function eliminarHorario(): string
    {
        if (!puede_eliminar('horarioList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar horarios.', 'error');
        }

        $id = (int)($_POST['horario_id'] ?? 0);

        $n = $this->escribir("DELETE FROM dsa_horario WHERE horario_id = :id", [':id' => $id]);

        return $n > 0
            ? $this->alerta('recargar', 'Franja eliminada', 'La franja de disponibilidad fue eliminada.')
            : $this->alerta('simple', 'Error', 'No fue posible eliminar la franja.', 'error');
    }

    /*======================================================================
      Bloqueos: mantenimiento y otros cierres
      ====================================================================*/

    public function bloqueos(int $instalacionid = 0, bool $soloVigentes = true): array
    {
        $sql = "SELECT b.*, i.instalacion_nombre, i.instalacion_codigo, s.sede_nombre
                  FROM dsa_bloqueo b
                  JOIN dsa_instalacion i ON i.instalacion_id = b.bloqueo_instalacionid
                  LEFT JOIN general_sede s ON s.sede_id = i.instalacion_sedeid
                 WHERE b.bloqueo_estado = 'A'";

        $params = [];
        if ($instalacionid > 0) { $sql .= " AND b.bloqueo_instalacionid = :i"; $params[':i'] = $instalacionid; }
        if ($soloVigentes)      { $sql .= " AND b.bloqueo_fin >= NOW()"; }

        $sql .= " ORDER BY b.bloqueo_inicio";

        return $this->filas($sql, $params);
    }

    public function guardarBloqueo(): string
    {
        $id = (int)($_POST['bloqueo_id'] ?? 0);

        if ($id > 0 && !puede_editar('bloqueoList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar bloqueos.', 'error');
        }
        if ($id === 0 && !puede_crear('bloqueoList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear bloqueos.', 'error');
        }

        $instalacion = (int)($_POST['bloqueo_instalacionid'] ?? 0);
        $tipo        = in_array($_POST['bloqueo_tipo'] ?? 'M', ['M','E','O'], true) ? $_POST['bloqueo_tipo'] : 'M';
        $inicio      = trim((string)($_POST['bloqueo_inicio'] ?? ''));
        $fin         = trim((string)($_POST['bloqueo_fin'] ?? ''));
        $motivo      = trim((string)($_POST['bloqueo_motivo'] ?? ''));

        if ($instalacion <= 0 || $this->instalacion($instalacion) === null) {
            return $this->alerta('simple', 'Instalación no válida', 'Seleccione una instalación.', 'error');
        }

        /* Los campos datetime-local llegan como "Y-m-dTH:i". */
        $inicio = str_replace('T', ' ', $inicio);
        $fin    = str_replace('T', ' ', $fin);

        if ($inicio === '' || $fin === '') {
            return $this->alerta('simple', 'Faltan fechas', 'Indique inicio y fin del bloqueo.', 'error');
        }
        if ($fin <= $inicio) {
            return $this->alerta('simple', 'Rango no válido', 'El fin debe ser posterior al inicio.', 'error');
        }

        /* Un bloqueo sobre reservas ya aceptadas deja al cliente sin
           instalación: se avisa en lugar de romperlas en silencio. */
        $enConflicto = (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_reserva
              WHERE reserva_instalacionid = :i
                AND reserva_estado IN ('P','C')
                AND TIMESTAMP(reserva_fecha, reserva_horainicio) < :fin
                AND TIMESTAMP(reserva_fecha, reserva_horafin)    > :inicio",
            [':i' => $instalacion, ':inicio' => $inicio, ':fin' => $fin]
        );

        $params = [':i' => $instalacion, ':t' => $tipo, ':ini' => $inicio, ':fin' => $fin,
                   ':m' => $motivo !== '' ? $motivo : null,
                   ':u' => (int)($_SESSION['usuarioid'] ?? 0) ?: null];

        if ($id > 0) {
            $params[':id'] = $id;
            $n = $this->escribir(
                "UPDATE dsa_bloqueo
                    SET bloqueo_instalacionid = :i, bloqueo_tipo = :t,
                        bloqueo_inicio = :ini, bloqueo_fin = :fin,
                        bloqueo_motivo = :m, bloqueo_usuarioid = :u
                  WHERE bloqueo_id = :id", $params);
        } else {
            $n = $this->escribir(
                "INSERT INTO dsa_bloqueo
                    (bloqueo_instalacionid, bloqueo_tipo, bloqueo_inicio, bloqueo_fin,
                     bloqueo_motivo, bloqueo_usuarioid, bloqueo_estado)
                 VALUES (:i, :t, :ini, :fin, :m, :u, 'A')", $params);
        }

        if ($n < 0) {
            return $this->alerta('simple', 'Error', 'No fue posible guardar el bloqueo.', 'error');
        }

        $texto = 'El periodo quedó bloqueado y no se podrá reservar.';
        if ($enConflicto > 0) {
            $texto .= " Atención: hay {$enConflicto} reserva(s) vigente(s) dentro de ese periodo que deberá reubicar.";
        }

        return $this->alerta('recargar', 'Bloqueo registrado', $texto,
            $enConflicto > 0 ? 'warning' : 'success');
    }

    public function eliminarBloqueo(): string
    {
        if (!puede_eliminar('bloqueoList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar bloqueos.', 'error');
        }

        $id = (int)($_POST['bloqueo_id'] ?? 0);
        $n  = $this->escribir("DELETE FROM dsa_bloqueo WHERE bloqueo_id = :id", [':id' => $id]);

        return $n > 0
            ? $this->alerta('recargar', 'Bloqueo eliminado', 'La instalación vuelve a estar disponible en ese periodo.')
            : $this->alerta('simple', 'Error', 'No fue posible eliminar el bloqueo.', 'error');
    }

    /*======================================================================
      Clientes
      ====================================================================*/

    public function clientes(string $busqueda = ''): array
    {
        $sql = "SELECT c.*,
                       COALESCE(m.monedero_saldo, 0) AS saldo,
                       (SELECT COUNT(1) FROM dsa_reserva r
                         WHERE r.reserva_clienteid = c.cliente_id
                           AND r.reserva_estado IN ('P','C')) AS reservas
                  FROM dsa_cliente c
                  LEFT JOIN dsa_monedero m ON m.monedero_clienteid = c.cliente_id
                 WHERE c.cliente_estado <> 'E'";

        $params = [];
        if ($busqueda !== '') {
            $sql .= " AND (c.cliente_nombre LIKE :b OR c.cliente_identificacion LIKE :b2)";
            $params[':b']  = '%' . $busqueda . '%';
            $params[':b2'] = '%' . $busqueda . '%';
        }

        $sql .= " ORDER BY c.cliente_nombre";

        return $this->filas($sql, $params);
    }

    /** Cuántos clientes hay (sin los dados de baja): para saber si se puede reservar sin cargarlos todos. */
    public function totalClientes(): int
    {
        return (int)$this->escalar("SELECT COUNT(1) FROM dsa_cliente WHERE cliente_estado <> 'E'");
    }

    /**
     * El buscador de clientes de la reserva.
     *
     * Antes la reserva cargaba TODOS los clientes en un <select>: con cien
     * o más, en el teléfono era una lista sin fin y sin forma de buscar, y
     * pesaba en cada carga. Ahora se pregunta al servidor por lo escrito
     * —nombre, identificación o celular— y vuelven doce como mucho.
     *
     * Sin texto devuelve los recientes: los que reservaron últimamente (y,
     * si aún no hay reservas, los últimos dados de alta), que suelen ser a
     * quien se vuelve a reservar.
     *
     * Orden: la identificación exacta primero, luego lo que empieza por lo
     * escrito, los activos antes que los inactivos y por nombre.
     */
    public function buscarClientes(): string
    {
        if (!puede_crear('reservaList') && !puede_editar('reservaList')) {
            return json_encode(['error' => 'Acceso denegado'], JSON_UNESCAPED_UNICODE);
        }
        $q = trim((string)($_POST['q'] ?? ''));
        $campos = "c.cliente_id, c.cliente_nombre, c.cliente_identificacion, c.cliente_celular, c.cliente_estado,
                   COALESCE(m.monedero_saldo, 0) AS saldo";

        if ($q === '') {
            $filas = $this->filas(
                "SELECT {$campos}, MAX(r.reserva_fecharegistro) AS ultima
                   FROM dsa_cliente c
                   LEFT JOIN dsa_monedero m ON m.monedero_clienteid = c.cliente_id
                   LEFT JOIN dsa_reserva r  ON r.reserva_clienteid  = c.cliente_id
                  WHERE c.cliente_estado = 'A'
                  GROUP BY c.cliente_id, c.cliente_nombre, c.cliente_identificacion, c.cliente_celular, c.cliente_estado, m.monedero_saldo
                  ORDER BY ultima IS NULL, ultima DESC, c.cliente_fecharegistro DESC
                  LIMIT 6");
            $modo = 'recientes';
        } else {
            $filas = $this->filas(
                "SELECT {$campos}
                   FROM dsa_cliente c
                   LEFT JOIN dsa_monedero m ON m.monedero_clienteid = c.cliente_id
                  WHERE c.cliente_estado <> 'E'
                    AND (c.cliente_nombre LIKE :todo OR c.cliente_identificacion LIKE :inicio OR c.cliente_celular LIKE :inicio2)
                  ORDER BY c.cliente_identificacion = :exacta DESC,
                           c.cliente_nombre LIKE :inicio3 DESC,
                           c.cliente_estado = 'A' DESC,
                           c.cliente_nombre
                  LIMIT 12",
                [':todo' => '%' . $q . '%', ':inicio' => $q . '%', ':inicio2' => $q . '%',
                 ':exacta' => $q, ':inicio3' => $q . '%']);
            $modo = 'busqueda';
        }

        $t = static fn($v) => html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return json_encode([
            'modo'     => $modo,
            'clientes' => array_map(static fn($c) => [
                'id'             => (int)$c['cliente_id'],
                'nombre'         => $t($c['cliente_nombre']),
                'identificacion' => $t($c['cliente_identificacion']),
                'celular'        => $t($c['cliente_celular']),
                'activo'         => $c['cliente_estado'] === 'A',
                'saldo'          => round((float)$c['saldo'], 2),
            ], $filas),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function cliente(int $id): ?array
    {
        $r = $this->filas("SELECT * FROM dsa_cliente WHERE cliente_id = :id", [':id' => $id]);
        return $r[0] ?? null;
    }

    /*----------  Tipo e identificación del cliente  ----------*/
    /*
    | El cliente es a quien se le emite el recibo o la factura, así que la
    | identificación se valida con la MISMA regla que la facturación
    | electrónica (ds_core/inc/sri.php): cédula con su dígito verificador,
    | RUC de 13 dígitos sobre una cédula válida y pasaporte libre. Antes se
    | aceptaba cualquier cadena de 5 a 20 caracteres, y una cédula con un
    | dígito cambiado sólo se descubría cuando el SRI devolvía el
    | comprobante.
    |
    | Los tipos que se guardan en cliente_tipoid: CED, RUC y PAS. Si no
    | llega o no es uno de ellos (los clientes antiguos tienen 'TDC'), se
    | deduce de la longitud, como hace sri_tipo_identificacion().
    */
    private function tipoCliente(string $tipo, string $identificacion): string
    {
        $tipo = strtoupper(trim($tipo));
        if (in_array($tipo, ['CED', 'RUC', 'PAS'], true)) {
            return $tipo;
        }
        return ['05' => 'CED', '04' => 'RUC'][sri_tipo_identificacion('', $identificacion)] ?? 'PAS';
    }

    /** '' si es válida; si no, el motivo, para mostrárselo a quien la escribe. */
    private function motivoIdentificacionNoValida(string $tipo, string $identificacion): string
    {
        if ($tipo === 'CED') {
            if (!preg_match('/^\d{10}$/', $identificacion)) { return 'La cédula tiene 10 dígitos.'; }
            return sri_identificacion_valida($identificacion, '05') ? '' : 'No es una cédula ecuatoriana válida: revise los dígitos.';
        }
        if ($tipo === 'RUC') {
            if (!preg_match('/^\d{13}$/', $identificacion)) { return 'El RUC tiene 13 dígitos.'; }
            return sri_identificacion_valida($identificacion, '04') ? '' : 'No es un RUC válido: revise los dígitos.';
        }
        /* Pasaporte: Arena alquila también a extranjeros. */
        return preg_match('/^[A-Za-z0-9\-]{5,20}$/', $identificacion) ? '' : 'Entre 5 y 20 letras o números, sin espacios.';
    }

    /**
     * El representante de la escuela con esa identificación, o null.
     *
     * Con un RUC de persona natural (la cédula seguida de 001) se busca por
     * la cédula: es la misma persona. Los textos salen sin escapar.
     */
    private function representantePorIdentificacion(string $identificacion): ?array
    {
        $cedula = preg_match('/^(\d{10})001$/', $identificacion, $m) ? $m[1] : $identificacion;
        $r = $this->filas(
            "SELECT R.repre_id, R.repre_identificacion, R.repre_primernombre, R.repre_segundonombre,
                    R.repre_apellidopaterno, R.repre_apellidomaterno, R.repre_correo, R.repre_celular,
                    R.repre_direccion,
                    (SELECT COUNT(1) FROM sujeto_alumno A WHERE A.alumno_repreid = R.repre_id AND A.alumno_estado = 'A') AS alumnos
               FROM alumno_representante R
              WHERE R.repre_identificacion IN (:i, :c) AND R.repre_estado <> 'E'
              ORDER BY R.repre_identificacion = :i2 DESC LIMIT 1",
            [':i' => $identificacion, ':c' => $cedula, ':i2' => $identificacion]);
        if (!$r) {
            return null;
        }
        $t = static fn($v) => trim(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $correo = $t($r[0]['repre_correo']);
        return [
            'id'        => (int)$r[0]['repre_id'],
            'nombre'    => preg_replace('/\s+/', ' ', $t($r[0]['repre_primernombre'] . ' ' . $r[0]['repre_segundonombre'] . ' '
                                                      . $r[0]['repre_apellidopaterno'] . ' ' . $r[0]['repre_apellidomaterno'])),
            /* «S/N» es como la escuela registra a quien no tiene correo: aquí
               no sirve para enviar recibos, así que no se copia. */
            'correo'    => filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '',
            'celular'   => preg_match('/^0\d{9}$/', $t($r[0]['repre_celular'])) ? $t($r[0]['repre_celular']) : '',
            'direccion' => $t($r[0]['repre_direccion']),
            'alumnos'   => (int)$r[0]['alumnos'],
        ];
    }

    /**
     * Lo que el formulario pregunta al salir del campo de identificación:
     * si es válida, si ya es cliente y, si no lo es, si es representante
     * de la escuela, para cargar sus datos. Sólo lectura.
     */
    public function consultarCliente(): string
    {
        if (!puede_crear('clienteList') && !puede_editar('clienteList')) {
            return json_encode(['error' => 'Acceso denegado'], JSON_UNESCAPED_UNICODE);
        }
        $identificacion = strtoupper(trim((string)($_POST['cliente_identificacion'] ?? '')));
        $propio = (int)($_POST['cliente_id'] ?? 0);
        if ($identificacion === '') {
            return json_encode(['valida' => false, 'motivo' => ''], JSON_UNESCAPED_UNICODE);
        }
        $tipo   = $this->tipoCliente((string)($_POST['cliente_tipoid'] ?? ''), $identificacion);
        $motivo = $this->motivoIdentificacionNoValida($tipo, $identificacion);

        $cli = $this->filas(
            "SELECT cliente_id, cliente_nombre, cliente_estado, cliente_correo, cliente_celular, cliente_direccion
               FROM dsa_cliente
              WHERE cliente_identificacion = :i AND cliente_id <> :id LIMIT 1",
            [':i' => $identificacion, ':id' => $propio]);

        $t = static fn($v) => html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cliente = null;
        if ($cli) {
            $baja = $cli[0]['cliente_estado'] === 'E';
            $cliente = ['id' => (int)$cli[0]['cliente_id'], 'nombre' => $t($cli[0]['cliente_nombre']), 'baja' => $baja];
            /* Dado de baja, el alta lo reactiva: se devuelven sus datos para
               no volver a escribirlos. De uno activo basta el nombre. */
            if ($baja) {
                $cliente += ['correo' => $t($cli[0]['cliente_correo']), 'celular' => $t($cli[0]['cliente_celular']),
                             'direccion' => $t($cli[0]['cliente_direccion'])];
            }
        }

        return json_encode([
            'tipo'          => $tipo,
            'valida'        => $motivo === '',
            'motivo'        => $motivo,
            'cliente'       => $cliente,
            'representante' => $cli ? null : $this->representantePorIdentificacion($identificacion),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function guardarCliente(): string
    {
        $id = (int)($_POST['cliente_id'] ?? 0);

        if ($id > 0 && !puede_editar('clienteList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar clientes.', 'error');
        }
        if ($id === 0 && !puede_crear('clienteList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear clientes.', 'error');
        }

        $identificacion = strtoupper(trim((string)($_POST['cliente_identificacion'] ?? '')));
        $nombre         = trim((string)($_POST['cliente_nombre'] ?? ''));
        $correo         = trim((string)($_POST['cliente_correo'] ?? ''));
        $celular        = trim((string)($_POST['cliente_celular'] ?? ''));
        $direccion      = trim((string)($_POST['cliente_direccion'] ?? ''));
        $estado         = ($_POST['cliente_estado'] ?? 'A') === 'A' ? 'A' : 'I';

        if ($nombre === '' || $identificacion === '') {
            return $this->alerta('simple', 'Faltan datos', 'Identificación y nombre son obligatorios.', 'error');
        }

        $tipo   = $this->tipoCliente((string)($_POST['cliente_tipoid'] ?? ''), $identificacion);
        $motivo = $this->motivoIdentificacionNoValida($tipo, $identificacion);
        if ($motivo !== '') {
            return $this->alerta('simple', 'Identificación no válida', $motivo, 'error');
        }

        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $this->alerta('simple', 'Correo no válido', 'Revise la dirección de correo.', 'error');
        }
        if ($celular !== '' && !preg_match('/^0\d{9}$/', $celular)) {
            return $this->alerta('simple', 'Celular no válido', 'Diez cifras empezando por 0, por ejemplo 0991234567.', 'error');
        }

        /* El representante sólo se enlaza si de verdad es la misma persona:
           el id llega del navegador y no se le cree sin comprobarlo. */
        $repre = null;
        $repreid = (int)($_POST['cliente_repreid'] ?? 0);
        if ($repreid > 0) {
            $r = $this->representantePorIdentificacion($identificacion);
            $repre = ($r && $r['id'] === $repreid) ? $repreid : null;
        }

        /* Un cliente dado de baja no desaparece (sigue en sus reservas), y
           su identificación bloqueaba para siempre volver a darlo de alta:
           «Cliente repetido», sin salida. Ahora el alta lo reactiva. */
        $existente = $this->filas(
            "SELECT cliente_id, cliente_estado FROM dsa_cliente
              WHERE cliente_identificacion = :i AND cliente_id <> :id LIMIT 1",
            [':i' => $identificacion, ':id' => $id]);
        $reactiva = false;
        if ($existente) {
            if ($id === 0 && $existente[0]['cliente_estado'] === 'E') {
                $id = (int)$existente[0]['cliente_id'];
                $reactiva = true;
            } else {
                return $this->alerta('simple', 'Cliente repetido',
                    "Ya existe un cliente con la identificación {$identificacion}.", 'error');
            }
        }

        $params = [':i' => $identificacion, ':n' => $nombre, ':t' => $tipo, ':r' => $repre,
                   ':c' => $correo !== '' ? $correo : null,
                   ':ce' => $celular !== '' ? $celular : null,
                   ':d' => $direccion !== '' ? $direccion : null,
                   ':e' => $estado];

        if ($id > 0) {
            $params[':id'] = $id;
            $n = $this->escribir(
                "UPDATE dsa_cliente
                    SET cliente_identificacion = :i, cliente_tipoid = :t, cliente_repreid = :r,
                        cliente_nombre = :n, cliente_correo = :c, cliente_celular = :ce,
                        cliente_direccion = :d, cliente_estado = :e
                  WHERE cliente_id = :id", $params);

            if ($n < 0) {
                return $this->alerta('simple', 'Error', 'No fue posible guardar el cliente.', 'error');
            }
            return $reactiva
                ? $this->alerta('redireccionar', 'Cliente reactivado',
                    "{$nombre} estaba dado de baja y se volvió a activar con estos datos.", 'success', $this->destinoCliente($id))
                : $this->alerta('redireccionar', 'Cliente actualizado',
                    "{$nombre} se actualizó correctamente.", 'success', APP_URL . 'clienteList/');
        }

        $n = $this->escribir(
            "INSERT INTO dsa_cliente
                (cliente_identificacion, cliente_tipoid, cliente_repreid, cliente_nombre,
                 cliente_correo, cliente_celular, cliente_direccion, cliente_estado)
             VALUES (:i, :t, :r, :n, :c, :ce, :d, :e)", $params);
        if ($n <= 0) {
            return $this->alerta('simple', 'Error', 'No fue posible crear el cliente.', 'error');
        }
        $nuevo = (int)$this->escalar("SELECT cliente_id FROM dsa_cliente WHERE cliente_identificacion = :i", [':i' => $identificacion]);

        return $this->alerta('redireccionar', 'Cliente creado',
            "{$nombre} quedó registrado.", 'success', $this->destinoCliente($nuevo));
    }

    /*
    | A dónde ir tras dar de alta un cliente. Si se vino desde una reserva
    | («Crear cliente» del buscador), de vuelta a ella con el cliente ya
    | elegido; si no, al listado. El destino sale de una lista cerrada, no
    | de una URL que mande el navegador.
    */
    private function destinoCliente(int $clienteId): string
    {
        if (($_POST['volver'] ?? '') === 'reservaForm' && $clienteId > 0) {
            return APP_URL . 'reservaForm/?cliente=' . $clienteId;
        }
        return APP_URL . 'clienteList/';
    }

    public function eliminarCliente(): string
    {
        if (!puede_eliminar('clienteList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar clientes.', 'error');
        }

        $id = (int)($_POST['cliente_id'] ?? 0);

        $vigentes = (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_reserva
              WHERE reserva_clienteid = :id AND reserva_estado IN ('P','C')",
            [':id' => $id]
        );
        if ($vigentes > 0) {
            return $this->alerta('simple', 'Cliente con reservas',
                "No se puede eliminar: tiene {$vigentes} reserva(s) vigente(s).", 'error');
        }

        $saldo = (float)$this->escalar(
            "SELECT COALESCE(monedero_saldo,0) FROM dsa_monedero WHERE monedero_clienteid = :id",
            [':id' => $id]
        );
        if ($saldo > 0) {
            return $this->alerta('simple', 'Cliente con saldo',
                'No se puede eliminar: tiene $' . number_format($saldo, 2) .
                ' en el monedero. Devuélvalo antes de dar de baja.', 'error');
        }

        $n = $this->escribir(
            "UPDATE dsa_cliente SET cliente_estado = 'E' WHERE cliente_id = :id", [':id' => $id]);

        return $n > 0
            ? $this->alerta('recargar', 'Cliente eliminado', 'El cliente fue dado de baja.')
            : $this->alerta('simple', 'Error', 'No fue posible eliminar el cliente.', 'error');
    }

    /*======================================================================
      Disponibilidad y tarifa
      ====================================================================*/

    /**
     * Tarifa por hora aplicable a una instalación en una fecha y hora.
     * Si hay una tarifa vigente para esa franja se usa; si no, la base.
     */
    public function tarifaAplicable(int $instalacionid, string $fecha, string $hora): float
    {
        $dia = (int)date('N', strtotime($fecha));

        $especial = $this->filas(
            "SELECT tarifa_valorhora FROM dsa_tarifa
              WHERE tarifa_instalacionid = :i
                AND tarifa_estado = 'A'
                AND (tarifa_dia IS NULL OR tarifa_dia = :d)
                AND tarifa_desde <= :h AND tarifa_hasta > :h2
                AND (tarifa_vigenciadesde IS NULL OR tarifa_vigenciadesde <= :f)
                AND (tarifa_vigenciahasta IS NULL OR tarifa_vigenciahasta >= :f2)
              ORDER BY tarifa_dia IS NULL, tarifa_id DESC
              LIMIT 1",
            [':i' => $instalacionid, ':d' => $dia, ':h' => $hora, ':h2' => $hora,
             ':f' => $fecha, ':f2' => $fecha]
        );

        if ($especial) {
            return (float)$especial[0]['tarifa_valorhora'];
        }

        return (float)$this->escalar(
            "SELECT instalacion_valorhora FROM dsa_instalacion WHERE instalacion_id = :i",
            [':i' => $instalacionid]
        );
    }

    /**
     * Comprueba si una instalación se puede reservar en un intervalo.
     *
     * Son tres condiciones y las tres deben cumplirse:
     *   1. El intervalo cae completo dentro de una franja de disponibilidad
     *      del día de la semana correspondiente.
     *   2. No pisa ningún bloqueo (mantenimiento, evento).
     *   3. No se cruza con otra reserva vigente de la misma instalación.
     *
     * Devuelve ['ok'=>bool, 'motivo'=>string].
     */
    public function verificarDisponibilidad(int $instalacionid, string $fecha,
                                            string $inicio, string $fin,
                                            int $excluirReserva = 0): array
    {
        $inst = $this->instalacion($instalacionid);
        if ($inst === null || $inst['instalacion_estado'] !== 'A') {
            return ['ok' => false, 'motivo' => 'La instalación no existe o está inactiva.'];
        }

        $dia     = (int)date('N', strtotime($fecha));
        $nombres = $this->dias();

        /* 1. Dentro del horario semanal */
        $cabe = (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_horario
              WHERE horario_instalacionid = :i AND horario_dia = :d
                AND horario_estado = 'A'
                AND horario_desde <= :ini AND horario_hasta >= :fin",
            [':i' => $instalacionid, ':d' => $dia, ':ini' => $inicio, ':fin' => $fin]
        );

        if ($cabe === 0) {
            return ['ok' => false,
                    'motivo' => 'Fuera del horario de atención: el ' . strtolower($nombres[$dia]) .
                                ' no hay una franja que cubra ' . $inicio . '–' . $fin . '.'];
        }

        /* 2. Sin bloqueos que se crucen */
        $bloqueo = $this->filas(
            "SELECT bloqueo_motivo, bloqueo_tipo FROM dsa_bloqueo
              WHERE bloqueo_instalacionid = :i AND bloqueo_estado = 'A'
                AND bloqueo_inicio < TIMESTAMP(:f, :fin)
                AND bloqueo_fin    > TIMESTAMP(:f2, :ini)
              LIMIT 1",
            [':i' => $instalacionid, ':f' => $fecha, ':fin' => $fin,
             ':f2' => $fecha, ':ini' => $inicio]
        );

        if ($bloqueo) {
            $motivo = $bloqueo[0]['bloqueo_motivo'] ?: 'mantenimiento programado';
            return ['ok' => false, 'motivo' => 'La instalación está bloqueada en ese periodo: ' . $motivo . '.'];
        }

        /* 3. Sin cruce con otra reserva vigente */
        $ocupada = $this->filas(
            "SELECT r.reserva_codigo, r.reserva_horainicio, r.reserva_horafin
               FROM dsa_reserva r
              WHERE r.reserva_instalacionid = :i
                AND r.reserva_fecha = :f
                AND r.reserva_estado IN ('P','C')
                AND r.reserva_id <> :ex
                AND r.reserva_horainicio < :fin
                AND r.reserva_horafin    > :ini
              LIMIT 1",
            [':i' => $instalacionid, ':f' => $fecha, ':ex' => $excluirReserva,
             ':fin' => $fin, ':ini' => $inicio]
        );

        if ($ocupada) {
            return ['ok' => false,
                    'motivo' => 'Ya hay una reserva (' . $ocupada[0]['reserva_codigo'] . ') de ' .
                                substr($ocupada[0]['reserva_horainicio'], 0, 5) . ' a ' .
                                substr($ocupada[0]['reserva_horafin'], 0, 5) . '.'];
        }

        return ['ok' => true, 'motivo' => ''];
    }

    /** Duración de cada bloque que ofrece el formulario de reserva, en minutos. */
    const BLOQUE_MINUTOS = 60;

    /**
     * Los bloques reservables de una instalación en una fecha.
     *
     * POR QUE EXISTE
     *
     * El formulario pedía «Desde» y «Hasta» como horas libres: el usuario
     * escribía una hora cualquiera y sólo al guardar se enteraba de que
     * caía fuera de horario, sobre otra reserva o sobre un mantenimiento.
     * Aquí se calcula, con las MISMAS tres reglas de verificarDisponibilidad(),
     * qué bloques del día se pueden elegir, para que la pantalla sólo ofrezca
     * esos. El servidor sigue verificando al guardar: esto guía, no autoriza.
     *
     * Cada franja semanal se parte en bloques de BLOQUE_MINUTOS desde su
     * inicio; el último puede ser más corto si la franja no es múltiplo.
     * Estado de cada bloque, por prioridad: ocupado, bloqueado, pasado, libre.
     * El índice de franja viaja en cada bloque porque una reserva debe caber
     * ENTERA en una sola franja (regla 1 de verificarDisponibilidad).
     */
    public function disponibilidadDia(int $instalacionid, string $fecha, int $excluirReserva = 0): array
    {
        $inst = $this->instalacion($instalacionid);
        if ($inst === null || $inst['instalacion_estado'] !== 'A') {
            return ['ok' => false, 'motivo' => 'La instalación no existe o está inactiva.'];
        }

        $dia     = (int)date('N', strtotime($fecha));
        $nombres = $this->dias();
        $aMin    = static function (string $t): int {
            $p = explode(':', $t);
            return (int)$p[0] * 60 + (int)($p[1] ?? 0);
        };
        $aHora   = static fn (int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        $franjas = $this->filas(
            "SELECT horario_desde, horario_hasta FROM dsa_horario
              WHERE horario_instalacionid = :i AND horario_dia = :d AND horario_estado = 'A'
              ORDER BY horario_desde",
            [':i' => $instalacionid, ':d' => $dia]
        );

        $reservas = array_map(static fn ($r) => [
                'ini' => $aMin($r['reserva_horainicio']), 'fin' => $aMin($r['reserva_horafin']),
                'codigo' => $r['reserva_codigo']],
            $this->filas(
                "SELECT reserva_codigo, reserva_horainicio, reserva_horafin FROM dsa_reserva
                  WHERE reserva_instalacionid = :i AND reserva_fecha = :f
                    AND reserva_estado IN ('P','C') AND reserva_id <> :ex",
                [':i' => $instalacionid, ':f' => $fecha, ':ex' => $excluirReserva]
            ));

        /* Los bloqueos son intervalos de fecha y hora: se recortan al día. */
        $inicioDia = strtotime($fecha . ' 00:00:00');
        $bloqueos  = array_map(static fn ($b) => [
                'ini' => max(0, intdiv(strtotime($b['bloqueo_inicio']) - $inicioDia, 60)),
                'fin' => min(1440, intdiv(strtotime($b['bloqueo_fin']) - $inicioDia, 60)),
                'motivo' => $b['bloqueo_motivo'] ?: 'Mantenimiento programado'],
            $this->filas(
                "SELECT bloqueo_inicio, bloqueo_fin, bloqueo_motivo FROM dsa_bloqueo
                  WHERE bloqueo_instalacionid = :i AND bloqueo_estado = 'A'
                    AND bloqueo_inicio < DATE_ADD(:f, INTERVAL 1 DAY)
                    AND bloqueo_fin    > :f2",
                [':i' => $instalacionid, ':f' => $fecha, ':f2' => $fecha]
            ));

        $hoy   = date('Y-m-d');
        $ahora = $fecha === $hoy ? (int)date('G') * 60 + (int)date('i') : null;

        $bloques = [];
        foreach ($franjas as $k => $f) {
            $desde = $aMin($f['horario_desde']);
            $hasta = $aMin($f['horario_hasta']);
            for ($t = $desde; $t < $hasta; $t += self::BLOQUE_MINUTOS) {
                $e = min($t + self::BLOQUE_MINUTOS, $hasta);
                $estado = 'libre'; $detalle = '';

                foreach ($reservas as $r) {
                    if ($r['ini'] < $e && $r['fin'] > $t) { $estado = 'ocupado'; $detalle = 'Reserva ' . $r['codigo']; break; }
                }
                if ($estado === 'libre') {
                    foreach ($bloqueos as $b) {
                        if ($b['ini'] < $e && $b['fin'] > $t) { $estado = 'bloqueado'; $detalle = $b['motivo']; break; }
                    }
                }
                if ($estado === 'libre' && ($fecha < $hoy || ($ahora !== null && $t < $ahora))) {
                    $estado = 'pasado'; $detalle = 'Hora ya transcurrida';
                }

                $bloques[] = [
                    'franja'  => $k,
                    'desde'   => $aHora($t),
                    'hasta'   => $aHora($e),
                    'estado'  => $estado,
                    'detalle' => $detalle,
                    'tarifa'  => $this->tarifaAplicable($instalacionid, $fecha, $aHora($t)),
                ];
            }
        }

        return [
            'ok'        => true,
            'dia'       => $dia,
            'diaNombre' => $nombres[$dia],
            'franjas'   => array_map(static fn ($f) => [substr($f['horario_desde'], 0, 5), substr($f['horario_hasta'], 0, 5)], $franjas),
            'bloques'   => $bloques,
            'libres'    => count(array_filter($bloques, static fn ($b) => $b['estado'] === 'libre')),
        ];
    }

    /**
     * Puerta AJAX de disponibilidadDia(). Sólo lee, y la usa quien puede
     * crear o editar reservas: el formulario es su único consumidor.
     * Se llama «consultar…» para que el guardián la clasifique como lectura.
     */
    public function consultarDisponibilidad(): string
    {
        if (!puede_crear('reservaList') && !puede_editar('reservaList')) {
            return json_encode(['ok' => false, 'motivo' => 'Su rol no puede reservar.'], JSON_UNESCAPED_UNICODE);
        }

        $inst    = (int)($_POST['instalacion_id'] ?? 0);
        $fecha   = trim((string)($_POST['fecha'] ?? ''));
        $excluir = (int)($_POST['reserva_id'] ?? 0);

        $p = explode('-', $fecha);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !checkdate((int)$p[1], (int)$p[2], (int)$p[0])) {
            return json_encode(['ok' => false, 'motivo' => 'Fecha no válida.'], JSON_UNESCAPED_UNICODE);
        }

        return json_encode($this->disponibilidadDia($inst, $fecha, $excluir), JSON_UNESCAPED_UNICODE);
    }

    /*======================================================================
      Reservas
      ====================================================================*/

    /**
     * El WHERE de las reservas, en UN solo sitio.
     *
     * Estaba escrito dos veces —en reservas() y en contarReservas()— con
     * las mismas cinco condiciones copiadas. Dos copias de un filtro es
     * peor que dos copias de cualquier otra cosa: el día que diverjan, el
     * recuento del paginador dejará de corresponder con las filas que se
     * ven, y nadie lo notará hasta que falte una reserva en la última
     * página.
     *
     * Devuelve el fragmento SQL y sus parámetros, para que las dos
     * consultas partan literalmente del mismo texto.
     */
    private function filtroReservas(array $filtros): array
    {
        $sql = '';
        $params = [];

        if (!empty($filtros['instalacion'])) {
            $sql .= " AND r.reserva_instalacionid = :i"; $params[':i'] = (int)$filtros['instalacion'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND r.reserva_estado = :e";        $params[':e'] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= " AND r.reserva_fecha >= :d";        $params[':d'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $sql .= " AND r.reserva_fecha <= :h";        $params[':h'] = $filtros['hasta'];
        }
        if (!empty($filtros['saldo'])) {
            /* Una cancelada no se cobra: su saldo no es deuda. */
            $sql .= " AND r.reserva_saldo > 0 AND r.reserva_estado <> 'X'";
        }

        return [$sql, $params];
    }

    /**
     * Una PÁGINA de reservas.
     *
     * PAGINACIÓN DE VERDAD, NO UN TOPE
     *
     * Antes se cortaba en 500 filas y se avisaba de que faltaban: honesto,
     * pero dejaba fuera de alcance todo lo que hubiera detrás. Ahora se
     * navega. Con la ventana por defecto del mes son 928 reservas: 19
     * páginas alcanzables en vez de 500 filas y un aviso.
     *
     * OFFSET y no cursor: son 5.851 filas y el ORDER BY va por
     * reserva_fecha, que está indexada. Un cursor seria mas eficiente a
     * partir de cientos de miles; aqui solo anadiria complejidad.
     *
     * LIMIT no admite parámetro en una sentencia preparada de MySQL, asi
     * que los dos numeros se acotan en PHP y se interpolan ya convertidos
     * a entero. La alternativa —concatenar lo que llegue— es justo lo que
     * no se hace.
     */
    public function reservas(array $filtros = [], int $pagina = 1, int $porPagina = self::POR_PAGINA): array
    {
        $porPagina = max(1, min(200, $porPagina));
        $pagina    = max(1, $pagina);
        $desde     = ($pagina - 1) * $porPagina;

        /*
        | LAS COLUMNAS, UNA A UNA
        |
        | Era «SELECT r.*» mas seis campos: 23 columnas por fila cuando la
        | pantalla usa 14. Traia observacion, motivo, usuario, las dos marcas
        | de tiempo y los identificadores de bloqueo y sede, que nadie pinta,
        | y ademas instalacion_codigo e instalacion_clase, que tampoco se
        | usan en esta lista —se comprobo en la vista antes de quitarlos.
        |
        | Y un «*» se lleva por delante cualquier columna que se anada
        | manana, incluida una que no deba salir de la base.
        */
        [$where, $params] = $this->filtroReservas($filtros);

        $sql = "SELECT r.reserva_id, r.reserva_codigo, r.reserva_fecha,
                       r.reserva_horainicio, r.reserva_horafin, r.reserva_horas,
                       r.reserva_total, r.reserva_abonado, r.reserva_saldo,
                       r.reserva_estado,
                       c.cliente_nombre, c.cliente_identificacion,
                       i.instalacion_nombre,
                       s.sede_nombre
                  FROM dsa_reserva     r
                  JOIN dsa_cliente     c ON c.cliente_id     = r.reserva_clienteid
                  JOIN dsa_instalacion i ON i.instalacion_id = r.reserva_instalacionid
                  LEFT JOIN general_sede s ON s.sede_id      = r.reserva_sedeid
                 WHERE 1 = 1" . $where
             . " ORDER BY r.reserva_fecha DESC, r.reserva_horainicio DESC, r.reserva_id DESC"
             . " LIMIT " . (int)$porPagina . " OFFSET " . (int)$desde;

        return $this->filas($sql, $params);
    }

    /** Cuántas reservas casan con el filtro, sin traerlas. */
    public function contarReservas(array $filtros = []): int
    {
        [$where, $params] = $this->filtroReservas($filtros);

        return (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_reserva r WHERE 1 = 1" . $where, $params);
    }

    /**
     * Los totales de TODO el filtro, no de la página visible.
     *
     * Al paginar, el pie de la tabla pasó a sumar sólo las cincuenta filas
     * que se ven mientras seguía rotulado «Totales». Un total que no totaliza
     * es peor que no tenerlo: quien lo lea creerá que la cartera de un mes
     * son doscientos dólares.
     *
     * Se calcula en la base y sobre el mismo filtro que la lista, con el
     * mismo fragmento WHERE, para que las tres cifras —filas, total y
     * saldo— no puedan discrepar entre sí.
     */
    public function totalesReservas(array $filtros = []): array
    {
        [$where, $params] = $this->filtroReservas($filtros);

        $f = $this->filas(
            /* Las canceladas se listan pero no suman: ni se facturan ni se
               cobran. Contarlas inflaba el «por cobrar» con dinero que
               nadie debe. Mismo criterio que el panel (economiaMes) e
               Insights. */
            "SELECT COALESCE(SUM(CASE WHEN r.reserva_estado <> 'X' THEN r.reserva_total END), 0)   AS total,
                    COALESCE(SUM(CASE WHEN r.reserva_estado <> 'X' THEN r.reserva_abonado END), 0) AS abonado,
                    COALESCE(SUM(CASE WHEN r.reserva_estado <> 'X' THEN r.reserva_saldo END), 0)   AS saldo
               FROM dsa_reserva r
              WHERE 1 = 1" . $where, $params)[0] ?? [];

        return [
            'total'   => (float)($f['total']   ?? 0),
            'abonado' => (float)($f['abonado'] ?? 0),
            'saldo'   => (float)($f['saldo']   ?? 0),
        ];
    }
    public function reserva(int $id): ?array
    {
        $r = $this->filas(
            "SELECT r.*, c.cliente_nombre, c.cliente_identificacion,
                    i.instalacion_nombre, i.instalacion_codigo
               FROM dsa_reserva r
               JOIN dsa_cliente c ON c.cliente_id = r.reserva_clienteid
               JOIN dsa_instalacion i ON i.instalacion_id = r.reserva_instalacionid
              WHERE r.reserva_id = :id", [':id' => $id]);

        return $r[0] ?? null;
    }

    /** Código legible y correlativo por año: RES-2026-00001 */
    private function siguienteCodigo(): string
    {
        $anio = date('Y');

        $ultimo = (string)$this->escalar(
            "SELECT reserva_codigo FROM dsa_reserva
              WHERE reserva_codigo LIKE :p
              ORDER BY reserva_id DESC LIMIT 1",
            [':p' => 'RES-' . $anio . '-%']
        );

        $n = $ultimo !== '' ? ((int)substr($ultimo, -5)) + 1 : 1;

        return sprintf('RES-%s-%05d', $anio, $n);
    }

    public function guardarReserva(): string
    {
        $id = (int)($_POST['reserva_id'] ?? 0);

        if ($id > 0 && !puede_editar('reservaList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar reservas.', 'error');
        }
        if ($id === 0 && !puede_crear('reservaList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear reservas.', 'error');
        }

        $cliente     = (int)($_POST['reserva_clienteid'] ?? 0);
        $instalacion = (int)($_POST['reserva_instalacionid'] ?? 0);
        $fecha       = trim((string)($_POST['reserva_fecha'] ?? ''));
        $inicio      = trim((string)($_POST['reserva_horainicio'] ?? ''));
        $fin         = trim((string)($_POST['reserva_horafin'] ?? ''));
        $observacion = trim((string)($_POST['reserva_observacion'] ?? ''));

        if ($this->cliente($cliente) === null) {
            return $this->alerta('simple', 'Cliente no válido', 'Seleccione un cliente.', 'error');
        }

        $inst = $this->instalacion($instalacion);
        if ($inst === null) {
            return $this->alerta('simple', 'Instalación no válida', 'Seleccione una instalación.', 'error');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
            || !preg_match('/^\d{2}:\d{2}$/', $inicio)
            || !preg_match('/^\d{2}:\d{2}$/', $fin)) {
            return $this->alerta('simple', 'Datos incompletos', 'Indique fecha, hora de inicio y hora de fin.', 'error');
        }

        if ($fin <= $inicio) {
            return $this->alerta('simple', 'Rango no válido', 'La hora de fin debe ser posterior a la de inicio.', 'error');
        }

        /* Las tres comprobaciones de disponibilidad. */
        $disp = $this->verificarDisponibilidad($instalacion, $fecha, $inicio, $fin, $id);
        if (!$disp['ok']) {
            return $this->alerta('simple', 'No disponible', $disp['motivo'], 'error');
        }

        /* El importe se congela al reservar: un cambio posterior de tarifa
           no altera lo ya pactado con el cliente. */
        $horas     = round((strtotime($fin) - strtotime($inicio)) / 3600, 2);
        $valorhora = $this->tarifaAplicable($instalacion, $fecha, $inicio);
        $total     = round($horas * $valorhora, 2);

        if ($total <= 0) {
            return $this->alerta('simple', 'Tarifa no definida',
                'La instalación no tiene tarifa por hora. Configúrela antes de reservar.', 'error');
        }

        if ($id > 0) {
            $actual = $this->reserva($id);
            if ($actual === null) {
                return $this->alerta('simple', 'Error', 'La reserva no existe.', 'error');
            }

            /* Reducir el total por debajo de lo ya abonado dejaría un saldo
               negativo: hay que devolver primero. */
            if ($total < (float)$actual['reserva_abonado']) {
                return $this->alerta('simple', 'Importe menor a lo abonado',
                    'El cliente ya abonó $' . number_format((float)$actual['reserva_abonado'], 2) .
                    '. Devuelva la diferencia antes de reducir la reserva.', 'error');
            }

            $n = $this->escribir(
                "UPDATE dsa_reserva
                    SET reserva_clienteid = :c, reserva_instalacionid = :i,
                        reserva_sedeid = :s, reserva_fecha = :f,
                        reserva_horainicio = :ini, reserva_horafin = :fin,
                        reserva_horas = :h, reserva_valorhora = :v,
                        reserva_total = :t,
                        reserva_observacion = :o
                  WHERE reserva_id = :id",
                /* reserva_saldo ya no se escribe: es columna generada
                   (total - abonado) desde la migración 061. La base la
                   recalcula sola al cambiar el total, y escribirla daría
                   error 3105. Con ella desaparece el parámetro :t2. */
                [':c' => $cliente, ':i' => $instalacion, ':s' => (int)$inst['instalacion_sedeid'],
                 ':f' => $fecha, ':ini' => $inicio, ':fin' => $fin, ':h' => $horas,
                 ':v' => $valorhora, ':t' => $total,
                 ':o' => $observacion !== '' ? $observacion : null, ':id' => $id]
            );

            return $n >= 0
                ? $this->alerta('redireccionar', 'Reserva actualizada',
                    'La reserva se actualizó correctamente.', 'success', APP_URL . 'reservaList/')
                : $this->alerta('simple', 'Error', 'No fue posible actualizar la reserva.', 'error');
        }

        $codigo = $this->siguienteCodigo();

        $n = $this->escribir(
            "INSERT INTO dsa_reserva
                (reserva_codigo, reserva_clienteid, reserva_instalacionid, reserva_sedeid,
                 reserva_fecha, reserva_horainicio, reserva_horafin, reserva_horas,
                 reserva_valorhora, reserva_total, reserva_abonado,
                 reserva_estado, reserva_observacion, reserva_usuarioid)
             VALUES (:cod, :c, :i, :s, :f, :ini, :fin, :h, :v, :t, 0, 'P', :o, :u)",
            /* Sin reserva_saldo: columna generada desde la migración 061.
               Nace valiendo el total, porque abonado nace en 0. */
            [':cod' => $codigo, ':c' => $cliente, ':i' => $instalacion,
             ':s' => (int)$inst['instalacion_sedeid'], ':f' => $fecha,
             ':ini' => $inicio, ':fin' => $fin, ':h' => $horas, ':v' => $valorhora,
             ':t' => $total,
             ':o' => $observacion !== '' ? $observacion : null,
             ':u' => (int)($_SESSION['usuarioid'] ?? 0) ?: null]
        );

        return $n > 0
            ? $this->alerta('redireccionar', 'Reserva creada',
                "{$codigo} · {$horas} h × \$" . number_format($valorhora, 2) .
                " = \$" . number_format($total, 2) . ". Registre el abono desde el detalle.",
                'success', APP_URL . 'reservaList/')
            : $this->alerta('simple', 'Error', 'No fue posible crear la reserva.', 'error');
    }

    public function cambiarEstadoReserva(): string
    {
        if (!puede_editar('reservaList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede modificar reservas.', 'error');
        }

        $id     = (int)($_POST['reserva_id'] ?? 0);
        $estado = (string)($_POST['reserva_estado'] ?? '');

        /* El catálogo manda también aquí: esta lista era una copia más de
           los códigos y podía quedarse atrás al añadir un estado. */
        if (!in_array($estado, $this->codigosEstadoReserva(), true)) {
            return $this->alerta('simple', 'Estado no válido', 'El estado indicado no existe.', 'error');
        }

        $r = $this->reserva($id);
        if ($r === null) {
            return $this->alerta('simple', 'Error', 'La reserva no existe.', 'error');
        }

        if ($estado === $r['reserva_estado']) {
            return $this->alerta('simple', 'Sin cambios',
                "{$r['reserva_codigo']} ya está " . $this->estadoReservaNombre($estado, true) . '.', 'info');
        }

        /*
        | REACTIVAR UNA RESERVA CANCELADA
        |
        | Cancelar libera la franja, así que mientras estuvo cancelada otra
        | persona pudo reservar esa misma hora, o pudo programarse un
        | mantenimiento. Devolverla a la vida sin mirar dejaba dos reservas
        | en la misma cancha a la misma hora. Se vuelve a pasar por las
        | mismas tres comprobaciones que al crearla (excluyéndose a sí misma).
        |
        | Sólo vuelve a Pendiente o Confirmada: «Cumplida» se marca sobre una
        | reserva viva, no se salta desde una cancelada.
        */
        if ($r['reserva_estado'] === 'X') {
            if (!in_array($estado, ['P', 'C'], true)) {
                return $this->alerta('simple', 'Estado no permitido',
                    'Una reserva cancelada sólo puede volver a Pendiente o Confirmada.', 'error');
            }
            $disp = $this->verificarDisponibilidad(
                (int)$r['reserva_instalacionid'], (string)$r['reserva_fecha'],
                substr((string)$r['reserva_horainicio'], 0, 5), substr((string)$r['reserva_horafin'], 0, 5),
                (int)$r['reserva_id']);
            if (!$disp['ok']) {
                return $this->alerta('simple', 'No se puede reactivar',
                    $disp['motivo'] . ' Cree una reserva nueva en otro horario.', 'error');
            }
        }

        /* Cancelar con dinero ya abonado exige decidir qué se hace con él;
           se bloquea aquí para que no quede un abono huérfano. */
        if ($estado === 'X' && (float)$r['reserva_abonado'] > 0) {
            return $this->alerta('simple', 'Reserva con abonos',
                'Tiene $' . number_format((float)$r['reserva_abonado'], 2) .
                ' abonados. Devuelva el importe o páselo al monedero antes de cancelar.', 'error');
        }

        $n = $this->escribir(
            "UPDATE dsa_reserva SET reserva_estado = :e WHERE reserva_id = :id",
            [':e' => $estado, ':id' => $id]);

        /* Del catálogo, en minúscula, para que quepa dentro de la frase. */
        $nombre = $this->estadoReservaNombre($estado, true);

        $texto = $r['reserva_estado'] === 'X'
            ? "{$r['reserva_codigo']} se reactivó y quedó como {$nombre}. El horario seguía libre y vuelve a estar reservado."
            : "{$r['reserva_codigo']} quedó como {$nombre}.";

        return $n >= 0
            ? $this->alerta('recargar', $r['reserva_estado'] === 'X' ? 'Reserva reactivada' : 'Reserva ' . $nombre, $texto)
            : $this->alerta('simple', 'Error', 'No fue posible cambiar el estado.', 'error');
    }

    /*======================================================================
      Monedero
      ====================================================================*/

    /** Monedero del cliente; lo crea si aún no existe. Devuelve su id. */
    private function asegurarMonedero(int $clienteid): int
    {
        $id = (int)$this->escalar(
            "SELECT monedero_id FROM dsa_monedero WHERE monedero_clienteid = :c",
            [':c' => $clienteid]
        );

        if ($id > 0) {
            return $id;
        }

        $this->escribir(
            "INSERT INTO dsa_monedero (monedero_clienteid, monedero_saldo) VALUES (:c, 0)",
            [':c' => $clienteid]
        );

        return (int)$this->escalar(
            "SELECT monedero_id FROM dsa_monedero WHERE monedero_clienteid = :c",
            [':c' => $clienteid]
        );
    }

    /**
     * Aplica un movimiento al monedero dentro de la transacción en curso.
     *
     * Deja constancia del saldo antes y después, de modo que el saldo
     * actual siempre se pueda auditar recorriendo el libro mayor. Lanza
     * excepción si el saldo no alcanza: quien llama está dentro de una
     * transacción y debe deshacerla.
     */
    private function moverMonedero(PDO $con, int $monederoId, string $tipo, string $origen,
                                   float $valor, array $extra = []): void
    {
        $stmt = $con->prepare(
            "SELECT monedero_saldo FROM dsa_monedero WHERE monedero_id = :m FOR UPDATE");
        $stmt->execute([':m' => $monederoId]);
        $saldoAnterior = (float)$stmt->fetchColumn();

        $saldoNuevo = $tipo === 'I' ? $saldoAnterior + $valor : $saldoAnterior - $valor;

        if ($saldoNuevo < 0) {
            throw new \RuntimeException(
                'Saldo insuficiente en el monedero: dispone de $' . number_format($saldoAnterior, 2) . '.');
        }

        $con->prepare("UPDATE dsa_monedero SET monedero_saldo = :s WHERE monedero_id = :m")
            ->execute([':s' => $saldoNuevo, ':m' => $monederoId]);

        $con->prepare(
            "INSERT INTO dsa_monedero_movimiento
                (movimiento_monederoid, movimiento_tipo, movimiento_origen, movimiento_valor,
                 movimiento_saldoanterior, movimiento_saldonuevo, movimiento_reservaid,
                 movimiento_pagoid, movimiento_referencia, movimiento_detalle,
                 movimiento_usuarioid, movimiento_fecha)
             VALUES (:m, :t, :o, :v, :sa, :sn, :r, :p, :ref, :d, :u, CURDATE())"
        )->execute([
            ':m' => $monederoId, ':t' => $tipo, ':o' => $origen, ':v' => $valor,
            ':sa' => $saldoAnterior, ':sn' => $saldoNuevo,
            ':r' => $extra['reserva'] ?? null, ':p' => $extra['pago'] ?? null,
            ':ref' => $extra['referencia'] ?? null, ':d' => $extra['detalle'] ?? null,
            ':u' => (int)($_SESSION['usuarioid'] ?? 0) ?: null,
        ]);
    }

    public function monederos(): array
    {
        return $this->filas(
            "SELECT m.monedero_id, m.monedero_saldo, c.cliente_id,
                    c.cliente_nombre, c.cliente_identificacion,
                    (SELECT COUNT(1) FROM dsa_monedero_movimiento mv
                      WHERE mv.movimiento_monederoid = m.monedero_id) AS movimientos,
                    (SELECT MAX(mv2.movimiento_fecha) FROM dsa_monedero_movimiento mv2
                      WHERE mv2.movimiento_monederoid = m.monedero_id) AS ultimo
               FROM dsa_monedero m
               JOIN dsa_cliente  c ON c.cliente_id = m.monedero_clienteid
              WHERE c.cliente_estado <> 'E'
              ORDER BY m.monedero_saldo DESC, c.cliente_nombre"
        );
    }

    public function movimientos(int $clienteid): array
    {
        return $this->filas(
            /* registro_ts: el instante del registro como número. Un TIMESTAMP
               se lee en la zona horaria de la sesión de MySQL, que en un
               hosting suele ser UTC: la hora saldría cinco horas adelantada.
               El número no depende de esa zona y PHP lo pinta en la de
               Ecuador (config/app.php). */
            "SELECT mv.*, r.reserva_codigo, UNIX_TIMESTAMP(mv.movimiento_fecharegistro) AS registro_ts
               FROM dsa_monedero_movimiento mv
               JOIN dsa_monedero m ON m.monedero_id = mv.movimiento_monederoid
               LEFT JOIN dsa_reserva r ON r.reserva_id = mv.movimiento_reservaid
              WHERE m.monedero_clienteid = :c
              ORDER BY mv.movimiento_id DESC",
            [':c' => $clienteid]
        );
    }

    public function saldoMonedero(int $clienteid): float
    {
        return (float)$this->escalar(
            "SELECT COALESCE(monedero_saldo, 0) FROM dsa_monedero WHERE monedero_clienteid = :c",
            [':c' => $clienteid]
        );
    }

    /** Transferencia anticipada o ajuste que suma saldo a favor. */
    public function ingresoMonedero(): string
    {
        if (!puede_crear('monederoList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede registrar movimientos.', 'error');
        }

        $cliente    = (int)($_POST['cliente_id'] ?? 0);
        $valor      = round((float)str_replace(',', '.', (string)($_POST['valor'] ?? '0')), 2);
        $origen     = in_array($_POST['origen'] ?? 'TRA', ['TRA','AJU'], true) ? $_POST['origen'] : 'TRA';
        $referencia = trim((string)($_POST['referencia'] ?? ''));
        $detalle    = trim((string)($_POST['detalle'] ?? ''));

        if ($this->cliente($cliente) === null) {
            return $this->alerta('simple', 'Cliente no válido', 'Seleccione un cliente.', 'error');
        }
        if ($valor <= 0) {
            return $this->alerta('simple', 'Valor no válido', 'El importe debe ser mayor que cero.', 'error');
        }
        if ($origen === 'TRA' && $referencia === '') {
            return $this->alerta('simple', 'Falta la referencia',
                'Indique el número o comprobante de la transferencia.', 'error');
        }

        $con = $this->con();
        if ($con === null) {
            return $this->alerta('simple', 'Error', 'Sin conexión a la base de datos.', 'error');
        }

        try {
            $con->beginTransaction();
            $mon = $this->asegurarMonedero($cliente);
            $this->moverMonedero($con, $mon, 'I', $origen, $valor,
                ['referencia' => $referencia !== '' ? $referencia : null,
                 'detalle'    => $detalle !== '' ? $detalle : null]);
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) $con->rollBack();
            return $this->alerta('simple', 'No se pudo registrar', $e->getMessage(), 'error');
        }

        return $this->alerta('recargar', 'Saldo acreditado',
            '$' . number_format($valor, 2) . ' ingresaron al monedero del cliente.');
    }

    /**
     * Egreso: salida de dinero del monedero a pedido del cliente.
     *
     * Se registra con origen 'DEV' en el libro mayor. El saldo no puede
     * quedar negativo; moverMonedero() lanza excepción si no alcanza y la
     * transacción se deshace.
     */
    public function egresoMonedero(): string
    {
        if (!puede_eliminar('monederoList') && !puede_editar('monederoList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede retirar saldo.', 'error');
        }

        $cliente = (int)($_POST['cliente_id'] ?? 0);
        $valor   = round((float)str_replace(',', '.', (string)($_POST['valor'] ?? '0')), 2);
        $detalle = trim((string)($_POST['detalle'] ?? ''));

        if ($this->cliente($cliente) === null) {
            return $this->alerta('simple', 'Cliente no válido', 'Seleccione un cliente.', 'error');
        }
        if ($valor <= 0) {
            return $this->alerta('simple', 'Valor no válido', 'El importe debe ser mayor que cero.', 'error');
        }

        $con = $this->con();
        if ($con === null) {
            return $this->alerta('simple', 'Error', 'Sin conexión a la base de datos.', 'error');
        }

        try {
            $con->beginTransaction();
            $mon = $this->asegurarMonedero($cliente);
            $this->moverMonedero($con, $mon, 'E', 'DEV', $valor,
                ['detalle' => $detalle !== '' ? $detalle : 'Egreso a pedido del cliente']);
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) $con->rollBack();
            return $this->alerta('simple', 'No se pudo registrar el egreso', $e->getMessage(), 'error');
        }

        return $this->alerta('recargar', 'Egreso registrado',
            '$' . number_format($valor, 2) . ' salieron del monedero del cliente.');
    }

    /*======================================================================
      Pagos y abonos de una reserva
      ====================================================================*/

    public function pagos(int $reservaid): array
    {
        return $this->filas(
            "SELECT p.*, f.forma_nombre, f.forma_codigo
               FROM dsa_pago p
               JOIN dsa_forma_ingreso f ON f.forma_id = p.pago_formaid
              WHERE p.pago_reservaid = :r AND p.pago_estado = 'A'
              ORDER BY p.pago_id",
            [':r' => $reservaid]
        );
    }

    /**
     * Los abonos de varias reservas de una vez, agrupados por reserva.
     * Para el listado: una consulta para la página entera, no una por fila.
     */
    public function pagosDeReservas(array $reservaIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $reservaIds)));
        if (!$ids) { return []; }

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $con = $this->con();
        if ($con === null) { return []; }
        $st = $con->prepare(
            "SELECT p.pago_id, p.pago_reservaid, p.pago_fecha, p.pago_valor, f.forma_nombre
               FROM dsa_pago p
               JOIN dsa_forma_ingreso f ON f.forma_id = p.pago_formaid
              WHERE p.pago_reservaid IN ($marcas) AND p.pago_estado = 'A'
              ORDER BY p.pago_id");
        $st->execute($ids);

        $agrupados = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $p) {
            $p['recibo_numero'] = $this->reciboNumero((int)$p['pago_id']);
            $agrupados[(int)$p['pago_reservaid']][] = $p;
        }
        return $agrupados;
    }

    /*======================================================================
      Recibo de cada pago: ver, descargar, enviar por correo o WhatsApp
      ====================================================================*/

    /*
    | dsa_pago no guarda un número de recibo y no hace falta añadirlo: se
    | deriva del id, que es único, no cambia y crece con el tiempo. «RA» lo
    | distingue de los recibos de la escuela (Fútbol) y de los comprobantes
    | electrónicos del SRI, que son otra cosa.
    */
    public function reciboNumero(int $pagoId): string
    {
        return 'RA-' . str_pad((string)$pagoId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Todo lo que lleva el recibo de un pago, o null si no existe o se anuló.
     * saldo_despues es lo que quedó pendiente JUSTO tras este pago, no el
     * saldo de hoy: reimprimir un recibo antiguo no debe cambiar su cifra.
     */
    public function pagoRecibo(int $pagoId): ?array
    {
        $f = $this->filas(
            "SELECT p.*, f.forma_nombre,
                    r.reserva_id, r.reserva_codigo, r.reserva_fecha, r.reserva_horainicio,
                    r.reserva_horafin, r.reserva_horas, r.reserva_total, r.reserva_sedeid,
                    c.cliente_id, c.cliente_nombre, c.cliente_identificacion,
                    c.cliente_correo, c.cliente_celular,
                    i.instalacion_codigo, i.instalacion_nombre,
                    s.sede_nombre, s.sede_direccion, s.sede_telefono, s.sede_email,
                    (SELECT COALESCE(SUM(x.pago_valor), 0) FROM dsa_pago x
                      WHERE x.pago_reservaid = p.pago_reservaid AND x.pago_estado = 'A'
                        AND x.pago_id <= p.pago_id) AS abonado_hasta
               FROM dsa_pago p
               JOIN dsa_forma_ingreso f ON f.forma_id = p.pago_formaid
               JOIN dsa_reserva r       ON r.reserva_id = p.pago_reservaid
               JOIN dsa_cliente c       ON c.cliente_id = r.reserva_clienteid
               JOIN dsa_instalacion i   ON i.instalacion_id = r.reserva_instalacionid
               LEFT JOIN general_sede s ON s.sede_id = r.reserva_sedeid
              WHERE p.pago_id = :p AND p.pago_estado = 'A'",
            [':p' => $pagoId]
        );
        if (!$f) { return null; }

        $r = $f[0];
        $r['recibo_numero'] = $this->reciboNumero($pagoId);
        $r['saldo_despues'] = max(0.0, round((float)$r['reserva_total'] - (float)$r['abonado_hasta'], 2));
        $r['concepto']      = 'Alquiler de ' . $r['instalacion_nombre'] . ' el '
                            . date('d/m/Y', strtotime($r['reserva_fecha'])) . ', de '
                            . substr($r['reserva_horainicio'], 0, 5) . ' a ' . substr($r['reserva_horafin'], 0, 5);
        return $r;
    }

    /** «Veinticinco dólares con 50/100». */
    public function montoEnLetras(float $valor): string
    {
        $entero = (int)floor(round($valor, 2));
        $cent   = (int)round((round($valor, 2) - $entero) * 100);
        $texto  = $entero === 0 ? 'cero' : $this->apocope($this->letras($entero));
        return ucfirst($texto) . ' ' . ($entero === 1 ? 'dólar' : 'dólares')
             . ' con ' . str_pad((string)$cent, 2, '0', STR_PAD_LEFT) . '/100';
    }

    /* «uno» se acorta delante de un sustantivo: veintiún dólares, un mil. */
    private function apocope(string $t): string
    {
        return preg_replace(['/veintiuno$/u', '/uno$/u'], ['veintiún', 'un'], $t);
    }

    private function letras(int $n): string
    {
        static $base = ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
            'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho',
            'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco',
            'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
        static $decenas  = [3 => 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        static $centenas = [1 => 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
            'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        if ($n >= 1000000) {
            $m = intdiv($n, 1000000); $resto = $n % 1000000;
            $t = $m === 1 ? 'un millón' : $this->apocope($this->letras($m)) . ' millones';
            return $t . ($resto ? ' ' . $this->letras($resto) : '');
        }
        if ($n >= 1000) {
            $k = intdiv($n, 1000); $resto = $n % 1000;
            $t = $k === 1 ? 'mil' : $this->apocope($this->letras($k)) . ' mil';
            return $t . ($resto ? ' ' . $this->letras($resto) : '');
        }
        if ($n >= 100) {
            if ($n === 100) { return 'cien'; }
            $resto = $n % 100;
            return $centenas[intdiv($n, 100)] . ($resto ? ' ' . $this->letras($resto) : '');
        }
        if ($n < 30) { return $base[$n]; }
        $u = $n % 10;
        return $decenas[intdiv($n, 10)] . ($u ? ' y ' . $base[$u] : '');
    }

    /**
     * El PDF del recibo, en memoria. Mismo formato que el recibo de la
     * escuela (ds_futbol/pagosReciboPDF): media hoja apaisada, logo y datos
     * de la sede arriba, el detalle en filas y abajo montos y el QR centrado.
     * FPDF trabaja en ISO-8859-1: todo texto pasa por $t().
     */
    public function reciboPdf(array $r): string
    {
        require_once DS_HUB_PATH . 'ds_futbol/app/lib/fpdf.php';
        require_once DS_HUB_PATH . 'ds_futbol/app/lib/barcode.php';

        $t    = static fn ($s) => mb_convert_encoding((string)$s, 'ISO-8859-1', 'UTF-8');
        $sede = (int)$r['reserva_sedeid'];
        $din  = static fn ($v) => '$' . number_format((float)$v, 2);

        $pdf = new \FPDF('L', 'mm', [130, 210]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);

        /* Cabecera: organización y sede a la izquierda, número a la derecha */
        $pdf->Rect(10, 10, 190, 40);
        ds_logo_dibujar($pdf, (int)$sede, 15, 14, 30, 30);   /* a su proporción, sin estirar */
        $pdf->SetXY(50, 14); $pdf->SetFont('Arial', 'B', 12); $pdf->Cell(85, 6, $t(ds_nombre_organizacion_may($sede)), 0, 0, 'L');
        $pdf->SetXY(50, 20); $pdf->SetFont('Arial', '', 8);  $pdf->Cell(85, 5, $t('Alquiler de instalaciones · Arena'), 0, 0, 'L');
        $pdf->SetXY(50, 27); $pdf->Cell(85, 5, $t('Sede ' . $r['sede_nombre']), 0, 0, 'L');
        $pdf->SetXY(50, 32); $pdf->Cell(85, 5, $t('Dirección: ' . $r['sede_direccion']), 0, 0, 'L');
        $pdf->SetXY(50, 37); $pdf->Cell(85, 5, $t('Teléfono: ' . $r['sede_telefono']), 0, 0, 'L');

        $pdf->Rect(140, 14, 55, 15);
        $pdf->SetXY(140, 15); $pdf->SetFont('Arial', '', 7);  $pdf->Cell(55, 4, 'RECIBO DE PAGO', 0, 0, 'C');
        $pdf->SetXY(140, 20); $pdf->SetFont('Arial', 'B', 14); $pdf->Cell(55, 8, $r['recibo_numero'], 0, 0, 'C');

        $pdf->Rect(140, 35, 55, 10); $pdf->Line(140, 38, 195, 38);
        $pdf->Line(158.3, 35, 158.3, 45); $pdf->Line(176.6, 35, 176.6, 45);
        $pdf->SetXY(140, 31); $pdf->SetFont('Arial', '', 7); $pdf->Cell(55, 4, 'Fecha de pago', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 5);
        foreach (['DIA', 'MES', $t('AÑO')] as $k => $et) { $pdf->SetXY(140 + $k * 18.33, 35); $pdf->Cell(18.33, 3, $et, 0, 0, 'C'); }
        $pdf->SetFont('Arial', '', 9);
        $fp = strtotime($r['pago_fecha']);
        foreach ([date('d', $fp), date('m', $fp), date('Y', $fp)] as $k => $v) { $pdf->SetXY(140 + $k * 18.33, 38); $pdf->Cell(18.33, 7, $v, 0, 0, 'C'); }

        /* Detalle */
        $pdf->Rect(10, 51, 190, 70);
        foreach ([60, 67, 74, 81, 88] as $y) { $pdf->Line(10, $y, 200, $y); }
        $pdf->SetXY(15, 52);  $pdf->SetFont('Arial', 'B', 11); $pdf->Cell(25, 8, 'POR', 0, 0, 'L');
        $pdf->SetXY(46, 52);  $pdf->Cell(40, 8, $din($r['pago_valor']), 0, 0, 'L');
        $pdf->SetXY(120, 52); $pdf->Cell(30, 8, 'RESERVA', 0, 0, 'C');
        $pdf->SetXY(150, 52); $pdf->Cell(45, 8, $t($r['reserva_codigo']), 0, 0, 'R');

        $filas = [
            [61, 'Recibí de:',       $r['cliente_nombre'] . ' · ' . $r['cliente_identificacion']],
            [68, 'La cantidad de:',  $this->montoEnLetras((float)$r['pago_valor'])],
            [75, 'Por concepto de:', $r['concepto']],
            [82, 'Forma de pago:',   $r['forma_nombre'] . ($r['pago_referencia'] ? ' · Ref. ' . $r['pago_referencia'] : '')
                                     . ((float)$r['pago_vuelto'] > 0 ? ' · Recibido ' . $din($r['pago_recibido']) . ', vuelto ' . $din($r['pago_vuelto']) : '')],
        ];
        foreach ($filas as [$y, $et, $val]) {
            $pdf->SetXY(15, $y); $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(30, 5, $t($et), 0, 0, 'L');
            $pdf->SetXY(46, $y); $pdf->SetFont('Arial', '', 9.5);
            /* Una línea por fila: lo que no cabe se recorta con «…» en vez de pisar la siguiente. */
            $val = $t($val);
            while ($pdf->GetStringWidth($val) > 150 && strlen($val) > 4) { $val = substr($val, 0, -4) . '...'; }
            $pdf->Cell(150, 5, $val, 0, 0, 'L');
        }

        /* Montos */
        $pdf->Line(10, 97, 100, 97); $pdf->Line(10, 105, 100, 105); $pdf->Line(10, 113, 100, 113);
        $pdf->SetXY(15, 89); $pdf->SetFont('Arial', '', 11); $pdf->Cell(40, 7, 'MONTO', 0, 0, 'L');
        foreach ([[98, 'TOTAL RESERVA:', $r['reserva_total']], [106, 'ESTE PAGO:', $r['pago_valor']], [114, 'SALDO PENDIENTE:', $r['saldo_despues']]] as [$y, $et, $v]) {
            $pdf->SetXY(15, $y); $pdf->SetFont('Arial', 'B', 9.5); $pdf->Cell(45, 6, $et, 0, 0, 'L');
            $pdf->SetXY(60, $y); $pdf->Cell(35, 6, $din($v), 0, 0, 'R');
        }

        /* QR centrado en el recuadro derecho (100–200 × 88–121). Sin firma
           ni «FIRMA AUTORIZADA»: el recibo no lleva rúbrica. */
        $pdf->Rect(100, 88, 100, 33);

        $qr  = (new \barcode_generator())->render_image('qr',
                   'Recibo ' . $r['recibo_numero'] . "\nReserva " . $r['reserva_codigo'] . "\nUSD " . number_format((float)$r['pago_valor'], 2)
                   . "\n" . date('d/m/Y', $fp) . "\n" . $r['sede_nombre'],
                   ['sx' => 4, 'sy' => 4, 'p' => -12]);
        $tmp = tempnam(sys_get_temp_dir(), 'qrra') . '.png';
        imagepng($qr, $tmp);
        imagedestroy($qr);
        $pdf->Image($tmp, 136.5, 91, 27, 27, 'PNG');
        @unlink($tmp);

        /* Justo bajo el recuadro (termina en 121): al final de la hoja va el
           pie de Core › Organización (ds_pdf_pie), y a 122 se encimaban. */
        $pdf->SetXY(10, 121.2); $pdf->SetFont('Arial', '', 6);
        $pdf->Cell(190, 2.4, $t('Emitido el ' . date('d/m/Y H:i') . ' · Este recibo no reemplaza al comprobante electrónico del SRI.'), 0, 0, 'C');

        return $pdf->Output('S');
    }

    /*
    | Enlace público del recibo, para WhatsApp. Mismo diseño que el de Fútbol
    | (pagosController::enlacePublicoRecibo) pero con su propia clave dentro
    | de lo firmado: un enlace de Arena no abre un recibo de la escuela.
    |   reciboPublico/{pago_id}-{vence}-{firma}
    */
    const RECIBO_ENLACE_DIAS = 7;

    private function firmaRecibo(int $pagoId, int $vence): string
    {
        return substr(hash_hmac('sha256', 'arena-recibo|' . $pagoId . '|' . $vence, TOKEN_SECRET), 0, 32);
    }

    public function enlacePublicoRecibo(int $pagoId, int $dias = self::RECIBO_ENLACE_DIAS): array
    {
        if (!defined('TOKEN_SECRET') || TOKEN_SECRET === '' || $pagoId <= 0) {
            return ['url' => '', 'vence' => 0];
        }
        $vence = strtotime('today 23:59:59 +' . $dias . ' days');
        return ['url'   => APP_URL . 'reciboPublico/' . $pagoId . '-' . $vence . '-' . $this->firmaRecibo($pagoId, $vence) . '/',
                'vence' => $vence];
    }

    /** Id del pago si el token es auténtico, vigente y el pago sigue activo; 0 si no. */
    public function pagoDeEnlacePublico(string $token): int
    {
        if (!defined('TOKEN_SECRET') || TOKEN_SECRET === ''
            || !preg_match('/^(\d{1,10})-(\d{9,11})-([0-9a-f]{32})$/', $token, $m)) {
            return 0;
        }
        [$_, $id, $vence, $firma] = $m;
        if ((int)$vence < time() || !hash_equals($this->firmaRecibo((int)$id, (int)$vence), $firma)) {
            return 0;
        }
        return $this->pagoRecibo((int)$id) !== null ? (int)$id : 0;
    }

    /**
     * Envía el recibo en PDF por correo. Puerta AJAX: «enviarReciboCorreo».
     *
     * El correo sale con la función mail() del servidor, igual que los
     * recibos de la escuela, pero en UTF-8 de punta a punta: asunto y
     * remitente codificados (RFC 2047), así una tilde no llega rota.
     */
    public function enviarReciboCorreo(): string
    {
        if (!puede_crear('reservaList') && !puede_editar('reservaList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede enviar recibos.', 'error');
        }

        $r = $this->pagoRecibo((int)($_POST['pago_id'] ?? 0));
        if ($r === null) {
            return $this->alerta('simple', 'Recibo no encontrado', 'El pago no existe o fue anulado.', 'error');
        }

        $correo = trim((string)($_POST['correo'] ?? ''));
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $correo)) {
            return $this->alerta('simple', 'Correo no válido', 'Revise la dirección de correo del destinatario.', 'error');
        }

        $nota = trim(mb_substr((string)($_POST['nota'] ?? ''), 0, 500));

        $org     = ds_nombre_organizacion();
        $desde   = ds_email_organizacion((int)$r['reserva_sedeid']) ?: ini_get('sendmail_from');
        $enc     = static fn (string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $asunto  = 'Recibo ' . $r['recibo_numero'] . ' · ' . $org;
        $cuerpo  = "Estimado/a " . $r['cliente_nombre'] . ",\r\n\r\n"
                 . "Adjunto encontrará el recibo " . $r['recibo_numero'] . " por el pago de su reserva "
                 . $r['reserva_codigo'] . ":\r\n\r\n"
                 . "  " . $r['concepto'] . "\r\n"
                 . "  Valor pagado: USD " . number_format((float)$r['pago_valor'], 2) . "\r\n"
                 . ((float)$r['saldo_despues'] > 0
                     ? "  Saldo pendiente: USD " . number_format((float)$r['saldo_despues'], 2) . "\r\n"
                     : "  La reserva queda pagada en su totalidad.\r\n")
                 . ($nota !== '' ? "\r\n" . $nota . "\r\n" : '')
                 . "\r\nGracias por su preferencia.\r\n" . $org . "\r\n";

        $limite  = 'ra' . bin2hex(random_bytes(12));
        $cabeceras = 'From: ' . $enc($org) . ' <' . $desde . ">\r\n"
                   . "MIME-Version: 1.0\r\n"
                   . 'Content-Type: multipart/mixed; boundary="' . $limite . '"';
        $mensaje = "--$limite\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: base64\r\n\r\n"
                 . chunk_split(base64_encode($cuerpo)) . "\r\n"
                 . "--$limite\r\n"
                 . 'Content-Type: application/pdf; name="Recibo-' . $r['recibo_numero'] . ".pdf\"\r\n"
                 . "Content-Transfer-Encoding: base64\r\n"
                 . 'Content-Disposition: attachment; filename="Recibo-' . $r['recibo_numero'] . ".pdf\"\r\n\r\n"
                 . chunk_split(base64_encode($this->reciboPdf($r))) . "\r\n"
                 . "--$limite--";

        /* mail() avisa con un warning cuando no hay servidor de correo; ese
           aviso no debe colarse en la respuesta JSON. */
        $enviado = @mail($correo, $enc($asunto), $mensaje, $cabeceras);

        return $enviado
            ? $this->alerta('simple', 'Recibo enviado', 'Se envió el recibo ' . $r['recibo_numero'] . ' a ' . $correo . '.')
            : $this->alerta('simple', 'No se pudo enviar el correo',
                'El servidor de correo no respondió. Descargue el PDF y envíelo por otro medio, o inténtelo más tarde.', 'error');
    }

    /**
     * Registra un abono contra una reserva.
     *
     * Todo ocurre dentro de una transacción porque son cuatro escrituras
     * que deben cuadrar entre sí: el pago, el acumulado de la reserva y,
     * según el caso, el saldo del monedero y su asiento en el libro mayor.
     * Si algo falla, no debe quedar un abono sin reflejar o un saldo movido
     * sin respaldo.
     */
    public function registrarPago(): string
    {
        if (!puede_crear('reservaList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede registrar pagos.', 'error');
        }

        $reservaid  = (int)($_POST['reserva_id'] ?? 0);
        $formaid    = (int)($_POST['pago_formaid'] ?? 0);
        $valor      = round((float)str_replace(',', '.', (string)($_POST['pago_valor'] ?? '0')), 2);
        $recibido   = round((float)str_replace(',', '.', (string)($_POST['pago_recibido'] ?? '0')), 2);
        $aMonedero  = ($_POST['pago_vueltoamonedero'] ?? 'N') === 'S';
        $referencia = trim((string)($_POST['pago_referencia'] ?? ''));
        $observacion= trim((string)($_POST['pago_observacion'] ?? ''));

        $reserva = $this->reserva($reservaid);
        if ($reserva === null) {
            return $this->alerta('simple', 'Reserva no encontrada', 'La reserva no existe.', 'error');
        }
        if ($reserva['reserva_estado'] === 'X') {
            return $this->alerta('simple', 'Reserva cancelada', 'No se pueden registrar abonos en una reserva cancelada.', 'error');
        }

        $forma = $this->filas(
            "SELECT * FROM dsa_forma_ingreso WHERE forma_id = :f AND forma_estado = 'A'",
            [':f' => $formaid]);
        if (!$forma) {
            return $this->alerta('simple', 'Forma de ingreso no válida', 'Seleccione cómo paga el cliente.', 'error');
        }
        $forma = $forma[0];

        if ($valor <= 0) {
            return $this->alerta('simple', 'Valor no válido', 'El abono debe ser mayor que cero.', 'error');
        }

        $saldo = (float)$reserva['reserva_saldo'];
        if ($valor > $saldo + 0.001) {
            return $this->alerta('simple', 'Abono mayor al saldo',
                'El saldo pendiente es $' . number_format($saldo, 2) .
                '. Registre como máximo ese importe.', 'error');
        }

        if ($forma['forma_requiereref'] === 'S' && $referencia === '') {
            return $this->alerta('simple', 'Falta la referencia',
                'Indique el número de comprobante para ' . mb_strtolower($forma['forma_nombre']) . '.', 'error');
        }

        /* El vuelto sólo tiene sentido si se recibió más de lo que se cobra. */
        $vuelto = 0.00;
        if ($forma['forma_esmonedero'] !== 'S' && $recibido > 0) {
            if ($recibido < $valor) {
                return $this->alerta('simple', 'Recibido insuficiente',
                    'Lo recibido no cubre el abono indicado.', 'error');
            }
            $vuelto = round($recibido - $valor, 2);
        }

        $con = $this->con();
        if ($con === null) {
            return $this->alerta('simple', 'Error', 'Sin conexión a la base de datos.', 'error');
        }

        try {
            $con->beginTransaction();

            /* 1. Pagar con monedero descuenta el saldo antes que nada: si
                  no alcanza, la excepción deshace todo lo demás. */
            if ($forma['forma_esmonedero'] === 'S') {
                $mon = $this->asegurarMonedero((int)$reserva['reserva_clienteid']);
                $this->moverMonedero($con, $mon, 'E', 'RES', $valor,
                    ['reserva' => $reservaid,
                     'detalle' => 'Aplicado a la reserva ' . $reserva['reserva_codigo']]);
            }

            /* 2. El abono */
            $con->prepare(
                "INSERT INTO dsa_pago
                    (pago_reservaid, pago_formaid, pago_valor, pago_recibido, pago_vuelto,
                     pago_vueltoamonedero, pago_referencia, pago_fecha, pago_observacion,
                     pago_usuarioid, pago_estado)
                 VALUES (:r, :f, :v, :rec, :vu, :vm, :ref, CURDATE(), :o, :u, 'A')"
            )->execute([
                ':r' => $reservaid, ':f' => $formaid, ':v' => $valor,
                ':rec' => $recibido > 0 ? $recibido : null, ':vu' => $vuelto,
                ':vm' => ($vuelto > 0 && $aMonedero) ? 'S' : 'N',
                ':ref' => $referencia !== '' ? $referencia : null,
                ':o' => $observacion !== '' ? $observacion : null,
                ':u' => (int)($_SESSION['usuarioid'] ?? 0) ?: null,
            ]);

            $pagoId = (int)$con->lastInsertId();

            /* 3. Acumulado de la reserva.
                  Sólo se toca lo abonado: el saldo es columna GENERADA
                  (total - abonado) desde la migración 061 y la base lo
                  recalcula en el mismo UPDATE.

                  Antes se escribía a mano, y había que razonar que MySQL
                  evalúa las asignaciones de izquierda a derecha para que
                  el saldo viera el abonado ya actualizado. Ese razonamiento
                  ya no hace falta: no se puede equivocar quien no escribe. */
            $con->prepare(
                "UPDATE dsa_reserva
                    SET reserva_abonado = reserva_abonado + :v
                  WHERE reserva_id = :r"
            )->execute([':v' => $valor, ':r' => $reservaid]);

            /* 4. El vuelto que el cliente decide dejar a su favor */
            if ($vuelto > 0 && $aMonedero) {
                $mon = $this->asegurarMonedero((int)$reserva['reserva_clienteid']);
                $this->moverMonedero($con, $mon, 'I', 'VUE', $vuelto,
                    ['reserva' => $reservaid, 'pago' => $pagoId,
                     'detalle' => 'Vuelto de la reserva ' . $reserva['reserva_codigo']]);
            }

            $con->commit();

        } catch (\Throwable $e) {
            if ($con->inTransaction()) $con->rollBack();
            return $this->alerta('simple', 'No se pudo registrar el abono', $e->getMessage(), 'error');
        }

        $nuevoSaldo = round($saldo - $valor, 2);

        $texto = '$' . number_format($valor, 2) . ' registrados. ';
        $texto .= $nuevoSaldo <= 0.001
            ? 'La reserva queda totalmente pagada.'
            : 'Saldo pendiente: $' . number_format($nuevoSaldo, 2) . '.';

        if ($vuelto > 0) {
            $texto .= $aMonedero
                ? ' Vuelto de $' . number_format($vuelto, 2) . ' acreditado al monedero.'
                : ' Vuelto entregado: $' . number_format($vuelto, 2) . '.';
        }

        /* Se va directo al recibo: es lo siguiente que se hace con un pago
           (entregarlo, enviarlo por correo o por WhatsApp). */
        return $this->alerta('redireccionar', 'Abono registrado',
            $texto . ' A continuación verá el recibo para enviarlo o descargarlo.', 'success',
            APP_URL . 'reciboPago/?id=' . $pagoId);
    }

    /*======================================================================
      Panel
      ====================================================================*/


    /*======================================================================
      Lo que el panel necesita saber

      QUÉ CAMBIÓ Y POR QUÉ

      resumen() contaba INVENTARIO: cuántas canchas hay, cuántas residencias,
      cuántos clientes. Son cifras que no se mueven en meses y que no
      cambian lo que nadie va a hacer hoy. Un recinto se dirige con otra
      pregunta: «¿cómo va el día, qué queda libre y qué he dejado sin
      cerrar?».

      Ninguna de estas cifras se guarda. Todas se derivan de las reservas y
      los horarios.

      EL DÍA DE LA SEMANA VA EN ISO, COMO EN EL RESTO DEL MÓDULO

      dsa_horario.horario_dia usa 1=lunes .. 7=domingo, que es lo que
      devuelve date('N') y lo que declara dias(). El equivalente en SQL es
      WEEKDAY()+1, NO DAYOFWEEK()-1: los dos coinciden de lunes a sábado,
      pero el domingo DAYOFWEEK devuelve 1 y restarle uno da 0, que no casa
      con ningún horario. Con esa fórmula la ocupación de los domingos
      habría salido siempre sin horas disponibles.
      ======================================================================*/

    /**
     * Ocupación de hoy: horas reservadas sobre horas que el recinto abre.
     *
     * Es la cifra que de verdad dirige un recinto de alquiler —decide
     * precios, promociones y si sobra o falta cancha—, y no estaba en
     * ninguna pantalla.
     */
    public function ocupacionHoy(): array
    {
        $disp = (float)$this->escalar(
            "SELECT COALESCE(SUM(
                        TIME_TO_SEC(TIMEDIFF(H.horario_hasta, H.horario_desde)) / 3600), 0)
               FROM dsa_horario H
               JOIN dsa_instalacion I ON I.instalacion_id = H.horario_instalacionid
              WHERE H.horario_estado = 'A'
                AND I.instalacion_estado = 'A'
                AND H.horario_dia = WEEKDAY(CURDATE()) + 1");

        /* Las canceladas no ocupan nada: la cancha quedó libre. */
        $usadas = (float)$this->escalar(
            "SELECT COALESCE(SUM(reserva_horas), 0)
               FROM dsa_reserva
              WHERE reserva_fecha = CURDATE()
                AND reserva_estado <> 'X'");

        return [
            'disponibles' => $disp,
            'reservadas'  => $usadas,
            'porcentaje'  => $disp > 0 ? (int)round($usadas * 100 / $disp) : 0,
        ];
    }

    /**
     * Lo accionable. Cada línea es algo que alguien tiene que ir a hacer.
     */
    public function requiereAtencion(): array
    {
        /* «Reservas pasadas sin cerrar» se quitó el 2026-10-30: pedía marcar
           una a una como cumplida cada reserva ya jugada, y no hay a quién
           le sirva ese paso. Una reserva cuya fecha pasó ya está jugada; si
           se debe algo, lo dice el aviso de saldos. */

        /* Reservas de hoy que siguen sin confirmar. */
        $sinConfirmar = (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_reserva
              WHERE reserva_fecha = CURDATE() AND reserva_estado = 'P'");

        /* Instalaciones activas SIN horario: no se pueden reservar y nada
           en la pantalla lo dice. */
        $sinHorario = (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_instalacion I
              WHERE I.instalacion_estado = 'A'
                AND NOT EXISTS (SELECT 1 FROM dsa_horario H
                                 WHERE H.horario_instalacionid = I.instalacion_id
                                   AND H.horario_estado = 'A')");

        return [
            'sinConfirmar' => $sinConfirmar,
            'sinHorario'   => $sinHorario,
        ];
    }

    /**
     * Reservas y dinero del mes.
     *
     * NO se llama sin comprobar permiso antes. Hasta ahora el panel
     * enseñaba importes de saldo a cualquiera que pudiera abrirlo.
     */
    public function economiaMes(): array
    {
        /* Este controlador tiene filas() y escalar(), pero NO fila(): no se
           asume la misma caja de herramientas que en League. Se toma la
           primera fila de la lista, que es lo que devuelve una agregación. */
        $f = $this->filas(
            "SELECT COALESCE(SUM(reserva_total), 0)   AS facturado,
                    COALESCE(SUM(reserva_abonado), 0) AS cobrado,
                    COALESCE(SUM(reserva_saldo), 0)   AS saldo
               FROM dsa_reserva
              WHERE reserva_estado <> 'X'
                AND reserva_fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND reserva_fecha <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH")[0] ?? [];

        /*
        | DOS ÁMBITOS, Y NO SE MEZCLAN
        |
        | La primera versión devolvía un solo «conSaldo» contando TODAS las
        | reservas con saldo (1.715) y lo pintaba junto al saldo DEL MES
        | (11.265,35). Contar una cosa y sumar otra: el aviso decía «1.715
        | reservas con saldo · $11.265,35» cuando esas 1.715 deben en
        | realidad 26.419,47. Inflaba el recuento y escondía la mitad de la
        | deuda a la vez.
        |
        | Ahora el mes va con el recuento del mes, y el total con el total.
        | Cada cifra viaja con la que le corresponde.
        */
        $pend = $this->filas(
            "SELECT COUNT(1) AS n, COALESCE(SUM(reserva_saldo), 0) AS v
               FROM dsa_reserva
              WHERE reserva_saldo > 0 AND reserva_estado <> 'X'")[0] ?? [];

        $pendMes = $this->filas(
            "SELECT COUNT(1) AS n
               FROM dsa_reserva
              WHERE reserva_saldo > 0 AND reserva_estado <> 'X'
                AND reserva_fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                AND reserva_fecha <  DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH")[0] ?? [];

        $fact = (float)($f['facturado'] ?? 0);
        $cobr = (float)($f['cobrado'] ?? 0);

        return [
            /* Del mes en curso */
            'facturado'   => $fact,
            'cobrado'     => $cobr,
            'saldo'       => (float)($f['saldo'] ?? 0),
            'conSaldoMes' => (int)($pendMes['n'] ?? 0),
            'avance'      => $fact > 0 ? (int)round($cobr * 100 / $fact) : 0,
            /* Acumulado, sin límite de fecha */
            'conSaldo'    => (int)($pend['n'] ?? 0),
            'saldoTotal'  => (float)($pend['v'] ?? 0),
        ];
    }

    /** Cuántas reservas vivas hay hoy y en los próximos siete días. */
    public function pulso(): array
    {
        return [
            'hoy' => (int)$this->escalar(
                "SELECT COUNT(1) FROM dsa_reserva
                  WHERE reserva_fecha = CURDATE() AND reserva_estado IN ('P','C','U')"),
            'semana' => (int)$this->escalar(
                "SELECT COUNT(1) FROM dsa_reserva
                  WHERE reserva_fecha > CURDATE()
                    AND reserva_fecha <= CURDATE() + INTERVAL 7 DAY
                    AND reserva_estado IN ('P','C')"),
            'clientes' => (int)$this->escalar(
                "SELECT COUNT(1) FROM dsa_cliente WHERE cliente_estado = 'A'"),
        ];
    }

    /**
     * La agenda de hoy, por hora.
     *
     * Es lo que mira quien atiende el mostrador, y hasta ahora había que
     * ir a Reservas y filtrar a mano para verlo.
     */
    public function agendaHoy(int $limite = 12): array
    {
        $limite = max(1, min(50, $limite));

        return $this->filas(
            "SELECT R.reserva_id, R.reserva_codigo, R.reserva_horainicio,
                    R.reserva_horafin, R.reserva_estado, R.reserva_saldo,
                    C.cliente_nombre, I.instalacion_nombre, I.instalacion_codigo
               FROM dsa_reserva R
               JOIN dsa_cliente C     ON C.cliente_id = R.reserva_clienteid
               JOIN dsa_instalacion I ON I.instalacion_id = R.reserva_instalacionid
              WHERE R.reserva_fecha = CURDATE() AND R.reserva_estado <> 'X'
              ORDER BY R.reserva_horainicio
              LIMIT " . $limite
        );
    }

    /**
     * Ocupación de hoy instalación por instalación.
     *
     * Responde «qué cancha está parada», que es donde se pierde dinero.
     */
    public function ocupacionPorInstalacion(): array
    {
        return $this->filas(
            "SELECT I.instalacion_id, I.instalacion_codigo, I.instalacion_nombre,
                    I.instalacion_clase,
                    COALESCE(H.horas, 0) AS disponibles,
                    COALESCE(R.horas, 0) AS reservadas
               FROM dsa_instalacion I
               LEFT JOIN (
                    SELECT horario_instalacionid AS id,
                           SUM(TIME_TO_SEC(TIMEDIFF(horario_hasta, horario_desde)) / 3600) AS horas
                      FROM dsa_horario
                     WHERE horario_estado = 'A' AND horario_dia = WEEKDAY(CURDATE()) + 1
                     GROUP BY horario_instalacionid
               ) H ON H.id = I.instalacion_id
               LEFT JOIN (
                    SELECT reserva_instalacionid AS id, SUM(reserva_horas) AS horas
                      FROM dsa_reserva
                     WHERE reserva_fecha = CURDATE() AND reserva_estado <> 'X'
                     GROUP BY reserva_instalacionid
               ) R ON R.id = I.instalacion_id
              WHERE I.instalacion_estado = 'A'
              ORDER BY I.instalacion_codigo"
        );
    }
    public function resumen(): array
    {
        return [
            ['valor' => $this->escalar("SELECT COUNT(1) FROM dsa_instalacion WHERE instalacion_estado='A' AND instalacion_clase='C'"),
             'etiqueta' => 'Canchas',      'icono' => 'fas fa-basketball-ball', 'color' => 'primary'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM dsa_instalacion WHERE instalacion_estado='A' AND instalacion_clase='R'"),
             'etiqueta' => 'Residencias',  'icono' => 'fas fa-bed',             'color' => 'info'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM dsa_reserva WHERE reserva_estado IN ('P','C') AND reserva_fecha >= CURDATE()"),
             'etiqueta' => 'Reservas vigentes', 'icono' => 'fas fa-calendar-check', 'color' => 'warning'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM dsa_cliente WHERE cliente_estado='A'"),
             'etiqueta' => 'Clientes',     'icono' => 'fas fa-user-friends',    'color' => 'success'],
        ];
    }

    /** Reservas con saldo pendiente: el control de abonos del panel. */
    public function saldosPendientes(int $limite = 8): array
    {
        return $this->filas(
            "SELECT r.reserva_codigo, r.reserva_fecha, r.reserva_total,
                    r.reserva_abonado, r.reserva_saldo,
                    c.cliente_nombre, i.instalacion_nombre
               FROM dsa_reserva   r
               JOIN dsa_cliente   c ON c.cliente_id     = r.reserva_clienteid
               JOIN dsa_instalacion i ON i.instalacion_id = r.reserva_instalacionid
              WHERE r.reserva_estado IN ('P','C') AND r.reserva_saldo > 0
              ORDER BY r.reserva_fecha
              LIMIT " . (int)$limite
        );
    }

    /*======================================================================
      Menú lateral del módulo
      ====================================================================*/

    public function menuLateral(string $vistaActual): string
    {
        $sql = "SELECT m.menu_vista, m.menu_nombre, m.menu_icono
                  FROM seguridad_menu m
                 WHERE m.menu_estado = 'A' AND m.menu_modulo = 'arena'";

        if (!es_superadministrador()) {
            $sql .= " AND EXISTS (SELECT 1 FROM seguridad_permiso p
                                   WHERE p.permiso_menuid = m.menu_id
                                     AND p.permiso_rolid  = :rol
                                     AND p.permiso_estado = 'A'
                                     AND p.permiso_ver    = 'S')";
        }
        $sql .= " ORDER BY m.menu_orden";

        $filas = $this->filas($sql, es_superadministrador() ? [] : [':rol' => rol_actual()]);

        $html = '';
        foreach ($filas as $f) {
            $activo = ($f['menu_vista'] === $vistaActual) ? ' active' : '';
            $html .= '<li class="nav-item">'
                   . '<a href="' . APP_URL . $f['menu_vista'] . '/" class="nav-link' . $activo . '">'
                   /* nav-icon es de AdminLTE 4: sin ella el texto se pega
                      al borde y los iconos no quedan alineados entre si. */
                   . '<i class="nav-icon ' . htmlspecialchars($f['menu_icono'], ENT_QUOTES, 'UTF-8') . '"></i>'
                   . '<p>' . htmlspecialchars($f['menu_nombre'], ENT_QUOTES, 'UTF-8') . '</p>'
                   . '</a></li>';
        }

        return $html;
    }
}
