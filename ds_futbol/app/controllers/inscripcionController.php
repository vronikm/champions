<?php

namespace app\controllers;

use app\models\mainModel;
use app\models\TokenHelper;

class inscripcionController extends mainModel
{
    /**
     * Genera el enlace de inscripción para una sede.
     */
    public function generarEnlaceControlador()
    {
        /* Al desplegar es fácil subir el código y dejar el config viejo. Sin
           esta comprobación, TOKEN_SECRET indefinido tumbaba la petición con
           un 500 y el usuario solo veía "No se pudo generar el enlace". */
        $faltante = TokenHelper::configFaltante();

        if (count($faltante) > 0) {
            error_log("[inscripcionEnlace] Faltan constantes en config/app.php: " . implode(', ', $faltante));

            return json_encode([
                'tipo'   => 'simple',
                'titulo' => 'Configuración incompleta',
                'texto'  => 'Falta definir en config/app.php: ' . implode(', ', $faltante)
                          . '. Avise al administrador del sistema.',
                'icono'  => 'error'
            ], JSON_UNESCAPED_UNICODE);
        }

        $sede_id = intval($this->limpiarCadena($_POST['sede_id'] ?? '0'));
        $horas   = intval($this->limpiarCadena($_POST['horas_vigencia'] ?? '72'));

        if ($sede_id <= 0) {
            return json_encode([
                'tipo'   => 'simple',
                'titulo' => 'Error',
                'texto'  => 'Debe seleccionar una sede.',
                'icono'  => 'error'
            ]);
        }

        if ($horas < 1 || $horas > 720) {
            $horas = 72;
        }

        $expiraEnSegundos = $horas * 3600;

        /* El usuario solo puede generar enlaces de las sedes que tiene asignadas.
           Se valida contra la BD y no contra el <select>, que es manipulable. */
        $sedeNombre = $this->obtenerSedePermitida($sede_id);

        if ($sedeNombre === null) {
            return json_encode([
                'tipo'   => 'simple',
                'titulo' => 'Sede no permitida',
                'texto'  => 'La sede seleccionada no existe, está inactiva o no está asignada a su usuario.',
                'icono'  => 'error'
            ]);
        }

        $urlFormulario  = TokenHelper::generarURL($sede_id, $expiraEnSegundos);
        $enlaceWhatsApp = TokenHelper::generarEnlaceWhatsApp($sede_id, $sedeNombre, $expiraEnSegundos);

        return json_encode([
            'tipo'            => 'enlace',
            'titulo'          => 'Enlace generado',
            'texto'           => 'El enlace de inscripción para la sede ' . $sedeNombre . ' ha sido generado.',
            'icono'           => 'success',
            'url_formulario'  => $urlFormulario,
            'enlace_whatsapp' => $enlaceWhatsApp,
            'sede_nombre'     => $sedeNombre,
            'vigencia_horas'  => $horas
        ]);
    }

