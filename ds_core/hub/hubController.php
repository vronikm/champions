<?php

namespace hub;

use PDO;

/**
 * Hub de aplicaciones DigiSports.
 *
 * Toma el catalogo estatico de ds_core/modulos.php y le anade las metricas
 * en vivo de cada modulo. No depende de ningun modulo: abre su propia
 * conexion con las credenciales del nucleo, de modo que el portal siga en
 * pie aunque un modulo concreto no este desplegado.
 *
 * Todas las consultas son defensivas: si una tabla falta o la BD falla, el
 * Hub degrada a valores neutros en lugar de romper la puerta de entrada.
 */
class hubController
{
    private ?PDO $conexion = null;

    /*----------  Modulos con sus metricas resueltas  ----------*/

    public function modulos(): array
    {
        $modulos = ds_modulos();

        foreach ($modulos as $clave => $m) {
            /* 'base' conserva la raiz del modulo: 'url' puede apuntar a una
               vista de entrada concreta, pero los accesos rapidos cuelgan
               siempre de la raiz. */
            $modulos[$clave]['base']     = $m['url'];
            $modulos[$clave]['metricas'] = [];
        }

        /* Metricas de los modulos construidos. Las globales (alumnos de
           toda la escuela, por cobrar, usuarios) sólo para la vista gerencial. */
        if ($this->vistaGerencial()) {
            $modulos['basketball']['metricas'] = $this->metricasBasketball();
            $modulos['core']['metricas']       = $this->metricasCore();
        }
        /* Sin vista gerencial, las tarjetas van sin cifras: las suyas están en
           «Mi día», justo debajo (hub-view.php). */
        $modulos['basketball']['url'] = DS_FUTBOL_URL . $this->entradaBasketball() . '/';

        /* Accesos rápidos: sólo los que el rol puede abrir. Un profesor veía
           «Alumnos» y «Mensualidades», que lo llevaban a «acceso denegado». */
        foreach ($modulos as $clave => $m) {
            $modulos[$clave]['accesos'] = array_values(array_filter($m['accesos'] ?? [],
                static fn($ac) => usuario_tiene_permiso(trim((string)$ac['ruta'], '/'), $clave)));
        }

        /* Un modulo construido solo se muestra si el rol tiene acceso. Los
           que aun no existen se dejan visibles como anticipo: no son
           navegables, asi que no revelan nada. */
        foreach ($modulos as $clave => $m) {
            if ($m['activo'] && !usuario_tiene_modulo($clave)) {
                unset($modulos[$clave]);
            }
        }

        return $modulos;
    }

    private function metricasCore(): array
    {
        return [
            ['valor' => $this->escalar("SELECT COUNT(1) FROM seguridad_usuario WHERE usuario_estado='A'"),
             'etiqueta' => 'Usuarios'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM seguridad_rol WHERE rol_estado='A'"),
             'etiqueta' => 'Roles'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM seguridad_permiso WHERE permiso_estado='A'"),
             'etiqueta' => 'Permisos'],
        ];
    }

    /**
     * Vista a la que entra Basketball segun el rol de la sesion.
     *
     * No todos los roles tienen permiso sobre 'dashboard': enviarlos alli
     * sin mas los dejaria en la pantalla de acceso denegado nada mas pulsar
     * la tarjeta.
     */
    private function entradaBasketball(): string
    {
        if (usuario_tiene_permiso('dashboard')) {
            return 'dashboard';
        }

        $alternativa = primera_vista_permitida();
        return $alternativa !== '' ? $alternativa : 'dashboard';
    }

    private function metricasBasketball(): array
    {
        return [
            ['valor' => $this->escalar("SELECT COUNT(1) FROM sujeto_alumno WHERE alumno_estado='A' AND " . sql_sedes_de_la_sesion('alumno_sedeid') . ""),
             'etiqueta' => 'Alumnos'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM asistencia_horario WHERE horario_estado='A' AND " . sql_sedes_de_la_sesion('horario_sedeid')),
             'etiqueta' => 'Horarios'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM alumno_pago WHERE pago_saldo > 0 AND pago_alumnoid IN (SELECT alumno_id FROM sujeto_alumno WHERE " . sql_sedes_de_la_sesion('alumno_sedeid') . ")"),
             'etiqueta' => 'Por cobrar'],
        ];
    }

