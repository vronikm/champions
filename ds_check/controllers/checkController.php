<?php

namespace check\controllers;

use PDO;

/*
|--------------------------------------------------------------------------
| DigiCheck — datos del módulo
|--------------------------------------------------------------------------
| Sólo LEE (horarios, alumnos y la asistencia de hoy). Las marcas no se
| escriben aquí: el dispositivo las envía a Fútbol por el camino de la
| asistencia sin conexión (asistenciaController::registrarMarcasLote), que
| ya resuelve plazo, permisos y conflictos.
|
| QUÉ ALUMNOS VE CADA USUARIO: los de los horarios en los que puede tomar
| asistencia, con la MISMA regla que Fútbol (asistenciaController::
| puedeTomarAsistencia): los que dicta, o todos los de sus sedes; con
| «todas las sedes», todos. Así lo que se escanea es exactamente lo que el
| servidor aceptará después.
*/
class checkController
{
    private ?PDO $con = null;

    private function conexion(): PDO
    {
        if ($this->con === null) {
            $this->con = seguridad_conexion();
            if (!$this->con) { throw new \RuntimeException('Sin conexión a la base de datos.'); }
        }
        return $this->con;
    }

    private function filas(string $sql, array $params = []): array
    {
        $q = $this->conexion()->prepare($sql);
        $q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Texto de la base listo para mostrar (sin entidades viejas ni espacios de más). */
    private static function texto($v): string
    {
        return preg_replace('/\s+/', ' ', trim(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /*==============  Menú lateral  ==============*/
    public function menuLateral(string $vistaActual): string
    {
        $sql = "SELECT m.menu_vista, m.menu_nombre, m.menu_icono
                  FROM seguridad_menu m
                 WHERE m.menu_estado = 'A' AND m.menu_modulo = :modulo";
        $params = [':modulo' => DS_MODULO];
        if (!es_superadministrador()) {
            $sql .= " AND EXISTS (SELECT 1 FROM seguridad_permiso p
                                   WHERE p.permiso_menuid = m.menu_id AND p.permiso_rolid = :rol
                                     AND p.permiso_estado = 'A' AND p.permiso_ver = 'S')";
            $params[':rol'] = rol_actual();
        }
        $html = '';
        foreach ($this->filas($sql . " ORDER BY m.menu_orden", $params) as $f) {
            $activo = $f['menu_vista'] === $vistaActual ? ' active' : '';
            $html .= '<li class="nav-item"><a href="' . APP_URL . $f['menu_vista'] . '/" class="nav-link' . $activo . '">'
                   . '<i class="nav-icon ' . htmlspecialchars((string)$f['menu_icono'], ENT_QUOTES, 'UTF-8') . '"></i>'
                   . '<p>' . htmlspecialchars((string)$f['menu_nombre'], ENT_QUOTES, 'UTF-8') . '</p></a></li>';
        }
        return $html;
    }

    /*==============  Horarios en los que el usuario toma asistencia  ==============*/
    /*
    | [horario_id => ['id', 'nombre', 'sede', 'dias' => [['dia' 1..7, 'inicio', 'fin', 'lugar']]]]
    | 1 = lunes … 7 = domingo (asistencia_horario_detalle.detalle_dia).
    */
    public function horariosDelUsuario(): array
    {
        $sedes = sedes_de_la_sesion();
        $donde = "H.horario_estado = 'A'";
        $par   = [];
        if ($sedes !== null) {
            $donde .= " AND (" . sql_sedes_de_la_sesion('H.horario_sedeid') . "
                         OR EXISTS (SELECT 1 FROM asistencia_horario_detalle D2
                                     WHERE D2.detalle_horarioid = H.horario_id AND D2.detalle_profesorid = :empleado))";
            $par[':empleado'] = empleado_actual();
        }
        $horarios = [];
        foreach ($this->filas("SELECT H.horario_id, H.horario_nombre, S.sede_nombre,
                    D.detalle_dia, HR.hora_inicio, HR.hora_fin, L.lugar_nombre
                FROM asistencia_horario H
                LEFT JOIN general_sede S ON S.sede_id = H.horario_sedeid
                LEFT JOIN asistencia_horario_detalle D ON D.detalle_horarioid = H.horario_id
                LEFT JOIN asistencia_hora HR ON HR.hora_id = D.detalle_horaid
                LEFT JOIN asistencia_lugar L ON L.lugar_id = D.detalle_lugarid
                WHERE $donde
                ORDER BY H.horario_nombre, D.detalle_dia, HR.hora_inicio", $par) as $f) {
            $id = (int)$f['horario_id'];
            $horarios[$id] ??= ['id' => $id, 'nombre' => self::texto($f['horario_nombre']), 'sede' => self::texto($f['sede_nombre']), 'dias' => []];
            if ($f['detalle_dia'] !== null) {
                $horarios[$id]['dias'][] = [
                    'dia'    => (int)$f['detalle_dia'],
                    'inicio' => $f['hora_inicio'] ? substr((string)$f['hora_inicio'], 0, 5) : '',
                    'fin'    => $f['hora_fin'] ? substr((string)$f['hora_fin'], 0, 5) : '',
                    'lugar'  => self::texto($f['lugar_nombre']),
                ];
            }
        }
        return $horarios;
    }

    /*==============  La lista que se descarga al dispositivo  ==============*/
    /*
    | Todo lo que la pantalla de escaneo necesita para trabajar sin Internet:
    | los horarios y sus alumnos ACTIVOS, cada uno con el código de su QR
    | (digicheck_codigo) y la marca que ya tiene HOY en el servidor (para
    | avisar «ya registrado» y como «anterior» al enviar).
    |
    | Sin foto: la lista pesa poco y se baja con la señal débil de la cancha.
    */
    public function lista(): array
    {
        $horarios = $this->horariosDelUsuario();
        $alumnos  = [];
        if ($horarios) {
            $ids  = implode(',', array_map('intval', array_keys($horarios)));
            $hoy  = date('Y-m-d');
            $col  = 'asistencia_D' . date('d');           /* un entero de date(), no del usuario */
            foreach ($this->filas("SELECT A.alumno_id, A.alumno_identificacion,
                        A.alumno_primernombre, A.alumno_segundonombre, A.alumno_apellidopaterno, A.alumno_apellidomaterno,
                        YEAR(A.alumno_fechanacimiento) AS categoria, S.sede_nombre,
                        GROUP_CONCAT(DISTINCT AH.asignahorario_horarioid) AS horarios,
                        MAX(NULLIF(AS1.$col, '')) AS marca_hoy
                    FROM asistencia_asignahorario AH
                    INNER JOIN sujeto_alumno A ON A.alumno_id = AH.asignahorario_alumnoid AND A.alumno_estado = 'A'
                    LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
                    LEFT JOIN asistencia_asistencia AS1 ON AS1.asistencia_alumnoid = A.alumno_id AND AS1.asistencia_aniomes = :aniomes
                    WHERE AH.asignahorario_horarioid IN ($ids)
                    GROUP BY A.alumno_id, A.alumno_identificacion, A.alumno_primernombre, A.alumno_segundonombre,
                             A.alumno_apellidopaterno, A.alumno_apellidomaterno, categoria, S.sede_nombre
                    ORDER BY A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_primernombre",
                    [':aniomes' => (int)date('Ym')]) as $f) {
                $id = (int)$f['alumno_id'];
                $alumnos[] = [
                    'id'        => $id,
                    'codigo'    => digicheck_codigo($id),
                    'nombres'   => self::texto($f['alumno_primernombre'] . ' ' . $f['alumno_segundonombre']),
                    'apellidos' => self::texto($f['alumno_apellidopaterno'] . ' ' . $f['alumno_apellidomaterno']),
                    'cedula'    => self::texto($f['alumno_identificacion']),
                    'categoria' => (string)$f['categoria'],
                    'sede'      => self::texto($f['sede_nombre']),
                    'horarios'  => array_map('intval', array_filter(explode(',', (string)$f['horarios']))),
                    'marcaHoy'  => (string)$f['marca_hoy'],
                ];
            }
            $fechaMarcas = $hoy;
        }
        return [
            'version'     => 1,
            'generada'    => date('c'),
            'fecha'       => $fechaMarcas ?? date('Y-m-d'),   /* de qué día son las «marcaHoy» */
            'usuario'     => (int)($_SESSION['usuarioid'] ?? 0),
            'escuela'     => ds_nombre_organizacion(0),
            'horarios'    => array_values($horarios),
            'alumnos'     => $alumnos,
        ];
    }

    /*==============  Carnets  ==============*/
    /** Sedes que el usuario puede ver, para el filtro de carnets. */
    public function sedesDelUsuario(): array
    {
        return array_map(static fn($f) => ['id' => (int)$f['sede_id'], 'nombre' => self::texto($f['sede_nombre'])],
            $this->filas("SELECT sede_id, sede_nombre FROM general_sede
                           WHERE " . sql_sedes_de_la_sesion('sede_id') . " ORDER BY sede_nombre"));
    }

    /*
    | Alumnos ACTIVOS para emitir su carnet (los de las sedes del usuario),
    | con filtro por sede, horario y texto (nombre, apellido o cédula).
    */
    public function alumnosCarnets(int $sede = 0, int $horario = 0, string $buscar = ''): array
    {
        $donde = "A.alumno_estado = 'A' AND " . sql_sedes_de_la_sesion('A.alumno_sedeid');
        $par   = [];
        if ($sede > 0)    { $donde .= " AND A.alumno_sedeid = :sede"; $par[':sede'] = $sede; }
        if ($horario > 0) {
            $donde .= " AND EXISTS (SELECT 1 FROM asistencia_asignahorario AH
                                     WHERE AH.asignahorario_alumnoid = A.alumno_id AND AH.asignahorario_horarioid = :horario)";
            $par[':horario'] = $horario;
        }
        $palabras = array_slice(array_filter(preg_split('/\s+/u', trim($buscar))), 0, 4);
        foreach (array_values($palabras) as $i => $p) {
            $donde .= " AND (A.alumno_identificacion LIKE :b$i OR A.alumno_primernombre LIKE :b$i OR A.alumno_segundonombre LIKE :b$i
                          OR A.alumno_apellidopaterno LIKE :b$i OR A.alumno_apellidomaterno LIKE :b$i)";
            $par[":b$i"] = '%' . $p . '%';
        }
        return array_map(fn($f) => $this->fichaCarnet($f), $this->filas("SELECT A.alumno_id, A.alumno_identificacion, A.alumno_imagen,
                    A.alumno_primernombre, A.alumno_segundonombre, A.alumno_apellidopaterno, A.alumno_apellidomaterno,
                    YEAR(A.alumno_fechanacimiento) AS categoria, A.alumno_sedeid, S.sede_nombre
                FROM sujeto_alumno A
                LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
                WHERE $donde
                ORDER BY A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_primernombre", $par));
    }

    /** Los datos de los carnets de unos alumnos (para la hoja de impresión), en el orden pedido. */
    public function carnetsDe(array $ids): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, 300);
        if (!$ids) { return []; }
        $filas = $this->filas("SELECT A.alumno_id, A.alumno_identificacion, A.alumno_imagen,
                    A.alumno_primernombre, A.alumno_segundonombre, A.alumno_apellidopaterno, A.alumno_apellidomaterno,
                    YEAR(A.alumno_fechanacimiento) AS categoria, A.alumno_sedeid, S.sede_nombre
                FROM sujeto_alumno A
                LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
                WHERE A.alumno_estado = 'A' AND A.alumno_id IN (" . implode(',', $ids) . ")
                  AND " . sql_sedes_de_la_sesion('A.alumno_sedeid'));
        $porId = [];
        foreach ($filas as $f) { $porId[(int)$f['alumno_id']] = $this->fichaCarnet($f); }
        return array_values(array_filter(array_map(static fn($id) => $porId[$id] ?? null, $ids)));
    }

    private function fichaCarnet(array $f): array
    {
        $id   = (int)$f['alumno_id'];
        $foto = trim((string)$f['alumno_imagen']) !== '' ? media_url('alumno', $f['alumno_imagen']) : '';
        if (str_contains($foto, '/dist/img/foto.jpg')) { $foto = ''; }   /* la genérica no es una foto del alumno */
        $nombres   = self::texto($f['alumno_primernombre'] . ' ' . $f['alumno_segundonombre']);
        $apellidos = self::texto($f['alumno_apellidopaterno'] . ' ' . $f['alumno_apellidomaterno']);
        return [
            'id'        => $id,
            'codigo'    => digicheck_codigo($id),
            'nombres'   => $nombres,
            'apellidos' => $apellidos,
            'iniciales' => mb_strtoupper(mb_substr($nombres, 0, 1) . mb_substr($apellidos, 0, 1)),
            'cedula'    => self::texto($f['alumno_identificacion']),
            'categoria' => (string)$f['categoria'],
            'sedeId'    => (int)$f['alumno_sedeid'],
            'sede'      => self::texto($f['sede_nombre']),
            'foto'      => $foto,
        ];
    }
}