    /**
     * Enlace de inscripción para alumnos adultos (migración 086).
     *
     * Método aparte, no una opción del anterior: el enlace de menores sigue
     * exactamente igual. Sólo para sedes activas, de la sesión y marcadas en
     * Core › Sedes como «Admite alumnos adultos».
     */
    public function generarEnlaceAdultoControlador()
    {
        $faltante = TokenHelper::configFaltante();
        if (count($faltante) > 0) {
            error_log("[inscripcionAdultoEnlace] Faltan constantes en config/app.php: " . implode(', ', $faltante));
            return json_encode([
                'tipo'   => 'simple',
                'titulo' => 'Configuración incompleta',
                'texto'  => 'Falta definir en config/app.php: ' . implode(', ', $faltante) . '. Avise al administrador del sistema.',
                'icono'  => 'error'
            ], JSON_UNESCAPED_UNICODE);
        }

        $sede_id = intval($_POST['sede_id'] ?? 0);
        $horas   = intval($_POST['horas_vigencia'] ?? 72);
        if ($horas < 1 || $horas > 720) {
            $horas = 72;
        }

        $sedeNombre = null;
        foreach ($this->listarSedesAdultos() as $s) {
            if ((int)$s['sede_id'] === $sede_id) {
                $sedeNombre = $s['sede_nombre'];
            }
        }
        if ($sedeNombre === null) {
            return json_encode([
                'tipo'   => 'simple',
                'titulo' => 'Sede no permitida',
                'texto'  => 'La sede no existe, está inactiva, no está asignada a su usuario o no admite alumnos adultos (Core › Sedes).',
                'icono'  => 'error'
            ], JSON_UNESCAPED_UNICODE);
        }

        $segundos = $horas * 3600;
        $url = TokenHelper::generarURLAdulto($sede_id, $segundos);

        return json_encode([
            'tipo'            => 'enlace',
            'titulo'          => 'Enlace generado',
            'texto'           => 'El enlace de inscripción para alumnos adultos de la sede ' . $sedeNombre . ' ha sido generado.',
            'icono'           => 'success',
            'url_formulario'  => $url,
            'enlace_whatsapp' => TokenHelper::generarEnlaceWhatsAppAdulto($url, $sedeNombre, $segundos),
            'sede_nombre'     => $sedeNombre,
            'vigencia_horas'  => $horas
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sedes de la sesión, activas y que admiten alumnos adultos (086).
     * Sin la migración, ninguna.
     */
    public function listarSedesAdultos(): array
    {
        $hay = (int)$this->ejecutarConsulta(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'general_sede' AND COLUMN_NAME = 'sede_adultos'"
        )->fetchColumn();
        if ($hay === 0) {
            return [];
        }

        return $this->ejecutarConsulta(
            "SELECT sede_id, sede_nombre FROM general_sede
              WHERE sede_adultos = 'S' AND " . sql_sedes_de_la_sesion('sede_id') . " AND " . sql_sede_activa('sede_id') . "
              ORDER BY sede_nombre"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Lista las sedes que el usuario en sesión puede usar para generar enlaces.
     * Los roles administrativos (1 y 2) ven todas; el resto solo las asignadas.
     */
    /* $soloActivas: el enlace de inscripción sólo para sedes activas
       (migración 078); las inscripciones pendientes, de todas. */
    public function listarSedesActivas(bool $soloActivas = true)
    {
        $rolid   = $_SESSION['rol'] ?? null;
        $usuario = $_SESSION['usuario'] ?? '';

        /* Las sedes de la sesión: sedes_de_la_sesion() (ds_core/inc/seguridad.php). */
        $consulta = $this->ejecutarConsulta(
            "SELECT sede_id, sede_nombre FROM general_sede WHERE " . sql_sedes_de_la_sesion('sede_id') . ($soloActivas ? " AND " . sql_sede_activa('sede_id') : '') . " ORDER BY sede_nombre"
        );

        return $consulta->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Alumnos que entraron por el enlace de inscripción y a los que todavía
     * les falta algo para quedar operativos en el sistema.
     *
     * El formulario público solo graba los datos personales: no asigna horario,
     * no registra el rubro de inscripción, no pide contacto de emergencia y
     * la foto es opcional. Sin esta pantalla esos alumnos quedan invisibles
     * entre el resto del listado y nadie los completa.
     *
     * Se identifican por su consentimiento de origen FORMULARIO, que es la
     * única huella que deja el formulario público. Los alumnos inscritos en
     * línea ANTES de que existiera alumno_consentimiento no aparecen acá.
     */
    /**
     * Cuantas inscripciones en linea estan a medias.
     *
     * Se apoya en listarPendientesInscripcion y cuenta sus filas, en lugar de
     * escribir un COUNT aparte. Un COUNT propio se desincronizaria el dia que
     * alguien cambie que significa «pendiente», y entonces el numerito del
     * menu diria una cosa y la pantalla otra. Dos cifras distintas para lo
     * mismo son peores que ninguna.
     *
     * La cola de inscripciones sin terminar es corta por naturaleza —es una
     * lista de tareas—, asi que traer las filas para contarlas no pesa. Medido:
     * 2,4 ms.
     */
    public function contarPendientesInscripcion(): int
    {
        return count($this->listarPendientesInscripcion());
    }

    /* Ya no recibe una búsqueda: la caja de la tabla filtra al vuelo. */
    public function listarPendientesInscripcion($sedeid = '')
    {
        $interna = "SELECT a.alumno_id,
                           a.alumno_identificacion,
                           CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno, a.alumno_apellidomaterno) AS alumno,
                           TIMESTAMPDIFF(YEAR, a.alumno_fechanacimiento, CURDATE()) AS edad,
                           s.sede_nombre,
                           CONCAT_WS(' ', r.repre_primernombre, r.repre_apellidopaterno) AS representante,
                           r.repre_celular,
                           o.fecha_online,
                           DATEDIFF(CURDATE(), DATE(o.fecha_online)) AS dias_espera,
                           (SELECT COUNT(*) FROM asistencia_asignahorario ah
                             WHERE ah.asignahorario_alumnoid = a.alumno_id) AS tiene_horario,
                           (SELECT COUNT(*) FROM alumno_cemergencia ce
                             WHERE ce.cemer_alumnoid = a.alumno_id) AS tiene_emergencia,
                           (SELECT COUNT(*) FROM alumno_pago p
                             WHERE p.pago_alumnoid = a.alumno_id
                               AND p.pago_rubroid = 'RIN'
                               AND p.pago_estado <> 'E') AS tiene_inscripcion,
                           (SELECT MAX(p2.pago_estado) FROM alumno_pago p2
                             WHERE p2.pago_alumnoid = a.alumno_id
                               AND p2.pago_rubroid = 'RIN'
                               AND p2.pago_estado <> 'E') AS estado_inscripcion,
                           CASE WHEN a.alumno_imagen IS NULL OR a.alumno_imagen = '' THEN 1 ELSE 0 END AS sin_foto
                      FROM sujeto_alumno a
                      INNER JOIN alumno_representante r ON r.repre_id = a.alumno_repreid
                      INNER JOIN general_sede s ON s.sede_id = a.alumno_sedeid
                      INNER JOIN (SELECT consent_alumnoid, MIN(consent_fecha) AS fecha_online
                                    FROM alumno_consentimiento
                                   WHERE consent_origen = 'FORMULARIO'
                                   GROUP BY consent_alumnoid) o ON o.consent_alumnoid = a.alumno_id
                     WHERE a.alumno_estado = 'A'";

        $parametros = [];

        // Los roles no administrativos solo ven sus sedes asignadas
        $rolid   = $_SESSION['rol'] ?? null;
        $usuario = $_SESSION['usuario'] ?? '';

        /* Las sedes de la sesión: sedes_de_la_sesion() (ds_core/inc/seguridad.php). */
			$interna .= " AND " . sql_sedes_de_la_sesion('a.alumno_sedeid');

        if ($sedeid !== '' && intval($sedeid) > 0) {
            $interna .= " AND a.alumno_sedeid = :sede";
            $parametros[':sede'] = intval($sedeid);
        }

        /* El filtro "le falta algo" va en una consulta externa: los contadores
           son subconsultas escalares y no se pueden reusar en el WHERE de su
           propio SELECT. */
        $consulta = "SELECT * FROM ($interna) X
                      WHERE X.tiene_horario = 0
                         OR X.tiene_inscripcion = 0
                         OR X.tiene_emergencia = 0
                         OR X.sin_foto = 1
                      ORDER BY X.fecha_online DESC";

        return $this->ejecutarConsulta($consulta, $parametros)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve el nombre de la sede si el usuario en sesión tiene acceso a ella,
     * o null si no existe o no le corresponde.
     */
    private function obtenerSedePermitida($sede_id)
    {
        $rolid   = $_SESSION['rol'] ?? null;
        $usuario = $_SESSION['usuario'] ?? '';

        $consulta = $this->ejecutarConsulta(
            "SELECT sede_nombre FROM general_sede WHERE sede_id = :sede AND " . sql_sedes_de_la_sesion('sede_id') . " AND " . sql_sede_activa('sede_id'),   /* una sede inactiva no recibe inscripciones */
            [":sede" => $sede_id]
        );

        $sede = $consulta->fetch(\PDO::FETCH_ASSOC);

        return $sede ? $sede['sede_nombre'] : null;
    }
}