    /*----------  Qué ve cada quien  ----------*/

    /**
     * Si la sesión ve la información gerencial: alumnos y sedes de toda la
     * escuela, ingresos, pagos y avisos de cobranza.
     *
     * El mismo criterio que el dashboard de Fútbol (dashboard-view.php):
     * superadministrador o permiso sobre el balance. Se apoya en un permiso
     * y no en el número de rol para que siga valiendo con roles nuevos. Un
     * profesor no lo tiene: ve sólo sus horarios, sus alumnos y la toma de
     * asistencia (miDia).
     */
    public function vistaGerencial(): bool
    {
        return es_superadministrador() || usuario_tiene_permiso('balanceResultados', 'basketball');
    }

    /**
     * El día de quien da clase: sus horarios activos, sus alumnos y cómo va
     * la toma de asistencia. null si la sesión no tiene ficha de empleado.
     *
     * Por horario: días y hora, lugar, alumnos activos, si hoy tiene clase y
     * cuántos están marcados hoy —Tomada / En curso / Pendiente, las mismas
     * reglas que «Registrar asistencia»—. Y del mes: los días con clase,
     * hasta hoy, en que no se marcó a nadie.
     */
    public function miDia(): ?array
    {
        static $cache = false;
        if ($cache !== false) { return $cache; }

        $emp = empleado_actual();
        if ($emp <= 0) { return $cache = null; }

        $hoyIso = (int)date('N');
        $col    = 'asistencia_D' . date('d');           /* un entero de date(), no del usuario */
        $aniomes = (int)date('Ym');

        $horarios = $this->filas(
            "SELECT H.horario_id, H.horario_nombre, H.horario_detalle, S.sede_nombre,
                    GROUP_CONCAT(DISTINCT L.lugar_nombre ORDER BY L.lugar_nombre SEPARATOR ', ') AS lugares,
                    GROUP_CONCAT(DISTINCT D.detalle_dia ORDER BY D.detalle_dia) AS dias,
                    MIN(HR.hora_inicio) AS inicio, MAX(HR.hora_fin) AS fin
               FROM asistencia_horario H
               LEFT JOIN general_sede S ON S.sede_id = H.horario_sedeid
               JOIN asistencia_horario_detalle D ON D.detalle_horarioid = H.horario_id
               LEFT JOIN asistencia_lugar L ON L.lugar_id = D.detalle_lugarid
               LEFT JOIN asistencia_hora HR ON HR.hora_id = D.detalle_horaid
              WHERE H.horario_estado = 'A'
                AND H.horario_id IN (SELECT detalle_horarioid FROM asistencia_horario_detalle WHERE detalle_profesorid = :emp)
              GROUP BY H.horario_id, H.horario_nombre, H.horario_detalle, S.sede_nombre
              ORDER BY inicio, H.horario_nombre",
            [':emp' => $emp]);

        $conteos = [];
        foreach ($this->filas(
            "SELECT AH.asignahorario_horarioid AS horario, COUNT(DISTINCT A.alumno_id) AS alumnos,
                    COUNT(DISTINCT CASE WHEN AA.$col IS NOT NULL AND AA.$col <> '' THEN A.alumno_id END) AS hoy
               FROM asistencia_asignahorario AH
               JOIN sujeto_alumno A ON A.alumno_id = AH.asignahorario_alumnoid AND A.alumno_estado = 'A'
               LEFT JOIN asistencia_asistencia AA ON AA.asistencia_alumnoid = A.alumno_id AND AA.asistencia_aniomes = :am
              WHERE AH.asignahorario_horarioid IN (SELECT detalle_horarioid FROM asistencia_horario_detalle WHERE detalle_profesorid = :emp)
              GROUP BY AH.asignahorario_horarioid",
            [':am' => $aniomes, ':emp' => $emp]) as $c) {
            $conteos[(int)$c['horario']] = $c;
        }

        $dias = []; $clasesHoy = 0; $pendientesHoy = 0;
        foreach ($horarios as $i => $h) {
            $id = (int)$h['horario_id'];
            $n  = (int)($conteos[$id]['alumnos'] ?? 0);
            $k  = (int)($conteos[$id]['hoy'] ?? 0);
            $semana = array_map('intval', array_filter(explode(',', (string)$h['dias']), 'strlen'));
            foreach ($semana as $d) { $dias[$d] = true; }
            $claseHoy = in_array($hoyIso, $semana, true);
            $estado = $n === 0 ? 'sin-alumnos' : ($k >= $n ? 'tomada' : ($k > 0 ? 'curso' : ($claseHoy ? 'pendiente' : 'sin-clase')));
            if ($claseHoy) { $clasesHoy++; }
            if ($estado === 'pendiente' || $estado === 'curso') { $pendientesHoy++; }
            $horarios[$i] += ['alumnos' => $n, 'marcados' => $k, 'clase_hoy' => $claseHoy, 'estado' => $estado, 'semana' => $semana];
        }

        /* Alumnos distintos (uno puede estar en dos de sus horarios). */
        $fila = $this->filas(
            "SELECT COUNT(DISTINCT A.alumno_id) AS n FROM asistencia_asignahorario AH
               JOIN sujeto_alumno A ON A.alumno_id = AH.asignahorario_alumnoid AND A.alumno_estado = 'A'
              WHERE AH.asignahorario_horarioid IN (SELECT detalle_horarioid FROM asistencia_horario_detalle WHERE detalle_profesorid = :emp)",
            [':emp' => $emp]);
        $alumnos = (int)($fila[0]['n'] ?? 0);

        /* Días del mes, hasta ayer, con clase y sin ninguna marca de sus alumnos. */
        $sinRegistro = 0;
        if ($horarios) {
            $sumas = [];
            for ($d = 1; $d <= 31; $d++) { $c = str_pad((string)$d, 2, '0', STR_PAD_LEFT); $sumas[] = "SUM(AA.asistencia_D$c IS NOT NULL AND AA.asistencia_D$c <> '') AS d$c"; }
            $marcas = $this->filas(
                "SELECT " . implode(', ', $sumas) . "
                   FROM asistencia_asistencia AA
                  WHERE AA.asistencia_aniomes = :am
                    AND AA.asistencia_alumnoid IN (SELECT AH.asignahorario_alumnoid FROM asistencia_asignahorario AH
                                                    WHERE AH.asignahorario_horarioid IN (SELECT detalle_horarioid FROM asistencia_horario_detalle WHERE detalle_profesorid = :emp))",
                [':am' => $aniomes, ':emp' => $emp])[0] ?? [];
            for ($d = 1; $d < (int)date('j'); $d++) {
                $semana = (int)date('N', mktime(0, 0, 0, (int)date('n'), $d, (int)date('Y')));
                if (isset($dias[$semana]) && (int)($marcas['d' . str_pad((string)$d, 2, '0', STR_PAD_LEFT)] ?? 0) === 0) { $sinRegistro++; }
            }
        }

        return $cache = ['horarios' => $horarios, 'alumnos' => $alumnos, 'clasesHoy' => $clasesHoy,
                         'pendientesHoy' => $pendientesHoy, 'sinRegistro' => $sinRegistro];
    }

    /*----------  Resumen del ecosistema  ----------*/

    public function resumenHoy(): array
    {
        /* Información gerencial: un profesor no la ve (vistaGerencial). */
        if (!$this->vistaGerencial()) { return []; }

        $ingresosMes = (float)$this->escalar(
            "SELECT IFNULL(SUM(pago_valor),0) FROM alumno_pago
              WHERE YEAR(pago_fecha)=YEAR(CURDATE()) AND MONTH(pago_fecha)=MONTH(CURDATE())
                AND pago_alumnoid IN (SELECT alumno_id FROM sujeto_alumno WHERE " . sql_sedes_de_la_sesion('alumno_sedeid') . ")"
        );

        return [
            ['valor' => '$' . number_format($ingresosMes, 0, ',', '.'), 'etiqueta' => 'Ingresos del mes'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM sujeto_alumno WHERE alumno_estado='A' AND " . sql_sedes_de_la_sesion('alumno_sedeid') . ""), 'etiqueta' => 'Alumnos activos'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM alumno_representante R WHERE EXISTS (SELECT 1 FROM sujeto_alumno WHERE alumno_repreid = R.repre_id AND " . sql_sedes_de_la_sesion('alumno_sedeid') . ")"), 'etiqueta' => 'Representantes'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM general_sede WHERE " . sql_sedes_de_la_sesion('sede_id')), 'etiqueta' => 'Sedes'],
        ];
    }

    /*----------  Actividad reciente  ----------*/

    public function actividadReciente(int $limite = 5): array
    {
        /* Información gerencial: un profesor no la ve (vistaGerencial). */
        if (!$this->vistaGerencial()) { return []; }

        $sql = "SELECT p.pago_valor,
                       CONCAT(a.alumno_primernombre,' ',a.alumno_apellidopaterno) AS alumno
                  FROM alumno_pago   p
                  JOIN sujeto_alumno a ON a.alumno_id = p.pago_alumnoid
                 WHERE " . sql_sedes_de_la_sesion('a.alumno_sedeid') . "
                 ORDER BY p.pago_id DESC
                 LIMIT :limite";

        $items = [];

        foreach ($this->filas($sql, [':limite' => $limite]) as $f) {
            $items[] = [
                'titulo' => 'Pago registrado',
                'meta'   => trim((string)($f['alumno'] ?? '')) . ' · Fútbol',
                'valor'  => '$' . number_format((float)$f['pago_valor'], 2),
                'estado' => 'success',
            ];
        }

        return $items;
    }

    /*----------  Avisos que requieren accion  ----------*/

    public function requiereAtencion(): array
    {
        /* Información gerencial: un profesor no la ve (vistaGerencial). */
        if (!$this->vistaGerencial()) { return []; }

        $avisos = [];

        /* Intentos de acceso fallidos.
           Va primero a proposito: es lo unico de esta lista que puede estar
           ocurriendo AHORA MISMO. El sistema ya anotaba los intentos, pero
           no se lo contaba a nadie; sin este aviso la bitacora solo sirve
           para reconstruir un incidente despues de que pase.

           Solo lo ve el Super Administrador: al resto no le corresponde y
           delataria que cuentas se estan tanteando. */
        if (es_superadministrador()) {
            $intentos = intentos_resumen(24);

            if ($intentos['alarma']) {
                $detalle = $intentos['fallos'] . ' intentos fallidos en 24 h, desde '
                         . $intentos['ips'] . ' ' . ($intentos['ips'] === 1 ? 'dirección' : 'direcciones');

                if ($intentos['inexistentes'] > 0) {
                    $detalle .= ', ' . $intentos['inexistentes'] . ' contra cuentas que no existen';
                }

                $avisos[] = [
                    'titulo' => 'Posible ataque al inicio de sesión',
                    'meta'   => $detalle,
                    'estado' => 'danger',
                    'url'    => DS_HUB_URL . 'ds_core/admin/usuarioList/',
                ];
            }
        }

        $pendientes = (int)$this->escalar("SELECT COUNT(1) FROM alumno_pago WHERE pago_saldo > 0 AND pago_alumnoid IN (SELECT alumno_id FROM sujeto_alumno WHERE " . sql_sedes_de_la_sesion('alumno_sedeid') . ")");
        if ($pendientes > 0) {
            $avisos[] = [
                'titulo' => $pendientes . ' ' . ($pendientes === 1 ? 'pago pendiente' : 'pagos pendientes'),
                'meta'   => 'Fútbol · Cobranza',
                'estado' => 'warning',
                'url'    => DS_FUTBOL_URL . 'pagosPendiente/',
            ];
        }

        $sinHorario = (int)$this->escalar(
            "SELECT COUNT(1) FROM sujeto_alumno a
              WHERE a.alumno_estado='A' AND " . sql_sedes_de_la_sesion('a.alumno_sedeid') . "
                AND NOT EXISTS (SELECT 1 FROM asistencia_asignahorario ah
                                 WHERE ah.asignahorario_alumnoid = a.alumno_id)"
        );
        if ($sinHorario > 0) {
            $avisos[] = [
                'titulo' => $sinHorario . ' alumnos sin horario asignado',
                'meta'   => 'Fútbol · Asistencia',
                'estado' => 'warning',
                'url'    => DS_FUTBOL_URL . 'alumnoList/',
            ];
        }

        $sinConsentimiento = (int)$this->escalar(
            "SELECT COUNT(1) FROM sujeto_alumno a
              WHERE a.alumno_estado='A' AND " . sql_sedes_de_la_sesion('a.alumno_sedeid') . "
                AND NOT EXISTS (SELECT 1 FROM alumno_consentimiento c
                                 WHERE c.consent_alumnoid = a.alumno_id)"
        );
        if ($sinConsentimiento > 0) {
            $avisos[] = [
                'titulo' => $sinConsentimiento . ' alumnos sin consentimiento LOPDP',
                'meta'   => 'Fútbol · Protección de datos',
                'estado' => 'danger',
                'url'    => DS_FUTBOL_URL . 'consentimientoList/',
            ];
        }

        return $avisos;
    }

    /*----------  Acceso a datos  ----------*/

    private function conectar(): ?PDO
    {
        if ($this->conexion !== null) {
            return $this->conexion;
        }

        try {
            $this->conexion = new PDO(
                /* utf8mb4: en MySQL «utf8» es utf8mb3, de tres bytes, y
                   trunca lo que necesite cuatro. Ver migración 041. */
                "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                /* Alinea collation_connection con la base. Ver DS_DB_INIT_COMANDO. */
                defined('DS_DB_INIT_COMANDO')
                    ? [PDO::MYSQL_ATTR_INIT_COMMAND => DS_DB_INIT_COMANDO] : []
            );
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\Throwable $e) {
            $this->conexion = null;
        }

        return $this->conexion;
    }

    /** Primer valor de la primera fila; 0 si la consulta falla. */
    private function escalar(string $sql)
    {
        try {
            $con = $this->conectar();
            if ($con === null) return 0;

            $fila = $con->query($sql)->fetch(PDO::FETCH_NUM);
            return $fila === false ? 0 : $fila[0];
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Todas las filas; [] si la consulta falla. */
    private function filas(string $sql, array $params = []): array
    {
        try {
            $con = $this->conectar();
            if ($con === null) return [];

            $stmt = $con->prepare($sql);
            foreach ($params as $marcador => $valor) {
                $stmt->bindValue($marcador, $valor, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Saludo segun la hora local configurada en ds_core/config/app.php. */
    public function saludo(): string
    {
        $hora = (int)date('G');
        if ($hora < 12) return 'Buenos días';
        if ($hora < 19) return 'Buenas tardes';
        return 'Buenas noches';
    }

    /*======================================================================
      Segundo factor de la propia cuenta

      POR QUÉ ESTO VIVE EN EL HUB Y NO EN CORE

      Se escribió primero en Core, que es donde está la administración de
      usuarios, y ahí estaba mal: sólo el rol 1 tiene concedido el módulo
      core, de modo que los otros cuatro usuarios del sistema no habrían
      podido proteger su propia cuenta. Justo al revés de lo que hace falta.

      El Hub es la única puerta por la que pasa todo el mundo: exige sesión
      y nada más. Proteger la cuenta de uno no es una función
      administrativa.

      Lo que SÍ se queda en Core es restablecer el factor de OTRA persona,
      que sí es administrativo y sólo para el superadministrador.

      NINGUNO DE ESTOS MÉTODOS ACEPTA UN ID DE USUARIO. Todos leen el de la
      sesión. Es lo que impide que alguien configure el segundo factor de
      una cuenta ajena pasando un parámetro.
      ====================================================================*/

    /** Alerta con el formato que espera el front del ecosistema. */
    private function alerta(string $tipo, string $titulo, string $texto,
                            string $icono = 'success'): string
    {
        return json_encode(
            ['tipo' => $tipo, 'titulo' => $titulo, 'texto' => $texto, 'icono' => $icono],
            JSON_UNESCAPED_UNICODE);
    }

    public function prepararSegundoFactor(): string
    {
        $yo = usuario_actual_id();
        if ($yo <= 0) {
            return $this->alerta('simple', 'Sin sesión', 'Vuelva a iniciar sesión.', 'error');
        }

        if (dosf_estado($yo) === 'A') {
            return $this->alerta('simple', 'Ya está activo',
                'Desactívelo primero si quiere vincular otro teléfono.', 'error');
        }

        if (dosf_preparar($yo) === '') {
            return $this->alerta('simple', 'No se pudo preparar',
                'La base de datos rechazó el cambio.', 'error');
        }

        return $this->alerta('recargar', 'Listo para vincular',
            'Escanee el código con su aplicación de verificación.');
    }

    /** Confirma el codigo y activa. Devuelve los codigos de recuperacion. */
    public function activarSegundoFactor(): string
    {
        $yo = usuario_actual_id();
        if ($yo <= 0) {
            return $this->alerta('simple', 'Sin sesión', 'Vuelva a iniciar sesión.', 'error');
        }

        $r = dosf_activar($yo, (string)($_POST['codigo'] ?? ''));

        if (!$r['ok']) {
            return $this->alerta('simple', 'No se pudo activar', $r['motivo'], 'error');
        }

        /* Los códigos viajan UNA vez, aquí. Después sólo existe su hash y
           no hay forma de volver a mostrarlos: sólo de generar otros. */
        $a = json_decode($this->alerta('simple', 'Verificación activada',
                'Guarde los códigos de recuperación: no se volverán a mostrar.'), true);
        $a['codigos'] = $r['codigos'];

        return json_encode($a, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Desactiva el segundo factor.
     *
     * PIDE LA CONTRASEÑA cuando ya está activo. Sin eso, a quien encuentre
     * una sesión abierta le bastaría con desactivarlo para quitarse el
     * estorbo, y el segundo factor dejaría de proteger justo en el caso
     * que debe cubrir.
     */
    public function desactivarSegundoFactor(): string
    {
        $yo = usuario_actual_id();
        if ($yo <= 0) {
            return $this->alerta('simple', 'Sin sesión', 'Vuelva a iniciar sesión.', 'error');
        }

        /* Una configuración a medias ('P') todavía NO protege nada:
           cancelarla no baja la seguridad de nadie. */
        $pendiente = dosf_estado($yo) === 'P';

        if (!$pendiente && !$this->claveCorrecta($yo, (string)($_POST['clave'] ?? ''))) {
            return $this->alerta('simple', 'Contraseña incorrecta',
                'Para desactivar la verificación hay que confirmar la contraseña.', 'error');
        }

        $nota = $pendiente ? 'Configuración cancelada antes de activarse'
                           : 'A petición del propio usuario';

        if (!dosf_desactivar($yo, 'DESACTIVAR', $nota)) {
            return $this->alerta('simple', 'No se pudo desactivar',
                'La base de datos rechazó el cambio.', 'error');
        }

        return $this->alerta('recargar',
            $pendiente ? 'Configuración cancelada' : 'Verificación desactivada',
            $pendiente ? 'Puede empezar de nuevo cuando quiera.'
                       : 'Su cuenta vuelve a entrar sólo con la contraseña.');
    }

    /** Juego nuevo de codigos de recuperacion. */
    public function regenerarCodigosRecuperacion(): string
    {
        $yo = usuario_actual_id();
        if ($yo <= 0) {
            return $this->alerta('simple', 'Sin sesión', 'Vuelva a iniciar sesión.', 'error');
        }

        if (!$this->claveCorrecta($yo, (string)($_POST['clave'] ?? ''))) {
            return $this->alerta('simple', 'Contraseña incorrecta',
                'Confirme su contraseña para generar códigos nuevos.', 'error');
        }

        $codigos = dosf_regenerar_codigos($yo);

        if (!$codigos) {
            return $this->alerta('simple', 'No se pudo generar',
                'La verificación en dos pasos no está activa.', 'error');
        }

        $a = json_decode($this->alerta('simple', 'Códigos nuevos',
                'Los anteriores dejan de valer. Guarde éstos.'), true);
        $a['codigos'] = $codigos;

        return json_encode($a, JSON_UNESCAPED_UNICODE);
    }

    /** ¿Es esta la contrasena del usuario indicado? */
    private function claveCorrecta(int $usuarioid, string $clave): bool
    {
        if ($clave === '') { return false; }

        $con = $this->conectar();
        if ($con === null) { return false; }

        try {
            $st = $con->prepare("SELECT usuario_clave FROM seguridad_usuario
                                  WHERE usuario_id = :id");
            $st->execute([':id' => $usuarioid]);
            $hash = (string)$st->fetchColumn();

            return $hash !== '' && password_verify($clave, $hash);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
